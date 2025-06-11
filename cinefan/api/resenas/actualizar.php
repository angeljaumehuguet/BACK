<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// validar método
Response::validateMethod(['PUT']);

try {
    // autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    // obtener datos de entrada
    $input = Response::getJsonInput();
    
    // validar campos requeridos
    Response::validateRequired($input, ['id']);
    
    $resenaId = (int)$input['id'];
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // verificar que la reseña existe y pertenece al usuario
    $checkSql = "SELECT r.id, r.puntuacion, r.titulo, r.texto_resena, r.es_spoiler, p.titulo as pelicula_titulo
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
    
    // campos actualizables
    $allowedFields = ['puntuacion', 'titulo', 'texto_resena', 'es_spoiler'];
    $updateFields = [];
    $params = [':id' => $resenaId];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $value = $input[$field];
            
            // validaciones específicas
            switch ($field) {
                case 'puntuacion':
                    $value = (int)$value;
                    if (!Utils::validateRating($value)) {
                        Response::error('La puntuación debe estar entre 1 y 5', 422);
                    }
                    break;
                    
                case 'titulo':
                    $value = Response::sanitizeInput($value);
                    if ($value && strlen($value) > 200) {
                        Response::error('El título no puede exceder los 200 caracteres', 422);
                    }
                    break;
                    
                case 'texto_resena':
                    $value = Response::sanitizeInput($value);
                    if (strlen($value) < 10) {
                        Response::error('La reseña debe tener al menos 10 caracteres', 422);
                    }
                    if (strlen($value) > MAX_REVIEW_LENGTH) {
                        Response::error('La reseña no puede exceder los ' . MAX_REVIEW_LENGTH . ' caracteres', 422);
                    }
                    break;
                    
                case 'es_spoiler':
                    $value = (bool)$value;
                    break;
            }
            
            $updateFields[] = "{$field} = :{$field}";
            $params[":{$field}"] = $value;
        }
    }
    
    if (empty($updateFields)) {
        Response::error('No hay campos para actualizar', 400);
    }
    
    // actualizar en la base de datos
    $updateSql = "UPDATE resenas SET " . implode(', ', $updateFields) . " WHERE id = :id";
    
    $updateStmt = $conn->prepare($updateSql);
    
    if ($updateStmt->execute($params)) {
        Utils::log("Usuario {$userId} actualizó reseña {$resenaId}", 'INFO');
        Response::success(null, 'Reseña actualizada exitosamente');
    } else {
        Response::error('Error al actualizar la reseña', 500);
    }
    
} catch (Exception $e) {
    Utils::log("Error en actualizar reseña: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}