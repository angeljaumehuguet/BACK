<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// validar método
Response::validateMethod(['DELETE']);

try {
    // autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    // obtener ID de la reseña
    $resenaId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$resenaId) {
        Response::error('ID de reseña requerido', 400);
    }
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // verificar que la reseña existe y pertenece al usuario
    $checkSql = "SELECT r.id, p.titulo as pelicula_titulo
                 FROM resenas r
                 INNER JOIN peliculas p ON r.id_pelicula = p.id
                 WHERE r.id = :id AND r.id_usuario = :user_id AND r.activo = true";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bindParam(':id', $resenaId);
    $checkStmt->bindParam(':user_id', $userId);
    $checkStmt->execute();
    
    $resena = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$resena) {
        Response::error('Reseña no encontrada o sin permisos', 404);
    }
    
    // eliminar lógicamente (soft delete)
    $deleteSql = "UPDATE resenas SET activo = false WHERE id = :id";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bindParam(':id', $resenaId);
    
    if ($deleteStmt->execute()) {
        Utils::log("Usuario {$userId} eliminó reseña {$resenaId} de película: {$resena['pelicula_titulo']}", 'INFO');
        Response::success(null, 'Reseña eliminada exitosamente');
    } else {
        Response::error('Error al eliminar la reseña', 500);
    }
    
} catch (Exception $e) {
    Utils::log("Error en eliminar reseña: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}