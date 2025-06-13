<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// Validar método HTTP
Response::validateMethod(['GET']);

try {
    // Autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    // Obtener parámetros de paginación con validación
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $limite = isset($_GET['limite']) ? min(50, max(1, (int)$_GET['limite'])) : 10;
    $offset = ($pagina - 1) * $limite;
    
    // Log de inicio de proceso
    Utils::log("Iniciando carga de feed - Usuario: {$userId}, Página: {$pagina}", 'INFO');
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // CONSULTA CORREGIDA - Elimina referencias a tablas/campos inexistentes
    $sql = "SELECT 
                r.id,
                r.puntuacion,
                r.texto_resena,
                r.fecha_resena as fecha_creacion,
                r.likes_count,
                
                -- Datos del usuario
                u.id as usuario_id,
                u.nombre_usuario,
                u.nombre_completo,
                u.avatar_url as imagen_perfil,
                
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
                g.color_hex as color_genero,
                
                -- Verificar si el usuario actual ha dado like (CORREGIDO)
                CASE WHEN rl.id IS NOT NULL THEN 1 ELSE 0 END as usuario_dio_like
                
            FROM resenas r
            INNER JOIN usuarios u ON r.id_usuario = u.id
            INNER JOIN peliculas p ON r.id_pelicula = p.id
            LEFT JOIN generos g ON p.genero_id = g.id
            LEFT JOIN resenas_likes rl ON r.id = rl.id_resena AND rl.id_usuario = :user_id
            
            WHERE r.activo = 1 
            AND p.activo = 1 
            AND u.activo = 1
            
            ORDER BY r.fecha_resena DESC
            LIMIT :limite OFFSET :offset";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $resenas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total de reseñas para paginación (CONSULTA OPTIMIZADA)
    $countSql = "SELECT COUNT(*) as total 
                 FROM resenas r
                 INNER JOIN peliculas p ON r.id_pelicula = p.id
                 INNER JOIN usuarios u ON r.id_usuario = u.id
                 WHERE r.activo = 1 AND p.activo = 1 AND u.activo = 1";
    
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
                'color_genero' => $resena['color_genero'] ?? '#6c757d',
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
    
    Utils::log("Feed cargado exitosamente - Usuario: {$userId}, Reseñas: " . count($resenasFormateadas), 'INFO');
    
    http_response_code(200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // Error específico de base de datos
    Utils::log("Error PDO en feed: " . $e->getMessage(), 'ERROR');
    Response::error('Error de base de datos: ' . $e->getMessage(), 500);
    
} catch (Exception $e) {
    // Error general
    Utils::log("Error general en feed: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}
?>