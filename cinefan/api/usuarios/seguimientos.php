<?php
require_once '../config/database.php';
require_once '../config/cors.php';
require_once '../includes/auth.php';
require_once '../includes/response.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// obtener metodo de peticion
$metodo = $_SERVER['REQUEST_METHOD'];

// obtener accion de la url
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
        enviarRespuesta(false, 'metodo no permitido', null, 405);
        break;
}

// listar seguidores del usuario
function listarSeguidores() {
    try {
        // verificar autenticacion
        $datosToken = verificarToken();
        if (!$datosToken) {
            enviarRespuesta(false, 'token invalido o expirado', null, 401);
            return;
        }

        $idUsuario = $datosToken['id_usuario'];
        
        // permitir ver seguidores de otro usuario si se especifica
        $idUsuarioTarget = isset($_GET['usuario']) ? (int)$_GET['usuario'] : $idUsuario;
        
        $db = conectarDB();

        // consulta para obtener seguidores
        $sql = "SELECT 
                    s.id,
                    s.fecha_seguimiento,
                    u.id as id_usuario,
                    u.nombre_usuario,
                    u.nombre_completo,
                    u.email,
                    u.fecha_registro,
                    (SELECT COUNT(*) FROM peliculas WHERE id_usuario = u.id AND activo = 1) as total_peliculas,
                    (SELECT COUNT(*) FROM resenas WHERE id_usuario = u.id AND activo = 1) as total_resenas,
                    (SELECT COUNT(*) FROM seguimientos WHERE id_seguido = u.id AND activo = 1) as total_seguidores
                FROM seguimientos s
                INNER JOIN usuarios u ON s.id_seguidor = u.id
                WHERE s.id_seguido = ? AND s.activo = 1 AND u.activo = 1
                ORDER BY s.fecha_seguimiento DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute([$idUsuarioTarget]);
        $seguidores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // formatear datos
        $seguidoresFormateados = [];
        foreach ($seguidores as $seguidor) {
            $seguidoresFormateados[] = [
                'id_seguimiento' => (int)$seguidor['id'],
                'fecha_seguimiento' => $seguidor['fecha_seguimiento'],
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

        enviarRespuesta(true, 'seguidores obtenidos exitosamente', [
            'seguidores' => $seguidoresFormateados,
            'total' => count($seguidoresFormateados),
            'usuario_target' => $idUsuarioTarget
        ]);

    } catch (Exception $e) {
        error_log("error obteniendo seguidores: " . $e->getMessage());
        enviarRespuesta(false, 'error interno del servidor', null, 500);
    }
}

// listar usuarios que sigue el usuario
function listarSiguiendo() {
    try {
        // verificar autenticacion
        $datosToken = verificarToken();
        if (!$datosToken) {
            enviarRespuesta(false, 'token invalido o expirado', null, 401);
            return;
        }

        $idUsuario = $datosToken['id_usuario'];
        
        // permitir ver siguiendo de otro usuario si se especifica
        $idUsuarioTarget = isset($_GET['usuario']) ? (int)$_GET['usuario'] : $idUsuario;
        
        $db = conectarDB();

        // consulta para obtener usuarios seguidos
        $sql = "SELECT 
                    s.id,
                    s.fecha_seguimiento,
                    u.id as id_usuario,
                    u.nombre_usuario,
                    u.nombre_completo,
                    u.email,
                    u.fecha_registro,
                    (SELECT COUNT(*) FROM peliculas WHERE id_usuario = u.id AND activo = 1) as total_peliculas,
                    (SELECT COUNT(*) FROM resenas WHERE id_usuario = u.id AND activo = 1) as total_resenas,
                    (SELECT COUNT(*) FROM seguimientos WHERE id_seguido = u.id AND activo = 1) as total_seguidores
                FROM seguimientos s
                INNER JOIN usuarios u ON s.id_seguido = u.id
                WHERE s.id_seguidor = ? AND s.activo = 1 AND u.activo = 1
                ORDER BY s.fecha_seguimiento DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute([$idUsuarioTarget]);
        $siguiendo = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // formatear datos
        $siguiendoFormateados = [];
        foreach ($siguiendo as $seguido) {
            $siguiendoFormateados[] = [
                'id_seguimiento' => (int)$seguido['id'],
                'fecha_seguimiento' => $seguido['fecha_seguimiento'],
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

        enviarRespuesta(true, 'siguiendo obtenidos exitosamente', [
            'siguiendo' => $siguiendoFormateados,
            'total' => count($siguiendoFormateados),
            'usuario_target' => $idUsuarioTarget
        ]);

    } catch (Exception $e) {
        error_log("error obteniendo siguiendo: " . $e->getMessage());
        enviarRespuesta(false, 'error interno del servidor', null, 500);
    }
}

// obtener estadisticas de seguimientos
function listarEstadisticasSeguimientos() {
    try {
        // verificar autenticacion
        $datosToken = verificarToken();
        if (!$datosToken) {
            enviarRespuesta(false, 'token invalido o expirado', null, 401);
            return;
        }

        $idUsuario = $datosToken['id_usuario'];
        $db = conectarDB();

        // obtener contadores
        $sqlSeguidores = "SELECT COUNT(*) as total FROM seguimientos WHERE id_seguido = ? AND activo = 1";
        $stmtSeguidores = $db->prepare($sqlSeguidores);
        $stmtSeguidores->execute([$idUsuario]);
        $totalSeguidores = $stmtSeguidores->fetchColumn();

        $sqlSiguiendo = "SELECT COUNT(*) as total FROM seguimientos WHERE id_seguidor = ? AND activo = 1";
        $stmtSiguiendo = $db->prepare($sqlSiguiendo);
        $stmtSiguiendo->execute([$idUsuario]);
        $totalSiguiendo = $stmtSiguiendo->fetchColumn();

        enviarRespuesta(true, 'estadisticas obtenidas exitosamente', [
            'total_seguidores' => (int)$totalSeguidores,
            'total_siguiendo' => (int)$totalSiguiendo
        ]);

    } catch (Exception $e) {
        error_log("error obteniendo estadisticas de seguimientos: " . $e->getMessage());
        enviarRespuesta(false, 'error interno del servidor', null, 500);
    }
}

// seguir a un usuario
function seguirUsuario() {
    try {
        // verificar autenticacion
        $datosToken = verificarToken();
        if (!$datosToken) {
            enviarRespuesta(false, 'token invalido o expirado', null, 401);
            return;
        }

        $idSeguidor = $datosToken['id_usuario'];

        // obtener datos de la peticion
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id_usuario'])) {
            enviarRespuesta(false, 'id de usuario requerido', null, 400);
            return;
        }

        $idSeguido = (int)$input['id_usuario'];

        // no puede seguirse a si mismo
        if ($idSeguidor === $idSeguido) {
            enviarRespuesta(false, 'no puedes seguirte a ti mismo', null, 400);
            return;
        }

        $db = conectarDB();

        // verificar que el usuario a seguir existe y esta activo
        $sqlUsuario = "SELECT id, nombre_usuario, nombre_completo FROM usuarios WHERE id = ? AND activo = 1";
        $stmtUsuario = $db->prepare($sqlUsuario);
        $stmtUsuario->execute([$idSeguido]);
        $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            enviarRespuesta(false, 'usuario no encontrado', null, 404);
            return;
        }

        // verificar si ya lo sigue
        $sqlExiste = "SELECT id FROM seguimientos WHERE id_seguidor = ? AND id_seguido = ? AND activo = 1";
        $stmtExiste = $db->prepare($sqlExiste);
        $stmtExiste->execute([$idSeguidor, $idSeguido]);
        
        if ($stmtExiste->fetch()) {
            enviarRespuesta(false, 'ya sigues a este usuario', null, 409);
            return;
        }

        // crear seguimiento
        $sqlInsertar = "INSERT INTO seguimientos (id_seguidor, id_seguido, fecha_seguimiento) VALUES (?, ?, NOW())";
        $stmtInsertar = $db->prepare($sqlInsertar);
        $stmtInsertar->execute([$idSeguidor, $idSeguido]);

        enviarRespuesta(true, 'usuario seguido exitosamente', [
            'id_seguimiento' => $db->lastInsertId(),
            'usuario_seguido' => [
                'id' => $idSeguido,
                'nombre_usuario' => $usuario['nombre_usuario'],
                'nombre_completo' => $usuario['nombre_completo']
            ]
        ]);

    } catch (Exception $e) {
        error_log("error siguiendo usuario: " . $e->getMessage());
        enviarRespuesta(false, 'error interno del servidor', null, 500);
    }
}

// dejar de seguir a un usuario
function dejarDeSeguir() {
    try {
        // verificar autenticacion
        $datosToken = verificarToken();
        if (!$datosToken) {
            enviarRespuesta(false, 'token invalido o expirado', null, 401);
            return;
        }

        $idSeguidor = $datosToken['id_usuario'];

        // obtener id de usuario de parametros url o json
        $idSeguido = null;
        
        if (isset($_GET['id_usuario'])) {
            $idSeguido = (int)$_GET['id_usuario'];
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['id_usuario'])) {
                $idSeguido = (int)$input['id_usuario'];
            }
        }

        if (!$idSeguido) {
            enviarRespuesta(false, 'id de usuario requerido', null, 400);
            return;
        }

        $db = conectarDB();

        // verificar que el seguimiento existe
        $sqlVerificar = "SELECT s.id, u.nombre_usuario, u.nombre_completo 
                        FROM seguimientos s
                        INNER JOIN usuarios u ON s.id_seguido = u.id
                        WHERE s.id_seguidor = ? AND s.id_seguido = ? AND s.activo = 1";
        $stmtVerificar = $db->prepare($sqlVerificar);
        $stmtVerificar->execute([$idSeguidor, $idSeguido]);
        $seguimiento = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

        if (!$seguimiento) {
            enviarRespuesta(false, 'no sigues a este usuario', null, 404);
            return;
        }

        // eliminar seguimiento (soft delete)
        $sqlEliminar = "UPDATE seguimientos SET activo = 0, fecha_eliminado = NOW() 
                       WHERE id_seguidor = ? AND id_seguido = ? AND activo = 1";
        $stmtEliminar = $db->prepare($sqlEliminar);
        $stmtEliminar->execute([$idSeguidor, $idSeguido]);

        enviarRespuesta(true, 'dejaste de seguir al usuario exitosamente', [
            'usuario' => [
                'id' => $idSeguido,
                'nombre_usuario' => $seguimiento['nombre_usuario'],
                'nombre_completo' => $seguimiento['nombre_completo']
            ]
        ]);

    } catch (Exception $e) {
        error_log("error dejando de seguir usuario: " . $e->getMessage());
        enviarRespuesta(false, 'error interno del servidor', null, 500);
    }
}
?>