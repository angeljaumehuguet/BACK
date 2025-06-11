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
        // buscar usuarios
        $userSql = "SELECT 'usuario' as tipo, u.id, u.nombre_usuario as titulo, 
                           u.nombre_completo as subtitulo, u.avatar_url as imagen,
                           COUNT(DISTINCT p.id) as peliculas,
                           COUNT(DISTINCT r.id) as resenas,
                           COUNT(DISTINCT s.id) as seguidores
                    FROM usuarios u
                    LEFT JOIN peliculas p ON u.id = p.id_usuario_creador AND p.activo = true
                    LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = true
                    LEFT JOIN seguimientos s ON u.id = s.id_seguido
                    WHERE u.activo = true 
                    AND (u.nombre_usuario LIKE :query OR u.nombre_completo LIKE :query)
                    AND u.id != :user_id
                    GROUP BY u.id
                    ORDER BY u.nombre_usuario
                    LIMIT :limite";
        
        $userStmt = $conn->prepare($userSql);
        $userStmt->bindParam(':query', $searchTerm);
        $userStmt->bindParam(':user_id', $userId);
        $userStmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $userStmt->execute();
        
        $usuarios = $userStmt->fetchAll(PDO::FETCH_ASSOC);
        $resultados['usuarios'] = $usuarios;
    }
    
    if ($tipo === 'peliculas' || $tipo === 'todos') {
        // buscar películas
        $movieSql = "SELECT 'pelicula' as tipo, p.id, p.titulo, 
                            CONCAT(p.director, ' (', p.ano_lanzamiento, ')') as subtitulo,
                            p.imagen_url as imagen, g.nombre as genero,
                            COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
                            COUNT(DISTINCT r.id) as total_resenas,
                            COUNT(DISTINCT f.id) as total_favoritos
                       FROM peliculas p
                       INNER JOIN generos g ON p.genero_id = g.id
                       LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = true
                       LEFT JOIN favoritos f ON p.id = f.id_pelicula
                       WHERE p.activo = true 
                       AND (p.titulo LIKE :query OR p.director LIKE :query OR p.sinopsis LIKE :query)
                       GROUP BY p.id
                       ORDER BY 
                           CASE 
                               WHEN p.titulo LIKE :query THEN 1
                               WHEN p.director LIKE :query THEN 2
                               ELSE 3
                           END,
                           puntuacion_promedio DESC
                       LIMIT :limite";
        
        $movieStmt = $conn->prepare($movieSql);
        $movieStmt->bindParam(':query', $searchTerm);
        $movieStmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $movieStmt->execute();
        
        $peliculas = $movieStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // formatear puntuación promedio
        foreach ($peliculas as &$pelicula) {
            $pelicula['puntuacion_promedio'] = round($pelicula['puntuacion_promedio'], 1);
        }
        
        $resultados['peliculas'] = $peliculas;
    }
    
    if ($tipo === 'resenas' || $tipo === 'todos') {
        // buscar reseñas
        $reviewSql = "SELECT 'resena' as tipo, r.id, r.titulo as titulo, 
                             CONCAT('Por ', u.nombre_usuario, ' - ', p.titulo) as subtitulo,
                             p.imagen_url as imagen, r.puntuacion, r.likes,
                             r.fecha_resena, p.titulo as titulo_pelicula,
                             u.nombre_usuario as autor
                      FROM resenas r
                      INNER JOIN usuarios u ON r.id_usuario = u.id
                      INNER JOIN peliculas p ON r.id_pelicula = p.id
                      WHERE r.activo = true AND u.activo = true AND p.activo = true
                      AND (r.titulo LIKE :query OR r.texto_resena LIKE :query)
                      ORDER BY r.likes DESC, r.fecha_resena DESC
                      LIMIT :limite";
        
        $reviewStmt = $conn->prepare($reviewSql);
        $reviewStmt->bindParam(':query', $searchTerm);
        $reviewStmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $reviewStmt->execute();
        
        $resenas = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // formatear fecha
        foreach ($resenas as &$resena) {
            $resena['fecha_formateada'] = Utils::timeAgo($resena['fecha_resena']);
        }
        
        $resultados['resenas'] = $resenas;
    }
    
    // calcular totales
    $totalResultados = 0;
    foreach ($resultados as $categoria) {
        $totalResultados += count($categoria);
    }
    
    Response::success([
        'query' => $query,
        'tipo' => $tipo,
        'resultados' => $resultados,
        'total_resultados' => $totalResultados,
        'limite_aplicado' => $limite
    ], 'Búsqueda completada exitosamente');
    
} catch (Exception $e) {
    Utils::log("Error en búsqueda global: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}