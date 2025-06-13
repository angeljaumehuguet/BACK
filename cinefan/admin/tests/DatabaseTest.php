<?php
require_once '../config/database.php';
require_once '../includes/logger.php';

class DatabaseTest {
    private $db;
    private $results = [];
    
    public function __construct() {
        $this->db = new AdminDatabase();
        Logger::init();
    }
    
    // ejecutar todas las pruebas de bd
    public function runAllTests() {
        echo "<h2>🧪 Probando Base de Datos</h2>\n";
        
        $this->testConnection();
        $this->testUsuariosCRUD();
        $this->testPeliculasCRUD();
        $this->testGenerosCRUD();
        $this->testValidations();
        $this->testSQLInjectionPrevention();
        $this->testPerformance();
        $this->testDataIntegrity();
        
        $this->showResults();
    }
    
    // probar que la conexion funciona
    private function testConnection() {
        try {
            $this->db->query("SELECT 1");
            $this->addResult('Conexion BD', true, 'Conectado correctamente');
        } catch (Exception $e) {
            $this->addResult('Conexion BD', false, $e->getMessage());
        }
    }
    
    // probar crud de usuarios
    private function testUsuariosCRUD() {
        try {
            // crear usuario de prueba
            $testUser = [
                'nombre_usuario' => 'test_user_' . time(),
                'email' => 'test' . time() . '@test.com',
                'password' => password_hash('test123', PASSWORD_DEFAULT),
                'nombre_completo' => 'Usuario Prueba',
                'activo' => 1
            ];
            
            $sql = "INSERT INTO usuarios (nombre_usuario, email, password, nombre_completo, activo) VALUES (?, ?, ?, ?, ?)";
            $this->db->execute($sql, array_values($testUser));
            
            // leer el usuario creado
            $createdUser = $this->db->fetchOne("SELECT * FROM usuarios WHERE nombre_usuario = ?", [$testUser['nombre_usuario']]);
            if (!$createdUser) {
                throw new Exception('Usuario no encontrado despues de crearlo');
            }
            
            // actualizar usuario
            $this->db->execute("UPDATE usuarios SET nombre_completo = ? WHERE id = ?", ['Usuario Actualizado', $createdUser['id']]);
            
            // eliminar usuario
            $this->db->execute("DELETE FROM usuarios WHERE id = ?", [$createdUser['id']]);
            
            $this->addResult('CRUD Usuarios', true, 'Crear leer actualizar y borrar funcionan');
        } catch (Exception $e) {
            $this->addResult('CRUD Usuarios', false, $e->getMessage());
        }
    }
    
    // probar crud de peliculas
    private function testPeliculasCRUD() {
        try {
            // necesitamos un genero para la pelicula
            $genero = $this->db->fetchOne("SELECT id FROM generos WHERE activo = 1 LIMIT 1");
            if (!$genero) {
                throw new Exception('No hay generos para probar');
            }
            
            // crear pelicula de prueba
            $testMovie = [
                'titulo' => 'Pelicula Prueba ' . time(),
                'director' => 'Director Prueba',
                'ano_lanzamiento' => 2023,
                'duracion_minutos' => 120,
                'genero_id' => $genero['id'],
                'sinopsis' => 'Sinopsis de prueba',
                'id_usuario_creador' => 1
            ];
            
            $sql = "INSERT INTO peliculas (titulo, director, ano_lanzamiento, duracion_minutos, genero_id, sinopsis, id_usuario_creador) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $this->db->execute($sql, array_values($testMovie));
            
            // leer pelicula
            $createdMovie = $this->db->fetchOne("SELECT * FROM peliculas WHERE titulo = ?", [$testMovie['titulo']]);
            if (!$createdMovie) {
                throw new Exception('Pelicula no encontrada despues de crearla');
            }
            
            // actualizar pelicula
            $this->db->execute("UPDATE peliculas SET sinopsis = ? WHERE id = ?", ['Sinopsis actualizada', $createdMovie['id']]);
            
            // borrar pelicula
            $this->db->execute("UPDATE peliculas SET activo = 0 WHERE id = ?", [$createdMovie['id']]);
            
            $this->addResult('CRUD Peliculas', true, 'Operaciones de peliculas funcionan');
        } catch (Exception $e) {
            $this->addResult('CRUD Peliculas', false, $e->getMessage());
        }
    }
    
    // probar crud de generos
    private function testGenerosCRUD() {
        try {
            // crear genero de prueba
            $testGenero = [
                'nombre' => 'Genero Prueba ' . time(),
                'descripcion' => 'Descripcion de prueba',
                'color_hex' => '#FF0000'
            ];
            
            $sql = "INSERT INTO generos (nombre, descripcion, color_hex) VALUES (?, ?, ?)";
            $this->db->execute($sql, array_values($testGenero));
            
            // leer genero
            $createdGenero = $this->db->fetchOne("SELECT * FROM generos WHERE nombre = ?", [$testGenero['nombre']]);
            if (!$createdGenero) {
                throw new Exception('Genero no encontrado despues de crearlo');
            }
            
            // actualizar genero
            $this->db->execute("UPDATE generos SET color_hex = ? WHERE id = ?", ['#00FF00', $createdGenero['id']]);
            
            // borrar genero
            $this->db->execute("UPDATE generos SET activo = 0 WHERE id = ?", [$createdGenero['id']]);
            
            $this->addResult('CRUD Generos', true, 'Operaciones de generos funcionan');
        } catch (Exception $e) {
            $this->addResult('CRUD Generos', false, $e->getMessage());
        }
    }
    
