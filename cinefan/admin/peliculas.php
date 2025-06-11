<?php
session_start();

// verificar sesion de administrador
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../api/config/database.php';
require_once 'includes/pdf_generator.php';

// manejar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'activar':
            activarPelicula($_POST['id']);
            break;
        case 'desactivar':
            desactivarPelicula($_POST['id']);
            break;
        case 'eliminar':
            eliminarPelicula($_POST['id']);
            break;
    }
    
    // redireccionar para evitar reenvio de formulario
    header('Location: peliculas.php');
    exit;
}

// generar pdf si se solicita
if (isset($_GET['generar_pdf'])) {
    generarPDFPeliculas();
    exit;
}

// obtener parametros de filtrado y paginacion
$filtroGenero = $_GET['genero'] ?? '';
$filtroEstado = $_GET['estado'] ?? 'todos';
$busqueda = $_GET['busqueda'] ?? '';
$pagina = max(1, $_GET['pagina'] ?? 1);
$elementosPorPagina = 10;
$offset = ($pagina - 1) * $elementosPorPagina;

// obtener peliculas con filtros
$db = conectarDB();
$peliculas = obtenerPeliculasAdmin($db, $filtroGenero, $filtroEstado, $busqueda, $elementosPorPagina, $offset);
$totalPeliculas = contarPeliculasAdmin($db, $filtroGenero, $filtroEstado, $busqueda);
$totalPaginas = ceil($totalPeliculas / $elementosPorPagina);

// obtener estadisticas
$estadisticas = obtenerEstadisticasPeliculasAdmin($db);

// obtener generos para filtro
$generos = obtenerGenerosDisponibles($db);

include 'includes/header.php';
?>

