<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// obtener método de petición
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {
    case 'GET':
        listarFavoritos();
        break;
    case 'POST':
        agregarFavorito();
        break;
    case 'DELETE':
        eliminarFavorito();
        break;
    default:
        Response::error('Método no permitido', 405);
        break;
}

// listar favoritos del usuario
function listarFavoritos() {
    try {
        // autenticación requerida
        $authData = Auth::requireAuth();
        $userId = $authData['user_id'];
        
        $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
        $limite = isset($_GET['limite']) ? min(50, max(1, (int)$_GET['limite'])) : DEFAULT_PAGE_SIZE;
        $offset = ($pagina - 1) * $limite;
        
        $db = getDatabase();
        $conn = $db->getConnection();

        // consulta para obtener favoritos con detalles de películas
        $sql = "SELECT 
                    f.id,
                    f.fecha_agregado,
                    p.id as id_pelicula,
                    p.titulo,
                    p.director,
                    p.ano_lanzamiento,
                    p.duracion_minutos,
                    p.imagen_url,
                    g.nombre as genero,
                    g.color_hex as color_genero,
                    COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
                    COUNT(DISTINCT r.id) as total_resenas
                FROM favoritos f
                INNER JOIN peliculas p ON f.id_pelicula = p.id
                INNER JOIN generos g ON p.genero_id = g.id
                LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = true
                WHERE f.id_usuario = :user_id AND p.activo = true
                GROUP BY f.id, p.id, g.id
                ORDER BY f.fecha_agregado DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $favoritos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // obtener total para paginación
        $totalSql = "SELECT COUNT(*) FROM favoritos f 
                     INNER JOIN peliculas p ON f.id_pelicula = p.id 
                     WHERE f.id_usuario = :user_id AND p.activo = true";
        $totalStmt = $conn->prepare($totalSql);
        $totalStmt->bindParam(':user_id', $userId);
        $totalStmt->execute();
        $total = $totalStmt->fetchColumn();

        // formatear datos
        $favoritosFormateados = [];
        foreach ($favoritos as $favorito) {
            $favoritosFormateados[] = [
                'id' => (int)$favorito['id'],
                'fecha_agregado' => $favorito['fecha_agregado'],
                'fecha_formateada' => Utils::timeAgo($favorito['fecha_agregado']),
                'pelicula' => [
                    'id' => (int)$favorito['id_pelicula'],
                    'titulo' => $favorito['titulo'],
                    'director' => $favorito['director'],
                    'ano_lanzamiento' => (int)$favorito['ano_lanzamiento'],
                    'genero' => $favorito['genero'],
                    'color_genero' => $favorito['color_genero'],
                    'duracion_minutos' => (int)$favorito['duracion_minutos'],
                    'duracion_formateada' => Utils::formatDuration($favorito['duracion_minutos']),
                    'imagen_url' => $favorito['imagen_url'],
                    'puntuacion_promedio' => round($favorito['puntuacion_promedio'], 1),
                    'total_resenas' => (int)$favorito['total_resenas']
                ]
            ];
        }

        Response::success([
            'favoritos' => $favoritosFormateados,
            'paginacion' => [
                'pagina_actual' => $pagina,
                'total_items' => (int)$total,
                'items_por_pagina' => $limite,
                'total_paginas' => ceil($total / $limite)
            ]
        ], 'Favoritos obtenidos exitosamente');

    } catch (Exception $e) {
        Utils::log("Error obteniendo favoritos: " . $e->getMessage(), 'ERROR');
        Response::error('Error interno del servidor', 500);
    }
}

