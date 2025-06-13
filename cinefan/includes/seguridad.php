<?php
class GestorSeguridad {
    
    // generar token csrf
    public static function generarTokenCSRF() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $nombreToken = defined('NOMBRE_TOKEN_CSRF') ? NOMBRE_TOKEN_CSRF : 'cinefan_csrf_token';
        
        if (!isset($_SESSION[$nombreToken])) {
            $_SESSION[$nombreToken] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION[$nombreToken];
    }
    
    // verificar token csrf
    public static function verificarTokenCSRF($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $nombreToken = defined('NOMBRE_TOKEN_CSRF') ? NOMBRE_TOKEN_CSRF : 'cinefan_csrf_token';
        
        if (!isset($_SESSION[$nombreToken])) {
            return false;
        }
        
        return hash_equals($_SESSION[$nombreToken], $token);
    }
    
    // limpiar entrada de usuario
    public static function limpiarEntrada($entrada) {
        if (is_array($entrada)) {
            return array_map([self::class, 'limpiarEntrada'], $entrada);
        }
        
        // limpiar cadena
        $entrada = trim($entrada);
        $entrada = stripslashes($entrada);
        $entrada = htmlspecialchars($entrada, ENT_QUOTES, 'UTF-8');
        
        return $entrada;
    }
    
    // validar email
    public static function validarEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    // validar clave segura
    public static function validarClave($clave) {
        $longitudMinima = defined('LONGITUD_MIN_CLAVE') ? LONGITUD_MIN_CLAVE : 6;
        
        if (strlen($clave) < $longitudMinima) {
            return false;
        }
        
        return true;
    }
    
    // hash de clave
    public static function hashearClave($clave) {
        return password_hash($clave, PASSWORD_DEFAULT);
    }
    
    // verificar clave
    public static function verificarClave($clave, $hash) {
        return password_verify($clave, $hash);
    }
    
    // verificar si usuario esta autenticado
    public static function estaAutenticado() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $nombreSesion = defined('NOMBRE_SESION_ADMIN') ? NOMBRE_SESION_ADMIN : 'cinefan_admin_sesion';
        
