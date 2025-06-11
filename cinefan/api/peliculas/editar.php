<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// validar método
Response::validateMethod(['PUT', 'PATCH']);

try {
    // autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    // obtener ID de la película desde la URL
    $peliculaId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$peliculaId) {
        Response::error('ID de película requerido', 400);
    }
    
    // obtener datos de entrada
    $input = Response::getJsonInput();
    
    if (empty($input)) {
        Response::error('Datos requeridos para actualizar', 400);
    }
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // verificar que la película existe y pertenece al usuario
    $checkSql = "SELECT id, titulo, id_usuario_creador 
                 FROM peliculas 
                 WHERE id = :id AND activo = true";
    
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bindParam(':id', $peliculaId);
    $checkStmt->execute();
    
    $pelicula = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pelicula) {
        Response::error('Película no encontrada', 404);
    }
    
    // verificar propiedad
    if ($pelicula['id_usuario_creador'] != $userId) {
        Response::error('No tienes permisos para editar esta película', 403);
    }
    
    // campos permitidos para actualizar
    $camposPermitidos = [
        'titulo', 'director', 'ano_lanzamiento', 'genero_id', 
        'duracion_minutos', 'sinopsis', 'imagen_url'
    ];
    
    $datosActualizar = [];
    $parametros = [':id' => $peliculaId];
    
    foreach ($camposPermitidos as $campo) {
        if (isset($input[$campo])) {
            $datosActualizar[] = "$campo = :$campo";
            $parametros[":$campo"] = Response::sanitizeInput($input[$campo]);
        }
    }
    
    if (empty($datosActualizar)) {
        Response::error('No hay datos válidos para actualizar', 400);
    }
    
    // validaciones específicas
    if (isset($input['ano_lanzamiento'])) {
        $ano = (int)$input['ano_lanzamiento'];
        if ($ano < 1890 || $ano > date('Y') + 5) {
            Response::error('Año de lanzamiento inválido', 400);
        }
    }
    
    if (isset($input['duracion_minutos'])) {
        $duracion = (int)$input['duracion_minutos'];
        if ($duracion < 1 || $duracion > 600) {
            Response::error('Duración inválida (1-600 minutos)', 400);
        }
    }
    
    if (isset($input['genero_id'])) {
        $generoId = (int)$input['genero_id'];
        $generoCheckSql = "SELECT id FROM generos WHERE id = :genero_id";
        $generoStmt = $conn->prepare($generoCheckSql);
        $generoStmt->bindParam(':genero_id', $generoId);
        $generoStmt->execute();
        
        if (!$generoStmt->fetch()) {
            Response::error('Género no válido', 400);
        }
    }
    
    // construir y ejecutar consulta de actualización
    $updateSql = "UPDATE peliculas 
                  SET " . implode(', ', $datosActualizar) . ", fecha_actualizacion = NOW() 
                  WHERE id = :id";
    
    $updateStmt = $conn->prepare($updateSql);
    $result = $updateStmt->execute($parametros);
    
    if (!$result) {
        Response::error('Error al actualizar película', 500);
    }
    
    // obtener datos actualizados
    $peliculaActualizadaSql = "SELECT p.id, p.titulo, p.director, p.ano_lanzamiento, 
                                     p.duracion_minutos, p.sinopsis, p.imagen_url, 
                                     p.fecha_creacion, p.fecha_actualizacion,
                                     g.nombre as genero, g.id as genero_id,
                                     u.nombre_usuario as creador
                               FROM peliculas p
                               INNER JOIN generos g ON p.genero_id = g.id
                               INNER JOIN usuarios u ON p.id_usuario_creador = u.id
                               WHERE p.id = :id";
    
    $stmt = $conn->prepare($peliculaActualizadaSql);
    $stmt->bindParam(':id', $peliculaId);
    $stmt->execute();
    
    $peliculaActualizada = $stmt->fetch(PDO::FETCH_ASSOC);
    
    Response::success($peliculaActualizada, 'Película actualizada exitosamente');
    
} catch (Exception $e) {
    Utils::log("Error editando película: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}