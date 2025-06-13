<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

class GenerosController {
    private $db;

    public function __construct() {
        $this->db = new AdminDatabase();
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($method) {
            case 'GET':
                $this->getGeneros();
                break;
            case 'POST':
                $this->createGenero();
                break;
            case 'PUT':
                $this->updateGenero();
                break;
            case 'DELETE':
                $this->deleteGenero();
                break;
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Metodo no valido']);
        }
    }

    // listar generos con cuantas peliculas tiene cada uno
    private function getGeneros() {
        try {
            $sql = "SELECT g.id, g.nombre, g.descripcion, g.color_hex,
                           COUNT(p.id) as total_peliculas
                    FROM generos g
                    LEFT JOIN peliculas p ON g.id = p.genero_id AND p.activo = 1
                    WHERE g.activo = 1
                    GROUP BY g.id
                    ORDER BY g.nombre";

            $generos = $this->db->fetchAll($sql);

            echo json_encode([
                'success' => true,
                'data' => $generos
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // crear genero nuevo
    private function createGenero() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $this->validateGenero($input);

            $sql = "INSERT INTO generos (nombre, descripcion, color_hex) VALUES (?, ?, ?)";
            $params = [
                $input['nombre'],
                $input['descripcion'] ?? null,
                $input['color_hex'] ?? '#6c757d' // color por defecto
            ];

            $this->db->execute($sql, $params);

            echo json_encode(['success' => true, 'message' => 'Genero creado']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // actualizar genero existente
    private function updateGenero() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;

            if (!$id) {
                throw new Exception('Falta ID del genero');
            }

            $this->validateGenero($input, $id);

            $sql = "UPDATE generos SET nombre = ?, descripcion = ?, color_hex = ? WHERE id = ?";
            $params = [
                $input['nombre'],
                $input['descripcion'] ?? null,
                $input['color_hex'] ?? '#6c757d',
                $id
            ];

            $this->db->execute($sql, $params);

            echo json_encode(['success' => true, 'message' => 'Genero actualizado']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // eliminar genero si no tiene peliculas
    private function deleteGenero() {
        try {
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                throw new Exception('Falta ID del genero');
            }

            // verificar que no tenga peliculas asociadas
            $peliculasCount = $this->db->fetchOne("SELECT COUNT(*) as count FROM peliculas WHERE genero_id = ? AND activo = 1", [$id]);
            if ($peliculasCount['count'] > 0) {
                throw new Exception('No se puede borrar porque tiene peliculas asociadas');
            }

            // soft delete
            $sql = "UPDATE generos SET activo = 0 WHERE id = ?";
            $this->db->execute($sql, [$id]);

            echo json_encode(['success' => true, 'message' => 'Genero eliminado']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // validar datos del genero
    private function validateGenero($data, $id = null) {
        $errors = [];

        if (empty($data['nombre'])) {
            $errors[] = 'Nombre del genero obligatorio';
        }

        // verificar que no exista otro genero con el mismo nombre
        $checkSql = "SELECT id FROM generos WHERE nombre = ? AND activo = 1";
        $checkParams = [$data['nombre']];
        
        if ($id) {
            $checkSql .= " AND id != ?";
            $checkParams[] = $id;
        }

        $existing = $this->db->fetchOne($checkSql, $checkParams);
        if ($existing) {
            $errors[] = 'Ya existe un genero con ese nombre';
        }

        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
    }
}

$controller = new GenerosController();
$controller->handleRequest();