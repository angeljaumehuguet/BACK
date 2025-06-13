<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// validar método
Response::validateMethod(['GET']);

try {
    // autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    // parámetros de consulta
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $limite = isset($_GET['limite']) ? min(50, max(1, (int)$_GET['limite'])) : DEFAULT_PAGE_SIZE;
    $genero = isset($_GET['genero']) ? (int)$_GET['genero'] : null;
    $anoDesde = isset($_GET['ano_desde']) ? (int)$_GET['ano_desde'] : null;
    $anoHasta = isset($_GET['ano_hasta']) ? (int)$_GET['ano_hasta'] : null;
    $puntuacionMin = isset($_GET['puntuacion_min']) ? (float)$_GET['puntuacion_min'] : null;
    
    if (strlen($query) < 2) {
        Response::error('La búsqueda debe tener al menos 2 caracteres', 400);
    }
    
    $offset = ($pagina - 1) * $limite;
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // construir consulta base
    $sql = "SELECT p.id, p.titulo, p.director, p.ano_lanzamiento, p.duracion_minutos,
                   p.sinopsis, p.imagen_url, p.fecha_creacion,
                   g.nombre as genero, g.color_hex as color_genero,
                   u.nombre_usuario as creador,
                   COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
                   COUNT(DISTINCT r.id) as total_resenas,
                   COUNT(DISTINCT f.id) as total_favoritos,
                   MAX(CASE WHEN f_user.id_usuario = :user_id_fav THEN 1 ELSE 0 END) as es_favorita,
                   MAX(CASE WHEN r_user.id_usuario = :user_id_resena THEN r_user.id ELSE NULL END) as resena_usuario_id
            FROM peliculas p
            INNER JOIN generos g ON p.genero_id = g.id
            INNER JOIN usuarios u ON p.id_usuario_creador = u.id
            LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = true
            LEFT JOIN favoritos f ON p.id = f.id_pelicula
            LEFT JOIN favoritos f_user ON p.id = f_user.id_pelicula AND f_user.id_usuario = :user_id_fav
            LEFT JOIN resenas r_user ON p.id = r_user.id_pelicula AND r_user.id_usuario = :user_id_resena
            WHERE p.activo = true AND u.activo = true";
    
    // inicializar parámetros
    $params = [
        ':user_id_fav' => $userId,
        ':user_id_resena' => $userId
    ];
    
    // filtro de búsqueda principal
    $searchTerm = "%{$query}%";
    $sql .= " AND (p.titulo LIKE :busqueda_titulo OR p.director LIKE :busqueda_director OR p.sinopsis LIKE :busqueda_sinopsis)";
    $params[':busqueda_titulo'] = $searchTerm;
    $params[':busqueda_director'] = $searchTerm;
    $params[':busqueda_sinopsis'] = $searchTerm;
    
    // filtros adicionales
    if ($genero) {
        $sql .= " AND p.genero_id = :genero";
        $params[':genero'] = $genero;
    }
    
    if ($anoDesde) {
        $sql .= " AND p.ano_lanzamiento >= :ano_desde";
        $params[':ano_desde'] = $anoDesde;
    }
    
    if ($anoHasta) {
        $sql .= " AND p.ano_lanzamiento <= :ano_hasta";
        $params[':ano_hasta'] = $anoHasta;
    }
    
    $sql .= " GROUP BY p.id";
    
    // filtro por puntuación después del GROUP BY
    if ($puntuacionMin) {
        $sql .= " HAVING puntuacion_promedio >= :puntuacion_min";
        $params[':puntuacion_min'] = $puntuacionMin;
    }
    
    // ordenamiento por relevancia
    $sql .= " ORDER BY 
              CASE 
                  WHEN p.titulo LIKE :titulo_exacto THEN 1
                  WHEN p.director LIKE :director_exacto THEN 2
                  WHEN p.titulo LIKE :titulo_inicio THEN 3
                  WHEN p.director LIKE :director_inicio THEN 4
                  ELSE 5
              END,
              total_resenas DESC,
              puntuacion_promedio DESC,
              p.fecha_creacion DESC";
    
    // parámetros para ordenamiento
    $params[':titulo_exacto'] = $query;
    $params[':director_exacto'] = $query;
    $params[':titulo_inicio'] = $query . '%';
    $params[':director_inicio'] = $query . '%';
    
    // construir consulta de conteo
    $countSql = "SELECT COUNT(DISTINCT p.id) as total
                 FROM peliculas p
                 INNER JOIN generos g ON p.genero_id = g.id
                 INNER JOIN usuarios u ON p.id_usuario_creador = u.id
                 LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = true
                 WHERE p.activo = true AND u.activo = true
                 AND (p.titulo LIKE :busqueda_titulo OR p.director LIKE :busqueda_director OR p.sinopsis LIKE :busqueda_sinopsis)";
    
    // preparar parámetros para el conteo
    $countParams = [
        ':busqueda_titulo' => $searchTerm,
        ':busqueda_director' => $searchTerm,
        ':busqueda_sinopsis' => $searchTerm
    ];
    
    // agregar filtros al conteo
    if ($genero) {
        $countSql .= " AND p.genero_id = :genero";
        $countParams[':genero'] = $genero;
    }
    
    if ($anoDesde) {
        $countSql .= " AND p.ano_lanzamiento >= :ano_desde";
        $countParams[':ano_desde'] = $anoDesde;
    }
    
    if ($anoHasta) {
        $countSql .= " AND p.ano_lanzamiento <= :ano_hasta";
        $countParams[':ano_hasta'] = $anoHasta;
    }
    
    if ($puntuacionMin) {
        $countSql .= " GROUP BY p.id HAVING AVG(r.puntuacion) >= :puntuacion_min";
        $countParams[':puntuacion_min'] = $puntuacionMin;
        
        // Para contar con HAVING, necesitamos una subconsulta
        $countSql = "SELECT COUNT(*) as total FROM ({$countSql}) as subquery";
    }
    
    // ejecutar consulta de conteo
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($countParams);
    $totalElementos = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // agregar paginación a consulta principal
    $sql .= " LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    
    // bindear parámetros nombrados
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // bindear parámetros de paginación como enteros
    $stmt->bindValue(count($params) + 1, $limite, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $peliculas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // formatear datos
    foreach ($peliculas as &$pelicula) {
        $pelicula['puntuacion_promedio'] = round($pelicula['puntuacion_promedio'], 1);
        $pelicula['duracion_formateada'] = Utils::formatDuration($pelicula['duracion_minutos']);
        $pelicula['es_favorita'] = (bool)$pelicula['es_favorita'];
        $pelicula['tiene_resena'] = !is_null($pelicula['resena_usuario_id']);
        $pelicula['fecha_agregada'] = Utils::timeAgo($pelicula['fecha_creacion']);
        
        // calcular relevancia de búsqueda
        $relevancia = 0;
        if (stripos($pelicula['titulo'], $query) !== false) $relevancia += 3;
        if (stripos($pelicula['director'], $query) !== false) $relevancia += 2;
        if (stripos($pelicula['sinopsis'], $query) !== false) $relevancia += 1;
        $pelicula['relevancia'] = $relevancia;
        
        // resumen de búsqueda
        $pelicula['sinopsis_resumen'] = strlen($pelicula['sinopsis']) > 200 
            ? substr($pelicula['sinopsis'], 0, 200) . '...' 
            : $pelicula['sinopsis'];
    }
    
    Response::paginated($peliculas, $pagina, $totalElementos, $limite, 'Búsqueda completada exitosamente', [
        'query' => $query,
        'filtros_aplicados' => [
            'genero' => $genero,
            'ano_desde' => $anoDesde,
            'ano_hasta' => $anoHasta,
            'puntuacion_min' => $puntuacionMin
        ]
    ]);
    
} catch (Exception $e) {
    Utils::log("Error en búsqueda de películas: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}