<?php
// verificar si el usuario es admin
function esAdmin() {
    return isset($_SESSION['admin_logueado']) && $_SESSION['admin_logueado'];
}

// redirigir si no es admin
function requiereAdmin() {
    if (!esAdmin()) {
        header('Location: login.php');
        exit;
    }
}

// limpiar entrada de datos
function limpiarDatos($datos) {
    if (is_array($datos)) {
        return array_map('limpiarDatos', $datos);
    }
    
    return htmlspecialchars(trim($datos), ENT_QUOTES, 'UTF-8');
}

// validar formato email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// generar token CSRF
function generarTokenCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// verificar token CSRF
function verificarTokenCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// formatear fecha para mostrar
function formatearFecha($fecha, $formato = 'd/m/Y H:i') {
    if (empty($fecha)) return '-';
    
    try {
        $dt = new DateTime($fecha);
        return $dt->format($formato);
    } catch (Exception $e) {
        return '-';
    }
}

// formatear tamaño de archivo
function formatearTamaño($bytes) {
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $indice = 0;
    
    while ($bytes >= 1024 && $indice < count($unidades) - 1) {
        $bytes /= 1024;
        $indice++;
    }
    
    return round($bytes, 2) . ' ' . $unidades[$indice];
}

// generar hash de contraseña
function hashearClave($clave) {
    return password_hash($clave, PASSWORD_DEFAULT);
}

// verificar contraseña
function verificarClave($clave, $hash) {
    return password_verify($clave, $hash);
}

// escribir log
function escribirLog($mensaje, $nivel = 'INFO') {
    if (!ACTIVAR_LOGS) return;
    
    $fecha = date('Y-m-d H:i:s');
    $usuario = $_SESSION['admin_usuario'] ?? 'anonimo';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
    
    $linea_log = "[{$fecha}] [{$nivel}] Usuario: {$usuario} IP: {$ip} - {$mensaje}" . PHP_EOL;
    
    $archivo_log = RUTA_LOGS . 'admin_' . date('Y-m-d') . '.log';
    
    // crear directorio si no existe
    if (!file_exists(RUTA_LOGS)) {
        mkdir(RUTA_LOGS, 0755, true);
    }
    
    file_put_contents($archivo_log, $linea_log, FILE_APPEND | LOCK_EX);
}

// validar que el año esté en rango válido
function validarAño($año) {
    $añoActual = date('Y');
    return is_numeric($año) && $año >= AÑO_MIN && $año <= $añoActual + 5;
}

// validar duracion de pelicula
function validarDuracion($duracion) {
    return is_numeric($duracion) && $duracion >= DURACION_MIN && $duracion <= DURACION_MAX;
}

// generar nombre archivo unico
function generarNombreArchivoUnico($nombreOriginal, $directorio) {
    $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
    $nombreBase = pathinfo($nombreOriginal, PATHINFO_FILENAME);
    $nombreBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombreBase);
    
    $contador = 1;
    $nombreFinal = $nombreBase . '.' . $extension;
    
    while (file_exists($directorio . $nombreFinal)) {
        $nombreFinal = $nombreBase . '_' . $contador . '.' . $extension;
        $contador++;
    }
    
    return $nombreFinal;
}

// obtener IP del usuario
function obtenerIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
    }
}

// mostrar mensaje flash
function mostrarMensaje($tipo, $mensaje) {
    echo "<div class='alert alert-{$tipo} alert-dismissible fade show' role='alert'>
            {$mensaje}
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
          </div>";
}

// verificar permisos de archivo
function verificarPermisos($archivo) {
    return is_readable($archivo) && is_writable($archivo);
}

// escapar para SQL LIKE
function escaparLike($cadena) {
    return str_replace(['%', '_'], ['\%', '\_'], $cadena);
}

// generar slug amigable
function generarSlug($texto) {
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    $texto = trim($texto, '-');
    return $texto;
}

// validaciones especificas para el proyecto
function validarDatosPelicula($datos) {
    $errores = [];
    
    if (empty($datos['titulo'])) {
        $errores[] = 'El titulo es obligatorio';
    } elseif (strlen($datos['titulo']) > LONGITUD_MAX_TITULO) {
        $errores[] = 'El titulo es demasiado largo';
    }
    
    if (empty($datos['director'])) {
        $errores[] = 'El director es obligatorio';
    }
    
    if (!validarAño($datos['año'])) {
        $errores[] = 'El año no es valido';
    }
    
    if (!validarDuracion($datos['duracion'])) {
        $errores[] = 'La duracion no es valida';
    }
    
    return $errores;
}

function validarDatosUsuario($datos) {
    $errores = [];
    
    if (empty($datos['nombre_usuario'])) {
        $errores[] = 'El nombre de usuario es obligatorio';
    }
    
    if (empty($datos['email']) || !validarEmail($datos['email'])) {
        $errores[] = 'El email no es valido';
    }
    
    if (isset($datos['clave']) && strlen($datos['clave']) < LONGITUD_MIN_CLAVE) {
        $errores[] = 'La contraseña debe tener al menos ' . LONGITUD_MIN_CLAVE . ' caracteres';
    }
    
    return $errores;
}

// funciones para estadisticas rapidas
function obtenerEstadisticaRapida($bd, $tabla, $condicion = '1=1') {
    $sql = "SELECT COUNT(*) FROM {$tabla} WHERE {$condicion}";
    return $bd->obtenerValor($sql);
}

function obtenerPromedioRapido($bd, $tabla, $campo, $condicion = '1=1') {
    $sql = "SELECT AVG({$campo}) FROM {$tabla} WHERE {$condicion}";
    return round($bd->obtenerValor($sql), 2);
}

// debug helper (solo en desarrollo)
function debug($variable, $salir = false) {
    if (ENTORNO_APP === 'desarrollo') {
        echo '<pre>';
        var_dump($variable);
        echo '</pre>';
        
        if ($salir) exit;
    }
}
?>