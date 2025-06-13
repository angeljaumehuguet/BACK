<?php
require_once '../config/database.php';
require_once '../config/cors.php';
require_once '../includes/auth.php';
require_once '../includes/response.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// obtener metodo de peticion
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {
    case 'GET':
        obtenerNotificaciones();
        break;
    case 'PUT':
        marcarComoLeida();
        break;
    case 'POST':
        if (isset($_GET['accion'])) {
            $accion = $_GET['accion'];
            if ($accion === 'marcar_todas_leidas') {
                marcarTodasComoLeidas();
            } elseif ($accion === 'crear') {
                crearNotificacion();
            } else {
                enviarRespuesta(false, 'accion no valida', null, 400);
            }
        } else {
            enviarRespuesta(false, 'accion requerida', null, 400);
        }
        break;
    case 'DELETE':
        eliminarNotificacion();
        break;
    default:
        enviarRespuesta(false, 'metodo no permitido', null, 405);
        break;
}

// obtener notificaciones del usuario
function obtenerNotificaciones() {
    try {
        // verificar autenticacion
        $datosToken = verificarToken();
        if (!$datosToken) {
            enviarRespuesta(false, 'token invalido o expirado', null, 401);
            return;
        }

        $idUsuario = $datosToken['id_usuario'];

        // parametros opcionales
        $pagina = max(1, $_GET['pagina'] ?? 1);
        $limite = min(50, max(1, $_GET['limite'] ?? 20));
        $soloNoLeidas = isset($_GET['solo_no_leidas']) && $_GET['solo_no_leidas'] === 'true';
        $tipo = $_GET['tipo'] ?? '';
        
        $offset = ($pagina - 1) * $limite;
        $db = conectarDB();

        // construir consulta
        $sql = "SELECT 
                    id,
                    tipo,
                    titulo,
                    mensaje,
                    datos_adicionales,
                    leida,
                    fecha_creacion,
                    fecha_lectura
                FROM notificaciones 
                WHERE id_usuario_destinatario = ? AND activo = 1";
        
        $params = [$idUsuario];

        // aplicar filtros
        if ($soloNoLeidas) {
            $sql .= " AND leida = 0";
        }

        if (!empty($tipo)) {
            $sql .= " AND tipo = ?";
            $params[] = $tipo;
        }

        // ordenar y paginar
        $sql .= " ORDER BY fecha_creacion DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // contar total
        $sqlCount = "SELECT COUNT(*) FROM notificaciones 
                     WHERE id_usuario_destinatario = ? AND activo = 1";
        $paramsCount = [$idUsuario];

        if ($soloNoLeidas) {
            $sqlCount .= " AND leida = 0";
        }

        if (!empty($tipo)) {
            $sqlCount .= " AND tipo = ?";
            $paramsCount[] = $tipo;
        }

        $stmtCount = $db->prepare($sqlCount);
        $stmtCount->execute($paramsCount);
        $totalNotificaciones = $stmtCount->fetchColumn();

        // contar no leidas
        $sqlNoLeidas = "SELECT COUNT(*) FROM notificaciones 
                       WHERE id_usuario_destinatario = ? AND activo = 1 AND leida = 0";
        $stmtNoLeidas = $db->prepare($sqlNoLeidas);
        $stmtNoLeidas->execute([$idUsuario]);
        $totalNoLeidas = $stmtNoLeidas->fetchColumn();

        // formatear notificaciones
        $notificacionesFormateadas = [];
        foreach ($notificaciones as $notif) {
            $datosAdicionales = null;
            if ($notif['datos_adicionales']) {
                $datosAdicionales = json_decode($notif['datos_adicionales'], true);
            }

            $notificacionesFormateadas[] = [
                'id' => (int)$notif['id'],
                'tipo' => $notif['tipo'],
                'titulo' => $notif['titulo'],
                'mensaje' => $notif['mensaje'],
                'datos_adicionales' => $datosAdicionales,
                'leida' => (bool)$notif['leida'],
                'fecha_creacion' => $notif['fecha_creacion'],
                'fecha_lectura' => $notif['fecha_lectura'],
                'tiempo_transcurrido' => calcularTiempoTranscurrido($notif['fecha_creacion'])
            ];
        }

        enviarRespuesta(true, 'notificaciones obtenidas exitosamente', [
            'notificaciones' => $notificacionesFormateadas,
            'total' => (int)$totalNotificaciones,
            'total_no_leidas' => (int)$totalNoLeidas,
            'pagina_actual' => $pagina,
            'total_paginas' => ceil($totalNotificaciones / $limite),
            'limite' => $limite
        ]);

    } catch (Exception $e) {
        error_log("error obteniendo notificaciones: " . $e->getMessage());
        enviarRespuesta(false, 'error interno del servidor', null, 500);
    }
}

