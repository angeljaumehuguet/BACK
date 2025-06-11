<?php
session_start();

// verificar autenticación
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../api/config/database.php';

try {
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // estadísticas generales
    $stats = [];
    
    // total usuarios
    $stmt = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = true");
    $stats['usuarios'] = $stmt->fetch()['total'];
    
    // total películas
    $stmt = $conn->query("SELECT COUNT(*) as total FROM peliculas WHERE activo = true");
    $stats['peliculas'] = $stmt->fetch()['total'];
    
    // total reseñas
    $stmt = $conn->query("SELECT COUNT(*) as total FROM resenas WHERE activo = true");
    $stats['resenas'] = $stmt->fetch()['total'];
    
    // usuarios activos (último mes)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM usuarios 
                          WHERE fecha_ultimo_acceso >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                          AND activo = true");
    $stats['usuarios_activos'] = $stmt->fetch()['total'];
    
    // películas por género
    $generos = $conn->query("SELECT g.nombre, COUNT(p.id) as total 
                             FROM generos g 
                             LEFT JOIN peliculas p ON g.id = p.genero_id AND p.activo = true
                             GROUP BY g.id, g.nombre 
                             ORDER BY total DESC")->fetchAll();
    
    // usuarios más activos
    $usuariosActivos = $conn->query("SELECT u.nombre_usuario, u.nombre_completo,
                                           COUNT(DISTINCT p.id) as peliculas,
                                           COUNT(DISTINCT r.id) as resenas
                                    FROM usuarios u
                                    LEFT JOIN peliculas p ON u.id = p.id_usuario_creador AND p.activo = true
                                    LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = true
                                    WHERE u.activo = true
                                    GROUP BY u.id
                                    HAVING (peliculas + resenas) > 0
                                    ORDER BY (peliculas + resenas) DESC
                                    LIMIT 10")->fetchAll();
    
    // reseñas recientes
    $resenasRecientes = $conn->query("SELECT r.puntuacion, r.texto_resena, r.fecha_resena,
                                           u.nombre_usuario, p.titulo as pelicula
                                    FROM resenas r
                                    INNER JOIN usuarios u ON r.id_usuario = u.id
                                    INNER JOIN peliculas p ON r.id_pelicula = p.id
                                    WHERE r.activo = true
                                    ORDER BY r.fecha_resena DESC
                                    LIMIT 5")->fetchAll();
    
} catch (Exception $e) {
    $error = "Error al cargar estadísticas: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CineFan Admin</title>
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>Dashboard</h1>
                <p>Resumen general del sistema CineFan</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['usuarios']); ?></h3>
                        <p>Usuarios Registrados</p>
                        <small><?php echo number_format($stats['usuarios_activos']); ?> activos este mes</small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🎬</div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['peliculas']); ?></h3>
                        <p>Películas</p>
                        <small>En la base de datos</small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['resenas']); ?></h3>
                        <p>Reseñas</p>
                        <small>Publicadas por usuarios</small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['peliculas'] > 0 ? $stats['resenas'] / $stats['peliculas'] : 0, 1); ?></h3>
                        <p>Promedio Reseñas</p>
                        <small>Por película</small>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Películas por Género</h3>
                    </div>
                    <div class="card-content">
                        <div class="chart-container">
                            <?php foreach ($generos as $genero): ?>
                                <div class="bar-item">
                                    <span class="bar-label"><?php echo htmlspecialchars($genero['nombre']); ?></span>
                                    <div class="bar-container">
                                        <div class="bar-fill" 
                                             style="width: <?php echo $stats['peliculas'] > 0 ? ($genero['total'] / $stats['peliculas']) * 100 : 0; ?>%"></div>
                                        <span class="bar-value"><?php echo $genero['total']; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Usuarios Más Activos</h3>
                    </div>
                    <div class="card-content">
                        <div class="user-list">
                            <?php foreach ($usuariosActivos as $usuario): ?>
                                <div class="user-item">
                                    <div class="user-info">
                                        <strong><?php echo htmlspecialchars($usuario['nombre_completo']); ?></strong>
                                        <small>@<?php echo htmlspecialchars($usuario['nombre_usuario']); ?></small>
                                    </div>
                                    <div class="user-stats">
                                        <span class="badge"><?php echo $usuario['peliculas']; ?> películas</span>
                                        <span class="badge"><?php echo $usuario['resenas']; ?> reseñas</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card full-width">
                    <div class="card-header">
                        <h3>Reseñas Recientes</h3>
                    </div>
                    <div class="card-content">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Película</th>
                                        <th>Puntuación</th>
                                        <th>Reseña</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resenasRecientes as $resena): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($resena['nombre_usuario']); ?></td>
                                            <td><?php echo htmlspecialchars($resena['pelicula']); ?></td>
                                            <td>
                                                <div class="rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <span class="star <?php echo $i <= $resena['puntuacion'] ? 'filled' : ''; ?>">⭐</span>
                                                    <?php endfor; ?>
                                                </div>
                                            </td>
                                            <td class="text-truncate">
                                                <?php echo htmlspecialchars(substr($resena['texto_resena'], 0, 100)) . '...'; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($resena['fecha_resena'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="js/admin.js"></script>
</body>
</html>