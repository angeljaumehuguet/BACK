<?php
class AdminDatabase {
    // datos para conectar a mysql
    private $host = 'localhost';
    private $database = 'cinefan_db';
    private $username = 'cinefan';
    private $password = 'angel';
    private $charset = 'utf8mb4';
    private $pdo;

    public function __construct() {
        $this->connect();
    }

    // aqui nos conectamos a la bd
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new Exception("Error conectando: " . $e->getMessage());
        }
    }

    // devuelve la conexion para usar en otros sitios
    public function getConnection() {
        return $this->pdo;
    }

    // ejecutar consultas preparadas para evitar sql injection
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Error en consulta: " . $e->getMessage());
        }
    }

    // traer todos los resultados
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    // traer solo un resultado
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    // ejecutar insert update delete
    public function execute($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }
}