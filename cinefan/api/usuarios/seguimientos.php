<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// obtener método de petición
$metodo = $_SERVER['REQUEST_METHOD'];

// obtener acción de la URL
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';

switch ($metodo) {
    case 'GET':
        if ($accion === 'seguidores') {
            listarSeguidores();
        } elseif ($accion === 'siguiendo') {
            listarSiguiendo();
        } else {
            listarEstadisticasSeguimientos();
        }
        break;
    case 'POST':
        seguirUsuario();
        break;
    case 'DELETE':
        dejarDeSeguir();
        break;
    default:
        Response::error('Método no permitido', 405);
        break;
}

// listar seguidores del usuario
function listarSeguidores() {
    try {
        // autenticación requerida
        $authData = Auth::requireAuth();
        $userId = $authData['user_id'];
        
        // permitir ver seguidores de otro usuario si se especifica
        $idUsuarioTarget = isset($_GET['usuario']) ? (int)$_GET['usuario'] : $userId;
        $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
        $limite = isset($_GET['limite']) ? min(50, max(1, (int)$_GET['limite'])) : DEFAULT_PAGE_SIZE;
        $offset = ($pagina - 1) * $limite;
        
        $db = getDatabase();
        $conn = $db->getConnection();

        // consulta para obtener seguidores
        $sql = "SELECT 
                    s.id,
                    s.fecha_seguimiento,
                    u.id as id_usuario,
                    u.nombre_usuario,
                    u.nombre_completo,
                    u.email,
                    u.fecha_registro,
                    COUNT(DISTINCT p.id) as total_peliculas,
                    COUNT(DISTINCT r.id) as total_resenas,
                    COUNT(DISTINCT s2.id) as total_seguidores
                FROM seguimientos s
                INNER JOIN usuarios u ON s.id_seguidor = u.id
                LEFT JOIN peliculas p ON u.id = p.id_usuario_creador AND p.activo = true
                LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = true
                LEFT JOIN seguimientos s2 ON u.id = s2.id_seguido
                WHERE s.id_seguido = :user_target AND u.activo = true
                GROUP BY s.id, u.id
                ORDER BY s.fecha_seguimiento DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_target', $idUsuarioTarget);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $seguidores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // obtener total para paginación
        $totalSql = "SELECT COUNT(*) FROM seguimientos s 
                     INNER JOIN usuarios u ON s.id_seguidor = u.id 
                     WHERE s.id_seguido = :user_target AND u.activo = true";
        $totalStmt = $conn->prepare($totalSql);
        $totalStmt->bindParam(':user_target', $idUsuarioTarget);
        $totalStmt->execute();
        $total = $totalStmt->fetchColumn();

        // formatear datos
        $seguidoresFormateados = [];
        foreach ($seguidores as $seguidor) {
            $seguidoresFormateados[] = [
                'id_seguimiento' => (int)$seguidor['id'],
                'fecha_seguimiento' => $seguidor['fecha_seguimiento'],
                'fecha_formateada' => Utils::timeAgo($seguidor['fecha_seguimiento']),
                'usuario' => [
                    'id' => (int)$seguidor['id_usuario'],
                    'nombre_usuario' => $seguidor['nombre_usuario'],
                    'nombre_completo' => $seguidor['nombre_completo'],
                    'email' => $seguidor['email'],
                    'fecha_registro' => $seguidor['fecha_registro'],
                    'estadisticas' => [
                        'total_peliculas' => (int)$seguidor['total_peliculas'],
                        'total_resenas' => (int)$seguidor['total_resenas'],
                        'total_seguidores' => (int)$seguidor['total_seguidores']
                    ]
                ]
            ];
        }

        Response::success([
            'seguidores' => $seguidoresFormateados,
            'paginacion' => [
                'pagina_actual' => $pagina,
                'total_items' => (int)$total,
                'items_por_pagina' => $limite,
                'total_paginas' => ceil($total / $limite)
            ],
            'usuario_target' => $idUsuarioTarget
        ], 'Seguidores obtenidos exitosamente');

    } catch (Exception $e) {
        Utils::log("Error obteniendo seguidores: " . $e->getMessage(), 'ERROR');
        Response::error('Error interno del servidor', 500);
    }
}

