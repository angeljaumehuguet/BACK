<?php
require_once '../config/cors.php';
require_once '../config/database.php';

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
    $busqueda = isset($_GET['busqueda']) ? Response::sanitizeInput($_GET['busqueda']) : null;
    $ordenar = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'fecha_desc';
    
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
                   MAX(CASE WHEN f.id_usuario = :user_id THEN 1 ELSE 0 END) as es_favorita,
                   MAX(CASE WHEN r.id_usuario = :user_id THEN r.id ELSE NULL END) as resena_usuario_id
            FROM peliculas p
            INNER JOIN generos g ON p.genero_id = g.id
            INNER JOIN usuarios u ON p.id_usuario_creador = u.id
            LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = true
            LEFT JOIN favoritos f ON p.id = f.id_pelicula
            WHERE p.activo = true AND u.activo = true";
    
    $params = [':user_id' => $userId];
    
    // filtro por usuario creador
    if ($usuario !== $userId) {
        $sql .= " AND p.id_usuario_creador = :creador";
        $params[':creador'] = $usuario;
    } else {
        $sql .= " AND p.id_usuario_creador = :user_id";
    }
    
    // filtro por género
    if ($genero) {
        $sql .= " AND p.genero_id = :genero";
        $params[':genero'] = $genero;
    }
    
    // filtro de búsqueda
    if ($busqueda) {
        $sql .= " AND (p.titulo LIKE :busqueda OR p.director LIKE :busqueda)";
        $params[':busqueda'] = "%{$busqueda}%";
    }
    
    $sql .= " GROUP BY p.id";
    
    // ordenamiento
    switch ($ordenar) {
        case 'titulo_asc':
            $sql .= " ORDER BY p.titulo ASC";
            break;
        case 'titulo_desc':
            $sql .= " ORDER BY p.titulo DESC";
            break;
        case 'ano_asc':
            $sql .= " ORDER BY p.ano_lanzamiento ASC";
            break;
        case 'ano_desc':
            $sql .= " ORDER BY p.ano_lanzamiento DESC";
            break;
        case 'puntuacion_desc':
            $sql .= " ORDER BY puntuacion_promedio DESC";
            break;
        case 'resenas_desc':
            $sql .= " ORDER BY total_resenas DESC";
            break;
        case 'fecha_asc':
            $sql .= " ORDER BY p.fecha_creacion ASC";
            break;
        default: // fecha_desc
            $sql .= " ORDER BY p.fecha_creacion DESC";
            break;
    }
    
    // contar total de registros
    $countSql = "SELECT COUNT(DISTINCT p.id) as total 
                 FROM peliculas p 
                 INNER JOIN usuarios u ON p.id_usuario_creador = u.id 
                 WHERE p.activo = true AND u.activo = true";
    
    if ($usuario !== $userId) {
        $countSql .= " AND p.id_usuario_creador = :creador";
    } else {
        $countSql .= " AND p.id_usuario_creador = :user_id";
    }
    
    if ($genero) {
        $countSql .= " AND p.genero_id = :genero";
    }
    
    if ($busqueda) {
        $countSql .= " AND (p.titulo LIKE :busqueda OR p.director LIKE :busqueda)";
    }
    
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalElementos = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // obtener datos paginados
    $sql .= " LIMIT :limite OFFSET :offset";
    $params[':limite'] = $limite;
    $params[':offset'] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $peliculas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // formatear datos
    foreach ($peliculas as &$pelicula) {
        $pelicula['puntuacion_promedio'] = round($pelicula['puntuacion_promedio'], 1);
        $pelicula['duracion_formateada'] = Utils::formatDuration($pelicula['duracion_minutos']);
        $pelicula['es_favorita'] = (bool)$pelicula['es_favorita'];
        $pelicula['tiene_resena'] = !is_null($pelicula['resena_usuario_id']);
        $pelicula['fecha_agregada'] = Utils::timeAgo($pelicula['fecha_creacion']);
    }
    
    Response::paginated($peliculas, $pagina, $totalElementos, $limite, 'Películas obtenidas exitosamente');
    
} catch (Exception $e) {
    Utils::log("Error en listar películas: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}