// marcar notificacion como leida
function marcarComoLeida() {
    try {
        // verificar autenticacion
        $datosToken = verificarToken();
        if (!$datosToken) {
            enviarRespuesta(false, 'token invalido o expirado', null, 401);
            return;
        }

        $idUsuario = $datosToken['id_usuario'];

        // obtener id de notificacion
        $idNotificacion = $_GET['id'] ?? null;
        if (!$idNotificacion) {
            enviarRespuesta(false, 'id de notificacion requerido', null, 400);
            return;
        }

        $db = conectarDB();

        // verificar que la notificacion pertenece al usuario
        $sqlVerificar = "SELECT id FROM notificaciones 
                        WHERE id = ? AND id_usuario_destinatario = ? AND activo = 1";
        $stmtVerificar = $db->prepare($sqlVerificar);
        $stmtVerificar->execute([$idNotificacion, $idUsuario]);

        if (!$stmtVerificar->fetch()) {
            enviarRespuesta(false, 'notificacion no encontrada', null, 404);
            return;
        }

        // marcar como leida
        $sqlActualizar = "UPDATE notificaciones 
                         SET leida = 1, fecha_lectura = NOW() 
                         WHERE id = ?";
        $stmtActualizar = $db->prepare($sqlActualizar);
        $stmtActualizar->execute([$idNotificacion]);

        enviarRespuesta(true, 'notificacion marcada como leida', [
            'id_notificacion' => (int)$idNotificacion
        ]);

    } catch (Exception $e) {
        error_log("error marcando notificacion como leida: " . $e->getMessage());
        enviarRespuesta(false, 'error interno del servidor', null, 500);
    }
}

// marcar todas las notificaciones como leidas
function marcarTodasComoLeidas() {
    try {
        // verificar autenticacion
        $datosToken = verificarToken();
        if (!$datosToken) {
            enviarRespuesta(false, 'token invalido o expirado', null, 401);
            return;
        }

        $idUsuario = $datosToken['id_usuario'];
        $db = conectarDB();

        // marcar todas como leidas
        $sql = "UPDATE notificaciones 
                SET leida = 1, fecha_lectura = NOW() 
                WHERE id_usuario_destinatario = ? AND leida = 0 AND activo = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$idUsuario]);

        $notificacionesActualizadas = $stmt->rowCount();

        enviarRespuesta(true, 'todas las notificaciones marcadas como leidas', [
            'notificaciones_actualizadas' => $notificacionesActualizadas
        ]);

    } catch (Exception $e) {
        error_log("error marcando todas las notificaciones como leidas: " . $e->getMessage());
        enviarRespuesta(false, 'error interno del servidor', null, 500);
    }
}

