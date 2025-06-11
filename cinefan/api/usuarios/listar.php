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
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $limite = isset($_GET['limite']) ? min(50, max(1, (int)$_GET['limite'])) : DEFAULT_PAGE_SIZE;
    $busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : null;
    $estado = isset($_GET['estado']) ? $_GET['estado'] : 'activos'; // activos, inactivos, todos
    $ordenar = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'fecha_desc';
    
    $offset = ($pagina - 1) * $limite;
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // construir consulta base
    $sql = "SELECT u.id, u.nombre_usuario, u.nombre_completo, u.email, u.avatar_url,
                   u.fecha_registro, u.fecha_ultimo_acceso, u.activo,
                   COUNT(DISTINCT p.id) as total_peliculas,
                   COUNT(DISTINCT r.id) as total_resenas,
                   COUNT(DISTINCT f.id) as total_favoritos,
                   COUNT(DISTINCT s1.id) as total_siguiendo,
                   COUNT(DISTINCT s2.id) as total_seguidores,
                   COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio
            FROM usuarios u
            LEFT JOIN peliculas p ON u.id = p.id_usuario_creador AND p.activo = true
            LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = true
            LEFT JOIN favoritos f ON u.id = f.id_usuario
            LEFT JOIN seguimientos s1 ON u.id = s1.id_seguidor AND s1.activo = true
            LEFT JOIN seguimientos s2 ON u.id = s2.id_seguido AND s2.activo = true
            WHERE u.id != ?";
    
    // inicializar parámetros
    $bindParams = [$userId];
    
    // filtro por estado
    switch ($estado) {
        case 'activos':
            $sql .= " AND u.activo = true";
            break;
        case 'inactivos':
            $sql .= " AND u.activo = false";
            break;
        // 'todos' no requiere filtro adicional
    }
    
    // filtro de búsqueda
    if ($busqueda) {
        $sql .= " AND (u.nombre_usuario LIKE ? OR u.nombre_completo LIKE ? OR u.email LIKE ?)";
        $searchTerm = "%{$busqueda}%";
        $bindParams[] = $searchTerm;
        $bindParams[] = $searchTerm;
        $bindParams[] = $searchTerm;
    }
    
    $sql .= " GROUP BY u.id";
    
    // ordenamiento
    switch ($ordenar) {
        case 'nombre_asc':
            $sql .= " ORDER BY u.nombre_usuario ASC";
            break;
        case 'nombre_desc':
            $sql .= " ORDER BY u.nombre_usuario DESC";
            break;
        case 'email_asc':
            $sql .= " ORDER BY u.email ASC";
            break;
        case 'email_desc':
            $sql .= " ORDER BY u.email DESC";
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
            $sql .= " ORDER BY u.fecha_registro ASC";
            break;
        default: // fecha_desc
            $sql .= " ORDER BY u.fecha_registro DESC";
            break;
    }
    
    // construir consulta de conteo
    $countSql = "SELECT COUNT(DISTINCT u.id) as total
                 FROM usuarios u
                 WHERE u.id != ?";
    
    // preparar parámetros para el conteo
    $countParams = [$userId];
    
    // agregar filtros al conteo
    switch ($estado) {
        case 'activos':
            $countSql .= " AND u.activo = true";
            break;
        case 'inactivos':
            $countSql .= " AND u.activo = false";
            break;
    }
    
    if ($busqueda) {
        $countSql .= " AND (u.nombre_usuario LIKE ? OR u.nombre_completo LIKE ? OR u.email LIKE ?)";
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
    }
    
    // ejecutar consulta de conteo
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($countParams);
    $totalElementos = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // agregar paginación a consulta principal
    $sql .= " LIMIT ? OFFSET ?";
    $bindParams[] = $limite;
    $bindParams[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($bindParams);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // formatear datos
    foreach ($usuarios as &$usuario) {
        $usuario['puntuacion_promedio'] = round($usuario['puntuacion_promedio'], 1);
        $usuario['activo'] = (bool)$usuario['activo'];
        $usuario['fecha_registro_formateada'] = Utils::timeAgo($usuario['fecha_registro']);
        $usuario['fecha_ultimo_acceso_formateada'] = $usuario['fecha_ultimo_acceso'] 
            ? Utils::timeAgo($usuario['fecha_ultimo_acceso']) 
            : 'Nunca';
        
        // verificar si el usuario actual sigue a este usuario
        $seguimientoSql = "SELECT COUNT(*) as siguiendo FROM seguimientos 
                          WHERE id_seguidor = ? AND id_seguido = ? AND activo = true";
        $seguimientoStmt = $conn->prepare($seguimientoSql);
        $seguimientoStmt->execute([$userId, $usuario['id']]);
        $usuario['es_seguido'] = (bool)$seguimientoStmt->fetch(PDO::FETCH_ASSOC)['siguiendo'];
        
        // estadísticas resumidas
        $usuario['estadisticas'] = [
            'peliculas' => (int)$usuario['total_peliculas'],
            'resenas' => (int)$usuario['total_resenas'],
            'favoritos' => (int)$usuario['total_favoritos'],
            'siguiendo' => (int)$usuario['total_siguiendo'],
            'seguidores' => (int)$usuario['total_seguidores'],
            'puntuacion_promedio' => $usuario['puntuacion_promedio']
        ];
        
        // limpiar campos duplicados
        unset($usuario['total_peliculas'], $usuario['total_resenas'], 
              $usuario['total_favoritos'], $usuario['total_siguiendo'], 
              $usuario['total_seguidores']);
    }
    
    Response::paginated($usuarios, $pagina, $totalElementos, $limite, 'Usuarios obtenidos exitosamente');
    
} catch (Exception $e) {
    Utils::log("Error en listar usuarios: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}