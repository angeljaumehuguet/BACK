<?php
/**
 * Gestion de Peliculas - Panel Administrativo
 * Archivo: cinefan/admin/peliculas.php
 * Autores: Juan Carlos y Angel Hernandez - DAM2
 * 
 * Aqui va toda la gestion de peliculas del sistema
 * Los admins pueden crear editar eliminar y ver todas las peliculas
 */

require_once 'includes/inicializar.php';

// verificar que este logueado
if (!Seguridad::estaAutenticado()) {
    header('Location: login.php?mensaje=acceso_denegado');
    exit;
}

// obtener parametros de URL
$accion = $_GET['accion'] ?? 'listar';
$idPelicula = $_GET['id'] ?? null;
$pagina = intval($_GET['pagina'] ?? 1);
$busqueda = trim($_GET['busqueda'] ?? '');
$filtroGenero = $_GET['genero'] ?? '';
$filtroAno = $_GET['ano'] ?? '';

// variables para formularios
$pelicula = null;
$error = '';
$mensaje = '';
$peliculas = [];
$totalPeliculas = 0;
$totalPaginas = 0;

try {
    $bd = new BaseDatosAdmin();
    
    // obtener generos para filtros y formularios
    $generos = $bd->obtenerTodos("SELECT * FROM generos WHERE activo = 1 ORDER BY nombre");
    
    // procesar las diferentes acciones
    switch ($accion) {
        case 'crear':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // obtener datos del formulario
                $datos = [
                    'titulo' => trim($_POST['titulo'] ?? ''),
                    'director' => trim($_POST['director'] ?? ''),
                    'ano_lanzamiento' => intval($_POST['ano_lanzamiento'] ?? 0),
                    'duracion_minutos' => intval($_POST['duracion_minutos'] ?? 0),
                    'sinopsis' => trim($_POST['sinopsis'] ?? ''),
                    'genero_id' => intval($_POST['genero_id'] ?? 0),
                    'imagen_url' => trim($_POST['imagen_url'] ?? ''),
                    'trailer_url' => trim($_POST['trailer_url'] ?? ''),
                    'pais_origen' => trim($_POST['pais_origen'] ?? ''),
                    'idioma_original' => trim($_POST['idioma_original'] ?? ''),
                    'presupuesto' => floatval($_POST['presupuesto'] ?? 0),
                    'recaudacion' => floatval($_POST['recaudacion'] ?? 0)
                ];
                
                // validaciones basicas
                if (empty($datos['titulo'])) {
                    $error = 'El titulo es obligatorio';
                } elseif (empty($datos['director'])) {
                    $error = 'El director es obligatorio';
                } elseif ($datos['ano_lanzamiento'] < AÑO_MIN || $datos['ano_lanzamiento'] > AÑO_MAX) {
                    $error = 'El año debe estar entre ' . AÑO_MIN . ' y ' . AÑO_MAX;
                } elseif ($datos['duracion_minutos'] < DURACION_MIN || $datos['duracion_minutos'] > DURACION_MAX) {
                    $error = 'La duración debe estar entre ' . DURACION_MIN . ' y ' . DURACION_MAX . ' minutos';
                } else {
                    // verificar que no exista una pelicula con mismo titulo y año
                    $existe = $bd->obtenerUno(
                        "SELECT id FROM peliculas WHERE titulo = ? AND ano_lanzamiento = ?",
                        [$datos['titulo'], $datos['ano_lanzamiento']]
                    );
                    
                    if ($existe) {
                        $error = 'Ya existe una pelicula con ese titulo y año';
                    } else {
                        // crear la pelicula
                        $bd->ejecutar(
                            "INSERT INTO peliculas (titulo, director, ano_lanzamiento, duracion_minutos, sinopsis, 
                             genero_id, imagen_url, trailer_url, pais_origen, idioma_original, presupuesto, 
                             recaudacion, fecha_agregada) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                            [
                                $datos['titulo'], $datos['director'], $datos['ano_lanzamiento'],
                                $datos['duracion_minutos'], $datos['sinopsis'], $datos['genero_id'],
                                $datos['imagen_url'], $datos['trailer_url'], $datos['pais_origen'],
                                $datos['idioma_original'], $datos['presupuesto'], $datos['recaudacion']
                            ]
                        );
                        
                        $mensaje = 'Pelicula creada correctamente';
                        
                        // registrar en logs
                        file_put_contents('logs/peliculas.log', 
                            date('Y-m-d H:i:s') . " - Pelicula creada: {$datos['titulo']} por admin\n", 
                            FILE_APPEND
                        );
                        
                        // limpiar formulario
                        $datos = [];
                    }
                }
                
                $pelicula = $datos; // mantener datos en caso de error
            }
            break;
            
        case 'editar':
            if (!$idPelicula) {
                $error = 'ID de pelicula no especificado';
                $accion = 'listar';
                break;
            }
            
            // obtener datos de la pelicula
            $pelicula = $bd->obtenerUno(
                "SELECT p.*, g.nombre as genero_nombre 
                 FROM peliculas p 
                 LEFT JOIN generos g ON p.genero_id = g.id 
                 WHERE p.id = ?",
                [$idPelicula]
            );
            
            if (!$pelicula) {
                $error = 'Pelicula no encontrada';
                $accion = 'listar';
                break;
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // procesar actualizacion
                $datos = [
                    'titulo' => trim($_POST['titulo'] ?? ''),
                    'director' => trim($_POST['director'] ?? ''),
                    'ano_lanzamiento' => intval($_POST['ano_lanzamiento'] ?? 0),
                    'duracion_minutos' => intval($_POST['duracion_minutos'] ?? 0),
                    'sinopsis' => trim($_POST['sinopsis'] ?? ''),
                    'genero_id' => intval($_POST['genero_id'] ?? 0),
                    'imagen_url' => trim($_POST['imagen_url'] ?? ''),
                    'trailer_url' => trim($_POST['trailer_url'] ?? ''),
                    'pais_origen' => trim($_POST['pais_origen'] ?? ''),
                    'idioma_original' => trim($_POST['idioma_original'] ?? ''),
                    'presupuesto' => floatval($_POST['presupuesto'] ?? 0),
                    'recaudacion' => floatval($_POST['recaudacion'] ?? 0)
                ];
                
                // validaciones
                if (empty($datos['titulo'])) {
                    $error = 'El titulo es obligatorio';
                } elseif (empty($datos['director'])) {
                    $error = 'El director es obligatorio';
                } elseif ($datos['ano_lanzamiento'] < AÑO_MIN || $datos['ano_lanzamiento'] > AÑO_MAX) {
                    $error = 'El año debe estar entre ' . AÑO_MIN . ' y ' . AÑO_MAX;
                } elseif ($datos['duracion_minutos'] < DURACION_MIN || $datos['duracion_minutos'] > DURACION_MAX) {
                    $error = 'La duración debe estar entre ' . DURACION_MIN . ' y ' . DURACION_MAX . ' minutos';
                } else {
                    // verificar que no exista otra pelicula con mismo titulo y año
                    $existe = $bd->obtenerUno(
                        "SELECT id FROM peliculas WHERE titulo = ? AND ano_lanzamiento = ? AND id != ?",
                        [$datos['titulo'], $datos['ano_lanzamiento'], $idPelicula]
                    );
                    
                    if ($existe) {
                        $error = 'Ya existe otra pelicula con ese titulo y año';
                    } else {
                        // actualizar la pelicula
                        $bd->ejecutar(
                            "UPDATE peliculas SET titulo = ?, director = ?, ano_lanzamiento = ?, 
                             duracion_minutos = ?, sinopsis = ?, genero_id = ?, imagen_url = ?, 
                             trailer_url = ?, pais_origen = ?, idioma_original = ?, presupuesto = ?, 
                             recaudacion = ? WHERE id = ?",
                            [
                                $datos['titulo'], $datos['director'], $datos['ano_lanzamiento'],
                                $datos['duracion_minutos'], $datos['sinopsis'], $datos['genero_id'],
                                $datos['imagen_url'], $datos['trailer_url'], $datos['pais_origen'],
                                $datos['idioma_original'], $datos['presupuesto'], $datos['recaudacion'],
                                $idPelicula
                            ]
                        );
                        
                        $mensaje = 'Pelicula actualizada correctamente';
                        
                        // actualizar datos en pantalla
                        $pelicula = array_merge($pelicula, $datos);
                        
                        // registrar en logs
                        file_put_contents('logs/peliculas.log', 
                            date('Y-m-d H:i:s') . " - Pelicula actualizada: {$datos['titulo']} por admin\n", 
                            FILE_APPEND
                        );
                    }
                }
            }
            break;
            
        case 'eliminar':
            if (!$idPelicula) {
                $error = 'ID de pelicula no especificado';
                break;
            }
            
            // obtener datos antes de eliminar
            $peliculaEliminar = $bd->obtenerUno("SELECT titulo FROM peliculas WHERE id = ?", [$idPelicula]);
            
            if (!$peliculaEliminar) {
                $error = 'Pelicula no encontrada';
            } else {
                // eliminar la pelicula
                $bd->ejecutar("DELETE FROM peliculas WHERE id = ?", [$idPelicula]);
                $mensaje = 'Pelicula eliminada correctamente';
                
                // registrar en logs
                file_put_contents('logs/peliculas.log', 
                    date('Y-m-d H:i:s') . " - Pelicula eliminada: {$peliculaEliminar['titulo']} por admin\n", 
                    FILE_APPEND
                );
            }
            
            $accion = 'listar';
            break;
    }
    
    // obtener listado de peliculas
    if ($accion === 'listar') {
        // construir filtros
        $where = ['1=1'];
        $params = [];
        
        if (!empty($busqueda)) {
            $where[] = "(p.titulo LIKE ? OR p.director LIKE ? OR p.sinopsis LIKE ?)";
            $params[] = "%$busqueda%";
            $params[] = "%$busqueda%";
            $params[] = "%$busqueda%";
        }
        
        if (!empty($filtroGenero)) {
            $where[] = "p.genero_id = ?";
            $params[] = $filtroGenero;
        }
        
        if (!empty($filtroAno)) {
            $where[] = "p.ano_lanzamiento = ?";
            $params[] = $filtroAno;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // contar total
        $totalPeliculas = $bd->obtenerUno(
            "SELECT COUNT(*) as total FROM peliculas p WHERE $whereClause",
            $params
        )['total'];
        
        // calcular paginacion
        $limite = TAMAÑO_PAGINA_DEFECTO;
        $offset = ($pagina - 1) * $limite;
        $totalPaginas = ceil($totalPeliculas / $limite);
        
        // obtener peliculas con estadisticas
        $peliculas = $bd->obtenerTodos(
            "SELECT p.*, g.nombre as genero_nombre,
                    COUNT(DISTINCT r.id) as total_resenas,
                    AVG(r.puntuacion) as puntuacion_promedio
             FROM peliculas p
             LEFT JOIN generos g ON p.genero_id = g.id
             LEFT JOIN resenas r ON p.id = r.id_pelicula
             WHERE $whereClause
             GROUP BY p.id
             ORDER BY p.fecha_agregada DESC
             LIMIT $limite OFFSET $offset",
            $params
        );
        
        // obtener años disponibles para filtro
        $anos = $bd->obtenerTodos(
            "SELECT DISTINCT ano_lanzamiento FROM peliculas ORDER BY ano_lanzamiento DESC"
        );
    }
    
} catch (Exception $e) {
    $error = 'Error en la base de datos: ' . $e->getMessage();
    file_put_contents('logs/errores_admin.log', 
        date('Y-m-d H:i:s') . " - Error peliculas: " . $e->getMessage() . "\n", 
        FILE_APPEND
    );
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Películas - CineFan Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* usar mismos estilos que en usuarios.php */
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); min-height: 100vh; width: 250px; position: fixed; top: 0; left: 0; z-index: 1000; padding-top: 20px; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar .logo { text-align: center; padding: 20px; color: white; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 20px; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; margin: 5px 15px; border-radius: 8px; transition: all 0.3s ease; display: flex; align-items: center; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.2); color: white; transform: translateX(5px); }
        .sidebar .nav-link i { width: 20px; margin-right: 10px; }
        .content { margin-left: 250px; padding: 20px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .table th { border-top: none; font-weight: 600; color: #495057; }
        .btn-admin { border-radius: 6px; padding: 6px 12px; font-size: 0.875rem; }
        .poster-pelicula { width: 60px; height: 90px; object-fit: cover; border-radius: 8px; }
        .rating-stars { color: #ffc107; }
        .filtros-form { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <!-- sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-film fa-2x mb-2"></i>
            <h4>CineFan Admin</h4>
            <small>v<?= VERSION_APP ?></small>
        </div>
        
        <nav class="nav flex-column">
            <a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a class="nav-link" href="usuarios.php"><i class="fas fa-users"></i> Usuarios</a>
            <a class="nav-link active" href="peliculas.php"><i class="fas fa-film"></i> Películas</a>
            <a class="nav-link" href="resenas.php"><i class="fas fa-star"></i> Reseñas</a>
            <a class="nav-link" href="generos.php"><i class="fas fa-tags"></i> Géneros</a>
            <a class="nav-link" href="reportes.php"><i class="fas fa-chart-bar"></i> Reportes</a>
            <a class="nav-link" href="pruebas/ejecutar_pruebas.php"><i class="fas fa-vial"></i> Tests & Pruebas</a>
            <hr style="border-color: rgba(255,255,255,0.2); margin: 20px 15px;">
            <a class="nav-link" href="configuracion.php"><i class="fas fa-cog"></i> Configuración</a>
            <a class="nav-link" href="cerrar_sesion.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
        </nav>
    </div>
    
    <div class="content">
        <!-- cabecera -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-film me-2"></i> Gestión de Películas</h2>
            <a href="peliculas.php?accion=crear" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Nueva Película
            </a>
        </div>
        
        <!-- mensajes -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($accion === 'listar'): ?>
            <!-- filtros -->
            <div class="card mb-4">
                <div class="card-body filtros-form">
                    <form method="GET" class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Buscar película</label>
                            <input type="text" class="form-control" name="busqueda" 
                                   value="<?= htmlspecialchars($busqueda) ?>" 
                                   placeholder="Título director sinopsis...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Género</label>
                            <select class="form-select" name="genero">
                                <option value="">Todos los géneros</option>
                                <?php foreach ($generos as $genero): ?>
                                    <option value="<?= $genero['id'] ?>" <?= $filtroGenero == $genero['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($genero['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Año</label>
                            <select class="form-select" name="ano">
                                <option value="">Todos</option>
                                <?php foreach ($anos as $ano): ?>
                                    <option value="<?= $ano['ano_lanzamiento'] ?>" 
                                            <?= $filtroAno == $ano['ano_lanzamiento'] ? 'selected' : '' ?>>
                                        <?= $ano['ano_lanzamiento'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- tabla de peliculas -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        Catálogo de Películas 
                        <span class="badge bg-secondary"><?= number_format($totalPeliculas) ?> total</span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($peliculas)): ?>
                        <div class="text-center p-5">
                            <i class="fas fa-film fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No se encontraron películas</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Película</th>
                                        <th>Director</th>
                                        <th>Año</th>
                                        <th>Género</th>
                                        <th>Duración</th>
                                        <th>Rating</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($peliculas as $peli): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($peli['imagen_url']): ?>
                                                        <img src="<?= htmlspecialchars($peli['imagen_url']) ?>" 
                                                             class="poster-pelicula me-3" 
                                                             alt="Poster"
                                                             onerror="this.src='https://via.placeholder.com/60x90?text=Sin+Imagen'">
                                                    <?php else: ?>
                                                        <div class="poster-pelicula bg-secondary d-flex align-items-center justify-content-center me-3">
                                                            <i class="fas fa-film text-white"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?= htmlspecialchars($peli['titulo']) ?></strong><br>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars(substr($peli['sinopsis'], 0, 60)) ?>
                                                            <?= strlen($peli['sinopsis']) > 60 ? '...' : '' ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($peli['director']) ?></td>
                                            <td><?= $peli['ano_lanzamiento'] ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= htmlspecialchars($peli['genero_nombre'] ?: 'Sin género') ?>
                                                </span>
                                            </td>
                                            <td><?= $peli['duracion_minutos'] ?> min</td>
                                            <td>
                                                <?php if ($peli['total_resenas'] > 0): ?>
                                                    <div class="rating-stars">
                                                        <?php 
                                                        $rating = round($peli['puntuacion_promedio'], 1);
                                                        for ($i = 1; $i <= 5; $i++): 
                                                        ?>
                                                            <i class="fas fa-star <?= $i <= $rating ? '' : 'text-muted' ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <small><?= $rating ?> (<?= $peli['total_resenas'] ?>)</small>
                                                <?php else: ?>
                                                    <small class="text-muted">Sin reseñas</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="peliculas.php?accion=editar&id=<?= $peli['id'] ?>" 
                                                       class="btn btn-outline-primary btn-admin">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="peliculas.php?accion=eliminar&id=<?= $peli['id'] ?>" 
                                                       class="btn btn-outline-danger btn-admin"
                                                       onclick="return confirm('¿Eliminar esta película?\n\nSe eliminarán también todas sus reseñas.')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- paginacion -->
                <?php if ($totalPaginas > 1): ?>
                    <div class="card-footer">
                        <nav>
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                    <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                                        <a class="page-link" href="peliculas.php?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&genero=<?= $filtroGenero ?>&ano=<?= $filtroAno ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
            
        <?php elseif ($accion === 'crear' || $accion === 'editar'): ?>
            <!-- formulario -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-<?= $accion === 'crear' ? 'plus' : 'edit' ?> me-2"></i>
                        <?= $accion === 'crear' ? 'Crear Película' : 'Editar Película' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Título <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="titulo" 
                                       value="<?= htmlspecialchars($pelicula['titulo'] ?? '') ?>" 
                                       required maxlength="<?= LONGITUD_MAX_TITULO ?>">
                                <div class="invalid-feedback">El título es obligatorio</div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Año Lanzamiento <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="ano_lanzamiento" 
                                       value="<?= $pelicula['ano_lanzamiento'] ?? date('Y') ?>" 
                                       required min="<?= AÑO_MIN ?>" max="<?= AÑO_MAX ?>">
                                <div class="invalid-feedback">Año entre <?= AÑO_MIN ?> y <?= AÑO_MAX ?></div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Director <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="director" 
                                       value="<?= htmlspecialchars($pelicula['director'] ?? '') ?>" 
                                       required maxlength="100">
                                <div class="invalid-feedback">El director es obligatorio</div>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Duración (min) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="duracion_minutos" 
                                       value="<?= $pelicula['duracion_minutos'] ?? '' ?>" 
                                       required min="<?= DURACION_MIN ?>" max="<?= DURACION_MAX ?>">
                                <div class="invalid-feedback">Entre <?= DURACION_MIN ?> y <?= DURACION_MAX ?> min</div>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Género</label>
                                <select class="form-select" name="genero_id">
                                    <option value="">Sin género</option>
                                    <?php foreach ($generos as $genero): ?>
                                        <option value="<?= $genero['id'] ?>" 
                                                <?= ($pelicula['genero_id'] ?? '') == $genero['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($genero['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Sinopsis</label>
                            <textarea class="form-control" name="sinopsis" rows="4" 
                                      maxlength="<?= LONGITUD_MAX_DESCRIPCION ?>"><?= htmlspecialchars($pelicula['sinopsis'] ?? '') ?></textarea>
                            <div class="form-text">Descripción de la película</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">URL Imagen/Poster</label>
                                <input type="url" class="form-control" name="imagen_url" 
                                       value="<?= htmlspecialchars($pelicula['imagen_url'] ?? '') ?>" 
                                       placeholder="https://ejemplo.com/imagen.jpg">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">URL Trailer</label>
                                <input type="url" class="form-control" name="trailer_url" 
                                       value="<?= htmlspecialchars($pelicula['trailer_url'] ?? '') ?>" 
                                       placeholder="https://youtube.com/watch?v=...">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">País Origen</label>
                                <input type="text" class="form-control" name="pais_origen" 
                                       value="<?= htmlspecialchars($pelicula['pais_origen'] ?? '') ?>" 
                                       maxlength="100">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Idioma Original</label>
                                <input type="text" class="form-control" name="idioma_original" 
                                       value="<?= htmlspecialchars($pelicula['idioma_original'] ?? '') ?>" 
                                       maxlength="50">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Presupuesto ($)</label>
                                <input type="number" class="form-control" name="presupuesto" 
                                       value="<?= $pelicula['presupuesto'] ?? '' ?>" 
                                       min="0" step="0.01">
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>
                                <?= $accion === 'crear' ? 'Crear Película' : 'Actualizar Película' ?>
                            </button>
                            <a href="peliculas.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i> Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // validacion de formularios
        (function() {
            'use strict';
            
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>