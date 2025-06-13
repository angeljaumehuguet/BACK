<?php
class PruebaRendimiento {
    private $bd;
    private $resultados = [];
    
    public function __construct() {
        $this->bd = new BaseDatosAdmin();
    }
    
    // ejecutar todas las pruebas de rendimiento
    public function ejecutarPruebasRendimiento() {
        $this->resultados = [];
        
        $this->probarRendimientoConsultasBaseDatos();
        $this->probarAgrupacionConexiones();
        $this->probarConsultaConjuntoDatosGrande();
        $this->probarConsultasConcurrentes();
        $this->probarUsoMemoria();
        $this->probarTiempoCargaPagina();
        
        return $this->resultados;
    }
    
    // probar rendimiento de consultas básicas
    private function probarRendimientoConsultasBaseDatos() {
        $consultas = [
            'usuarios' => 'SELECT * FROM usuarios LIMIT 50',
            'peliculas' => 'SELECT p.*, g.nombre as genero FROM peliculas p LEFT JOIN generos g ON p.genero_id = g.id LIMIT 50',
            'resenas' => 'SELECT r.*, u.nombre_usuario, p.titulo FROM resenas r JOIN usuarios u ON r.id_usuario = u.id JOIN peliculas p ON r.id_pelicula = p.id LIMIT 50'
        ];
        
        $todasRapidas = true;
        $tiempoTotal = 0;
        $consultasLentas = [];
        
        foreach ($consultas as $nombre => $sql) {
            $tiempoInicio = microtime(true);
            
            try {
                $resultado = $this->bd->obtenerTodos($sql);
                $tiempoFin = microtime(true);
                $tiempoConsulta = round(($tiempoFin - $tiempoInicio) * 1000, 2);
                
                $tiempoTotal += $tiempoConsulta;
                
                // considerar lenta si tarda más de 100ms
                if ($tiempoConsulta > 100) {
                    $todasRapidas = false;
                    $consultasLentas[] = "$nombre: {$tiempoConsulta}ms";
                }
                
            } catch (Exception $e) {
                $todasRapidas = false;
                $consultasLentas[] = "$nombre: ERROR";
            }
        }
        
        $tiempoPromedio = round($tiempoTotal / count($consultas), 2);
        
        if ($todasRapidas) {
            $this->agregarResultado('Rendimiento Consultas BD', true, 
                "Todas las consultas rápidas. Promedio: {$tiempoPromedio}ms", $tiempoPromedio);
        } else {
            $this->agregarResultado('Rendimiento Consultas BD', false, 
                "Consultas lentas detectadas: " . implode(', ', $consultasLentas), $tiempoPromedio);
        }
    }
    
