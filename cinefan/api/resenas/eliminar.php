<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// Validar método
Response::validateMethod(['DELETE', 'POST']);

try {
    // Autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    Utils::log("Usuario autenticado: ID={$userId}", 'INFO');
    
    // OBTENER ID DE RESEÑA - MÉTODO SÚPER ROBUSTO
    $resenaId = null;
    
    Utils::log("Método HTTP: " . $_SERVER['REQUEST_METHOD'], 'DEBUG');
    Utils::log("GET params: " . json_encode($_GET), 'DEBUG');
    Utils::log("POST params: " . json_encode($_POST), 'DEBUG');
    
    // Método 1: Parámetro GET (?id=X)
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $resenaId = (int)$_GET['id'];
        Utils::log("ID obtenido desde GET: {$resenaId}", 'DEBUG');
    }
    // Método 2: Parámetro POST
    elseif (isset($_POST['id']) && !empty($_POST['id'])) {
        $resenaId = (int)$_POST['id'];
        Utils::log("ID obtenido desde POST: {$resenaId}", 'DEBUG');
    }
    // Método 3: PATH_INFO (/eliminar.php/123)
    elseif (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
        $pathParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
        Utils::log("PATH_INFO parts: " . json_encode($pathParts), 'DEBUG');
        if (!empty($pathParts[0]) && is_numeric($pathParts[0])) {
            $resenaId = (int)$pathParts[0];
            Utils::log("ID obtenido desde PATH_INFO: {$resenaId}", 'DEBUG');
        }
    }
    // Método 4: JSON body
    else {
        $rawInput = file_get_contents('php://input');
        Utils::log("Raw input: " . ($rawInput ?: 'vacío'), 'DEBUG');
        
        if (!empty($rawInput)) {
            $data = json_decode($rawInput, true);
            Utils::log("JSON decoded: " . json_encode($data), 'DEBUG');
            
            if (json_last_error() === JSON_ERROR_NONE && isset($data['id']) && !empty($data['id'])) {
                $resenaId = (int)$data['id'];
                Utils::log("ID obtenido desde JSON body: {$resenaId}", 'DEBUG');
            } else {
                Utils::log("JSON decode error: " . json_last_error_msg(), 'ERROR');
            }
        }
    }
    
    // VALIDACIÓN ROBUSTA DEL ID
    if (!$resenaId || $resenaId <= 0) {
        $debugInfo = [
            'method' => $_SERVER['REQUEST_METHOD'],
            'get' => $_GET,
            'post' => $_POST,
            'path_info' => $_SERVER['PATH_INFO'] ?? null,
            'raw_body' => file_get_contents('php://input'),
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
            'content_length' => $_SERVER['CONTENT_LENGTH'] ?? null
        ];
        
        Utils::log("ERROR: ID de reseña no válido. Debug: " . json_encode($debugInfo), 'ERROR');
        Response::error('ID de reseña requerido. Recibido: ' . ($resenaId ?? 'null') . '. Revisa que estés enviando el parámetro "id" correctamente.', 400);
    }
    
    Utils::log("Procesando eliminación de reseña ID: {$resenaId} por usuario: {$userId}", 'INFO');
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // VERIFICAR QUE LA RESEÑA EXISTE Y PERTENECE AL USUARIO
    Utils::log("Verificando existencia y propiedad de reseña...", 'DEBUG');
    
    // Verificar si las columnas necesarias existen
    $hasDateColumn = Utils::columnExists('resenas', 'fecha_eliminacion', $conn);
    Utils::log("Tabla resenas tiene columna fecha_eliminacion: " . ($hasDateColumn ? 'SÍ' : 'NO'), 'DEBUG');
    
    $checkSql = "SELECT r.id, r.id_usuario, r.activo, p.titulo as pelicula_titulo
                 FROM resenas r
                 INNER JOIN peliculas p ON r.id_pelicula = p.id
                 WHERE r.id = ? AND r.activo = 1";
                 
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$resenaId]);
    $resena = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    Utils::log("Resultado consulta reseña: " . json_encode($resena), 'DEBUG');
    
    if (!$resena) {
        Utils::log("ERROR: Reseña no encontrada. ID: {$resenaId}", 'ERROR');
        Response::error('Reseña no encontrada o ya eliminada', 404);
    }
    
    // VERIFICAR PROPIEDAD DE LA RESEÑA
    if ($resena['id_usuario'] != $userId) {
        Utils::log("ERROR: Usuario {$userId} intentó eliminar reseña {$resenaId} que pertenece al usuario {$resena['id_usuario']}", 'ERROR');
        Response::error('No tienes permisos para eliminar esta reseña', 403);
    }
    
    Utils::log("Reseña verificada. Película: {$resena['pelicula_titulo']}", 'INFO');
    
    // ELIMINAR LÓGICAMENTE (SOFT DELETE) - ADAPTATIVO
    Utils::log("Ejecutando soft delete...", 'DEBUG');
    
    if ($hasDateColumn) {
        $deleteSql = "UPDATE resenas SET activo = 0, fecha_eliminacion = NOW() WHERE id = ? AND activo = 1";
    } else {
        $deleteSql = "UPDATE resenas SET activo = 0 WHERE id = ? AND activo = 1";
        Utils::log("Usando SQL sin fecha_eliminacion (columna no existe)", 'WARNING');
    }
    
    $deleteStmt = $conn->prepare($deleteSql);
    
    Utils::log("SQL delete: " . $deleteSql, 'DEBUG');
    Utils::log("Parámetro: {$resenaId}", 'DEBUG');
    
    if ($deleteStmt->execute([$resenaId])) {
        $filasAfectadas = $deleteStmt->rowCount();
        Utils::log("Delete ejecutado. Filas afectadas: {$filasAfectadas}", 'DEBUG');
        
        if ($filasAfectadas > 0) {
            Utils::log("Usuario {$userId} eliminó reseña {$resenaId} de película: {$resena['pelicula_titulo']}", 'INFO');
            
            // Verificar que realmente se eliminó
            $verifyStmt = $conn->prepare("SELECT activo FROM resenas WHERE id = ?");
            $verifyStmt->execute([$resenaId]);
            $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            Utils::log("Verificación eliminación: " . json_encode($verifyResult), 'DEBUG');
            
            Response::success([
                'id' => (int)$resenaId,
                'pelicula_titulo' => $resena['pelicula_titulo'],
                'filas_afectadas' => $filasAfectadas,
                'usuario_id' => (int)$userId
            ], 'Reseña eliminada exitosamente');
        } else {
            Utils::log("ERROR: No se eliminó ninguna fila para reseña {$resenaId}", 'ERROR');
            Response::error('No se pudo eliminar la reseña - posiblemente ya estaba eliminada', 500);
        }
    } else {
        $errorInfo = $deleteStmt->errorInfo();
        Utils::log("ERROR ejecutando DELETE para reseña {$resenaId}: " . json_encode($errorInfo), 'ERROR');
        Response::error('Error al eliminar la reseña: ' . $errorInfo[2], 500);
    }
    
} catch (Exception $e) {
    Utils::log("EXCEPCIÓN en eliminar reseña: " . $e->getMessage(), 'ERROR');
    Utils::log("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    Response::error('Error interno del servidor: ' . $e->getMessage(), 500);
}
?>