<?php
// definir rutas antes que nada
define('RUTA_ADMIN', dirname(__DIR__));
define('RUTA_LOGS', RUTA_ADMIN . '/logs/');
define('RUTA_SUBIDAS', RUTA_ADMIN . '/subidas/');
define('RUTA_PDF_TEMP', RUTA_ADMIN . '/temp/pdf/');

class InstaladorAdmin {
    private $errores = [];
    private $exitos = [];
    private $avisos = [];
    private $bd = null;
    
    public function __construct() {
        // crear directorios necesarios
        $this->crearDirectorios();
    }
    
    // crear directorios necesarios
    private function crearDirectorios() {
        $directorios = [
            RUTA_LOGS,
            RUTA_SUBIDAS,
            RUTA_PDF_TEMP,
            RUTA_ADMIN . '/temp/'
        ];
        
        foreach ($directorios as $directorio) {
            if (!is_dir($directorio)) {
                if (@mkdir($directorio, 0755, true)) {
                    $this->exitos[] = "✅ Directorio creado: " . basename($directorio);
                } else {
                    $this->errores[] = "❌ No se pudo crear directorio: " . basename($directorio);
                }
            } else {
                $this->exitos[] = "✅ Directorio existe: " . basename($directorio);
            }
            
            // verificar permisos de escritura
            if (is_dir($directorio) && !is_writable($directorio)) {
                $this->avisos[] = "⚠️ Directorio sin permisos de escritura: " . basename($directorio);
            }
        }
    }
    
    // verificar requisitos del sistema
    public function verificarRequisitos() {
        // verificar version php
        if (version_compare(PHP_VERSION, '7.4.0') >= 0) {
            $this->exitos[] = "✅ PHP " . PHP_VERSION . " (compatible)";
        } else {
            $this->errores[] = "❌ Versión PHP muy antigua: " . PHP_VERSION . " (requiere 7.4+)";
        }
        
        // verificar extensiones requeridas
        $extensiones_requeridas = ['pdo', 'pdo_mysql', 'json', 'session', 'mbstring'];
        
        foreach ($extensiones_requeridas as $ext) {
            if (extension_loaded($ext)) {
                $this->exitos[] = "✅ Extensión $ext disponible";
            } else {
                $this->errores[] = "❌ Extensión $ext faltante";
            }
        }
        
        // verificar funciones criticas
        $funciones_requeridas = ['password_hash', 'password_verify', 'random_bytes', 'hash_equals'];
        
        foreach ($funciones_requeridas as $func) {
            if (function_exists($func)) {
                $this->exitos[] = "✅ Función $func disponible";
            } else {
                $this->errores[] = "❌ Función $func no disponible";
            }
        }
        
        // verificar permisos de escritura en directorios clave
        $directorios_escritura = [RUTA_ADMIN, RUTA_LOGS, RUTA_SUBIDAS];
        
        foreach ($directorios_escritura as $directorio) {
            if (is_dir($directorio) && is_writable($directorio)) {
                $this->exitos[] = "✅ Permisos escritura OK: " . basename($directorio);
            } else {
                $this->errores[] = "❌ Sin permisos escritura: " . basename($directorio);
            }
        }
    }
    
    // probar conexion bd
    public function probarBaseDatos() {
        try {
            require_once RUTA_ADMIN . '/config/basedatos.php';
            
            $this->bd = new BaseDatosAdmin();
            $resultado = $this->bd->obtenerUno("SELECT 1 as prueba");
            
            if ($resultado && $resultado['prueba'] == 1) {
                $this->exitos[] = "✅ Conexión BD establecida correctamente";
                return true;
            } else {
                $this->errores[] = "❌ Conexión BD falló - respuesta inválida";
                return false;
            }
            
        } catch (Exception $e) {
            $this->errores[] = "❌ Error BD: " . $e->getMessage();
            return false;
        }
    }
    
    // verificar estructura de bd
    public function verificarEstructuraBaseDatos() {
        if (!$this->bd) {
            $this->errores[] = "❌ No hay conexión BD para verificar estructura";
            return false;
        }
        
        $tablas_requeridas = ['usuarios', 'peliculas', 'generos', 'resenas'];
        
        foreach ($tablas_requeridas as $tabla) {
            try {
                $resultado = $this->bd->obtenerUno("SHOW TABLES LIKE '$tabla'");
                if ($resultado) {
                    $this->exitos[] = "✅ Tabla '$tabla' existe";
                } else {
                    $this->errores[] = "❌ Tabla '$tabla' faltante";
                }
            } catch (Exception $e) {
                $this->errores[] = "❌ Error verificando tabla '$tabla': " . $e->getMessage();
            }
        }
        
        // verificar si hay datos minimos
        try {
            $conteo_usuarios = $this->bd->obtenerUno("SELECT COUNT(*) as count FROM usuarios")['count'];
            $conteo_generos = $this->bd->obtenerUno("SELECT COUNT(*) as count FROM generos")['count'];
            
            if ($conteo_usuarios > 0) {
                $this->exitos[] = "✅ BD tiene $conteo_usuarios usuarios";
            } else {
                $this->avisos[] = "⚠️ BD sin usuarios (crear usuario admin)";
            }
            
            if ($conteo_generos > 0) {
                $this->exitos[] = "✅ BD tiene $conteo_generos géneros";
            } else {
                $this->avisos[] = "⚠️ BD sin géneros";
            }
            
        } catch (Exception $e) {
            $this->avisos[] = "⚠️ Error verificando datos: " . $e->getMessage();
        }
    }
    
