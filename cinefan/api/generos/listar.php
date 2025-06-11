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
    
    // obtener géneros con estadísticas
    $sql = "SELECT 
                g.id,
                g.nombre as genero,
                g.descripcion,
                g.color_hex,
                COUNT(p.id) as total_peliculas,
                COALESCE(AVG((SELECT AVG(puntuacion) FROM resenas WHERE id_pelicula = p.id AND activo = true)), 0) as puntuacion_promedio,
                COUNT(DISTINCT p.id_usuario_creador) as usuarios_contribuyeron
            FROM generos g
            LEFT JOIN peliculas p ON g.id = p.genero_id AND p.activo = true
            WHERE g.activo = true
            GROUP BY g.id
            ORDER BY total_peliculas DESC, g.nombre ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $generos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // formatear datos
    $generosFormateados = [];
    foreach ($generos as $genero) {
        $generosFormateados[] = [
            'id' => (int)$genero['id'],
            'nombre' => $genero['genero'],
            'descripcion' => $genero['descripcion'],
            'color_hex' => $genero['color_hex'],
            'total_peliculas' => (int)$genero['total_peliculas'],
            'puntuacion_promedio' => round($genero['puntuacion_promedio'], 1),
            'usuarios_contribuyeron' => (int)$genero['usuarios_contribuyeron']
        ];
    }
    
    Response::success([
        'generos' => $generosFormateados,
        'total' => count($generosFormateados)
    ], 'Géneros obtenidos exitosamente');
    
} catch (Exception $e) {
    Utils::log("Error obteniendo géneros: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}
?>