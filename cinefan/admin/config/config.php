<?php
// datos de conexion a la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'cinefan_db');
define('DB_USER', 'cinefan');
define('DB_PASS', 'angel');
define('DB_CHARSET', 'utf8mb4');

// info basica de la app
define('APP_NAME', 'CineFan Admin Panel');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development'); // cambiar a production cuando subamos

// configuracion de seguridad
define('ADMIN_SESSION_NAME', 'cinefan_admin_session');
define('CSRF_TOKEN_NAME', 'cinefan_csrf_token');
define('PASSWORD_MIN_LENGTH', 6);

// rutas de archivos (rutas absolutas para evitar errores)
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');
define('LOGS_PATH', dirname(__DIR__) . '/logs/');
define('PDF_TEMP_PATH', dirname(__DIR__) . '/temp/pdf/');

// limites para paginacion
define('DEFAULT_PAGE_SIZE', 10);
define('MAX_PAGE_SIZE', 100);

// configuracion de PDFs
define('PDF_AUTHOR', 'Juan Carlos y Angel');
define('PDF_CREATOR', 'CineFan Admin Panel');
define('PDF_TITLE_PREFIX', 'CineFan - ');

// logs del sistema
define('ENABLE_LOGGING', true);
define('LOG_LEVEL', 'INFO'); // DEBUG INFO WARNING ERROR

// validaciones para formularios
define('MAX_TITLE_LENGTH', 200);
define('MAX_DESCRIPTION_LENGTH', 1000);
define('MIN_YEAR', 1895);
define('MAX_YEAR', 2030);
define('MIN_DURATION', 1);
define('MAX_DURATION', 600);

// configuracion de reseñas
define('MAX_REVIEW_LENGTH', 1000);

// crear directorios si no existen
$dirs = [UPLOAD_PATH, LOGS_PATH, PDF_TEMP_PATH];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}
?>