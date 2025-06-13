<?php
require_once '../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use TCPDF;

class CineFanPDFGenerator {
    private $db;
    private $pdf;

    public function __construct() {
        $this->db = new AdminDatabase();
        $this->setupPDF();
    }

    // configurar tcpdf
    private function setupPDF() {
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // info del documento
        $this->pdf->SetCreator('CineFan Admin Panel');
        $this->pdf->SetAuthor('Juan Carlos y Angel Hernandez - DAM2');
        $this->pdf->SetTitle('Reporte CineFan');
        $this->pdf->SetSubject('Datos administrativos');
        
        // margenes
        $this->pdf->SetMargins(15, 30, 15);
        $this->pdf->SetHeaderMargin(10);
        $this->pdf->SetFooterMargin(10);
        $this->pdf->SetAutoPageBreak(TRUE, 25);
    }

    // generar diferentes tipos de reportes
    public function generateReport($type) {
        switch ($type) {
            case 'usuarios':
                $this->reporteUsuarios();
                break;
            case 'peliculas':
                $this->reportePeliculas();
                break;
            case 'resenas':
                $this->reporteResenas();
                break;
            case 'generos':
                $this->reporteGeneros();
                break;
            case 'estadisticas':
                $this->reporteEstadisticas();
                break;
            default:
                throw new Exception('Tipo de reporte no valido');
        }
    }

    // reporte de usuarios
    private function reporteUsuarios() {
        $this->pdf->AddPage();
        
        // titulo
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->Cell(0, 15, 'Listado de Usuarios - CineFan', 0, 1, 'C');
        $this->pdf->Ln(5);
        
        // fecha
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 10, 'Generado: ' . date('d/m/Y H:i'), 0, 1, 'C');
        $this->pdf->Ln(10);
        
