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
    Utils::log("Usuario autenticado: ID={$userId}", 'INFO');
    
    // OBTENER ID DE PELÍCULA CON DEBUG
    $peliculaId = null;
    
    Utils::log("Método HTTP: " . $_SERVER['REQUEST_METHOD'], 'DEBUG');
    Utils::log("GET params: " . json_encode($_GET), 'DEBUG');
    
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $peliculaId = (int)$_GET['id'];
        Utils::log("ID obtenido desde GET: {$peliculaId}", 'DEBUG');
    }
    
    if (!$peliculaId || $peliculaId <= 0) {
        Utils::log("ERROR: ID de película no válido: {$peliculaId}", 'ERROR');
        Response::error('ID de película requerido y debe ser mayor a 0', 400);
    }
    
    Utils::log("Procesando eliminación de película ID: {$peliculaId} por usuario: {$userId}", 'INFO');
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // VERIFICAR QUE LA PELÍCULA EXISTE Y PERTENECE AL USUARIO CON DEBUG
    Utils::log("Verificando existencia y propiedad de película...", 'DEBUG');
    $checkSql = "SELECT id, titulo, id_usuario_creador FROM peliculas 
               WHERE id = ? AND activo = 1";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$peliculaId]);
    $pelicula = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    Utils::log("Resultado consulta película: " . json_encode($pelicula), 'DEBUG');
    
    if (!$pelicula) {
        Utils::log("ERROR: Película no encontrada. ID: {$peliculaId}", 'ERROR');
        Response::error('Película no encontrada', 404);
    }
    
    // Verificar propiedad
    if ($pelicula['id_usuario_creador'] != $userId) {
        Utils::log("ERROR: Usuario {$userId} no es propietario de película {$peliculaId} (owner: {$pelicula['id_usuario_creador']})", 'ERROR');
        Response::error('No tienes permisos para eliminar esta película', 403);
    }
    
    Utils::log("Película verificada. Título: {$pelicula['titulo']}", 'INFO');
    
    // ELIMINAR LÓGICAMENTE (SOFT DELETE) CON DEBUG
    Utils::log("Ejecutando soft delete...", 'DEBUG');
    $deleteSql = "UPDATE peliculas SET activo = 0 WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    
    Utils::log("SQL delete: " . $deleteSql, 'DEBUG');
    Utils::log("Parámetro: {$peliculaId}", 'DEBUG');
    
    if ($deleteStmt->execute([$peliculaId])) {
        $filasAfectadas = $deleteStmt->rowCount();
        Utils::log("Delete ejecutado. Filas afectadas: {$filasAfectadas}", 'DEBUG');
        
        if ($filasAfectadas > 0) {
            Utils::log("Usuario {$userId} eliminó película: {$pelicula['titulo']} (ID: {$peliculaId})", 'INFO');
            
            // Verificar que realmente se eliminó
            $verifyStmt = $conn->prepare("SELECT activo FROM peliculas WHERE id = ?");
            $verifyStmt->execute([$peliculaId]);
            $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            Utils::log("Verificación eliminación: " . json_encode($verifyResult), 'DEBUG');
            
            Response::success([
                'id' => (int)$peliculaId,
                'titulo' => $pelicula['titulo'],
                'filas_afectadas' => $filasAfectadas
            ], 'Película eliminada exitosamente');
        } else {
            Utils::log("ERROR: No se eliminó ninguna fila para película {$peliculaId}", 'ERROR');
            Response::error('No se pudo eliminar la película - posiblemente ya estaba eliminada', 500);
        }
    } else {
        $errorInfo = $deleteStmt->errorInfo();
        Utils::log("ERROR ejecutando DELETE para película {$peliculaId}: " . json_encode($errorInfo), 'ERROR');
        Response::error('Error al eliminar la película: ' . $errorInfo[2], 500);
    }
    
} catch (Exception $e) {
    Utils::log("EXCEPCIÓN en eliminar película: " . $e->getMessage(), 'ERROR');
    Utils::log("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    Response::error('Error interno del servidor: ' . $e->getMessage(), 500);
}
?>