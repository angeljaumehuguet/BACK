<?php
class BaseDatosAdmin {
    // datos para conectar a mysql
    private $host = 'localhost';
    private $basedatos = 'cinefan_db';
    private $usuario = 'cinefan';
    private $clave = 'angel';
    private $charset = 'utf8mb4';
    private $pdo;

    public function __construct() {
        $this->conectar();
    }

    // aqui nos conectamos a la bd
    private function conectar() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->basedatos};charset={$this->charset}";
            $opciones = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, $this->usuario, $this->clave, $opciones);
        } catch (PDOException $e) {
            throw new Exception("Error conectando: " . $e->getMessage());
        }
    }

    // devuelve la conexion para usar en otros sitios
    public function obtenerConexion() {
        return $this->pdo;
    }

    // ejecutar consultas preparadas para evitar inyeccion sql
    public function consulta($sql, $parametros = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($parametros);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Error en consulta: " . $e->getMessage());
        }
    }

    // traer todos los resultados
    public function obtenerTodos($sql, $parametros = []) {
        return $this->consulta($sql, $parametros)->fetchAll();
    }

    // traer solo un resultado
    public function obtenerUno($sql, $parametros = []) {
        return $this->consulta($sql, $parametros)->fetch();
    }

    // ejecutar insert update delete
    public function ejecutar($sql, $parametros = []) {
        return $this->consulta($sql, $parametros)->rowCount();
    }
    
    // obtener el ultimo id insertado
    public function ultimoId() {
        return $this->pdo->lastInsertId();
    }
    
    // comenzar transaccion
    public function comenzarTransaccion() {
        return $this->pdo->beginTransaction();
    }
    
    // confirmar transaccion
    public function confirmarTransaccion() {
        return $this->pdo->commit();
    }
    
    // deshacer transaccion
    public function deshacerTransaccion() {
        return $this->pdo->rollback();
    }
    
    // verificar si la conexion esta activa
    public function estaConectado() {
        try {
            if ($this->pdo === null) {
                return false;
            }
            
            $this->pdo->query('SELECT 1');
            return true;
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // limpiar entrada para prevenir inyeccion sql
    public function limpiarEntrada($entrada) {
        return htmlspecialchars(strip_tags(trim($entrada)));
    }
    
    // validar email
    public function esEmailValido($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    // validar entero positivo
    public function esEnteroPositivoValido($valor) {
        return filter_var($valor, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]) !== false;
    }
    
    // contar filas en una tabla
    public function contarFilas($tabla, $condicion = '', $parametros = []) {
        $sql = "SELECT COUNT(*) as total FROM {$tabla}";
        if (!empty($condicion)) {
            $sql .= " WHERE {$condicion}";
        }
        
        $resultado = $this->obtenerUno($sql, $parametros);
        return $resultado ? (int)$resultado['total'] : 0;
    }
    
    // obtener informacion de la configuracion de la bd
    public function obtenerInfoBD() {
        return [
            'host' => $this->host,
            'basedatos' => $this->basedatos,
            'charset' => $this->charset,
            'conectado' => $this->estaConectado()
        ];
    }
}

// funcion auxiliar para obtener instancia de base de datos
function obtenerBaseDatos() {
    static $basedatos = null;
    
    if ($basedatos === null) {
        $basedatos = new BaseDatosAdmin();
    }
    
    return $basedatos;
}

// funcion auxiliar para obtener conexion
function obtenerConexion() {
    return obtenerBaseDatos()->obtenerConexion();
}

// auxiliar para ejecutar consultas con parametros seguros
function ejecutarConsultaSegura($sql, $parametros = []) {
    $bd = obtenerBaseDatos();
    return $bd->consulta($sql, $parametros);
}

// auxiliar para consultas paginadas
function ejecutarConsultaPaginada($sql, $parametros = [], $limite = 20, $desplazamiento = 0) {
    $bd = obtenerBaseDatos();
    $sql .= " LIMIT $limite OFFSET $desplazamiento";
    return $bd->consulta($sql, $parametros);
}
?>