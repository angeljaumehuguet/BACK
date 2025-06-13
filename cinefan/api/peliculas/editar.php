<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// Validar método
Response::validateMethod(['PUT', 'PATCH', 'POST']);

try {
    // Autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    Utils::log("Usuario autenticado: ID={$userId}", 'INFO');
    
    // Obtener datos de entrada
    $input = Response::getJsonInput();
    Utils::log("Input recibido: " . json_encode($input), 'DEBUG');
    
    if (empty($input)) {
        Utils::log("ERROR: Input vacío", 'ERROR');
        Response::error('Datos requeridos para actualizar', 400);
    }
    
    // OBTENER ID DE PELÍCULA
    $peliculaId = null;
    
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $peliculaId = (int)$_GET['id'];
    } elseif (isset($input['id']) && !empty($input['id'])) {
        $peliculaId = (int)$input['id'];
    }
    
    if (!$peliculaId || $peliculaId <= 0) {
        Utils::log("ERROR: ID de película inválido: {$peliculaId}", 'ERROR');
        Response::error('ID de película requerido y debe ser mayor a 0', 400);
    }
    
    Utils::log("Procesando edición de película ID: {$peliculaId}", 'INFO');
    
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
        Utils::log("ERROR: Película no encontrada. ID: {$peliculaId}", 'ERROR');
        Response::error('Película no encontrada', 404);
    }
    
    // Verificar propiedad
    if ($pelicula['id_usuario_creador'] != $userId) {
        Utils::log("ERROR: Usuario {$userId} no es propietario de película {$peliculaId}", 'ERROR');
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
        if (isset($input[$campo]) && $input[$campo] !== null && $input[$campo] !== '') {
            $valor = Response::sanitizeInput($input[$campo]);
            $datosActualizar[] = "$campo = ?";
            $parametros[] = $valor;
            Utils::log("Campo a actualizar: {$campo} = '{$valor}'", 'DEBUG');
        }
    }
    
    if (empty($datosActualizar)) {
        Utils::log("ERROR: No hay datos válidos para actualizar", 'ERROR');
        Response::error('No hay datos válidos para actualizar', 400);
    }
    
    // VALIDACIONES ESPECÍFICAS
    if (isset($input['ano_lanzamiento'])) {
        $ano = (int)$input['ano_lanzamiento'];
        if ($ano < 1890 || $ano > date('Y') + 5) {
            Utils::log("ERROR: Año inválido: {$ano}", 'ERROR');
            Response::error('Año de lanzamiento inválido (1890-' . (date('Y') + 5) . ')', 400);
        }
    }
    
    if (isset($input['duracion_minutos'])) {
        $duracion = (int)$input['duracion_minutos'];
        if ($duracion < 1 || $duracion > 600) {
            Utils::log("ERROR: Duración inválida: {$duracion}", 'ERROR');
            Response::error('Duración inválida (1-600 minutos)', 400);
        }
    }
    
    // VALIDACIÓN DE GÉNERO
    if (isset($input['genero_id'])) {
        $generoId = (int)$input['genero_id'];
        $generoCheckSql = "SELECT id, nombre FROM generos WHERE id = ? AND activo = 1";
        $generoStmt = $conn->prepare($generoCheckSql);
        $generoStmt->execute([$generoId]);
        $generoData = $generoStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$generoData) {
            Utils::log("ERROR: Género no válido: {$generoId}", 'ERROR');
            Response::error('Género no válido', 400);
        }
        Utils::log("Género válido: {$generoData['nombre']}", 'INFO');
    }
    
    // AGREGAR FECHA_MODIFICACION SOLO SI LA COLUMNA EXISTE
    $columnsQuery = "SHOW COLUMNS FROM peliculas LIKE 'fecha_modificacion'";
    $columnsStmt = $conn->query($columnsQuery);
    if ($columnsStmt->rowCount() > 0) {
        $datosActualizar[] = "fecha_modificacion = NOW()";
        Utils::log("Agregando fecha_modificacion al UPDATE", 'DEBUG');
    } else {
        Utils::log("Columna fecha_modificacion no existe, saltando...", 'WARNING');
    }
    
    $parametros[] = $peliculaId; // Para la cláusula WHERE
    
    // CONSTRUIR Y EJECUTAR CONSULTA
    $updateSql = "UPDATE peliculas 
                  SET " . implode(', ', $datosActualizar) . "
                  WHERE id = ?";
    
    Utils::log("SQL: " . $updateSql, 'DEBUG');
    Utils::log("Parámetros: " . json_encode($parametros), 'DEBUG');
    
    $updateStmt = $conn->prepare($updateSql);
    
    if ($updateStmt->execute($parametros)) {
        $filasAfectadas = $updateStmt->rowCount();
        Utils::log("Actualización exitosa. Filas afectadas: {$filasAfectadas}", 'INFO');
        
        // OBTENER DATOS ACTUALIZADOS
        $selectSql = "SELECT p.*, g.nombre as genero_nombre, g.color_hex as genero_color
                      FROM peliculas p
                      LEFT JOIN generos g ON p.genero_id = g.id
                      WHERE p.id = ?";
        
        $selectStmt = $conn->prepare($selectSql);
        $selectStmt->execute([$peliculaId]);
        $peliculaActualizada = $selectStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$peliculaActualizada) {
            Utils::log("ERROR: No se pudieron obtener los datos actualizados", 'ERROR');
            Response::error('Error obteniendo datos actualizados', 500);
        }
        
        Utils::log("Usuario {$userId} actualizó película: {$peliculaActualizada['titulo']} (ID: {$peliculaId})", 'INFO');
        
        // FORMATEAR RESPUESTA CON MANEJO SEGURO DE FECHA
        $tiempoTranscurrido = 'hace un momento';
        if (function_exists('Utils::timeAgo')) {
            try {
                $tiempoTranscurrido = Utils::timeAgo($peliculaActualizada['fecha_creacion']);
            } catch (Exception $e) {
                Utils::log("Error en timeAgo: " . $e->getMessage(), 'WARNING');
            }
        }
        
        $response = [
            'id' => (int)$peliculaActualizada['id'],
            'titulo' => $peliculaActualizada['titulo'],
            'director' => $peliculaActualizada['director'],
            'ano_lanzamiento' => (int)$peliculaActualizada['ano_lanzamiento'],
            'duracion_minutos' => (int)$peliculaActualizada['duracion_minutos'],
            'sinopsis' => $peliculaActualizada['sinopsis'],
            'imagen_url' => $peliculaActualizada['imagen_url'],
            'fecha_creacion' => $peliculaActualizada['fecha_creacion'],
            'fecha_modificacion' => $peliculaActualizada['fecha_modificacion'] ?? $peliculaActualizada['fecha_creacion'],
            'tiempo_transcurrido' => $tiempoTranscurrido,
            'genero' => [
                'id' => (int)$peliculaActualizada['genero_id'],
                'nombre' => $peliculaActualizada['genero_nombre'] ?? 'Sin género',
                'color' => $peliculaActualizada['genero_color'] ?? '#6c757d'
            ]
        ];
        
        Response::success($response, 'Película actualizada exitosamente');
        
    } else {
        $errorInfo = $updateStmt->errorInfo();
        Utils::log("ERROR en ejecución SQL: " . json_encode($errorInfo), 'ERROR');
        Response::error('Error al actualizar la película: ' . $errorInfo[2], 500);
    }
    
} catch (Exception $e) {
    Utils::log("EXCEPCIÓN en editar película: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor: ' . $e->getMessage(), 500);
}
?>