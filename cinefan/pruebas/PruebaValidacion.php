<?php
class PruebaValidacion {
    private $resultados = [];
    
    public function __construct() {
    }
    
    // ejecutar todas las pruebas de validacion
    public function ejecutarPruebasValidacion() {
        $this->resultados = [];
        
        $this->probarValidacionEmail();
        $this->probarValidacionClave();
        $this->probarValidacionCamposRequeridos();
        $this->probarValidacionNumerica();
        $this->probarValidacionFecha();
        $this->probarValidacionLongitudCadena();
        $this->probarValidacionSubidaArchivo();
        $this->probarValidacionCaracteresEspeciales();
        
        return $this->resultados;
    }
    
    // probar validacion de emails
    private function probarValidacionEmail() {
        $emailsValidos = [
            'prueba@ejemplo.com',
            'usuario.nombre@dominio.co.uk',
            'admin@cinefan.local',
            'prueba123@dominio-prueba.com'
        ];
        
        $emailsInvalidos = [
            'email-invalido',
            '@dominio.com',
            'usuario@',
            'usuario..nombre@dominio.com',
            'usuario@dominio',
            ''
        ];
        
        $validosAprobados = 0;
        $invalidosRechazados = 0;
        
        // probar emails válidos
        foreach ($emailsValidos as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validosAprobados++;
            }
        }
        