    // crear usuario administrador
    public function crearUsuarioAdmin() {
        if (!$this->bd) {
            $this->errores[] = "❌ No hay conexión BD para crear admin";
            return false;
        }
        
        try {
            // verificar si ya existe tabla administradores
            $resultado = $this->bd->obtenerUno("SHOW TABLES LIKE 'administradores'");
            
            if (!$resultado) {
                // crear tabla administradores
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
                
                $this->bd->ejecutar($sql);
                $this->exitos[] = "✅ Tabla administradores creada";
            }
            
            // verificar si ya existe admin
            $admin = $this->bd->obtenerUno("SELECT * FROM administradores WHERE usuario = 'admin'");
            
            if (!$admin) {
                // crear usuario admin por defecto
                $claveDefecto = 'admin123';
                $claveHasheada = password_hash($claveDefecto, PASSWORD_DEFAULT);
                
                $sql = "INSERT INTO administradores (usuario, password, nombre_completo, email, nivel_acceso) 
                        VALUES (?, ?, ?, ?, ?)";
                
                $this->bd->ejecutar($sql, [
                    'admin',
                    $claveHasheada,
                    'Administrador',
                    'admin@cinefan.local',
                    'admin'
                ]);
                
                $this->exitos[] = "✅ Usuario admin creado";
                $this->avisos[] = "⚠️ Clave por defecto: $claveDefecto (¡CAMBIAR!)";
                
                return true;
            } else {
                $this->exitos[] = "✅ Usuario admin ya existe";
                return true;
            }
            
        } catch (Exception $e) {
            $this->errores[] = "❌ Error creando admin: " . $e->getMessage();
            return false;
        }
    }
    
    // crear archivos de configuracion adicionales
    public function crearArchivosConfiguracion() {
        // crear .htaccess para subidas
        $contenido_htaccess = "# Proteger directorio subidas\n";
        $contenido_htaccess .= "Options -ExecCGI\n";
        $contenido_htaccess .= "AddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n";
        $contenido_htaccess .= "<Files *.php>\n";
        $contenido_htaccess .= "    Deny from all\n";
        $contenido_htaccess .= "</Files>\n";
        
        $archivo_htaccess = RUTA_SUBIDAS . '.htaccess';
        if (!file_exists($archivo_htaccess)) {
            if (file_put_contents($archivo_htaccess, $contenido_htaccess)) {
                $this->exitos[] = "✅ Archivo .htaccess creado para subidas";
            } else {
                $this->avisos[] = "⚠️ No se pudo crear .htaccess para subidas";
            }
        }
        
        // crear archivo index.php en directorios sensibles
        $contenido_index = "<?php\n// Acceso denegado\nheader('HTTP/1.1 403 Forbidden');\nexit('Acceso denegado');\n?>";
        
        $directorios_protegidos = [RUTA_LOGS, RUTA_SUBIDAS, RUTA_PDF_TEMP];
        
        foreach ($directorios_protegidos as $directorio) {
            $archivo_index = $directorio . 'index.php';
            if (!file_exists($archivo_index)) {
                if (file_put_contents($archivo_index, $contenido_index)) {
                    $this->exitos[] = "✅ Protección index.php creada en " . basename($directorio);
                } else {
                    $this->avisos[] = "⚠️ No se pudo crear index.php en " . basename($directorio);
                }
            }
        }
    }
    
    // ejecutar instalacion completa
    public function instalar() {
        $this->verificarRequisitos();
        
        if ($this->probarBaseDatos()) {
            $this->verificarEstructuraBaseDatos();
            $this->crearUsuarioAdmin();
        }
        
        $this->crearArchivosConfiguracion();
        
        // escribir log de instalacion
        $contenido_log = "Instalación CineFan Admin - " . date('Y-m-d H:i:s') . "\n";
        $contenido_log .= "Errores: " . count($this->errores) . "\n";
        $contenido_log .= "Éxitos: " . count($this->exitos) . "\n";
        $contenido_log .= "Avisos: " . count($this->avisos) . "\n";
        
        if (is_dir(RUTA_LOGS)) {
            file_put_contents(RUTA_LOGS . 'instalacion.log', $contenido_log);
        }
        
        return empty($this->errores);
    }
    
    // obtener resultados
    public function obtenerResultados() {
        return [
            'errores' => $this->errores,
            'exitos' => $this->exitos,
            'avisos' => $this->avisos,
            'exito_general' => empty($this->errores)
        ];
    }
}

