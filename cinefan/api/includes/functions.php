<?php
// includes/functions.php - funciones de utilidad del sistema antiguo

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// función para enviar respuesta (sistema antiguo)
function enviarRespuesta($exito, $mensaje, $datos = null, $codigo = 200) {
    http_response_code($codigo);
    
    $respuesta = [
        'exito' => $exito,
        'mensaje' => $mensaje,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($datos !== null) {
        $respuesta['datos'] = $datos;
    }
    
    header('Content-Type: application/json');
    echo json_encode($respuesta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// función para verificar token (sistema antiguo)
function verificarToken() {
    $headers = getallheaders();
    $token = null;
    
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            $token = $matches[1];
        }
    }
    
    if (!$token) {
        return false;
    }
    
    $payload = Auth::verifyToken($token);
    
    if (!$payload) {
        return false;
    }
    
    return [
        'id_usuario' => $payload['user_id'],
        'usuario' => $payload['username'],
        'email' => $payload['email']
    ];
}

// función para conectar a BD (sistema antiguo)
function conectarDB() {
    $db = new Database();
    return $db->getConnection();
}

// funciones de utilidad
class Utils {
    
    public static function timeAgo($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        
        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;
        
        $string = array(
            'y' => 'año',
            'm' => 'mes',
            'w' => 'semana',
            'd' => 'día',
            'h' => 'hora',
            'i' => 'minuto',
            's' => 'segundo',
        );
        
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }
        
        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? 'hace ' . implode(', ', $string) : 'ahora mismo';
    }
    
    public static function formatDuration($minutes) {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        if ($hours > 0) {
            return $hours . 'h ' . $mins . 'm';
        }
        return $mins . 'm';
    }
    
    public static function validateRating($rating) {
        return is_numeric($rating) && $rating >= 1 && $rating <= 5;
    }
    
    public static function validateMovieYear($year) {
        $currentYear = date('Y');
        return is_numeric($year) && $year >= 1895 && $year <= ($currentYear + 5);
    }
    
    public static function log($message, $level = 'INFO') {
        $logFile = __DIR__ . '/../logs/app.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        
        // crear directorio de logs si no existe
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    public static function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
}

// función para obtener estadísticas del sistema (usado en estadisticas/general.php)
function obtenerEstadisticasSistema($db) {
    $sql = "SELECT 
                (SELECT COUNT(*) FROM usuarios WHERE activo = 1) as total_usuarios,
                (SELECT COUNT(*) FROM peliculas WHERE activo = 1) as total_peliculas,
                (SELECT COUNT(*) FROM resenas WHERE activo = 1) as total_resenas,
                (SELECT COUNT(*) FROM favoritos) as total_favoritos,
                (SELECT COUNT(*) FROM seguimientos) as total_seguimientos,
                (SELECT COUNT(*) FROM likes_resenas) as total_likes";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'total_usuarios' => (int)$datos['total_usuarios'],
        'total_peliculas' => (int)$datos['total_peliculas'],
        'total_resenas' => (int)$datos['total_resenas'],
        'total_favoritos' => (int)$datos['total_favoritos'],
        'total_seguimientos' => (int)$datos['total_seguimientos'],
        'total_likes' => (int)$datos['total_likes']
    ];
}

function obtenerEstadisticasPeliculas($db) {
    $sql = "SELECT 
                p.id, p.titulo, p.director,
                COUNT(DISTINCT r.id) as total_resenas,
                COUNT(DISTINCT f.id) as total_favoritos,
                COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio
            FROM peliculas p
            LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
            LEFT JOIN favoritos f ON p.id = f.id_pelicula
            WHERE p.activo = 1
            GROUP BY p.id, p.titulo, p.director
            ORDER BY (COUNT(DISTINCT r.id) + COUNT(DISTINCT f.id)) DESC
            LIMIT 10";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $peliculas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // formatear resultados
    foreach ($peliculas as &$pelicula) {
        $pelicula['puntuacion_promedio'] = round($pelicula['puntuacion_promedio'], 1);
        $pelicula['total_resenas'] = (int)$pelicula['total_resenas'];
        $pelicula['total_favoritos'] = (int)$pelicula['total_favoritos'];
    }
    
    return $peliculas;
}

