<?php
require_once 'DatabaseTest.php';
require_once 'SecurityTest.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineFan Admin - Pruebas del Sistema</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .test-section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .btn {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Sistema de Pruebas - CineFan Admin</h1>
        <p>Desarrollado por Juan Carlos y Angel Hernandez - DAM2</p>
        
        <div class="test-section">
            <h2>Ejecutar Pruebas</h2>
            <button class="btn" onclick="runDatabaseTests()">Probar Base de Datos</button>
            <button class="btn" onclick="runSecurityTests()">Probar Seguridad</button>
            <button class="btn" onclick="runAllTests()">Ejecutar Todo</button>
        </div>
        
        <div id="test-results"></div>
    </div>

    <script>
        function runDatabaseTests() {
            document.getElementById('test-results').innerHTML = '<p>Ejecutando pruebas de BD...</p>';
            
            fetch('?action=database_tests')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('test-results').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('test-results').innerHTML = '<p style="color: red;">Error: ' + error + '</p>';
                });
        }
        
        function runSecurityTests() {
            document.getElementById('test-results').innerHTML = '<p>Ejecutando pruebas de seguridad...</p>';
            
            fetch('?action=security_tests')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('test-results').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('test-results').innerHTML = '<p style="color: red;">Error: ' + error + '</p>';
                });
        }
        
        function runAllTests() {
            document.getElementById('test-results').innerHTML = '<p>Ejecutando todas las pruebas...</p>';
            
            fetch('?action=all_tests')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('test-results').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('test-results').innerHTML = '<p style="color: red;">Error: ' + error + '</p>';
                });
        }
    </script>
</body>
</html>

<?php
// manejar peticiones ajax
if (isset($_GET['action'])) {
    ob_start();
    
    switch ($_GET['action']) {
        case 'database_tests':
            $dbTest = new DatabaseTest();
            $dbTest->runAllTests();
            break;
            
        case 'security_tests':
            $secTest = new SecurityTest();
            $secTest->runSecurityTests();
            break;
            
        case 'all_tests':
            $dbTest = new DatabaseTest();
            $dbTest->runAllTests();
            echo "<hr>";
            $secTest = new SecurityTest();
            $secTest->runSecurityTests();
            break;
    }
    
    $output = ob_get_clean();
    echo $output;
    exit;
}
?>