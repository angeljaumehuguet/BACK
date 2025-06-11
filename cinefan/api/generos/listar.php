<?php
require_once '../config/database.php';
require_once '../config/cors.php';
require_once '../includes/response.php';

// solo permitir metodo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    enviarRespuesta(false, 'metodo no permitido', null, 405);
    exit;
}

try {
    $db = conectarDB();

    // obtener generos con estadisticas
    $sql = "SELECT 
                g.nombre as genero,
                COUNT(p.id) as total_peliculas,
                COALESCE(AVG(p.puntuacion_promedio), 0) as puntuacion_promedio,
                COUNT(DISTINCT p.id_usuario) as usuarios_contribuyeron
            FROM (
                SELECT 'Acción' as nombre
                UNION SELECT 'Aventura'
                UNION SELECT 'Comedia'
                UNION SELECT 'Drama'
                UNION SELECT 'Terror'
                UNION SELECT 'Ciencia Ficción'
                UNION SELECT 'Romance'
                UNION SELECT 'Thriller'
                UNION SELECT 'Animación'
                UNION SELECT 'Documental'
                UNION SELECT 'Musical'
                UNION SELECT 'Western'
                UNION SELECT 'Fantasía'
            ) g
            LEFT JOIN peliculas p ON g.nombre = p.genero AND p.activo = 1
            GROUP BY g.nombre
            ORDER BY total_peliculas DESC, g.nombre ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $generos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // formatear datos
    $generosFormateados = [];
    foreach ($generos as $genero) {
        $generosFormateados[] = [
            'nombre' => $genero['genero'],
            'total_peliculas' => (int)$genero['total_peliculas'],
            'puntuacion_promedio' => round($genero['puntuacion_promedio'], 1),
            'usuarios_contribuyeron' => (int)$genero['usuarios_contribuyeron']
        ];
    }

    enviarRespuesta(true, 'generos obtenidos exitosamente', [
        'generos' => $generosFormateados,
        'total' => count($generosFormateados)
    ]);

} catch (Exception $e) {
    error_log("error obteniendo generos: " . $e->getMessage());
    enviarRespuesta(false, 'error interno del servidor', null, 500);
}
?>

---

<?php
// api/buscar/global.php - busqueda global en peliculas, usuarios y resenas

require_once '../config/database.php';
require_once '../config/cors.php';
require_once '../includes/auth.php';
require_once '../includes/response.php';

// solo permitir metodo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    enviarRespuesta(false, 'metodo no permitido', null, 405);
    exit;
}

// obtener parametros de busqueda
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos'; // todos, peliculas, usuarios, resenas
$limite = isset($_GET['limite']) ? min((int)$_GET['limite'], 50) : 20;

if (empty($query)) {
    enviarRespuesta(false, 'parametro de busqueda requerido', null, 400);
    exit;
}

if (strlen($query) < 2) {
    enviarRespuesta(false, 'la busqueda debe tener al menos 2 caracteres', null, 400);
    exit;
}

try {
    $db = conectarDB();
    $resultados = [];

    // buscar en peliculas
    if ($tipo === 'todos' || $tipo === 'peliculas') {
        $resultados['peliculas'] = buscarPeliculas($db, $query, $limite);
    }

    // buscar en usuarios
    if ($tipo === 'todos' || $tipo === 'usuarios') {
        $resultados['usuarios'] = buscarUsuarios($db, $query, $limite);
    }

    // buscar en resenas
    if ($tipo === 'todos' || $tipo === 'resenas') {
        $resultados['resenas'] = buscarResenas($db, $query, $limite);
    }

    // calcular totales
    $totalResultados = 0;
    foreach ($resultados as $categoria) {
        $totalResultados += count($categoria);
    }

    enviarRespuesta(true, 'busqueda completada exitosamente', [
        'query' => $query,
        'tipo' => $tipo,
        'resultados' => $resultados,
        'total_resultados' => $totalResultados
    ]);

} catch (Exception $e) {
    error_log("error en busqueda global: " . $e->getMessage());
    enviarRespuesta(false, 'error interno del servidor', null, 500);
}

// buscar en peliculas
function buscarPeliculas($db, $query, $limite) {
    $sql = "SELECT 
                p.id,
                p.titulo,
                p.director,
                p.ano_lanzamiento,
                p.genero,
                p.duracion_minutos,
                p.imagen_url,
                p.puntuacion_promedio,
                u.nombre_usuario as creador,
                (SELECT COUNT(*) FROM resenas r WHERE r.id_pelicula = p.id AND r.activo = 1) as total_resenas,
                (SELECT COUNT(*) FROM favoritos f WHERE f.id_pelicula = p.id AND f.activo = 1) as total_favoritos
            FROM peliculas p
            INNER JOIN usuarios u ON p.id_usuario = u.id
            WHERE p.activo = 1 
            AND (
                p.titulo LIKE ? 
                OR p.director LIKE ? 
                OR p.genero LIKE ?
                OR p.sinopsis LIKE ?
            )
            ORDER BY 
                CASE 
                    WHEN p.titulo LIKE ? THEN 1
                    WHEN p.director LIKE ? THEN 2
                    ELSE 3
                END,
                p.puntuacion_promedio DESC,
                total_resenas DESC
            LIMIT ?";

    $searchTerm = "%{$query}%";
    $titleMatch = "{$query}%";
    $directorMatch = "{$query}%";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $searchTerm, $searchTerm, $searchTerm, $searchTerm,
        $titleMatch, $directorMatch, $limite
    ]);

    $peliculas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // formatear resultados
    $peliculasFormateadas = [];
    foreach ($peliculas as $pelicula) {
        $peliculasFormateadas[] = [
            'id' => (int)$pelicula['id'],
            'titulo' => $pelicula['titulo'],
            'director' => $pelicula['director'],
            'ano_lanzamiento' => (int)$pelicula['ano_lanzamiento'],
            'genero' => $pelicula['genero'],
            'duracion_minutos' => (int)$pelicula['duracion_minutos'],
            'imagen_url' => $pelicula['imagen_url'],
            'puntuacion_promedio' => round($pelicula['puntuacion_promedio'], 1),
            'creador' => $pelicula['creador'],
            'total_resenas' => (int)$pelicula['total_resenas'],
            'total_favoritos' => (int)$pelicula['total_favoritos']
        ];
    }

    return $peliculasFormateadas;
}

