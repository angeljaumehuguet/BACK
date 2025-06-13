<?php
session_start();

// verificar autenticacion admin
if (!isset($_SESSION['admin_logueado']) || !$_SESSION['admin_logueado']) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado');
}

require_once 'config/configuracion.php';
require_once 'includes/basedatos.php';
require_once 'includes/utilidades.php';

// verificar que sea una peticion POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Método no permitido');
}

// obtener datos del formulario
$accion = $_POST['accion_crud'] ?? '';
$tipo = $_POST['tipo_entidad'] ?? '';

try {
    $bd = new BaseDatos();
    
    switch ($tipo) {
        case 'usuario':
            $resultado = procesarUsuario($bd, $accion, $_POST);
            break;
            
        case 'pelicula':
            $resultado = procesarPelicula($bd, $accion, $_POST);
            break;
            
        case 'resena':
            $resultado = procesarResena($bd, $accion, $_POST);
            break;
            
        default:
            throw new Exception('Tipo de entidad no reconocido');
    }
    
    // escribir log de la operacion
    escribirLog("CRUD {$accion} {$tipo} - Resultado: " . ($resultado ? 'Éxito' : 'Fallo'));
    
    // respuesta exitosa
    header('Content-Type: application/json');
    echo json_encode([
        'exito' => true,
        'mensaje' => "Operación {$accion} completada correctamente",
        'datos' => $resultado
    ]);
    
} catch (Exception $e) {
    // manejo de errores
    escribirLog("Error CRUD {$accion} {$tipo}: " . $e->getMessage(), 'ERROR');
    
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode([
        'exito' => false,
        'mensaje' => 'Error procesando la operación: ' . $e->getMessage()
    ]);
}

// funciones para procesar cada entidad

function procesarUsuario($bd, $accion, $datos) {
    switch ($accion) {
        case 'crear':
            return crearUsuario($bd, $datos);
            
        case 'editar':
            return editarUsuario($bd, $datos);
            
        case 'eliminar':
            return eliminarUsuario($bd, $datos['id']);
            
        case 'cambiar_estado':
            return cambiarEstadoUsuario($bd, $datos['id'], $datos['nuevo_estado']);
            
        default:
            throw new Exception('Acción de usuario no reconocida');
    }
}

function crearUsuario($bd, $datos) {
    // validar datos
    $errores = validarDatosUsuario($datos);
    if (!empty($errores)) {
        throw new Exception('Datos de usuario inválidos: ' . implode(', ', $errores));
    }
    
    // verificar que el usuario no existe
    $existente = $bd->obtenerUno(
        "SELECT id FROM usuarios WHERE nombre_usuario = ? OR email = ?",
        [$datos['nombre_usuario'], $datos['email']]
    );
    
    if ($existente) {
        throw new Exception('Ya existe un usuario con ese nombre o email');
    }
    
    // hashear contraseña
    $claveHash = hashearClave($datos['clave']);
    
    // insertar usuario
    $sql = "INSERT INTO usuarios (nombre_usuario, email, nombre_completo, clave_hash, 
                                  activo, avatar_url, biografia, fecha_registro) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $parametros = [
        limpiarDatos($datos['nombre_usuario']),
        limpiarDatos($datos['email']),
        limpiarDatos($datos['nombre_completo'] ?? ''),
        $claveHash,
        (int)($datos['activo'] ?? 1),
        limpiarDatos($datos['avatar_url'] ?? ''),
        limpiarDatos($datos['biografia'] ?? '')
    ];
    
    $filas = $bd->ejecutar($sql, $parametros);
    
    if ($filas > 0) {
        $nuevoId = $bd->ultimoId();
        escribirLog("Usuario creado: ID {$nuevoId}, Usuario: {$datos['nombre_usuario']}");
        return ['id' => $nuevoId, 'nombre_usuario' => $datos['nombre_usuario']];
    }
    
    throw new Exception('No se pudo crear el usuario');
}

