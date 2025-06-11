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
    
    // obtener ID de la película
    $peliculaId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$peliculaId) {
        Response::error('ID de película requerido', 400);
    }
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // obtener detalles de la película (consulta simplificada)
    $sql = "SELECT p.id, p.titulo, p.director, p.ano_lanzamiento, p.duracion_minutos,
                   p.sinopsis, p.imagen_url, p.fecha_creacion,
                   g.nombre as genero, g.color_hex as color_genero,
                   u.nombre_usuario as creador, u.nombre_completo as creador_completo
            FROM peliculas p
            INNER JOIN generos g ON p.genero_id = g.id
            INNER JOIN usuarios u ON p.id_usuario_creador = u.id
            WHERE p.id = ? AND p.activo = 1 AND u.activo = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$peliculaId]);
    $pelicula = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pelicula) {
        Response::error('Película no encontrada', 404);
    }
    
    // obtener estadísticas adicionales
    $statsSql = "SELECT 
                    COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
                    COUNT(DISTINCT r.id) as total_resenas,
                    COUNT(DISTINCT f.id) as total_favoritos
                 FROM peliculas p
                 LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
                 LEFT JOIN favoritos f ON p.id = f.id_pelicula
                 WHERE p.id = ?";
    
    $statsStmt = $conn->prepare($statsSql);
    $statsStmt->execute([$peliculaId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // verificar si es favorita del usuario
    $favSql = "SELECT id FROM favoritos WHERE id_usuario = ? AND id_pelicula = ?";
    $favStmt = $conn->prepare($favSql);
    $favStmt->execute([$userId, $peliculaId]);
    $esFavorita = $favStmt->fetch() ? true : false;
    
    // verificar si el usuario tiene reseña
    $resenaSql = "SELECT id FROM resenas WHERE id_usuario = ? AND id_pelicula = ? AND activo = 1";
    $resenaStmt = $conn->prepare($resenaSql);
    $resenaStmt->execute([$userId, $peliculaId]);
    $tieneResena = $resenaStmt->fetch() ? true : false;
    
    // obtener reseñas de la película
    $resenasSql = "SELECT r.id, r.puntuacion, r.titulo as titulo_resena, r.texto_resena, 
                          r.fecha_resena, r.likes,
                          u.nombre_usuario, u.nombre_completo,
                          CASE WHEN lr.id IS NOT NULL THEN 1 ELSE 0 END as usuario_dio_like
                   FROM resenas r
                   INNER JOIN usuarios u ON r.id_usuario = u.id
                   LEFT JOIN likes_resenas lr ON r.id = lr.id_resena AND lr.id_usuario = ?
                   WHERE r.id_pelicula = ? AND r.activo = 1 AND u.activo = 1
                   ORDER BY r.fecha_resena DESC
                   LIMIT 10";
    
    $resenasStmt = $conn->prepare($resenasSql);
    $resenasStmt->execute([$userId, $peliculaId]);
    $resenas = $resenasStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // formatear datos
    $pelicula['puntuacion_promedio'] = round($stats['puntuacion_promedio'], 1);
    $pelicula['total_resenas'] = (int)$stats['total_resenas'];
    $pelicula['total_favoritos'] = (int)$stats['total_favoritos'];
    $pelicula['duracion_formateada'] = $pelicula['duracion_minutos'] . ' min';
    $pelicula['es_favorita'] = $esFavorita;
    $pelicula['tiene_resena'] = $tieneResena;
    $pelicula['fecha_agregada'] = date('d/m/Y', strtotime($pelicula['fecha_creacion']));
    $pelicula['es_propietario'] = $pelicula['creador'] === $authData['username'];
    
    foreach ($resenas as &$resena) {
        $resena['fecha_formateada'] = date('d/m/Y H:i', strtotime($resena['fecha_resena']));
        $resena['usuario_dio_like'] = (bool)$resena['usuario_dio_like'];
    }
    
    $pelicula['resenas'] = $resenas;
    
    Response::success($pelicula, 'Detalles de película obtenidos exitosamente');
    
} catch (Exception $e) {
    Utils::log("Error en detalle película: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}
?>