// buscar en usuarios
function buscarUsuarios($db, $query, $limite) {
    $sql = "SELECT 
                u.id,
                u.nombre_usuario,
                u.nombre_completo,
                u.email,
                u.fecha_registro,
                (SELECT COUNT(*) FROM peliculas WHERE id_usuario = u.id AND activo = 1) as total_peliculas,
                (SELECT COUNT(*) FROM resenas WHERE id_usuario = u.id AND activo = 1) as total_resenas,
                (SELECT COUNT(*) FROM seguimientos WHERE id_seguido = u.id AND activo = 1) as total_seguidores
            FROM usuarios u
            WHERE u.activo = 1 
            AND (
                u.nombre_usuario LIKE ? 
                OR u.nombre_completo LIKE ?
                OR u.email LIKE ?
            )
            ORDER BY 
                CASE 
                    WHEN u.nombre_usuario LIKE ? THEN 1
                    WHEN u.nombre_completo LIKE ? THEN 2
                    ELSE 3
                END,
                total_resenas DESC,
                total_seguidores DESC
            LIMIT ?";

    $searchTerm = "%{$query}%";
    $usernameMatch = "{$query}%";
    $nameMatch = "{$query}%";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $searchTerm, $searchTerm, $searchTerm,
        $usernameMatch, $nameMatch, $limite
    ]);

    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // formatear resultados (sin mostrar email completo por privacidad)
    $usuariosFormateados = [];
    foreach ($usuarios as $usuario) {
        $usuariosFormateados[] = [
            'id' => (int)$usuario['id'],
            'nombre_usuario' => $usuario['nombre_usuario'],
            'nombre_completo' => $usuario['nombre_completo'],
            'email_publico' => substr($usuario['email'], 0, 3) . '***@' . substr(strrchr($usuario['email'], '@'), 1),
            'fecha_registro' => $usuario['fecha_registro'],
            'estadisticas' => [
                'total_peliculas' => (int)$usuario['total_peliculas'],
                'total_resenas' => (int)$usuario['total_resenas'],
                'total_seguidores' => (int)$usuario['total_seguidores']
            ]
        ];
    }

    return $usuariosFormateados;
}

// buscar en resenas
function buscarResenas($db, $query, $limite) {
    $sql = "SELECT 
                r.id,
                r.texto_resena,
                r.puntuacion,
                r.fecha_creacion,
                r.likes,
                r.dislikes,
                u.nombre_usuario as autor,
                p.titulo as pelicula_titulo,
                p.id as pelicula_id,
                p.director as pelicula_director,
                p.ano_lanzamiento as pelicula_ano
            FROM resenas r
            INNER JOIN usuarios u ON r.id_usuario = u.id
            INNER JOIN peliculas p ON r.id_pelicula = p.id
            WHERE r.activo = 1 
            AND p.activo = 1
            AND u.activo = 1
            AND r.texto_resena LIKE ?
            ORDER BY 
                r.likes DESC,
                r.fecha_creacion DESC
            LIMIT ?";

    $searchTerm = "%{$query}%";

    $stmt = $db->prepare($sql);
    $stmt->execute([$searchTerm, $limite]);

    $resenas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // formatear resultados
    $resenasFormateadas = [];
    foreach ($resenas as $resena) {
        // resaltar termino de busqueda en el texto
        $textoResaltado = str_ireplace($query, "<mark>{$query}</mark>", $resena['texto_resena']);
        
        $resenasFormateadas[] = [
            'id' => (int)$resena['id'],
            'texto_resena' => $resena['texto_resena'],
            'texto_resaltado' => $textoResaltado,
            'puntuacion' => (int)$resena['puntuacion'],
            'fecha_creacion' => $resena['fecha_creacion'],
            'likes' => (int)$resena['likes'],
            'dislikes' => (int)$resena['dislikes'],
            'autor' => $resena['autor'],
            'pelicula' => [
                'id' => (int)$resena['pelicula_id'],
                'titulo' => $resena['pelicula_titulo'],
                'director' => $resena['pelicula_director'],
                'ano_lanzamiento' => (int)$resena['pelicula_ano']
            ]
        ];
    }

    return $resenasFormateadas;
}
?>