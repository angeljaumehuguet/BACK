<?php
session_start();

// verificar autenticación
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../api/config/database.php';

// verificar si se solicita generar PDF
if (isset($_GET['generar']) && $_GET['generar'] === 'pdf') {
    require_once 'includes/pdf_generator.php';
    exit();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - CineFan Admin</title>
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>Reportes</h1>
                <p>Generar reportes en PDF del sistema CineFan</p>
            </div>
            
            <div class="reports-grid">
                <div class="report-card">
                    <div class="report-icon">👥</div>
                    <div class="report-content">
                        <h3>Reporte de Usuarios</h3>
                        <p>Lista completa de usuarios registrados con estadísticas</p>
                        <a href="?generar=pdf&tipo=usuarios" class="btn btn-primary" target="_blank">
                            Generar PDF
                        </a>
                    </div>
                </div>
                
                <div class="report-card">
                    <div class="report-icon">🎬</div>
                    <div class="report-content">
                        <h3>Reporte de Películas</h3>
                        <p>Catálogo de películas por género y puntuación</p>
                        <a href="?generar=pdf&tipo=peliculas" class="btn btn-primary" target="_blank">
                            Generar PDF
                        </a>
                    </div>
                </div>
                
                <div class="report-card">
                    <div class="report-icon">📝</div>
                    <div class="report-content">
                        <h3>Reporte de Reseñas</h3>
                        <p>Actividad de reseñas y engagement</p>
                        <a href="?generar=pdf&tipo=resenas" class="btn btn-primary" target="_blank">
                            Generar PDF
                        </a>
                    </div>
                </div>
                
                <div class="report-card">
                    <div class="report-icon">📊</div>
                    <div class="report-content">
                        <h3>Reporte General</h3>
                        <p>Estadísticas completas del sistema</p>
                        <a href="?generar=pdf&tipo=general" class="btn btn-primary" target="_blank">
                            Generar PDF
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="js/admin.js"></script>
</body>
</html>