<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../includes/response.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/config.php';

// validar método
Response::validateMethod(['POST']);

try {
    // autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    // obtener datos de entrada
    $input = Response::getJsonInput();
    
    // validar campos requeridos
    Response::validateRequired($input, ['id_pelicula', 'puntuacion', 'titulo', 'texto_resena']);
    
    $idPelicula = (int)$input['id_pelicula'];
    $puntuacion = (int)$input['puntuacion'];
    $titulo = Response::sanitizeInput($input['titulo']);
    $textoResena = Response::sanitizeInput($input['texto_resena']);
    $esSpoiler = isset($input['es_spoiler']) ? (bool)$input['es_spoiler'] : false;
    
    // validaciones específicas
    if ($puntuacion < 1 || $puntuacion > 10) {
        Response::error('La puntuación debe estar entre 1 y 10', 400);
    }
    
    if (strlen($titulo) < 5 || strlen($titulo) > 100) {
        Response::error('El título debe tener entre 5 y 100 caracteres', 400);
    }
    
    if (strlen($textoResena) < 10 || strlen($textoResena) > 2000) {
        Response::error('La reseña debe tener entre 10 y 2000 caracteres', 400);
    }
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // verificar que la película existe y está activa
    $peliculaSql = "SELECT id, titulo FROM peliculas WHERE id = :id_pelicula AND activo = true";
    $peliculaStmt = $conn->prepare($peliculaSql);
    $peliculaStmt->bindParam(':id_pelicula', $idPelicula, PDO::PARAM_INT);
    $peliculaStmt->execute();
    
    $pelicula = $peliculaStmt->fetch(PDO::FETCH_ASSOC);
    if (!$pelicula) {
        Response::error('Película no encontrada', 404);
    }
    
    // verificar si el usuario ya tiene una reseña para esta película
    $resenaExistenteSql = "SELECT id FROM resenas WHERE id_usuario = :id_usuario AND id_pelicula = :id_pelicula AND activo = true";
    $resenaExistenteStmt = $conn->prepare($resenaExistenteSql);
    $resenaExistenteStmt->bindParam(':id_usuario', $userId, PDO::PARAM_INT);
    $resenaExistenteStmt->bindParam(':id_pelicula', $idPelicula, PDO::PARAM_INT);
    $resenaExistenteStmt->execute();
    
    if ($resenaExistenteStmt->fetch()) {
        Response::error('Ya tienes una reseña para esta película', 400);
    }
    
    // iniciar transacción
    $conn->beginTransaction();
    
    try {
        // insertar nueva reseña
        $insertSql = "INSERT INTO resenas (id_usuario, id_pelicula, puntuacion, titulo, texto_resena, es_spoiler, fecha_resena, activo) 
                      VALUES (:id_usuario, :id_pelicula, :puntuacion, :titulo, :texto_resena, :es_spoiler, NOW(), true)";
        
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bindParam(':id_usuario', $userId, PDO::PARAM_INT);
        $insertStmt->bindParam(':id_pelicula', $idPelicula, PDO::PARAM_INT);
        $insertStmt->bindParam(':puntuacion', $puntuacion, PDO::PARAM_INT);
        $insertStmt->bindParam(':titulo', $titulo, PDO::PARAM_STR);
        $insertStmt->bindParam(':texto_resena', $textoResena, PDO::PARAM_STR);
        $insertStmt->bindParam(':es_spoiler', $esSpoiler, PDO::PARAM_BOOL);
        
        $insertStmt->execute();
        $resenaId = $conn->lastInsertId();
        
        // actualizar estadísticas de la película
        $updatePeliculaSql = "UPDATE peliculas p 
                              SET puntuacion_promedio = (
                                  SELECT AVG(r.puntuacion) 
                                  FROM resenas r 
                                  WHERE r.id_pelicula = p.id AND r.activo = true
                              ) 
                              WHERE p.id = :id_pelicula";
        
        $updatePeliculaStmt = $conn->prepare($updatePeliculaSql);
        $updatePeliculaStmt->bindParam(':id_pelicula', $idPelicula, PDO::PARAM_INT);
        $updatePeliculaStmt->execute();
        
        // confirmar transacción
        $conn->commit();
        
        // obtener datos completos de la reseña creada
        $resenaCompletaSql = "SELECT r.id, r.puntuacion, r.titulo, r.texto_resena, r.es_spoiler, r.fecha_resena,
                                     u.nombre_usuario, u.nombre_completo,
                                     p.titulo as pelicula_titulo
                              FROM resenas r
                              INNER JOIN usuarios u ON r.id_usuario = u.id
                              INNER JOIN peliculas p ON r.id_pelicula = p.id
                              WHERE r.id = :resena_id";
        
        $resenaCompletaStmt = $conn->prepare($resenaCompletaSql);
        $resenaCompletaStmt->bindParam(':resena_id', $resenaId, PDO::PARAM_INT);
        $resenaCompletaStmt->execute();
        
        $resenaCompleta = $resenaCompletaStmt->fetch(PDO::FETCH_ASSOC);
        
        // formatear datos
        $resenaCompleta['fecha_formateada'] = Utils::timeAgo($resenaCompleta['fecha_resena']);
        $resenaCompleta['es_spoiler'] = (bool)$resenaCompleta['es_spoiler'];
        
        Utils::log("Usuario {$userId} creó reseña para película: {$pelicula['titulo']} (ID: {$resenaId})", 'INFO');
        
        Response::success($resenaCompleta, 'Reseña creada exitosamente');
        
    } catch (Exception $e) {
        // revertir transacción en caso de error
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    Utils::log("Error en crear reseña: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}