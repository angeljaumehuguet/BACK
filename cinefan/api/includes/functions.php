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
        $logMessage = "[$timestamp] CineFan [$nivel]: $mensaje" . PHP_EOL;
        
        // Escribir a archivo de log
        $logFile = __DIR__ . '/../logs/cinefan.log';
        
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
     * Calcula el tiempo transcurrido desde una fecha
     * @param string $fechaCreacion Fecha en formato Y-m-d H:i:s
     * @return string Tiempo transcurrido formateado
     */
    public static function timeAgo($fechaCreacion) {
        if (empty($fechaCreacion)) {
            return 'Fecha desconocida';
        }
        
        try {
            $fecha = new DateTime($fechaCreacion);
            $ahora = new DateTime();
            $diferencia = $ahora->diff($fecha);
            
            // Calcular tiempo transcurrido
            if ($diferencia->y > 0) {
                return $diferencia->y == 1 ? 'Hace 1 año' : 'Hace ' . $diferencia->y . ' años';
            } elseif ($diferencia->m > 0) {
                return $diferencia->m == 1 ? 'Hace 1 mes' : 'Hace ' . $diferencia->m . ' meses';
            } elseif ($diferencia->d > 0) {
                if ($diferencia->d >= 7) {
                    $semanas = floor($diferencia->d / 7);
                    return $semanas == 1 ? 'Hace 1 semana' : 'Hace ' . $semanas . ' semanas';
                }
                return $diferencia->d == 1 ? 'Hace 1 día' : 'Hace ' . $diferencia->d . ' días';
            } elseif ($diferencia->h > 0) {
                return $diferencia->h == 1 ? 'Hace 1 hora' : 'Hace ' . $diferencia->h . ' horas';
            } elseif ($diferencia->i > 0) {
                return $diferencia->i == 1 ? 'Hace 1 minuto' : 'Hace ' . $diferencia->i . ' minutos';
            } else {
                return 'Hace unos segundos';
            }
            
        } catch (Exception $e) {
            self::log("Error en timeAgo: " . $e->getMessage(), 'ERROR');
            return 'Fecha inválida';
        }
    }
    
    /**
     * Validar puntuación de reseña (1-5 estrellas)
     */
    public static function validateRating($rating) {
        return is_numeric($rating) && $rating >= 1 && $rating <= 5;
    }
    
    /**
     * Sanitizar texto para prevenir XSS
     */
    public static function sanitizeText($text) {
        if (empty($text)) {
            return '';
        }
        return htmlspecialchars(strip_tags(trim($text)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validar email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validar URL
     */
    public static function validateUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Generar token aleatorio seguro
     */
    public static function generateSecureToken($length = 32) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        } else {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        }
    }
    
    /**
     * Formatear duración en minutos a horas y minutos
     */
    public static function formatDuration($minutes) {
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($remainingMinutes == 0) {
            return $hours . 'h';
        }
        
        return $hours . 'h ' . $remainingMinutes . 'min';
    }
    
    /**
     * Truncar texto con elipsis
     */
    public static function truncateText($text, $maxLength = 150) {
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        
        return substr($text, 0, $maxLength - 3) . '...';
    }
    
    /**
     * Obtener IP del cliente
     */
    public static function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Convertir array a formato CSV
     */
    public static function arrayToCSV($array) {
        if (empty($array)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Escribir headers
        fputcsv($output, array_keys($array[0]));
        
        // Escribir datos
        foreach ($array as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Validar que una cadena no contenga HTML peligroso
     */
    public static function isSafeHTML($html) {
        // Lista de etiquetas peligrosas
        $dangerousTags = [
            'script', 'iframe', 'object', 'embed', 'form', 
            'input', 'button', 'select', 'textarea', 'link', 'meta'
        ];
        
        $html = strtolower($html);
        
        foreach ($dangerousTags as $tag) {
            if (strpos($html, '<' . $tag) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Calcular tamaño de archivo legible
     */
    public static function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Verificar si una tabla existe en la base de datos
     */
    public static function tableExists($tableName, $connection) {
        try {
            $stmt = $connection->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            self::log("Error verificando tabla {$tableName}: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Verificar si una columna existe en una tabla
     */
    public static function columnExists($tableName, $columnName, $connection) {
        try {
            $stmt = $connection->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE ?");
            $stmt->execute([$columnName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            self::log("Error verificando columna {$columnName} en tabla {$tableName}: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
}

// Funciones helper globales para compatibilidad
if (!function_exists('timeAgo')) {
    function timeAgo($date) {
        return Utils::timeAgo($date);
    }
}

if (!function_exists('logMessage')) {
    function logMessage($message, $level = 'INFO') {
        Utils::log($message, $level);
    }
}
?>