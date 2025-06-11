<?php
require_once '../config/cors.php';
require_once '../config/database.php';

// validar método
Response::validateMethod(['POST']);

try {
    // autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    // obtener datos de entrada
    $input = Response::getJsonInput();
    
    // validar campos requeridos
    Response::validateRequired($input, ['id_pelicula', 'puntuacion', 'texto_resena']);
    
    $peliculaId = (int)$input['id_pelicula'];
    $puntuacion = (int)$input['puntuacion'];
    $textoResena = Response::sanitizeInput($input['texto_resena']);
    $titulo = isset($input['titulo']) ? Response::sanitizeInput($input['titulo']) : null;
    $esSpoiler = isset($input['es_spoiler']) ? (bool)$input['es_spoiler'] : false;
    
    // validaciones
    $errores = [];
    
    if (!Utils::validateRating($puntuacion)) {
        $errores['puntuacion'] = 'La puntuación debe estar entre 1 y 5';
    }
    
    if (strlen($textoResena) < 10) {
        $errores['texto_resena'] = 'La reseña debe tener al menos 10 caracteres';
    }
    
    if (strlen($textoResena) > MAX_REVIEW_LENGTH) {
        $errores['texto_resena'] = 'La reseña no puede exceder los ' . MAX_REVIEW_LENGTH . ' caracteres';
    }
    
    if ($titulo && strlen($titulo) > 200) {
        $errores['titulo'] = 'El título no puede exceder los 200 caracteres';
    }
    
    if (!empty($errores)) {
        Response::error('Errores de validación', 422, $errores);
    }
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // verificar que la película existe
    $peliculaSql = "SELECT id, titulo FROM peliculas WHERE id = :id AND activo = true";
    $peliculaStmt = $conn->prepare($peliculaSql);
    $peliculaStmt->bindParam(':id', $peliculaId);
    $peliculaStmt->execute();
    
    $pelicula = $peliculaStmt->fetch(PDO::FETCH_ASSOC);
    if (!$pelicula) {
        Response::error('Película no encontrada', 404);
    }
    
    // verificar si el usuario ya reseñó esta película
    $checkSql = "SELECT id FROM resenas 
               WHERE id_usuario = :user_id AND id_pelicula = :pelicula_id AND activo = true";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bindParam(':user_id', $userId);
    $checkStmt->bindParam(':pelicula_id', $peliculaId);
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        Response::error('Ya has reseñado esta película', 409);
    }
    
    // insertar nueva reseña
    $insertSql = "INSERT INTO resenas (id_usuario, id_pelicula, puntuacion, titulo, texto_resena, es_spoiler) 
                  VALUES (:user_id, :pelicula_id, :puntuacion, :titulo, :texto_resena, :es_spoiler)";
    
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bindParam(':user_id', $userId);
    $insertStmt->bindParam(':pelicula_id', $peliculaId);
    $insertStmt->bindParam(':puntuacion', $puntuacion);
    $insertStmt->bindParam(':titulo', $titulo);
    $insertStmt->bindParam(':texto_resena', $textoResena);
    $insertStmt->bindParam(':es_spoiler', $esSpoiler, PDO::PARAM_BOOL);
    
    if ($insertStmt->execute()) {
        $resenaId = $conn->lastInsertId();
        
        Utils::log("Usuario {$userId} creó reseña para película {$peliculaId} (ID: {$resenaId})", 'INFO');
        
        Response::success([
            'id' => $resenaId,
            'pelicula_titulo' => $pelicula['titulo'],
            'puntuacion' => $puntuacion
        ], 'Reseña creada exitosamente', 201);
    } else {
        Response::error('Error al crear la reseña', 500);
    }
    
} catch (Exception $e) {
    Utils::log("Error en crear reseña: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}