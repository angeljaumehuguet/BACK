<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Validar método HTTP
Response::validateMethod(['GET']);

try {
    // Autenticación requerida
    $authData = Auth::requireAuth();
    $userIdLogueado = $authData['user_id'];
    
    // Obtener parámetros
    $usuario = isset($_GET['usuario']) ? (int)$_GET['usuario'] : $userIdLogueado;
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $limite = isset($_GET['limite']) ? min(50, max(1, (int)$_GET['limite'])) : 20;
    $offset = ($pagina - 1) * $limite;
    
    // Filtros opcionales
    $genero = isset($_GET['genero']) ? (int)$_GET['genero'] : null;
    $busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : null;
    
    Utils::log("Listando películas - Usuario: {$usuario}, Página: {$pagina}", 'INFO');
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // Construir consulta base (CORREGIDA)
    $sqlBase = "FROM peliculas p
                INNER JOIN usuarios u ON p.id_usuario_creador = u.id
                LEFT JOIN generos g ON p.genero_id = g.id
                WHERE p.activo = 1 AND u.activo = 1
                AND p.id_usuario_creador = :usuario";
    
    $parametros = [':usuario' => $usuario];
    
    // Agregar filtros si existen
    if ($genero) {
        $sqlBase .= " AND p.genero_id = :genero";
        $parametros[':genero'] = $genero;
    }
    
    if ($busqueda) {
        $sqlBase .= " AND (p.titulo LIKE :busqueda OR p.director LIKE :busqueda)";
        $parametros[':busqueda'] = "%{$busqueda}%";
    }
    
    // CONSULTA PRINCIPAL CORREGIDA
    $sql = "SELECT 
                p.id,
                p.titulo,
                p.director,
                p.ano_lanzamiento,
                p.duracion_minutos,
                p.sinopsis,
                p.imagen_url,
                p.fecha_creacion,
                p.fecha_actualizacion,
                
                -- Datos del género
                g.id as genero_id,
                g.nombre as genero,
                g.color_hex as color_genero,
                
                -- Datos del usuario creador
                u.nombre_usuario as usuario_creador,
                u.nombre_completo as nombre_creador,
                
                -- Estadísticas de la película (subconsultas optimizadas)
                (SELECT COUNT(*) FROM resenas r WHERE r.id_pelicula = p.id AND r.activo = 1) as total_resenas,
                (SELECT AVG(r.puntuacion) FROM resenas r WHERE r.id_pelicula = p.id AND r.activo = 1) as puntuacion_promedio,
                (SELECT COUNT(*) FROM favoritos f WHERE f.id_pelicula = p.id) as total_favoritos,
                
                -- Verificar si el usuario logueado tiene reseña
                (SELECT r.id FROM resenas r WHERE r.id_pelicula = p.id AND r.id_usuario = :user_logueado AND r.activo = 1 LIMIT 1) as resena_usuario_id,
                (SELECT r.puntuacion FROM resenas r WHERE r.id_pelicula = p.id AND r.id_usuario = :user_logueado AND r.activo = 1 LIMIT 1) as puntuacion_usuario,
                
                -- Verificar si está en favoritos del usuario logueado
                (SELECT f.id FROM favoritos f WHERE f.id_pelicula = p.id AND f.id_usuario = :user_logueado LIMIT 1) as es_favorito
                
            " . $sqlBase . "
            ORDER BY p.fecha_creacion DESC
            LIMIT :limite OFFSET :offset";
    
    // Preparar y ejecutar consulta principal
    $stmt = $conn->prepare($sql);
    foreach ($parametros as $key => $value) {
        $stmt->bindParam($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindParam(':user_logueado', $userIdLogueado, PDO::PARAM_INT);
    $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $peliculas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Consulta para contar total (optimizada)
    $countSql = "SELECT COUNT(*) as total " . $sqlBase;
    $countStmt = $conn->prepare($countSql);
    foreach ($parametros as $key => $value) {
        $countStmt->bindParam($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalPeliculas = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Formatear datos para respuesta
    $peliculasFormateadas = [];
    foreach ($peliculas as $pelicula) {
        // Calcular puntuación promedio formateada
        $puntuacionPromedio = $pelicula['puntuacion_promedio'] 
            ? round((float)$pelicula['puntuacion_promedio'], 1) 
            : 0;
        
        $peliculasFormateadas[] = [
            'id' => (int)$pelicula['id'],
            'titulo' => $pelicula['titulo'],
            'director' => $pelicula['director'],
            'ano_lanzamiento' => (int)$pelicula['ano_lanzamiento'],
            'duracion_minutos' => (int)$pelicula['duracion_minutos'],
            'sinopsis' => $pelicula['sinopsis'],
            'imagen_url' => $pelicula['imagen_url'],
            'fecha_creacion' => $pelicula['fecha_creacion'],
            'fecha_actualizacion' => $pelicula['fecha_actualizacion'],
            
            // Información del género
            'genero' => [
                'id' => (int)$pelicula['genero_id'],
                'nombre' => $pelicula['genero'] ?? 'Sin género',
                'color' => $pelicula['color_genero'] ?? '#6c757d'
            ],
            
            // Información del creador
            'usuario_creador' => [
                'nombre_usuario' => $pelicula['usuario_creador'],
                'nombre_completo' => $pelicula['nombre_creador']
            ],
            
            // Estadísticas
            'estadisticas' => [
                'total_resenas' => (int)$pelicula['total_resenas'],
                'puntuacion_promedio' => $puntuacionPromedio,
                'total_favoritos' => (int)$pelicula['total_favoritos']
            ],
            
            // Estado para usuario logueado
            'estado_usuario' => [
                'tiene_resena' => !is_null($pelicula['resena_usuario_id']),
                'puntuacion_usuario' => $pelicula['puntuacion_usuario'] ? (int)$pelicula['puntuacion_usuario'] : null,
                'es_favorito' => !is_null($pelicula['es_favorito']),
                'es_propietario' => ($usuario == $userIdLogueado)
            ]
        ];
    }
    
    // Calcular información de paginación
    $totalPaginas = ceil($totalPeliculas / $limite);
    
    // Respuesta exitosa
    $response = [
        'exito' => true,
        'mensaje' => 'Películas obtenidas exitosamente',
        'datos' => $peliculasFormateadas,
        'paginacion' => [
            'pagina_actual' => $pagina,
            'total_paginas' => $totalPaginas,
            'total_elementos' => (int)$totalPeliculas,
            'elementos_por_pagina' => $limite,
            'tiene_siguiente' => $pagina < $totalPaginas,
            'tiene_anterior' => $pagina > 1
        ],
        'filtros_aplicados' => [
            'usuario' => $usuario,
            'genero' => $genero,
            'busqueda' => $busqueda
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    Utils::log("Películas listadas exitosamente - Usuario: {$usuario}, Total: " . count($peliculasFormateadas), 'INFO');
    
    http_response_code(200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    Utils::log("Error PDO en listar películas: " . $e->getMessage(), 'ERROR');
    Response::error('Error de base de datos: ' . $e->getMessage(), 500);
    
} catch (Exception $e) {
    Utils::log("Error general en listar películas: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}
?>