<div class="admin-content">
    <div class="content-header">
        <h1><i class="fas fa-film"></i> Gestión de Películas</h1>
        <div class="header-actions">
            <button onclick="generarPDF()" class="btn btn-pdf">
                <i class="fas fa-file-pdf"></i> Generar PDF
            </button>
            <button onclick="refrescar()" class="btn btn-secondary">
                <i class="fas fa-sync-alt"></i> Refrescar
            </button>
        </div>
    </div>

    <!-- estadisticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-film"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($estadisticas['total_peliculas']); ?></h3>
                <p>Total Películas</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon active">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($estadisticas['peliculas_activas']); ?></h3>
                <p>Activas</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon inactive">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($estadisticas['peliculas_inactivas']); ?></h3>
                <p>Inactivas</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($estadisticas['puntuacion_promedio'], 1); ?></h3>
                <p>Puntuación Promedio</p>
            </div>
        </div>
    </div>

    <!-- filtros -->
    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <label for="busqueda">Buscar:</label>
                <input type="text" id="busqueda" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" 
                       placeholder="Título, director...">
            </div>
            
            <div class="filter-group">
                <label for="genero">Género:</label>
                <select id="genero" name="genero">
                    <option value="">Todos los géneros</option>
                    <?php foreach ($generos as $genero): ?>
                        <option value="<?php echo htmlspecialchars($genero); ?>" 
                                <?php echo $filtroGenero === $genero ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($genero); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="estado">Estado:</label>
                <select id="estado" name="estado">
                    <option value="todos" <?php echo $filtroEstado === 'todos' ? 'selected' : ''; ?>>Todos</option>
                    <option value="activos" <?php echo $filtroEstado === 'activos' ? 'selected' : ''; ?>>Activos</option>
                    <option value="inactivos" <?php echo $filtroEstado === 'inactivos' ? 'selected' : ''; ?>>Inactivos</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filtrar
            </button>
            
            <a href="peliculas.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Limpiar
            </a>
        </form>
    </div>

    <!-- tabla de peliculas -->
    <div class="table-section">
        <div class="table-header">
            <h3>Películas (<?php echo number_format($totalPeliculas); ?> total)</h3>
            <div class="table-actions">
                <span class="showing-info">
                    Mostrando <?php echo $offset + 1; ?>-<?php echo min($offset + $elementosPorPagina, $totalPeliculas); ?> 
                    de <?php echo number_format($totalPeliculas); ?>
                </span>
            </div>
        </div>

        <?php if (empty($peliculas)): ?>
            <div class="empty-state">
                <i class="fas fa-film"></i>
                <h3>No se encontraron películas</h3>
                <p>No hay películas que coincidan con los filtros seleccionados.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Película</th>
                            <th>Director</th>
                            <th>Año</th>
                            <th>Género</th>
                            <th>Creador</th>
                            <th>Puntuación</th>
                            <th>Reseñas</th>
                            <th>Estado</th>
                            <th>Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($peliculas as $pelicula): ?>
                            <tr>
                                <td><?php echo $pelicula['id']; ?></td>
                                <td>
                                    <div class="movie-info">
                                        <?php if ($pelicula['imagen_url']): ?>
                                            <img src="<?php echo htmlspecialchars($pelicula['imagen_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($pelicula['titulo']); ?>"
                                                 class="movie-thumb">
                                        <?php endif; ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($pelicula['titulo']); ?></strong>
                                            <?php if ($pelicula['duracion_minutos']): ?>
                                                <small><?php echo $pelicula['duracion_minutos']; ?> min</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($pelicula['director']); ?></td>
                                <td><?php echo $pelicula['ano_lanzamiento']; ?></td>
                                <td>
                                    <span class="badge badge-genre">
                                        <?php echo htmlspecialchars($pelicula['genero']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($pelicula['creador']); ?></td>
                                <td>
                                    <div class="rating">
                                        <span class="stars">
                                            <?php 
                                            $rating = round($pelicula['puntuacion_promedio']);
                                            for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $rating ? 'active' : ''; ?>"></i>
                                            <?php endfor; ?>
                                        </span>
                                        <small><?php echo number_format($pelicula['puntuacion_promedio'], 1); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-count">
                                        <?php echo $pelicula['total_resenas']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $pelicula['activo'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $pelicula['activo'] ? 'Activa' : 'Inactiva'; ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo date('d/m/Y H:i', strtotime($pelicula['fecha_creacion'])); ?></small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($pelicula['activo']): ?>
                                            <button onclick="confirmarAccion('desactivar', <?php echo $pelicula['id']; ?>, '<?php echo htmlspecialchars($pelicula['titulo']); ?>')" 
                                                    class="btn btn-sm btn-warning" title="Desactivar">
                                                <i class="fas fa-pause"></i>
                                            </button>
                                        <?php else: ?>
                                            <button onclick="confirmarAccion('activar', <?php echo $pelicula['id']; ?>, '<?php echo htmlspecialchars($pelicula['titulo']); ?>')" 
                                                    class="btn btn-sm btn-success" title="Activar">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button onclick="verDetalle(<?php echo $pelicula['id']; ?>)" 
                                                class="btn btn-sm btn-info" title="Ver Detalles">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <button onclick="confirmarAccion('eliminar', <?php echo $pelicula['id']; ?>, '<?php echo htmlspecialchars($pelicula['titulo']); ?>')" 
                                                class="btn btn-sm btn-danger" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- paginacion -->
            <?php if ($totalPaginas > 1): ?>
                <div class="pagination-section">
                    <nav class="pagination">
                        <?php if ($pagina > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>" 
                               class="page-link">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>

                        <?php
                        $inicio = max(1, $pagina - 2);
                        $fin = min($totalPaginas, $pagina + 2);
                        
                        for ($i = $inicio; $i <= $fin; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" 
                               class="page-link <?php echo $i === $pagina ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($pagina < $totalPaginas): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>" 
                               class="page-link">
                                Siguiente <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- modal de confirmacion -->
<div id="modalConfirmacion" class="modal">
    <div class="modal-content">
        <h3 id="modalTitulo">Confirmar Acción</h3>
        <p id="modalMensaje"></p>
        <div class="modal-actions">
            <button onclick="cerrarModal()" class="btn btn-secondary">Cancelar</button>
            <button onclick="ejecutarAccion()" class="btn btn-danger" id="btnConfirmar">Confirmar</button>
        </div>
    </div>
</div>

<script>
let accionPendiente = null;
let idPendiente = null;

function confirmarAccion(accion, id, titulo) {
    accionPendiente = accion;
    idPendiente = id;
    
    const modal = document.getElementById('modalConfirmacion');
    const modalTitulo = document.getElementById('modalTitulo');
    const modalMensaje = document.getElementById('modalMensaje');
    const btnConfirmar = document.getElementById('btnConfirmar');
    
    let mensaje = '';
    let botonTexto = '';
    let botonClase = 'btn-danger';
    
    switch (accion) {
        case 'activar':
            mensaje = `¿Estás seguro de que quieres activar la película "${titulo}"?`;
            botonTexto = 'Activar';
            botonClase = 'btn-success';
            break;
        case 'desactivar':
            mensaje = `¿Estás seguro de que quieres desactivar la película "${titulo}"?`;
            botonTexto = 'Desactivar';
            botonClase = 'btn-warning';
            break;
        case 'eliminar':
            mensaje = `¿Estás seguro de que quieres eliminar permanentemente la película "${titulo}"? Esta acción no se puede deshacer.`;
            botonTexto = 'Eliminar';
            botonClase = 'btn-danger';
            break;
    }
    
    modalTitulo.textContent = `Confirmar ${accion.charAt(0).toUpperCase() + accion.slice(1)}`;
    modalMensaje.textContent = mensaje;
    btnConfirmar.textContent = botonTexto;
    btnConfirmar.className = `btn ${botonClase}`;
    
    modal.style.display = 'block';
}

function ejecutarAccion() {
    if (accionPendiente && idPendiente) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="accion" value="${accionPendiente}">
            <input type="hidden" name="id" value="${idPendiente}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function cerrarModal() {
    document.getElementById('modalConfirmacion').style.display = 'none';
    accionPendiente = null;
    idPendiente = null;
}

function verDetalle(id) {
    // abrir en nueva ventana los detalles de la pelicula
    window.open(`../api/peliculas/detalle.php?id=${id}`, '_blank');
}

function generarPDF() {
    const params = new URLSearchParams(window.location.search);
    params.set('generar_pdf', '1');
    window.open(`peliculas.php?${params.toString()}`, '_blank');
}

function refrescar() {
    window.location.reload();
}

// cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const modal = document.getElementById('modalConfirmacion');
    if (event.target === modal) {
        cerrarModal();
    }
}
</script>

<?php include 'includes/sidebar.php'; ?>

<?php
// funciones auxiliares

function obtenerPeliculasAdmin($db, $filtroGenero, $filtroEstado, $busqueda, $limite, $offset) {
    $sql = "SELECT 
                p.id, p.titulo, p.director, p.ano_lanzamiento, p.genero, p.duracion_minutos,
                p.imagen_url, p.puntuacion_promedio, p.activo, p.fecha_creacion,
                u.nombre_usuario as creador,
                COUNT(r.id) as total_resenas
            FROM peliculas p
            INNER JOIN usuarios u ON p.id_usuario = u.id
            LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
            WHERE 1=1";
    
    $params = [];
    
    // aplicar filtros
    if (!empty($filtroGenero)) {
        $sql .= " AND p.genero = ?";
        $params[] = $filtroGenero;
    }
    
    if ($filtroEstado === 'activos') {
        $sql .= " AND p.activo = 1";
    } elseif ($filtroEstado === 'inactivos') {
        $sql .= " AND p.activo = 0";
    }
    
    if (!empty($busqueda)) {
        $sql .= " AND (p.titulo LIKE ? OR p.director LIKE ?)";
        $params[] = "%{$busqueda}%";
        $params[] = "%{$busqueda}%";
    }
    
    $sql .= " GROUP BY p.id ORDER BY p.fecha_creacion DESC LIMIT ? OFFSET ?";
    $params[] = $limite;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function contarPeliculasAdmin($db, $filtroGenero, $filtroEstado, $busqueda) {
    $sql = "SELECT COUNT(*) FROM peliculas p WHERE 1=1";
    $params = [];
    
    if (!empty($filtroGenero)) {
        $sql .= " AND p.genero = ?";
        $params[] = $filtroGenero;
    }
    
    if ($filtroEstado === 'activos') {
        $sql .= " AND p.activo = 1";
    } elseif ($filtroEstado === 'inactivos') {
        $sql .= " AND p.activo = 0";
    }
    
    if (!empty($busqueda)) {
        $sql .= " AND (p.titulo LIKE ? OR p.director LIKE ?)";
        $params[] = "%{$busqueda}%";
        $params[] = "%{$busqueda}%";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function obtenerEstadisticasPeliculasAdmin($db) {
    $sql = "SELECT 
                COUNT(*) as total_peliculas,
                SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as peliculas_activas,
                SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as peliculas_inactivas,
                AVG(CASE WHEN activo = 1 THEN puntuacion_promedio ELSE NULL END) as puntuacion_promedio
            FROM peliculas";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function obtenerGenerosDisponibles($db) {
    $sql = "SELECT DISTINCT genero FROM peliculas WHERE genero IS NOT NULL ORDER BY genero";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function activarPelicula($id) {
    $db = conectarDB();
    $sql = "UPDATE peliculas SET activo = 1 WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
}

function desactivarPelicula($id) {
    $db = conectarDB();
    $sql = "UPDATE peliculas SET activo = 0 WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
}

function eliminarPelicula($id) {
    $db = conectarDB();
    try {
        $db->beginTransaction();
        
        // eliminar resenas asociadas
        $sql1 = "UPDATE resenas SET activo = 0 WHERE id_pelicula = ?";
        $stmt1 = $db->prepare($sql1);
        $stmt1->execute([$id]);
        
        // eliminar favoritos asociados
        $sql2 = "UPDATE favoritos SET activo = 0 WHERE id_pelicula = ?";
        $stmt2 = $db->prepare($sql2);
        $stmt2->execute([$id]);
        
        // eliminar pelicula
        $sql3 = "DELETE FROM peliculas WHERE id = ?";
        $stmt3 = $db->prepare($sql3);
        $stmt3->execute([$id]);
        
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function generarPDFPeliculas() {
    require_once 'includes/pdf_generator.php';
    
    // obtener parametros actuales
    $filtroGenero = $_GET['genero'] ?? '';
    $filtroEstado = $_GET['estado'] ?? 'todos';
    $busqueda = $_GET['busqueda'] ?? '';
    
    $db = conectarDB();
    $peliculas = obtenerPeliculasAdmin($db, $filtroGenero, $filtroEstado, $busqueda, 1000, 0);
    
    generarPDFListadoPeliculas($peliculas, $filtroGenero, $filtroEstado, $busqueda);
}
?>