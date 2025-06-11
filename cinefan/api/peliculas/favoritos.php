<?php

require_once '../config/database.php';
require_once '../config/cors.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// obtener metodo de peticion
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
        enviarRespuesta(false, 'metodo no permitido', null, 405);
        break;
}

// listar favoritos del usuario
function listarFavoritos() {
    try {
        // verificar autenticacion
        $datosToken = verificarToken();
        if (!$datosToken) {
            enviarRespuesta(false, 'token invalido o expirado', null, 401);
            return;
        }

        $idUsuario = $datosToken['id_usuario'];
        $db = conectarDB();

        // consulta para obtener favoritos con detalles de peliculas
        $sql = "SELECT 
                    f.id,
                    f.fecha_agregado,
                    p.id as id_pelicula,
                    p.titulo,
                    p.director,
                    p.ano_lanzamiento,
                    p.genero,
                    p.duracion_minutos,
                    p.imagen_url,
                    p.puntuacion_promedio,
                    (SELECT COUNT(*) FROM resenas r WHERE r.id_pelicula = p.id) as total_resenas
                FROM favoritos f
                INNER JOIN peliculas p ON f.id_pelicula = p.id
                WHERE f.id_usuario = ? AND f.activo = 1 AND p.activo = 1
                ORDER BY f.fecha_agregado DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute([$idUsuario]);
        $favoritos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // formatear datos
        $favoritosFormateados = [];
        foreach ($favoritos as $favorito) {
            $favoritosFormateados[] = [
                'id' => (int)$favorito['id'],
                'fecha_agregado' => $favorito['fecha_agregado'],
                'pelicula' => [
                    'id' => (int)$favorito['id_pelicula'],
                    'titulo' => $favorito['titulo'],
                    'director' => $favorito['director'],
                    'ano_lanzamiento' => (int)$favorito['ano_lanzamiento'],
                    'genero' => $favorito['genero'],
                    'duracion_minutos' => (int)$favorito['duracion_minutos'],
                    'imagen_url' => $favorito['imagen_url'],
                    'puntuacion_promedio' => round($favorito['puntuacion_promedio'], 1),
                    'total_resenas' => (int)$favorito['total_resenas']
                ]
            ];
        }

        enviarRespuesta(true, 'favoritos obtenidos exitosamente', [
            'favoritos' => $favoritosFormateados,
            'total' => count($favoritosFormateados)
        ]);

    } catch (Exception $e) {
        error_log("error obteniendo favoritos: " . $e->getMessage());
        enviarRespuesta(false, 'error interno del servidor', null, 500);
    }
}

// agregar pelicula a favoritos
function agregarFavorito() {
    try {
        // verificar autenticacion
        $datosToken = verificarToken();
        if (!$datosToken) {
            enviarRespuesta(false, 'token invalido o expirado', null, 401);
            return;
        }

        $idUsuario = $datosToken['id_usuario'];

        // obtener datos de la peticion
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id_pelicula'])) {
            enviarRespuesta(false, 'id de pelicula requerido', null, 400);
            return;
        }

        $idPelicula = (int)$input['id_pelicula'];
        $db = conectarDB();

        // verificar que la pelicula existe y esta activa
        $sqlPelicula = "SELECT id, titulo FROM peliculas WHERE id = ? AND activo = 1";
        $stmtPelicula = $db->prepare($sqlPelicula);
        $stmtPelicula->execute([$idPelicula]);
        $pelicula = $stmtPelicula->fetch(PDO::FETCH_ASSOC);

        if (!$pelicula) {
            enviarRespuesta(false, 'pelicula no encontrada', null, 404);
            return;
        }

        // verificar si ya esta en favoritos
        $sqlExiste = "SELECT id FROM favoritos WHERE id_usuario = ? AND id_pelicula = ? AND activo = 1";
        $stmtExiste = $db->prepare($sqlExiste);
        $stmtExiste->execute([$idUsuario, $idPelicula]);
        
        if ($stmtExiste->fetch()) {
            enviarRespuesta(false, 'la pelicula ya esta en favoritos', null, 409);
            return;
        }

        // agregar a favoritos
        $sqlInsertar = "INSERT INTO favoritos (id_usuario, id_pelicula, fecha_agregado) VALUES (?, ?, NOW())";
        $stmtInsertar = $db->prepare($sqlInsertar);
        $stmtInsertar->execute([$idUsuario, $idPelicula]);

        enviarRespuesta(true, 'pelicula agregada a favoritos exitosamente', [
            'id_favorito' => $db->lastInsertId(),
            'pelicula' => $pelicula['titulo']
        ]);

    } catch (Exception $e) {
        error_log("error agregando favorito: " . $e->getMessage());
        enviarRespuesta(false, 'error interno del servidor', null, 500);
    }
}

// eliminar pelicula de favoritos
function eliminarFavorito() {
    try {
        // verificar autenticacion
        $datosToken = verificarToken();
        if (!$datosToken) {
            enviarRespuesta(false, 'token invalido o expirado', null, 401);
            return;
        }

        $idUsuario = $datosToken['id_usuario'];

        // obtener id de pelicula de parametros url o json
        $idPelicula = null;
        
        if (isset($_GET['id_pelicula'])) {
            $idPelicula = (int)$_GET['id_pelicula'];
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['id_pelicula'])) {
                $idPelicula = (int)$input['id_pelicula'];
            }
        }

        if (!$idPelicula) {
            enviarRespuesta(false, 'id de pelicula requerido', null, 400);
            return;
        }

        $db = conectarDB();

        // verificar que el favorito existe y pertenece al usuario
        $sqlVerificar = "SELECT id, (SELECT titulo FROM peliculas WHERE id = ?) as titulo 
                        FROM favoritos 
                        WHERE id_usuario = ? AND id_pelicula = ? AND activo = 1";
        $stmtVerificar = $db->prepare($sqlVerificar);
        $stmtVerificar->execute([$idPelicula, $idUsuario, $idPelicula]);
        $favorito = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

        if (!$favorito) {
            enviarRespuesta(false, 'favorito no encontrado', null, 404);
            return;
        }

        // eliminar de favoritos (soft delete)
        $sqlEliminar = "UPDATE favoritos SET activo = 0, fecha_eliminado = NOW() 
                       WHERE id_usuario = ? AND id_pelicula = ? AND activo = 1";
        $stmtEliminar = $db->prepare($sqlEliminar);
        $stmtEliminar->execute([$idUsuario, $idPelicula]);

        enviarRespuesta(true, 'pelicula eliminada de favoritos exitosamente', [
            'pelicula' => $favorito['titulo']
        ]);

    } catch (Exception $e) {
        error_log("error eliminando favorito: " . $e->getMessage());
        enviarRespuesta(false, 'error interno del servidor', null, 500);
    }
}
?>