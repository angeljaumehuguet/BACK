<?php
class Utils {
    
    /**
     * Convierte una fecha en formato "hace X tiempo"
     * @param string $datetime - Fecha en formato MySQL
     * @return string - Texto formateado
     */
    public static function timeAgo($datetime) {
        if (empty($datetime)) {
            return 'Fecha no disponible';
        }
        
        try {
            $time = time() - strtotime($datetime);
            
            if ($time < 1) {
                return 'ahora mismo';
            }
            
            $timeIntervals = [
                31536000 => 'año',
                2628000 => 'mes',
                604800 => 'semana',
                86400 => 'día',
                3600 => 'hora',
                60 => 'minuto',
                1 => 'segundo'
            ];
            
            foreach ($timeIntervals as $seconds => $label) {
                $interval = intval($time / $seconds);
                if ($interval >= 1) {
                    if ($interval == 1) {
                        return "hace 1 $label";
                    } else {
                        $plural = self::getPluralForm($label);
                        return "hace $interval $plural";
                    }
                }
            }
            
            return 'ahora mismo';
            
        } catch (Exception $e) {
            return 'Fecha inválida';
        }
    }
    
    /**
     * Obtiene la forma plural de una palabra en español
     * @param string $word - Palabra singular
     * @return string - Palabra en plural
     */
    private static function getPluralForm($word) {
        $plurals = [
            'año' => 'años',
            'mes' => 'meses',
            'semana' => 'semanas',
            'día' => 'días',
            'hora' => 'horas',
            'minuto' => 'minutos',
            'segundo' => 'segundos'
        ];
        
        return isset($plurals[$word]) ? $plurals[$word] : $word . 's';
    }
    
    /**
     * Registra un mensaje en los logs
     * @param string $message - Mensaje a registrar
     * @param string $level - Nivel del log (INFO, ERROR, DEBUG, WARNING)
     */
    public static function log($message, $level = 'INFO') {
        if (!defined('ENABLE_LOGGING') || !ENABLE_LOGGING) {
            return;
        }
        
        $logLevel = defined('LOG_LEVEL') ? LOG_LEVEL : 'INFO';
        $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
        
        if ($levels[$level] < $levels[$logLevel]) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        
        $logFile = __DIR__ . '/../logs/cinefan.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Valida un email
     * @param string $email - Email a validar
     * @return bool - True si es válido
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Valida una contraseña
     * @param string $password - Contraseña a validar
     * @return array - Array con resultado y mensaje
     */
    public static function validatePassword($password) {
        $minLength = defined('MIN_PASSWORD_LENGTH') ? MIN_PASSWORD_LENGTH : 6;
        
        if (strlen($password) < $minLength) {
            return [
                'valid' => false,
                'message' => "La contraseña debe tener al menos $minLength caracteres"
            ];
        }
        
        return ['valid' => true, 'message' => 'Contraseña válida'];
    }
    
    /**
     * Sanitiza una cadena de texto
     * @param string $string - Cadena a sanitizar
     * @return string - Cadena sanitizada
     */
    public static function sanitizeString($string) {
        return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Genera una cadena aleatoria
     * @param int $length - Longitud de la cadena
     * @return string - Cadena aleatoria
     */
    public static function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }
    
    /**
     * Valida un rating
     * @param float $rating - Rating a validar
     * @return bool - True si es válido
     */
    public static function validateRating($rating) {
        return is_numeric($rating) && $rating >= 0 && $rating <= 5;
    }
    
    /**
     * Formatea un número de likes/seguidores
     * @param int $number - Número a formatear
     * @return string - Número formateado
     */
    public static function formatNumber($number) {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }
        
        return (string) $number;
    }
    
    /**
     * Valida si una URL de imagen es válida
     * @param string $url - URL a validar
     * @return bool - True si es válida
     */
    public static function validateImageUrl($url) {
        if (empty($url)) {
            return true; // URLs vacías son permitidas
        }
        
        // Validar formato de URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Validar extensión de imagen
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        return in_array($extension, $allowedExtensions);
    }
    
    /**
     * Trunca un texto a un número específico de caracteres
     * @param string $text - Texto a truncar
     * @param int $length - Longitud máxima
     * @param string $suffix - Sufijo a agregar
     * @return string - Texto truncado
     */
    public static function truncateText($text, $length = 100, $suffix = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length) . $suffix;
    }
    
    /**
     * Convierte un array asociativo a query string
     * @param array $params - Parámetros
     * @return string - Query string
     */
    public static function buildQueryString($params) {
        $queryParts = [];
        
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $queryParts[] = urlencode($key) . '=' . urlencode($value);
            }
        }
        
        return implode('&', $queryParts);
    }
    
    /**
     * Valida si una fecha es válida
     * @param string $date - Fecha en formato Y-m-d
     * @return bool - True si es válida
     */
    public static function validateDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Obtiene la extensión de un archivo
     * @param string $filename - Nombre del archivo
     * @return string - Extensión en minúsculas
     */
    public static function getFileExtension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    
    /**
     * Valida el tipo MIME de una imagen
     * @param string $mimeType - Tipo MIME
     * @return bool - True si es válido
     */
    public static function validateImageMimeType($mimeType) {
        $allowedTypes = defined('ALLOWED_IMAGE_TYPES') ? 
                       ALLOWED_IMAGE_TYPES : 
                       ['image/jpeg', 'image/png', 'image/gif'];
        
        return in_array($mimeType, $allowedTypes);
    }
    
    /**
     * Genera un slug a partir de un texto
     * @param string $text - Texto original
     * @return string - Slug generado
     */
    public static function generateSlug($text) {
        // Convertir a minúsculas
        $text = mb_strtolower($text, 'UTF-8');
        
        // Reemplazar caracteres especiales
        $text = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $text);
        
        // Quitar caracteres no alfanuméricos
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        
        // Reemplazar espacios y múltiples guiones con un solo guión
        $text = preg_replace('/[\s-]+/', '-', $text);
        
        // Quitar guiones del inicio y final
        return trim($text, '-');
    }
}
?>