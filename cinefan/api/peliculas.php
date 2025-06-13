<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

class PeliculasController {
    private $db;

    public function __construct() {
        $this->db = new AdminDatabase();
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($method) {
            case 'GET':
                $this->getPeliculas();
                break;
            case 'POST':
                $this->createPelicula();
                break;
            case 'PUT':
                $this->updatePelicula();
                break;
            case 'DELETE':
                $this->deletePelicula();
                break;
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Metodo no soportado']);
        }
    }

    // listar peliculas con joins para traer genero y estadisticas
    private function getPeliculas() {
        try {
            $search = $_GET['search'] ?? '';
            $genero = $_GET['genero'] ?? '';
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            // query compleja con joins para traer todo de una vez
            $sql = "SELECT p.id, p.titulo, p.director, p.ano_lanzamiento, p.duracion_minutos, 
                           g.nombre as genero, p.activo,
                           COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
                           COUNT(r.id) as total_resenas
                    FROM peliculas p
                    LEFT JOIN generos g ON p.genero_id = g.id
                    LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
                    WHERE p.activo = 1";
            $params = [];

            // busqueda por titulo o director
            if (!empty($search)) {
                $sql .= " AND (p.titulo LIKE ? OR p.director LIKE ?)";
                $searchParam = "%{$search}%";
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            // filtro por genero
            if (!empty($genero) && $genero !== 'todos') {
                $sql .= " AND p.genero_id = ?";
                $params[] = $genero;
            }

            $sql .= " GROUP BY p.id ORDER BY p.fecha_creacion DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $peliculas = $this->db->fetchAll($sql, $params);

            // contar total
            $countSql = "SELECT COUNT(*) as total FROM peliculas p WHERE p.activo = 1";
            $countParams = [];
            if (!empty($search)) {
                $countSql .= " AND (p.titulo LIKE ? OR p.director LIKE ?)";
                $searchParam = "%{$search}%";
                $countParams[] = $searchParam;
                $countParams[] = $searchParam;
            }
            if (!empty($genero) && $genero !== 'todos') {
                $countSql .= " AND p.genero_id = ?";
                $countParams[] = $genero;
            }

            $total = $this->db->fetchOne($countSql, $countParams)['total'];

            echo json_encode([
                'success' => true,
                'data' => $peliculas,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // crear pelicula nueva
    private function createPelicula() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $this->validatePelicula($input);

            $sql = "INSERT INTO peliculas (titulo, director, ano_lanzamiento, duracion_minutos, 
                                         genero_id, sinopsis, imagen_url, id_usuario_creador) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [
                $input['titulo'],
                $input['director'],
                $input['ano_lanzamiento'],
                $input['duracion_minutos'],
                $input['genero_id'],
                $input['sinopsis'] ?? null,
                $input['imagen_url'] ?? null,
                1 // ponemos id 1 como creador admin
            ];

            $this->db->execute($sql, $params);

            echo json_encode(['success' => true, 'message' => 'Pelicula agregada']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // actualizar datos de pelicula
    private function updatePelicula() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;

            if (!$id) {
                throw new Exception('Falta ID de la pelicula');
            }

            $this->validatePelicula($input, $id);

            $sql = "UPDATE peliculas SET titulo = ?, director = ?, ano_lanzamiento = ?, 
                                       duracion_minutos = ?, genero_id = ?, sinopsis = ?, imagen_url = ?
                    WHERE id = ?";
            $params = [
                $input['titulo'],
                $input['director'],
                $input['ano_lanzamiento'],
                $input['duracion_minutos'],
                $input['genero_id'],
                $input['sinopsis'] ?? null,
                $input['imagen_url'] ?? null,
                $id
            ];

            $this->db->execute($sql, $params);

            echo json_encode(['success' => true, 'message' => 'Pelicula actualizada']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // eliminar pelicula (soft delete)
    private function deletePelicula() {
        try {
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                throw new Exception('Falta ID de pelicula');
            }

            // desactivamos en lugar de borrar
            $sql = "UPDATE peliculas SET activo = 0 WHERE id = ?";
            $this->db->execute($sql, [$id]);

            echo json_encode(['success' => true, 'message' => 'Pelicula eliminada']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // validaciones para peliculas
    private function validatePelicula($data, $id = null) {
        $errors = [];

        if (empty($data['titulo'])) {
            $errors[] = 'Titulo obligatorio';
        }

        if (empty($data['director'])) {
            $errors[] = 'Director obligatorio';
        }

        // validar año dentro de rango logico
        if (empty($data['ano_lanzamiento']) || $data['ano_lanzamiento'] < 1895 || $data['ano_lanzamiento'] > 2030) {
            $errors[] = 'Año debe estar entre 1895 y 2030';
        }

        // duracion razonable
        if (empty($data['duracion_minutos']) || $data['duracion_minutos'] < 1 || $data['duracion_minutos'] > 600) {
            $errors[] = 'Duracion debe estar entre 1 y 600 minutos';
        }

        if (empty($data['genero_id'])) {
            $errors[] = 'Genero obligatorio';
        }

        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
    }
}

$controller = new PeliculasController();
$controller->handleRequest();