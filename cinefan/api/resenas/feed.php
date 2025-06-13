<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/utils.php';
require_once '../config/config.php';

// validar método
Response::validateMethod(['GET']);

try {
    // autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    // parámetros de consulta
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $limite = isset($_GET['limite']) ? min(50, max(1, (int)$_GET['limite'])) : DEFAULT_PAGE_SIZE;
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos'; // todos, siguiendo, recientes, mis_resenas
    $genero = isset($_GET['genero']) ? (int)$_GET['genero'] : null;
    
    $offset = ($pagina - 1) * $limite;
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // construir consulta base con todos los datos necesarios
    $sql = "SELECT r.id, r.puntuacion, r.titulo as titulo_resena, r.texto_resena, 
               r.fecha_resena, r.likes, r.es_spoiler,
               u.id as usuario_id, u.nombre_usuario, u.nombre_completo, u.avatar_url,
               p.id as pelicula_id, p.titulo as pelicula_titulo, p.director, 
               p.ano_lanzamiento, p.imagen_url as pelicula_imagen,
               g.nombre as genero, g.color_hex as color_genero,
               CASE WHEN lr.id IS NOT NULL THEN 1 ELSE 0 END as usuario_dio_like,
               CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END as pelicula_es_favorita
        FROM resenas r
        INNER JOIN usuarios u ON r.id_usuario = u.id
        INNER JOIN peliculas p ON r.id_pelicula = p.id
        INNER JOIN generos g ON p.genero_id = g.id
        LEFT JOIN likes_resenas lr ON r.id = lr.id_resena AND lr.id_usuario = ?
        LEFT JOIN favoritos f ON p.id = f.id_pelicula AND f.id_usuario = ?";
    
    // parámetros base
    $params = [$userId, $userId];
    
    // condiciones base
    $conditions = ["r.activo = 1", "u.activo = 1", "p.activo = 1"];
    
    // filtro por tipo de feed
    switch ($tipo) {
        case 'siguiendo':
            $sql .= " INNER JOIN seguimientos s ON r.id_usuario = s.id_seguido 
                      AND s.id_seguidor = ? AND s.activo = 1";
            $params[] = $userId;
            break;
        case 'mis_resenas':
            $conditions[] = "r.id_usuario = ?";
            $params[] = $userId;
            break;
        case 'recientes':
            $conditions[] = "r.fecha_resena >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        // 'todos' no requiere filtro adicional
    }
    
    // filtro por género
    if ($genero) {
        $conditions[] = "p.genero_id = ?";
        $params[] = $genero;
    }
    
    // agregar condiciones
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY r.fecha_resena DESC";
    
    // consulta de conteo para paginación
    $countSql = "SELECT COUNT(DISTINCT r.id) as total
                 FROM resenas r
                 INNER JOIN usuarios u ON r.id_usuario = u.id
                 INNER JOIN peliculas p ON r.id_pelicula = p.id
                 INNER JOIN generos g ON p.genero_id = g.id";
    
    // aplicar los mismos filtros para el conteo
    $countParams = [$userId, $userId]; // para los LEFT JOIN
    
    switch ($tipo) {
        case 'siguiendo':
            $countSql .= " INNER JOIN seguimientos s ON r.id_usuario = s.id_seguido 
                           AND s.id_seguidor = ? AND s.activo = 1";
            $countParams[] = $userId;
            break;
        case 'mis_resenas':
            $conditions[count($conditions) - 1] = "r.id_usuario = ?";
            $countParams[] = $userId;
            break;
    }
    
    if ($genero) {
        $countParams[] = $genero;
    }
    
    if (!empty($conditions)) {
        $countSql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($countParams);
    $totalElementos = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // obtener datos paginados
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limite;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $resenas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // formatear datos para la respuesta
    foreach ($resenas as &$resena) {
        // formatear fecha
        $resena['fecha_formateada'] = Utils::timeAgo($resena['fecha_resena']);
        
        // convertir a boolean
        $resena['usuario_dio_like'] = (bool)$resena['usuario_dio_like'];
        $resena['pelicula_es_favorita'] = (bool)$resena['pelicula_es_favorita'];
        $resena['es_spoiler'] = (bool)$resena['es_spoiler'];
        
        // texto preview
        $resena['texto_preview'] = strlen($resena['texto_resena']) > 200 
            ? substr($resena['texto_resena'], 0, 200) . '...' 
            : $resena['texto_resena'];
        
        // formatear puntuación
        $resena['puntuacion'] = (float)$resena['puntuacion'];
        
        // formatear likes
        $resena['likes'] = (int)$resena['likes'];
        
        // información del usuario
        $resena['usuario'] = [
            'id' => (int)$resena['usuario_id'],
            'nombre_usuario' => $resena['nombre_usuario'],
            'nombre_completo' => $resena['nombre_completo'],
            'avatar_url' => $resena['avatar_url']
        ];
        
        // información de la película
        $resena['pelicula'] = [
            'id' => (int)$resena['pelicula_id'],
            'titulo' => $resena['pelicula_titulo'],
            'director' => $resena['director'],
            'ano_lanzamiento' => (int)$resena['ano_lanzamiento'],
            'imagen_url' => $resena['pelicula_imagen'],
            'genero' => $resena['genero'],
            'color_genero' => $resena['color_genero']
        ];
        
        // limpiar campos duplicados
        unset($resena['usuario_id'], $resena['nombre_usuario'], $resena['nombre_completo'], $resena['avatar_url']);
        unset($resena['pelicula_id'], $resena['pelicula_titulo'], $resena['director'], 
              $resena['ano_lanzamiento'], $resena['pelicula_imagen'], $resena['genero'], 
              $resena['color_genero']);
    }
    
    Response::paginated($resenas, $pagina, $totalElementos, $limite, 'Feed obtenido exitosamente');
    
} catch (Exception $e) {
    Utils::log("Error en feed: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}
?>