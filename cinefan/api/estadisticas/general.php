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
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // obtener estadísticas básicas
    $estadisticas = [
        'totales' => obtenerTotales($conn),
        'peliculas_populares' => obtenerPeliculasPopulares($conn),
        'usuarios_activos' => obtenerUsuariosActivos($conn),
        'resenas_destacadas' => obtenerResenasDestacadas($conn),
        'generos_stats' => obtenerGenerosSimple($conn)
    ];
    
    Response::success($estadisticas, 'Estadísticas obtenidas exitosamente');
    
} catch (Exception $e) {
    Utils::log("Error en estadísticas: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}

// obtener totales generales
function obtenerTotales($conn) {
    try {
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM usuarios WHERE activo = 1) as total_usuarios,
                    (SELECT COUNT(*) FROM peliculas WHERE activo = 1) as total_peliculas,
                    (SELECT COUNT(*) FROM resenas WHERE activo = 1) as total_resenas,
                    (SELECT COUNT(*) FROM generos WHERE activo = 1) as total_generos";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $totales = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'usuarios' => (int)$totales['total_usuarios'],
            'peliculas' => (int)$totales['total_peliculas'],
            'resenas' => (int)$totales['total_resenas'],
            'generos' => (int)$totales['total_generos']
        ];
    } catch (Exception $e) {
        return [
            'usuarios' => 0,
            'peliculas' => 0,
            'resenas' => 0,
            'generos' => 0
        ];
    }
}

// obtener películas más populares
function obtenerPeliculasPopulares($conn) {
    try {
        $sql = "SELECT 
                    p.id, p.titulo, p.director, p.ano_lanzamiento,
                    g.nombre as genero,
                    COUNT(r.id) as total_resenas
                 FROM peliculas p
                 INNER JOIN generos g ON p.genero_id = g.id
                 LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
                 WHERE p.activo = 1
                 GROUP BY p.id
                 ORDER BY total_resenas DESC
                 LIMIT 5";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $peliculas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($pelicula) {
            return [
                'id' => (int)$pelicula['id'],
                'titulo' => $pelicula['titulo'],
                'director' => $pelicula['director'],
                'ano_lanzamiento' => (int)$pelicula['ano_lanzamiento'],
                'genero' => $pelicula['genero'],
                'total_resenas' => (int)$pelicula['total_resenas']
            ];
        }, $peliculas);
    } catch (Exception $e) {
        return [];
    }
}

// obtener usuarios más activos
function obtenerUsuariosActivos($conn) {
    try {
        $sql = "SELECT 
                    u.id, u.nombre_usuario, u.nombre_completo,
                    COUNT(r.id) as total_resenas
                 FROM usuarios u
                 LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = 1
                 WHERE u.activo = 1
                 GROUP BY u.id
                 HAVING total_resenas > 0
                 ORDER BY total_resenas DESC
                 LIMIT 5";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($usuario) {
            return [
                'id' => (int)$usuario['id'],
                'nombre_usuario' => $usuario['nombre_usuario'],
                'nombre_completo' => $usuario['nombre_completo'],
                'total_resenas' => (int)$usuario['total_resenas']
            ];
        }, $usuarios);
    } catch (Exception $e) {
        return [];
    }
}

// obtener reseñas destacadas
function obtenerResenasDestacadas($conn) {
    try {
        $sql = "SELECT 
                    r.id, r.titulo as titulo_resena, r.puntuacion, r.likes,
                    u.nombre_usuario as autor,
                    p.titulo as pelicula_titulo
                  FROM resenas r
                  INNER JOIN usuarios u ON r.id_usuario = u.id
                  INNER JOIN peliculas p ON r.id_pelicula = p.id
                  WHERE r.activo = 1 AND u.activo = 1 AND p.activo = 1
                  ORDER BY r.likes DESC
                  LIMIT 5";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $resenas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($resena) {
            return [
                'id' => (int)$resena['id'],
                'titulo_resena' => $resena['titulo_resena'] ?: 'Sin título',
                'puntuacion' => (int)$resena['puntuacion'],
                'likes' => (int)$resena['likes'],
                'autor' => $resena['autor'],
                'pelicula_titulo' => $resena['pelicula_titulo']
            ];
        }, $resenas);
    } catch (Exception $e) {
        return [];
    }
}

// obtener estadísticas simples de géneros
function obtenerGenerosSimple($conn) {
    try {
        $sql = "SELECT 
                    g.nombre as genero,
                    g.color_hex,
                    COUNT(p.id) as total_peliculas
                FROM generos g
                LEFT JOIN peliculas p ON g.id = p.genero_id AND p.activo = 1
                WHERE g.activo = 1
                GROUP BY g.id
                ORDER BY total_peliculas DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $generos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(function($genero) {
            return [
                'nombre' => $genero['genero'],
                'color_hex' => $genero['color_hex'],
                'total_peliculas' => (int)$genero['total_peliculas']
            ];
        }, $generos);
    } catch (Exception $e) {
        return [];
    }
}
?>