<?php
/**
 * Aqui va el dashboard principal donde los admins pueden ver todo
 * Tiene estadisticas graficos y accesos rapidos a las funciones
 */

// iniciar configuracion del admin
require_once 'includes/inicializar.php';

// verificar que este logueado como admin
if (!Seguridad::estaAutenticado()) {
    header('Location: login.php?mensaje=acceso_denegado');
    exit;
}

// obtener info del usuario actual
$usuarioActual = Seguridad::obtenerUsuarioActual();

try {
    // conectar con la BD para obtener estadisticas
    $bd = new BaseDatosAdmin();
    
    // obtener estadisticas generales del sistema
    $stats = [];
    
    // total de usuarios
    $stats['usuarios'] = $bd->obtenerUno("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1")['total'] ?? 0;
    $stats['usuarios_hoy'] = $bd->obtenerUno("SELECT COUNT(*) as total FROM usuarios WHERE DATE(fecha_registro) = CURDATE()")['total'] ?? 0;
    
    // total de peliculas
    $stats['peliculas'] = $bd->obtenerUno("SELECT COUNT(*) as total FROM peliculas")['total'] ?? 0;
    $stats['peliculas_mes'] = $bd->obtenerUno("SELECT COUNT(*) as total FROM peliculas WHERE MONTH(fecha_agregada) = MONTH(CURDATE()) AND YEAR(fecha_agregada) = YEAR(CURDATE())")['total'] ?? 0;
    
    // total de resenas
    $stats['resenas'] = $bd->obtenerUno("SELECT COUNT(*) as total FROM resenas")['total'] ?? 0;
    $stats['resenas_semana'] = $bd->obtenerUno("SELECT COUNT(*) as total FROM resenas WHERE fecha_resena >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")['total'] ?? 0;
    
    // total de generos
    $stats['generos'] = $bd->obtenerUno("SELECT COUNT(*) as total FROM generos WHERE activo = 1")['total'] ?? 0;
    
    // puntuacion promedio
    $promedio = $bd->obtenerUno("SELECT AVG(puntuacion) as promedio FROM resenas");
    $stats['puntuacion_promedio'] = $promedio ? round($promedio['promedio'], 1) : 0;
    
    // obtener actividad reciente
    $actividadReciente = $bd->obtenerTodos("
        SELECT 'usuario' as tipo, nombre_usuario as titulo, fecha_registro as fecha, 'success' as color
        FROM usuarios 
        WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'pelicula' as tipo, titulo, fecha_agregada as fecha, 'info' as color
        FROM peliculas 
        WHERE fecha_agregada >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'resena' as tipo, CONCAT('Reseña: ', p.titulo) as titulo, r.fecha_resena as fecha, 'warning' as color
        FROM resenas r
        JOIN peliculas p ON r.id_pelicula = p.id
        WHERE r.fecha_resena >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY fecha DESC
        LIMIT 10
    ");
    
    // top peliculas mejor puntuadas
    $topPeliculas = $bd->obtenerTodos("
        SELECT p.titulo, p.ano_lanzamiento, AVG(r.puntuacion) as promedio, COUNT(r.id) as total_resenas
        FROM peliculas p
        LEFT JOIN resenas r ON p.id = r.id_pelicula
        GROUP BY p.id
        HAVING total_resenas >= 1
        ORDER BY promedio DESC, total_resenas DESC
        LIMIT 5
    ");
    
    // usuarios mas activos
    $usuariosActivos = $bd->obtenerTodos("
        SELECT u.nombre_usuario, u.nombre_completo, COUNT(r.id) as total_resenas
        FROM usuarios u
        LEFT JOIN resenas r ON u.id = r.id_usuario
        WHERE u.activo = 1
        GROUP BY u.id
        ORDER BY total_resenas DESC
        LIMIT 5
    ");
    
} catch (Exception $e) {
    // si hay error en las consultas usar valores por defecto
    $stats = [
        'usuarios' => 0, 'usuarios_hoy' => 0,
        'peliculas' => 0, 'peliculas_mes' => 0,
        'resenas' => 0, 'resenas_semana' => 0,
        'generos' => 0, 'puntuacion_promedio' => 0
    ];
    $actividadReciente = [];
    $topPeliculas = [];
    $usuariosActivos = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo - CineFan</title>
    
    <!-- estilos externos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.0.1/dist/chart.min.css" rel="stylesheet">
    
    <style>
        /* estilos personalizados para el panel */
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            padding-top: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .logo {
            text-align: center;
            padding: 20px;
            color: white;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 20px;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .card-stat {
            border: none;
            border-radius: 15px;
            background: white;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            transition: transform 0.2s ease;
        }
        
        .card-stat:hover {
            transform: translateY(-5px);
        }
        
        .card-stat .card-body {
            padding: 25px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 2.2em;
            font-weight: bold;
            color: #2c3e50;
            margin: 0;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
            margin: 0;
        }
        
        .stat-change {
            font-size: 0.8em;
            margin-top: 5px;
        }
        
        .bg-primary-gradient { background: linear-gradient(45deg, #667eea, #764ba2); }
        .bg-success-gradient { background: linear-gradient(45deg, #56ab2f, #a8e6cf); }
        .bg-warning-gradient { background: linear-gradient(45deg, #f093fb, #f5576c); }
        .bg-info-gradient { background: linear-gradient(45deg, #4facfe, #00f2fe); }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        .badge-activity {
            font-size: 0.7em;
            padding: 4px 8px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .btn-admin {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-admin:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <!-- sidebar de navegacion -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-film fa-2x mb-2"></i>
            <h4>CineFan Admin</h4>
            <small>v<?= VERSION_APP ?></small>
        </div>
        
        <nav class="nav flex-column">
            <a class="nav-link active" href="index.php">
                <i class="fas fa-tachometer-alt"></i>
                Dashboard
            </a>
            <a class="nav-link" href="usuarios.php">
                <i class="fas fa-users"></i>
                Usuarios
            </a>
            <a class="nav-link" href="peliculas.php">
                <i class="fas fa-film"></i>
                Películas
            </a>
            <a class="nav-link" href="resenas.php">
                <i class="fas fa-star"></i>
                Reseñas
            </a>
            <a class="nav-link" href="generos.php">
                <i class="fas fa-tags"></i>
                Géneros
            </a>
            <a class="nav-link" href="reportes.php">
                <i class="fas fa-chart-bar"></i>
                Reportes
            </a>
            <a class="nav-link" href="pruebas/ejecutar_pruebas.php">
                <i class="fas fa-vial"></i>
                Tests & Pruebas
            </a>
            <hr style="border-color: rgba(255,255,255,0.2); margin: 20px 15px;">
            <a class="nav-link" href="configuracion.php">
                <i class="fas fa-cog"></i>
                Configuración
            </a>
            <a class="nav-link" href="cerrar_sesion.php">
                <i class="fas fa-sign-out-alt"></i>
                Cerrar Sesión
            </a>
        </nav>
    </div>
    
    <!-- contenido principal -->
    <div class="content">
        <!-- tarjeta de bienvenida -->
        <div class="card welcome-card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3 class="mb-2">¡Bienvenido, <?= htmlspecialchars($usuarioActual['nombre_usuario']) ?>!</h3>
                        <p class="mb-0">Panel de control administrativo de CineFan. Aquí puedes gestionar todos los aspectos del sistema.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex justify-content-end align-items-center">
                            <div class="me-3">
                                <small>Última sesión:</small><br>
                                <small><?= date('d/m/Y H:i', $usuarioActual['tiempo_inicio']) ?></small>
                            </div>
                            <i class="fas fa-user-shield fa-3x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- tarjetas de estadisticas -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card card-stat">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-primary-gradient mx-auto">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="stat-number"><?= number_format($stats['usuarios']) ?></h3>
                        <p class="stat-label">Usuarios Registrados</p>
                        <small class="stat-change text-success">
                            <i class="fas fa-arrow-up"></i> +<?= $stats['usuarios_hoy'] ?> hoy
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card card-stat">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-success-gradient mx-auto">
                            <i class="fas fa-film"></i>
                        </div>
                        <h3 class="stat-number"><?= number_format($stats['peliculas']) ?></h3>
                        <p class="stat-label">Películas en Sistema</p>
                        <small class="stat-change text-success">
                            <i class="fas fa-arrow-up"></i> +<?= $stats['peliculas_mes'] ?> este mes
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card card-stat">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-warning-gradient mx-auto">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3 class="stat-number"><?= number_format($stats['resenas']) ?></h3>
                        <p class="stat-label">Reseñas Publicadas</p>
                        <small class="stat-change text-success">
                            <i class="fas fa-arrow-up"></i> +<?= $stats['resenas_semana'] ?> esta semana
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card card-stat">
                    <div class="card-body text-center">
                        <div class="stat-icon bg-info-gradient mx-auto">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 class="stat-number"><?= $stats['puntuacion_promedio'] ?></h3>
                        <p class="stat-label">Puntuación Promedio</p>
                        <small class="stat-change">
                            <i class="fas fa-star text-warning"></i> de 5 estrellas
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- actividad reciente -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>
                            Actividad Reciente
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($actividadReciente)): ?>
                            <p class="text-muted text-center py-3">No hay actividad reciente</p>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($actividadReciente as $actividad): ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <span class="badge bg-<?= $actividad['color'] ?> badge-activity me-3">
                                            <?= ucfirst($actividad['tipo']) ?>
                                        </span>
                                        <div class="flex-grow-1">
                                            <p class="mb-0"><?= htmlspecialchars($actividad['titulo']) ?></p>
                                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($actividad['fecha'])) ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- top peliculas -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy me-2"></i>
                            Top Películas
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topPeliculas)): ?>
                            <p class="text-muted text-center py-3">No hay datos de películas</p>
                        <?php else: ?>
                            <?php foreach ($topPeliculas as $index => $pelicula): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge bg-primary me-3">#<?= $index + 1 ?></span>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?= htmlspecialchars($pelicula['titulo']) ?></h6>
                                        <small class="text-muted"><?= $pelicula['ano_lanzamiento'] ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="text-warning">
                                            <?= round($pelicula['promedio'], 1) ?> <i class="fas fa-star"></i>
                                        </span><br>
                                        <small class="text-muted"><?= $pelicula['total_resenas'] ?> reseñas</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- accesos rapidos -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Accesos Rápidos
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="usuarios.php?accion=crear" class="btn btn-primary btn-admin w-100">
                            <i class="fas fa-user-plus me-2"></i>
                            Crear Usuario
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="peliculas.php?accion=crear" class="btn btn-success btn-admin w-100">
                            <i class="fas fa-film me-2"></i>
                            Agregar Película
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="reportes.php?tipo=usuarios" class="btn btn-info btn-admin w-100">
                            <i class="fas fa-file-pdf me-2"></i>
                            Generar Reporte
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="pruebas/ejecutar_pruebas.php" class="btn btn-warning btn-admin w-100">
                            <i class="fas fa-vial me-2"></i>
                            Ejecutar Tests
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // actualizar estadisticas cada 30 segundos
        setInterval(function() {
            // aqui podriamos hacer una peticion AJAX para actualizar stats
            console.log('Actualizando estadisticas...');
        }, 30000);
        
        // animar las tarjetas de estadisticas al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card-stat');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
        });
    </script>
</body>
</html>