<?php
/**
 * HERRAMIENTA DE DIAGNÓSTICO PARA ENDPOINTS DE CINEFAN
 * Úsala para verificar que todo funciona correctamente
 */

// Configuración básica
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once 'config/database.php';
require_once 'includes/functions.php';

function testDatabase() {
    echo "<h3>🔍 Test Conexión Base de Datos</h3>";
    try {
        $db = getDatabase();
        $conn = $db->getConnection();
        echo "✅ Conexión exitosa<br>";
        
        // Test básico de consulta
        $stmt = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Usuarios activos: {$result['total']}<br>";
        
        $stmt = $conn->query("SELECT COUNT(*) as total FROM peliculas WHERE activo = 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Películas activas: {$result['total']}<br>";
        
        $stmt = $conn->query("SELECT COUNT(*) as total FROM resenas WHERE activo = 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Reseñas activas: {$result['total']}<br>";
        
        $stmt = $conn->query("SELECT COUNT(*) as total FROM generos WHERE activo = 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Géneros activos: {$result['total']}<br>";
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>";
    }
}

function testGeneros() {
    echo "<h3>🎬 Test Géneros</h3>";
    try {
        $db = getDatabase();
        $conn = $db->getConnection();
        
        $stmt = $conn->query("SELECT id, nombre, color_hex FROM generos WHERE activo = 1 ORDER BY id");
        $generos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Color</th></tr>";
        
        foreach ($generos as $genero) {
            echo "<tr>";
            echo "<td>{$genero['id']}</td>";
            echo "<td>{$genero['nombre']}</td>";
            echo "<td style='background-color: {$genero['color_hex']}'>{$genero['color_hex']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>";
    }
}

function testFunctions() {
    echo "<h3>🔧 Test Funciones Utilities</h3>";
    
    // Test Utils::timeAgo
    if (function_exists('Utils::timeAgo')) {
        echo "✅ Utils::timeAgo existe<br>";
        try {
            $test = Utils::timeAgo('2024-01-01 12:00:00');
            echo "✅ Utils::timeAgo funciona: {$test}<br>";
        } catch (Exception $e) {
            echo "❌ Utils::timeAgo error: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ Utils::timeAgo no existe<br>";
    }
    
    // Test Utils::log
    try {
        Utils::log("Test log message", 'INFO');
        echo "✅ Utils::log funciona<br>";
    } catch (Exception $e) {
        echo "❌ Utils::log error: " . $e->getMessage() . "<br>";
    }
}

function testEndpoints() {
    echo "<h3>🌐 Test Endpoints</h3>";
    
    $endpoints = [
        'usuarios/login.php',
        'peliculas/listar.php',
        'resenas/feed.php',
        'generos/listar.php'
    ];
    
    foreach ($endpoints as $endpoint) {
        $url = "http://{$_SERVER['HTTP_HOST']}/cinefan/api/{$endpoint}";
        echo "<strong>Testing:</strong> <a href='{$url}' target='_blank'>{$endpoint}</a><br>";
        
        if (file_exists($endpoint)) {
            echo "✅ Archivo existe<br>";
        } else {
            echo "❌ Archivo no existe<br>";
        }
    }
}

function showServerInfo() {
    echo "<h3>🖥️ Información del Servidor</h3>";
    echo "<strong>PHP Version:</strong> " . phpversion() . "<br>";
    echo "<strong>Server:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
    echo "<strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
    echo "<strong>Current Directory:</strong> " . __DIR__ . "<br>";
    echo "<strong>MySQL Available:</strong> " . (extension_loaded('pdo_mysql') ? '✅ Sí' : '❌ No') . "<br>";
    echo "<strong>JSON Available:</strong> " . (extension_loaded('json') ? '✅ Sí' : '❌ No') . "<br>";
}

function showDebugInfo() {
    echo "<h3>🐛 Debug Info</h3>";
    echo "<strong>Error Reporting:</strong> " . error_reporting() . "<br>";
    echo "<strong>Display Errors:</strong> " . ini_get('display_errors') . "<br>";
    echo "<strong>Log Errors:</strong> " . ini_get('log_errors') . "<br>";
    echo "<strong>Error Log:</strong> " . ini_get('error_log') . "<br>";
    echo "<strong>Memory Limit:</strong> " . ini_get('memory_limit') . "<br>";
    echo "<strong>Max Execution Time:</strong> " . ini_get('max_execution_time') . "<br>";
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineFan API - Diagnóstico</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        h3 { color: #666; border-bottom: 1px solid #ccc; }
        .success { color: green; }
        .error { color: red; }
        table { margin: 10px 0; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>🎬 CineFan API - Herramienta de Diagnóstico</h1>
    <p><strong>Fecha:</strong> <?= date('Y-m-d H:i:s') ?></p>
    
    <?php
    showServerInfo();
    showDebugInfo();
    testDatabase();
    testGeneros();
    testFunctions();
    testEndpoints();
    ?>
    
    <h3>📋 Próximos Pasos</h3>
    <ol>
        <li>Verifica que todas las conexiones sean exitosas</li>
        <li>Asegúrate de que los géneros estén configurados</li>
        <li>Testa los endpoints directamente</li>
        <li>Revisa los logs de Apache/PHP si hay errores</li>
    </ol>
    
    <h3>🔗 Enlaces Útiles</h3>
    <ul>
        <li><a href="http://<?= $_SERVER['HTTP_HOST'] ?>/cinefan/api/generos/listar.php" target="_blank">Listar Géneros</a></li>
        <li><a href="http://<?= $_SERVER['HTTP_HOST'] ?>/cinefan/api/peliculas/listar.php" target="_blank">Listar Películas</a></li>
        <li><a href="http://<?= $_SERVER['HTTP_HOST'] ?>/cinefan/api/resenas/feed.php" target="_blank">Feed de Reseñas</a></li>
        <li><a href="http://<?= $_SERVER['HTTP_HOST'] ?>/phpmyadmin" target="_blank">phpMyAdmin</a></li>
    </ul>
    
    <hr>
    <p><small>Generado automáticamente - CineFan API v1.0</small></p>
</body>
</html>