        // probar emails inválidos
        foreach ($emailsInvalidos as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalidosRechazados++;
            }
        }
        
        if ($validosAprobados === count($emailsValidos) && $invalidosRechazados === count($emailsInvalidos)) {
            $this->agregarResultado('Validación Email', true, 
                "Validación email funcionando correctamente. Válidos: $validosAprobados, Inválidos rechazados: $invalidosRechazados");
        } else {
            $this->agregarResultado('Validación Email', false, 
                "Validación email falló. Válidos aprobados: $validosAprobados/" . count($emailsValidos) . 
                ", Inválidos rechazados: $invalidosRechazados/" . count($emailsInvalidos));
        }
    }
    
    // probar validacion de claves
    private function probarValidacionClave() {
        $clavesValidas = [
            'clave123',
            'MiClaveSecreta1',
            'Admin2024!',
            'clavePrueba456'
        ];
        
        $clavesInvalidas = [
            '123',           // muy corta
            '',              // vacía
            'abc',           // muy corta
            '12345'          // solo números y corta
        ];
        
        $longitudMinima = defined('LONGITUD_MIN_CLAVE') ? LONGITUD_MIN_CLAVE : 6;
        
        $validasAprobadas = 0;
        $invalidasRechazadas = 0;
        
        // probar claves válidas
        foreach ($clavesValidas as $clave) {
            if (strlen($clave) >= $longitudMinima) {
                $validasAprobadas++;
            }
        }
        
        // probar claves inválidas
        foreach ($clavesInvalidas as $clave) {
            if (strlen($clave) < $longitudMinima) {
                $invalidasRechazadas++;
            }
        }
        
        if ($validasAprobadas === count($clavesValidas) && $invalidasRechazadas === count($clavesInvalidas)) {
            $this->agregarResultado('Validación Clave', true, 
                "Validación clave funcionando. Longitud mínima: $longitudMinima caracteres");
        } else {
            $this->agregarResultado('Validación Clave', false, 
                "Problemas validación clave detectados");
        }
    }
    
    // probar validacion de campos requeridos
    private function probarValidacionCamposRequeridos() {
        $casosPrueba = [
            ['campo' => 'Juan Carlos', 'requerido' => true, 'debe_aprobar' => true],
            ['campo' => '', 'requerido' => true, 'debe_aprobar' => false],
            ['campo' => '   ', 'requerido' => true, 'debe_aprobar' => false],
            ['campo' => null, 'requerido' => true, 'debe_aprobar' => false],
            ['campo' => 'Valor opcional', 'requerido' => false, 'debe_aprobar' => true],
            ['campo' => '', 'requerido' => false, 'debe_aprobar' => true]
        ];
        
        $aprobadas = 0;
        $total = count($casosPrueba);
        
        foreach ($casosPrueba as $prueba) {
            $esValido = $this->validarRequerido($prueba['campo'], $prueba['requerido']);
            
            if ($esValido === $prueba['debe_aprobar']) {
                $aprobadas++;
            }
        }
        
        if ($aprobadas === $total) {
            $this->agregarResultado('Validación Campos Requeridos', true, 
                "Validación campo requerido funcionando correctamente");
        } else {
            $this->agregarResultado('Validación Campos Requeridos', false, 
                "Validación campo requerido falló: $aprobadas/$total pruebas aprobadas");
        }
    }
    
    // probar validacion numerica
    private function probarValidacionNumerica() {
        $pruebasNumericas = [
            ['valor' => '123', 'min' => 1, 'max' => 1000, 'debe_aprobar' => true],
            ['valor' => '2024', 'min' => 1895, 'max' => 2030, 'debe_aprobar' => true],
            ['valor' => '0', 'min' => 1, 'max' => 100, 'debe_aprobar' => false],
            ['valor' => '2050', 'min' => 1895, 'max' => 2030, 'debe_aprobar' => false],
            ['valor' => 'abc', 'min' => 1, 'max' => 100, 'debe_aprobar' => false],
            ['valor' => '', 'min' => 1, 'max' => 100, 'debe_aprobar' => false]
        ];
        
        $aprobadas = 0;
        $total = count($pruebasNumericas);
        
        foreach ($pruebasNumericas as $prueba) {
            $esValido = $this->validarRangoNumerico($prueba['valor'], $prueba['min'], $prueba['max']);
            
            if ($esValido === $prueba['debe_aprobar']) {
                $aprobadas++;
            }
        }
        
        if ($aprobadas === $total) {
            $this->agregarResultado('Validación Numérica', true, 
                "Validación rango numérico funcionando correctamente");
        } else {
            $this->agregarResultado('Validación Numérica', false, 
                "Validación numérica falló: $aprobadas/$total pruebas aprobadas");
        }
    }
    
    // probar validacion de fechas
    private function probarValidacionFecha() {
        $pruebasFecha = [
            ['fecha' => '2024-01-15', 'debe_aprobar' => true],
            ['fecha' => '2024-12-31', 'debe_aprobar' => true],
            ['fecha' => '2024-13-01', 'debe_aprobar' => false], // mes inválido
            ['fecha' => '2024-02-30', 'debe_aprobar' => false], // día inválido
            ['fecha' => 'no-es-fecha', 'debe_aprobar' => false],
            ['fecha' => '', 'debe_aprobar' => false]
        ];
        
        $aprobadas = 0;
        $total = count($pruebasFecha);
        
        foreach ($pruebasFecha as $prueba) {
            $esValido = $this->validarFecha($prueba['fecha']);
            
            if ($esValido === $prueba['debe_aprobar']) {
                $aprobadas++;
            }
        }
        
        if ($aprobadas === $total) {
            $this->agregarResultado('Validación Fecha', true, 
                "Validación fecha funcionando correctamente");
        } else {
            $this->agregarResultado('Validación Fecha', false, 
                "Validación fecha falló: $aprobadas/$total pruebas aprobadas");
        }
    }
    
    // probar validacion de longitud de cadenas
    private function probarValidacionLongitudCadena() {
        $pruebasLongitud = [
            ['cadena' => 'Título normal', 'min' => 5, 'max' => 50, 'debe_aprobar' => true],
            ['cadena' => 'Ok', 'min' => 5, 'max' => 50, 'debe_aprobar' => false], // muy corto
            ['cadena' => str_repeat('a', 100), 'min' => 5, 'max' => 50, 'debe_aprobar' => false], // muy largo
            ['cadena' => '', 'min' => 1, 'max' => 10, 'debe_aprobar' => false], // vacío
            ['cadena' => 'Perfecto', 'min' => 1, 'max' => 20, 'debe_aprobar' => true]
        ];
        
        $aprobadas = 0;
        $total = count($pruebasLongitud);
        
        foreach ($pruebasLongitud as $prueba) {
            $esValido = $this->validarLongitudCadena($prueba['cadena'], $prueba['min'], $prueba['max']);
            
            if ($esValido === $prueba['debe_aprobar']) {
                $aprobadas++;
            }
        }
        
        if ($aprobadas === $total) {
            $this->agregarResultado('Validación Longitud Cadena', true, 
                "Validación longitud cadena funcionando correctamente");
        } else {
            $this->agregarResultado('Validación Longitud Cadena', false, 
                "Validación longitud cadena falló: $aprobadas/$total pruebas aprobadas");
        }
    }
    
    // probar validacion de subida de archivos
    private function probarValidacionSubidaArchivo() {
        $pruebasArchivo = [
            ['nombre_archivo' => 'imagen.jpg', 'tamaño' => 1024000, 'debe_aprobar' => true],
            ['nombre_archivo' => 'documento.pdf', 'tamaño' => 512000, 'debe_aprobar' => true],
            ['nombre_archivo' => 'script.php', 'tamaño' => 1024, 'debe_aprobar' => false], // extensión peligrosa
            ['nombre_archivo' => 'archivo_enorme.jpg', 'tamaño' => 10240000, 'debe_aprobar' => false], // muy grande
            ['nombre_archivo' => '', 'tamaño' => 0, 'debe_aprobar' => false] // archivo vacío
        ];
        
        $aprobadas = 0;
        $total = count($pruebasArchivo);
        
        foreach ($pruebasArchivo as $prueba) {
            $esValido = $this->validarSubidaArchivo($prueba['nombre_archivo'], $prueba['tamaño']);
            
            if ($esValido === $prueba['debe_aprobar']) {
                $aprobadas++;
            }
        }
        
        if ($aprobadas === $total) {
            $this->agregarResultado('Validación Subida Archivos', true, 
                "Validación subida archivos funcionando correctamente");
        } else {
            $this->agregarResultado('Validación Subida Archivos', false, 
                "Validación subida archivos falló: $aprobadas/$total pruebas aprobadas");
        }
    }
    
    // probar validacion de caracteres especiales
    private function probarValidacionCaracteresEspeciales() {
        $pruebasCaracteres = [
            ['entrada' => 'Texto normal', 'debe_aprobar' => true],
            ['entrada' => 'Película española', 'debe_aprobar' => true],
            ['entrada' => '<script>alert("xss")</script>', 'debe_aprobar' => false],
            ['entrada' => 'SELECT * FROM usuarios', 'debe_aprobar' => false],
            ['entrada' => 'Título con números 123', 'debe_aprobar' => true],
            ['entrada' => '', 'debe_aprobar' => true] // vacío es válido para campos opcionales
        ];
        
        $aprobadas = 0;
        $total = count($pruebasCaracteres);
        
        foreach ($pruebasCaracteres as $prueba) {
            $esValido = $this->validarCaracteresEspeciales($prueba['entrada']);
            
            if ($esValido === $prueba['debe_aprobar']) {
                $aprobadas++;
            }
        }
        
        if ($aprobadas === $total) {
            $this->agregarResultado('Validación Caracteres Especiales', true, 
                "Validación caracteres especiales funcionando correctamente");
        } else {
            $this->agregarResultado('Validación Caracteres Especiales', false, 
                "Validación caracteres especiales falló: $aprobadas/$total pruebas aprobadas");
        }
    }
    
    // funciones de validacion auxiliares
    private function validarRequerido($campo, $requerido) {
        if (!$requerido) return true;
        return !empty(trim($campo));
    }
    
    private function validarRangoNumerico($valor, $min, $max) {
        if (!is_numeric($valor)) return false;
        $num = (int)$valor;
        return $num >= $min && $num <= $max;
    }
    
    private function validarFecha($fecha) {
        if (empty($fecha)) return false;
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return $d && $d->format('Y-m-d') === $fecha;
    }
    
    private function validarLongitudCadena($cadena, $min, $max) {
        $longitud = strlen($cadena);
        return $longitud >= $min && $longitud <= $max;
    }
    
    private function validarSubidaArchivo($nombreArchivo, $tamaño) {
        if (empty($nombreArchivo) || $tamaño <= 0) return false;
        
        // extensiones peligrosas
        $extensionesPeligrosas = ['php', 'exe', 'bat', 'cmd', 'sh'];
        $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
        
        if (in_array($extension, $extensionesPeligrosas)) return false;
        
        // tamaño máximo 5MB
        if ($tamaño > 5 * 1024 * 1024) return false;
        
        return true;
    }
    
    private function validarCaracteresEspeciales($entrada) {
        if (empty($entrada)) return true;
        
        // detectar posibles ataques
        $peligrosos = ['<script', 'javascript:', 'SELECT ', 'INSERT ', 'DELETE ', 'UPDATE ', 'DROP '];
        
        foreach ($peligrosos as $patron) {
            if (stripos($entrada, $patron) !== false) {
                return false;
            }
        }
        
        return true;
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
        Registrador::info("Prueba Validación $nombre: $estado - $descripcion");
    }
}
?>