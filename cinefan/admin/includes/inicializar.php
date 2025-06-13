<?php
// definir rutas antes que nada
if (!defined('RUTA_ADMIN')) {
    define('RUTA_ADMIN', dirname(__DIR__));
}

// cargar configuracion principal
require_once RUTA_ADMIN . '/config/configuracion.php';
require_once RUTA_ADMIN . '/config/basedatos.php';

// verificar que todas las constantes necesarias esten definidas
$constantes_requeridas = [
    'RUTA_LOGS', 'NOMBRE_TOKEN_CSRF', 'RUTA_PDF_TEMP', 
    'RUTA_SUBIDAS', 'NOMBRE_SESION_ADMIN'
];

foreach ($constantes_requeridas as $constante) {
    if (!defined($constante)) {
        // definir constantes que faltan como respaldo
        switch ($constante) {
            case 'RUTA_LOGS':
                define('RUTA_LOGS', RUTA_ADMIN . '/logs/');
                break;
            case 'NOMBRE_TOKEN_CSRF':
                define('NOMBRE_TOKEN_CSRF', 'cinefan_csrf_token');
                break;
            case 'RUTA_PDF_TEMP':
                define('RUTA_PDF_TEMP', RUTA_ADMIN . '/temp/pdf/');
                break;
            case 'RUTA_SUBIDAS':
                define('RUTA_SUBIDAS', RUTA_ADMIN . '/subidas/');
                break;
            case 'NOMBRE_SESION_ADMIN':
                define('NOMBRE_SESION_ADMIN', 'cinefan_admin_sesion');
                break;
        }
    }
}

// crear directorios necesarios
$directorios = [RUTA_LOGS, RUTA_PDF_TEMP, RUTA_SUBIDAS];
foreach ($directorios as $directorio) {
    if (!is_dir($directorio)) {
        @mkdir($directorio, 0755, true);
    }
}

// inicializar sesion si no esta activa
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.name', NOMBRE_SESION_ADMIN);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    session_start();
}

// cargar clases principales
require_once RUTA_ADMIN . '/includes/registrador.php';
require_once RUTA_ADMIN . '/includes/seguridad.php';

// inicializar registrador
Registrador::inicializar();
Registrador::info('Sistema admin inicializado correctamente');

// funcion ayuda para verificar instalacion
function verificar_instalacion() {
    try {
        $bd = new BaseDatosAdmin();
        $bd->obtenerUno("SELECT 1 FROM usuarios LIMIT 1");
        return true;
    } catch (Exception $e) {
        Registrador::error('Error verificando instalacion: ' . $e->getMessage());
        return false;
    }
}

// funcion ayuda para redirigir con mensaje
function redirigir_con_mensaje($url, $mensaje, $tipo = 'info') {
    $_SESSION['mensaje_flash'] = $mensaje;
    $_SESSION['tipo_flash'] = $tipo;
    header("Location: $url");
    exit;
}

// funcion ayuda para mostrar mensajes flash
function mostrar_mensaje_flash() {
    if (isset($_SESSION['mensaje_flash'])) {
        $mensaje = $_SESSION['mensaje_flash'];
        $tipo = $_SESSION['tipo_flash'] ?? 'info';
        
        unset($_SESSION['mensaje_flash']);
        unset($_SESSION['tipo_flash']);
        
        $clase_css = [
            'exito' => 'alert-success',
            'error' => 'alert-danger', 
            'aviso' => 'alert-warning',
            'info' => 'alert-info'
        ][$tipo] ?? 'alert-info';
        
        echo "<div class='alert $clase_css alert-dismissible fade show' role='alert'>";
        echo htmlspecialchars($mensaje);
        echo "<button type='button' class='btn-close' data-bs-dismiss='alert'></button>";
        echo "</div>";
    }
}

// definir zona horaria
date_default_timezone_set('Europe/Madrid');

// configurar manejo de errores para desarrollo
if (defined('ENTORNO_APP') && ENTORNO_APP === 'desarrollo') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', RUTA_LOGS . 'errores_php.log');
}

Registrador::info('Inicializacion completada exitosamente');
?>