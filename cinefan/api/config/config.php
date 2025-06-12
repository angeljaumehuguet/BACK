<?php
// configuración de la aplicación
define('APP_NAME', 'CineFan API');
define('APP_VERSION', '1.0.0');
define('API_VERSION', 'v1');
define('DEBUG_SQL', true);

// configuración de jwt
define('JWT_SECRET', 'JuanAngelCineFan101003');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRATION', 86400); // 24 horas

// configuración de paginación
define('DEFAULT_PAGE_SIZE', 10);
define('MAX_PAGE_SIZE', 50);

// configuración de archivos
define('MAX_IMAGE_SIZE', 5242880); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// configuración de validación
define('MIN_PASSWORD_LENGTH', 6);
define('MAX_USERNAME_LENGTH', 50);
define('MAX_REVIEW_LENGTH', 1000);

// configuración de logs
define('ENABLE_LOGGING', true);
define('LOG_LEVEL', 'DEBUG');

// zona horaria
date_default_timezone_set('Europe/Madrid');

// configuración de errores para desarrollo
if (defined('DEVELOPMENT') && DEVELOPMENT) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    define('LOG_LEVEL', 'DEBUG');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}