// agregar película a favoritos
function agregarFavorito() {
    try {
        // autenticación requerida
        $authData = Auth::requireAuth();
        $userId = $authData['user_id'];

        // obtener datos de la petición
        $input = Response::getJsonInput();
        
        if (!isset($input['id_pelicula'])) {
            Response::error('ID de película requerido', 400);
        }

        $idPelicula = (int)$input['id_pelicula'];
        $db = getDatabase();
        $conn = $db->getConnection();

        // verificar que la película existe y está activa
        $sqlPelicula = "SELECT id, titulo FROM peliculas WHERE id = :id AND activo = true";
        $stmtPelicula = $conn->prepare($sqlPelicula);
        $stmtPelicula->bindParam(':id', $idPelicula);
        $stmtPelicula->execute();
        $pelicula = $stmtPelicula->fetch(PDO::FETCH_ASSOC);

        if (!$pelicula) {
            Response::error('Película no encontrada', 404);
        }

        // verificar si ya está en favoritos
        $sqlExiste = "SELECT id FROM favoritos WHERE id_usuario = :user_id AND id_pelicula = :pelicula_id";
        $stmtExiste = $conn->prepare($sqlExiste);
        $stmtExiste->bindParam(':user_id', $userId);
        $stmtExiste->bindParam(':pelicula_id', $idPelicula);
        $stmtExiste->execute();
        
        if ($stmtExiste->fetch()) {
            Response::error('La película ya está en favoritos', 409);
        }

        // agregar a favoritos
        $sqlInsertar = "INSERT INTO favoritos (id_usuario, id_pelicula, fecha_agregado) VALUES (:user_id, :pelicula_id, NOW())";
        $stmtInsertar = $conn->prepare($sqlInsertar);
        $stmtInsertar->bindParam(':user_id', $userId);
        $stmtInsertar->bindParam(':pelicula_id', $idPelicula);
        $stmtInsertar->execute();

        Response::success([
            'id_favorito' => $conn->lastInsertId(),
            'pelicula' => $pelicula['titulo']
        ], 'Película agregada a favoritos exitosamente');

    } catch (Exception $e) {
        Utils::log("Error agregando favorito: " . $e->getMessage(), 'ERROR');
        Response::error('Error interno del servidor', 500);
    }
}

// eliminar película de favoritos
function eliminarFavorito() {
    try {
        // autenticación requerida
        $authData = Auth::requireAuth();
        $userId = $authData['user_id'];

        // obtener id de película de parámetros URL o JSON
        $idPelicula = null;
        
        if (isset($_GET['id_pelicula'])) {
            $idPelicula = (int)$_GET['id_pelicula'];
        } else {
            $input = Response::getJsonInput();
            if (isset($input['id_pelicula'])) {
                $idPelicula = (int)$input['id_pelicula'];
            }
        }

        if (!$idPelicula) {
            Response::error('ID de película requerido', 400);
        }

        $db = getDatabase();
        $conn = $db->getConnection();

        // verificar que el favorito existe y pertenece al usuario
        $sqlVerificar = "SELECT f.id, p.titulo 
                        FROM favoritos f
                        INNER JOIN peliculas p ON f.id_pelicula = p.id
                        WHERE f.id_usuario = :user_id AND f.id_pelicula = :pelicula_id";
        
        $stmtVerificar = $conn->prepare($sqlVerificar);
        $stmtVerificar->bindParam(':user_id', $userId);
        $stmtVerificar->bindParam(':pelicula_id', $idPelicula);
        $stmtVerificar->execute();
        
        $favorito = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

        if (!$favorito) {
            Response::error('Favorito no encontrado', 404);
        }

        // eliminar favorito
        $sqlEliminar = "DELETE FROM favoritos WHERE id_usuario = :user_id AND id_pelicula = :pelicula_id";
        $stmtEliminar = $conn->prepare($sqlEliminar);
        $stmtEliminar->bindParam(':user_id', $userId);
        $stmtEliminar->bindParam(':pelicula_id', $idPelicula);
        $stmtEliminar->execute();

        Response::success([
            'pelicula' => $favorito['titulo']
        ], 'Película eliminada de favoritos exitosamente');

    } catch (Exception $e) {
        Utils::log("Error eliminando favorito: " . $e->getMessage(), 'ERROR');
        Response::error('Error interno del servidor', 500);
    }
}