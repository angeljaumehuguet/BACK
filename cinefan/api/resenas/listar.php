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
    $ordenar = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'fecha_desc';
    
    $offset = ($pagina - 1) * $limite;
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // construir consulta base
    $sql = "SELECT r.id, r.puntuacion, r.titulo as titulo_resena, r.texto_resena, 
                   r.fecha_resena, r.likes, r.es_spoiler,
                   p.id as pelicula_id, p.titulo as pelicula_titulo, p.director, 
                   p.ano_lanzamiento, p.imagen_url as pelicula_imagen,
                   g.nombre as genero, g.color_hex as color_genero
            FROM resenas r
            INNER JOIN peliculas p ON r.id_pelicula = p.id
            INNER JOIN generos g ON p.genero_id = g.id
            WHERE r.id_usuario = :user_id AND r.activo = true AND p.activo = true";
    
    $params = [':user_id' => $usuario];
    
    // ordenamiento
    switch ($ordenar) {
        case 'puntuacion_desc':
            $sql .= " ORDER BY r.puntuacion DESC, r.fecha_resena DESC";
            break;
        case 'puntuacion_asc':
            $sql .= " ORDER BY r.puntuacion ASC, r.fecha_resena DESC";
            break;
        case 'pelicula_asc':
            $sql .= " ORDER BY p.titulo ASC";
            break;
        case 'pelicula_desc':
            $sql .= " ORDER BY p.titulo DESC";
            break;
        case 'likes_desc':
            $sql .= " ORDER BY r.likes DESC, r.fecha_resena DESC";
            break;
        case 'fecha_asc':
            $sql .= " ORDER BY r.fecha_resena ASC";
            break;
        default: // fecha_desc
            $sql .= " ORDER BY r.fecha_resena DESC";
            break;
    }
    
    // contar total de registros
    $countSql = "SELECT COUNT(*) as total FROM resenas r 
               INNER JOIN peliculas p ON r.id_pelicula = p.id
               WHERE r.id_usuario = :user_id AND r.activo = true AND p.activo = true";
    
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
        $resena['es_spoiler'] = (bool)$resena['es_spoiler'];
        $resena['es_propietario'] = ($usuario === $userId);
        $resena['texto_preview'] = strlen($resena['texto_resena']) > 150 
            ? substr($resena['texto_resena'], 0, 150) . '...' 
            : $resena['texto_resena'];
    }
    
    Response::paginated($resenas, $pagina, $totalElementos, $limite, 'Reseñas obtenidas exitosamente');
    
} catch (Exception $e) {
    Utils::log("Error en listar reseñas: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}