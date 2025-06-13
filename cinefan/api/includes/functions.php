<?php
/**
 * Utilidades generales para CineFan API
 * Funciones helper para logging, validaciones, etc.
 */

class Utils {
    
    /**
     * Registrar eventos en log
     */
    public static function log($mensaje, $nivel = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$nivel] $mensaje" . PHP_EOL;
        
        // Escribir a archivo de log
        $logFile = '../logs/cinefan.log';
        
        // Crear directorio de logs si no existe
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        // Escribir al log
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // También escribir al log de errores de PHP en desarrollo
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("CineFan [$nivel]: $mensaje");
        }
    }
    
    /**
     * Validar puntuación (1-5)
     */
    public static function validateRating($puntuacion) {
        return is_numeric($puntuacion) && $puntuacion >= 1 && $puntuacion <= 5;
    }
    
    /**
     * Validar email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Generar ID único
     */
    public static function generateUniqueId() {
        return uniqid(time(), true);
    }
    
    /**
     * Limpiar texto para evitar XSS
     */
    public static function sanitizeHtml($texto) {
        return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Formatear fecha para respuesta API
     */
    public static function formatApiDate($fecha) {
        if (is_string($fecha)) {
            $fecha = new DateTime($fecha);
        }
        return $fecha->format('Y-m-d H:i:s');
    }
    
    /**
     * Validar URL de imagen
     */
    public static function isValidImageUrl($url) {
        if (empty($url)) {
            return true; // URL vacía es válida (opcional)
        }
        
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Generar slug amigable
     */
    public static function generateSlug($texto) {
        $texto = strtolower($texto);
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
        $texto = trim($texto, '-');
        return $texto;
    }
    
    /**
     * Formatear duración en minutos a formato legible
     */
    public static function formatDuration($minutos) {
        if ($minutos < 60) {
            return $minutos . 'min';
        }
        
        $horas = floor($minutos / 60);
        $mins = $minutos % 60;
        
        if ($mins == 0) {
            return $horas . 'h';
        }
        
        return $horas . 'h ' . $mins . 'min';
    }
    
    /**
     * Obtener IP del cliente
     */
    public static function getClientIp() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Generar token seguro
     */
    public static function generateSecureToken($length = 32) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        }
        
        // Fallback para versiones antiguas de PHP
        return bin2hex(openssl_random_pseudo_bytes($length / 2));
    }
    
    /**
     * Verificar si string está vacío o es solo espacios
     */
    public static function isEmptyString($str) {
        return empty(trim($str));
    }
    
    /**
     * Truncar texto con elipsis
     */
    public static function truncateText($texto, $maxLength = 100, $suffix = '...') {
        if (strlen($texto) <= $maxLength) {
            return $texto;
        }
        
        return substr($texto, 0, $maxLength - strlen($suffix)) . $suffix;
    }
    
    /**
     * Convertir array a objeto para JSON
     */
    public static function arrayToObject($array) {
        return json_decode(json_encode($array));
    }
    
    /**
     * Verificar si es una petición AJAX
     */
    public static function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Obtener user agent del cliente
     */
    public static function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
    
    /**
     * Debug: imprimir variable con formato
     */
    public static function debug($variable, $die = false) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            echo '<pre>';
            print_r($variable);
            echo '</pre>';
            
            if ($die) {
                die();
            }
        }
    }
}

// Funciones helper globales

/**
 * Función helper para logging rápido
 */
function logInfo($mensaje) {
    Utils::log($mensaje, 'INFO');
}

function logError($mensaje) {
    Utils::log($mensaje, 'ERROR');
}

function logWarning($mensaje) {
    Utils::log($mensaje, 'WARNING');
}

/**
 * Función helper para respuestas JSON rápidas
 */
function jsonResponse($data, $success = true, $message = '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'exito' => $success,
        'mensaje' => $message,
        'datos' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Función helper para errores JSON rápidos
 */
function jsonError($message, $code = 400) {
    http_response_code($code);
    jsonResponse(null, false, $message);
}
?>