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
            activarResena($_POST['id']);
            break;
        case 'desactivar':
            desactivarResena($_POST['id']);
            break;
        case 'eliminar':
            eliminarResena($_POST['id']);
            break;
        case 'moderar':
            moderarResena($_POST['id'], $_POST['motivo'] ?? '');
            break;
    }
    
    // redireccionar para evitar reenvio de formulario
    header('Location: resenas.php');
    exit;
}

// generar pdf si se solicita
if (isset($_GET['generar_pdf'])) {
    generarPDFResenas();
    exit;
}

// obtener parametros de filtrado y paginacion
$filtroPuntuacion = $_GET['puntuacion'] ?? '';
$filtroEstado = $_GET['estado'] ?? 'todos';
$filtroFecha = $_GET['fecha'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';
$pagina = max(1, $_GET['pagina'] ?? 1);
$elementosPorPagina = 15;
$offset = ($pagina - 1) * $elementosPorPagina;

// obtener resenas con filtros
$db = conectarDB();
$resenas = obtenerResenasAdmin($db, $filtroPuntuacion, $filtroEstado, $filtroFecha, $busqueda, $elementosPorPagina, $offset);
$totalResenas = contarResenasAdmin($db, $filtroPuntuacion, $filtroEstado, $filtroFecha, $busqueda);
$totalPaginas = ceil($totalResenas / $elementosPorPagina);

// obtener estadisticas
$estadisticas = obtenerEstadisticasResenasAdmin($db);

include 'includes/header.php';
?>

<div class="admin-content">
    <div class="content-header">
        <h1><i class="fas fa-star"></i> Gestión de Reseñas</h1>
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
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($estadisticas['total_resenas']); ?></h3>
                <p>Total Reseñas</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon active">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($estadisticas['resenas_activas']); ?></h3>
                <p>Activas</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($estadisticas['resenas_reportadas']); ?></h3>
                <p>Reportadas</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-thumbs-up"></i>
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
                       placeholder="Texto de reseña, autor, película...">
            </div>
            
            <div class="filter-group">
                <label for="puntuacion">Puntuación:</label>
                <select id="puntuacion" name="puntuacion">
                    <option value="">Todas las puntuaciones</option>
                    <option value="5" <?php echo $filtroPuntuacion === '5' ? 'selected' : ''; ?>>5 estrellas</option>
                    <option value="4" <?php echo $filtroPuntuacion === '4' ? 'selected' : ''; ?>>4 estrellas</option>
                    <option value="3" <?php echo $filtroPuntuacion === '3' ? 'selected' : ''; ?>>3 estrellas</option>
                    <option value="2" <?php echo $filtroPuntuacion === '2' ? 'selected' : ''; ?>>2 estrellas</option>
                    <option value="1" <?php echo $filtroPuntuacion === '1' ? 'selected' : ''; ?>>1 estrella</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="estado">Estado:</label>
                <select id="estado" name="estado">
                    <option value="todos" <?php echo $filtroEstado === 'todos' ? 'selected' : ''; ?>>Todos</option>
                    <option value="activas" <?php echo $filtroEstado === 'activas' ? 'selected' : ''; ?>>Activas</option>
                    <option value="inactivas" <?php echo $filtroEstado === 'inactivas' ? 'selected' : ''; ?>>Inactivas</option>
                    <option value="reportadas" <?php echo $filtroEstado === 'reportadas' ? 'selected' : ''; ?>>Reportadas</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="fecha">Período:</label>
                <select id="fecha" name="fecha">
                    <option value="">Todas las fechas</option>
                    <option value="hoy" <?php echo $filtroFecha === 'hoy' ? 'selected' : ''; ?>>Hoy</option>
                    <option value="semana" <?php echo $filtroFecha === 'semana' ? 'selected' : ''; ?>>Esta semana</option>
                    <option value="mes" <?php echo $filtroFecha === 'mes' ? 'selected' : ''; ?>>Este mes</option>
                    <option value="trimestre" <?php echo $filtroFecha === 'trimestre' ? 'selected' : ''; ?>>Último trimestre</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filtrar
            </button>
            
            <a href="resenas.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Limpiar
            </a>
        </form>
    </div>

    <!-- tabla de resenas -->
    <div class="table-section">
        <div class="table-header">
            <h3>Reseñas (<?php echo number_format($totalResenas); ?> total)</h3>
            <div class="table-actions">
                <span class="showing-info">
                    Mostrando <?php echo $offset + 1; ?>-<?php echo min($offset + $elementosPorPagina, $totalResenas); ?> 
                    de <?php echo number_format($totalResenas); ?>
                </span>
            </div>
        </div>

        <?php if (empty($resenas)): ?>
            <div class="empty-state">
                <i class="fas fa-star"></i>
                <h3>No se encontraron reseñas</h3>
                <p>No hay reseñas que coincidan con los filtros seleccionados.</p>
            </div>
        <?php else: ?>
            <div class="reviews-grid">
                <?php foreach ($resenas as $resena): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="review-meta">
                                <div class="movie-info">
                                    <strong><?php echo htmlspecialchars($resena['pelicula_titulo']); ?></strong>
                                    <small><?php echo htmlspecialchars($resena['pelicula_director']); ?> (<?php echo $resena['pelicula_ano']; ?>)</small>
                                </div>
                                <div class="review-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $resena['puntuacion'] ? 'active' : ''; ?>"></i>
                                    <?php endfor; ?>
                                    <span><?php echo $resena['puntuacion']; ?>/5</span>
                                </div>
                            </div>
                            <div class="review-status">
                                <span class="badge badge-<?php echo $resena['activo'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $resena['activo'] ? 'Activa' : 'Inactiva'; ?>
                                </span>
                                <?php if ($resena['total_reportes'] > 0): ?>
                                    <span class="badge badge-warning" title="<?php echo $resena['total_reportes']; ?> reportes">
                                        <i class="fas fa-flag"></i> <?php echo $resena['total_reportes']; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="review-content">
                            <p><?php echo nl2br(htmlspecialchars(substr($resena['texto_resena'], 0, 200))); ?>
                               <?php if (strlen($resena['texto_resena']) > 200): ?>
                                   <span class="read-more">... <a href="#" onclick="verResenaCompleta(<?php echo $resena['id']; ?>)">Ver más</a></span>
                               <?php endif; ?>
                            </p>
                        </div>

                        <div class="review-footer">
                            <div class="author-info">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($resena['autor']); ?></span>
                                <small><?php echo date('d/m/Y H:i', strtotime($resena['fecha_creacion'])); ?></small>
                            </div>
                            
                            <div class="review-stats">
                                <span class="stat">
                                    <i class="fas fa-thumbs-up"></i> <?php echo $resena['likes']; ?>
                                </span>
                                <span class="stat">
                                    <i class="fas fa-thumbs-down"></i> <?php echo $resena['dislikes']; ?>
                                </span>
                            </div>
                        </div>

                        <div class="review-actions">
                            <?php if ($resena['activo']): ?>
                                <button onclick="confirmarAccion('desactivar', <?php echo $resena['id']; ?>, 'reseña')" 
                                        class="btn btn-sm btn-warning" title="Desactivar">
                                    <i class="fas fa-pause"></i>
                                </button>
                            <?php else: ?>
                                <button onclick="confirmarAccion('activar', <?php echo $resena['id']; ?>, 'reseña')" 
                                        class="btn btn-sm btn-success" title="Activar">
                                    <i class="fas fa-play"></i>
                                </button>
                            <?php endif; ?>
                            
                            <button onclick="verResenaCompleta(<?php echo $resena['id']; ?>)" 
                                    class="btn btn-sm btn-info" title="Ver Completa">
                                <i class="fas fa-eye"></i>
                            </button>
                            
                            <button onclick="moderarResena(<?php echo $resena['id']; ?>)" 
                                    class="btn btn-sm btn-secondary" title="Moderar">
                                <i class="fas fa-gavel"></i>
                            </button>
                            
                            <button onclick="confirmarAccion('eliminar', <?php echo $resena['id']; ?>, 'reseña')" 
                                    class="btn btn-sm btn-danger" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
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

<!-- modal de moderacion -->
<div id="modalModeracion" class="modal">
    <div class="modal-content">
        <h3>Moderar Reseña</h3>
        <div class="form-group">
            <label for="motivoModeracion">Motivo de moderación:</label>
            <textarea id="motivoModeracion" rows="3" placeholder="Describe el motivo de la moderación..."></textarea>
        </div>
        <div class="modal-actions">
            <button onclick="cerrarModalModeracion()" class="btn btn-secondary">Cancelar</button>
            <button onclick="ejecutarModeracion()" class="btn btn-warning">Moderar</button>
        </div>
    </div>
</div>

<!-- modal de resena completa -->
<div id="modalResenaCompleta" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3 id="resenaModalTitulo">Reseña Completa</h3>
            <button onclick="cerrarModalResena()" class="btn-close">×</button>
        </div>
        <div id="resenaModalContenido" class="modal-body">
            <!-- contenido se carga dinamicamente -->
        </div>
    </div>
</div>

<script>
let accionPendiente = null;
let idPendiente = null;
let idResenaModerar = null;

function confirmarAccion(accion, id, tipo) {
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
            mensaje = `¿Estás seguro de que quieres activar esta ${tipo}?`;
            botonTexto = 'Activar';
            botonClase = 'btn-success';
            break;
        case 'desactivar':
            mensaje = `¿Estás seguro de que quieres desactivar esta ${tipo}?`;
            botonTexto = 'Desactivar';
            botonClase = 'btn-warning';
            break;
        case 'eliminar':
            mensaje = `¿Estás seguro de que quieres eliminar permanentemente esta ${tipo}? Esta acción no se puede deshacer.`;
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

function moderarResena(id) {
    idResenaModerar = id;
    document.getElementById('modalModeracion').style.display = 'block';
}

function ejecutarModeracion() {
    const motivo = document.getElementById('motivoModeracion').value;
    
    if (idResenaModerar) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="accion" value="moderar">
            <input type="hidden" name="id" value="${idResenaModerar}">
            <input type="hidden" name="motivo" value="${motivo}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function cerrarModalModeracion() {
    document.getElementById('modalModeracion').style.display = 'none';
    document.getElementById('motivoModeracion').value = '';
    idResenaModerar = null;
}

function verResenaCompleta(id) {
    // cargar resena completa via ajax
    fetch(`../api/resenas/detalle.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.exito) {
                const resena = data.datos;
                document.getElementById('resenaModalTitulo').textContent = 
                    `Reseña de "${resena.pelicula.titulo}" por ${resena.usuario.nombre_usuario}`;
                
                document.getElementById('resenaModalContenido').innerHTML = `
                    <div class="resena-detalle">
                        <div class="resena-info">
                            <h4>Película: ${resena.pelicula.titulo}</h4>
                            <p><strong>Director:</strong> ${resena.pelicula.director}</p>
                            <p><strong>Año:</strong> ${resena.pelicula.ano_lanzamiento}</p>
                            <div class="rating">
                                ${Array(5).fill().map((_, i) => 
                                    `<i class="fas fa-star ${i < resena.puntuacion ? 'active' : ''}"></i>`
                                ).join('')}
                                <span>${resena.puntuacion}/5</span>
                            </div>
                        </div>
                        <div class="resena-texto">
                            <h5>Reseña:</h5>
                            <p>${resena.texto_resena.replace(/\n/g, '<br>')}</p>
                        </div>
                        <div class="resena-stats">
                            <p><strong>Autor:</strong> ${resena.usuario.nombre_usuario}</p>
                            <p><strong>Fecha:</strong> ${new Date(resena.fecha_creacion).toLocaleString()}</p>
                            <p><strong>Likes:</strong> ${resena.likes} | <strong>Dislikes:</strong> ${resena.dislikes}</p>
                        </div>
                    </div>
                `;
                
                document.getElementById('modalResenaCompleta').style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error cargando reseña:', error);
            alert('Error al cargar la reseña completa');
        });
}

function cerrarModalResena() {
    document.getElementById('modalResenaCompleta').style.display = 'none';
}

function generarPDF() {
    const params = new URLSearchParams(window.location.search);
    params.set('generar_pdf', '1');
    window.open(`resenas.php?${params.toString()}`, '_blank');
}

function refrescar() {
    window.location.reload();
}

// cerrar modales al hacer clic fuera
window.onclick = function(event) {
    const modalConfirmacion = document.getElementById('modalConfirmacion');
    const modalModeracion = document.getElementById('modalModeracion');
    const modalResena = document.getElementById('modalResenaCompleta');
    
    if (event.target === modalConfirmacion) {
        cerrarModal();
    } else if (event.target === modalModeracion) {
        cerrarModalModeracion();
    } else if (event.target === modalResena) {
        cerrarModalResena();
    }
}
</script>

<?php include 'includes/sidebar.php'; ?>

<?php
// funciones auxiliares

function obtenerResenasAdmin($db, $filtroPuntuacion, $filtroEstado, $filtroFecha, $busqueda, $limite, $offset) {
    $sql = "SELECT 
                r.id, r.texto_resena, r.puntuacion, r.likes, r.dislikes, 
                r.activo, r.fecha_creacion,
                u.nombre_usuario as autor,
                p.titulo as pelicula_titulo, p.director as pelicula_director, 
                p.ano_lanzamiento as pelicula_ano,
                (SELECT COUNT(*) FROM reportes_resena rr WHERE rr.id_resena = r.id) as total_reportes
            FROM resenas r
            INNER JOIN usuarios u ON r.id_usuario = u.id
            INNER JOIN peliculas p ON r.id_pelicula = p.id
            WHERE 1=1";
    
    $params = [];
    
    // aplicar filtros
    if (!empty($filtroPuntuacion)) {
        $sql .= " AND r.puntuacion = ?";
        $params[] = $filtroPuntuacion;
    }
    
    if ($filtroEstado === 'activas') {
        $sql .= " AND r.activo = 1";
    } elseif ($filtroEstado === 'inactivas') {
        $sql .= " AND r.activo = 0";
    } elseif ($filtroEstado === 'reportadas') {
        $sql .= " AND (SELECT COUNT(*) FROM reportes_resena rr WHERE rr.id_resena = r.id) > 0";
    }
    
    if (!empty($filtroFecha)) {
        switch ($filtroFecha) {
            case 'hoy':
                $sql .= " AND DATE(r.fecha_creacion) = CURDATE()";
                break;
            case 'semana':
                $sql .= " AND r.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'mes':
                $sql .= " AND r.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'trimestre':
                $sql .= " AND r.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
                break;
        }
    }
    
    if (!empty($busqueda)) {
        $sql .= " AND (r.texto_resena LIKE ? OR u.nombre_usuario LIKE ? OR p.titulo LIKE ?)";
        $params[] = "%{$busqueda}%";
        $params[] = "%{$busqueda}%";
        $params[] = "%{$busqueda}%";
    }
    
    $sql .= " ORDER BY r.fecha_creacion DESC LIMIT ? OFFSET ?";
    $params[] = $limite;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function contarResenasAdmin($db, $filtroPuntuacion, $filtroEstado, $filtroFecha, $busqueda) {
    $sql = "SELECT COUNT(*) FROM resenas r 
            INNER JOIN usuarios u ON r.id_usuario = u.id
            INNER JOIN peliculas p ON r.id_pelicula = p.id
            WHERE 1=1";
    $params = [];
    
    if (!empty($filtroPuntuacion)) {
        $sql .= " AND r.puntuacion = ?";
        $params[] = $filtroPuntuacion;
    }
    
    if ($filtroEstado === 'activas') {
        $sql .= " AND r.activo = 1";
    } elseif ($filtroEstado === 'inactivas') {
        $sql .= " AND r.activo = 0";
    } elseif ($filtroEstado === 'reportadas') {
        $sql .= " AND (SELECT COUNT(*) FROM reportes_resena rr WHERE rr.id_resena = r.id) > 0";
    }
    
    if (!empty($filtroFecha)) {
        switch ($filtroFecha) {
            case 'hoy':
                $sql .= " AND DATE(r.fecha_creacion) = CURDATE()";
                break;
            case 'semana':
                $sql .= " AND r.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'mes':
                $sql .= " AND r.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'trimestre':
                $sql .= " AND r.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
                break;
        }
    }
    
    if (!empty($busqueda)) {
        $sql .= " AND (r.texto_resena LIKE ? OR u.nombre_usuario LIKE ? OR p.titulo LIKE ?)";
        $params[] = "%{$busqueda}%";
        $params[] = "%{$busqueda}%";
        $params[] = "%{$busqueda}%";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function obtenerEstadisticasResenasAdmin($db) {
    $sql = "SELECT 
                COUNT(*) as total_resenas,
                SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as resenas_activas,
                (SELECT COUNT(DISTINCT id_resena) FROM reportes_resena) as resenas_reportadas,
                AVG(CASE WHEN activo = 1 THEN puntuacion ELSE NULL END) as puntuacion_promedio
            FROM resenas";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function activarResena($id) {
    $db = conectarDB();
    $sql = "UPDATE resenas SET activo = 1 WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
}

function desactivarResena($id) {
    $db = conectarDB();
    $sql = "UPDATE resenas SET activo = 0 WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
}

function eliminarResena($id) {
    $db = conectarDB();
    try {
        $db->beginTransaction();
        
        // eliminar likes asociados
        $sql1 = "UPDATE likes_resenas SET activo = 0 WHERE id_resena = ?";
        $stmt1 = $db->prepare($sql1);
        $stmt1->execute([$id]);
        
        // eliminar reportes asociados
        $sql2 = "DELETE FROM reportes_resena WHERE id_resena = ?";
        $stmt2 = $db->prepare($sql2);
        $stmt2->execute([$id]);
        
        // eliminar resena
        $sql3 = "DELETE FROM resenas WHERE id = ?";
        $stmt3 = $db->prepare($sql3);
        $stmt3->execute([$id]);
        
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function moderarResena($id, $motivo) {
    $db = conectarDB();
    // desactivar resena y registrar moderacion
    $sql1 = "UPDATE resenas SET activo = 0, motivo_moderacion = ?, fecha_moderacion = NOW() WHERE id = ?";
    $stmt1 = $db->prepare($sql1);
    $stmt1->execute([$motivo, $id]);
    
    // registrar accion de moderacion en log
    $sql2 = "INSERT INTO log_moderacion (tipo, id_elemento, motivo, fecha_moderacion, id_moderador) 
             VALUES ('resena', ?, ?, NOW(), ?)";
    $stmt2 = $db->prepare($sql2);
    $stmt2->execute([$id, $motivo, $_SESSION['admin_id']]);
}

function generarPDFResenas() {
    require_once 'includes/pdf_generator.php';
    
    // obtener parametros actuales
    $filtroPuntuacion = $_GET['puntuacion'] ?? '';
    $filtroEstado = $_GET['estado'] ?? 'todos';
    $filtroFecha = $_GET['fecha'] ?? '';
    $busqueda = $_GET['busqueda'] ?? '';
    
    $db = conectarDB();
    $resenas = obtenerResenasAdmin($db, $filtroPuntuacion, $filtroEstado, $filtroFecha, $busqueda, 1000, 0);
    
    generarPDFListadoResenas($resenas, $filtroPuntuacion, $filtroEstado, $filtroFecha, $busqueda);
}
?>