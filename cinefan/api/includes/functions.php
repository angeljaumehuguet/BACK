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
                return 'Hace un momento';
            }
        } catch (Exception $e) {
            // Si hay error, devolver fecha formateada
            return date('d/m/Y H:i', strtotime($fechaCreacion));
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
    
    /**
     * Validar longitud de cadena
     */
    public static function validateStringLength($texto, $minLength = 1, $maxLength = 255) {
        $length = strlen(trim($texto));
        return $length >= $minLength && $length <= $maxLength;
    }
    
    /**
     * Formatear número con separadores de miles
     */
    public static function formatNumber($numero, $decimales = 0) {
        return number_format($numero, $decimales, ',', '.');
    }
    
    /**
     * Convertir bytes a formato legible
     */
    public static function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Validar que un valor esté en un array de opciones válidas
     */
    public static function validateInArray($valor, $opciones) {
        return in_array($valor, $opciones, true);
    }
    
    /**
     * Generar hash MD5 de un archivo
     */
    public static function getFileHash($filePath) {
        if (file_exists($filePath)) {
            return md5_file($filePath);
        }
        return false;
    }
    
    /**
     * Limpiar y validar número entero
     */
    public static function cleanInt($valor, $default = 0) {
        $cleaned = filter_var($valor, FILTER_VALIDATE_INT);
        return $cleaned !== false ? $cleaned : $default;
    }
    
    /**
     * Limpiar y validar número flotante
     */
    public static function cleanFloat($valor, $default = 0.0) {
        $cleaned = filter_var($valor, FILTER_VALIDATE_FLOAT);
        return $cleaned !== false ? $cleaned : $default;
    }
    
    /**
     * Verificar si una fecha es válida
     */
    public static function isValidDate($fecha, $formato = 'Y-m-d') {
        $dateTime = DateTime::createFromFormat($formato, $fecha);
        return $dateTime && $dateTime->format($formato) === $fecha;
    }
    
    /**
     * Generar código QR simple (requiere librería externa)
     */
    public static function generateQRCode($texto, $size = 150) {
        // Implementación básica usando API externa
        $url = "https://api.qrserver.com/v1/create-qr-code/";
        $params = http_build_query([
            'size' => $size . 'x' . $size,
            'data' => $texto
        ]);
        
        return $url . '?' . $params;
    }
    
    /**
     * Calcular edad a partir de fecha de nacimiento
     */
    public static function calculateAge($fechaNacimiento) {
        try {
            $nacimiento = new DateTime($fechaNacimiento);
            $hoy = new DateTime();
            $edad = $hoy->diff($nacimiento);
            return $edad->y;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Validar código postal español
     */
    public static function validateSpanishPostalCode($codigoPostal) {
        return preg_match('/^[0-5][0-9]{4}$/', $codigoPostal);
    }
    
    /**
     * Generar contraseña aleatoria
     */
    public static function generateRandomPassword($length = 12) {
        $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $caracteres[rand(0, strlen($caracteres) - 1)];
        }
        
        return $password;
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

function logDebug($mensaje) {
    Utils::log($mensaje, 'DEBUG');
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

/**
 * Función helper para sanitizar entrada
 */
function sanitizeInput($input) {
    return Utils::sanitizeHtml(trim($input));
}

/**
 * Función helper para validar email
 */
function isValidEmail($email) {
    return Utils::validateEmail($email);
}

/**
 * Función helper para formatear tiempo transcurrido
 */
function timeAgo($fecha) {
    return Utils::timeAgo($fecha);
}

/**
 * Función helper para validar puntuación
 */
function isValidRating($puntuacion) {
    return Utils::validateRating($puntuacion);
}

?>