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
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    $estadisticas = [];

    // estadisticas generales del sistema
    $estadisticas['sistema'] = [
        'total_usuarios' => (int)$conn->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")->fetchColumn(),
        'total_peliculas' => (int)$conn->query("SELECT COUNT(*) FROM peliculas WHERE activo = 1")->fetchColumn(),
        'total_resenas' => (int)$conn->query("SELECT COUNT(*) FROM resenas WHERE activo = 1")->fetchColumn(),
        'total_favoritos' => (int)$conn->query("SELECT COUNT(*) FROM favoritos")->fetchColumn(),
        'total_seguimientos' => (int)$conn->query("SELECT COUNT(*) FROM seguimientos")->fetchColumn(),
        'total_likes' => (int)$conn->query("SELECT COUNT(*) FROM likes_resenas")->fetchColumn()
    ];
    
    // estadisticas de peliculas más populares
    $peliculasStmt = $conn->query("
        SELECT p.id, p.titulo, p.director,
               COUNT(DISTINCT r.id) as total_resenas,
               COUNT(DISTINCT f.id) as total_favoritos,
               COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio
        FROM peliculas p
        LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
        LEFT JOIN favoritos f ON p.id = f.id_pelicula
        WHERE p.activo = 1
        GROUP BY p.id, p.titulo, p.director
        ORDER BY (COUNT(DISTINCT r.id) + COUNT(DISTINCT f.id)) DESC
        LIMIT 5
    ");
    
    $estadisticas['peliculas_populares'] = array_map(function($p) {
        return [
            'id' => (int)$p['id'],
            'titulo' => $p['titulo'],
            'director' => $p['director'],
            'total_resenas' => (int)$p['total_resenas'],
            'total_favoritos' => (int)$p['total_favoritos'],
            'puntuacion_promedio' => round($p['puntuacion_promedio'], 1)
        ];
    }, $peliculasStmt->fetchAll(PDO::FETCH_ASSOC));
    
    // estadisticas de usuarios más activos
    $usuariosStmt = $conn->query("
        SELECT u.id, u.nombre_usuario, u.nombre_completo,
               COUNT(DISTINCT p.id) as total_peliculas,
               COUNT(DISTINCT r.id) as total_resenas,
               COUNT(DISTINCT s.id) as total_seguidores
        FROM usuarios u
        LEFT JOIN peliculas p ON u.id = p.id_usuario_creador AND p.activo = 1
        LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = 1
        LEFT JOIN seguimientos s ON u.id = s.id_seguido
        WHERE u.activo = 1
        GROUP BY u.id, u.nombre_usuario, u.nombre_completo
        ORDER BY (COUNT(DISTINCT p.id) + COUNT(DISTINCT r.id)) DESC
        LIMIT 5
    ");
    
    $estadisticas['usuarios_activos'] = array_map(function($u) {
        return [
            'id' => (int)$u['id'],
            'nombre_usuario' => $u['nombre_usuario'],
            'nombre_completo' => $u['nombre_completo'],
            'total_peliculas' => (int)$u['total_peliculas'],
            'total_resenas' => (int)$u['total_resenas'],
            'total_seguidores' => (int)$u['total_seguidores']
        ];
    }, $usuariosStmt->fetchAll(PDO::FETCH_ASSOC));
    
    // reseñas con más likes
    $resenasStmt = $conn->query("
        SELECT r.id, r.titulo as titulo_resena, r.puntuacion, r.likes,
               u.nombre_usuario as autor,
               p.titulo as titulo_pelicula
        FROM resenas r
        INNER JOIN usuarios u ON r.id_usuario = u.id
        INNER JOIN peliculas p ON r.id_pelicula = p.id
        WHERE r.activo = 1 AND u.activo = 1 AND p.activo = 1
        ORDER BY r.likes DESC
        LIMIT 5
    ");
    
    $estadisticas['resenas_populares'] = array_map(function($r) {
        return [
            'id' => (int)$r['id'],
            'titulo_resena' => $r['titulo_resena'],
            'puntuacion' => (int)$r['puntuacion'],
            'likes' => (int)$r['likes'],
            'autor' => $r['autor'],
            'titulo_pelicula' => $r['titulo_pelicula']
        ];
    }, $resenasStmt->fetchAll(PDO::FETCH_ASSOC));
    
    // estadísticas por género
    $generosStmt = $conn->query("
        SELECT g.nombre as genero, g.color_hex,
               COUNT(DISTINCT p.id) as total_peliculas,
               COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio
        FROM generos g
        LEFT JOIN peliculas p ON g.id = p.genero_id AND p.activo = 1
        LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
        WHERE g.activo = 1
        GROUP BY g.id, g.nombre, g.color_hex
        ORDER BY total_peliculas DESC
        LIMIT 10
    ");
    
    $estadisticas['generos'] = array_map(function($g) {
        return [
            'genero' => $g['genero'],
            'color_hex' => $g['color_hex'],
            'total_peliculas' => (int)$g['total_peliculas'],
            'puntuacion_promedio' => round($g['puntuacion_promedio'], 1)
        ];
    }, $generosStmt->fetchAll(PDO::FETCH_ASSOC));

    Response::success($estadisticas, 'Estadísticas obtenidas exitosamente');

} catch (Exception $e) {
    Utils::log("Error obteniendo estadísticas generales: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}