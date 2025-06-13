<?php
// incluir inicializador que configura todo correctamente
require_once '../includes/inicializar.php';

// ahora si podemos incluir las clases de pruebas
require_once 'PruebaBaseDatos.php';
require_once 'PruebaSeguridad.php';
require_once 'PruebaRendimiento.php';
require_once 'PruebaValidacion.php';

class EjecutorPruebas {
    private $resultados = [];
    private $totalPruebas = 0;
    private $pruebasAprobadas = 0;
    
    public function __construct() {
        Registrador::info('Iniciando ejecucion de pruebas');
    }
    
    // ejecutar todas las pruebas
    public function ejecutarTodasLasPruebas() {
        echo $this->obtenerCabeceraHTML();
        
        echo "<div class='container mt-4'>";
        echo "<h1><i class='fas fa-vial'></i> Suite de Pruebas - CineFan</h1>";
        echo "<p class='text-muted'>Ejecutando pruebas automatizadas del sistema</p>";
        echo "<hr>";
        
        // ejecutar cada categoria de pruebas
        $this->ejecutarPruebasBaseDatos();
        $this->ejecutarPruebasSeguridad();
        $this->ejecutarPruebasRendimiento();
        $this->ejecutarPruebasValidacion();
        
        // mostrar resumen final
        $this->mostrarResumenFinal();
        
        echo "</div>";
        echo $this->obtenerPieHTML();
        
        Registrador::info("Pruebas completadas: {$this->pruebasAprobadas}/{$this->totalPruebas} aprobadas");
    }
    
    // ejecutar solo pruebas de bd
    public function ejecutarPruebasBaseDatos() {
        echo "<div class='card mb-4'>";
        echo "<div class='card-header bg-primary text-white'>";
        echo "<h3><i class='fas fa-database'></i> Pruebas de Base de Datos</h3>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        try {
            $pruebasBD = new PruebaBaseDatos();
            $resultados = $pruebasBD->ejecutarTodasLasPruebas();
            $this->procesarResultados($resultados, 'BaseDatos');
            
        } catch (Exception $e) {
            $this->mostrarError('Pruebas Base Datos', $e->getMessage());
        }
        