function obtenerEstadisticasUsuarios($db) {
    $sql = "SELECT 
                u.id, u.nombre_usuario, u.nombre_completo,
                COUNT(DISTINCT p.id) as total_peliculas,
                COUNT(DISTINCT r.id) as total_resenas,
                COUNT(DISTINCT s.id) as total_seguidores
            FROM usuarios u
            LEFT JOIN peliculas p ON u.id = p.id_usuario_creador AND p.activo = 1
            LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = 1
            LEFT JOIN seguimientos s ON u.id = s.id_seguido
            WHERE u.activo = 1
            GROUP BY u.id
            ORDER BY (COUNT(DISTINCT p.id) + COUNT(DISTINCT r.id)) DESC
            LIMIT 10";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerEstadisticasResenas($db) {
    $sql = "SELECT 
                r.id, r.titulo as titulo_resena, r.puntuacion, r.likes,
                p.titulo as titulo_pelicula,
                u.nombre_usuario as autor
            FROM resenas r
            INNER JOIN peliculas p ON r.id_pelicula = p.id
            INNER JOIN usuarios u ON r.id_usuario = u.id
            WHERE r.activo = 1 AND p.activo = 1 AND u.activo = 1
            ORDER BY r.likes DESC, r.puntuacion DESC
            LIMIT 10";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerEstadisticasGeneros($db) {
    $sql = "SELECT 
                g.id,
                g.nombre as genero,
                g.descripcion,
                g.color_hex,
                COUNT(DISTINCT p.id) as total_peliculas,
                COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio
            FROM generos g
            LEFT JOIN peliculas p ON g.id = p.genero_id AND p.activo = 1
            LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
            WHERE g.activo = 1
            GROUP BY g.id, g.nombre, g.descripcion, g.color_hex
            ORDER BY total_peliculas DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerActividadReciente($db) {
    $sql = "SELECT 
                'resena' as tipo,
                r.id,
                r.titulo as contenido,
                u.nombre_usuario as usuario,
                r.fecha_resena as fecha,
                p.titulo as pelicula_relacionada
            FROM resenas r
            INNER JOIN usuarios u ON r.id_usuario = u.id
            INNER JOIN peliculas p ON r.id_pelicula = p.id
            WHERE r.activo = 1 AND u.activo = 1 AND p.activo = 1
            
            UNION ALL
            
            SELECT 
                'pelicula' as tipo,
                p.id,
                p.titulo as contenido,
                u.nombre_usuario as usuario,
                p.fecha_creacion as fecha,
                NULL as pelicula_relacionada
            FROM peliculas p
            INNER JOIN usuarios u ON p.id_usuario_creador = u.id
            WHERE p.activo = 1 AND u.activo = 1
            
            ORDER BY fecha DESC
            LIMIT 20";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $actividad = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // formatear resultados
    foreach ($actividad as &$item) {
        $item['fecha_formateada'] = Utils::timeAgo($item['fecha']);
    }
    
    return $actividad;
}

// funciones de búsqueda (para buscar/global.php)
function buscarPeliculas($db, $query, $limite) {
    $searchTerm = "%{$query}%";
    
    $sql = "SELECT 
                p.id, p.titulo, p.director, p.ano_lanzamiento,
                p.duracion_minutos, p.imagen_url,
                g.nombre as genero,
                u.nombre_usuario as creador,
                COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
                COUNT(DISTINCT r.id) as total_resenas,
                COUNT(DISTINCT f.id) as total_favoritos
            FROM peliculas p
            INNER JOIN generos g ON p.genero_id = g.id
            INNER JOIN usuarios u ON p.id_usuario_creador = u.id
            LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
            LEFT JOIN favoritos f ON p.id = f.id_pelicula
            WHERE p.activo = 1 AND u.activo = 1
            AND (p.titulo LIKE ? OR p.director LIKE ? OR p.sinopsis LIKE ?)
            GROUP BY p.id
            ORDER BY 
                CASE 
                    WHEN p.titulo LIKE ? THEN 1
                    WHEN p.director LIKE ? THEN 2
                    ELSE 3
                END,
                puntuacion_promedio DESC
            LIMIT ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $searchTerm, $searchTerm, $searchTerm, 
        $searchTerm, $searchTerm, 
        $limite
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarUsuarios($db, $query, $limite) {
    $searchTerm = "%{$query}%";
    
    $sql = "SELECT 
                u.id, u.nombre_usuario, u.nombre_completo, u.fecha_registro,
                COUNT(DISTINCT p.id) as total_peliculas,
                COUNT(DISTINCT r.id) as total_resenas,
                COUNT(DISTINCT s.id) as total_seguidores
            FROM usuarios u
            LEFT JOIN peliculas p ON u.id = p.id_usuario_creador AND p.activo = 1
            LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = 1
            LEFT JOIN seguimientos s ON u.id = s.id_seguido
            WHERE u.activo = 1
            AND (u.nombre_usuario LIKE ? OR u.nombre_completo LIKE ?)
            GROUP BY u.id
            ORDER BY 
                CASE 
                    WHEN u.nombre_usuario LIKE ? THEN 1
                    ELSE 2
                END,
                total_seguidores DESC
            LIMIT ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limite]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarResenas($db, $query, $limite) {
    $searchTerm = "%{$query}%";
    
    $sql = "SELECT 
                r.id, r.titulo as titulo_resena, r.texto_resena, r.puntuacion,
                r.fecha_resena, r.likes,
                p.titulo as titulo_pelicula, p.id as id_pelicula,
                u.nombre_usuario as autor, u.id as id_autor
            FROM resenas r
            INNER JOIN peliculas p ON r.id_pelicula = p.id
            INNER JOIN usuarios u ON r.id_usuario = u.id
            WHERE r.activo = 1 AND p.activo = 1 AND u.activo = 1
            AND (r.titulo LIKE ? OR r.texto_resena LIKE ?)
            ORDER BY r.likes DESC, r.fecha_resena DESC
            LIMIT ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$searchTerm, $searchTerm, $limite]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}