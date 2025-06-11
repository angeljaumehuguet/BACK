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
    
    // obtener ID de la película
    $peliculaId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$peliculaId) {
        Response::error('ID de película requerido', 400);
    }
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // verificar que la película existe y pertenece al usuario
    $checkSql = "SELECT id, titulo FROM peliculas 
               WHERE id = :id AND id_usuario_creador = :user_id AND activo = true";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bindParam(':id', $peliculaId);
    $checkStmt->bindParam(':user_id', $userId);
    $checkStmt->execute();
    
    $pelicula = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$pelicula) {
        Response::error('Película no encontrada o sin permisos', 404);
    }
    
    // eliminar lógicamente (soft delete)
    $deleteSql = "UPDATE peliculas SET activo = false WHERE id = :id";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bindParam(':id', $peliculaId);
    
    if ($deleteStmt->execute()) {
        Utils::log("Usuario {$userId} eliminó película: {$pelicula['titulo']} (ID: {$peliculaId})", 'INFO');
        Response::success(null, 'Película eliminada exitosamente');
    } else {
        Response::error('Error al eliminar la película', 500);
    }
    
} catch (Exception $e) {
    Utils::log("Error en eliminar película: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}