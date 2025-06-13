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
    
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos'; // usuarios, peliculas, resenas, todos
    $limite = isset($_GET['limite']) ? min(20, max(1, (int)$_GET['limite'])) : 10;
    
    if (strlen($query) < 2) {
        Response::error('La búsqueda debe tener al menos 2 caracteres', 400);
    }
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    $resultados = [];
    $searchTerm = "%{$query}%";
    
    if ($tipo === 'usuarios' || $tipo === 'todos') {
        // buscar usuarios (consulta simplificada)
        $userSql = "SELECT 'usuario' as tipo, u.id, u.nombre_usuario as titulo, 
                           u.nombre_completo as subtitulo, u.avatar_url as imagen
                    FROM usuarios u
                    WHERE u.activo = 1 
                    AND (u.nombre_usuario LIKE ? OR u.nombre_completo LIKE ?)
                    AND u.id != ?
                    ORDER BY u.nombre_usuario
                    LIMIT ?";
        
        $userStmt = $conn->prepare($userSql);
        $userStmt->execute([$searchTerm, $searchTerm, $userId, $limite]);
        $usuarios = $userStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // agregar estadísticas de usuarios
        foreach ($usuarios as &$usuario) {
            $statsSql = "SELECT 
                            COUNT(DISTINCT p.id) as peliculas,
                            COUNT(DISTINCT r.id) as resenas
                         FROM usuarios u
                         LEFT JOIN peliculas p ON u.id = p.id_usuario_creador AND p.activo = 1
                         LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = 1
                         WHERE u.id = ?";
            $statsStmt = $conn->prepare($statsSql);
            $statsStmt->execute([$usuario['id']]);
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            $usuario['peliculas'] = (int)$stats['peliculas'];
            $usuario['resenas'] = (int)$stats['resenas'];
        }
        
        $resultados = array_merge($resultados, $usuarios);
    }
    
    if ($tipo === 'peliculas' || $tipo === 'todos') {
        // buscar películas (consulta simplificada)
        $movieSql = "SELECT 'pelicula' as tipo, p.id, p.titulo, 
                            CONCAT(p.director, ' (', p.ano_lanzamiento, ')') as subtitulo,
                            p.imagen_url as imagen, g.nombre as genero
                       FROM peliculas p
                       INNER JOIN generos g ON p.genero_id = g.id
                       WHERE p.activo = 1 
                       AND (p.titulo LIKE ? OR p.director LIKE ?)
                       ORDER BY p.titulo
                       LIMIT ?";
        
        $movieStmt = $conn->prepare($movieSql);
        $movieStmt->execute([$searchTerm, $searchTerm, $limite]);
        $peliculas = $movieStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // agregar estadísticas de películas
        foreach ($peliculas as &$pelicula) {
            $statsSql = "SELECT 
                            COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
                            COUNT(DISTINCT r.id) as total_resenas
                         FROM peliculas p
                         LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
                         WHERE p.id = ?";
            $statsStmt = $conn->prepare($statsSql);
            $statsStmt->execute([$pelicula['id']]);
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            $pelicula['puntuacion_promedio'] = round($stats['puntuacion_promedio'], 1);
            $pelicula['total_resenas'] = (int)$stats['total_resenas'];
        }
        
        $resultados = array_merge($resultados, $peliculas);
    }
    
    if ($tipo === 'resenas' || $tipo === 'todos') {
        // buscar reseñas (consulta simplificada)
        $reviewSql = "SELECT 'resena' as tipo, r.id, 
                             COALESCE(r.titulo, 'Sin título') as titulo_resena,
                             CONCAT(u.nombre_usuario, ' - ', p.titulo) as subtitulo,
                             p.imagen_url as imagen, r.puntuacion, r.likes,
                             r.texto_resena
                      FROM resenas r
                      INNER JOIN usuarios u ON r.id_usuario = u.id
                      INNER JOIN peliculas p ON r.id_pelicula = p.id
                      WHERE r.activo = 1 AND u.activo = 1 AND p.activo = 1
                      AND (r.titulo LIKE ? OR r.texto_resena LIKE ?)
                      ORDER BY r.likes DESC, r.fecha_resena DESC
                      LIMIT ?";
        
        $reviewStmt = $conn->prepare($reviewSql);
        $reviewStmt->execute([$searchTerm, $searchTerm, $limite]);
        $resenas = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // formatear reseñas
        foreach ($resenas as &$resena) {
            $resena['titulo'] = $resena['titulo_resena'];
            $resena['texto_preview'] = strlen($resena['texto_resena']) > 100 
                ? substr($resena['texto_resena'], 0, 100) . '...' 
                : $resena['texto_resena'];
        }
        
        $resultados = array_merge($resultados, $resenas);
    }
    
    // limitar resultados totales
    if (count($resultados) > $limite) {
        $resultados = array_slice($resultados, 0, $limite);
    }
    
    Response::success([
        'query' => $query,
        'tipo' => $tipo,
        'total_resultados' => count($resultados),
        'resultados' => $resultados
    ], 'Búsqueda completada exitosamente');
    
} catch (Exception $e) {
    Utils::log("Error en búsqueda: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}
?>