<?php
require_once '../config/cors.php';
require_once '../config/database.php';

// validar método
Response::validateMethod(['GET']);

try {
    // autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    // obtener ID de la película
    $peliculaId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$peliculaId) {
        Response::error('ID de película requerido', 400);
    }
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // obtener detalles de la película
    $sql = "SELECT p.id, p.titulo, p.director, p.ano_lanzamiento, p.duracion_minutos,
                   p.sinopsis, p.imagen_url, p.fecha_creacion,
                   g.nombre as genero, g.color_hex as color_genero,
                   u.nombre_usuario as creador, u.nombre_completo as creador_completo,
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
            WHERE p.id = :pelicula_id AND p.activo = true AND u.activo = true
            GROUP BY p.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':pelicula_id', $peliculaId);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    $pelicula = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pelicula) {
        Response::error('Película no encontrada', 404);
    }
    
    // obtener reseñas de la película
    $resenasSql = "SELECT r.id, r.puntuacion, r.titulo as titulo_resena, r.texto_resena, 
                          r.fecha_resena, r.likes,
                          u.nombre_usuario, u.nombre_completo,
                          CASE WHEN lr.id IS NOT NULL THEN 1 ELSE 0 END as usuario_dio_like
                   FROM resenas r
                   INNER JOIN usuarios u ON r.id_usuario = u.id
                   LEFT JOIN likes_resenas lr ON r.id = lr.id_resena AND lr.id_usuario = :user_id
                   WHERE r.id_pelicula = :pelicula_id AND r.activo = true AND u.activo = true
                   ORDER BY r.fecha_resena DESC
                   LIMIT 10";
    
    $resenasStmt = $conn->prepare($resenasSql);
    $resenasStmt->bindParam(':pelicula_id', $peliculaId);
    $resenasStmt->bindParam(':user_id', $userId);
    $resenasStmt->execute();
    
    $resenas = $resenasStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // formatear datos
    $pelicula['puntuacion_promedio'] = round($pelicula['puntuacion_promedio'], 1);
    $pelicula['duracion_formateada'] = Utils::formatDuration($pelicula['duracion_minutos']);
    $pelicula['es_favorita'] = (bool)$pelicula['es_favorita'];
    $pelicula['tiene_resena'] = !is_null($pelicula['resena_usuario_id']);
    $pelicula['fecha_agregada'] = Utils::timeAgo($pelicula['fecha_creacion']);
    $pelicula['es_propietario'] = $pelicula['creador'] === $authData['username'];
    
    foreach ($resenas as &$resena) {
        $resena['fecha_formateada'] = Utils::timeAgo($resena['fecha_resena']);
        $resena['usuario_dio_like'] = (bool)$resena['usuario_dio_like'];
    }
    
    $pelicula['resenas'] = $resenas;
    
    Response::success($pelicula, 'Detalles de película obtenidos exitosamente');
    
} catch (Exception $e) {
    Utils::log("Error en detalle película: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}