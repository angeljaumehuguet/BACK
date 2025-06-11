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
    
    $peliculaId = (int)$input['id'];
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // verificar que la película existe y pertenece al usuario
    $checkSql = "SELECT id, titulo FROM peliculas 
               WHERE id = :id AND id_usuario_creador = :user_id AND activo = true";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bindParam(':id', $peliculaId);
    $checkStmt->bindParam(':user_id', $userId);
    $checkStmt->execute();
    
    $peliculaExistente = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$peliculaExistente) {
        Response::error('Película no encontrada o sin permisos', 404);
    }
    
    // campos actualizables
    $allowedFields = ['titulo', 'director', 'ano_lanzamiento', 'duracion_minutos', 'genero', 'sinopsis', 'imagen_url'];
    $updateFields = [];
    $params = [':id' => $peliculaId];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $value = $field === 'sinopsis' ? $input[$field] : Response::sanitizeInput($input[$field]);
            
            // validaciones específicas
            switch ($field) {
                case 'titulo':
                    if (strlen($value) < 1 || strlen($value) > 200) {
                        Response::error('El título debe tener entre 1 y 200 caracteres', 422);
                    }
                    break;
                    
                case 'director':
                    if (strlen($value) < 1 || strlen($value) > 100) {
                        Response::error('El director debe tener entre 1 y 100 caracteres', 422);
                    }
                    break;
                    
                case 'ano_lanzamiento':
                    $value = (int)$value;
                    if (!Utils::validateMovieYear($value)) {
                        Response::error('El año debe estar entre 1895 y ' . (date('Y') + 5), 422);
                    }
                    break;
                    
                case 'duracion_minutos':
                    $value = (int)$value;
                    if ($value < 1 || $value > 600) {
                        Response::error('La duración debe estar entre 1 y 600 minutos', 422);
                    }
                    break;
                    
                case 'genero':
                    // verificar que el género existe
                    $generoSql = "SELECT id FROM generos WHERE nombre = :genero AND activo = true";
                    $generoStmt = $conn->prepare($generoSql);
                    $generoStmt->bindParam(':genero', $value);
                    $generoStmt->execute();
                    
                    $generoData = $generoStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$generoData) {
                        Response::error('Género no válido', 422);
                    }
                    
                    $updateFields[] = "genero_id = :genero_id";
                    $params[':genero_id'] = $generoData['id'];
                    continue 2; // saltar la adición normal del campo
                    
                case 'sinopsis':
                    if (strlen($value) > 1000) {
                        Response::error('La sinopsis no puede exceder los 1000 caracteres', 422);
                    }
                    break;
                    
                case 'imagen_url':
                    if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
                        Response::error('La URL de la imagen no es válida', 422);
                    }
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
    $updateSql = "UPDATE peliculas SET " . implode(', ', $updateFields) . " WHERE id = :id";
    
    $updateStmt = $conn->prepare($updateSql);
    
    if ($updateStmt->execute($params)) {
        Utils::log("Usuario {$userId} actualizó película {$peliculaId}", 'INFO');
        Response::success(null, 'Película actualizada exitosamente');
    } else {
        Response::error('Error al actualizar la película', 500);
    }
    
} catch (Exception $e) {
    Utils::log("Error en actualizar película: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}