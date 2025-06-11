<?php
class Response {
    
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
        
        // log del error
        if (ENABLE_LOGGING) {
            error_log("API Error [{$codigo}]: {$mensaje}");
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
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
    
    public static function validateMethod($allowedMethods) {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if (!in_array($method, $allowedMethods)) {
            self::error('Método HTTP no permitido', 405);
        }
    }
    
    public static function getJsonInput() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::error('JSON inválido en el cuerpo de la petición', 400);
        }
        
        return $data ?: [];
    }
    
    public static function validateRequired($data, $requiredFields) {
        $errores = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $errores[$field] = "El campo {$field} es requerido";
            }
        }
        
        if (!empty($errores)) {
            self::error('Faltan campos requeridos', 422, $errores);
        }
    }
    
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}