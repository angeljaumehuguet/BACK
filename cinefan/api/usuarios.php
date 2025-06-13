<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

class UsuariosController {
    private $db;

    public function __construct() {
        $this->db = new AdminDatabase();
    }

    // aqui manejamos las peticiones que llegan
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($method) {
            case 'GET':
                $this->getUsuarios();
                break;
            case 'POST':
                $this->createUsuario();
                break;
            case 'PUT':
                $this->updateUsuario();
                break;
            case 'DELETE':
                $this->deleteUsuario();
                break;
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Metodo no permitido']);
        }
    }

    // obtener listado de usuarios con filtros
    private function getUsuarios() {
        try {
            // recogemos los parametros que llegan por get
            $search = $_GET['search'] ?? '';
            $filter = $_GET['filter'] ?? 'todos';
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            // consulta base
            $sql = "SELECT id, nombre_usuario, email, nombre_completo, fecha_registro, activo 
                    FROM usuarios WHERE 1=1";
            $params = [];

            // si hay busqueda la agregamos
            if (!empty($search)) {
                $sql .= " AND (nombre_usuario LIKE ? OR email LIKE ? OR nombre_completo LIKE ?)";
                $searchParam = "%{$search}%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            // filtro por estado activo inactivo
            if ($filter !== 'todos') {
                $sql .= " AND activo = ?";
                $params[] = ($filter === 'activos') ? 1 : 0;
            }

            // ordenar y paginar
            $sql .= " ORDER BY fecha_registro DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $usuarios = $this->db->fetchAll($sql, $params);

            // contar total para la paginacion
            $countSql = str_replace('SELECT id, nombre_usuario, email, nombre_completo, fecha_registro, activo FROM usuarios', 'SELECT COUNT(*) as total FROM usuarios', explode(' LIMIT', $sql)[0]);
            $countParams = array_slice($params, 0, -2);
            $total = $this->db->fetchOne($countSql, $countParams)['total'];

            echo json_encode([
                'success' => true,
                'data' => $usuarios,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // crear nuevo usuario
    private function createUsuario() {
        try {
            // leemos los datos que llegan por post
            $input = json_decode(file_get_contents('php://input'), true);
            
            // validamos los datos antes de guardar
            $this->validateUsuario($input);

            $sql = "INSERT INTO usuarios (nombre_usuario, email, password, nombre_completo, activo) 
                    VALUES (?, ?, ?, ?, ?)";
            $params = [
                $input['nombre_usuario'],
                $input['email'],
                password_hash($input['password'], PASSWORD_DEFAULT), // hasheamos la contraseña
                $input['nombre_completo'],
                $input['activo'] ?? true
            ];

            $this->db->execute($sql, $params);

            echo json_encode(['success' => true, 'message' => 'Usuario creado correctamente']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // actualizar usuario existente
    private function updateUsuario() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;

            if (!$id) {
                throw new Exception('Falta el ID del usuario');
            }

            $this->validateUsuario($input, $id);

            $sql = "UPDATE usuarios SET nombre_usuario = ?, email = ?, nombre_completo = ?, activo = ? 
                    WHERE id = ?";
            $params = [
                $input['nombre_usuario'],
                $input['email'],
                $input['nombre_completo'],
                $input['activo'] ?? true,
                $id
            ];

            $this->db->execute($sql, $params);

            echo json_encode(['success' => true, 'message' => 'Usuario actualizado']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // eliminar usuario (no lo borramos solo lo desactivamos)
    private function deleteUsuario() {
        try {
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                throw new Exception('Falta el ID del usuario');
            }

            // soft delete para no perder los datos
            $sql = "UPDATE usuarios SET activo = 0 WHERE id = ?";
            $this->db->execute($sql, [$id]);

            echo json_encode(['success' => true, 'message' => 'Usuario eliminado']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // validar datos del usuario
    private function validateUsuario($data, $id = null) {
        $errors = [];

        // campos obligatorios
        if (empty($data['nombre_usuario'])) {
            $errors[] = 'El nombre de usuario es obligatorio';
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email no valido';
        }

        if (empty($data['nombre_completo'])) {
            $errors[] = 'Nombre completo obligatorio';
        }

        // verificar que no existan duplicados
        $checkSql = "SELECT id FROM usuarios WHERE (nombre_usuario = ? OR email = ?)";
        $checkParams = [$data['nombre_usuario'], $data['email']];
        
        if ($id) {
            $checkSql .= " AND id != ?";
            $checkParams[] = $id;
        }

        $existing = $this->db->fetchOne($checkSql, $checkParams);
        if ($existing) {
            $errors[] = 'Ya existe un usuario con ese nombre o email';
        }

        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
    }
}

$controller = new UsuariosController();
$controller->handleRequest();