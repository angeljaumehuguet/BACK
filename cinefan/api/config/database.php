<?php
class Database {
    // configuracion de la base de datos
    private $host = 'localhost';
    private $db_name = 'cinefan_db';
    private $username = 'cinefan';
    private $password = 'angel';
    private $charset = 'utf8mb4';
    
    private $connection = null;
    
    /**
     * Obtener conexión a la base de datos
     */
    public function getConnection() {
        try {
            if ($this->connection === null) {
                $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
                
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}",
                    PDO::ATTR_TIMEOUT => 30
                ];
                
                $this->connection = new PDO($dsn, $this->username, $this->password, $options);
                
                // configurar zona horaria
                $this->connection->exec("SET time_zone = '+01:00'");
                
                // log de conexión exitosa
                error_log("Conexión a base de datos establecida exitosamente");
            }
            
            return $this->connection;
            
        } catch (PDOException $e) {
            error_log("Error de conexión a la base de datos: " . $e->getMessage());
            throw new Exception('Error de conexión a la base de datos: ' . $e->getMessage());;
        }
    }
    
    /**
     * Verificar si la conexión está activa
     */
    public function isConnected() {
        try {
            if ($this->connection === null) {
                return false;
            }
            
            $this->connection->query('SELECT 1');
            return true;
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Cerrar conexión
     */
    public function closeConnection() {
        $this->connection = null;
    }
    
    /**
     * Iniciar transacción
     */
    public function beginTransaction() {
        if ($this->connection) {
            return $this->connection->beginTransaction();
        }
        return false;
    }
    
    /**
     * Confirmar transacción
     */
    public function commit() {
        if ($this->connection) {
            return $this->connection->commit();
        }
        return false;
    }
    
    /**
     * Revertir transacción
     */
    public function rollback() {
        if ($this->connection) {
            return $this->connection->rollback();
        }
        return false;
    }
    
    /**
     * Obtener último ID insertado
     */
    public function getLastInsertId() {
        if ($this->connection) {
            return $this->connection->lastInsertId();
        }
        return null;
    }
    
    /**
     * Ejecutar consulta preparada
     */
    public function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
            
        } catch (PDOException $e) {
            error_log("Error ejecutando consulta: " . $e->getMessage());
            throw new Exception("Error en la consulta a la base de datos");
        }
    }
    
    /**
     * Obtener una sola fila
     */
    public function fetchSingle($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Obtener múltiples filas
     */
    public function fetchMultiple($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Ejecutar insert/update/delete
     */
    public function executeStatement($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Verificar si existe un registro
     */
    public function recordExists($table, $conditions = []) {
        $sql = "SELECT 1 FROM {$table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $sql .= " LIMIT 1";
        
        $result = $this->fetchSingle($sql, $params);
        return $result !== false;
    }
    
    /**
     * Obtener configuración de la base de datos
     */
    public function getDatabaseInfo() {
        return [
            'host' => $this->host,
            'database' => $this->db_name,
            'charset' => $this->charset,
            'connected' => $this->isConnected()
        ];
    }
    
    /**
     * Limpiar entrada para prevenir inyección SQL
     */
    public function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
    
    /**
     * Validar email
     */
    public function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validar entero positivo
     */
    public function isValidPositiveInt($value) {
        return filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]) !== false;
    }
}

/**
 * Función helper para obtener instancia de base de datos
 */
function getDatabase() {
    static $database = null;
    
    if ($database === null) {
        $database = new Database();
    }
    
    return $database;
}

/**
 * Función helper para obtener conexión
 */
function getConnection() {
    return getDatabase()->getConnection();
}

?>