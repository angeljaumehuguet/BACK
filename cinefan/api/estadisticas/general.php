<?php
require_once '../config/database.php';
require_once '../config/cors.php';
require_once '../includes/auth.php';
require_once '../includes/response.php';

// solo permitir metodo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    enviarRespuesta(false, 'metodo no permitido', null, 405);
    exit;
}

try {
    // verificar autenticacion
    $datosToken = verificarToken();
    if (!$datosToken) {
        enviarRespuesta(false, 'token invalido o expirado', null, 401);
        return;
    }

    $db = conectarDB();
    $estadisticas = [];

    // estadisticas generales del sistema
    $estadisticas['sistema'] = obtenerEstadisticasSistema($db);
    
    // estadisticas de peliculas
    $estadisticas['peliculas'] = obtenerEstadisticasPeliculas($db);
    
    // estadisticas de usuarios
    $estadisticas['usuarios'] = obtenerEstadisticasUsuarios($db);
    
    // estadisticas de resenas
    $estadisticas['resenas'] = obtenerEstadisticasResenas($db);
    
    // estadisticas de generos
    $estadisticas['generos'] = obtenerEstadisticasGeneros($db);
    
    // actividad reciente
    $estadisticas['actividad_reciente'] = obtenerActividadReciente($db);

    enviarRespuesta(true, 'estadisticas obtenidas exitosamente', $estadisticas);

} catch (Exception $e) {
    error_log("error obteniendo estadisticas generales: " . $e->getMessage());
    enviarRespuesta(false, 'error interno del servidor', null, 500);
}

