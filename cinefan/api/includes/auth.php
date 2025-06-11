<?php
require_once __DIR__ . '/../config/database.php';
require_once '../config/config.php';

class Auth {
    
    public static function generateToken($userData) {
        $header = json_encode(['typ' => 'JWT', 'alg' => JWT_ALGORITHM]);
        
        $payload = json_encode([
            'user_id' => $userData['id'],
            'username' => $userData['nombre_usuario'],
            'email' => $userData['email'],
            'iat' => time(),
            'exp' => time() + JWT_EXPIRATION
        ]);
        
        $headerEncoded = self::base64UrlEncode($header);
        $payloadEncoded = self::base64UrlEncode($payload);
        
        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, JWT_SECRET, true);
        $signatureEncoded = self::base64UrlEncode($signature);
        
        return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
    }
    
    public static function verifyToken($token) {
        if (!$token) {
            return false;
        }
        
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        // verificar firma
        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, JWT_SECRET, true);
        $expectedSignature = self::base64UrlEncode($signature);
        
        if (!hash_equals($expectedSignature, $signatureEncoded)) {
            return false;
        }
        
        // decodificar payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        
        // verificar expiración
        if ($payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    public static function getTokenFromHeader() {
        $headers = getallheaders();
        
        if (!$headers) {
            return null;
        }
        
        // buscar header de autorización (case insensitive)
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'authorization') {
                if (preg_match('/Bearer\s(\S+)/', $value, $matches)) {
                    return $matches[1];
                }
            }
        }
        
        return null;
    }
    
    public static function requireAuth() {
        $token = self::getTokenFromHeader();
        $payload = self::verifyToken($token);
        
        if (!$payload) {
            http_response_code(401);
            echo json_encode([
                'exito' => false,
                'mensaje' => 'Token inválido o expirado',
                'codigo' => 'TOKEN_INVALID'
            ]);
            exit();
        }
        
        return $payload;
    }
    
    public static function checkResourceOwnership($userId, $resourceUserId) {
        if ($userId != $resourceUserId) {
            http_response_code(403);
            echo json_encode([
                'exito' => false,
                'mensaje' => 'No tienes permisos para acceder a este recurso',
                'codigo' => 'ACCESS_DENIED'
            ]);
            exit();
        }
    }
    
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private static function base64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}