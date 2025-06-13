<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// Validar método
Response::validateMethod(['GET']);

try {
    // Autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    // Obtener parámetros de paginación
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $limite = isset($_GET['limite']) ? min(50, max(1, (int)$_GET['limite'])) : 10;
    $offset = ($pagina - 1) * $limite;
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // Consulta para obtener el feed de reseñas con información completa
    $sql = "SELECT 
                r.id,
                r.puntuacion,
                r.texto_resena,
                r.fecha_creacion,
                r.likes_count,
                
                -- Datos del usuario
                u.id as usuario_id,
                u.nombre_usuario,
                u.nombre_completo,
                u.imagen_perfil,
                
                -- Datos de la película
                p.id as pelicula_id,
                p.titulo,
                p.director,
                p.ano_lanzamiento,
                p.duracion_minutos,
                p.imagen_url,
                p.sinopsis,
                
                -- Datos del género
                g.nombre as genero,
                
                -- Verificar si el usuario actual ha dado like
                CASE WHEN rl.id IS NOT NULL THEN true ELSE false END as usuario_dio_like
                
            FROM resenas r
            INNER JOIN usuarios u ON r.id_usuario = u.id
            INNER JOIN peliculas p ON r.id_pelicula = p.id
            LEFT JOIN generos g ON p.genero_id = g.id
            LEFT JOIN resenas_likes rl ON r.id = rl.id_resena AND rl.id_usuario = :user_id
            
            WHERE r.activo = true 
            AND p.activo = true 
            AND u.activo = true
            
            ORDER BY r.fecha_creacion DESC
            LIMIT :limite OFFSET :offset";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $resenas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total de reseñas para paginación
    $countSql = "SELECT COUNT(*) as total 
                 FROM resenas r
                 INNER JOIN peliculas p ON r.id_pelicula = p.id
                 INNER JOIN usuarios u ON r.id_usuario = u.id
                 WHERE r.activo = true AND p.activo = true AND u.activo = true";
    
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute();
    $totalResenas = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Formatear datos para respuesta
    $resenasFormateadas = [];
    foreach ($resenas as $resena) {
        $resenasFormateadas[] = [
            'id' => (int)$resena['id'],
            'puntuacion' => (int)$resena['puntuacion'],
            'texto_resena' => $resena['texto_resena'],
            'fecha_creacion' => $resena['fecha_creacion'],
            'likes_count' => (int)$resena['likes_count'],
            'usuario_dio_like' => (bool)$resena['usuario_dio_like'],
            
            // Información del usuario
            'usuario' => [
                'id' => (int)$resena['usuario_id'],
                'nombre_usuario' => $resena['nombre_usuario'],
                'nombre_completo' => $resena['nombre_completo'],
                'imagen_perfil' => $resena['imagen_perfil']
            ],
            
            // Información de la película
            'pelicula' => [
                'id' => (int)$resena['pelicula_id'],
                'titulo' => $resena['titulo'],
                'director' => $resena['director'],
                'ano_lanzamiento' => (int)$resena['ano_lanzamiento'],
                'duracion_minutos' => (int)$resena['duracion_minutos'],
                'genero' => $resena['genero'] ?? 'Sin género',
                'imagen_url' => $resena['imagen_url'],
                'sinopsis' => $resena['sinopsis']
            ]
        ];
    }
    
    // Calcular información de paginación
    $totalPaginas = ceil($totalResenas / $limite);
    $tieneSiguiente = $pagina < $totalPaginas;
    $tieneAnterior = $pagina > 1;
    
    // Respuesta exitosa con datos de paginación
    $response = [
        'exito' => true,
        'mensaje' => 'Feed obtenido exitosamente',
        'datos' => $resenasFormateadas,
        'paginacion' => [
            'pagina_actual' => $pagina,
            'total_paginas' => $totalPaginas,
            'total_elementos' => (int)$totalResenas,
            'elementos_por_pagina' => $limite,
            'tiene_siguiente' => $tieneSiguiente,
            'tiene_anterior' => $tieneAnterior
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    Utils::log("Feed cargado para usuario {$userId} - Página: {$pagina}, Reseñas: " . count($resenasFormateadas), 'INFO');
    
    http_response_code(200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    Utils::log("Error en feed: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}
?>