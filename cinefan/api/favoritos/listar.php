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
    $usuario = isset($_GET['usuario']) ? (int)$_GET['usuario'] : $userId;
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $limite = isset($_GET['limite']) ? min(50, max(1, (int)$_GET['limite'])) : DEFAULT_PAGE_SIZE;
    $genero = isset($_GET['genero']) ? (int)$_GET['genero'] : null;
    $busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : null;
    $ordenar = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'fecha_desc';
    
    $offset = ($pagina - 1) * $limite;
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // construir consulta base
    $sql = "SELECT f.id as favorito_id, f.fecha_agregado,
                   p.id as pelicula_id, p.titulo, p.director, p.ano_lanzamiento, 
                   p.duracion_minutos, p.sinopsis, p.imagen_url, p.fecha_creacion,
                   g.nombre as genero, g.color_hex as color_genero,
                   u.nombre_usuario as creador,
                   COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
                   COUNT(DISTINCT r.id) as total_resenas,
                   COUNT(DISTINCT f2.id) as total_favoritos,
                   MAX(CASE WHEN r_user.id_usuario = ? THEN r_user.id ELSE NULL END) as resena_usuario_id
            FROM favoritos f
            INNER JOIN peliculas p ON f.id_pelicula = p.id
            INNER JOIN generos g ON p.genero_id = g.id
            INNER JOIN usuarios u ON p.id_usuario_creador = u.id
            LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = true
            LEFT JOIN favoritos f2 ON p.id = f2.id_pelicula
            LEFT JOIN resenas r_user ON p.id = r_user.id_pelicula AND r_user.id_usuario = ?
            WHERE f.id_usuario = ? AND p.activo = true AND u.activo = true";
    
    // inicializar parámetros
    $bindParams = [$userId, $userId, $usuario];
    
    // filtro por género
    if ($genero) {
        $sql .= " AND p.genero_id = ?";
        $bindParams[] = $genero;
    }
    
    // filtro de búsqueda
    if ($busqueda) {
        $sql .= " AND (p.titulo LIKE ? OR p.director LIKE ?)";
        $searchTerm = "%{$busqueda}%";
        $bindParams[] = $searchTerm;
        $bindParams[] = $searchTerm;
    }
    
    $sql .= " GROUP BY f.id, p.id";
    
    // ordenamiento
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
            $sql .= " ORDER BY puntuacion_promedio DESC, f.fecha_agregado DESC";
            break;
        case 'resenas_desc':
            $sql .= " ORDER BY total_resenas DESC, f.fecha_agregado DESC";
            break;
        case 'popularidad_desc':
            $sql .= " ORDER BY total_favoritos DESC, total_resenas DESC, f.fecha_agregado DESC";
            break;
        case 'fecha_asc':
            $sql .= " ORDER BY f.fecha_agregado ASC";
            break;
        default: // fecha_desc
            $sql .= " ORDER BY f.fecha_agregado DESC";
            break;
    }
    
    // construir consulta de conteo
    $countSql = "SELECT COUNT(DISTINCT f.id) as total
                 FROM favoritos f
                 INNER JOIN peliculas p ON f.id_pelicula = p.id
                 INNER JOIN usuarios u ON p.id_usuario_creador = u.id
                 WHERE f.id_usuario = ? AND p.activo = true AND u.activo = true";
    
    // preparar parámetros para el conteo
    $countParams = [$usuario];
    
    // agregar filtros al conteo
    if ($genero) {
        $countSql .= " AND p.genero_id = ?";
        $countParams[] = $genero;
    }
    
    if ($busqueda) {
        $countSql .= " AND (p.titulo LIKE ? OR p.director LIKE ?)";
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
    }
    
    // ejecutar consulta de conteo
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($countParams);
    $totalElementos = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // agregar paginación a consulta principal
    $sql .= " LIMIT ? OFFSET ?";
    $bindParams[] = $limite;
    $bindParams[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($bindParams);
    $favoritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // formatear datos
    foreach ($favoritos as &$favorito) {
        $favorito['fecha_agregado_formateada'] = Utils::timeAgo($favorito['fecha_agregado']);
        $favorito['puntuacion_promedio'] = round($favorito['puntuacion_promedio'], 1);
        $favorito['duracion_formateada'] = Utils::formatDuration($favorito['duracion_minutos']);
        $favorito['tiene_resena'] = !is_null($favorito['resena_usuario_id']);
        $favorito['es_propietario'] = ($usuario === $userId);
        
        // información de la película
        $favorito['pelicula'] = [
            'id' => (int)$favorito['pelicula_id'],
            'titulo' => $favorito['titulo'],
            'director' => $favorito['director'],
            'ano_lanzamiento' => (int)$favorito['ano_lanzamiento'],
            'duracion_minutos' => (int)$favorito['duracion_minutos'],
            'sinopsis' => $favorito['sinopsis'],
            'imagen_url' => $favorito['imagen_url'],
            'fecha_creacion' => $favorito['fecha_creacion'],
            'genero' => $favorito['genero'],
            'color_genero' => $favorito['color_genero'],
            'creador' => $favorito['creador'],
            'puntuacion_promedio' => $favorito['puntuacion_promedio'],
            'duracion_formateada' => $favorito['duracion_formateada'],
            'estadisticas' => [
                'resenas' => (int)$favorito['total_resenas'],
                'favoritos' => (int)$favorito['total_favoritos'],
                'puntuacion_promedio' => $favorito['puntuacion_promedio']
            ]
        ];
        
        // información del favorito
        $favorito['favorito'] = [
            'id' => (int)$favorito['favorito_id'],
            'fecha_agregado' => $favorito['fecha_agregado'],
            'fecha_agregado_formateada' => $favorito['fecha_agregado_formateada']
        ];
        
        // resumen de sinopsis
        $favorito['pelicula']['sinopsis_resumen'] = strlen($favorito['sinopsis']) > 150 
            ? substr($favorito['sinopsis'], 0, 150) . '...' 
            : $favorito['sinopsis'];
        
        // limpiar campos duplicados
        unset($favorito['favorito_id'], $favorito['fecha_agregado'], $favorito['fecha_agregado_formateada'],
              $favorito['pelicula_id'], $favorito['titulo'], $favorito['director'], $favorito['ano_lanzamiento'],
              $favorito['duracion_minutos'], $favorito['sinopsis'], $favorito['imagen_url'], $favorito['fecha_creacion'],
              $favorito['genero'], $favorito['color_genero'], $favorito['creador'], $favorito['puntuacion_promedio'],
              $favorito['total_resenas'], $favorito['total_favoritos'], $favorito['duracion_formateada'],
              $favorito['resena_usuario_id']);
    }
    
    Response::paginated($favoritos, $pagina, $totalElementos, $limite, 'Favoritos obtenidos exitosamente');
    
} catch (Exception $e) {
    Utils::log("Error en listar favoritos: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}