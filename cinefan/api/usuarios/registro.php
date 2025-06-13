<?php
require_once '../config/cors.php';
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
    Response::validateRequired($input, ['nombre_usuario', 'email', 'password', 'nombre_completo']);
    
    $nombreUsuario = Response::sanitizeInput($input['nombre_usuario']);
    $email = Response::sanitizeInput($input['email']);
    $password = $input['password'];
    $nombreCompleto = Response::sanitizeInput($input['nombre_completo']);
    
    // validaciones
    $errores = [];
    
    // validar nombre de usuario
    if (strlen($nombreUsuario) < 3 || strlen($nombreUsuario) > 50) {
        $errores['nombre_usuario'] = 'El nombre de usuario debe tener entre 3 y 50 caracteres';
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $nombreUsuario)) {
        $errores['nombre_usuario'] = 'El nombre de usuario solo puede contener letras, números y guiones bajos';
    }
    
    // validar email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = 'El formato del email no es válido';
    }
    
    // validar contraseña
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errores['password'] = 'La contraseña debe tener al menos ' . MIN_PASSWORD_LENGTH . ' caracteres';
    }
    
    // validar nombre completo
    if (strlen($nombreCompleto) < 2) {
        $errores['nombre_completo'] = 'El nombre completo es muy corto';
    }
    
    if (!empty($errores)) {
        Response::error('Errores de validación', 422, $errores);
    }
    
    // conectar a la base de datos
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // verificar si el usuario ya existe
    $checkSql = "SELECT id FROM usuarios WHERE nombre_usuario = :nombre_usuario OR email = :email";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bindParam(':nombre_usuario', $nombreUsuario);
    $checkStmt->bindParam(':email', $email);
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        Response::error('El nombre de usuario o email ya está en uso', 409);
    }
    
    // hash de la contraseña
    $hashedPassword = Auth::hashPassword($password);
    
    // insertar nuevo usuario
    $insertSql = "INSERT INTO usuarios (nombre_usuario, email, password, nombre_completo) 
                  VALUES (:nombre_usuario, :email, :password, :nombre_completo)";
    
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bindParam(':nombre_usuario', $nombreUsuario);
    $insertStmt->bindParam(':email', $email);
    $insertStmt->bindParam(':password', $hashedPassword);
    $insertStmt->bindParam(':nombre_completo', $nombreCompleto);
    
    if ($insertStmt->execute()) {
        $userId = $conn->lastInsertId();
        
        Utils::log("Nuevo usuario registrado: {$nombreUsuario} (ID: {$userId})", 'INFO');
        
        Response::success([
            'id' => $userId,
            'nombre_usuario' => $nombreUsuario,
            'email' => $email,
            'nombre_completo' => $nombreCompleto
        ], 'Usuario registrado exitosamente');
    } else {
        Response::error('Error al registrar el usuario', 500);
    }
    
} catch (Exception $e) {
    Utils::log("Error en registro: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}