// listar usuarios que sigue el usuario
function listarSiguiendo() {
    try {
        // autenticación requerida
        $authData = Auth::requireAuth();
        $userId = $authData['user_id'];
        
        // permitir ver siguiendo de otro usuario si se especifica
        $idUsuarioTarget = isset($_GET['usuario']) ? (int)$_GET['usuario'] : $userId;
        $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
        $limite = isset($_GET['limite']) ? min(50, max(1, (int)$_GET['limite'])) : DEFAULT_PAGE_SIZE;
        $offset = ($pagina - 1) * $limite;
        
        $db = getDatabase();
        $conn = $db->getConnection();

        // consulta para obtener usuarios seguidos
        $sql = "SELECT 
                    s.id,
                    s.fecha_seguimiento,
                    u.id as id_usuario,
                    u.nombre_usuario,
                    u.nombre_completo,
                    u.email,
                    u.fecha_registro,
                    COUNT(DISTINCT p.id) as total_peliculas,
                    COUNT(DISTINCT r.id) as total_resenas,
                    COUNT(DISTINCT s2.id) as total_seguidores
                FROM seguimientos s
                INNER JOIN usuarios u ON s.id_seguido = u.id
                LEFT JOIN peliculas p ON u.id = p.id_usuario_creador AND p.activo = true
                LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = true
                LEFT JOIN seguimientos s2 ON u.id = s2.id_seguido
                WHERE s.id_seguidor = :user_target AND u.activo = true
                GROUP BY s.id, u.id
                ORDER BY s.fecha_seguimiento DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_target', $idUsuarioTarget);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $siguiendo = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // obtener total para paginación
        $totalSql = "SELECT COUNT(*) FROM seguimientos s 
                     INNER JOIN usuarios u ON s.id_seguido = u.id 
                     WHERE s.id_seguidor = :user_target AND u.activo = true";
        $totalStmt = $conn->prepare($totalSql);
        $totalStmt->bindParam(':user_target', $idUsuarioTarget);
        $totalStmt->execute();
        $total = $totalStmt->fetchColumn();

        // formatear datos
        $siguiendoFormateados = [];
        foreach ($siguiendo as $seguido) {
            $siguiendoFormateados[] = [
                'id_seguimiento' => (int)$seguido['id'],
                'fecha_seguimiento' => $seguido['fecha_seguimiento'],
                'fecha_formateada' => Utils::timeAgo($seguido['fecha_seguimiento']),
                'usuario' => [
                    'id' => (int)$seguido['id_usuario'],
                    'nombre_usuario' => $seguido['nombre_usuario'],
                    'nombre_completo' => $seguido['nombre_completo'],
                    'email' => $seguido['email'],
                    'fecha_registro' => $seguido['fecha_registro'],
                    'estadisticas' => [
                        'total_peliculas' => (int)$seguido['total_peliculas'],
                        'total_resenas' => (int)$seguido['total_resenas'],
                        'total_seguidores' => (int)$seguido['total_seguidores']
                    ]
                ]
            ];
        }

        Response::success([
            'siguiendo' => $siguiendoFormateados,
            'paginacion' => [
                'pagina_actual' => $pagina,
                'total_items' => (int)$total,
                'items_por_pagina' => $limite,
                'total_paginas' => ceil($total / $limite)
            ],
            'usuario_target' => $idUsuarioTarget
        ], 'Siguiendo obtenidos exitosamente');

    } catch (Exception $e) {
        Utils::log("Error obteniendo siguiendo: " . $e->getMessage(), 'ERROR');
        Response::error('Error interno del servidor', 500);
    }
}

// obtener estadísticas de seguimientos
function listarEstadisticasSeguimientos() {
    try {
        // autenticación requerida
        $authData = Auth::requireAuth();
        $userId = $authData['user_id'];
        
        $db = getDatabase();
        $conn = $db->getConnection();

        // obtener contadores
        $sqlSeguidores = "SELECT COUNT(*) as total FROM seguimientos WHERE id_seguido = :user_id";
        $stmtSeguidores = $conn->prepare($sqlSeguidores);
        $stmtSeguidores->bindParam(':user_id', $userId);
        $stmtSeguidores->execute();
        $totalSeguidores = $stmtSeguidores->fetchColumn();

        $sqlSiguiendo = "SELECT COUNT(*) as total FROM seguimientos WHERE id_seguidor = :user_id";
        $stmtSiguiendo = $conn->prepare($sqlSiguiendo);
        $stmtSiguiendo->bindParam(':user_id', $userId);
        $stmtSiguiendo->execute();
        $totalSiguiendo = $stmtSiguiendo->fetchColumn();

        Response::success([
            'total_seguidores' => (int)$totalSeguidores,
            'total_siguiendo' => (int)$totalSiguiendo
        ], 'Estadísticas obtenidas exitosamente');

    } catch (Exception $e) {
        Utils::log("Error obteniendo estadísticas de seguimientos: " . $e->getMessage(), 'ERROR');
        Response::error('Error interno del servidor', 500);
    }
}