// ejecutar instalacion si se solicita
if (isset($_GET['instalar']) && $_GET['instalar'] == '1') {
    $instalador = new InstaladorAdmin();
    $exito = $instalador->instalar();
    $resultados = $instalador->obtenerResultados();
    
    // mostrar resultados y redirigir
    echo obtenerHTMLInstalacion($resultados);
    
    if ($exito) {
        echo "<script>setTimeout(function(){ window.location.href = '../index.php'; }, 3000);</script>";
    }
    
} else {
    // mostrar formulario de instalacion
    echo obtenerHTMLFormularioInstalacion();
}

// html del formulario de instalacion
function obtenerHTMLFormularioInstalacion() {
    return '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación CineFan Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .tarjeta-instalacion { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .cabecera-instalacion { background: linear-gradient(45deg, #667eea, #764ba2); color: white; border-radius: 15px 15px 0 0; }
    </style>
</head>
<body class="d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="tarjeta-instalacion">
                    <div class="cabecera-instalacion p-4 text-center">
                        <h2><i class="fas fa-film me-2"></i>CineFan Panel Admin</h2>
                        <p class="mb-0">Instalación y Configuración</p>
                    </div>
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <h4>Bienvenido al Instalador</h4>
                            <p class="text-muted">Este asistente configurará el panel administrativo de CineFan</p>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Qué se va a hacer:</h6>
                            <ul class="mb-0">
                                <li>Verificar requisitos del sistema</li>
                                <li>Comprobar conexión a la base de datos</li>
                                <li>Crear directorios necesarios</li>
                                <li>Configurar usuario administrador</li>
                                <li>Aplicar configuraciones de seguridad</li>
                            </ul>
                        </div>
                        
                        <div class="d-grid">
                            <a href="?instalar=1" class="btn btn-primary btn-lg">
                                <i class="fas fa-play me-2"></i>Comenzar Instalación
                            </a>
                        </div>
                        
                        <hr>
                        <p class="text-center text-muted">
                            <small>Desarrollado por Juan Carlos y Angel Hernandez - DAM2</small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
}

// html de resultados de instalacion
function obtenerHTMLInstalacion($resultados) {
    $clase_alerta = $resultados['exito_general'] ? 'alert-success' : 'alert-danger';
    $icono = $resultados['exito_general'] ? 'fa-check-circle' : 'fa-times-circle';
    $titulo = $resultados['exito_general'] ? 'Instalación Completada' : 'Instalación Incompleta';
    
    $html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado Instalación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .tarjeta-resultado { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    </style>
</head>
<body class="d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="tarjeta-resultado">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <div class="' . $clase_alerta . '" role="alert">
                                <h3><i class="fas ' . $icono . '"></i> ' . $titulo . '</h3>
                            </div>
                        </div>
    ';
    
    // mostrar exitos
    if (!empty($resultados['exitos'])) {
        $html .= '<div class="alert alert-success"><h6>✅ Completado exitosamente:</h6><ul class="mb-0">';
        foreach ($resultados['exitos'] as $exito) {
            $html .= '<li>' . htmlspecialchars($exito) . '</li>';
        }
        $html .= '</ul></div>';
    }
    
    // mostrar avisos
    if (!empty($resultados['avisos'])) {
        $html .= '<div class="alert alert-warning"><h6>⚠️ Avisos:</h6><ul class="mb-0">';
        foreach ($resultados['avisos'] as $aviso) {
            $html .= '<li>' . htmlspecialchars($aviso) . '</li>';
        }
        $html .= '</ul></div>';
    }
    
    // mostrar errores
    if (!empty($resultados['errores'])) {
        $html .= '<div class="alert alert-danger"><h6>❌ Errores encontrados:</h6><ul class="mb-0">';
        foreach ($resultados['errores'] as $error) {
            $html .= '<li>' . htmlspecialchars($error) . '</li>';
        }
        $html .= '</ul></div>';
    }
    
    if ($resultados['exito_general']) {
        $html .= '
                        <div class="text-center">
                            <div class="alert alert-info">
                                <p><strong>🎉 Instalación completada con éxito!</strong></p>
                                <p>Serás redirigido al panel de administración en 3 segundos...</p>
                                <a href="../index.php" class="btn btn-primary">Ir al Panel Admin</a>
                            </div>
                        </div>';
    } else {
        $html .= '
                        <div class="text-center">
                            <div class="alert alert-warning">
                                <p><strong>⚠️ Instalación incompleta</strong></p>
                                <p>Corrige los errores y vuelve a ejecutar el instalador</p>
                                <a href="?" class="btn btn-warning">Reintentar</a>
                            </div>
                        </div>';
    }
    
    $html .= '
                        <hr>
                        <p class="text-center text-muted">
                            <small>CineFan Panel Admin - Juan Carlos y Angel Hernandez</small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}
?>