// obtener estadisticas generales del sistema
function obtenerEstadisticasSistema($db) {
    $sql = "SELECT 
                (SELECT COUNT(*) FROM usuarios WHERE activo = 1) as total_usuarios,
                (SELECT COUNT(*) FROM peliculas WHERE activo = 1) as total_peliculas,
                (SELECT COUNT(*) FROM resenas WHERE activo = 1) as total_resenas,
                (SELECT COUNT(*) FROM favoritos WHERE activo = 1) as total_favoritos,
                (SELECT COUNT(*) FROM seguimientos WHERE activo = 1) as total_seguimientos,
                (SELECT COUNT(*) FROM likes_resenas WHERE activo = 1) as total_likes";

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

// obtener estadisticas de peliculas
function obtenerEstadisticasPeliculas($db) {
    // peliculas mas populares
    $sqlPopulares = "SELECT 
                        p.id, p.titulo, p.director, p.puntuacion_promedio,
                        COUNT(r.id) as total_resenas,
                        COUNT(f.id) as total_favoritos
                     FROM peliculas p
                     LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
                     LEFT JOIN favoritos f ON p.id = f.id_pelicula AND f.activo = 1
                     WHERE p.activo = 1
                     GROUP BY p.id
                     ORDER BY (COUNT(r.id) + COUNT(f.id)) DESC, p.puntuacion_promedio DESC
                     LIMIT 5";

    $stmtPopulares = $db->prepare($sqlPopulares);
    $stmtPopulares->execute();
    $populares = $stmtPopulares->fetchAll(PDO::FETCH_ASSOC);

    // peliculas mejor puntuadas
    $sqlMejorPuntuadas = "SELECT 
                            p.id, p.titulo, p.director, p.puntuacion_promedio,
                            COUNT(r.id) as total_resenas
                          FROM peliculas p
                          LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
                          WHERE p.activo = 1 AND p.puntuacion_promedio > 0
                          GROUP BY p.id
                          HAVING total_resenas >= 3
                          ORDER BY p.puntuacion_promedio DESC, total_resenas DESC
                          LIMIT 5";

    $stmtMejorPuntuadas = $db->prepare($sqlMejorPuntuadas);
    $stmtMejorPuntuadas->execute();
    $mejorPuntuadas = $stmtMejorPuntuadas->fetchAll(PDO::FETCH_ASSOC);

    // estadisticas por ano
    $sqlPorAno = "SELECT 
                    ano_lanzamiento,
                    COUNT(*) as total_peliculas,
                    ROUND(AVG(puntuacion_promedio), 1) as puntuacion_promedio
                  FROM peliculas 
                  WHERE activo = 1 AND ano_lanzamiento >= YEAR(CURDATE()) - 10
                  GROUP BY ano_lanzamiento
                  ORDER BY ano_lanzamiento DESC
                  LIMIT 10";

    $stmtPorAno = $db->prepare($sqlPorAno);
    $stmtPorAno->execute();
    $porAno = $stmtPorAno->fetchAll(PDO::FETCH_ASSOC);

    return [
        'mas_populares' => array_map('formatearPeliculaEstadistica', $populares),
        'mejor_puntuadas' => array_map('formatearPeliculaEstadistica', $mejorPuntuadas),
        'por_ano' => array_map(function($item) {
            return [
                'ano' => (int)$item['ano_lanzamiento'],
                'total_peliculas' => (int)$item['total_peliculas'],
                'puntuacion_promedio' => (float)$item['puntuacion_promedio']
            ];
        }, $porAno)
    ];
}

// obtener estadisticas de usuarios
function obtenerEstadisticasUsuarios($db) {
    // usuarios mas activos
    $sqlActivos = "SELECT 
                     u.id, u.nombre_usuario, u.nombre_completo,
                     COUNT(DISTINCT p.id) as total_peliculas,
                     COUNT(DISTINCT r.id) as total_resenas,
                     COUNT(DISTINCT s.id) as total_seguidores
                   FROM usuarios u
                   LEFT JOIN peliculas p ON u.id = p.id_usuario AND p.activo = 1
                   LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = 1
                   LEFT JOIN seguimientos s ON u.id = s.id_seguido AND s.activo = 1
                   WHERE u.activo = 1
                   GROUP BY u.id
                   ORDER BY (total_peliculas + total_resenas) DESC, total_seguidores DESC
                   LIMIT 5";

    $stmtActivos = $db->prepare($sqlActivos);
    $stmtActivos->execute();
    $activos = $stmtActivos->fetchAll(PDO::FETCH_ASSOC);

    // nuevos usuarios (ultimos 30 dias)
    $sqlNuevos = "SELECT COUNT(*) as total 
                  FROM usuarios 
                  WHERE activo = 1 AND fecha_registro >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    
    $stmtNuevos = $db->prepare($sqlNuevos);
    $stmtNuevos->execute();
    $nuevosUsuarios = $stmtNuevos->fetchColumn();

    return [
        'mas_activos' => array_map(function($usuario) {
            return [
                'id' => (int)$usuario['id'],
                'nombre_usuario' => $usuario['nombre_usuario'],
                'nombre_completo' => $usuario['nombre_completo'],
                'total_peliculas' => (int)$usuario['total_peliculas'],
                'total_resenas' => (int)$usuario['total_resenas'],
                'total_seguidores' => (int)$usuario['total_seguidores']
            ];
        }, $activos),
        'nuevos_ultimos_30_dias' => (int)$nuevosUsuarios
    ];
}

// obtener estadisticas de resenas
function obtenerEstadisticasResenas($db) {
    // resenas con mas likes
    $sqlMasLikes = "SELECT 
                      r.id, r.texto_resena, r.puntuacion, r.likes, r.dislikes,
                      u.nombre_usuario as autor,
                      p.titulo as pelicula_titulo
                    FROM resenas r
                    INNER JOIN usuarios u ON r.id_usuario = u.id
                    INNER JOIN peliculas p ON r.id_pelicula = p.id
                    WHERE r.activo = 1 AND u.activo = 1 AND p.activo = 1
                    ORDER BY r.likes DESC, r.fecha_creacion DESC
                    LIMIT 5";

    $stmtMasLikes = $db->prepare($sqlMasLikes);
    $stmtMasLikes->execute();
    $masLikes = $stmtMasLikes->fetchAll(PDO::FETCH_ASSOC);

    // actividad de resenas por mes
    $sqlPorMes = "SELECT 
                    YEAR(fecha_creacion) as ano,
                    MONTH(fecha_creacion) as mes,
                    COUNT(*) as total_resenas,
                    ROUND(AVG(puntuacion), 1) as puntuacion_promedio
                  FROM resenas 
                  WHERE activo = 1 AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  GROUP BY YEAR(fecha_creacion), MONTH(fecha_creacion)
                  ORDER BY ano DESC, mes DESC
                  LIMIT 12";

    $stmtPorMes = $db->prepare($sqlPorMes);
    $stmtPorMes->execute();
    $porMes = $stmtPorMes->fetchAll(PDO::FETCH_ASSOC);

    return [
        'con_mas_likes' => array_map(function($resena) {
            return [
                'id' => (int)$resena['id'],
                'texto_preview' => substr($resena['texto_resena'], 0, 100) . '...',
                'puntuacion' => (int)$resena['puntuacion'],
                'likes' => (int)$resena['likes'],
                'dislikes' => (int)$resena['dislikes'],
                'autor' => $resena['autor'],
                'pelicula_titulo' => $resena['pelicula_titulo']
            ];
        }, $masLikes),
        'por_mes' => array_map(function($item) {
            return [
                'ano' => (int)$item['ano'],
                'mes' => (int)$item['mes'],
                'total_resenas' => (int)$item['total_resenas'],
                'puntuacion_promedio' => (float)$item['puntuacion_promedio']
            ];
        }, $porMes)
    ];
}

// obtener estadisticas de generos
function obtenerEstadisticasGeneros($db) {
    $sql = "SELECT 
                genero,
                COUNT(*) as total_peliculas,
                ROUND(AVG(puntuacion_promedio), 1) as puntuacion_promedio,
                COUNT(DISTINCT id_usuario) as usuarios_contribuyeron
            FROM peliculas 
            WHERE activo = 1
            GROUP BY genero
            ORDER BY total_peliculas DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $generos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(function($genero) {
        return [
            'nombre' => $genero['genero'],
            'total_peliculas' => (int)$genero['total_peliculas'],
            'puntuacion_promedio' => (float)$genero['puntuacion_promedio'],
            'usuarios_contribuyeron' => (int)$genero['usuarios_contribuyeron']
        ];
    }, $generos);
}

// obtener actividad reciente
function obtenerActividadReciente($db) {
    // ultimas peliculas agregadas
    $sqlPeliculas = "SELECT p.titulo, p.director, u.nombre_usuario, p.fecha_creacion
                     FROM peliculas p
                     INNER JOIN usuarios u ON p.id_usuario = u.id
                     WHERE p.activo = 1 AND u.activo = 1
                     ORDER BY p.fecha_creacion DESC
                     LIMIT 5";

    $stmtPeliculas = $db->prepare($sqlPeliculas);
    $stmtPeliculas->execute();
    $ultimasPeliculas = $stmtPeliculas->fetchAll(PDO::FETCH_ASSOC);

    // ultimas resenas
    $sqlResenas = "SELECT 
                     SUBSTR(r.texto_resena, 1, 50) as texto_preview,
                     p.titulo as pelicula,
                     u.nombre_usuario,
                     r.fecha_creacion
                   FROM resenas r
                   INNER JOIN peliculas p ON r.id_pelicula = p.id
                   INNER JOIN usuarios u ON r.id_usuario = u.id
                   WHERE r.activo = 1 AND p.activo = 1 AND u.activo = 1
                   ORDER BY r.fecha_creacion DESC
                   LIMIT 5";

    $stmtResenas = $db->prepare($sqlResenas);
    $stmtResenas->execute();
    $ultimasResenas = $stmtResenas->fetchAll(PDO::FETCH_ASSOC);

    return [
        'ultimas_peliculas' => $ultimasPeliculas,
        'ultimas_resenas' => array_map(function($resena) {
            return [
                'texto_preview' => $resena['texto_preview'] . '...',
                'pelicula' => $resena['pelicula'],
                'autor' => $resena['nombre_usuario'],
                'fecha' => $resena['fecha_creacion']
            ];
        }, $ultimasResenas)
    ];
}

// funcion auxiliar para formatear datos de peliculas
function formatearPeliculaEstadistica($pelicula) {
    return [
        'id' => (int)$pelicula['id'],
        'titulo' => $pelicula['titulo'],
        'director' => $pelicula['director'],
        'puntuacion_promedio' => round($pelicula['puntuacion_promedio'], 1),
        'total_resenas' => isset($pelicula['total_resenas']) ? (int)$pelicula['total_resenas'] : 0,
        'total_favoritos' => isset($pelicula['total_favoritos']) ? (int)$pelicula['total_favoritos'] : 0
    ];
}
?>