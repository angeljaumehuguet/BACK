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
    
    // parámetros de consulta
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'siguiendo'; // siguiendo, seguidores
    $usuario = isset($_GET['usuario']) ? (int)$_GET['usuario'] : $userId;
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $limite = isset($_GET['limite']) ? min(50, max(1, (int)$_GET['limite'])) : DEFAULT_PAGE_SIZE;
    $busqueda = isset($_GET['busqueda']) ? Response::sanitizeInput($_GET['busqueda']) : null;
    $ordenar = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'fecha_desc';
    
    $offset = ($pagina - 1) * $limite;
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    if ($tipo === 'siguiendo') {
        // usuarios que sigue el usuario especificado
        $sql = "SELECT s.id as seguimiento_id, s.fecha_seguimiento,
                       u.id as usuario_id, u.nombre_usuario, u.nombre_completo, u.email, u.avatar_url,
                       u.fecha_registro, u.fecha_ultimo_acceso,
                       COUNT(DISTINCT p.id) as total_peliculas,
                       COUNT(DISTINCT r.id) as total_resenas,
                       COUNT(DISTINCT f.id) as total_favoritos,
                       COUNT(DISTINCT s2.id) as total_seguidores,
                       COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
                       CASE WHEN s_user.id IS NOT NULL THEN 1 ELSE 0 END as es_seguido_por_mi
                FROM seguimientos s
                INNER JOIN usuarios u ON s.id_seguido = u.id
                LEFT JOIN peliculas p ON u.id = p.id_usuario_creador AND p.activo = true
                LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = true
                LEFT JOIN favoritos f ON u.id = f.id_usuario
                LEFT JOIN seguimientos s2 ON u.id = s2.id_seguido AND s2.activo = true
                LEFT JOIN seguimientos s_user ON u.id = s_user.id_seguido AND s_user.id_seguidor = :user_id_check AND s_user.activo = true
                WHERE s.id_seguidor = :user_id_seguidor AND s.activo = true AND u.activo = true";
        
        $params = [
            ':user_id_seguidor' => $usuario,
            ':user_id_check' => $userId
        ];
        
    } else { // seguidores
        // usuarios que siguen al usuario especificado
        $sql = "SELECT s.id as seguimiento_id, s.fecha_seguimiento,
                       u.id as usuario_id, u.nombre_usuario, u.nombre_completo, u.email, u.avatar_url,
                       u.fecha_registro, u.fecha_ultimo_acceso,
                       COUNT(DISTINCT p.id) as total_peliculas,
                       COUNT(DISTINCT r.id) as total_resenas,
                       COUNT(DISTINCT f.id) as total_favoritos,
                       COUNT(DISTINCT s2.id) as total_seguidores,
                       COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
                       CASE WHEN s_user.id IS NOT NULL THEN 1 ELSE 0 END as es_seguido_por_mi
                FROM seguimientos s
                INNER JOIN usuarios u ON s.id_seguidor = u.id
                LEFT JOIN peliculas p ON u.id = p.id_usuario_creador AND p.activo = true
                LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = true
                LEFT JOIN favoritos f ON u.id = f.id_usuario
                LEFT JOIN seguimientos s2 ON u.id = s2.id_seguido AND s2.activo = true
                LEFT JOIN seguimientos s_user ON u.id = s_user.id_seguido AND s_user.id_seguidor = :user_id_check AND s_user.activo = true
                WHERE s.id_seguido = :user_id_seguido AND s.activo = true AND u.activo = true";
        
        $params = [
            ':user_id_seguido' => $usuario,
            ':user_id_check' => $userId
        ];
    }
    
    // filtro de búsqueda
    if ($busqueda) {
        $sql .= " AND (u.nombre_usuario LIKE :busqueda OR u.nombre_completo LIKE :busqueda)";
        $params[':busqueda'] = "%{$busqueda}%";
    }
    
    $sql .= " GROUP BY s.id, u.id";
    
    // ordenamiento
    switch ($ordenar) {
        case 'nombre_asc':
            $sql .= " ORDER BY u.nombre_usuario ASC";
            break;
        case 'nombre_desc':
            $sql .= " ORDER BY u.nombre_usuario DESC";
            break;
        case 'peliculas_desc':
            $sql .= " ORDER BY total_peliculas DESC, u.nombre_usuario ASC";
            break;
        case 'resenas_desc':
            $sql .= " ORDER BY total_resenas DESC, u.nombre_usuario ASC";
            break;
        case 'seguidores_desc':
            $sql .= " ORDER BY total_seguidores DESC, u.nombre_usuario ASC";
            break;
        case 'fecha_asc':
            $sql .= " ORDER BY s.fecha_seguimiento ASC";
            break;
        default: // fecha_desc
            $sql .= " ORDER BY s.fecha_seguimiento DESC";
            break;
    }
    
    // construir consulta de conteo
    if ($tipo === 'siguiendo') {
        $countSql = "SELECT COUNT(DISTINCT s.id) as total
                     FROM seguimientos s
                     INNER JOIN usuarios u ON s.id_seguido = u.id
                     WHERE s.id_seguidor = :user_id_seguidor AND s.activo = true AND u.activo = true";
        $countParams = [':user_id_seguidor' => $usuario];
    } else {
        $countSql = "SELECT COUNT(DISTINCT s.id) as total
                     FROM seguimientos s
                     INNER JOIN usuarios u ON s.id_seguidor = u.id
                     WHERE s.id_seguido = :user_id_seguido AND s.activo = true AND u.activo = true";
        $countParams = [':user_id_seguido' => $usuario];
    }
    
    // agregar filtro de búsqueda al conteo
    if ($busqueda) {
        $countSql .= " AND (u.nombre_usuario LIKE :busqueda OR u.nombre_completo LIKE :busqueda)";
        $countParams[':busqueda'] = "%{$busqueda}%";
    }
    
    // ejecutar consulta de conteo
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($countParams);
    $totalElementos = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // agregar paginación a consulta principal
    $sql .= " LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    
    // bindear parámetros nombrados
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // bindear parámetros de paginación como enteros
    $stmt->bindValue(count($params) + 1, $limite, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $seguimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // formatear datos
    foreach ($seguimientos as &$seguimiento) {
        $seguimiento['fecha_seguimiento_formateada'] = Utils::timeAgo($seguimiento['fecha_seguimiento']);
        $seguimiento['fecha_registro_formateada'] = Utils::timeAgo($seguimiento['fecha_registro']);
        $seguimiento['fecha_ultimo_acceso_formateada'] = $seguimiento['fecha_ultimo_acceso'] 
            ? Utils::timeAgo($seguimiento['fecha_ultimo_acceso']) 
            : 'Nunca';
        $seguimiento['puntuacion_promedio'] = round($seguimiento['puntuacion_promedio'], 1);
        $seguimiento['es_seguido_por_mi'] = (bool)$seguimiento['es_seguido_por_mi'];
        
        // agrupar datos del usuario
        $seguimiento['usuario'] = [
            'id' => (int)$seguimiento['usuario_id'],
            'nombre_usuario' => $seguimiento['nombre_usuario'],
            'nombre_completo' => $seguimiento['nombre_completo'],
            'email' => $seguimiento['email'],
            'avatar_url' => $seguimiento['avatar_url'],
            'fecha_registro' => $seguimiento['fecha_registro'],
            'fecha_ultimo_acceso' => $seguimiento['fecha_ultimo_acceso'],
            'estadisticas' => [
                'peliculas' => (int)$seguimiento['total_peliculas'],
                'resenas' => (int)$seguimiento['total_resenas'],
                'favoritos' => (int)$seguimiento['total_favoritos'],
                'seguidores' => (int)$seguimiento['total_seguidores'],
                'puntuacion_promedio' => $seguimiento['puntuacion_promedio']
            ]
        ];
        
        // información del seguimiento
        $seguimiento['seguimiento'] = [
            'id' => (int)$seguimiento['seguimiento_id'],
            'fecha' => $seguimiento['fecha_seguimiento'],
            'fecha_formateada' => $seguimiento['fecha_seguimiento_formateada']
        ];
        
        // limpiar campos duplicados
        unset($seguimiento['usuario_id'], $seguimiento['nombre_usuario'], $seguimiento['nombre_completo'],
              $seguimiento['email'], $seguimiento['avatar_url'], $seguimiento['fecha_registro'],
              $seguimiento['fecha_ultimo_acceso'], $seguimiento['total_peliculas'], $seguimiento['total_resenas'],
              $seguimiento['total_favoritos'], $seguimiento['total_seguidores'], $seguimiento['seguimiento_id'],
              $seguimiento['fecha_seguimiento'], $seguimiento['fecha_seguimiento_formateada'],
              $seguimiento['fecha_registro_formateada'], $seguimiento['fecha_ultimo_acceso_formateada'],
              $seguimiento['puntuacion_promedio']);
    }
    
    $mensaje = $tipo === 'siguiendo' ? 'Usuarios seguidos obtenidos exitosamente' : 'Seguidores obtenidos exitosamente';
    
    Response::paginated($seguimientos, $pagina, $totalElementos, $limite, $mensaje, [
        'tipo' => $tipo,
        'usuario_target' => $usuario,
        'es_propio' => ($usuario === $userId)
    ]);
    
} catch (Exception $e) {
    Utils::log("Error en listar seguimientos: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}