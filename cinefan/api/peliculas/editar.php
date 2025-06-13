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
    
    // OBTENER ID DE PELÍCULA CORRECTAMENTE
    $peliculaId = null;
    
    // Primero intentar desde URL (?id=X)
    if (isset($_GET['id'])) {
        $peliculaId = (int)$_GET['id'];
    }
    // Si no está en URL, intentar desde JSON body
    elseif (isset($input['id'])) {
        $peliculaId = (int)$input['id'];
    }
    
    if (!$peliculaId) {
        Response::error('ID de película requerido', 400);
    }
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // VERIFICAR QUE LA PELÍCULA EXISTE Y PERTENECE AL USUARIO
    $checkSql = "SELECT id, titulo, id_usuario_creador 
                 FROM peliculas 
                 WHERE id = ? AND activo = 1";
    
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$peliculaId]);
    $pelicula = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pelicula) {
        Response::error('Película no encontrada', 404);
    }
    
    // Verificar propiedad
    if ($pelicula['id_usuario_creador'] != $userId) {
        Response::error('No tienes permisos para editar esta película', 403);
    }
    
    // CAMPOS PERMITIDOS PARA ACTUALIZAR
    $camposPermitidos = [
        'titulo', 'director', 'ano_lanzamiento', 'genero_id', 
        'duracion_minutos', 'sinopsis', 'imagen_url'
    ];
    
    $datosActualizar = [];
    $parametros = [];
    
    foreach ($camposPermitidos as $campo) {
        if (isset($input[$campo])) {
            $datosActualizar[] = "$campo = ?";
            $parametros[] = Response::sanitizeInput($input[$campo]);
        }
    }
    
    if (empty($datosActualizar)) {
        Response::error('No hay datos válidos para actualizar', 400);
    }
    
    // VALIDACIONES ESPECÍFICAS
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
        $generoCheckSql = "SELECT id FROM generos WHERE id = ?";
        $generoStmt = $conn->prepare($generoCheckSql);
        $generoStmt->execute([$generoId]);
        
        if (!$generoStmt->fetch()) {
            Response::error('Género no válido', 400);
        }
    }
    
    // Agregar fecha de modificación y ID para WHERE
    $datosActualizar[] = "fecha_modificacion = NOW()";
    $parametros[] = $peliculaId;
    
    // CONSTRUIR Y EJECUTAR CONSULTA DE ACTUALIZACIÓN
    $updateSql = "UPDATE peliculas 
                  SET " . implode(', ', $datosActualizar) . "
                  WHERE id = ?";
    
    $updateStmt = $conn->prepare($updateSql);
    
    if ($updateStmt->execute($parametros)) {
        // OBTENER DATOS ACTUALIZADOS CON TIMEAGO
        $selectSql = "SELECT p.*, g.nombre as genero_nombre, g.color_hex as genero_color
                      FROM peliculas p
                      LEFT JOIN generos g ON p.genero_id = g.id
                      WHERE p.id = ?";
        
        $selectStmt = $conn->prepare($selectSql);
        $selectStmt->execute([$peliculaId]);
        $peliculaActualizada = $selectStmt->fetch(PDO::FETCH_ASSOC);
        
        Utils::log("Usuario {$userId} actualizó película: {$peliculaActualizada['titulo']} (ID: {$peliculaId})", 'INFO');
        
        // FORMATEAR RESPUESTA CON DATOS COMPLETOS
        $response = [
            'id' => (int)$peliculaActualizada['id'],
            'titulo' => $peliculaActualizada['titulo'],
            'director' => $peliculaActualizada['director'],
            'ano_lanzamiento' => (int)$peliculaActualizada['ano_lanzamiento'],
            'duracion_minutos' => (int)$peliculaActualizada['duracion_minutos'],
            'sinopsis' => $peliculaActualizada['sinopsis'],
            'imagen_url' => $peliculaActualizada['imagen_url'],
            'fecha_creacion' => $peliculaActualizada['fecha_creacion'],
            'fecha_modificacion' => $peliculaActualizada['fecha_modificacion'],
            'tiempo_transcurrido' => Utils::timeAgo($peliculaActualizada['fecha_creacion']),
            'genero' => [
                'id' => (int)$peliculaActualizada['genero_id'],
                'nombre' => $peliculaActualizada['genero_nombre'] ?? 'Sin género',
                'color' => $peliculaActualizada['genero_color'] ?? '#6c757d'
            ]
        ];
        
        Response::success($response, 'Película actualizada exitosamente');
        
    } else {
        Response::error('Error al actualizar la película', 500);
    }
    
} catch (Exception $e) {
    Utils::log("Error en editar película: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}
?>