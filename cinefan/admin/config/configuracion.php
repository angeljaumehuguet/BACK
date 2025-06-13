<?php
// datos de conexion a la base de datos
define('HOST_BD', 'localhost');
define('NOMBRE_BD', 'cinefan_db');
define('USUARIO_BD', 'cinefan');
define('CLAVE_BD', 'angel');
define('CHARSET_BD', 'utf8mb4');

// informacion basica de la aplicacion
define('NOMBRE_APP', 'CineFan Panel Admin');
define('VERSION_APP', '1.0.0');
define('ENTORNO_APP', 'desarrollo'); // cambiar a produccion cuando subamos

// configuracion de seguridad
define('NOMBRE_SESION_ADMIN', 'cinefan_admin_sesion');
define('NOMBRE_TOKEN_CSRF', 'cinefan_csrf_token');
define('LONGITUD_MIN_CLAVE', 6);

// rutas de archivos (rutas absolutas para evitar errores)
define('RUTA_SUBIDAS', dirname(__DIR__) . '/subidas/');
define('RUTA_LOGS', dirname(__DIR__) . '/logs/');
define('RUTA_PDF_TEMP', dirname(__DIR__) . '/temp/pdf/');

// limites para paginacion
define('TAMAÑO_PAGINA_DEFECTO', 10);
define('TAMAÑO_PAGINA_MAXIMO', 100);

// configuracion de PDFs
define('AUTOR_PDF', 'Juan Carlos y Angel');
define('CREADOR_PDF', 'CineFan Panel Admin');
define('PREFIJO_TITULO_PDF', 'CineFan - ');

// logs del sistema
define('ACTIVAR_LOGS', true);
define('NIVEL_LOG', 'INFO'); // DEBUG INFO WARNING ERROR

// validaciones para formularios
define('LONGITUD_MAX_TITULO', 200);
define('LONGITUD_MAX_DESCRIPCION', 1000);
define('AÑO_MIN', 1895);
define('AÑO_MAX', 2030);
define('DURACION_MIN', 1);
define('DURACION_MAX', 600);

// configuracion de resenas
define('LONGITUD_MAX_RESENA', 1000);

// crear directorios si no existen
$directorios = [RUTA_SUBIDAS, RUTA_LOGS, RUTA_PDF_TEMP];
foreach ($directorios as $directorio) {
    if (!is_dir($directorio)) {
        @mkdir($directorio, 0755, true);
    }
}
?>