<?php
require_once '../config/cors.php';
require_once '../config/database.php';

// validar método
Response::validateMethod(['GET', 'PUT']);

try {
    // autenticación requerida
    $authData = Auth::requireAuth();
    $userId = $authData['user_id'];
    
    $db = getDatabase();
    $conn = $db->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // obtener perfil del usuario
        $sql = "SELECT u.id, u.nombre_usuario, u.email, u.nombre_completo, 
                       u.fecha_registro, u.fecha_ultimo_acceso, u.avatar_url, u.biografia,
                       COUNT(DISTINCT p.id) as total_peliculas,
                       COUNT(DISTINCT r.id) as total_resenas,
                       COUNT(DISTINCT f.id) as total_favoritos,
                       COUNT(DISTINCT s1.id) as total_siguiendo,
                       COUNT(DISTINCT s2.id) as total_seguidores,
                       COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio
                FROM usuarios u
                LEFT JOIN peliculas p ON u.id = p.id_usuario_creador AND p.activo = true
                LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = true
                LEFT JOIN favoritos f ON u.id = f.id_usuario
                LEFT JOIN seguimientos s1 ON u.id = s1.id_seguidor AND s1.activo = true
                LEFT JOIN seguimientos s2 ON u.id = s2.id_seguido AND s2.activo = true
                WHERE u.id = :user_id AND u.activo = true
                GROUP BY u.id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$profile) {
            Response::error('Usuario no encontrado', 404);
        }
        
        // formatear datos
        $profile['puntuacion_promedio'] = round($profile['puntuacion_promedio'], 1);
        $profile['fecha_registro_formateada'] = date('d/m/Y', strtotime($profile['fecha_registro']));
        
        if ($profile['fecha_ultimo_acceso']) {
            $profile['ultimo_acceso_formateado'] = Utils::timeAgo($profile['fecha_ultimo_acceso']);
        }
        
        Response::success($profile, 'Perfil obtenido exitosamente');
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // actualizar perfil
        $input = Response::getJsonInput();
        
        $allowedFields = ['nombre_completo', 'email', 'biografia', 'avatar_url'];
        $updateFields = [];
        $params = [':user_id' => $userId];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $value = Response::sanitizeInput($input[$field]);
                
                // validaciones específicas
                if ($field === 'email') {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        Response::error('El formato del email no es válido', 422);
                    }
                    
                    // verificar si el email ya existe para otro usuario
                    $checkSql = "SELECT id FROM usuarios WHERE email = :email AND id != :user_id";
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->bindParam(':email', $value);
                    $checkStmt->bindParam(':user_id', $userId);
                    $checkStmt->execute();
                    
                    if ($checkStmt->fetch()) {
                        Response::error('El email ya está en uso por otro usuario', 409);
                    }
                }
                
                if ($field === 'biografia' && strlen($value) > 500) {
                    Response::error('La biografía no puede exceder los 500 caracteres', 422);
                }
                
                $updateFields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $value;
            }
        }
        
        if (empty($updateFields)) {
            Response::error('No hay campos para actualizar', 400);
        }
        
        // actualizar en la base de datos
        $updateSql = "UPDATE usuarios SET " . implode(', ', $updateFields) . " WHERE id = :user_id";
        
        $updateStmt = $conn->prepare($updateSql);
        
        if ($updateStmt->execute($params)) {
            Utils::log("Usuario {$userId} actualizó su perfil", 'INFO');
            Response::success(null, 'Perfil actualizado exitosamente');
        } else {
            Response::error('Error al actualizar el perfil', 500);
        }
    }
    
} catch (Exception $e) {
    Utils::log("Error en perfil: " . $e->getMessage(), 'ERROR');
    Response::error('Error interno del servidor', 500);
}