        // obtener datos
        $usuarios = $this->db->fetchAll("
            SELECT nombre_usuario, email, nombre_completo, fecha_registro, activo
            FROM usuarios
            ORDER BY fecha_registro DESC
        ");
        
        // estadisticas
        $stats = $this->db->fetchOne("
            SELECT 
                COUNT(*) as total,
                SUM(activo) as activos,
                COUNT(*) - SUM(activo) as inactivos
            FROM usuarios
        ");
        
        // mostrar resumen
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->Cell(0, 10, 'Resumen:', 0, 1, 'L');
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 8, "Total usuarios: {$stats['total']}", 0, 1, 'L');
        $this->pdf->Cell(0, 8, "Activos: {$stats['activos']}", 0, 1, 'L');
        $this->pdf->Cell(0, 8, "Inactivos: {$stats['inactivos']}", 0, 1, 'L');
        $this->pdf->Ln(10);
        
        // tabla headers
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->SetFillColor(108, 75, 162); // color morado
        $this->pdf->SetTextColor(255, 255, 255);
        
        $this->pdf->Cell(40, 8, 'Usuario', 1, 0, 'C', true);
        $this->pdf->Cell(60, 8, 'Email', 1, 0, 'C', true);
        $this->pdf->Cell(50, 8, 'Nombre', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Registro', 1, 1, 'C', true);
        
        // datos
        $this->pdf->SetFont('helvetica', '', 9);
        $this->pdf->SetTextColor(0, 0, 0);
        
        foreach ($usuarios as $usuario) {
            $this->pdf->Cell(40, 6, $usuario['nombre_usuario'], 1, 0, 'L');
            $this->pdf->Cell(60, 6, $usuario['email'], 1, 0, 'L');
            $this->pdf->Cell(50, 6, $usuario['nombre_completo'], 1, 0, 'L');
            $this->pdf->Cell(30, 6, date('d/m/Y', strtotime($usuario['fecha_registro'])), 1, 1, 'C');
        }
        
        $this->descargarPDF('Usuarios_' . date('Y-m-d'));
    }

    // reporte de peliculas
    private function reportePeliculas() {
        $this->pdf->AddPage();
        
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->Cell(0, 15, 'Catalogo de Peliculas - CineFan', 0, 1, 'C');
        $this->pdf->Ln(5);
        
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 10, 'Generado: ' . date('d/m/Y H:i'), 0, 1, 'C');
        $this->pdf->Ln(10);
        
        // obtener peliculas con estadisticas
        $peliculas = $this->db->fetchAll("
            SELECT p.titulo, p.director, p.ano_lanzamiento, g.nombre as genero,
                   COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
                   COUNT(r.id) as total_resenas
            FROM peliculas p
            LEFT JOIN generos g ON p.genero_id = g.id
            LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
            WHERE p.activo = 1
            GROUP BY p.id
            ORDER BY puntuacion_promedio DESC
        ");
        
        // stats generales
        $stats = $this->db->fetchOne("
            SELECT 
                COUNT(*) as total_peliculas,
                AVG(ano_lanzamiento) as ano_promedio,
                AVG(duracion_minutos) as duracion_promedio
            FROM peliculas
            WHERE activo = 1
        ");
        
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->Cell(0, 10, 'Resumen del Catalogo:', 0, 1, 'L');
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 8, "Total peliculas: {$stats['total_peliculas']}", 0, 1, 'L');
        $this->pdf->Cell(0, 8, "Año promedio: " . round($stats['ano_promedio']), 0, 1, 'L');
        $this->pdf->Cell(0, 8, "Duracion promedio: " . round($stats['duracion_promedio']) . " min", 0, 1, 'L');
        $this->pdf->Ln(10);
        
        // tabla headers
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->SetFillColor(108, 75, 162);
        $this->pdf->SetTextColor(255, 255, 255);
        
        $this->pdf->Cell(60, 8, 'Titulo', 1, 0, 'C', true);
        $this->pdf->Cell(40, 8, 'Director', 1, 0, 'C', true);
        $this->pdf->Cell(20, 8, 'Año', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Genero', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Puntuacion', 1, 1, 'C', true);
        
        $this->pdf->SetFont('helvetica', '', 8);
        $this->pdf->SetTextColor(0, 0, 0);
        
        foreach ($peliculas as $pelicula) {
            // cortar titulo si es muy largo
            $titulo = strlen($pelicula['titulo']) > 35 ? substr($pelicula['titulo'], 0, 32) . '...' : $pelicula['titulo'];
            
            $this->pdf->Cell(60, 6, $titulo, 1, 0, 'L');
            $this->pdf->Cell(40, 6, substr($pelicula['director'], 0, 25), 1, 0, 'L');
            $this->pdf->Cell(20, 6, $pelicula['ano_lanzamiento'], 1, 0, 'C');
            $this->pdf->Cell(30, 6, $pelicula['genero'], 1, 0, 'L');
            $this->pdf->Cell(30, 6, number_format($pelicula['puntuacion_promedio'], 1) . '/5 (' . $pelicula['total_resenas'] . ')', 1, 1, 'C');
        }
        
        $this->descargarPDF('Peliculas_' . date('Y-m-d'));
    }

    // reporte de resenas  
    private function reporteResenas() {
        $this->pdf->AddPage();
        
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->Cell(0, 15, 'Reporte de Resenas - CineFan', 0, 1, 'C');
        $this->pdf->Ln(5);
        
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 10, 'Generado: ' . date('d/m/Y H:i'), 0, 1, 'C');
        $this->pdf->Ln(10);
        
        // obtener resenas
        $resenas = $this->db->fetchAll("
            SELECT r.puntuacion, r.titulo, r.fecha_resena, r.likes,
                   u.nombre_usuario, p.titulo as titulo_pelicula
            FROM resenas r
            INNER JOIN usuarios u ON r.id_usuario = u.id
            INNER JOIN peliculas p ON r.id_pelicula = p.id
            WHERE r.activo = 1
            ORDER BY r.fecha_resena DESC
            LIMIT 50
        ");
        
        $stats = $this->db->fetchOne("
            SELECT 
                COUNT(*) as total_resenas,
                AVG(puntuacion) as puntuacion_promedio,
                SUM(likes) as total_likes
            FROM resenas
            WHERE activo = 1
        ");
        
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->Cell(0, 10, 'Resumen de Resenas:', 0, 1, 'L');
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 8, "Total resenas: {$stats['total_resenas']}", 0, 1, 'L');
        $this->pdf->Cell(0, 8, "Puntuacion promedio: " . number_format($stats['puntuacion_promedio'], 1) . "/5", 0, 1, 'L');
        $this->pdf->Cell(0, 8, "Total likes: {$stats['total_likes']}", 0, 1, 'L');
        $this->pdf->Ln(10);
        
        // tabla headers
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->SetFillColor(108, 75, 162);
        $this->pdf->SetTextColor(255, 255, 255);
        
        $this->pdf->Cell(30, 8, 'Usuario', 1, 0, 'C', true);
        $this->pdf->Cell(50, 8, 'Pelicula', 1, 0, 'C', true);
        $this->pdf->Cell(20, 8, 'Puntos', 1, 0, 'C', true);
        $this->pdf->Cell(50, 8, 'Titulo Resena', 1, 0, 'C', true);
        $this->pdf->Cell(20, 8, 'Likes', 1, 0, 'C', true);
        $this->pdf->Cell(20, 8, 'Fecha', 1, 1, 'C', true);
        
        $this->pdf->SetFont('helvetica', '', 8);
        $this->pdf->SetTextColor(0, 0, 0);
        
        foreach ($resenas as $resena) {
            $this->pdf->Cell(30, 6, substr($resena['nombre_usuario'], 0, 20), 1, 0, 'L');
            $this->pdf->Cell(50, 6, substr($resena['titulo_pelicula'], 0, 30), 1, 0, 'L');
            $this->pdf->Cell(20, 6, $resena['puntuacion'] . '/5', 1, 0, 'C');
            $this->pdf->Cell(50, 6, substr($resena['titulo'], 0, 30), 1, 0, 'L');
            $this->pdf->Cell(20, 6, $resena['likes'], 1, 0, 'C');
            $this->pdf->Cell(20, 6, date('d/m', strtotime($resena['fecha_resena'])), 1, 1, 'C');
        }
        
        $this->descargarPDF('Resenas_' . date('Y-m-d'));
    }

    // reporte de generos
    private function reporteGeneros() {
        $this->pdf->AddPage();
        
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->Cell(0, 15, 'Reporte de Generos - CineFan', 0, 1, 'C');
        $this->pdf->Ln(5);
        
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 10, 'Generado: ' . date('d/m/Y H:i'), 0, 1, 'C');
        $this->pdf->Ln(10);
        
        // obtener generos con estadisticas
        $generos = $this->db->fetchAll("
            SELECT g.nombre, g.descripcion, g.color_hex,
                   COUNT(p.id) as total_peliculas,
                   COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio
            FROM generos g
            LEFT JOIN peliculas p ON g.id = p.genero_id AND p.activo = 1
            LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
            WHERE g.activo = 1
            GROUP BY g.id
            ORDER BY total_peliculas DESC
        ");
        
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->Cell(0, 10, 'Estadisticas por Genero:', 0, 1, 'L');
        $this->pdf->Ln(5);
        
        // tabla headers
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->SetFillColor(108, 75, 162);
        $this->pdf->SetTextColor(255, 255, 255);
        
        $this->pdf->Cell(40, 8, 'Genero', 1, 0, 'C', true);
        $this->pdf->Cell(80, 8, 'Descripcion', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Peliculas', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Puntuacion', 1, 1, 'C', true);
        
        $this->pdf->SetFont('helvetica', '', 9);
        $this->pdf->SetTextColor(0, 0, 0);
        
        foreach ($generos as $genero) {
            $this->pdf->Cell(40, 6, $genero['nombre'], 1, 0, 'L');
            $this->pdf->Cell(80, 6, substr($genero['descripcion'], 0, 50), 1, 0, 'L');
            $this->pdf->Cell(30, 6, $genero['total_peliculas'], 1, 0, 'C');
            $this->pdf->Cell(30, 6, number_format($genero['puntuacion_promedio'], 1) . '/5', 1, 1, 'C');
        }
        
        $this->descargarPDF('Generos_' . date('Y-m-d'));
    }

    // reporte de estadisticas generales
    private function reporteEstadisticas() {
        $this->pdf->AddPage();
        
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->Cell(0, 15, 'Estadisticas Generales - CineFan', 0, 1, 'C');
        $this->pdf->Ln(5);
        
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 10, 'Generado: ' . date('d/m/Y H:i'), 0, 1, 'C');
        $this->pdf->Ln(10);
        
        // obtener estadisticas
        $stats = $this->db->fetchOne("
            SELECT 
                (SELECT COUNT(*) FROM usuarios WHERE activo = 1) as total_usuarios,
                (SELECT COUNT(*) FROM peliculas WHERE activo = 1) as total_peliculas,
                (SELECT COUNT(*) FROM resenas WHERE activo = 1) as total_resenas,
                (SELECT COUNT(*) FROM generos WHERE activo = 1) as total_generos,
                (SELECT COALESCE(AVG(puntuacion), 0) FROM resenas WHERE activo = 1) as puntuacion_promedio,
                (SELECT COALESCE(SUM(likes), 0) FROM resenas WHERE activo = 1) as total_likes
        ");
        
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->Cell(0, 10, 'Resumen de la Plataforma', 0, 1, 'L');
        
        $this->pdf->SetFont('helvetica', '', 12);
        $this->pdf->Cell(0, 8, "Usuarios registrados: {$stats['total_usuarios']}", 0, 1, 'L');
        $this->pdf->Cell(0, 8, "Peliculas en catalogo: {$stats['total_peliculas']}", 0, 1, 'L');
        $this->pdf->Cell(0, 8, "Resenas escritas: {$stats['total_resenas']}", 0, 1, 'L');
        $this->pdf->Cell(0, 8, "Generos disponibles: {$stats['total_generos']}", 0, 1, 'L');
        $this->pdf->Cell(0, 8, "Puntuacion media: " . number_format($stats['puntuacion_promedio'], 2) . "/5", 0, 1, 'L');
        $this->pdf->Cell(0, 8, "Total likes: {$stats['total_likes']}", 0, 1, 'L');
        $this->pdf->Ln(10);
        
        // top peliculas
        $topPeliculas = $this->db->fetchAll("
            SELECT p.titulo, p.director, COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
                   COUNT(r.id) as total_resenas
            FROM peliculas p
            LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
            WHERE p.activo = 1
            GROUP BY p.id
            HAVING total_resenas > 0
            ORDER BY puntuacion_promedio DESC, total_resenas DESC
            LIMIT 10
        ");
        
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->Cell(0, 10, 'Top 10 Mejores Peliculas', 0, 1, 'L');
        
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->SetFillColor(108, 75, 162);
        $this->pdf->SetTextColor(255, 255, 255);
        
        $this->pdf->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->pdf->Cell(70, 8, 'Pelicula', 1, 0, 'C', true);
        $this->pdf->Cell(50, 8, 'Director', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'Puntuacion', 1, 0, 'C', true);
        $this->pdf->Cell(20, 8, 'Resenas', 1, 1, 'C', true);
        
        $this->pdf->SetFont('helvetica', '', 9);
        $this->pdf->SetTextColor(0, 0, 0);
        
        $pos = 1;
        foreach ($topPeliculas as $pelicula) {
            $this->pdf->Cell(10, 6, $pos, 1, 0, 'C');
            $this->pdf->Cell(70, 6, substr($pelicula['titulo'], 0, 40), 1, 0, 'L');
            $this->pdf->Cell(50, 6, substr($pelicula['director'], 0, 30), 1, 0, 'L');
            $this->pdf->Cell(30, 6, number_format($pelicula['puntuacion_promedio'], 1) . '/5', 1, 0, 'C');
            $this->pdf->Cell(20, 6, $pelicula['total_resenas'], 1, 1, 'C');
            $pos++;
        }
        
        $this->descargarPDF('Estadisticas_' . date('Y-m-d'));
    }

    // descargar el pdf
    private function descargarPDF($filename) {
        $this->pdf->Output($filename . '.pdf', 'D');
    }
}

// manejar la peticion
try {
    $type = $_GET['type'] ?? 'estadisticas';
    $generator = new CineFanPDFGenerator();
    $generator->generateReport($type);
} catch (Exception $e) {
    http_response_code(500);
    echo "Error generando PDF: " . $e->getMessage();
}
?>