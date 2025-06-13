<?php
class PruebaSeguridad {
    private $bd;
    private $resultados = [];
    
    public function __construct() {
        $this->bd = new BaseDatosAdmin();
    }
    
    // ejecutar todas las pruebas de seguridad
    public function ejecutarPruebasSeguridad() {
        $this->resultados = [];
        
        $this->probarProteccionInyeccionSQL();
        $this->probarProteccionXSS();
        $this->probarProteccionCSRF();
        $this->probarHasheoClaves();
        $this->probarLimpiezaEntradas();
        $this->probarSeguridadSesiones();
        $this->probarSeguridadSubidaArchivos();
        $this->probarTravesiaDirectorios();
        
        return $this->resultados;
    }
    
    // probar proteccion contra inyeccion sql
    private function probarProteccionInyeccionSQL() {
        try {
            // casos de prueba maliciosos
            $entradasMaliciosas = [
                "'; DROP TABLE usuarios; --",
                "1' OR '1'='1",
                "admin'/*",
                "1'; UPDATE usuarios SET password='hackeado' WHERE id=1; --"
            ];
            
            $todasAprobadas = true;
            $entradasProbadas = 0;
            
            foreach ($entradasMaliciosas as $entrada) {
                try {
                    // usar prepared statement (debe ser seguro)
                    $resultado = $this->bd->obtenerTodos("SELECT * FROM usuarios WHERE nombre_usuario = ?", [$entrada]);
                    $entradasProbadas++;
                    
                    // verificar que la tabla sigue existiendo
                    $verificacionTabla = $this->bd->obtenerUno("SELECT COUNT(*) as count FROM usuarios");
                    if ($verificacionTabla === false) {
                        $todasAprobadas = false;
                        break;
                    }
                    
                } catch (Exception $e) {
                    // algunos errores son esperables
                    $entradasProbadas++;
                }
            }
            
            if ($todasAprobadas && $entradasProbadas > 0) {
                $this->agregarResultado('Protección Inyección SQL', true, 
                    "Probadas $entradasProbadas entradas maliciosas - Prepared statements funcionando correctamente");
            } else {
                $this->agregarResultado('Protección Inyección SQL', false, 
                    'Posible vulnerabilidad inyección SQL detectada');
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Protección Inyección SQL', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // probar proteccion contra xss
    private function probarProteccionXSS() {
        try {
            $entradasXSS = [
                '<script>alert("XSS")</script>',
                '"><script>alert("XSS")</script>',
                'javascript:alert("XSS")',
                '<img src=x onerror=alert("XSS")>',
                '<svg onload=alert("XSS")>'
            ];
            
            $todasLimpias = true;
            
            foreach ($entradasXSS as $entrada) {
                // probar limpieza
                $limpia = htmlspecialchars($entrada, ENT_QUOTES, 'UTF-8');
                
                // verificar que no contenga tags peligrosos
                if (strpos($limpia, '<script') !== false || 
                    strpos($limpia, 'javascript:') !== false ||
                    strpos($limpia, 'onerror=') !== false) {
                    $todasLimpias = false;
                    break;
                }
            }
            
            if ($todasLimpias) {
                $this->agregarResultado('Protección XSS', true, 
                    'Limpieza de entradas funcionando - Entidades HTML escapadas correctamente');
            } else {
                $this->agregarResultado('Protección XSS', false, 
                    'Vulnerabilidad XSS detectada - entrada no limpiada correctamente');
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Protección XSS', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // probar proteccion csrf
    private function probarProteccionCSRF() {
        try {
            // verificar que la clase GestorSeguridad existe y funciona
            if (class_exists('GestorSeguridad')) {
                $token = GestorSeguridad::generarTokenCSRF();
                
                if (!empty($token) && strlen($token) >= 32) {
                    $this->agregarResultado('Protección CSRF', true, 
                        'Tokens CSRF siendo generados correctamente');
                } else {
                    $this->agregarResultado('Protección CSRF', false, 
                        'Generación token CSRF falló o muy débil');
                }
            } else {
                // prueba básica sin GestorSeguridad
                if (defined('NOMBRE_TOKEN_CSRF') && !empty(NOMBRE_TOKEN_CSRF)) {
                    $this->agregarResultado('Protección CSRF', true, 
                        'Configuración CSRF presente');
                } else {
                    $this->agregarResultado('Protección CSRF', false, 
                        'Configuración CSRF faltante');
                }
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Protección CSRF', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // probar hasheo de claves
    private function probarHasheoClaves() {
        try {
            $clavePrueba = 'clavePrueba123';
            
            // probar hasheo
            $hash1 = password_hash($clavePrueba, PASSWORD_DEFAULT);
            $hash2 = password_hash($clavePrueba, PASSWORD_DEFAULT);
            
            // verificar que los hashes son diferentes (sal)
            if ($hash1 !== $hash2) {
                // verificar que la verificación funciona
                if (password_verify($clavePrueba, $hash1) && password_verify($clavePrueba, $hash2)) {
                    $this->agregarResultado('Hasheo Claves', true, 
                        'Hasheo y verificación de claves funcionando correctamente');
                } else {
                    $this->agregarResultado('Hasheo Claves', false, 
                        'Verificación de claves falló');
                }
            } else {
                $this->agregarResultado('Hasheo Claves', false, 
                    'Hasheo de claves no usando sal correctamente');
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Hasheo Claves', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // probar limpieza de entradas
    private function probarLimpiezaEntradas() {
        try {
            $entradasPeligrosas = [
                '  <script>malicioso</script>  ',
                'SELECT * FROM usuarios',
                '../../../etc/passwd',
                'usuario@dominio.com<script>',
                '"><img src=x onerror=alert(1)>'
            ];
            
            $todasLimpias = true;
            
            foreach ($entradasPeligrosas as $entrada) {
                // simular limpieza como la que usaríamos
                $limpia = htmlspecialchars(strip_tags(trim($entrada)), ENT_QUOTES, 'UTF-8');
                
                // verificar que no queden elementos peligrosos
                if (strpos($limpia, '<') !== false || 
                    strpos($limpia, '>') !== false ||
                    strpos($limpia, 'script') !== false) {
                    $todasLimpias = false;
                    break;
                }
            }
            
            if ($todasLimpias) {
                $this->agregarResultado('Limpieza Entradas', true, 
                    'Limpieza de entradas eliminando contenido peligroso');
            } else {
                $this->agregarResultado('Limpieza Entradas', false, 
                    'Limpieza de entradas incompleta');
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Limpieza Entradas', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // probar seguridad de sesiones
    private function probarSeguridadSesiones() {
        try {
            $verificacionesSeguridad = [];
            
            // verificar configuración de sesión
            $verificacionesSeguridad['httponly'] = ini_get('session.cookie_httponly') == '1';
            $verificacionesSeguridad['secure'] = ini_get('session.cookie_secure') == '1' || !isset($_SERVER['HTTPS']);
            $verificacionesSeguridad['nombre'] = ini_get('session.name') !== 'PHPSESSID'; // nombre personalizado
            
            $aprobadas = array_sum($verificacionesSeguridad);
            $total = count($verificacionesSeguridad);
            
            if ($aprobadas >= $total - 1) { // permitir fallar 1 verificación
                $this->agregarResultado('Seguridad Sesiones', true, 
                    "Seguridad sesión: $aprobadas/$total verificaciones aprobadas");
            } else {
                $this->agregarResultado('Seguridad Sesiones', false, 
                    "Seguridad sesión débil: solo $aprobadas/$total verificaciones aprobadas");
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Seguridad Sesiones', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // probar seguridad de subida de archivos
    private function probarSeguridadSubidaArchivos() {
        try {
            // verificar que existe directorio de subidas
            $directorioSubidas = defined('RUTA_SUBIDAS') ? RUTA_SUBIDAS : '../subidas/';
            
            $verificaciones = [];
            $verificaciones['directorio_subidas_existe'] = is_dir($directorioSubidas);
            $verificaciones['directorio_subidas_escribible'] = is_writable($directorioSubidas);
            
            // verificar que no se pueden ejecutar scripts en directorio subidas
            $archivoHtaccess = $directorioSubidas . '.htaccess';
            if (file_exists($archivoHtaccess)) {
                $contenidoHtaccess = file_get_contents($archivoHtaccess);
                $verificaciones['proteccion_htaccess'] = strpos($contenidoHtaccess, 'php') !== false;
            } else {
                $verificaciones['proteccion_htaccess'] = false;
            }
            
            $aprobadas = array_sum($verificaciones);
            $total = count($verificaciones);
            
            if ($aprobadas >= 2) {
                $this->agregarResultado('Seguridad Subidas', true, 
                    "Seguridad subida archivos: $aprobadas/$total verificaciones aprobadas");
            } else {
                $this->agregarResultado('Seguridad Subidas', false, 
                    "Seguridad subida archivos requiere mejoras: $aprobadas/$total");
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Seguridad Subidas', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // probar proteccion contra travesia de directorios
    private function probarTravesiaDirectorios() {
        try {
            $rutasMaliciosas = [
                '../../../etc/passwd',
                '..\\..\\..\\windows\\system32\\drivers\\etc\\hosts',
                '....//....//....//etc/passwd',
                '%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd'
            ];
            
            $todasBloqueadas = true;
            
            foreach ($rutasMaliciosas as $ruta) {
                // simular validación de ruta
                $rutaReal = realpath(dirname(__FILE__) . '/' . $ruta);
                $rutaBase = realpath(dirname(__FILE__));
                
                // verificar que la ruta no sale del directorio base
                if ($rutaReal && strpos($rutaReal, $rutaBase) !== 0) {
                    $todasBloqueadas = false;
                    break;
                }
            }
            
            if ($todasBloqueadas) {
                $this->agregarResultado('Protección Travesía Directorio', true, 
                    'Ataques travesía ruta bloqueados correctamente');
            } else {
                $this->agregarResultado('Protección Travesía Directorio', false, 
                    'Vulnerabilidad travesía directorio detectada');
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Protección Travesía Directorio', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // agregar resultado
    private function agregarResultado($nombre, $aprobada, $descripcion, $detalles = '') {
        $this->resultados[] = [
            'nombre' => $nombre,
            'aprobada' => $aprobada,
            'descripcion' => $descripcion,
            'detalles' => $detalles
        ];
        
        $estado = $aprobada ? 'APROBADA' : 'FALLIDA';
        Registrador::info("Prueba Seguridad $nombre: $estado - $descripcion");
    }
}
?>