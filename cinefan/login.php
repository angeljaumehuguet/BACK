<?php
/**
 * Aqui va el login para acceder al panel admin
 * Solo pueden entrar los usuarios que tengan rol de administrador en la BD
 */

// empezamos la sesion como siempre hacemos
session_start();

// incluir las configuraciones necesarias
require_once 'config/configuracion.php';
require_once 'config/basedatos.php';
require_once 'includes/seguridad.php';

// esto redirige si ya esta logueado para no estar en bucle
if (Seguridad::estaAutenticado()) {
    header('Location: index.php');
    exit;
}

// aqui van las variables para manejar errores y mensajes
$error = '';
$mensaje = '';

// procesar el formulario cuando lo envien
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave = $_POST['clave'] ?? '';
    
    // validar que no vengan vacios los campos
    if (empty($usuario) || empty($clave)) {
        $error = 'Usuario y contraseña son obligatorios';
    } else {
        try {
            // aqui conectamos con la base de datos
            $bd = new BaseDatosAdmin();
            
            // buscar al usuario en la BD con rol admin
            $sql = "SELECT u.*, r.nombre as rol_nombre 
                   FROM usuarios u 
                   LEFT JOIN roles r ON u.rol_id = r.id 
                   WHERE (u.nombre_usuario = ? OR u.email = ?) 
                   AND u.activo = 1 
                   AND (r.nombre = 'administrador' OR r.nombre = 'admin')";
            
            $usuarioEncontrado = $bd->obtenerUno($sql, [$usuario, $usuario]);
            
            // verificar si existe y la clave es correcta
            if ($usuarioEncontrado && password_verify($clave, $usuarioEncontrado['password'])) {
                // todo bien iniciamos sesion
                Seguridad::iniciarSesionAdmin($usuarioEncontrado['id'], $usuarioEncontrado['nombre_usuario']);
                
                // registrar el acceso en logs
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
                file_put_contents('logs/accesos_admin.log', 
                    date('Y-m-d H:i:s') . " - Login exitoso: {$usuarioEncontrado['nombre_usuario']} desde IP: $ip\n", 
                    FILE_APPEND
                );
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'Credenciales incorrectas o sin permisos de administrador';
                
                // registrar intento fallido
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
                file_put_contents('logs/accesos_admin.log', 
                    date('Y-m-d H:i:s') . " - Login fallido: $usuario desde IP: $ip\n", 
                    FILE_APPEND
                );
            }
            
        } catch (Exception $e) {
            $error = 'Error de conexion con la base de datos';
            // registrar el error en logs
            file_put_contents('logs/errores_admin.log', 
                date('Y-m-d H:i:s') . " - Error login: " . $e->getMessage() . "\n", 
                FILE_APPEND
            );
        }
    }
}

// comprobar si hay mensajes en la URL
if (isset($_GET['mensaje'])) {
    switch ($_GET['mensaje']) {
        case 'sesion_expirada':
            $mensaje = 'Tu sesión ha expirado por inactividad';
            break;
        case 'acceso_denegado':
            $mensaje = 'Debes iniciar sesión para acceder al panel';
            break;
        case 'sesion_cerrada':
            $mensaje = 'Has cerrado sesión correctamente';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Administrativo - CineFan</title>
    
    <!-- aqui van los estilos de bootstrap y fontawesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* estilos personalizados para el login */
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container-login {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            padding: 40px;
            max-width: 450px;
            width: 100%;
        }
        
        .logo-admin {
            color: #fff;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            margin-bottom: 30px;
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            padding: 12px 15px;
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.95);
            border-color: #f093fb;
            box-shadow: 0 0 0 0.2rem rgba(240, 147, 251, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(45deg, #f093fb 0%, #f5576c 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            color: white;
            font-weight: bold;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 87, 108, 0.4);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .form-label {
            color: #fff;
            font-weight: 600;
        }
        
        .texto-info {
            color: rgba(255, 255, 255, 0.8);
            text-align: center;
            margin-top: 20px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="container-login">
            
            <!-- aqui va el logo y titulo del panel -->
            <div class="text-center logo-admin">
                <i class="fas fa-shield-halved fa-4x mb-3"></i>
                <h2 class="mb-2">Panel Administrativo</h2>
                <p class="mb-0">CineFan Management System</p>
                <small>v<?= VERSION_APP ?></small>
            </div>
            
            <!-- mostrar errores si los hay -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <!-- mostrar mensajes informativos -->
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-info d-flex align-items-center">
                    <i class="fas fa-info-circle me-2"></i>
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>
            
            <!-- formulario de login -->
            <form method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="usuario" class="form-label">
                        <i class="fas fa-user me-1"></i> Usuario o Email
                    </label>
                    <input type="text" 
                           class="form-control" 
                           id="usuario" 
                           name="usuario" 
                           required 
                           maxlength="<?= LONGITUD_MAX_NOMBRE_USUARIO ?>"
                           placeholder="Introduce tu usuario o email"
                           value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
                    <div class="invalid-feedback">
                        Por favor introduce tu usuario o email
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="clave" class="form-label">
                        <i class="fas fa-lock me-1"></i> Contraseña
                    </label>
                    <div class="input-group">
                        <input type="password" 
                               class="form-control" 
                               id="clave" 
                               name="clave" 
                               required 
                               minlength="<?= LONGITUD_MIN_CLAVE ?>"
                               placeholder="Introduce tu contraseña">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback">
                        La contraseña debe tener al menos <?= LONGITUD_MIN_CLAVE ?> caracteres
                    </div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Acceder al Panel
                    </button>
                </div>
            </form>
            
            <!-- informacion adicional -->
            <div class="texto-info">
                <p class="mb-1">
                    <i class="fas fa-shield-alt me-1"></i>
                    Acceso solo para administradores
                </p>
                <p class="mb-0">
                    <i class="fas fa-clock me-1"></i>
                    Sesiones con timeout de seguridad
                </p>
            </div>
            
        </div>
    </div>
    
    <!-- scripts de bootstrap y validacion -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // validacion del formulario en el lado cliente
        (function() {
            'use strict';
            
            // obtener el formulario
            var formulario = document.querySelector('.needs-validation');
            
            // cuando se envie el formulario
            formulario.addEventListener('submit', function(evento) {
                if (!formulario.checkValidity()) {
                    evento.preventDefault();
                    evento.stopPropagation();
                }
                
                formulario.classList.add('was-validated');
            }, false);
        })();
        
        // funcionalidad para mostrar/ocultar contraseña
        document.getElementById('togglePassword').addEventListener('click', function() {
            const campoPassword = document.getElementById('clave');
            const icono = this.querySelector('i');
            
            if (campoPassword.type === 'password') {
                campoPassword.type = 'text';
                icono.classList.remove('fa-eye');
                icono.classList.add('fa-eye-slash');
            } else {
                campoPassword.type = 'password';
                icono.classList.remove('fa-eye-slash');
                icono.classList.add('fa-eye');
            }
        });
        
        // limpiar mensajes despues de unos segundos
        setTimeout(function() {
            const alertas = document.querySelectorAll('.alert');
            alertas.forEach(function(alerta) {
                if (!alerta.classList.contains('alert-danger')) {
                    alerta.style.opacity = '0';
                    setTimeout(function() {
                        alerta.remove();
                    }, 300);
                }
            });
        }, 5000);
    </script>
</body>
</html>