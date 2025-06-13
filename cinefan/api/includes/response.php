<?php
/**
 * Clase Response para manejar respuestas de la API CineFan
 * Incluye todos los métodos necesarios para los endpoints
 */

class Response {
    
    /**
     * Respuesta exitosa
     */
    public static function success($data = null, $mensaje = 'Operación exitosa', $codigo = 200) {
        http_response_code($codigo);
        
        $response = [
            'exito' => true,
            'mensaje' => $mensaje,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($data !== null) {
            $response['datos'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * Respuesta de error
     */
    public static function error($mensaje = 'Error en la operación', $codigo = 400, $detalles = null) {
        http_response_code($codigo);
        
        $response = [
            'exito' => false,
            'mensaje' => $mensaje,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($detalles !== null) {
            $response['errores'] = $detalles;
        }
        
        // Log del error
        if (defined('ENABLE_LOGGING') && ENABLE_LOGGING) {
            Utils::log("API Error [{$codigo}]: {$mensaje}", 'ERROR');
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * Respuesta paginada
     */
    public static function paginated($data, $pagina, $totalElementos, $elementosPorPagina, $mensaje = 'Datos obtenidos') {
        $totalPaginas = ceil($totalElementos / $elementosPorPagina);
        
        $response = [
            'exito' => true,
            'mensaje' => $mensaje,
            'datos' => $data,
            'paginacion' => [
                'pagina_actual' => (int)$pagina,
                'elementos_por_pagina' => (int)$elementosPorPagina,
                'total_elementos' => (int)$totalElementos,
                'total_paginas' => (int)$totalPaginas,
                'tiene_anterior' => $pagina > 1,
                'tiene_siguiente' => $pagina < $totalPaginas
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * Validar método HTTP
     */
    public static function validateMethod($allowedMethods) {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if (!in_array($method, $allowedMethods)) {
            self::error('Método HTTP no permitido', 405);
        }
    }
    
    /**
     * Obtener datos JSON del cuerpo de la petición
     */
    public static function getJsonInput() {
        $input = file_get_contents('php://input');
        
        if (empty($input)) {
            return [];
        }
        
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::error('JSON inválido en el cuerpo de la petición', 400);
        }
        
        return $data ? $data : [];
    }
    
    /**
     * Validar campos requeridos
     */
    public static function validateRequired($data, $requiredFields) {
        $errores = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && empty(trim($data[$field])))) {
                $errores[$field] = "El campo {$field} es requerido";
            }
        }
        
        if (!empty($errores)) {
            self::error('Faltan campos requeridos', 422, $errores);
        }
    }
    
    /**
     * Limpiar y sanitizar entrada
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        if (is_string($data)) {
            return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
        }
        
        return $data;
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
     * Validar número entero en rango
     */
    public static function validateIntRange($value, $min, $max) {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        return $int !== false && $int >= $min && $int <= $max;
    }
    
    /**
     * Validar longitud de string
     */
    public static function validateStringLength($string, $min, $max) {
        $length = strlen($string);
        return $length >= $min && $length <= $max;
    }
    
    /**
     * Respuesta con headers personalizados
     */
    public static function sendWithHeaders($data, $headers = []) {
        // Headers por defecto
        $defaultHeaders = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ];
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        foreach ($allHeaders as $name => $value) {
            header("{$name}: {$value}");
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * Respuesta de archivo (para descargas)
     */
    public static function sendFile($filePath, $fileName = null, $mimeType = 'application/octet-stream') {
        if (!file_exists($filePath)) {
            self::error('Archivo no encontrado', 404);
        }
        
        $fileName = $fileName ?: basename($filePath);
        $fileSize = filesize($filePath);
        
        header("Content-Type: {$mimeType}");
        header("Content-Disposition: attachment; filename=\"{$fileName}\"");
        header("Content-Length: {$fileSize}");
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        readfile($filePath);
        exit();
    }
    
    /**
     * Respuesta de redirección
     */
    public static function redirect($url, $permanent = false) {
        $code = $permanent ? 301 : 302;
        http_response_code($code);
        header("Location: {$url}");
        exit();
    }
    
    /**
     * Respuesta de estado (para health checks)
     */
    public static function status($status = 'OK', $details = []) {
        $response = [
            'status' => $status,
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => defined('APP_VERSION') ? APP_VERSION : '1.0.0'
        ];
        
        if (!empty($details)) {
            $response['details'] = $details;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * Respuesta CORS preflight
     */
    public static function handleCorsPrelight() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Max-Age: 86400');
            http_response_code(200);
            exit();
        }
    }
    
    /**
     * Obtener información de la petición
     */
    public static function getRequestInfo() {
        return [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'query_string' => $_SERVER['QUERY_STRING'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => Utils::getClientIp(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Respuesta de error de validación con detalles
     */
    public static function validationError($errores, $mensaje = 'Errores de validación') {
        self::error($mensaje, 422, $errores);
    }
    
    /**
     * Respuesta de no autorizado
     */
    public static function unauthorized($mensaje = 'No autorizado') {
        self::error($mensaje, 401);
    }
    
    /**
     * Respuesta de prohibido
     */
    public static function forbidden($mensaje = 'Acceso prohibido') {
        self::error($mensaje, 403);
    }
    
    /**
     * Respuesta de no encontrado
     */
    public static function notFound($mensaje = 'Recurso no encontrado') {
        self::error($mensaje, 404);
    }
    
    /**
     * Respuesta de conflicto
     */
    public static function conflict($mensaje = 'Conflicto de recursos') {
        self::error($mensaje, 409);
    }
    
    /**
     * Respuesta de límite de tasa excedido
     */
    public static function tooManyRequests($mensaje = 'Demasiadas peticiones') {
        self::error($mensaje, 429);
    }
    
    /**
     * Respuesta de error interno del servidor
     */
    public static function internalError($mensaje = 'Error interno del servidor') {
        self::error($mensaje, 500);
    }
    
    /**
     * Debug: mostrar información de depuración
     */
    public static function debug($data, $message = 'Debug info') {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $response = [
                'debug' => true,
                'message' => $message,
                'data' => $data,
                'request_info' => self::getRequestInfo(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit();
        }
    }
}
?>