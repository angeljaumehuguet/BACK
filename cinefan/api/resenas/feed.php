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
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $limite = isset($_GET['limite']) ? min(50, max(1, (int)$_GET['limite'])) : DEFAULT_PAGE_SIZE;
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos'; // todos, siguiendo, recientes
    $genero = isset($_GET['genero']) ? (int)$_GET['genero'] : null;
    
    $offset = ($pagina - 1) * $limite;
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // construir consulta base
    $sql = "SELECT r.id, r.puntuacion, r.titulo as titulo_resena, r.texto_resena, 
                   r.fecha_resena, r.likes, r.es_spoiler,
                   u.nombre_usuario, u.nombre_completo, u.avatar_url,
                   p.id as pelicula_id, p.titulo as pelicula_titulo, p.director, 
                   p.ano_lanzamiento, p.imagen_url as pelicula_imagen,
                   g.nombre as genero, g.color_hex as color_genero,
                   CASE WHEN lr.id IS NOT NULL THEN 1 ELSE 0 END as usuario_dio_like,
                   CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END as pelicula_es_favorita
            FROM resenas r
            INNER JOIN usuarios u ON r.id_usuario = u.id
            INNER JOIN peliculas p ON r.id_pelicula = p.id
            INNER JOIN generos g ON p.genero_id = g.id
            LEFT JOIN likes_resenas lr ON r.id = lr.id_resena AND lr.id_usuario = :user_id
            LEFT JOIN favoritos f ON p.id = f.id_pelicula AND f.id_usuario = :user_id";
    
    $params = [':user_id' => $userId];
    
    // condiciones base
    $conditions = ["r.activo = true", "u.activo = true", "p.activo = true"];
    
    // filtro por tipo de feed
    switch ($tipo) {
        case 'siguiendo':
            $sql .= " INNER JOIN seguimientos s ON r.id_usuario = s.id_seguido AND s.id_seguidor = :user_id AND s.activo = true";
            break;
        case 'mis_resenas':
            $conditions[] = "r.id_usuario = :user_id";
            break;
        case 'recientes':
            $conditions[] = "r.fecha_resena >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        // 'todos' no requiere filtro adicional
    }
    
    // filtro por género
    if ($genero) {
        $conditions[] = "p.genero_id = :genero";
        $params[':genero'] = $genero;
    }
    
    // agregar condiciones
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY r.fecha_resena DESC";
    
    // contar total de registros
    $countSql = str_replace(
        "SELECT r.id, r.puntuacion, r.titulo as titulo_resena, r.texto_resena, r.fecha_resena, r.likes, r.es_spoiler, u.nombre_usuario, u.nombre_completo, u.avatar_url, p.id as pelicula_id, p.titulo as pelicula_titulo, p.director, p.ano_lanzamiento, p.imagen_url as pelicula_imagen, g.nombre as genero, g.color_hex as color_genero, CASE WHEN lr.id IS NOT NULL THEN 1 ELSE 0 END as usuario_dio_like, CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END as pelicula_es_favorita",
        "SELECT COUNT(DISTINCT r.id) as total",
        $sql
    );
    
    // remover ORDER BY del count
    $countSql = preg_replace('/ORDER BY.*$/', '', $countSql);
    
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalElementos = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // obtener datos paginados
    $sql .= " LIMIT :limite OFFSET :offset";
    $params[':limite'] = $limite;
    $params[':offset'] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $resenas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // formatear datos
    foreach ($resenas as &$resena) {
        $resena['fecha_formateada'] = Utils::timeAgo($resena['fecha_resena']);
        $resena['usuario_dio_like'] = (bool)$resena['usuario_dio_like'];
        $resena['pelicula_es_favorita'] = (bool)$resena['pelicula_es_favorita'];
        $resena['es_spoiler'] = (bool)$resena['es_spoiler'];
        $resena['texto_preview'] = strlen($resena['texto_resena']) > 200 
            ? substr($resena['texto_resena'], 0, 200) . '...' 
            : $resena['texto_resena'];
    }
    
    Response::paginated($resenas, $pagina, $totalElementos, $limite, 'Feed obtenido exitosamente');
    
} catch (Exception $e) {
    Utils::log("Error en feed: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}