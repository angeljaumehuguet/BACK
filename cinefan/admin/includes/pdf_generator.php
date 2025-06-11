<?php
// Clase simple para generar PDF (sin dependencias externas)
class SimplePDF {
    private $content = '';
    private $title = '';
    
    public function __construct($title = 'Reporte CineFan') {
        $this->title = $title;
        $this->content = $this->getHeader();
    }
    
    private function getHeader() {
        return "
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; border-bottom: 2px solid #ac6cfc; padding-bottom: 20px; margin-bottom: 30px; }
            .title { color: #ac6cfc; font-size: 24px; font-weight: bold; }
            .subtitle { color: #666; font-size: 14px; margin-top: 5px; }
            .date { color: #999; font-size: 12px; margin-top: 10px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #f8f9fa; font-weight: bold; }
            .stats { display: flex; gap: 20px; margin: 20px 0; }
            .stat-item { background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; }
            .stat-number { font-size: 24px; font-weight: bold; color: #ac6cfc; }
            .footer { text-align: center; margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
        </style>
        
        <div class='header'>
            <div class='title'>🎬 CineFan</div>
            <div class='subtitle'>Sistema de Gestión de Reseñas Cinematográficas</div>
            <div class='date'>Reporte generado el " . date('d/m/Y H:i') . "</div>
        </div>";
    }
    
    public function addContent($html) {
        $this->content .= $html;
    }
    
    public function output() {
        $this->content .= "
        <div class='footer'>
            <p>CineFan - Panel de Administración v1.0</p>
            <p>Generado automáticamente por el sistema</p>
        </div>";
        
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="reporte_cinefan_' . date('Y-m-d_H-i') . '.html"');
        echo $this->content;
    }
}

// Generar reporte según el tipo
$tipo = $_GET['tipo'] ?? 'general';

try {
    $db = getDatabase();
    $conn = $db->getConnection();
    
    switch ($tipo) {
        case 'usuarios':
            $pdf = new SimplePDF('Reporte de Usuarios - CineFan');
            
            // Estadísticas
            $stats = $conn->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
                SUM(CASE WHEN fecha_ultimo_acceso >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as activos_mes
                FROM usuarios")->fetch();
            
            $pdf->addContent("
            <h2>Reporte de Usuarios</h2>
            <div class='stats'>
                <div class='stat-item'>
                    <div class='stat-number'>{$stats['total']}</div>
                    <div>Total Usuarios</div>
                </div>
                <div class='stat-item'>
                    <div class='stat-number'>{$stats['activos']}</div>
                    <div>Usuarios Activos</div>
                </div>
                <div class='stat-item'>
                    <div class='stat-number'>{$stats['activos_mes']}</div>
                    <div>Activos Este Mes</div>
                </div>
            </div>");
            
            // Lista de usuarios
            $usuarios = $conn->query("SELECT nombre_usuario, email, nombre_completo, fecha_registro, activo,
                COUNT(DISTINCT p.id) as peliculas, COUNT(DISTINCT r.id) as resenas
                FROM usuarios u
                LEFT JOIN peliculas p ON u.id = p.id_usuario_creador
                LEFT JOIN resenas r ON u.id = r.id_usuario
                GROUP BY u.id ORDER BY u.fecha_registro DESC")->fetchAll();
            
            $pdf->addContent("<h3>Lista de Usuarios</h3>");
            $pdf->addContent("<table>");
            $pdf->addContent("<tr><th>Usuario</th><th>Email</th><th>Nombre</th><th>Películas</th><th>Reseñas</th><th>Registro</th><th>Estado</th></tr>");
            
            foreach ($usuarios as $usuario) {
                $estado = $usuario['activo'] ? 'Activo' : 'Inactivo';
                $fecha = date('d/m/Y', strtotime($usuario['fecha_registro']));
                $pdf->addContent("<tr>
                    <td>{$usuario['nombre_usuario']}</td>
                    <td>{$usuario['email']}</td>
                    <td>{$usuario['nombre_completo']}</td>
                    <td>{$usuario['peliculas']}</td>
                    <td>{$usuario['resenas']}</td>
                    <td>{$fecha}</td>
                    <td>{$estado}</td>
                </tr>");
            }
            $pdf->addContent("</table>");
            break;
            
        case 'peliculas':
            $pdf = new SimplePDF('Reporte de Películas - CineFan');
            
            // Estadísticas por género
            $generos = $conn->query("SELECT g.nombre, COUNT(p.id) as total, AVG(r.puntuacion) as promedio
                FROM generos g 
                LEFT JOIN peliculas p ON g.id = p.genero_id AND p.activo = 1
                LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
                GROUP BY g.id ORDER BY total DESC")->fetchAll();
            
            $pdf->addContent("<h2>Reporte de Películas por Género</h2>");
            $pdf->addContent("<table>");
            $pdf->addContent("<tr><th>Género</th><th>Películas</th><th>Puntuación Promedio</th></tr>");
            
            foreach ($generos as $genero) {
                $promedio = $genero['promedio'] ? number_format($genero['promedio'], 1) : 'N/A';
                $pdf->addContent("<tr>
                    <td>{$genero['nombre']}</td>
                    <td>{$genero['total']}</td>
                    <td>{$promedio}</td>
                </tr>");
            }
            $pdf->addContent("</table>");
            break;
            
        case 'general':
            $pdf = new SimplePDF('Reporte General - CineFan');
            
            // Estadísticas generales
            $stats = $conn->query("SELECT 
                (SELECT COUNT(*) FROM usuarios WHERE activo = 1) as usuarios,
                (SELECT COUNT(*) FROM peliculas WHERE activo = 1) as peliculas,
                (SELECT COUNT(*) FROM resenas WHERE activo = 1) as resenas,
                (SELECT AVG(puntuacion) FROM resenas WHERE activo = 1) as promedio_puntuacion
            ")->fetch();
            
            $pdf->addContent("
            <h2>Reporte General del Sistema</h2>
            <div class='stats'>
                <div class='stat-item'>
                    <div class='stat-number'>{$stats['usuarios']}</div>
                    <div>Usuarios</div>
                </div>
                <div class='stat-item'>
                    <div class='stat-number'>{$stats['peliculas']}</div>
                    <div>Películas</div>
                </div>
                <div class='stat-item'>
                    <div class='stat-number'>{$stats['resenas']}</div>
                    <div>Reseñas</div>
                </div>
                <div class='stat-item'>
                    <div class='stat-number'>" . number_format($stats['promedio_puntuacion'], 1) . "</div>
                    <div>Puntuación Promedio</div>
                </div>
            </div>");
            break;
    }
    
    $pdf->output();
    
} catch (Exception $e) {
    echo "Error generando reporte: " . $e->getMessage();
}