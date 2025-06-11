<?php
session_start();

// si ya está logueado, redirigir al dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../api/config/database.php';
    
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($usuario) || empty($password)) {
        $error = 'Todos los campos son requeridos';
    } else {
        try {
            $db = getDatabase();
            $conn = $db->getConnection();
            
            $sql = "SELECT id, usuario, password, nombre_completo, nivel_acceso 
                    FROM administradores 
                    WHERE usuario = :usuario AND activo = true";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':usuario', $usuario);
            $stmt->execute();
            
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin && password_verify($password, $admin['password'])) {
                // login exitoso
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_usuario'] = $admin['usuario'];
                $_SESSION['admin_nombre'] = $admin['nombre_completo'];
                $_SESSION['admin_nivel'] = $admin['nivel_acceso'];
                
                // actualizar último acceso
                $updateSql = "UPDATE administradores SET ultimo_acceso = NOW() WHERE id = :id";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bindParam(':id', $admin['id']);
                $updateStmt->execute();
                
                header('Location: index.php');
                exit();
            } else {
                $error = 'Usuario o contraseña incorrectos';
            }
        } catch (Exception $e) {
            $error = 'Error de conexión. Intenta de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineFan - Administración</title>
    <link href="css/admin.css" rel="stylesheet">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>🎬 CineFan</h1>
                <p>Panel de Administración</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario" 
                           value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>" 
                           required autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" 
                           required autocomplete="current-password">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    Iniciar Sesión
                </button>
            </form>
            
            <div class="login-footer">
                <p><small>Sistema de administración CineFan v1.0</small></p>
            </div>
        </div>
    </div>
</body>
</html>