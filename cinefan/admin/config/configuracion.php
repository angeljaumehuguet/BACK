<?php
// configuracion base de datos
define('HOST_BD', 'localhost');
define('NOMBRE_BD', 'cinefan_db');
define('USUARIO_BD', 'cinefan');
define('CLAVE_BD', 'angel');
define('CHARSET_BD', 'utf8mb4');

// configuracion de la aplicacion
define('NOMBRE_APP', 'CineFan Panel Admin');
define('VERSION_APP', '1.2.0');
define('ENTORNO_APP', 'desarrollo'); // cambiar a produccion cuando subamos
define('URL_BASE_ADMIN', '/cinefan/admin/');

// configuracion de seguridad admin
define('NOMBRE_SESION_ADMIN', 'cinefan_admin_sesion');
define('NOMBRE_TOKEN_CSRF', 'cinefan_csrf_token');
define('LONGITUD_MIN_CLAVE', 6);
define('TIEMPO_SESION_ADMIN', 1800); // 30 minutos
define('INTENTOS_LOGIN_MAX', 5);
define('TIEMPO_BLOQUEO_LOGIN', 900); // 15 minutos

// rutas del sistema
define('RUTA_BASE', dirname(__DIR__) . '/');
define('RUTA_SUBIDAS', RUTA_BASE . 'subidas/');
define('RUTA_LOGS', RUTA_BASE . 'logs/');
define('RUTA_PDF_TEMP', RUTA_BASE . 'temp/pdf/');
define('RUTA_BACKUP', RUTA_BASE . 'backup/');
define('RUTA_ASSETS', RUTA_BASE . 'assets/');

// configuracion de paginacion
define('TAMAÑO_PAGINA_DEFECTO', 10);
define('TAMAÑO_PAGINA_MAXIMO', 100);
define('TAMAÑO_PAGINA_MINIMO', 5);

// configuracion de PDFs
define('AUTOR_PDF', 'Juan Carlos y Angel Hernandez');
define('CREADOR_PDF', 'CineFan Panel Admin v' . VERSION_APP);
define('PREFIJO_TITULO_PDF', 'CineFan - ');
define('MARGENES_PDF', [15, 15, 15]); // izq arr der
define('FUENTE_PDF_DEFECTO', 'Arial');
define('TAMAÑO_FUENTE_PDF', 9);

// configuracion de logs
define('ACTIVAR_LOGS', true);
define('NIVEL_LOG', 'INFO'); // DEBUG INFO WARNING ERROR
define('TAMAÑO_MAX_LOG', 10485760); // 10MB
define('ROTAR_LOGS', true);
define('DIAS_MANTENER_LOGS', 30);

// validaciones para formularios
define('LONGITUD_MAX_TITULO', 200);
define('LONGITUD_MAX_DESCRIPCION', 1000);
define('LONGITUD_MAX_NOMBRE_USUARIO', 50);
define('LONGITUD_MAX_EMAIL', 100);
define('LONGITUD_MAX_NOMBRE_COMPLETO', 100);

// validaciones para peliculas
define('AÑO_MIN', 1895);
define('AÑO_MAX', 2030);
define('DURACION_MIN', 1);
define('DURACION_MAX', 600);

// configuracion de resenas
define('LONGITUD_MAX_RESENA', 1000);
define('LONGITUD_MAX_TITULO_RESENA', 100);
define('PUNTUACION_MIN', 1);
define('PUNTUACION_MAX', 5);