// seguir usuario
function seguirUsuario() {
    try {
        // autenticación requerida
        $authData = Auth::requireAuth();
        $userId = $authData['user_id'];

        // obtener datos de la petición
        $input = Response::getJsonInput();
        
        if (!isset($input['id_usuario'])) {
            Response::error('ID de usuario requerido', 400);
        }

        $idUsuarioSeguir = (int)$input['id_usuario'];
        
        if ($idUsuarioSeguir === $userId) {
            Response::error('No puedes seguirte a ti mismo', 400);
        }

        $db = getDatabase();
        $conn = $db->getConnection();

        // verificar que el usuario a seguir existe
        $sqlUsuario = "SELECT id, nombre_usuario FROM usuarios WHERE id = :id AND activo = true";
        $stmtUsuario = $conn->prepare($sqlUsuario);
        $stmtUsuario->bindParam(':id', $idUsuarioSeguir);
        $stmtUsuario->execute();
        $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            Response::error('Usuario no encontrado', 404);
        }

        // verificar si ya lo sigue
        $sqlExiste = "SELECT id FROM seguimientos WHERE id_seguidor = :seguidor AND id_seguido = :seguido";
        $stmtExiste = $conn->prepare($sqlExiste);
        $stmtExiste->bindParam(':seguidor', $userId);
        $stmtExiste->bindParam(':seguido', $idUsuarioSeguir);
        $stmtExiste->execute();
        
        if ($stmtExiste->fetch()) {
            Response::error('Ya sigues a este usuario', 409);
        }

        // crear seguimiento
        $sqlInsertar = "INSERT INTO seguimientos (id_seguidor, id_seguido, fecha_seguimiento) VALUES (:seguidor, :seguido, NOW())";
        $stmtInsertar = $conn->prepare($sqlInsertar);
        $stmtInsertar->bindParam(':seguidor', $userId);
        $stmtInsertar->bindParam(':seguido', $idUsuarioSeguir);
        $stmtInsertar->execute();

        Response::success([
            'id_seguimiento' => $conn->lastInsertId(),
            'usuario_seguido' => $usuario['nombre_usuario']
        ], 'Usuario seguido exitosamente');

    } catch (Exception $e) {
        Utils::log("Error siguiendo usuario: " . $e->getMessage(), 'ERROR');
        Response::error('Error interno del servidor', 500);
    }
}

// dejar de seguir usuario
function dejarDeSeguir() {
    try {
        // autenticación requerida
        $authData = Auth::requireAuth();
        $userId = $authData['user_id'];

        // obtener id de usuario de parámetros URL o JSON
        $idUsuarioDejarSeguir = null;
        
        if (isset($_GET['id_usuario'])) {
            $idUsuarioDejarSeguir = (int)$_GET['id_usuario'];
        } else {
            $input = Response::getJsonInput();
            if (isset($input['id_usuario'])) {
                $idUsuarioDejarSeguir = (int)$input['id_usuario'];
            }
        }

        if (!$idUsuarioDejarSeguir) {
            Response::error('ID de usuario requerido', 400);
        }

        $db = getDatabase();
        $conn = $db->getConnection();

        // verificar que el seguimiento existe
        $sqlVerificar = "SELECT s.id, u.nombre_usuario 
                        FROM seguimientos s
                        INNER JOIN usuarios u ON s.id_seguido = u.id
                        WHERE s.id_seguidor = :seguidor AND s.id_seguido = :seguido";
        
        $stmtVerificar = $conn->prepare($sqlVerificar);
        $stmtVerificar->bindParam(':seguidor', $userId);
        $stmtVerificar->bindParam(':seguido', $idUsuarioDejarSeguir);
        $stmtVerificar->execute();
        
        $seguimiento = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

        if (!$seguimiento) {
            Response::error('No sigues a este usuario', 404);
        }

        // eliminar seguimiento
        $sqlEliminar = "DELETE FROM seguimientos WHERE id_seguidor = :seguidor AND id_seguido = :seguido";
        $stmtEliminar = $conn->prepare($sqlEliminar);
        $stmtEliminar->bindParam(':seguidor', $userId);
        $stmtEliminar->bindParam(':seguido', $idUsuarioDejarSeguir);
        $stmtEliminar->execute();

        Response::success([
            'usuario_dejado_seguir' => $seguimiento['nombre_usuario']
        ], 'Has dejado de seguir al usuario exitosamente');

    } catch (Exception $e) {
        Utils::log("Error dejando de seguir usuario: " . $e->getMessage(), 'ERROR');
        Response::error('Error interno del servidor', 500);
    }
}