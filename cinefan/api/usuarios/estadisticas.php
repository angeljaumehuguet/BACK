<?php
require_once '../config/cors.php';
require_once '../config/database.php';

// validar método
Response::validateMethod(['GET']);

try {
    // autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    // obtener parámetros opcionales
    $targetUserId = isset($_GET['usuario']) ? (int)$_GET['usuario'] : $userId;
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // estadísticas generales
    $sql = "CALL GetUsuarioEstadisticas(:user_id)";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $targetUserId);
    $stmt->execute();
    
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    
    // estadísticas por género
    $generosSql = "SELECT g.nombre as genero, COUNT(r.id) as total_resenas, 
                          AVG(r.puntuacion) as puntuacion_promedio
                   FROM resenas r
                   INNER JOIN peliculas p ON r.id_pelicula = p.id
                   INNER JOIN generos g ON p.genero_id = g.id
                   WHERE r.id_usuario = :user_id AND r.activo = true
                   GROUP BY g.id, g.nombre
                   ORDER BY total_resenas DESC";
    
    $generosStmt = $conn->prepare($generosSql);
    $generosStmt->bindParam(':user_id', $targetUserId);
    $generosStmt->execute();
    
    $estadisticasGeneros = $generosStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // reseñas recientes
    $recentesSql = "SELECT p.titulo, r.puntuacion, r.fecha_resena
                    FROM resenas r
                    INNER JOIN peliculas p ON r.id_pelicula = p.id
                    WHERE r.id_usuario = :user_id AND r.activo = true
                    ORDER BY r.fecha_resena DESC
                    LIMIT 5";
    
    $recientesStmt = $conn->prepare($recentesSql);
    $recientesStmt->bindParam(':user_id', $targetUserId);
    $recientesStmt->execute();
    
    $resenasRecientes = $recientesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // formatear datos
    foreach ($estadisticasGeneros as &$genero) {
        $genero['puntuacion_promedio'] = round($genero['puntuacion_promedio'], 1);
    }
    
    foreach ($resenasRecientes as &$resena) {
        $resena['fecha_formateada'] = Utils::timeAgo($resena['fecha_resena']);
    }
    
    $result = [
        'generales' => $estadisticas,
        'por_generos' => $estadisticasGeneros,
        'resenas_recientes' => $resenasRecientes
    ];
    
    Response::success($result, 'Estadísticas obtenidas exitosamente');
    
} catch (Exception $e) {
    Utils::log("Error en estadísticas: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}