// configuracion de archivos
define('TAMAÑO_MAX_IMAGEN', 5242880); // 5MB
define('EXTENSIONES_IMAGEN', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('TAMAÑO_MAX_ARCHIVO', 10485760); // 10MB

// configuracion de email (para notificaciones admin)
define('ACTIVAR_EMAIL', false);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PUERTO', 587);
define('SMTP_USUARIO', '');
define('SMTP_CLAVE', '');
define('EMAIL_ADMIN', 'admin@cinefan.local');

// configuracion de cache
define('ACTIVAR_CACHE', true);
define('TIEMPO_CACHE_ESTADISTICAS', 300); // 5 minutos
define('TIEMPO_CACHE_LISTADOS', 60); // 1 minuto

// configuracion de backup automatico
define('ACTIVAR_BACKUP_AUTO', true);
define('FRECUENCIA_BACKUP', 'diario'); // diario semanal mensual
define('MANTENER_BACKUPS', 7); // cantidad de backups a mantener

// configuracion API externa (si necesitamos)
define('API_PELICULAS_URL', 'https://api.themoviedb.org/3/');
define('API_PELICULAS_KEY', ''); // aqui iria la key real

// configuracion de desarrollo/debug
define('MOSTRAR_ERRORES', ENTORNO_APP === 'desarrollo');
define('LOG_CONSULTAS_SQL', ENTORNO_APP === 'desarrollo');
define('TIEMPO_EJECUCION_MAX', 30);

// configuraciones especificas por entorno
if (ENTORNO_APP === 'desarrollo') {
    // configuracion para desarrollo
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    define('ACTIVAR_DEBUG', true);
    define('CACHE_TIEMPO_CORTO', 0); // sin cache en desarrollo
    
} elseif (ENTORNO_APP === 'produccion') {
    // configuracion para produccion
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    
    define('ACTIVAR_DEBUG', false);
    define('CACHE_TIEMPO_CORTO', 300);
    
    // configuracion de seguridad adicional para produccion
    define('USAR_HTTPS', true);
    define('COOKIE_SECURE', true);
    define('COOKIE_HTTPONLY', true);
}

// configuracion de timezone
date_default_timezone_set('Europe/Madrid');

// configuracion de session
ini_set('session.gc_maxlifetime', TIEMPO_SESION_ADMIN);
ini_set('session.cookie_lifetime', TIEMPO_SESION_ADMIN);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

if (defined('USAR_HTTPS') && USAR_HTTPS) {
    ini_set('session.cookie_secure', 1);
}

// crear directorios necesarios
$directorios_necesarios = [
    RUTA_SUBIDAS,
    RUTA_LOGS,
    RUTA_PDF_TEMP,
    RUTA_BACKUP,
    RUTA_ASSETS
];

foreach ($directorios_necesarios as $directorio) {
    if (!is_dir($directorio)) {
        if (!@mkdir($directorio, 0755, true)) {
            error_log("No se pudo crear el directorio: {$directorio}");
        }
    }
}

// configuracion de generos disponibles (para evitar consultas repetitivas)
define('GENEROS_DISPONIBLES', [
    1 => ['nombre' => 'Accion', 'color' => '#ff6b35'],
    2 => ['nombre' => 'Drama', 'color' => '#2e86ab'],
    3 => ['nombre' => 'Comedia', 'color' => '#f77f00'],
    4 => ['nombre' => 'Terror', 'color' => '#c1121f'],
    5 => ['nombre' => 'Ciencia Ficcion', 'color' => '#6a4c93'],
    6 => ['nombre' => 'Romance', 'color' => '#f72585'],
    7 => ['nombre' => 'Thriller', 'color' => '#560bad'],
    8 => ['nombre' => 'Aventura', 'color' => '#38a3a5']
]);

// configuracion de mensajes del sistema
define('MENSAJES_SISTEMA', [
    'usuario_creado' => 'Usuario creado correctamente',
    'usuario_editado' => 'Usuario actualizado correctamente',
    'usuario_eliminado' => 'Usuario eliminado correctamente',
    'pelicula_creada' => 'Película añadida correctamente',
    'pelicula_editada' => 'Película actualizada correctamente',
    'pelicula_eliminada' => 'Película eliminada correctamente',
    'resena_eliminada' => 'Reseña eliminada correctamente',
    'error_generico' => 'Se ha producido un error inesperado',
    'acceso_denegado' => 'No tienes permisos para realizar esta acción',
    'sesion_expirada' => 'Tu sesión ha expirado, inicia sesión nuevamente'
]);

// estadisticas y metricas del sistema
define('METRICAS_SISTEMA', [
    'usuarios_por_pagina' => 10,
    'peliculas_por_pagina' => 12,
    'resenas_por_pagina' => 15,
    'max_intentos_subida' => 3,
    'tiempo_limite_operacion' => 30
]);

// configuracion de notificaciones
define('TIPOS_NOTIFICACION', [
    'info' => ['icono' => 'fa-info-circle', 'clase' => 'alert-info'],
    'success' => ['icono' => 'fa-check-circle', 'clase' => 'alert-success'],
    'warning' => ['icono' => 'fa-exclamation-triangle', 'clase' => 'alert-warning'],
    'danger' => ['icono' => 'fa-times-circle', 'clase' => 'alert-danger']
]);

// configuracion de testing
define('ACTIVAR_TESTING', ENTORNO_APP === 'desarrollo');
define('TIEMPO_LIMITE_TEST', 5); // segundos
define('TESTS_UNITARIOS_MINIMOS', 8);
define('COBERTURA_MINIMA', 90); // porcentaje

// validar configuracion critica
function validarConfiguracion() {
    $errores = [];
    
    // verificar conexion BD
    if (empty(HOST_BD) || empty(NOMBRE_BD) || empty(USUARIO_BD)) {
        $errores[] = 'Configuración de base de datos incompleta';
    }
    
    // verificar directorios
    $directorios = [RUTA_LOGS, RUTA_PDF_TEMP, RUTA_BACKUP];
    foreach ($directorios as $dir) {
        if (!is_dir($dir) || !is_writable($dir)) {
            $errores[] = "Directorio no accesible: {$dir}";
        }
    }
    
    // verificar configuracion de sesion
    if (TIEMPO_SESION_ADMIN < 300) { // minimo 5 minutos
        $errores[] = 'Tiempo de sesión demasiado corto (mínimo 5 minutos)';
    }
    
    return $errores;
}

// ejecutar validacion automatica
if (MOSTRAR_ERRORES) {
    $errores_config = validarConfiguracion();
    if (!empty($errores_config)) {
        foreach ($errores_config as $error) {
            error_log("Error de configuración: {$error}");
        }
    }
}

// configuracion adicional para produccion
if (ENTORNO_APP === 'produccion') {
    // ocultar version de PHP
    header_remove('X-Powered-By');
    
    // headers de seguridad
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    if (defined('USAR_HTTPS') && USAR_HTTPS) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// constantes calculadas
define('FECHA_VERSION', '2024-12-20');
define('HASH_VERSION', md5(VERSION_APP . FECHA_VERSION));
define('TIEMPO_INICIO_APP', microtime(true));

// informacion del sistema para debug
if (ACTIVAR_DEBUG) {
    define('INFO_SISTEMA', [
        'version' => VERSION_APP,
        'fecha_version' => FECHA_VERSION,
        'entorno' => ENTORNO_APP,
        'php_version' => PHP_VERSION,
        'memoria_limite' => ini_get('memory_limit'),
        'tiempo_limite' => ini_get('max_execution_time'),
        'timezone' => date_default_timezone_get()
    ]);
}

// mensaje de confirmacion de carga
if (ACTIVAR_DEBUG) {
    error_log("Configuración cargada correctamente - Entorno: " . ENTORNO_APP . " - Version: " . VERSION_APP);
}
?>