        echo "</div>";
        echo "</div>";
    }
    
    // ejecutar pruebas de seguridad
    public function ejecutarPruebasSeguridad() {
        echo "<div class='card mb-4'>";
        echo "<div class='card-header bg-warning text-dark'>";
        echo "<h3><i class='fas fa-shield-alt'></i> Pruebas de Seguridad</h3>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        try {
            $pruebasSeg = new PruebaSeguridad();
            $resultados = $pruebasSeg->ejecutarPruebasSeguridad();
            $this->procesarResultados($resultados, 'Seguridad');
            
        } catch (Exception $e) {
            $this->mostrarError('Pruebas Seguridad', $e->getMessage());
        }
        
        echo "</div>";
        echo "</div>";
    }
    
    // ejecutar pruebas de rendimiento
    public function ejecutarPruebasRendimiento() {
        echo "<div class='card mb-4'>";
        echo "<div class='card-header bg-info text-white'>";
        echo "<h3><i class='fas fa-tachometer-alt'></i> Pruebas de Rendimiento</h3>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        try {
            $pruebasRend = new PruebaRendimiento();
            $resultados = $pruebasRend->ejecutarPruebasRendimiento();
            $this->procesarResultados($resultados, 'Rendimiento');
            
        } catch (Exception $e) {
            $this->mostrarError('Pruebas Rendimiento', $e->getMessage());
        }
        
        echo "</div>";
        echo "</div>";
    }
    
    // ejecutar pruebas de validacion
    public function ejecutarPruebasValidacion() {
        echo "<div class='card mb-4'>";
        echo "<div class='card-header bg-success text-white'>";
        echo "<h3><i class='fas fa-check-double'></i> Pruebas de Validación</h3>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        try {
            $pruebasVal = new PruebaValidacion();
            $resultados = $pruebasVal->ejecutarPruebasValidacion();
            $this->procesarResultados($resultados, 'Validacion');
            
        } catch (Exception $e) {
            $this->mostrarError('Pruebas Validacion', $e->getMessage());
        }
        
        echo "</div>";
        echo "</div>";
    }
    
    public function ejecutarPruebasCompatibilidad() {
        echo "<div class='card mb-4'>";
        echo "<div class='card-header bg-secondary text-white'>";
        echo "<h3><i class='fas fa-globe'></i> Pruebas de Compatibilidad</h3>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        try {
            $pruebasComp = new PruebaCompatibilidadNavegadores();
            $resultados = $pruebasComp->ejecutarPruebasCompatibilidad();
            $this->procesarResultados($resultados, 'Compatibilidad');
            
            // Mostrar reporte adicional
            echo $pruebasComp->generarReporteCompatibilidad();
            
        } catch (Exception $e) {
            $this->mostrarError('Pruebas Compatibilidad', $e->getMessage());
        }
        
        echo "</div>";
        echo "</div>";
    }

    // procesar resultados de pruebas
    private function procesarResultados($resultados, $categoria) {
        echo "<div class='row'>";
        
        foreach ($resultados as $prueba) {
            $this->totalPruebas++;
            
            $claseAlerta = $prueba['aprobada'] ? 'alert-success' : 'alert-danger';
            $icono = $prueba['aprobada'] ? 'fa-check-circle' : 'fa-times-circle';
            
            if ($prueba['aprobada']) {
                $this->pruebasAprobadas++;
            }
            
            echo "<div class='col-md-6 mb-3'>";
            echo "<div class='alert $claseAlerta' role='alert'>";
            echo "<h6><i class='fas $icono'></i> {$prueba['nombre']}</h6>";
            echo "<p class='mb-1'>{$prueba['descripcion']}</p>";
            
            if (!empty($prueba['detalles'])) {
                echo "<small>{$prueba['detalles']}</small>";
            }
            
            if (isset($prueba['tiempo'])) {
                echo "<br><small class='text-muted'>Tiempo: {$prueba['tiempo']}ms</small>";
            }
            
            echo "</div>";
            echo "</div>";
        }
        
        echo "</div>";
    }
    
    // mostrar error en ejecucion
    private function mostrarError($categoria, $mensaje) {
        echo "<div class='alert alert-danger' role='alert'>";
        echo "<h6><i class='fas fa-exclamation-triangle'></i> Error en $categoria</h6>";
        echo "<p>$mensaje</p>";
        echo "</div>";
    }
    
    // mostrar resumen final
    private function mostrarResumenFinal() {
        $porcentaje = $this->totalPruebas > 0 ? round(($this->pruebasAprobadas / $this->totalPruebas) * 100, 1) : 0;
        $claseAlerta = $porcentaje >= 80 ? 'alert-success' : ($porcentaje >= 60 ? 'alert-warning' : 'alert-danger');
        
        echo "<div class='card border-0'>";
        echo "<div class='card-body'>";
        echo "<div class='$claseAlerta' role='alert'>";
        echo "<h4><i class='fas fa-chart-pie'></i> Resumen Final de Pruebas</h4>";
        echo "<div class='row'>";
        echo "<div class='col-md-3'>";
        echo "<h5>{$this->pruebasAprobadas}/{$this->totalPruebas}</h5>";
        echo "<small>Pruebas Aprobadas</small>";
        echo "</div>";
        echo "<div class='col-md-3'>";
        echo "<h5>{$porcentaje}%</h5>";
        echo "<small>Porcentaje Éxito</small>";
        echo "</div>";
        echo "<div class='col-md-3'>";
        echo "<h5>" . ($this->totalPruebas - $this->pruebasAprobadas) . "</h5>";
        echo "<small>Pruebas Fallidas</small>";
        echo "</div>";
        echo "<div class='col-md-3'>";
        echo "<h5>" . date('H:i:s') . "</h5>";
        echo "<small>Hora Finalización</small>";
        echo "</div>";
        echo "</div>";
        
        if ($porcentaje >= 80) {
            echo "<p class='mt-3 mb-0'><strong>✅ Sistema funcionando correctamente</strong></p>";
        } elseif ($porcentaje >= 60) {
            echo "<p class='mt-3 mb-0'><strong>⚠️ Sistema funcional con algunas advertencias</strong></p>";
        } else {
            echo "<p class='mt-3 mb-0'><strong>❌ Sistema requiere atención inmediata</strong></p>";
        }
        
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }
    
    // obtener cabecera html
    private function obtenerCabeceraHTML() {
        return '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pruebas CineFan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 1200px; }
        .card { box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none; }
        .alert { border-left: 4px solid; }
        .alert-success { border-left-color: #28a745; }
        .alert-danger { border-left-color: #dc3545; }
        .alert-warning { border-left-color: #ffc107; }
        .alert-info { border-left-color: #17a2b8; }
    </style>
</head>
<body>
        ';
    }
    
    // obtener pie html
    private function obtenerPieHTML() {
        return '
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
        ';
    }
}

// manejar ejecucion segun parametros
if (isset($_GET['prueba'])) {
    $ejecutor = new EjecutorPruebas();
    
    switch ($_GET['prueba']) {
        case 'basedatos':
            $ejecutor->ejecutarPruebasBaseDatos();
            break;
        case 'seguridad':
            $ejecutor->ejecutarPruebasSeguridad();
            break;
        case 'todas':
        default:
            $ejecutor->ejecutarTodasLasPruebas();
            break;
    }
} else {
    // mostrar menu de seleccion
    echo '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pruebas CineFan - Menú</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3><i class="fas fa-vial"></i> Suite de Pruebas CineFan</h3>
                    </div>
                    <div class="card-body">
                        <p>Selecciona qué tipo de pruebas ejecutar:</p>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <a href="?prueba=basedatos" class="btn btn-outline-primary btn-lg w-100">
                                    <i class="fas fa-database mb-2"></i><br>
                                    Pruebas de Base de Datos
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="?prueba=seguridad" class="btn btn-outline-warning btn-lg w-100">
                                    <i class="fas fa-shield-alt mb-2"></i><br>
                                    Pruebas de Seguridad
                                </a>
                            </div>
                            <div class="col-md-12 mb-3">
                                <a href="?prueba=todas" class="btn btn-success btn-lg w-100">
                                    <i class="fas fa-play mb-2"></i><br>
                                    Ejecutar Todas las Pruebas
                                </a>
                            </div>
                        </div>
                        
                        <hr>
                        <p class="text-muted text-center">
                            <small>Sistema desarrollado por Juan Carlos y Angel Hernandez</small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
    ';
}
?>