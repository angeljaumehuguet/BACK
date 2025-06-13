<?php
class BaseDatos {
    private $host;
    private $nombre_bd;
    private $usuario;
    private $clave;
    private $conexion;
    
    public function __construct() {
        // aqui van los datos de configuracion
        $this->host = HOST_BD;
        $this->nombre_bd = NOMBRE_BD;
        $this->usuario = USUARIO_BD;
        $this->clave = CLAVE_BD;
        
        $this->conectar();
    }
    
    // establecer conexion con la bd
    private function conectar() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->nombre_bd};charset=" . CHARSET_BD;
            $opciones = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conexion = new PDO($dsn, $this->usuario, $this->clave, $opciones);
        } catch (PDOException $e) {
            throw new Exception("Error conectando a la base de datos: " . $e->getMessage());
        }
    }
    
    // obtener conexion
    public function obtenerConexion() {
        return $this->conexion;
    }
    
    // ejecutar consulta que devuelve un solo valor
    public function obtenerValor($sql, $parametros = []) {
        try {
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($parametros);
            
            $resultado = $stmt->fetch();
            
            // devolver el primer valor de la primera columna
            return $resultado ? array_values($resultado)[0] : null;
        } catch (PDOException $e) {
            throw new Exception("Error ejecutando consulta: " . $e->getMessage());
        }
    }
    
    // obtener una sola fila
    public function obtenerUno($sql, $parametros = []) {
        try {
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($parametros);
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Error ejecutando consulta: " . $e->getMessage());
        }
    }
    
    // obtener todas las filas
    public function obtenerTodos($sql, $parametros = []) {
        try {
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($parametros);
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error ejecutando consulta: " . $e->getMessage());
        }
    }
    
    // ejecutar consulta que no devuelve datos (INSERT UPDATE DELETE)
    public function ejecutar($sql, $parametros = []) {
        try {
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($parametros);
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Error ejecutando consulta: " . $e->getMessage());
        }
    }
    
    // obtener ultimo ID insertado
    public function ultimoId() {
        return $this->conexion->lastInsertId();
    }
    
    // iniciar transaccion
    public function iniciarTransaccion() {
        return $this->conexion->beginTransaction();
    }
    
    // confirmar transaccion
    public function confirmarTransaccion() {
        return $this->conexion->commit();
    }
    
    // cancelar transaccion
    public function cancelarTransaccion() {
        return $this->conexion->rollBack();
    }
    
    // cerrar conexion
    public function cerrar() {
        $this->conexion = null;
    }
    
    // destructor
    public function __destruct() {
        $this->cerrar();
    }
}
?>