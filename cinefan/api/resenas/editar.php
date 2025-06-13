<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// Validar método
Response::validateMethod(['PUT', 'PATCH']);

try {
    // Autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    // Obtener datos de entrada
    $input = Response::getJsonInput();
    
    if (empty($input)) {
        Response::error('Datos requeridos para actualizar', 400);
    }
    
    // Obtener ID de la reseña (desde URL o desde JSON body)
    $resenaId = null;
    
    // Primero intentar desde URL
    if (isset($_GET['id'])) {
        $resenaId = (int)$_GET['id'];
    }
    // Si no está en URL, intentar desde JSON body
    elseif (isset($input['id'])) {
        $resenaId = (int)$input['id'];
    }
    
    if (!$resenaId) {
        Response::error('ID de reseña requerido', 400);
    }
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // Verificar que la reseña existe y pertenece al usuario
    $checkSql = "SELECT r.id, r.puntuacion, r.texto_resena, p.titulo as pelicula_titulo
                 FROM resenas r
                 INNER JOIN peliculas p ON r.id_pelicula = p.id
                 WHERE r.id = :id AND r.id_usuario = :user_id AND r.activo = true";
    
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bindParam(':id', $resenaId);
    $checkStmt->bindParam(':user_id', $userId);
    $checkStmt->execute();
    
    $resenaExistente = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resenaExistente) {
        Response::error('Reseña no encontrada o sin permisos', 404);
    }
    
    // Validar campos a actualizar
    $camposActualizar = [];
    $parametros = [':id' => $resenaId];
    
    // Validar puntuación
    if (isset($input['puntuacion'])) {
        $puntuacion = (int)$input['puntuacion'];
        if ($puntuacion < 1 || $puntuacion > 5) {
            Response::error('La puntuación debe estar entre 1 y 5', 400);
        }
        $camposActualizar[] = "puntuacion = :puntuacion";
        $parametros[':puntuacion'] = $puntuacion;
    }
    
    // Validar texto de reseña
    if (isset($input['texto_resena'])) {
        $textoResena = trim($input['texto_resena']);
        if (empty($textoResena)) {
            Response::error('El contenido de la reseña no puede estar vacío', 400);
        }
        if (strlen($textoResena) < 10) {
            Response::error('La reseña debe tener al menos 10 caracteres', 400);
        }
        if (strlen($textoResena) > 1000) {
            Response::error('La reseña no puede exceder 1000 caracteres', 400);
        }
        $camposActualizar[] = "texto_resena = :texto_resena";
        $parametros[':texto_resena'] = $textoResena;
    }
    
    if (empty($camposActualizar)) {
        Response::error('No hay datos válidos para actualizar', 400);
    }
    
    // Agregar fecha de modificación
    $camposActualizar[] = "fecha_modificacion = NOW()";
    
    // Construir y ejecutar consulta de actualización
    $updateSql = "UPDATE resenas 
                  SET " . implode(', ', $camposActualizar) . "
                  WHERE id = :id";
    
    $updateStmt = $conn->prepare($updateSql);
    
    foreach ($parametros as $param => $value) {
        $updateStmt->bindValue($param, $value);
    }
    
    if ($updateStmt->execute()) {
        // Obtener la reseña actualizada
        $selectSql = "SELECT r.id, r.puntuacion, r.texto_resena, r.fecha_creacion, r.fecha_modificacion,
                             p.titulo as pelicula_titulo, p.id as pelicula_id
                      FROM resenas r
                      INNER JOIN peliculas p ON r.id_pelicula = p.id
                      WHERE r.id = :id";
        
        $selectStmt = $conn->prepare($selectSql);
        $selectStmt->bindParam(':id', $resenaId);
        $selectStmt->execute();
        
        $resenaActualizada = $selectStmt->fetch(PDO::FETCH_ASSOC);
        
        // Log de la operación
        Utils::log("Usuario {$userId} actualizó reseña {$resenaId} de película: {$resenaActualizada['pelicula_titulo']}", 'INFO');
        
        // Respuesta exitosa con datos actualizados
        Response::success([
            'id' => (int)$resenaActualizada['id'],
            'puntuacion' => (int)$resenaActualizada['puntuacion'],
            'texto_resena' => $resenaActualizada['texto_resena'],
            'fecha_creacion' => $resenaActualizada['fecha_creacion'],
            'fecha_modificacion' => $resenaActualizada['fecha_modificacion'],
            'pelicula' => [
                'id' => (int)$resenaActualizada['pelicula_id'],
                'titulo' => $resenaActualizada['pelicula_titulo']
            ]
        ], 'Reseña actualizada exitosamente');
        
    } else {
        Response::error('Error al actualizar la reseña', 500);
    }
    
} catch (Exception $e) {
    Utils::log("Error en editar reseña: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}
?>