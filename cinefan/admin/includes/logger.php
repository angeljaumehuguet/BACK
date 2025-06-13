<?php
class Logger {
    private static $logFile;
    
    // inicializar el sistema de logs
    public static function init() {
        if (!is_dir(LOGS_PATH)) {
            mkdir(LOGS_PATH, 0755, true);
        }
        self::$logFile = LOGS_PATH . 'admin_' . date('Y-m-d') . '.log';
    }
    
    // escribir entrada en el log
    public static function log($message, $level = 'INFO', $context = []) {
        if (!ENABLE_LOGGING) return;
        
        if (!self::$logFile) {
            self::init();
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    // log de debug para desarrollo
    public static function debug($message, $context = []) {
        self::log($message, 'DEBUG', $context);
    }
    
    // log de informacion general
    public static function info($message, $context = []) {
        self::log($message, 'INFO', $context);
    }
    
    // log de advertencias
    public static function warning($message, $context = []) {
        self::log($message, 'WARNING', $context);
    }
    
    // log de errores
    public static function error($message, $context = []) {
        self::log($message, 'ERROR', $context);
    }
}