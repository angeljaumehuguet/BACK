<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// Validar método
Response::validateMethod(['GET']);

try {
    // Autenticación requerida
    $authData = Auth::requireAuth();
    $userIdLogueado = $authData['user_id'];
    
    // Parámetros de consulta
    $usuario = isset($_GET['usuario']) ? (int)$_GET['usuario'] : $userIdLogueado;
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $limite = isset($_GET['limite']) ? min(50, max(1, (int)$_GET['limite'])) : 20;
    $offset = ($pagina - 1) * $limite;
    
    // Filtros opcionales
    $genero = isset($_GET['genero']) ? (int)$_GET['genero'] : null;
    $busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : null;
    $ordenar = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'fecha_desc';
    
    Utils::log("Listando películas - Usuario: {$usuario}, Página: {$pagina}", 'INFO');
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // Consulta principal
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
                g.nombre as genero_nombre,
                g.color_hex as genero_color,
                
                -- Datos del usuario creador
                u.nombre_usuario as usuario_creador,
                u.nombre_completo as nombre_creador,
                
                -- Estadísticas básicas
                COALESCE(stats.total_resenas, 0) as total_resenas,
                COALESCE(stats.puntuacion_promedio, 0) as puntuacion_promedio,
                COALESCE(stats.total_favoritos, 0) as total_favoritos,
                
                -- Datos específicos del usuario logueado
                user_data.resena_id as resena_usuario_id,
                user_data.puntuacion_usuario,
                user_data.es_favorito
                
            FROM peliculas p
            INNER JOIN usuarios u ON p.id_usuario_creador = u.id
            LEFT JOIN generos g ON p.genero_id = g.id
            
            -- Subconsulta optimizada para estadísticas
            LEFT JOIN (
                SELECT 
                    r.id_pelicula,
                    COUNT(DISTINCT r.id) as total_resenas,
                    AVG(r.puntuacion) as puntuacion_promedio,
                    COUNT(DISTINCT f.id) as total_favoritos
                FROM resenas r
                LEFT JOIN favoritos f ON r.id_pelicula = f.id_pelicula
                WHERE r.activo = 1
                GROUP BY r.id_pelicula
            ) stats ON p.id = stats.id_pelicula
            
            -- Subconsulta para datos específicos del usuario logueado
            LEFT JOIN (
                SELECT 
                    p2.id as pelicula_id,
                    r2.id as resena_id,
                    r2.puntuacion as puntuacion_usuario,
                    CASE WHEN f2.id IS NOT NULL THEN 1 ELSE 0 END as es_favorito
                FROM peliculas p2
                LEFT JOIN resenas r2 ON p2.id = r2.id_pelicula AND r2.id_usuario = ? AND r2.activo = 1
                LEFT JOIN favoritos f2 ON p2.id = f2.id_pelicula AND f2.id_usuario = ?
            ) user_data ON p.id = user_data.pelicula_id
            
            WHERE p.activo = 1 AND u.activo = 1";
    
    // Inicializar parámetros
    $parametros = [$userIdLogueado, $userIdLogueado];
    
    // Aplicar filtros
    if ($usuario && $usuario != $userIdLogueado) {
        $sql .= " AND p.id_usuario_creador = ?";
        $parametros[] = $usuario;
    }
    
    if ($genero) {
        $sql .= " AND p.genero_id = ?";
        $parametros[] = $genero;
    }
    
    if ($busqueda) {
        $sql .= " AND (p.titulo LIKE ? OR p.director LIKE ? OR p.sinopsis LIKE ?)";
        $searchTerm = "%{$busqueda}%";
        $parametros[] = $searchTerm;
        $parametros[] = $searchTerm;
        $parametros[] = $searchTerm;
    }
    
    // Aplicar ordenamiento
    switch ($ordenar) {
        case 'titulo_asc':
            $sql .= " ORDER BY p.titulo ASC";
            break;
        case 'titulo_desc':
            $sql .= " ORDER BY p.titulo DESC";
            break;
        case 'director_asc':
            $sql .= " ORDER BY p.director ASC";
            break;
        case 'director_desc':
            $sql .= " ORDER BY p.director DESC";
            break;
        case 'ano_asc':
            $sql .= " ORDER BY p.ano_lanzamiento ASC";
            break;
        case 'ano_desc':
            $sql .= " ORDER BY p.ano_lanzamiento DESC";
            break;
        case 'puntuacion_desc':
            $sql .= " ORDER BY puntuacion_promedio DESC, p.fecha_creacion DESC";
            break;
        case 'puntuacion_asc':
            $sql .= " ORDER BY puntuacion_promedio ASC, p.fecha_creacion DESC";
            break;
        case 'resenas_desc':
            $sql .= " ORDER BY total_resenas DESC, p.fecha_creacion DESC";
            break;
        case 'fecha_asc':
            $sql .= " ORDER BY p.fecha_creacion ASC";
            break;
        default: // fecha_desc
            $sql .= " ORDER BY p.fecha_creacion DESC";
            break;
    }
    
    // Agregar paginación
    $sql .= " LIMIT ? OFFSET ?";
    $parametros[] = $limite;
    $parametros[] = $offset;
    
    // Ejecutar consulta principal
    $stmt = $conn->prepare($sql);
    $stmt->execute($parametros);
    $peliculas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // consulta conteo
    $countSql = "SELECT COUNT(DISTINCT p.id) as total 
                 FROM peliculas p
                 INNER JOIN usuarios u ON p.id_usuario_creador = u.id
                 WHERE p.activo = 1 AND u.activo = 1";
    
    $countParametros = [];
    
    // Aplicar mismo filtro de usuario
    if ($usuario && $usuario != $userIdLogueado) {
        $countSql .= " AND p.id_usuario_creador = ?";
        $countParametros[] = $usuario;
    }
    
    // Aplicar mismo filtro de género
    if ($genero) {
        $countSql .= " AND p.genero_id = ?";
        $countParametros[] = $genero;
    }
    
    // Aplicar mismo filtro de búsqueda
    if ($busqueda) {
        $countSql .= " AND (p.titulo LIKE ? OR p.director LIKE ? OR p.sinopsis LIKE ?)";
        $countParametros[] = $searchTerm;
        $countParametros[] = $searchTerm;
        $countParametros[] = $searchTerm;
    }
    
    // Ejecutar consulta de conteo
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($countParametros);
    $totalPeliculas = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 🔥 FORMATEAR RESULTADOS CON TIMEAGO()
    $peliculasFormateadas = [];
    foreach ($peliculas as $pelicula) {
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
            'tiempo_transcurrido' => Utils::timeAgo($pelicula['fecha_creacion']),
            
            // Información del género
            'genero' => [
                'id' => (int)$pelicula['genero_id'],
                'nombre' => $pelicula['genero_nombre'] ?? 'Sin género',
                'color' => $pelicula['genero_color'] ?? '#6c757d'
            ],
            
            // Información del creador
            'usuario_creador' => [
                'nombre_usuario' => $pelicula['usuario_creador'],
                'nombre_completo' => $pelicula['nombre_creador']
            ],
            
            // Estadísticas
            'estadisticas' => [
                'total_resenas' => (int)$pelicula['total_resenas'],
                'puntuacion_promedio' => round((float)$pelicula['puntuacion_promedio'], 1),
                'total_favoritos' => (int)$pelicula['total_favoritos']
            ],
            
            // Estado para usuario logueado
            'estado_usuario' => [
                'tiene_resena' => !is_null($pelicula['resena_usuario_id']),
                'puntuacion_usuario' => $pelicula['puntuacion_usuario'] ? 
                    (int)$pelicula['puntuacion_usuario'] : null,
                'es_favorito' => (bool)$pelicula['es_favorito'],
                'es_propietario' => ($pelicula['usuario_creador'] == $userIdLogueado)
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
            'busqueda' => $busqueda,
            'ordenar' => $ordenar
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