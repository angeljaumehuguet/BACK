<?php
class SecurityManager {
    
    // validar datos de entrada con reglas
    public static function validateInput($data, $rules = []) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // verificar campos obligatorios
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = "El campo {$field} es obligatorio";
                continue;
            }
            
            if (empty($value)) continue;
            
            // longitud minima
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[$field] = "El campo {$field} debe tener minimo {$rule['min_length']} caracteres";
            }
            
            // longitud maxima
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[$field] = "El campo {$field} no puede tener mas de {$rule['max_length']} caracteres";
            }
            
            // validar email
            if (isset($rule['email']) && $rule['email'] && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "El campo {$field} debe ser un email valido";
            }
            
            // verificar que sea numero
            if (isset($rule['numeric']) && $rule['numeric'] && !is_numeric($value)) {
                $errors[$field] = "El campo {$field} debe ser un numero";
            }
            
            // valor minimo
            if (isset($rule['min_value']) && $value < $rule['min_value']) {
                $errors[$field] = "El campo {$field} debe ser mayor o igual a {$rule['min_value']}";
            }
            
            // valor maximo
            if (isset($rule['max_value']) && $value > $rule['max_value']) {
                $errors[$field] = "El campo {$field} debe ser menor o igual a {$rule['max_value']}";
            }
        }
        
        return $errors;
    }
    
    // limpiar datos de entrada
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    // generar token para csrf
    public static function generateCSRFToken() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION[CSRF_TOKEN_NAME] = $token;
        return $token;
    }
    
    // verificar token csrf
    public static function validateCSRFToken($token) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION[CSRF_TOKEN_NAME]) && 
               hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    // detectar sql injection
    public static function preventSQLInjection($input) {
        // patrones tipicos de inyeccion sql
        $patterns = [
            '/(\s|^)(select|insert|update|delete|drop|create|alter|exec|execute|union|script|javascript|vbscript)(\s|$)/i',
            '/(\s|^)(or|and)(\s+\d+\s*=\s*\d+|\s+\'\w*\'\s*=\s*\'\w*\')/i',
            '/(\s|^)(union(\s+all)?(\s+select)|exec(\s+sp_|(\s*\()|(\s+xp_)))/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                throw new Exception('Intento de SQL injection detectado');
            }
        }
        
        return $input;
    }
    
    // prevenir ataques xss
    public static function preventXSS($input) {
        // tags peligrosos que no permitimos
        $dangerous_tags = ['script', 'iframe', 'embed', 'object', 'form', 'input', 'textarea', 'button'];
        
        foreach ($dangerous_tags as $tag) {
            if (stripos($input, "<{$tag}") !== false) {
                throw new Exception('Contenido XSS detectado');
            }
        }
        
        return $input;
    }
}
