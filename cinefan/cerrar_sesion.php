<?php
session_start();

// limpiar todas las variables de sesion
$_SESSION = array();

// eliminar la cookie de sesion si existe
if (ini_get("session.use_cookies")) {
    $parametros_cookie = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $parametros_cookie["path"], $parametros_cookie["domain"],
        $parametros_cookie["secure"], $parametros_cookie["httponly"]
    );
}

// destruir la sesion
session_destroy();

// redirigir al login con mensaje
header('Location: login.php?mensaje=sesion_cerrada');
exit;
?>