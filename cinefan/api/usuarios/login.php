<?php
require_once '../config/cors.php';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// validar método
Response::validateMethod(['POST']);

try {
    // obtener datos de entrada
    $input = Response::getJsonInput();
    
    // validar campos requeridos
    Response::validateRequired($input, ['usuario', 'password']);
    
    $usuario = Response::sanitizeInput($input['usuario']);
    $password = $input['password'];
    
    // conectar a la base de datos
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // buscar usuario por nombre de usuario o email
    $sql = "SELECT id, nombre_usuario, email, password, nombre_completo, activo, fecha_registro 
        FROM usuarios 
        WHERE (nombre_usuario = :nombre_usuario OR email = :email) AND activo = true";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':nombre_usuario' => $usuario,
        ':email' => $usuario
    ]);
    
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        Response::error('Usuario no encontrado', 404);
    }
    
    // verificar contraseña
    if (!Auth::verifyPassword($password, $userData['password'])) {
        Response::error('Contraseña incorrecta', 401);
    }
    
    // actualizar último acceso
    $updateSql = "UPDATE usuarios SET fecha_ultimo_acceso = NOW() WHERE id = :id";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bindParam(':id', $userData['id']);
    $updateStmt->execute();
    
    // generar token
    $token = Auth::generateToken($userData);
    
    // preparar respuesta (sin incluir password)
    unset($userData['password']);
    
    Response::success([
        'usuario' => $userData,
        'token' => $token
    ], 'Login exitoso');
    
} catch (Exception $e) {
    Utils::log("Error en login: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}