// crear notificacion (para uso interno del sistema)
function crearNotificacion() {
    try {
        // verificar autenticacion (solo administradores pueden crear notificaciones manuales)
        $datosToken = verificarToken();
        if (!$datosToken) {
            enviarRespuesta(false, 'token invalido o expirado', null, 401);
            return;
        }

        // obtener datos de la peticion
        $input = json_decode(file_get_contents('php://input'), true);
        
        $camposRequeridos = ['id_usuario_destinatario', 'tipo', 'titulo', 'mensaje'];
        foreach ($camposRequeridos as $campo) {
            if (!isset($input[$campo]) || empty($input[$campo])) {
                enviarRespuesta(false, "campo requerido: $campo", null, 400);
                return;
            }
        }

        $idUsuarioDestinatario = (int)$input['id_usuario_destinatario'];
        $tipo = $input['tipo'];
        $titulo = $input['titulo'];
        $mensaje = $input['mensaje'];
        $datosAdicionales = $input['datos_adicionales'] ?? null;

        // validar tipo
        $tiposValidos = ['nuevo_seguidor', 'like_resena', 'respuesta_resena', 'pelicula_favoriteada', 'mencion', 'sistema'];
        if (!in_array($tipo, $tiposValidos)) {
            enviarRespuesta(false, 'tipo de notificacion invalido', null, 400);
            return;
        }

        $db = conectarDB();

        // verificar que el usuario destinatario existe
        $sqlUsuario = "SELECT id FROM usuarios WHERE id = ? AND activo = 1";
        $stmtUsuario = $db->prepare($sqlUsuario);
        $stmtUsuario->execute([$idUsuarioDestinatario]);
        
        if (!$stmtUsuario->fetch()) {
            enviarRespuesta(false, 'usuario destinatario no encontrado', null, 404);
            return;
        }

        // crear notificacion
        $sql = "INSERT INTO notificaciones (id_usuario_destinatario, tipo, titulo, mensaje, datos_adicionales) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $idUsuarioDestinatario,
            $tipo,
            $titulo,
            $mensaje,
            $datosAdicionales ? json_encode($datosAdicionales) : null
        ]);

        $idNotificacion = $db->lastInsertId();

        enviarRespuesta(true, 'notificacion creada exitosamente', [
            'id_notificacion' => (int)$idNotificacion,
            'tipo' => $tipo,
            'titulo' => $titulo
        ]);

    } catch (Exception $e) {
        error_log("error creando notificacion: " . $e->getMessage());
        enviarRespuesta(false, 'error interno del servidor', null, 500);
    }
}

// eliminar notificacion
function eliminarNotificacion() {
    try {
        // verificar autenticacion
        $datosToken = verificarToken();
        if (!$datosToken) {
            enviarRespuesta(false, 'token invalido o expirado', null, 401);
            return;
        }

        $idUsuario = $datosToken['id_usuario'];

        // obtener id de notificacion
        $idNotificacion = $_GET['id'] ?? null;
        if (!$idNotificacion) {
            enviarRespuesta(false, 'id de notificacion requerido', null, 400);
            return;
        }

        $db = conectarDB();

        // verificar que la notificacion pertenece al usuario
        $sqlVerificar = "SELECT id FROM notificaciones 
                        WHERE id = ? AND id_usuario_destinatario = ? AND activo = 1";
        $stmtVerificar = $db->prepare($sqlVerificar);
        $stmtVerificar->execute([$idNotificacion, $idUsuario]);

        if (!$stmtVerificar->fetch()) {
            enviarRespuesta(false, 'notificacion no encontrada', null, 404);
            return;
        }

        // marcar como inactiva (soft delete)
        $sqlEliminar = "UPDATE notificaciones SET activo = 0 WHERE id = ?";
        $stmtEliminar = $db->prepare($sqlEliminar);
        $stmtEliminar->execute([$idNotificacion]);

        enviarRespuesta(true, 'notificacion eliminada exitosamente', [
            'id_notificacion' => (int)$idNotificacion
        ]);

    } catch (Exception $e) {
        error_log("error eliminando notificacion: " . $e->getMessage());
        enviarRespuesta(false, 'error interno del servidor', null, 500);
    }
}

