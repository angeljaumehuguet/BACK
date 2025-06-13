<?php
/**
 * Este script crea las tablas y datos necesarios para el panel admin
 * Solo se ejecuta una vez para configurar todo
 */

require_once 'config/configuracion.php';
require_once 'config/basedatos.php';

// mostrar errores para debug durante desarrollo
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Inicializando Sistema Administrativo CineFan</h1>";
echo "<p>Autores: Juan Carlos y Angel Hernandez - DAM2</p>";
echo "<hr>";

try {
    // conectar con la base de datos
    $bd = new BaseDatosAdmin();
    echo "<p>✅ Conexion con la base de datos establecida</p>";
    
    // crear tabla de roles si no existe
    $sqlRoles = "
    CREATE TABLE IF NOT EXISTS roles (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nombre VARCHAR(50) NOT NULL UNIQUE,
        descripcion TEXT,
        permisos JSON,
        activo BOOLEAN DEFAULT TRUE,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $bd->ejecutar($sqlRoles);
    echo "<p>✅ Tabla 'roles' creada o verificada</p>";
    
    // agregar columna rol_id a usuarios si no existe
    try {
        $bd->ejecutar("ALTER TABLE usuarios ADD COLUMN rol_id INT DEFAULT NULL");
        echo "<p>✅ Columna 'rol_id' agregada a tabla usuarios</p>";
    } catch (Exception $e) {
        // probablemente ya existe la columna
        echo "<p>⚠️ Columna 'rol_id' ya existe en usuarios</p>";
    }
    
    // crear foreign key si no existe
    try {
        $bd->ejecutar("ALTER TABLE usuarios ADD FOREIGN KEY (rol_id) REFERENCES roles(id)");
        echo "<p>✅ Foreign key agregada entre usuarios y roles</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ Foreign key ya existe o error: " . $e->getMessage() . "</p>";
    }
    
    // insertar roles por defecto
    $rolesDefecto = [
        [
            'nombre' => 'administrador',
            'descripcion' => 'Acceso completo al sistema',
            'permisos' => json_encode([
                'usuarios' => ['crear', 'leer', 'actualizar', 'eliminar'],
                'peliculas' => ['crear', 'leer', 'actualizar', 'eliminar'],
                'resenas' => ['crear', 'leer', 'actualizar', 'eliminar'],
                'generos' => ['crear', 'leer', 'actualizar', 'eliminar'],
                'panel_admin' => true,
                'generar_reportes' => true,
                'configuracion' => true
            ])
        ],
        [
            'nombre' => 'moderador',
            'descripcion' => 'Puede moderar contenido',
            'permisos' => json_encode([
                'peliculas' => ['leer', 'actualizar'],
                'resenas' => ['leer', 'actualizar', 'eliminar'],
                'usuarios' => ['leer'],
                'panel_admin' => true
            ])
        ],
        [
            'nombre' => 'usuario',
            'descripcion' => 'Usuario normal del sistema',
            'permisos' => json_encode([
                'peliculas' => ['leer'],
                'resenas' => ['crear', 'leer', 'actualizar_propias'],
                'perfil' => ['actualizar_propio']
            ])
        ]
    ];
    
    foreach ($rolesDefecto as $rol) {
        // verificar si ya existe
        $existe = $bd->obtenerUno("SELECT id FROM roles WHERE nombre = ?", [$rol['nombre']]);
        
        if (!$existe) {
            $bd->ejecutar(
                "INSERT INTO roles (nombre, descripcion, permisos) VALUES (?, ?, ?)",
                [$rol['nombre'], $rol['descripcion'], $rol['permisos']]
            );
            echo "<p>✅ Rol '{$rol['nombre']}' creado</p>";
        } else {
            echo "<p>⚠️ Rol '{$rol['nombre']}' ya existe</p>";
        }
    }
    
    // obtener ID del rol administrador
    $rolAdmin = $bd->obtenerUno("SELECT id FROM roles WHERE nombre = 'administrador'");
    $rolUsuario = $bd->obtenerUno("SELECT id FROM roles WHERE nombre = 'usuario'");
    
    // crear usuarios administradores por defecto
    $adminsDefecto = [
        [
            'nombre_usuario' => 'admin_juan',
            'email' => 'juan@cinefan.local',
            'password' => password_hash('JuanAdmin2024!', PASSWORD_DEFAULT),
            'nombre_completo' => 'Juan Carlos - Administrador',
            'rol_id' => $rolAdmin['id']
        ],
        [
            'nombre_usuario' => 'admin_angel',
            'email' => 'angel@cinefan.local', 
            'password' => password_hash('AngelAdmin2024!', PASSWORD_DEFAULT),
            'nombre_completo' => 'Angel Hernandez - Administrador',
            'rol_id' => $rolAdmin['id']
        ],
        [
            'nombre_usuario' => 'administrador',
            'email' => 'admin@cinefan.local',
            'password' => password_hash('CineFanAdmin2024!', PASSWORD_DEFAULT),
            'nombre_completo' => 'Administrador General',
            'rol_id' => $rolAdmin['id']
        ]
    ];
    
    foreach ($adminsDefecto as $admin) {
        // verificar si ya existe el usuario
        $existe = $bd->obtenerUno(
            "SELECT id FROM usuarios WHERE nombre_usuario = ? OR email = ?", 
            [$admin['nombre_usuario'], $admin['email']]
        );
        
        if (!$existe) {
            $bd->ejecutar(
                "INSERT INTO usuarios (nombre_usuario, email, password, nombre_completo, rol_id, activo, fecha_registro) 
                 VALUES (?, ?, ?, ?, ?, 1, NOW())",
                [
                    $admin['nombre_usuario'],
                    $admin['email'], 
                    $admin['password'],
                    $admin['nombre_completo'],
                    $admin['rol_id']
                ]
            );
            echo "<p>✅ Usuario administrador '{$admin['nombre_usuario']}' creado</p>";
        } else {
            // actualizar rol si ya existe
            $bd->ejecutar(
                "UPDATE usuarios SET rol_id = ? WHERE nombre_usuario = ? OR email = ?",
                [$admin['rol_id'], $admin['nombre_usuario'], $admin['email']]
            );
            echo "<p>⚠️ Usuario '{$admin['nombre_usuario']}' ya existe - rol actualizado</p>";
        }
    }
    
    // actualizar usuarios existentes sin rol para que tengan rol usuario
    $bd->ejecutar(
        "UPDATE usuarios SET rol_id = ? WHERE rol_id IS NULL",
        [$rolUsuario['id']]
    );
    echo "<p>✅ Usuarios existentes actualizados con rol 'usuario'</p>";
    
    // crear directorios necesarios
    $directorios = [
        'logs',
        'temp',
        'temp/pdf',
        'subidas',
        'backup'
    ];
    
    foreach ($directorios as $directorio) {
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
            echo "<p>✅ Directorio '$directorio' creado</p>";
        } else {
            echo "<p>⚠️ Directorio '$directorio' ya existe</p>";
        }
    }
    
    // crear archivo .htaccess para proteger directorios
    $htaccessContent = "
# Proteccion para directorios sensibles
Options -Indexes
Deny from all
<Files *.log>
    Deny from all
</Files>
    ";
    
    $directoriosProtegidos = ['logs', 'temp', 'backup'];
    foreach ($directoriosProtegidos as $dir) {
        if (is_dir($dir)) {
            file_put_contents("$dir/.htaccess", $htaccessContent);
            echo "<p>✅ Proteccion .htaccess creada en '$dir'</p>";
        }
    }
    
    // crear tabla de sesiones admin para mejor control
    $sqlSesiones = "
    CREATE TABLE IF NOT EXISTS sesiones_admin (
        id VARCHAR(128) PRIMARY KEY,
        usuario_id INT NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        ultimo_acceso TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        activa BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_usuario_activa (usuario_id, activa),
        INDEX idx_ultimo_acceso (ultimo_acceso)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $bd->ejecutar($sqlSesiones);
    echo "<p>✅ Tabla 'sesiones_admin' creada o verificada</p>";
    
    // crear tabla de logs de actividad
    $sqlLogs = "
    CREATE TABLE IF NOT EXISTS logs_actividad (
        id INT PRIMARY KEY AUTO_INCREMENT,
        usuario_id INT,
        accion VARCHAR(100) NOT NULL,
        tabla_afectada VARCHAR(50),
        registro_id INT,
        datos_anteriores JSON,
        datos_nuevos JSON,
        ip_address VARCHAR(45),
        user_agent TEXT,
        fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
        INDEX idx_usuario_fecha (usuario_id, fecha_hora),
        INDEX idx_accion (accion),
        INDEX idx_tabla (tabla_afectada)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $bd->ejecutar($sqlLogs);
    echo "<p>✅ Tabla 'logs_actividad' creada o verificada</p>";
    
    echo "<hr>";
    echo "<h2>🎉 Inicializacion Completada!</h2>";
    echo "<p><strong>Credenciales de administradores creados:</strong></p>";
    echo "<ul>";
    echo "<li><strong>admin_juan</strong> / JuanAdmin2024!</li>";
    echo "<li><strong>admin_angel</strong> / AngelAdmin2024!</li>"; 
    echo "<li><strong>administrador</strong> / CineFanAdmin2024!</li>";
    echo "</ul>";
    echo "<p><a href='login.php' class='btn btn-primary'>Ir al Login</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error durante la inicializacion: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicializacion Admin - CineFan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f8f9fa; 
            padding: 20px;
        }
        .container { 
            max-width: 800px; 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        h1 { color: #343a40; }
        h2 { color: #28a745; }
        .btn { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <!-- El contenido PHP se muestra aqui -->
    </div>
</body>
</html>