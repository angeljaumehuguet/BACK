<?php
require_once 'inicializar.php';

class GeneradorPDF {
    private $bd;
    
    public function __construct() {
        $this->bd = new BaseDatosAdmin();
    }
    
    // generar pdf segun tipo
    public function generar($tipo) {
        switch ($tipo) {
            case 'usuarios':
                return $this->generarUsuarios();
            case 'peliculas':
                return $this->generarPeliculas();
            case 'resenas':
                return $this->generarResenas();
            default:
                throw new Exception("Tipo de reporte no valido: $tipo");
        }
    }
    
    // generar reporte de usuarios
    private function generarUsuarios() {
        Registrador::info('Generando reporte PDF usuarios');
        
        // obtener datos de usuarios
        $usuarios = $this->bd->obtenerTodos("
            SELECT u.*, COUNT(r.id) as total_resenas 
            FROM usuarios u 
            LEFT JOIN resenas r ON u.id = r.id_usuario 
            GROUP BY u.id 
            ORDER BY u.fecha_registro DESC
        ");
        
        // crear contenido html del pdf
        $html = $this->crearHTMLBase();
        $html .= '<h1>Reporte de Usuarios - CineFan</h1>';
        $html .= '<p>Generado el: ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '<p>Total de usuarios: ' . count($usuarios) . '</p><br>';
        
        if (empty($usuarios)) {
            $html .= '<p>No hay usuarios registrados.</p>';
        } else {
            $html .= '<table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
            $html .= '<thead style="background-color: #f8f9fa;">';
            $html .= '<tr>';
            $html .= '<th>ID</th>';
            $html .= '<th>Usuario</th>';
            $html .= '<th>Email</th>';
            $html .= '<th>Nombre Completo</th>';
            $html .= '<th>Reseñas</th>';
            $html .= '<th>Registro</th>';
            $html .= '<th>Estado</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            foreach ($usuarios as $usuario) {
                $estado = $usuario['activo'] ? 'Activo' : 'Inactivo';
                $fecha = date('d/m/Y', strtotime($usuario['fecha_registro']));
                
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($usuario['id']) . '</td>';
                $html .= '<td><strong>' . htmlspecialchars($usuario['nombre_usuario']) . '</strong></td>';
                $html .= '<td>' . htmlspecialchars($usuario['email']) . '</td>';
                $html .= '<td>' . htmlspecialchars($usuario['nombre_completo'] ?: 'Sin nombre') . '</td>';
                $html .= '<td>' . $usuario['total_resenas'] . '</td>';
                $html .= '<td>' . $fecha . '</td>';
                $html .= '<td>' . $estado . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
        }
        
        $html .= $this->crearPieHTML();
        
        return $this->convertirHTMLaPDF($html);
    }
    
    // generar reporte de peliculas
    private function generarPeliculas() {
        Registrador::info('Generando reporte PDF peliculas');
        
        // obtener datos de peliculas
        $peliculas = $this->bd->obtenerTodos("
            SELECT p.*, g.nombre as genero,
                   COUNT(r.id) as total_resenas,
                   ROUND(AVG(r.puntuacion), 1) as puntuacion_promedio
            FROM peliculas p 
            LEFT JOIN generos g ON p.genero_id = g.id
            LEFT JOIN resenas r ON p.id = r.id_pelicula
            GROUP BY p.id 
            ORDER BY p.fecha_creacion DESC
        ");
        
        $html = $this->crearHTMLBase();
        $html .= '<h1>Reporte de Películas - CineFan</h1>';
        $html .= '<p>Generado el: ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '<p>Total de películas: ' . count($peliculas) . '</p><br>';
        
        if (empty($peliculas)) {
            $html .= '<p>No hay películas registradas.</p>';
        } else {
            $html .= '<table border="1" cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 12px;">';
            $html .= '<thead style="background-color: #f8f9fa;">';
            $html .= '<tr>';
            $html .= '<th>ID</th>';
            $html .= '<th>Título</th>';
            $html .= '<th>Director</th>';
            $html .= '<th>Año</th>';
            $html .= '<th>Género</th>';
            $html .= '<th>Duración</th>';
            $html .= '<th>Puntuación</th>';
            $html .= '<th>Reseñas</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            foreach ($peliculas as $pelicula) {
                $puntuacion = $pelicula['puntuacion_promedio'] ?: 'Sin puntuación';
                
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($pelicula['id']) . '</td>';
                $html .= '<td><strong>' . htmlspecialchars($pelicula['titulo']) . '</strong></td>';
                $html .= '<td>' . htmlspecialchars($pelicula['director']) . '</td>';
                $html .= '<td>' . $pelicula['ano_lanzamiento'] . '</td>';
                $html .= '<td>' . htmlspecialchars($pelicula['genero'] ?: 'Sin género') . '</td>';
                $html .= '<td>' . $pelicula['duracion_minutos'] . ' min</td>';
                $html .= '<td>' . $puntuacion . '</td>';
                $html .= '<td>' . $pelicula['total_resenas'] . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
        }
        
        $html .= $this->crearPieHTML();
        
        return $this->convertirHTMLaPDF($html);
    }
    
    // generar reporte de resenas
    private function generarResenas() {
        Registrador::info('Generando reporte PDF resenas');
        
        // obtener datos de resenas
        $resenas = $this->bd->obtenerTodos("
            SELECT r.*, p.titulo as pelicula, u.nombre_usuario as usuario,
                   COUNT(l.id) as total_likes
            FROM resenas r 
            JOIN peliculas p ON r.id_pelicula = p.id
            JOIN usuarios u ON r.id_usuario = u.id
            LEFT JOIN likes_resenas l ON r.id = l.id_resena
            GROUP BY r.id 
            ORDER BY r.fecha_creacion DESC 
            LIMIT 100
        ");
        
        $html = $this->crearHTMLBase();
        $html .= '<h1>Reporte de Reseñas - CineFan</h1>';
        $html .= '<p>Generado el: ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '<p>Total de reseñas (últimas 100): ' . count($resenas) . '</p><br>';
        
        if (empty($resenas)) {
            $html .= '<p>No hay reseñas registradas.</p>';
        } else {
            $html .= '<table border="1" cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 11px;">';
            $html .= '<thead style="background-color: #f8f9fa;">';
            $html .= '<tr>';
            $html .= '<th>ID</th>';
            $html .= '<th>Usuario</th>';
            $html .= '<th>Película</th>';
            $html .= '<th>Puntuación</th>';
            $html .= '<th>Likes</th>';
            $html .= '<th>Fecha</th>';
            $html .= '<th>Comentario</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            foreach ($resenas as $resena) {
                $estrellas = str_repeat('★', $resena['puntuacion']) . str_repeat('☆', 5 - $resena['puntuacion']);
                $fecha = date('d/m/Y', strtotime($resena['fecha_creacion']));
                $comentario = $resena['comentario'] ? 
                    (strlen($resena['comentario']) > 60 ? substr($resena['comentario'], 0, 60) . '...' : $resena['comentario']) :
                    'Sin comentario';
                
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($resena['id']) . '</td>';
                $html .= '<td><strong>' . htmlspecialchars($resena['usuario']) . '</strong></td>';
                $html .= '<td>' . htmlspecialchars($resena['pelicula']) . '</td>';
                $html .= '<td>' . $estrellas . '</td>';
                $html .= '<td>' . $resena['total_likes'] . '</td>';
                $html .= '<td>' . $fecha . '</td>';
                $html .= '<td><small>' . htmlspecialchars($comentario) . '</small></td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
        }
        
        $html .= $this->crearPieHTML();
        
        return $this->convertirHTMLaPDF($html);
    }
    
    // crear estructura html base
    private function crearHTMLBase() {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte CineFan</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px;
            font-size: 14px;
            line-height: 1.4;
        }
        h1 { 
            color: #333; 
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px;
        }
        th { 
            background-color: #667eea; 
            color: white; 
            padding: 10px;
            text-align: left;
        }
        td { 
            padding: 8px; 
            border-bottom: 1px solid #ddd;
        }
        tr:nth-child(even) { 
            background-color: #f9f9f9; 
        }
        .pie { 
            margin-top: 30px; 
            text-align: center; 
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
        ';
    }
    
    // crear pie del html
    private function crearPieHTML() {
        return '
    <div class="pie">
        <hr>
        <p>Reporte generado por CineFan Panel Admin</p>
        <p>Desarrollado por Juan Carlos y Angel Hernandez - DAM2</p>
        <p>Fecha de generacion: ' . date('d/m/Y H:i:s') . '</p>
    </div>
</body>
</html>
        ';
    }
    
    // convertir html a pdf usando tcpdf o respaldo a html simple
    private function convertirHTMLaPDF($html) {
        // intentar usar tcpdf si esta disponible
        if (class_exists('TCPDF')) {
            return $this->usarTCPDF($html);
        }
        
        // respaldo: devolver html como contenido (el navegador puede imprimirlo como pdf)
        Registrador::advertencia('TCPDF no disponible, usando respaldo HTML');
        return $this->usarRespaldoHTML($html);
    }
    
    // usar tcpdf para generar pdf real
    private function usarTCPDF($html) {
        try {
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // configurar pdf
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('Juan Carlos y Angel Hernandez');
            $pdf->SetTitle('Reporte CineFan');
            $pdf->SetSubject('Reporte administrativo');
            
            // configurar cabecera y pie
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(true);
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(TRUE, 25);
            
            // agregar pagina
            $pdf->AddPage();
            
            // escribir html
            $pdf->writeHTML($html, true, false, true, false, '');
            
            return $pdf->Output('', 'S'); // devolver como cadena
            
        } catch (Exception $e) {
            Registrador::error('Error usando TCPDF: ' . $e->getMessage());
            return $this->usarRespaldoHTML($html);
        }
    }
    
    // respaldo cuando tcpdf no funciona
    private function usarRespaldoHTML($html) {
        // crear un html optimizado para impresion
        $htmlParaImpresion = str_replace('</head>', '
        <style>
            @media print {
                body { margin: 0; }
                .no-imprimir { display: none; }
            }
            @page { margin: 2cm; }
            body { 
                font-family: Arial, sans-serif; 
                font-size: 12px;
                line-height: 1.3;
            }
        </style>
        </head>', $html);
        
        return $htmlParaImpresion;
    }
}
?>