// funcion auxiliar para calcular tiempo transcurrido
function calcularTiempoTranscurrido($fechaCreacion) {
    $fecha = new DateTime($fechaCreacion);
    $ahora = new DateTime();
    $intervalo = $ahora->diff($fecha);

    if ($intervalo->days > 0) {
        if ($intervalo->days == 1) {
            return 'Hace 1 día';
        } else {
            return 'Hace ' . $intervalo->days . ' días';
        }
    } elseif ($intervalo->h > 0) {
        if ($intervalo->h == 1) {
            return 'Hace 1 hora';
        } else {
            return 'Hace ' . $intervalo->h . ' horas';
        }
    } elseif ($intervalo->i > 0) {
        if ($intervalo->i == 1) {
            return 'Hace 1 minuto';
        } else {
            return 'Hace ' . $intervalo->i . ' minutos';
        }
    } else {
        return 'Hace unos momentos';
    }
}

// funcion auxiliar para crear notificacion automática (para uso en otros endpoints)
function crearNotificacionAutomatica($idUsuarioDestinatario, $tipo, $titulo, $mensaje, $datosAdicionales = null) {
    try {
        $db = conectarDB();
        
        $sql = "INSERT INTO notificaciones (id_usuario_destinatario, tipo, titulo, mensaje, datos_adicionales) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $idUsuarioDestinatario,
            $tipo,
            $titulo,
            $mensaje,
            $datosAdicionales ? json_encode($datosAdicionales) : null
        ]);

        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log("error creando notificacion automatica: " . $e->getMessage());
        return false;
    }
}
?>

---

<?php
// api/includes/notificaciones_helper.php - funciones auxiliares para notificaciones

require_once __DIR__ . '/../config/database.php';

// crear notificacion de nuevo seguidor
function notificarNuevoSeguidor($idUsuarioSeguido, $idUsuarioSeguidor, $nombreUsuarioSeguidor) {
    $titulo = 'Nuevo seguidor';
    $mensaje = "@{$nombreUsuarioSeguidor} ha comenzado a seguirte";
    $datosAdicionales = [
        'id_usuario_seguidor' => $idUsuarioSeguidor,
        'nombre_usuario_seguidor' => $nombreUsuarioSeguidor,
        'accion' => 'seguir'
    ];
    
    return crearNotificacionAutomatica($idUsuarioSeguido, 'nuevo_seguidor', $titulo, $mensaje, $datosAdicionales);
}

// crear notificacion de like en resena
function notificarLikeResena($idUsuarioAutorResena, $idUsuarioQueDioLike, $nombreUsuarioQueDioLike, $idResena, $tituloResena) {
    $titulo = 'Like en tu reseña';
    $mensaje = "A @{$nombreUsuarioQueDioLike} le gustó tu reseña de \"{$tituloResena}\"";
    $datosAdicionales = [
        'id_usuario_like' => $idUsuarioQueDioLike,
        'nombre_usuario_like' => $nombreUsuarioQueDioLike,
        'id_resena' => $idResena,
        'titulo_resena' => $tituloResena,
        'accion' => 'like_resena'
    ];
    
    return crearNotificacionAutomatica($idUsuarioAutorResena, 'like_resena', $titulo, $mensaje, $datosAdicionales);
}

// crear notificacion de pelicula favoriteada
function notificarPeliculaFavoriteada($idUsuarioCreadorPelicula, $idUsuarioQueFavoriteo, $nombreUsuarioQueFavoriteo, $idPelicula, $tituloPelicula) {
    $titulo = 'Tu película fue agregada a favoritos';
    $mensaje = "@{$nombreUsuarioQueFavoriteo} agregó \"{$tituloPelicula}\" a sus favoritos";
    $datosAdicionales = [
        'id_usuario_favorito' => $idUsuarioQueFavoriteo,
        'nombre_usuario_favorito' => $nombreUsuarioQueFavoriteo,
        'id_pelicula' => $idPelicula,
        'titulo_pelicula' => $tituloPelicula,
        'accion' => 'favorito'
    ];
    
    return crearNotificacionAutomatica($idUsuarioCreadorPelicula, 'pelicula_favoriteada', $titulo, $mensaje, $datosAdicionales);
}

