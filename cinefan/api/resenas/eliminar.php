<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// Validar método
Response::validateMethod(['DELETE']);

try {
    // Autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    // OBTENER ID DE RESEÑA - MÉTODO MEJORADO
    $resenaId = null;
    
    // Método 1: Desde parámetros URL (?id=X)
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $resenaId = (int)$_GET['id'];
        Utils::log("ID obtenido desde URL: " . $resenaId, 'DEBUG');
    }
    // Método 2: Desde URL path (/eliminar.php/123)
    elseif (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
        $pathParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
        if (!empty($pathParts[0]) && is_numeric($pathParts[0])) {
            $resenaId = (int)$pathParts[0];
            Utils::log("ID obtenido desde PATH_INFO: " . $resenaId, 'DEBUG');
        }
    }
    // Método 3: Desde JSON body
    else {
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $data = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['id']) && !empty($data['id'])) {
                $resenaId = (int)$data['id'];
                Utils::log("ID obtenido desde JSON body: " . $resenaId, 'DEBUG');
            }
        }
    }
    
    // VERIFICAR QUE TENEMOS UN ID VÁLIDO
    if (!$resenaId || $resenaId <= 0) {
        Utils::log("Error: ID de reseña no válido. GET: " . json_encode($_GET) . ", POST: " . file_get_contents('php://input'), 'ERROR');
        Response::error('ID de reseña requerido y debe ser un número válido', 400);
    }
    
    Utils::log("Intentando eliminar reseña ID: {$resenaId} por usuario: {$userId}", 'INFO');
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // VERIFICAR QUE LA RESEÑA EXISTE Y PERTENECE AL USUARIO
    $checkSql = "SELECT r.id, r.id_usuario, p.titulo as pelicula_titulo
                 FROM resenas r
                 INNER JOIN peliculas p ON r.id_pelicula = p.id
                 WHERE r.id = ? AND r.activo = 1";
                 
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$resenaId]);
    $resena = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resena) {
        Utils::log("Reseña no encontrada. ID: {$resenaId}", 'ERROR');
        Response::error('Reseña no encontrada', 404);
    }
    
    // VERIFICAR PROPIEDAD DE LA RESEÑA
    if ($resena['id_usuario'] != $userId) {
        Utils::log("Usuario {$userId} intentó eliminar reseña {$resenaId} que pertenece al usuario {$resena['id_usuario']}", 'ERROR');
        Response::error('No tienes permisos para eliminar esta reseña', 403);
    }
    
    // ELIMINAR LÓGICAMENTE (SOFT DELETE)
    $deleteSql = "UPDATE resenas SET activo = 0, fecha_eliminacion = NOW() WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    
    if ($deleteStmt->execute([$resenaId])) {
        // Verificar que se actualizó al menos una fila
        if ($deleteStmt->rowCount() > 0) {
            Utils::log("Usuario {$userId} eliminó reseña {$resenaId} de película: {$resena['pelicula_titulo']}", 'INFO');
            Response::success([
                'id' => (int)$resenaId,
                'pelicula_titulo' => $resena['pelicula_titulo']
            ], 'Reseña eliminada exitosamente');
        } else {
            Utils::log("No se pudo eliminar la reseña {$resenaId} - ninguna fila afectada", 'ERROR');
            Response::error('No se pudo eliminar la reseña', 500);
        }
    } else {
        Utils::log("Error ejecutando DELETE para reseña {$resenaId}", 'ERROR');
        Response::error('Error al eliminar la reseña', 500);
    }
    
} catch (Exception $e) {
    Utils::log("Error en eliminar reseña: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}
?>