        return isset($_SESSION[$nombreSesion . '_id_usuario']) && 
               isset($_SESSION[$nombreSesion . '_autenticado']) &&
               $_SESSION[$nombreSesion . '_autenticado'] === true;
    }
    
    // iniciar sesion de admin
    public static function iniciarSesionAdmin($idUsuario, $nombreUsuario) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $nombreSesion = defined('NOMBRE_SESION_ADMIN') ? NOMBRE_SESION_ADMIN : 'cinefan_admin_sesion';
        
        // regenerar id de sesion por seguridad
        session_regenerate_id(true);
        
        $_SESSION[$nombreSesion . '_id_usuario'] = $idUsuario;
        $_SESSION[$nombreSesion . '_nombre_usuario'] = $nombreUsuario;
        $_SESSION[$nombreSesion . '_autenticado'] = true;
        $_SESSION[$nombreSesion . '_tiempo_inicio'] = time();
        
        Registrador::info("Inicio sesion admin exitoso para usuario: $nombreUsuario");
    }
    
    // cerrar sesion
    public static function cerrarSesion() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $nombreSesion = defined('NOMBRE_SESION_ADMIN') ? NOMBRE_SESION_ADMIN : 'cinefan_admin_sesion';
        
        // limpiar variables de sesion
        unset($_SESSION[$nombreSesion . '_id_usuario']);
        unset($_SESSION[$nombreSesion . '_nombre_usuario']);
        unset($_SESSION[$nombreSesion . '_autenticado']);
        unset($_SESSION[$nombreSesion . '_tiempo_inicio']);
        
        // destruir sesion completamente
        session_destroy();
        
        Registrador::info("Cierre sesion admin completado");
    }
    
    // obtener info del usuario actual
    public static function obtenerUsuarioActual() {
        if (!self::estaAutenticado()) {
            return null;
        }
        
        $nombreSesion = defined('NOMBRE_SESION_ADMIN') ? NOMBRE_SESION_ADMIN : 'cinefan_admin_sesion';
        
        return [
            'id' => $_SESSION[$nombreSesion . '_id_usuario'] ?? null,
            'nombre_usuario' => $_SESSION[$nombreSesion . '_nombre_usuario'] ?? null,
            'tiempo_inicio' => $_SESSION[$nombreSesion . '_tiempo_inicio'] ?? null
        ];
    }
    
    // verificar permisos de directorio
    public static function validarRuta($ruta) {
        $rutaReal = realpath($ruta);
        $rutaBase = realpath(dirname(__DIR__)); // directorio admin
        
        // asegurar que la ruta esta dentro del directorio permitido
        if ($rutaReal === false || strpos($rutaReal, $rutaBase) !== 0) {
            Registrador::advertencia("Intento travesia ruta detectado: $ruta");
            return false;
        }
        
        return true;
    }
    
    // limitar intentos de inicio sesion
    public static function verificarIntentosInicioSesion($ip) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $claveIntentos = 'intentos_inicio_sesion_' . md5($ip);
        $claveTiempo = 'tiempo_intentos_inicio_sesion_' . md5($ip);
        
        $intentos = $_SESSION[$claveIntentos] ?? 0;
        $ultimoIntento = $_SESSION[$claveTiempo] ?? 0;
        
        // resetear despues de 15 minutos
        if (time() - $ultimoIntento > 900) {
            $intentos = 0;
        }
        
        // maximo 5 intentos
        if ($intentos >= 5) {
            Registrador::advertencia("Demasiados intentos inicio sesion desde IP: $ip");
            return false;
        }
        
        return true;
    }
    
    // registrar intento de inicio sesion fallido
    public static function registrarInicioSesionFallido($ip) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $claveIntentos = 'intentos_inicio_sesion_' . md5($ip);
        $claveTiempo = 'tiempo_intentos_inicio_sesion_' . md5($ip);
        
        $intentos = $_SESSION[$claveIntentos] ?? 0;
        $_SESSION[$claveIntentos] = $intentos + 1;
        $_SESSION[$claveTiempo] = time();
        
        Registrador::advertencia("Intento inicio sesion fallido desde IP: $ip (intento " . ($_SESSION[$claveIntentos]) . ")");
    }
    
    // limpiar intentos de inicio sesion despues de inicio exitoso
    public static function limpiarIntentosInicioSesion($ip) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $claveIntentos = 'intentos_inicio_sesion_' . md5($ip);
        $claveTiempo = 'tiempo_intentos_inicio_sesion_' . md5($ip);
        
        unset($_SESSION[$claveIntentos]);
        unset($_SESSION[$claveTiempo]);
    }
    
    // detectar posibles ataques inyeccion sql en entrada
    public static function detectarInyeccionSQL($entrada) {
        $patronesPeligrosos = [
            '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION)\b)/i',
            '/(\b(OR|AND)\s+\d+\s*=\s*\d+\b)/i',
            '/(\'|\"|;|--|\*|\/\*|\*\/)/i'
        ];
        
        foreach ($patronesPeligrosos as $patron) {
            if (preg_match($patron, $entrada)) {
                Registrador::advertencia("Posible intento inyeccion SQL detectado: " . substr($entrada, 0, 100));
                return true;
            }
        }
        
        return false;
    }
    
    // detectar posibles ataques xss
    public static function detectarXSS($entrada) {
        $patronesPeligrosos = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi'
        ];
        
        foreach ($patronesPeligrosos as $patron) {
            if (preg_match($patron, $entrada)) {
                Registrador::advertencia("Posible intento XSS detectado: " . substr($entrada, 0, 100));
                return true;
            }
        }
        
        return false;
    }
    
    // configurar cabeceras de seguridad
    public static function establecerCabecerasSeguridad() {
        // prevenir clickjacking
        header('X-Frame-Options: DENY');
        
        // prevenir mime sniffing
        header('X-Content-Type-Options: nosniff');
        
        // habilitar proteccion xss
        header('X-XSS-Protection: 1; mode=block');
        
        // politica de referrer
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // politica de seguridad contenido basica
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com");
    }
    
    // generar nonce para csp
    public static function generarNonce() {
        return base64_encode(random_bytes(16));
    }
    
    // validar subida archivo
    public static function validarSubidaArchivo($archivo) {
        // extensiones peligrosas
        $extensionesPeligrosas = ['php', 'exe', 'bat', 'cmd', 'sh', 'phtml', 'php3', 'php4', 'php5'];
        
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        
        if (in_array($extension, $extensionesPeligrosas)) {
            Registrador::seguridad("Intento subida archivo peligroso: " . $archivo['name']);
            return false;
        }
        
        // tamaño maximo 5MB
        if ($archivo['size'] > 5 * 1024 * 1024) {
            return false;
        }
        
        return true;
    }
    
    // verificar fortaleza clave
    public static function verificarFortalezaClave($clave) {
        $puntuacion = 0;
        $problemas = [];
        
        // longitud
        if (strlen($clave) >= 8) {
            $puntuacion += 1;
        } else {
            $problemas[] = 'Muy corta (minimo 8 caracteres)';
        }
        
        // mayusculas
        if (preg_match('/[A-Z]/', $clave)) {
            $puntuacion += 1;
        } else {
            $problemas[] = 'Falta mayuscula';
        }
        
        // minusculas
        if (preg_match('/[a-z]/', $clave)) {
            $puntuacion += 1;
        } else {
            $problemas[] = 'Falta minuscula';
        }
        
        // numeros
        if (preg_match('/[0-9]/', $clave)) {
            $puntuacion += 1;
        } else {
            $problemas[] = 'Falta numero';
        }
        
        // caracteres especiales
        if (preg_match('/[^A-Za-z0-9]/', $clave)) {
            $puntuacion += 1;
        } else {
            $problemas[] = 'Falta caracter especial';
        }
        
        return [
            'puntuacion' => $puntuacion,
            'nivel' => $puntuacion >= 4 ? 'fuerte' : ($puntuacion >= 3 ? 'medio' : 'debil'),
            'problemas' => $problemas
        ];
    }
}

// aplicar cabeceras de seguridad automaticamente
if (!headers_sent()) {
    GestorSeguridad::establecerCabecerasSeguridad();
}
?>