function editarUsuario($bd, $datos) {
    $id = (int)$datos['id'];
    
    if ($id <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    // verificar que el usuario existe
    $usuario = $bd->obtenerUno("SELECT * FROM usuarios WHERE id = ?", [$id]);
    if (!$usuario) {
        throw new Exception('Usuario no encontrado');
    }
    
    // construir query de actualizacion
    $campos = [];
    $valores = [];
    
    if (!empty($datos['nombre_usuario'])) {
        $campos[] = 'nombre_usuario = ?';
        $valores[] = limpiarDatos($datos['nombre_usuario']);
    }
    
    if (!empty($datos['email'])) {
        $campos[] = 'email = ?';
        $valores[] = limpiarDatos($datos['email']);
    }
    
    if (isset($datos['nombre_completo'])) {
        $campos[] = 'nombre_completo = ?';
        $valores[] = limpiarDatos($datos['nombre_completo']);
    }
    
    if (!empty($datos['clave'])) {
        $campos[] = 'clave_hash = ?';
        $valores[] = hashearClave($datos['clave']);
    }
    
    if (isset($datos['activo'])) {
        $campos[] = 'activo = ?';
        $valores[] = (int)$datos['activo'];
    }
    
    if (isset($datos['avatar_url'])) {
        $campos[] = 'avatar_url = ?';
        $valores[] = limpiarDatos($datos['avatar_url']);
    }
    
    if (isset($datos['biografia'])) {
        $campos[] = 'biografia = ?';
        $valores[] = limpiarDatos($datos['biografia']);
    }
    
    if (empty($campos)) {
        throw new Exception('No hay datos para actualizar');
    }
    
    // agregar fecha de actualizacion
    $campos[] = 'fecha_actualizacion = NOW()';
    $valores[] = $id; // para el WHERE
    
    $sql = "UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = ?";
    $filas = $bd->ejecutar($sql, $valores);
    
    if ($filas > 0) {
        escribirLog("Usuario editado: ID {$id}");
        return ['id' => $id, 'filas_afectadas' => $filas];
    }
    
    throw new Exception('No se pudo actualizar el usuario');
}

function eliminarUsuario($bd, $id) {
    $id = (int)$id;
    
    if ($id <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    // verificar que existe
    $usuario = $bd->obtenerUno("SELECT nombre_usuario FROM usuarios WHERE id = ?", [$id]);
    if (!$usuario) {
        throw new Exception('Usuario no encontrado');
    }
    
    // iniciar transaccion para eliminar en cascada
    $bd->iniciarTransaccion();
    
    try {
        // eliminar reseñas del usuario
        $bd->ejecutar("UPDATE resenas SET activo = 0 WHERE id_usuario = ?", [$id]);
        
        // eliminar peliculas del usuario
        $bd->ejecutar("UPDATE peliculas SET activo = 0 WHERE id_usuario_creador = ?", [$id]);
        
        // eliminar favoritos del usuario
        $bd->ejecutar("DELETE FROM favoritos WHERE id_usuario = ?", [$id]);
        
        // eliminar seguimientos
        $bd->ejecutar("DELETE FROM seguimientos WHERE id_seguidor = ? OR id_seguido = ?", [$id, $id]);
        
        // marcar usuario como inactivo (eliminacion logica)
        $filas = $bd->ejecutar("UPDATE usuarios SET activo = 0, fecha_eliminacion = NOW() WHERE id = ?", [$id]);
        
        $bd->confirmarTransaccion();
        
        escribirLog("Usuario eliminado: ID {$id}, Usuario: {$usuario['nombre_usuario']}");
        return ['id' => $id, 'eliminado' => true];
        
    } catch (Exception $e) {
        $bd->cancelarTransaccion();
        throw new Exception('Error eliminando usuario: ' . $e->getMessage());
    }
}

function cambiarEstadoUsuario($bd, $id, $nuevoEstado) {
    $id = (int)$id;
    $nuevoEstado = (int)$nuevoEstado;
    
    if ($id <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    $sql = "UPDATE usuarios SET activo = ?, fecha_actualizacion = NOW() WHERE id = ?";
    $filas = $bd->ejecutar($sql, [$nuevoEstado, $id]);
    
    if ($filas > 0) {
        $estado = $nuevoEstado ? 'activado' : 'desactivado';
        escribirLog("Usuario {$estado}: ID {$id}");
        return ['id' => $id, 'nuevo_estado' => $nuevoEstado];
    }
    
    throw new Exception('No se pudo cambiar el estado del usuario');
}

function procesarPelicula($bd, $accion, $datos) {
    switch ($accion) {
        case 'crear':
            return crearPelicula($bd, $datos);
            
        case 'editar':
            return editarPelicula($bd, $datos);
            
        case 'eliminar':
            return eliminarPelicula($bd, $datos['id']);
            
        default:
            throw new Exception('Acción de película no reconocida');
    }
}

function crearPelicula($bd, $datos) {
    // validar datos
    $errores = validarDatosPelicula($datos);
    if (!empty($errores)) {
        throw new Exception('Datos de película inválidos: ' . implode(', ', $errores));
    }
    
    // insertar pelicula
    $sql = "INSERT INTO peliculas (titulo, director, ano_lanzamiento, genero_id, 
                                   duracion_minutos, sinopsis, imagen_url, 
                                   id_usuario_creador, fecha_creacion, activo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 1)";
    
    $parametros = [
        limpiarDatos($datos['titulo']),
        limpiarDatos($datos['director']),
        (int)$datos['ano_lanzamiento'],
        (int)$datos['genero_id'],
        (int)$datos['duracion_minutos'],
        limpiarDatos($datos['sinopsis'] ?? ''),
        limpiarDatos($datos['imagen_url'] ?? '')
    ];
    
    $filas = $bd->ejecutar($sql, $parametros);
    
    if ($filas > 0) {
        $nuevoId = $bd->ultimoId();
        escribirLog("Película creada: ID {$nuevoId}, Título: {$datos['titulo']}");
        return ['id' => $nuevoId, 'titulo' => $datos['titulo']];
    }
    
    throw new Exception('No se pudo crear la película');
}

function editarPelicula($bd, $datos) {
    $id = (int)$datos['id'];
    
    if ($id <= 0) {
        throw new Exception('ID de película inválido');
    }
    
    // construir query de actualizacion
    $campos = [];
    $valores = [];
    
    if (!empty($datos['titulo'])) {
        $campos[] = 'titulo = ?';
        $valores[] = limpiarDatos($datos['titulo']);
    }
    
    if (!empty($datos['director'])) {
        $campos[] = 'director = ?';
        $valores[] = limpiarDatos($datos['director']);
    }
    
    if (!empty($datos['ano_lanzamiento'])) {
        $campos[] = 'ano_lanzamiento = ?';
        $valores[] = (int)$datos['ano_lanzamiento'];
    }
    
    if (!empty($datos['genero_id'])) {
        $campos[] = 'genero_id = ?';
        $valores[] = (int)$datos['genero_id'];
    }
    
    if (!empty($datos['duracion_minutos'])) {
        $campos[] = 'duracion_minutos = ?';
        $valores[] = (int)$datos['duracion_minutos'];
    }
    
    if (isset($datos['sinopsis'])) {
        $campos[] = 'sinopsis = ?';
        $valores[] = limpiarDatos($datos['sinopsis']);
    }
    
    if (isset($datos['imagen_url'])) {
        $campos[] = 'imagen_url = ?';
        $valores[] = limpiarDatos($datos['imagen_url']);
    }
    
    if (empty($campos)) {
        throw new Exception('No hay datos para actualizar');
    }
    
    $campos[] = 'fecha_actualizacion = NOW()';
    $valores[] = $id;
    
    $sql = "UPDATE peliculas SET " . implode(', ', $campos) . " WHERE id = ?";
    $filas = $bd->ejecutar($sql, $valores);
    
    if ($filas > 0) {
        escribirLog("Película editada: ID {$id}");
        return ['id' => $id, 'filas_afectadas' => $filas];
    }
    
    throw new Exception('No se pudo actualizar la película');
}

function eliminarPelicula($bd, $id) {
    $id = (int)$id;
    
    if ($id <= 0) {
        throw new Exception('ID de película inválido');
    }
    
    // obtener info de la pelicula
    $pelicula = $bd->obtenerUno("SELECT titulo FROM peliculas WHERE id = ?", [$id]);
    if (!$pelicula) {
        throw new Exception('Película no encontrada');
    }
    
    // iniciar transaccion
    $bd->iniciarTransaccion();
    
    try {
        // eliminar reseñas de la pelicula
        $bd->ejecutar("UPDATE resenas SET activo = 0 WHERE id_pelicula = ?", [$id]);
        
        // eliminar de favoritos
        $bd->ejecutar("DELETE FROM favoritos WHERE id_pelicula = ?", [$id]);
        
        // marcar pelicula como inactiva
        $filas = $bd->ejecutar("UPDATE peliculas SET activo = 0, fecha_eliminacion = NOW() WHERE id = ?", [$id]);
        
        $bd->confirmarTransaccion();
        
        escribirLog("Película eliminada: ID {$id}, Título: {$pelicula['titulo']}");
        return ['id' => $id, 'eliminado' => true];
        
    } catch (Exception $e) {
        $bd->cancelarTransaccion();
        throw new Exception('Error eliminando película: ' . $e->getMessage());
    }
}

function procesarResena($bd, $accion, $datos) {
    switch ($accion) {
        case 'eliminar':
            return eliminarResena($bd, $datos['id']);
            
        default:
            throw new Exception('Acción de reseña no reconocida');
    }
}

function eliminarResena($bd, $id) {
    $id = (int)$id;
    
    if ($id <= 0) {
        throw new Exception('ID de reseña inválido');
    }
    
    // obtener info de la resena
    $resena = $bd->obtenerUno(
        "SELECT r.titulo, p.titulo as pelicula, u.nombre_usuario 
         FROM resenas r 
         JOIN peliculas p ON r.id_pelicula = p.id 
         JOIN usuarios u ON r.id_usuario = u.id 
         WHERE r.id = ?", 
        [$id]
    );
    
    if (!$resena) {
        throw new Exception('Reseña no encontrada');
    }
    
    // iniciar transaccion
    $bd->iniciarTransaccion();
    
    try {
        // eliminar likes de la resena
        $bd->ejecutar("DELETE FROM likes_resenas WHERE id_resena = ?", [$id]);
        
        // marcar resena como inactiva
        $filas = $bd->ejecutar("UPDATE resenas SET activo = 0, fecha_eliminacion = NOW() WHERE id = ?", [$id]);
        
        $bd->confirmarTransaccion();
        
        escribirLog("Reseña eliminada: ID {$id}, Usuario: {$resena['nombre_usuario']}, Película: {$resena['pelicula']}");
        return ['id' => $id, 'eliminado' => true];
        
    } catch (Exception $e) {
        $bd->cancelarTransaccion();
        throw new Exception('Error eliminando reseña: ' . $e->getMessage());
    }
}

// funciones auxiliares para obtener detalles (para los modales)
if (isset($_GET['accion']) && $_GET['accion'] === 'ajax') {
    $punto = $_GET['punto'] ?? '';
    
    try {
        $bd = new BaseDatos();
        
        switch ($punto) {
            case 'usuario_detalle':
                $id = (int)($_GET['id'] ?? 0);
                $usuario = $bd->obtenerUno("SELECT * FROM usuarios WHERE id = ?", [$id]);
                echo json_encode(['exito' => true, 'datos' => $usuario]);
                break;
                
            case 'pelicula_detalle':
                $id = (int)($_GET['id'] ?? 0);
                $pelicula = $bd->obtenerUno("SELECT * FROM peliculas WHERE id = ?", [$id]);
                echo json_encode(['exito' => true, 'datos' => $pelicula]);
                break;
                
            case 'resena_detalle':
                $id = (int)($_GET['id'] ?? 0);
                $sql = "SELECT r.*, p.titulo as pelicula_titulo, p.director, p.imagen_url as pelicula_imagen,
                              u.nombre_usuario, u.avatar_url, g.nombre as genero
                       FROM resenas r
                       INNER JOIN peliculas p ON r.id_pelicula = p.id
                       INNER JOIN usuarios u ON r.id_usuario = u.id
                       INNER JOIN generos g ON p.genero_id = g.id
                       WHERE r.id = ?";
                $resena = $bd->obtenerUno($sql, [$id]);
                echo json_encode(['exito' => true, 'datos' => $resena]);
                break;
                
            default:
                echo json_encode(['exito' => false, 'mensaje' => 'Punto no reconocido']);
        }
    } catch (Exception $e) {
        echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
    }
    
    exit;
}
?>