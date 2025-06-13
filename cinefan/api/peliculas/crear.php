<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// validar método
Response::validateMethod(['POST']);

try {
    // autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    // obtener datos de entrada
    $input = Response::getJsonInput();
    
    // validar campos requeridos
    Response::validateRequired($input, ['titulo', 'director', 'ano_lanzamiento', 'duracion_minutos', 'genero_id']);
    
    $titulo = Response::sanitizeInput($input['titulo']);
    $director = Response::sanitizeInput($input['director']);
    $anoLanzamiento = (int)$input['ano_lanzamiento'];
    $duracionMinutos = (int)$input['duracion_minutos'];
    $generoId = (int)$input['genero_id'];
    $sinopsis = isset($input['sinopsis']) ? Response::sanitizeInput($input['sinopsis']) : null;
    $imagenUrl = isset($input['imagen_url']) ? Response::sanitizeInput($input['imagen_url']) : null;
    
    // validaciones
    $errores = [];
    
    if (strlen($titulo) < 1 || strlen($titulo) > 200) {
        $errores['titulo'] = 'El título debe tener entre 1 y 200 caracteres';
    }
    
    if (strlen($director) < 1 || strlen($director) > 100) {
        $errores['director'] = 'El director debe tener entre 1 y 100 caracteres';
    }
    
    if ($anoLanzamiento < 1895 || $anoLanzamiento > (date('Y') + 5)) {
        $errores['ano_lanzamiento'] = 'El año debe estar entre 1895 y ' . (date('Y') + 5);
    }
    
    if ($duracionMinutos < 1 || $duracionMinutos > 600) {
        $errores['duracion_minutos'] = 'La duración debe estar entre 1 y 600 minutos';
    }
    
    if ($sinopsis && strlen($sinopsis) > 1000) {
        $errores['sinopsis'] = 'La sinopsis no puede exceder los 1000 caracteres';
    }
    
    if ($imagenUrl && !filter_var($imagenUrl, FILTER_VALIDATE_URL)) {
        $errores['imagen_url'] = 'La URL de la imagen no es válida';
    }
    
    if (!empty($errores)) {
        Response::error('Errores de validación', 422, $errores);
    }
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // verificar si el género existe
    $generoSql = "SELECT id, nombre FROM generos WHERE id = :genero_id AND activo = true";
    $generoStmt = $conn->prepare($generoSql);
    $generoStmt->bindParam(':genero_id', $generoId);
    $generoStmt->execute();
    
    $generoData = $generoStmt->fetch(PDO::FETCH_ASSOC);
    if (!$generoData) {
        Response::error('Género no válido. Debe ser un ID de género existente.', 422);
    }
    
    // verificar si el usuario ya tiene una película con el mismo título
    $checkSql = "SELECT id FROM peliculas 
               WHERE titulo = :titulo AND id_usuario_creador = :user_id AND activo = true";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bindParam(':titulo', $titulo);
    $checkStmt->bindParam(':user_id', $userId);
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        Response::error('Ya tienes una película con este título', 409);
    }
    
    // insertar nueva película
    $insertSql = "INSERT INTO peliculas (titulo, director, ano_lanzamiento, duracion_minutos, 
                                         genero_id, sinopsis, imagen_url, id_usuario_creador) 
                  VALUES (:titulo, :director, :ano, :duracion, :genero_id, :sinopsis, :imagen_url, :user_id)";
    
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bindParam(':titulo', $titulo);
    $insertStmt->bindParam(':director', $director);
    $insertStmt->bindParam(':ano', $anoLanzamiento);
    $insertStmt->bindParam(':duracion', $duracionMinutos);
    $insertStmt->bindParam(':genero_id', $generoId);
    $insertStmt->bindParam(':sinopsis', $sinopsis);
    $insertStmt->bindParam(':imagen_url', $imagenUrl);
    $insertStmt->bindParam(':user_id', $userId);
    
    if ($insertStmt->execute()) {
        $peliculaId = $conn->lastInsertId();
        
        Utils::log("Usuario {$userId} creó película: {$titulo} (ID: {$peliculaId})", 'INFO');
        
        // obtener datos completos de la película creada
        $peliculaCreadadSql = "SELECT p.id, p.titulo, p.director, p.ano_lanzamiento, 
                                     p.duracion_minutos, p.sinopsis, p.imagen_url,
                                     g.nombre as genero, g.color_hex
                               FROM peliculas p
                               INNER JOIN generos g ON p.genero_id = g.id
                               WHERE p.id = :id";
        
        $peliculaStmt = $conn->prepare($peliculaCreadadSql);
        $peliculaStmt->bindParam(':id', $peliculaId);
        $peliculaStmt->execute();
        $peliculaCompleta = $peliculaStmt->fetch(PDO::FETCH_ASSOC);
        
        Response::success([
            'id' => (int)$peliculaCompleta['id'],
            'titulo' => $peliculaCompleta['titulo'],
            'director' => $peliculaCompleta['director'],
            'ano_lanzamiento' => (int)$peliculaCompleta['ano_lanzamiento'],
            'duracion_minutos' => (int)$peliculaCompleta['duracion_minutos'],
            'duracion_formateada' => Utils::formatDuration($peliculaCompleta['duracion_minutos']),
            'genero' => $peliculaCompleta['genero'],
            'color_genero' => $peliculaCompleta['color_hex'],
            'sinopsis' => $peliculaCompleta['sinopsis'],
            'imagen_url' => $peliculaCompleta['imagen_url']
        ], 'Película creada exitosamente', 201);
    } else {
        Response::error('Error al crear la película', 500);
    }
    
} catch (Exception $e) {
    Utils::log("Error en crear película: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}