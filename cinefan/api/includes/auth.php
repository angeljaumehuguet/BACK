<?php
/**
 * Sistema de autenticación para CineFan API
 * Gestión de tokens, verificación de usuarios, etc.
 */

class Auth {
    
    private static $tokenSecret = 'CineFan_Secret_Key_2025'; // Cambiar en producción
    private static $tokenExpiration = 86400; // 24 horas en segundos
    
    /**
     * Generar token JWT simple
     */
    public static function generateToken($userData) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $payload = json_encode([
            'user_id' => $userData['id'],
            'username' => $userData['nombre_usuario'],
            'email' => $userData['email'],
            'exp' => time() + self::$tokenExpiration,
            'iat' => time()
        ]);
        
        $base64Header = base64_encode($header);
        $base64Payload = base64_encode($payload);
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::$tokenSecret, true);
        $base64Signature = base64_encode($signature);
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    /**
     * Verificar y decodificar token
     */
    public static function verifyToken($token) {
        if (empty($token)) {
            return false;
        }
        
        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) {
            return false;
        }
        
        list($base64Header, $base64Payload, $base64Signature) = $tokenParts;
        
        // Verificar firma
        $signature = base64_decode($base64Signature);
        $expectedSignature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::$tokenSecret, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }
        
        // Decodificar payload
        $payload = json_decode(base64_decode($base64Payload), true);
        
        // Verificar expiración
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    /**
     * Obtener token del header Authorization
     */
    public static function getTokenFromHeader() {
        $headers = self::getRequestHeaders();
        
        if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
            if (strpos($auth, 'Bearer ') === 0) {
                return substr($auth, 7);
            }
        }
        
        return null;
    }
    
    /**
     * Obtener headers de la petición
     */
    private static function getRequestHeaders() {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        
        // Fallback para servidores que no tienen getallheaders
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('HTTP_', '', $key);
                $headerName = str_replace('_', '-', $headerName);
                $headerName = ucwords(strtolower($headerName), '-');
                $headers[$headerName] = $value;
            }
        }
        
        return $headers;
    }
    
    /**
     * Requerir autenticación (middleware)
     */
    public static function requireAuth() {
        $token = self::getTokenFromHeader();
        
        if (!$token) {
            http_response_code(401);
            echo json_encode([
                'exito' => false,
                'mensaje' => 'Token de autorización requerido',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        }
        
        $payload = self::verifyToken($token);
        
        if (!$payload) {
            http_response_code(401);
            echo json_encode([
                'exito' => false,
                'mensaje' => 'Token inválido o expirado',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        }
        
        // Verificar que el usuario aún existe y está activo
        try {
            $db = getDatabase();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT id, nombre_usuario, activo FROM usuarios WHERE id = :id");
            $stmt->bindParam(':id', $payload['user_id']);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !$user['activo']) {
                http_response_code(401);
                echo json_encode([
                    'exito' => false,
                    'mensaje' => 'Usuario no válido o inactivo',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit;
            }
            
        } catch (Exception $e) {
            Utils::log("Error verificando usuario en token: " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode([
                'exito' => false,
                'mensaje' => 'Error interno del servidor',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        }
        
        return $payload;
    }
    
    /**
     * Hash de contraseña
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verificar contraseña
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generar código de verificación (para email, etc.)
     */
    public static function generateVerificationCode($length = 6) {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $code;
    }
    
    /**
     * Verificar si el usuario tiene permisos específicos
     */
    public static function hasPermission($userId, $permission) {
        // Implementación básica - expandir según necesidades
        try {
            $db = getDatabase();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT rol FROM usuarios WHERE id = :id");
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return false;
            }
            
            // Por ahora, solo verificamos si es admin
            if ($permission === 'admin') {
                return $user['rol'] === 'admin';
            }
            
            return true; // Usuario normal tiene permisos básicos
            
        } catch (Exception $e) {
            Utils::log("Error verificando permisos: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Obtener información del usuario actual
     */
    public static function getCurrentUser() {
        $token = self::getTokenFromHeader();
        
        if (!$token) {
            return null;
        }
        
        $payload = self::verifyToken($token);
        
        if (!$payload) {
            return null;
        }
        
        try {
            $db = getDatabase();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("
                SELECT id, nombre_usuario, email, nombre_completo, imagen_perfil, rol, fecha_registro 
                FROM usuarios 
                WHERE id = :id AND activo = true
            ");
            $stmt->bindParam(':id', $payload['user_id']);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            Utils::log("Error obteniendo usuario actual: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    
    /**
     * Revocar token (logout)
     */
    public static function revokeToken($token) {
        // En una implementación más compleja, guardaríamos tokens revocados en BD
        // Por ahora, simplemente registramos el logout
        $payload = self::verifyToken($token);
        
        if ($payload) {
            Utils::log("Usuario {$payload['user_id']} hizo logout", 'INFO');
        }
        
        return true;
    }
    
    /**
     * Verificar si el token está próximo a expirar
     */
    public static function isTokenExpiringSoon($token, $minutesThreshold = 30) {
        $payload = self::verifyToken($token);
        
        if (!$payload || !isset($payload['exp'])) {
            return false;
        }
        
        $expirationTime = $payload['exp'];
        $thresholdTime = time() + ($minutesThreshold * 60);
        
        return $expirationTime <= $thresholdTime;
    }
    
    /**
     * Refrescar token si está próximo a expirar
     */
    public static function refreshTokenIfNeeded($token) {
        if (self::isTokenExpiringSoon($token)) {
            $payload = self::verifyToken($token);
            
            if ($payload) {
                // Buscar datos actuales del usuario
                try {
                    $db = getDatabase();
                    $conn = $db->getConnection();
                    
                    $stmt = $conn->prepare("
                        SELECT id, nombre_usuario, email, nombre_completo 
                        FROM usuarios 
                        WHERE id = :id AND activo = true
                    ");
                    $stmt->bindParam(':id', $payload['user_id']);
                    $stmt->execute();
                    
                    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($userData) {
                        return self::generateToken($userData);
                    }
                    
                } catch (Exception $e) {
                    Utils::log("Error refrescando token: " . $e->getMessage(), 'ERROR');
                }
            }
        }
        
        return $token; // Devolver token original si no necesita refresh
    }
}
?>