    // probar rendimiento de conexiones
    private function probarAgrupacionConexiones() {
        $tiempoInicio = microtime(true);
        $conexiones = 0;
        
        try {
            // simular múltiples conexiones rápidas
            for ($i = 0; $i < 10; $i++) {
                $resultado = $this->bd->obtenerUno("SELECT 1");
                if ($resultado) {
                    $conexiones++;
                }
            }
            
            $tiempoFin = microtime(true);
            $tiempoTotal = round(($tiempoFin - $tiempoInicio) * 1000, 2);
            $tiempoPromedio = round($tiempoTotal / 10, 2);
            
            if ($conexiones === 10 && $tiempoPromedio < 50) {
                $this->agregarResultado('Agrupación Conexiones', true, 
                    "10 conexiones en {$tiempoTotal}ms. Prom: {$tiempoPromedio}ms", $tiempoPromedio);
            } else {
                $this->agregarResultado('Agrupación Conexiones', false, 
                    "Rendimiento conexión pobre. {$conexiones}/10 exitosas, prom: {$tiempoPromedio}ms", $tiempoPromedio);
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Agrupación Conexiones', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // probar consultas con conjuntos de datos grandes
    private function probarConsultaConjuntoDatosGrande() {
        $tiempoInicio = microtime(true);
        
        try {
            // consulta compleja que puede ser lenta
            $sql = "SELECT u.id, u.nombre_usuario, u.email,
                          COUNT(r.id) as total_resenas,
                          AVG(r.puntuacion) as promedio_puntuacion,
                          MAX(r.fecha_creacion) as ultima_resena
                   FROM usuarios u 
                   LEFT JOIN resenas r ON u.id = r.id_usuario
                   GROUP BY u.id
                   ORDER BY total_resenas DESC";
            
            $resultado = $this->bd->obtenerTodos($sql);
            
            $tiempoFin = microtime(true);
            $tiempoConsulta = round(($tiempoFin - $tiempoInicio) * 1000, 2);
            
            $conteoRegistros = count($resultado);
            
            // consideramos aceptable si procesa más de 100 registros en menos de 500ms
            if ($tiempoConsulta < 500) {
                $this->agregarResultado('Consulta Conjunto Datos Grande', true, 
                    "Procesados {$conteoRegistros} registros en {$tiempoConsulta}ms", $tiempoConsulta);
            } else {
                $this->agregarResultado('Consulta Conjunto Datos Grande', false, 
                    "Consulta lenta: {$conteoRegistros} registros en {$tiempoConsulta}ms", $tiempoConsulta);
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Consulta Conjunto Datos Grande', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // simular consultas concurrentes
    private function probarConsultasConcurrentes() {
        $tiempoInicio = microtime(true);
        $consultasExitosas = 0;
        
        try {
            // simular 5 consultas "concurrentes" 
            $consultas = [
                "SELECT COUNT(*) as total FROM usuarios",
                "SELECT COUNT(*) as total FROM peliculas", 
                "SELECT COUNT(*) as total FROM resenas",
                "SELECT COUNT(*) as total FROM generos",
                "SELECT COUNT(*) as total FROM usuarios WHERE activo = 1"
            ];
            
            foreach ($consultas as $sql) {
                $resultado = $this->bd->obtenerUno($sql);
                if ($resultado) {
                    $consultasExitosas++;
                }
            }
            
            $tiempoFin = microtime(true);
            $tiempoTotal = round(($tiempoFin - $tiempoInicio) * 1000, 2);
            
            if ($consultasExitosas === 5 && $tiempoTotal < 200) {
                $this->agregarResultado('Consultas Concurrentes', true, 
                    "5 consultas ejecutadas en {$tiempoTotal}ms", $tiempoTotal);
            } else {
                $this->agregarResultado('Consultas Concurrentes', false, 
                    "Rendimiento consulta concurrente pobre: {$consultasExitosas}/5 en {$tiempoTotal}ms", $tiempoTotal);
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Consultas Concurrentes', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // probar uso de memoria
    private function probarUsoMemoria() {
        $memoriaInicio = memory_get_usage(true);
        
        try {
            // cargar datos que consuman memoria
            $usuarios = $this->bd->obtenerTodos("SELECT * FROM usuarios");
            $peliculas = $this->bd->obtenerTodos("SELECT * FROM peliculas");
            $resenas = $this->bd->obtenerTodos("SELECT * FROM resenas LIMIT 500");
            
            $memoriaFin = memory_get_usage(true);
            $memoriaUsada = round(($memoriaFin - $memoriaInicio) / 1024 / 1024, 2); // MB
            
            $totalRegistros = count($usuarios) + count($peliculas) + count($resenas);
            
            // considerar eficiente si usa menos de 10MB para procesar los datos
            if ($memoriaUsada < 10) {
                $this->agregarResultado('Uso de Memoria', true, 
                    "Procesados {$totalRegistros} registros usando {$memoriaUsada}MB");
            } else {
                $this->agregarResultado('Uso de Memoria', false, 
                    "Alto uso memoria: {$memoriaUsada}MB para {$totalRegistros} registros");
            }
            
            // limpiar memoria
            unset($usuarios, $peliculas, $resenas);
            
        } catch (Exception $e) {
            $this->agregarResultado('Uso de Memoria', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // simular tiempo de carga de página
    private function probarTiempoCargaPagina() {
        $tiempoInicio = microtime(true);
        
        try {
            // simular carga de datos para una página típica
            $estadisticas = [
                'usuarios' => $this->bd->obtenerUno("SELECT COUNT(*) as total FROM usuarios"),
                'peliculas' => $this->bd->obtenerUno("SELECT COUNT(*) as total FROM peliculas"),
                'resenas' => $this->bd->obtenerUno("SELECT COUNT(*) as total FROM resenas")
            ];
            
            $usuarios = $this->bd->obtenerTodos("SELECT * FROM usuarios LIMIT 10");
            $peliculas = $this->bd->obtenerTodos("SELECT p.*, g.nombre as genero FROM peliculas p LEFT JOIN generos g ON p.genero_id = g.id LIMIT 10");
            
            $tiempoFin = microtime(true);
            $tiempoCarga = round(($tiempoFin - $tiempoInicio) * 1000, 2);
            
            // tiempo de carga aceptable < 300ms
            if ($tiempoCarga < 300) {
                $this->agregarResultado('Tiempo Carga Página', true, 
                    "Simulación carga página: {$tiempoCarga}ms", $tiempoCarga);
            } else {
                $this->agregarResultado('Tiempo Carga Página', false, 
                    "Carga página lenta: {$tiempoCarga}ms", $tiempoCarga);
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Tiempo Carga Página', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // agregar resultado
    private function agregarResultado($nombre, $aprobada, $descripcion, $tiempo = null) {
        $this->resultados[] = [
            'nombre' => $nombre,
            'aprobada' => $aprobada,
            'descripcion' => $descripcion,
            'tiempo' => $tiempo,
            'detalles' => ''
        ];
        
        $estado = $aprobada ? 'APROBADA' : 'FALLIDA';
        Registrador::info("Prueba Rendimiento $nombre: $estado - $descripcion");
    }
}
?>