// crear notificacion de comentario en resena
function notificarComentarioResena($idUsuarioAutorResena, $idUsuarioComentario, $nombreUsuarioComentario, $idResena, $tituloResena, $textoComentario) {
    $titulo = 'Nuevo comentario en tu reseña';
    $mensaje = "@{$nombreUsuarioComentario} comentó en tu reseña de \"{$tituloResena}\"";
    $datosAdicionales = [
        'id_usuario_comentario' => $idUsuarioComentario,
        'nombre_usuario_comentario' => $nombreUsuarioComentario,
        'id_resena' => $idResena,
        'titulo_resena' => $tituloResena,
        'texto_comentario' => substr($textoComentario, 0, 100),
        'accion' => 'comentario_resena'
    ];
    
    return crearNotificacionAutomatica($idUsuarioAutorResena, 'respuesta_resena', $titulo, $mensaje, $datosAdicionales);
}

// crear notificacion del sistema
function notificarSistema($idUsuario, $titulo, $mensaje, $datosAdicionales = null) {
    return crearNotificacionAutomatica($idUsuario, 'sistema', $titulo, $mensaje, $datosAdicionales);
}

// notificar a todos los usuarios (para anuncios)
function notificarTodosLosUsuarios($titulo, $mensaje, $datosAdicionales = null) {
    try {
        $db = conectarDB();
        
        // obtener todos los usuarios activos
        $sql = "SELECT id FROM usuarios WHERE activo = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $notificacionesCreadas = 0;
        foreach ($usuarios as $idUsuario) {
            if (crearNotificacionAutomatica($idUsuario, 'sistema', $titulo, $mensaje, $datosAdicionales)) {
                $notificacionesCreadas++;
            }
        }
        
        return $notificacionesCreadas;
    } catch (Exception $e) {
        error_log("error notificando a todos los usuarios: " . $e->getMessage());
        return 0;
    }
}

// limpiar notificaciones antiguas de un usuario
function limpiarNotificacionesAntiguas($idUsuario, $diasConservar = 30) {
    try {
        $db = conectarDB();
        
        $sql = "UPDATE notificaciones 
                SET activo = 0 
                WHERE id_usuario_destinatario = ? 
                AND leida = 1 
                AND fecha_lectura < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$idUsuario, $diasConservar]);
        
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("error limpiando notificaciones antiguas: " . $e->getMessage());
        return 0;
    }
}

// obtener conteo de notificaciones no leidas
function obtenerConteoNotificacionesNoLeidas($idUsuario) {
    try {
        $db = conectarDB();
        
        $sql = "SELECT COUNT(*) FROM notificaciones 
                WHERE id_usuario_destinatario = ? AND leida = 0 AND activo = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$idUsuario]);
        
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("error obteniendo conteo de notificaciones no leidas: " . $e->getMessage());
        return 0;
    }
}

// marcar notificaciones relacionadas como leidas (ej: cuando se lee una resena)
function marcarNotificacionesRelacionadasComoLeidas($idUsuario, $tipoContenido, $idContenido) {
    try {
        $db = conectarDB();
        
        $sql = "UPDATE notificaciones 
                SET leida = 1, fecha_lectura = NOW() 
                WHERE id_usuario_destinatario = ? 
                AND JSON_EXTRACT(datos_adicionales, '$.\"{$tipoContenido}\"') = ? 
                AND leida = 0 
                AND activo = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$idUsuario, $idContenido]);
        
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("error marcando notificaciones relacionadas como leidas: " . $e->getMessage());
        return 0;
    }
}
?>