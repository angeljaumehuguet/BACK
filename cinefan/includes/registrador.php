<?php
class Registrador {
    private static $archivoLog;
    
    // inicializar el sistema de logs
    public static function inicializar() {
        if (!is_dir(RUTA_LOGS)) {
            mkdir(RUTA_LOGS, 0755, true);
        }
        self::$archivoLog = RUTA_LOGS . 'admin_' . date('Y-m-d') . '.log';
    }
    
    // escribir entrada en el log
    public static function escribirLog($mensaje, $nivel = 'INFO', $contexto = []) {
        if (!ACTIVAR_LOGS) return;
        
        if (!self::$archivoLog) {
            self::inicializar();
        }
        
        $marcaTiempo = date('Y-m-d H:i:s');
        $textoContexto = !empty($contexto) ? ' | Contexto: ' . json_encode($contexto) : '';
        $entradaLog = "[{$marcaTiempo}] [{$nivel}] {$mensaje}{$textoContexto}" . PHP_EOL;
        
        file_put_contents(self::$archivoLog, $entradaLog, FILE_APPEND | LOCK_EX);
    }
    
    // log de depuracion para desarrollo
    public static function depurar($mensaje, $contexto = []) {
        self::escribirLog($mensaje, 'DEBUG', $contexto);
    }
    
    // log de informacion general
    public static function info($mensaje, $contexto = []) {
        self::escribirLog($mensaje, 'INFO', $contexto);
    }
    
    // log de advertencias
    public static function advertencia($mensaje, $contexto = []) {
        self::escribirLog($mensaje, 'WARNING', $contexto);
    }
    
    // log de errores
    public static function error($mensaje, $contexto = []) {
        self::escribirLog($mensaje, 'ERROR', $contexto);
    }
    
    // log de acciones criticas
    public static function critico($mensaje, $contexto = []) {
        self::escribirLog($mensaje, 'CRITICAL', $contexto);
    }
    
    // log de actividad de usuarios
    public static function actividad($accion, $usuario, $detalles = []) {
        $mensaje = "Actividad usuario: $accion por usuario $usuario";
        self::escribirLog($mensaje, 'ACTIVITY', $detalles);
    }
    
    // log de seguridad
    public static function seguridad($evento, $detalles = []) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
        $mensaje = "Evento seguridad: $evento desde IP $ip";
        self::escribirLog($mensaje, 'SECURITY', $detalles);
    }
    
    // leer las ultimas entradas del log
    public static function leerUltimasEntradas($cantidad = 50) {
        if (!file_exists(self::$archivoLog)) {
            return [];
        }
        
        $lineas = file(self::$archivoLog, FILE_IGNORE_NEW_LINES);
        return array_slice($lineas, -$cantidad);
    }
    
    // limpiar logs antiguos
    public static function limpiarLogsAntiguos($diasRetener = 30) {
        $patron = RUTA_LOGS . 'admin_*.log';
        $archivos = glob($patron);
        
        $fechaLimite = time() - ($diasRetener * 24 * 60 * 60);
        
        foreach ($archivos as $archivo) {
            if (filemtime($archivo) < $fechaLimite) {
                unlink($archivo);
                self::info("Log antiguo eliminado: " . basename($archivo));
            }
        }
    }
    
    // obtener estadisticas de logs
    public static function obtenerEstadisticas() {
        if (!file_exists(self::$archivoLog)) {
            return [
                'total_lineas' => 0,
                'errores' => 0,
                'advertencias' => 0,
                'info' => 0
            ];
        }
        
        $contenido = file_get_contents(self::$archivoLog);
        
        return [
            'total_lineas' => substr_count($contenido, "\n"),
            'errores' => substr_count($contenido, '[ERROR]'),
            'advertencias' => substr_count($contenido, '[WARNING]'),
            'info' => substr_count($contenido, '[INFO]'),
            'debug' => substr_count($contenido, '[DEBUG]'),
            'seguridad' => substr_count($contenido, '[SECURITY]')
        ];
    }
    
    // rotar logs si son muy grandes
    public static function rotarLogs($tamañoMaximo = 10485760) { // 10MB por defecto
        if (!file_exists(self::$archivoLog)) {
            return;
        }
        
        if (filesize(self::$archivoLog) > $tamañoMaximo) {
            $archivoRotado = self::$archivoLog . '.old';
            rename(self::$archivoLog, $archivoRotado);
            self::info("Log rotado - archivo grande detectado");
        }
    }
    
    // exportar logs a formato json
    public static function exportarJSON($archivo = null) {
        if (!$archivo) {
            $archivo = RUTA_LOGS . 'export_' . date('Y-m-d_H-i-s') . '.json';
        }
        
        $lineas = self::leerUltimasEntradas(1000);
        $registros = [];
        
        foreach ($lineas as $linea) {
            // parsear linea de log
            if (preg_match('/\[(.*?)\] \[(.*?)\] (.*)/', $linea, $coincidencias)) {
                $registros[] = [
                    'fecha' => $coincidencias[1],
                    'nivel' => $coincidencias[2],
                    'mensaje' => $coincidencias[3]
                ];
            }
        }
        
        file_put_contents($archivo, json_encode($registros, JSON_PRETTY_PRINT));
        return $archivo;
    }
    
    // obtener ruta del archivo de log actual
    public static function obtenerRutaLog() {
        if (!self::$archivoLog) {
            self::inicializar();
        }
        return self::$archivoLog;
    }
    
    // verificar si el sistema de logs esta funcionando
    public static function verificarSistema() {
        try {
            $mensajePrueba = "Prueba sistema logs - " . date('H:i:s');
            self::info($mensajePrueba);
            
            // verificar que se escribio
            $ultimasLineas = self::leerUltimasEntradas(1);
            return !empty($ultimasLineas) && strpos($ultimasLineas[0], $mensajePrueba) !== false;
            
        } catch (Exception $e) {
            return false;
        }
    }
}
?>