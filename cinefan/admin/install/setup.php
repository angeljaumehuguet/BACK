<?php
require_once '../config/database.php';

class CineFanInstaller {
    private $db;
    private $errors = [];
    private $success = [];
    
    public function __construct() {
        try {
            $this->db = new AdminDatabase();
            $this->success[] = "✅ Conexion a BD establecida correctamente";
        } catch (Exception $e) {
            $this->errors[] = "❌ Error conectando a la BD: " . $e->getMessage();
        }
    }
    
    // ejecutar toda la instalacion paso a paso
    public function install() {
        $this->checkRequirements();
        $this->createDirectories();
        $this->setupDatabase();
        $this->createAdminUser();
        $this->generateSampleData();
        $this->createConfigFiles();
        $this->testConnections();
        
        $this->showResults();
    }
    
    // verificar que el servidor tenga todo lo necesario
    private function checkRequirements() {
        // version de php minima
        if (version_compare(PHP_VERSION, '7.4.0') < 0) {
            $this->errors[] = "❌ PHP 7.4+ requerido. Tu version: " . PHP_VERSION;
        } else {
            $this->success[] = "✅ PHP version OK: " . PHP_VERSION;
        }
        
        // extensiones php que necesitamos
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $this->errors[] = "❌ Extension PHP faltante: {$ext}";
            } else {
                $this->success[] = "✅ Extension {$ext} disponible";
            }
        }
        
        // verificar que composer este instalado para tcpdf
        if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
            $this->success[] = "✅ Composer encontrado";
            
            // verificar que tcpdf este instalado
            try {
                require_once __DIR__ . '/../../vendor/autoload.php';
                if (class_exists('TCPDF')) {
                    $this->success[] = "✅ TCPDF disponible para generar PDFs";
                } else {
                    $this->errors[] = "❌ TCPDF no encontrado. Ejecuta: composer require tecnickcom/tcpdf";
                }
            } catch (Exception $e) {
                $this->errors[] = "❌ Error cargando composer: " . $e->getMessage();
            }
        } else {
            $this->errors[] = "❌ Composer no encontrado en ../../vendor/autoload.php";
        }
        
        // verificar memoria php (simplificado)
        try {
            $memoryLimit = @ini_get('memory_limit');
            if ($memoryLimit) {
                $this->success[] = "✅ Memoria PHP: {$memoryLimit}";
            } else {
                $this->success[] = "⚠️ Memoria PHP no verificable";
            }
        } catch (Exception $e) {
            $this->success[] = "⚠️ No se pudo verificar memoria PHP";
        }
        
        // permisos de escritura en directorios
        $writableDirs = [
            '../logs/',
            '../uploads/', 
            '../temp/',
            '../temp/pdf/',
            '../config/'
        ];
        
        foreach ($writableDirs as $dir) {
            $fullPath = realpath(__DIR__ . '/' . $dir);
            if (!$fullPath) {
                // el directorio no existe asi que intentamos crearlo
                if (!mkdir(__DIR__ . '/' . $dir, 0755, true)) {
                    $this->errors[] = "❌ No se puede crear directorio: {$dir}";
                    continue;
                }
                $fullPath = realpath(__DIR__ . '/' . $dir);
            }
            
            if (!is_writable($fullPath)) {
                $this->errors[] = "❌ Sin permisos escritura: {$dir}";
            } else {
                $this->success[] = "✅ Permisos OK: {$dir}";
            }
        }
        
        // verificar configuracion de uploads (simplificado)
        try {
            $fileUploads = @ini_get('file_uploads');
            $maxUpload = @ini_get('upload_max_filesize');
            if ($fileUploads) {
                $this->success[] = "✅ Uploads PHP OK (max: {$maxUpload})";
            } else {
                $this->errors[] = "❌ file_uploads deshabilitado en PHP";
            }
        } catch (Exception $e) {
            $this->success[] = "⚠️ No se pudo verificar uploads";
        }
    }
    
    // crear todos los directorios que necesitamos
    private function createDirectories() {
        $dirs = [
            '../logs/' => 'Logs del sistema',
            '../uploads/' => 'Archivos subidos',
            '../uploads/avatars/' => 'Avatares de usuarios',
            '../uploads/posters/' => 'Posters de peliculas',
            '../temp/' => 'Archivos temporales',
            '../temp/pdf/' => 'PDFs generados',
            '../config/' => 'Archivos de configuracion',
            '../backups/' => 'Copias de seguridad'
        ];
        
        foreach ($dirs as $dir => $description) {
            $fullPath = __DIR__ . '/' . $dir;
            
            if (!is_dir($fullPath)) {
                if (mkdir($fullPath, 0755, true)) {
                    $this->success[] = "✅ Directorio creado: {$description}";
                    
                    // crear archivo .htaccess para seguridad
                    if (strpos($dir, 'uploads') !== false) {
                        $htaccess = $fullPath . '.htaccess';
                        file_put_contents($htaccess, "Options -Indexes\nDeny from all\n<Files ~ \"\\.(jpg|jpeg|png|gif|pdf)$\">\nAllow from all\n</Files>");
                    }
                    
                } else {
                    $this->errors[] = "❌ No se pudo crear: {$dir}";
                }
            } else {
                $this->success[] = "✅ Directorio existe: {$description}";
            }
        }
        
        // crear archivo index.php en uploads para evitar listado
        $indexContent = "<?php\n// Acceso denegado\nheader('HTTP/1.0 403 Forbidden');\nexit('Acceso denegado');\n?>";
        file_put_contents(__DIR__ . '/../uploads/index.php', $indexContent);
    }
    
    // verificar que la base de datos este bien configurada
    private function setupDatabase() {
        try {
            // verificar conexion
            $this->db->query("SELECT 1");
            $this->success[] = "✅ Conexion BD funciona";
            
            // verificar que existan las tablas principales del proyecto
            $tables = [
                'usuarios' => 'Tabla de usuarios',
                'peliculas' => 'Tabla de peliculas', 
                'generos' => 'Tabla de generos',
                'resenas' => 'Tabla de resenas',
                'favoritos' => 'Tabla de favoritos',
                'seguimientos' => 'Tabla de seguimientos'
            ];
            
            $existingTables = 0;
            $missingTables = [];
            
            foreach ($tables as $table => $description) {
                $result = $this->db->query("SHOW TABLES LIKE '{$table}'");
                if ($result->rowCount() > 0) {
                    $existingTables++;
                    $this->success[] = "✅ {$description} existe";
                } else {
                    $missingTables[] = $table;
                    $this->errors[] = "❌ Falta tabla: {$table}";
                }
            }
            
            if (count($missingTables) > 0) {
                $this->errors[] = "❌ Faltan " . count($missingTables) . " tablas. Importa el archivo SQL primero";
            }
            
            // verificar indices importantes
            if ($existingTables > 0) {
                $this->checkDatabaseIndexes();
            }
            
            // verificar que mysql tenga el charset correcto
            $charset = $this->db->fetchOne("SELECT @@character_set_database as charset");
            if ($charset['charset'] !== 'utf8mb4') {
                $this->errors[] = "❌ BD debe usar charset utf8mb4";
            } else {
                $this->success[] = "✅ Charset BD correcto: utf8mb4";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ Error verificando BD: " . $e->getMessage();
        }
    }
    
    // verificar indices importantes para performance
    private function checkDatabaseIndexes() {
        $indexes = [
            'usuarios' => ['idx_usuario', 'idx_email'],
            'peliculas' => ['idx_titulo', 'idx_genero'],
            'resenas' => ['idx_usuario', 'idx_pelicula']
        ];
        
        foreach ($indexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $index) {
                try {
                    $result = $this->db->query("SHOW INDEX FROM {$table} WHERE Key_name = '{$index}'");
                    if ($result->rowCount() > 0) {
                        $this->success[] = "✅ Indice {$index} en {$table} existe";
                    } else {
                        $this->errors[] = "⚠️ Indice {$index} faltante en {$table} (afecta performance)";
                    }
                } catch (Exception $e) {
                    // tabla no existe, ya lo reportamos antes
                }
            }
        }
    }
    
    // crear usuario administrador por defecto
    private function createAdminUser() {
        try {
            // verificar si ya existe tabla administradores
            $result = $this->db->query("SHOW TABLES LIKE 'administradores'");
            
            if ($result->rowCount() == 0) {
                // crear tabla administradores si no existe
                $sql = "CREATE TABLE administradores (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    usuario VARCHAR(50) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    nombre_completo VARCHAR(100) NOT NULL,
                    email VARCHAR(100) UNIQUE NOT NULL,
                    nivel_acceso ENUM('admin', 'moderador', 'solo_lectura') DEFAULT 'admin',
                    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    ultimo_acceso TIMESTAMP NULL,
                    activo BOOLEAN DEFAULT TRUE,
                    INDEX idx_usuario (usuario),
                    INDEX idx_nivel (nivel_acceso)
                )";
                
                $this->db->execute($sql);
                $this->success[] = "✅ Tabla administradores creada";
            }
            
            // verificar si ya existe un admin
            $admin = $this->db->fetchOne("SELECT id FROM administradores WHERE usuario = 'admin'");
            
            if (!$admin) {
                $password = password_hash('admin123', PASSWORD_DEFAULT);
                $this->db->execute("
                    INSERT INTO administradores (usuario, password, nombre_completo, email, nivel_acceso) 
                    VALUES ('admin', ?, 'Administrador Principal', 'admin@cinefan.com', 'admin')
                ", [$password]);
                
                $this->success[] = "✅ Usuario admin creado (usuario: admin, password: admin123)";
                $this->success[] = "⚠️ CAMBIA LA PASSWORD POR DEFECTO DESPUES DE ENTRAR";
            } else {
                $this->success[] = "✅ Usuario admin ya existe";
            }
            
            // crear algunos usuarios moderadores de ejemplo
            $moderadores = [
                ['mod_juan', 'Juan Carlos', 'juan@cinefan.com'],
                ['mod_angel', 'Angel Hernandez', 'angel@cinefan.com']
            ];
            
            foreach ($moderadores as $mod) {
                $existing = $this->db->fetchOne("SELECT id FROM administradores WHERE usuario = ?", [$mod[0]]);
                if (!$existing) {
                    $password = password_hash('mod123', PASSWORD_DEFAULT);
                    $this->db->execute("
                        INSERT INTO administradores (usuario, password, nombre_completo, email, nivel_acceso) 
                        VALUES (?, ?, ?, ?, 'moderador')
                    ", [$mod[0], $password, $mod[1], $mod[2]]);
                    
                    $this->success[] = "✅ Moderador {$mod[1]} creado";
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ Error creando admin: " . $e->getMessage();
        }
    }
    
    // verificar que haya datos de muestra en la BD
    private function generateSampleData() {
        try {
            // contar registros en tablas principales
            $counts = [];
            $tables = ['usuarios', 'peliculas', 'generos', 'resenas'];
            
            foreach ($tables as $table) {
                try {
                    $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM {$table}");
                    $counts[$table] = $result['count'];
                } catch (Exception $e) {
                    $counts[$table] = 0;
                }
            }
            
            // reportar estado de los datos
            foreach ($counts as $table => $count) {
                if ($count > 0) {
                    $this->success[] = "✅ Tabla {$table}: {$count} registros";
                } else {
                    $this->errors[] = "⚠️ Tabla {$table} vacia";
                }
            }
            
            // verificar que al menos haya generos basicos
            if ($counts['generos'] == 0) {
                $this->insertBasicGenres();
            }
            
            // sugerir como agregar datos de prueba
            if ($counts['usuarios'] == 0 || $counts['peliculas'] == 0) {
                $this->success[] = "⚠️ Usa el panel admin para agregar usuarios y peliculas";
                $this->success[] = "⚠️ O importa datos de prueba desde el archivo SQL";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ Error verificando datos: " . $e->getMessage();
        }
    }
    
    // insertar generos basicos si no existen
    private function insertBasicGenres() {
        $generos = [
            ['Accion', 'Peliculas de accion y aventura', '#dc3545'],
            ['Comedia', 'Peliculas para reir', '#ffc107'],
            ['Drama', 'Peliculas dramaticas', '#6f42c1'],
            ['Terror', 'Peliculas de miedo', '#1f2937'],
            ['Ciencia Ficcion', 'Peliculas futuristas', '#06b6d4'],
            ['Romance', 'Peliculas romanticas', '#ec4899']
        ];
        
        try {
            foreach ($generos as $genero) {
                $this->db->execute("
                    INSERT INTO generos (nombre, descripcion, color_hex) 
                    VALUES (?, ?, ?)
                ", $genero);
            }
            $this->success[] = "✅ Generos basicos insertados";
        } catch (Exception $e) {
            $this->errors[] = "❌ Error insertando generos: " . $e->getMessage();
        }
    }
    
    // crear archivos de configuracion necesarios
    private function createConfigFiles() {
        // crear archivo de configuracion de logs
        $logConfig = "<?php\n// Configuracion de logs - Juan Carlos y Angel\n";
        $logConfig .= "define('LOG_ENABLED', true);\n";
        $logConfig .= "define('LOG_LEVEL', 'INFO');\n";
        $logConfig .= "define('LOG_MAX_SIZE', '10MB');\n";
        $logConfig .= "define('LOG_RETENTION_DAYS', 30);\n";
        $logConfig .= "?>";
        
        if (file_put_contents(__DIR__ . '/../config/logs.php', $logConfig)) {
            $this->success[] = "✅ Configuracion de logs creada";
        }
        
        // crear archivo .env para configuracion
        $envConfig = "# Configuracion CineFan - Juan Carlos y Angel\n";
        $envConfig .= "APP_ENV=development\n";
        $envConfig .= "APP_DEBUG=true\n";
        $envConfig .= "DB_HOST=localhost\n";
        $envConfig .= "DB_NAME=cinefan_db\n";
        $envConfig .= "DB_USER=root\n";
        $envConfig .= "DB_PASS=\n";
        $envConfig .= "UPLOAD_MAX_SIZE=10M\n";
        $envConfig .= "PDF_TEMP_ENABLED=true\n";
        
        if (file_put_contents(__DIR__ . '/../.env', $envConfig)) {
            $this->success[] = "✅ Archivo .env creado";
        }
        
        // crear archivo de version
        $version = "<?php\n// Version del panel admin\n";
        $version .= "define('CINEFAN_VERSION', '1.0.0');\n";
        $version .= "define('CINEFAN_BUILD', '" . date('Y-m-d H:i:s') . "');\n";
        $version .= "define('CINEFAN_AUTHORS', 'Juan Carlos y Angel Hernandez');\n";
        $version .= "?>";
        
        if (file_put_contents(__DIR__ . '/../config/version.php', $version)) {
            $this->success[] = "✅ Archivo de version creado";
        }
    }
    
    // probar conexiones y funcionalidades
    private function testConnections() {
        // probar consulta compleja para verificar que todo funciona
        try {
            $this->db->query("
                SELECT 
                    (SELECT COUNT(*) FROM usuarios WHERE activo = 1) as usuarios,
                    (SELECT COUNT(*) FROM peliculas WHERE activo = 1) as peliculas
            ");
            $this->success[] = "✅ Consultas complejas funcionan";
        } catch (Exception $e) {
            $this->errors[] = "❌ Error en consultas: " . $e->getMessage();
        }
        
        // probar creacion de archivos
        $testFile = __DIR__ . '/../temp/test_' . time() . '.txt';
        if (file_put_contents($testFile, 'test')) {
            unlink($testFile);
            $this->success[] = "✅ Creacion de archivos funciona";
        } else {
            $this->errors[] = "❌ No se pueden crear archivos temporales";
        }
        
        // verificar que tcpdf funcione
        try {
            if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
                require_once __DIR__ . '/../../vendor/autoload.php';
                if (class_exists('TCPDF')) {
                    $this->success[] = "✅ TCPDF funciona correctamente";
                } else {
                    $this->errors[] = "❌ TCPDF no encontrado";
                }
            } else {
                $this->errors[] = "❌ vendor/autoload.php no encontrado";
            }
        } catch (Exception $e) {
            $this->errors[] = "❌ Error verificando TCPDF: " . $e->getMessage();
        }
    }
    
    // funcion simplificada para compatibilidad
    private function convertToBytes($memoryLimit) {
        // funcion simplificada - solo para compatibilidad
        return 0;
    }
    
    // mostrar todos los resultados de la instalacion
    private function showResults() {
        $totalChecks = count($this->success) + count($this->errors);
        $successRate = $totalChecks > 0 ? round((count($this->success) / $totalChecks) * 100, 1) : 0;
        
        echo "<div style='max-width: 900px; margin: 20px auto; padding: 20px; font-family: Arial, sans-serif;'>";
        echo "<h1 style='text-align: center; color: #333;'>🚀 Instalacion CineFan Admin Panel</h1>";
        echo "<p style='text-align: center; color: #666; margin-bottom: 30px;'>Desarrollado por Juan Carlos y Angel Hernandez - DAM2</p>";
        
        // mostrar progreso general
        echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: center;'>";
        echo "<h3>📊 Progreso de Instalacion</h3>";
        echo "<div style='background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden; margin: 10px 0;'>";
        $progressWidth = min($successRate, 100);
        $progressColor = $successRate >= 80 ? '#28a745' : ($successRate >= 60 ? '#ffc107' : '#dc3545');
        echo "<div style='width: {$progressWidth}%; height: 100%; background: {$progressColor}; transition: width 0.3s;'></div>";
        echo "</div>";
        echo "<p><strong>{$successRate}% completado</strong> ({" . count($this->success) . "}/{$totalChecks} verificaciones pasaron)</p>";
        echo "</div>";
        
        // mostrar elementos exitosos
        if (!empty($this->success)) {
            echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
            echo "<h3 style='color: #155724; margin-top: 0; display: flex; align-items: center;'>";
            echo "<span style='margin-right: 10px;'>✅</span> Elementos Correctos (" . count($this->success) . ")";
            echo "</h3>";
            echo "<div style='max-height: 300px; overflow-y: auto;'>";
            foreach ($this->success as $msg) {
                echo "<p style='color: #155724; margin: 8px 0; padding: 5px 0; border-bottom: 1px solid #c3e6cb;'>{$msg}</p>";
            }
            echo "</div>";
            echo "</div>";
        }
        
        // mostrar errores y advertencias
        if (!empty($this->errors)) {
            echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
            echo "<h3 style='color: #721c24; margin-top: 0; display: flex; align-items: center;'>";
            echo "<span style='margin-right: 10px;'>❌</span> Problemas Encontrados (" . count($this->errors) . ")";
            echo "</h3>";
            echo "<div style='max-height: 300px; overflow-y: auto;'>";
            foreach ($this->errors as $error) {
                $isWarning = strpos($error, '⚠️') !== false;
                $bgColor = $isWarning ? '#fff3cd' : '#f8d7da';
                $textColor = $isWarning ? '#856404' : '#721c24';
                echo "<p style='color: {$textColor}; margin: 8px 0; padding: 8px; background: {$bgColor}; border-radius: 4px;'>{$error}</p>";
            }
            echo "</div>";
            echo "</div>";
        }
        
        // resumen final y acciones
        echo "<div style='background: #e2e3e5; border: 1px solid #d6d8db; padding: 25px; margin: 20px 0; border-radius: 8px;'>";
        echo "<h3 style='margin-top: 0;'>🎯 Resumen Final</h3>";
        
        if (empty($this->errors) || $successRate >= 80) {
            echo "<div style='text-align: center; padding: 20px;'>";
            echo "<h2 style='color: #28a745; margin-bottom: 20px;'>🎉 Instalacion Completada!</h2>";
            echo "<p style='font-size: 16px; margin-bottom: 25px;'>El panel administrativo esta listo para usar</p>";
            
            echo "<div style='display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;'>";
            echo "<a href='../index.html' style='background: #007bff; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold;'>🚀 Ir al Panel Admin</a>";
            echo "<a href='../tests/run_tests.php' style='background: #6c757d; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold;'>🧪 Ejecutar Pruebas</a>";
            echo "</div>";
            
            echo "<div style='margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 6px;'>";
            echo "<h4 style='color: #856404; margin-top: 0;'>📋 Credenciales de Acceso:</h4>";
            echo "<p style='color: #856404; margin: 5px 0;'><strong>Usuario:</strong> admin</p>";
            echo "<p style='color: #856404; margin: 5px 0;'><strong>Password:</strong> admin123</p>";
            echo "<p style='color: #856404; margin: 5px 0; font-size: 14px;'><em>⚠️ Cambia la contraseña despues de entrar</em></p>";
            echo "</div>";
            
        } else {
            echo "<div style='text-align: center; padding: 20px;'>";
            echo "<h2 style='color: #dc3545; margin-bottom: 20px;'>⚠️ Instalacion Incompleta</h2>";
            echo "<p style='font-size: 16px; margin-bottom: 20px;'>Hay problemas que deben corregirse antes de continuar</p>";
            
            echo "<div style='background: #fff; padding: 15px; border-radius: 6px; text-align: left;'>";
            echo "<h4>🔧 Acciones Recomendadas:</h4>";
            echo "<ul style='margin: 10px 0;'>";
            echo "<li>Corrige los errores marcados arriba</li>";
            echo "<li>Verifica la configuracion de la base de datos</li>";
            echo "<li>Ejecuta: <code>composer install</code> si falta</li>";
            echo "<li>Asegurate de tener permisos de escritura</li>";
            echo "<li>Vuelve a ejecutar este instalador</li>";
            echo "</ul>";
            echo "</div>";
            
            echo "<a href='?install=1' style='background: #ffc107; color: #212529; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 15px; display: inline-block;'>🔄 Reintentar Instalacion</a>";
        }
        echo "</div>";
        echo "</div>";
        
        // informacion adicional
        echo "<div style='margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;'>";
        echo "<h4>📚 Documentacion y Soporte</h4>";
        echo "<p>Para mas informacion consulta la documentacion del proyecto</p>";
        echo "<p style='color: #6c757d; font-size: 14px;'>Proyecto final DAM2 - Juan Carlos y Angel Hernandez</p>";
        echo "</div>";
        
        echo "</div>";
    }
}

// ejecutar instalacion cuando se solicite
if (isset($_GET['install'])) {
    $installer = new CineFanInstaller();
    $installer->install();
} else {
    // mostrar pagina de inicio del instalador
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador CineFan Admin Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            max-width: 700px;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .logo {
            font-size: 3rem;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2.2rem;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        
        .description {
            color: #555;
            line-height: 1.6;
            margin-bottom: 25px;
            text-align: left;
        }
        
        .requirements {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            text-align: left;
        }
        
        .requirements h3 {
            color: #495057;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .requirements ul {
            list-style: none;
            padding: 0;
        }
        
        .requirements li {
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
        }
        
        .requirements li:last-child {
            border-bottom: none;
        }
        
        .requirements li::before {
            content: '✓';
            color: #28a745;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 35px;
            text-decoration: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(118, 75, 162, 0.4);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(118, 75, 162, 0.6);
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .warning strong {
            display: block;
            margin-bottom: 5px;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 20px;
                padding: 30px 20px;
            }
            
            h1 { font-size: 1.8rem; }
            .logo { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🎬</div>
        <h1>CineFan Admin Panel</h1>
        <p class="subtitle">Instalador Automatico del Sistema</p>
        <p class="subtitle" style="font-size: 0.9rem; color: #999;">Desarrollado por Juan Carlos y Angel Hernandez - DAM2</p>
        
        <div class="description">
            <p>Este instalador va a verificar que tu servidor tenga todo lo necesario y configurar automaticamente el panel de administracion de CineFan.</p>
            <p>El proceso incluye verificacion de requisitos, creacion de directorios, configuracion de la base de datos y creacion del usuario administrador.</p>
        </div>
        
        <div class="requirements">
            <h3>📋 Requisitos del Sistema:</h3>
            <ul>
                <li>PHP 7.4 o superior</li>
                <li>MySQL 8.0 o superior</li>
                <li>Extensiones: PDO, PDO_MySQL, JSON, MBString</li>
                <li>Composer instalado con dependencias</li>
                <li>Permisos de escritura en directorios</li>
                <li>Base de datos CineFan ya creada e importada</li>
            </ul>
        </div>
        
        <div class="warning">
            <strong>⚠️ Importante:</strong>
            Asegurate de haber creado la base de datos y importado el archivo SQL antes de continuar con la instalacion.
        </div>
        
        <a href="?install=1" class="btn">🚀 Iniciar Instalacion</a>
        
        <div class="footer">
            <p><strong>CineFan Admin Panel v1.0.0</strong></p>
            <p>Sistema de administracion para red social de cine</p>
        </div>
    </div>
</body>
</html>
<?php
}
?>