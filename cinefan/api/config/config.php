<?php
/**
 * Configuración general de CineFan API
 * Define constantes y configuraciones globales
 */

// Información de la aplicación
define('APP_NAME', 'CineFan');
define('APP_VERSION', '1.0.0');
define('API_VERSION', 'v1');

// Configuración de debugging
define('DEBUG_MODE', true); // Cambiar a false en producción
define('ENABLE_LOGGING', true);

// Configuración de autenticación
define('JWT_SECRET', 'CineFan_JWT_Secret_2025'); // Cambiar en producción
define('JWT_EXPIRATION', 86400); // 24 horas
define('MIN_PASSWORD_LENGTH', 6);
define('MAX_PASSWORD_LENGTH', 255);

// Configuración de validaciones
define('MIN_USERNAME_LENGTH', 3);
define('MAX_USERNAME_LENGTH', 50);
define('MAX_EMAIL_LENGTH', 255);
define('MAX_FULL_NAME_LENGTH', 100);

// Configuración de contenido
define('MAX_MOVIE_TITLE_LENGTH', 255);
define('MAX_DIRECTOR_LENGTH', 100);
define('MAX_SYNOPSIS_LENGTH', 1000);
define('MIN_MOVIE_YEAR', 1895);
define('MAX_MOVIE_YEAR', 2030);
define('MIN_MOVIE_DURATION', 1);
define('MAX_MOVIE_DURATION', 600); // 10 horas

// Configuración de reseñas
define('MIN_REVIEW_LENGTH', 10);
define('MAX_REVIEW_LENGTH', 1000);
define('MIN_RATING', 1);
define('MAX_RATING', 5);

// Configuración de paginación
define('DEFAULT_PAGE_SIZE', 10);
define('MAX_PAGE_SIZE', 50);

// Configuración de archivos
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('UPLOAD_PATH', '../uploads/');

// Configuración de URLs
define('BASE_URL', 'http://192.168.0.36/cinefan/api/');
define('ADMIN_URL', 'http://192.168.0.36/cinefan/admin/');
define('FRONTEND_URL', 'http://192.168.0.36/cinefan/');

// Configuración de seguridad
define('BCRYPT_COST', 12);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 300); // 5 minutos
define('CSRF_TOKEN_LENGTH', 32);

// Configuración de email (para futuras implementaciones)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@cinefan.com');
define('SMTP_FROM_NAME', 'CineFan');

// Configuración de cache
define('CACHE_ENABLED', false);
define('CACHE_TTL', 3600); // 1 hora

// Configuración de logs
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('LOG_ROTATION', true);

// Rutas de archivos
define('LOG_PATH', '../logs/');
define('TEMP_PATH', '../temp/');
define('BACKUP_PATH', '../backups/');

// Configuración de sesiones
define('SESSION_LIFETIME', 86400); // 24 horas
define('SESSION_NAME', 'cinefan_session');

// Configuración de límites de API
define('API_RATE_LIMIT', 100); // Peticiones por minuto
define('API_BURST_LIMIT', 10); // Peticiones por segundo

// Configuración de notificaciones
define('ENABLE_NOTIFICATIONS', true);
define('NOTIFICATION_TYPES', [
    'new_review',
    'new_follower',
    'review_liked',
    'movie_recommendation'
]);

// Configuración de géneros de películas
define('DEFAULT_GENRES', [
    'Acción',
    'Aventuras',
    'Ciencia Ficción',
    'Comedia',
    'Drama',
    'Fantasía',
    'Horror',
    'Misterio',
    'Romance',
    'Suspense',
    'Thriller',
    'Western',
    'Animación',
    'Documental',
    'Musical',
    'Biografía',
    'Historia',
    'Guerra',
    'Crimen',
    'Familia'
]);

// Configuración de roles de usuario
define('USER_ROLES', [
    'user' => 'Usuario',
    'moderator' => 'Moderador',
    'admin' => 'Administrador'
]);

// Configuración de estados de contenido
define('CONTENT_STATUS', [
    'draft' => 'Borrador',
    'published' => 'Publicado',
    'hidden' => 'Oculto',
    'deleted' => 'Eliminado'
]);

// Configuración de timezone
date_default_timezone_set('Europe/Madrid');

// Configuración de headers de respuesta
define('API_HEADERS', [
    'Content-Type' => 'application/json; charset=utf-8',
    'X-API-Version' => API_VERSION,
    'X-Powered-By' => APP_NAME . ' ' . APP_VERSION
]);

// Configuración de errores PHP
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . 'php_errors.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . 'php_errors.log');
}

// Configuración de memoria y tiempo de ejecución
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);

// Función helper para obtener configuración
function getConfig($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

// Función helper para verificar si estamos en modo debug
function isDebugMode() {
    return defined('DEBUG_MODE') && DEBUG_MODE;
}

// Función helper para verificar si el logging está habilitado
function isLoggingEnabled() {
    return defined('ENABLE_LOGGING') && ENABLE_LOGGING;
}

// Crear directorios necesarios si no existen
$directorios = [LOG_PATH, TEMP_PATH, UPLOAD_PATH];

foreach ($directorios as $directorio) {
    if (!is_dir($directorio)) {
        @mkdir($directorio, 0755, true);
    }
}

// Log de inicialización
if (isLoggingEnabled()) {
    $logMessage = "CineFan API inicializada - " . date('Y-m-d H:i:s') . PHP_EOL;
    @file_put_contents(LOG_PATH . 'app.log', $logMessage, FILE_APPEND | LOCK_EX);
}
?>