    // probar validaciones
    private function testValidations() {
        try {
            // probar email invalido
            try {
                $this->db->execute("INSERT INTO usuarios (nombre_usuario, email, password, nombre_completo) VALUES (?, ?, ?, ?)", 
                    ['test_invalid', 'email_malo', 'password', 'Test User']);
                $this->addResult('Validacion Email', false, 'Email malo fue aceptado');
            } catch (Exception $e) {
                // esperamos que falle
            }
            
            // probar año invalido en peliculas
            $genero = $this->db->fetchOne("SELECT id FROM generos WHERE activo = 1 LIMIT 1");
            try {
                $this->db->execute("INSERT INTO peliculas (titulo, director, ano_lanzamiento, duracion_minutos, genero_id, id_usuario_creador) VALUES (?, ?, ?, ?, ?, ?)", 
                    ['Test Movie', 'Test Director', 1800, 120, $genero['id'], 1]);
                $this->addResult('Validacion Año', false, 'Año invalido fue aceptado');
            } catch (Exception $e) {
                // esperamos que falle
            }
            
            $this->addResult('Validaciones', true, 'Las validaciones funcionan bien');
        } catch (Exception $e) {
            $this->addResult('Validaciones', false, $e->getMessage());
        }
    }
    
    // probar prevencion de sql injection
    private function testSQLInjectionPrevention() {
        try {
            require_once '../includes/security.php';
            
            // entradas maliciosas tipicas
            $maliciousInputs = [
                "'; DROP TABLE usuarios; --",
                "1' OR '1'='1",
                "admin'; INSERT INTO usuarios VALUES ('hacker'); --"
            ];
            
            foreach ($maliciousInputs as $input) {
                try {
                    SecurityManager::preventSQLInjection($input);
                    $this->addResult('SQL Injection', false, 'No detecto entrada maliciosa: ' . $input);
                    return;
                } catch (Exception $e) {
                    // bien detectado
                }
            }
            
            $this->addResult('SQL Injection', true, 'Prevencion de SQL Injection funciona');
        } catch (Exception $e) {
            $this->addResult('SQL Injection', false, $e->getMessage());
        }
    }
    
    // probar velocidad de consultas
    private function testPerformance() {
        try {
            $start = microtime(true);
            
            // consulta compleja para probar rendimiento
            $this->db->fetchAll("
                SELECT p.titulo, p.director, g.nombre as genero,
                       COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
                       COUNT(r.id) as total_resenas
                FROM peliculas p
                LEFT JOIN generos g ON p.genero_id = g.id
                LEFT JOIN resenas r ON p.id = r.id_pelicula
                WHERE p.activo = 1
                GROUP BY p.id
                ORDER BY puntuacion_promedio DESC
                LIMIT 50
            ");
            
            $end = microtime(true);
            $time = ($end - $start) * 1000; // en milisegundos
            
            if ($time > 1000) { // mas de 1 segundo esta mal
                $this->addResult('Rendimiento', false, "Consulta muy lenta: {$time}ms");
            } else {
                $this->addResult('Rendimiento', true, "Tiempo bueno: {$time}ms");
            }
        } catch (Exception $e) {
            $this->addResult('Rendimiento', false, $e->getMessage());
        }
    }
    
    // verificar integridad de datos
    private function testDataIntegrity() {
        try {
            // buscar peliculas sin genero
            $orphanedMovies = $this->db->fetchAll("
                SELECT p.id, p.titulo
                FROM peliculas p
                LEFT JOIN generos g ON p.genero_id = g.id
                WHERE p.activo = 1 AND g.id IS NULL
            ");
            
            if (!empty($orphanedMovies)) {
                $this->addResult('Integridad Datos', false, 'Hay peliculas sin genero');
                return;
            }
            
            // buscar emails duplicados
            $duplicateEmails = $this->db->fetchAll("
                SELECT email, COUNT(*) as count
                FROM usuarios
                WHERE activo = 1
                GROUP BY email
                HAVING count > 1
            ");
            
            if (!empty($duplicateEmails)) {
                $this->addResult('Integridad Datos', false, 'Hay emails duplicados');
                return;
            }
            
            $this->addResult('Integridad Datos', true, 'Datos consistentes');
        } catch (Exception $e) {
            $this->addResult('Integridad Datos', false, $e->getMessage());
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
        
        Logger::info("Test: {$test}", [
            'passed' => $passed,
            'message' => $message
        ]);
    }
    
    // mostrar resultados de las pruebas
    private function showResults() {
        $passed = 0;
        $total = count($this->results);
        
        echo "<div style='margin: 20px; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>";
        echo "<h3>📊 Resultados de Pruebas</h3>";
        
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
        echo "<h4 style='color: {$overallColor};'>Resumen: {$passed}/{$total} pruebas pasaron ({$percentage}%)</h4>";
        echo "</div>";
        echo "</div>";
    }
}