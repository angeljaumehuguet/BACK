<?php
require_once '../includes/security.php';

class SecurityTest {
    private $results = [];
    
    // ejecutar todas las pruebas de seguridad
    public function runSecurityTests() {
        echo "<h2>🔒 Probando Seguridad</h2>\n";
        
        $this->testInputValidation();
        $this->testXSSPrevention();
        $this->testCSRFProtection();
        $this->testPasswordSecurity();
        
        $this->showResults();
    }
    
    // probar validacion de entradas
    private function testInputValidation() {
        try {
            $rules = [
                'email' => ['required' => true, 'email' => true],
                'password' => ['required' => true, 'min_length' => 6],
                'age' => ['numeric' => true, 'min_value' => 18, 'max_value' => 120]
            ];
            
            // datos correctos
            $validData = [
                'email' => 'test@example.com',
                'password' => 'password123',
                'age' => 25
            ];
            
            $errors = SecurityManager::validateInput($validData, $rules);
            if (!empty($errors)) {
                throw new Exception('Datos validos fueron rechazados: ' . implode(', ', $errors));
            }
            
            // datos incorretos
            $invalidData = [
                'email' => 'email-malo',
                'password' => '123',
                'age' => 15
            ];
            
            $errors = SecurityManager::validateInput($invalidData, $rules);
            if (count($errors) !== 3) {
                throw new Exception('No se detectaron todos los errores');
            }
            
            $this->addResult('Validacion Entrada', true, 'Validaciones funcionan bien');
        } catch (Exception $e) {
            $this->addResult('Validacion Entrada', false, $e->getMessage());
        }
    }
    
    // probar prevencion de xss
    private function testXSSPrevention() {
        try {
            $xssInputs = [
                '<script>alert("XSS")</script>',
                '<iframe src="javascript:alert(\'XSS\')"></iframe>',
                '<form><input type="hidden" name="malicious" value="data"></form>'
            ];
            
            foreach ($xssInputs as $input) {
                try {
                    SecurityManager::preventXSS($input);
                    $this->addResult('Prevencion XSS', false, 'XSS no detectado: ' . $input);
                    return;
                } catch (Exception $e) {
                    // bien detectado
                }
            }
            
            $this->addResult('Prevencion XSS', true, 'Prevencion XSS funciona');
        } catch (Exception $e) {
            $this->addResult('Prevencion XSS', false, $e->getMessage());
        }
    }
    
    // probar tokens csrf
    private function testCSRFProtection() {
        try {
            // generar token
            $token = SecurityManager::generateCSRFToken();
            
            if (empty($token)) {
                throw new Exception('Token CSRF no se genero');
            }
            
            // validar token correcto
            if (!SecurityManager::validateCSRFToken($token)) {
                throw new Exception('Token CSRF valido fue rechazado');
            }
            
            // validar token incorrecto
            if (SecurityManager::validateCSRFToken('token_malo')) {
                throw new Exception('Token CSRF malo fue aceptado');
            }
            
            $this->addResult('Proteccion CSRF', true, 'Proteccion CSRF funciona');
        } catch (Exception $e) {
            $this->addResult('Proteccion CSRF', false, $e->getMessage());
        }
    }
    
    // probar hashing de contraseñas
    private function testPasswordSecurity() {
        try {
            $password = 'MiPasswordSegura123!';
            
            // crear hash
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if (!password_verify($password, $hash)) {
                throw new Exception('Hash de password no funciona');
            }
            
            // verificar password incorrecta
            if (password_verify('password_malo', $hash)) {
                throw new Exception('Password incorrecta fue aceptada');
            }
            
            $this->addResult('Seguridad Passwords', true, 'Hashing de passwords funciona');
        } catch (Exception $e) {
            $this->addResult('Seguridad Passwords', false, $e->getMessage());
        }
    }
    
    // agregar resultado de prueba
    private function addResult($test, $passed, $message) {
        $this->results[] = [
            'test' => $test,
            'passed' => $passed,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    // mostrar resultados
    private function showResults() {
        $passed = 0;
        $total = count($this->results);
        
        echo "<div style='margin: 20px; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>";
        echo "<h3>🔐 Resultados Pruebas Seguridad</h3>";
        
        foreach ($this->results as $result) {
            $icon = $result['passed'] ? '✅' : '❌';
            $color = $result['passed'] ? 'green' : 'red';
            
            if ($result['passed']) $passed++;
            
            echo "<div style='margin: 10px 0; padding: 10px; border-left: 3px solid {$color};'>";
            echo "<strong>{$icon} {$result['test']}</strong><br>";
            echo "<span style='color: {$color};'>{$result['message']}</span><br>";
            echo "<small>Ejecutado: {$result['timestamp']}</small>";
            echo "</div>";
        }
        
        $percentage = round(($passed / $total) * 100, 1);
        $overallColor = $percentage >= 80 ? 'green' : ($percentage >= 60 ? 'orange' : 'red');
        
        echo "<div style='margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px;'>";
        echo "<h4 style='color: {$overallColor};'>Resumen Seguridad: {$passed}/{$total} pruebas pasaron ({$percentage}%)</h4>";
        echo "</div>";
        echo "</div>";
    }
}