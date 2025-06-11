<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// validar método
Response::validateMethod(['POST', 'DELETE']);

try {
    // autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    // obtener ID de la reseña
    $resenaId = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = Response::getJsonInput();
        Response::validateRequired($input, ['id_resena']);
        $resenaId = (int)$input['id_resena'];
    } else {
        $resenaId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    }
    
    if (!$resenaId) {
        Response::error('ID de reseña requerido', 400);
    }
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // verificar que la reseña existe
    $checkSql = "SELECT id, id_usuario FROM resenas WHERE id = :id AND activo = true";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bindParam(':id', $resenaId);
    $checkStmt->execute();
    
    $resena = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$resena) {
        Response::error('Reseña no encontrada', 404);
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // AGREGAR LIKE
        
        // no permitir dar like a reseña propia
        if ($resena['id_usuario'] == $userId) {
            Response::error('No puedes dar like a tu propia reseña', 400);
        }
        
        // verificar si ya dio like
        $checkLikeSql = "SELECT id FROM likes_resenas WHERE id_usuario = :user_id AND id_resena = :resena_id";
        $checkLikeStmt = $conn->prepare($checkLikeSql);
        $checkLikeStmt->bindParam(':user_id', $userId);
        $checkLikeStmt->bindParam(':resena_id', $resenaId);
        $checkLikeStmt->execute();
        
        if ($checkLikeStmt->fetch()) {
            Response::error('Ya has dado like a esta reseña', 409);
        }
        
        // agregar like
        $likeSql = "INSERT INTO likes_resenas (id_usuario, id_resena) VALUES (:user_id, :resena_id)";
        $likeStmt = $conn->prepare($likeSql);
        $likeStmt->bindParam(':user_id', $userId);
        $likeStmt->bindParam(':resena_id', $resenaId);
        
        if ($likeStmt->execute()) {
            // actualizar contador
            $updateSql = "UPDATE resenas SET likes = (SELECT COUNT(*) FROM likes_resenas WHERE id_resena = :resena_id) WHERE id = :resena_id";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bindParam(':resena_id', $resenaId);
            $updateStmt->execute();
            
            Utils::log("Usuario {$userId} dio like a reseña {$resenaId}", 'INFO');
            Response::success(null, 'Like agregado exitosamente');
        } else {
            Response::error('Error al agregar like', 500);
        }
        
    } else {
        // QUITAR LIKE (DELETE)
        
        // verificar si el usuario tiene like en esta reseña
        $checkLikeSql = "SELECT id FROM likes_resenas WHERE id_usuario = :user_id AND id_resena = :resena_id";
        $checkLikeStmt = $conn->prepare($checkLikeSql);
        $checkLikeStmt->bindParam(':user_id', $userId);
        $checkLikeStmt->bindParam(':resena_id', $resenaId);
        $checkLikeStmt->execute();
        
        if (!$checkLikeStmt->fetch()) {
            Response::error('No has dado like a esta reseña', 404);
        }
        
        // quitar like
        $unlikeSql = "DELETE FROM likes_resenas WHERE id_usuario = :user_id AND id_resena = :resena_id";
        $unlikeStmt = $conn->prepare($unlikeSql);
        $unlikeStmt->bindParam(':user_id', $userId);
        $unlikeStmt->bindParam(':resena_id', $resenaId);
        
        if ($unlikeStmt->execute()) {
            // actualizar contador
            $updateSql = "UPDATE resenas SET likes = (SELECT COUNT(*) FROM likes_resenas WHERE id_resena = :resena_id) WHERE id = :resena_id";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bindParam(':resena_id', $resenaId);
            $updateStmt->execute();
            
            Utils::log("Usuario {$userId} quitó like de reseña {$resenaId}", 'INFO');
            Response::success(null, 'Like removido exitosamente');
        } else {
            Response::error('Error al remover like', 500);
        }
    }
    
} catch (Exception $e) {
    Utils::log("Error en gestión de likes: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}