<?php
require_once '../config/cors.php';
require_once '../config/database.php';

// validar método
Response::validateMethod(['GET']);

try {
    // autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos'; // usuarios, peliculas, todos
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
                           COUNT(DISTINCT r.id) as resenas
                    FROM usuarios u
                    LEFT JOIN peliculas p ON u.id = p.id_usuario_creador AND p.activo = true
                    LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = true
                    WHERE u.activo = true 
                    AND (u.nombre_usuario LIKE :query OR u.nombre_completo LIKE :query)
                    AND u.id != :user_id
                    GROUP BY u.id
                    ORDER BY u.nombre_usuario
                    LIMIT :limite";
        
        $userStmt = $conn->prepare($userSql);
        $userStmt->bindParam(':query', $searchTerm);
        $userStmt->bindParam(':user_id', $userId);
        $userStmt->bindParam(':limite', $limite);
        $userStmt->execute();
        
        $usuarios = $userStmt->fetchAll(PDO::FETCH_ASSOC);
        $resultados = array_merge($resultados, $usuarios);
    }
    
    if ($tipo === 'peliculas' || $tipo === 'todos') {
        // buscar películas
        $movieSql = "SELECT 'pelicula' as tipo, p.id, p.titulo, 
                            CONCAT(p.director, ' (', p.ano_lanzamiento, ')') as subtitulo,
                            p.imagen_url as imagen, g.nombre as genero,
                            COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
                            COUNT(DISTINCT r.id) as total_resenas
                       FROM peliculas p
                       INNER JOIN generos g ON p.genero_id = g.id
                       LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = true
                       WHERE p.activo = true 
                       AND (p.titulo LIKE :query OR p.director LIKE :query)
                       GROUP BY p.id
                       ORDER BY total_resenas DESC, p.titulo
                       LIMIT :limite";
        
        $movieStmt = $conn->prepare($movieSql);
        $movieStmt->bindParam(':query', $searchTerm);
        $movieStmt->bindParam(':limite', $limite);
        $movieStmt->execute();
        
        $peliculas = $movieStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // formatear películas
        foreach ($peliculas as &$pelicula) {
            $pelicula['puntuacion_promedio'] = round($pelicula['puntuacion_promedio'], 1);
        }
        
        $resultados = array_merge($resultados, $peliculas);
    }
    
    // limitar resultados totales
    if (count($resultados) > $limite) {
        $resultados = array_slice($resultados, 0, $limite);
    }
    
    Response::success([
        'query' => $query,
        'total_resultados' => count($resultados),
        'resultados' => $resultados
    ], 'Búsqueda completada');
    
} catch (Exception $e) {
    Utils::log("Error en búsqueda: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}