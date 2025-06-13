<?php
session_start();

// redirigir si ya esta logueado
if (isset($_SESSION['admin_logueado']) && $_SESSION['admin_logueado']) {
    header('Location: index.php');
    exit;
}

$error = '';

// procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave = $_POST['clave'] ?? '';
    
    // pero para el proyecto usamos credenciales estaticas
    $admins_validos = [
        'admin_juan' => 'juan1234',
        'admin_angel' => 'angel1234',
        'administrador' => 'cinefan1234'
    ];
    
    if (isset($admins_validos[$usuario]) && $admins_validos[$usuario] === $clave) {
        $_SESSION['admin_logueado'] = true;
        $_SESSION['admin_usuario'] = $usuario;
        $_SESSION['admin_inicio'] = time();
        
        header('Location: index.php');
        exit;
    } else {
        $error = 'Credenciales incorrectas';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Admin - CineFan</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-login {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
        }
        .form-control {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .btn-admin {
            background: linear-gradient(45deg, #f093fb 0%, #f5576c 100%);
            border: none;
            color: white;
            font-weight: bold;
        }
        .logo-admin {
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card card-login">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-shield fa-3x logo-admin mb-3"></i>
                            <h3 class="text-white">Panel Administrativo</h3>
                            <p class="text-white-50">CineFan Management System</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="usuario" class="form-label text-white">
                                    <i class="fas fa-user"></i> Usuario
                                </label>
                                <input type="text" class="form-control" id="usuario" name="usuario" required
                                       placeholder="Introduce tu usuario admin">
                                <div class="invalid-feedback">
                                    El usuario es obligatorio
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="clave" class="form-label text-white">
                                    <i class="fas fa-lock"></i> Contraseña
                                </label>
                                <input type="password" class="form-control" id="clave" name="clave" required
                                       placeholder="Introduce tu contraseña">
                                <div class="invalid-feedback">
                                    La contraseña es obligatoria
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-admin btn-lg">
                                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <small class="text-white-50">
                                Desarrollado por Juan Carlos & Angel Hernandez<br>
                                DAM2 - Proyecto CineFan
                            </small>
                        </div>
                        
                        <!-- info de prueba solo en desarrollo -->
                        <div class="mt-4 p-3 bg-dark bg-opacity-25 rounded">
                            <small class="text-white-50">
                                <strong>Credenciales de prueba:</strong><br>
                                • admin_juan / juan1234<br>
                                • admin_angel / angel1234<br>
                                • administrador / cinefan1234
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // validacion del formulario
        (function() {
            'use strict';
            
            // aqui obtenemos todos los formularios que necesitan validacion
            const formularios = document.querySelectorAll('.needs-validation');
            
            // aqui iteramos sobre cada formulario
            Array.prototype.slice.call(formularios).forEach(function(formulario) {
                formulario.addEventListener('submit', function(evento) {
                    if (!formulario.checkValidity()) {
                        evento.preventDefault();
                        evento.stopPropagation();
                    }
                    formulario.classList.add('was-validated');
                }, false);
            });
        })();
        
        // auto focus en el campo usuario
        document.getElementById('usuario').focus();
        
        // aqui va el evento enter para pasar al siguiente campo
        document.getElementById('usuario').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('clave').focus();
            }
        });
    </script>
</body>
</html>