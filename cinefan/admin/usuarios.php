<?php
session_start();

// verificar autenticación
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../api/config/database.php';

$mensaje = '';
$tipoMensaje = '';

// procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $usuarioId = (int)($_POST['usuario_id'] ?? 0);
    
    try {
        $db = getDatabase();
        $conn = $db->getConnection();
        
        switch ($accion) {
            case 'activar':
                $stmt = $conn->prepare("UPDATE usuarios SET activo = true WHERE id = :id");
                $stmt->bindParam(':id', $usuarioId);
                if ($stmt->execute()) {
                    $mensaje = 'Usuario activado exitosamente';
                    $tipoMensaje = 'success';
                }
                break;
                
            case 'desactivar':
                $stmt = $conn->prepare("UPDATE usuarios SET activo = false WHERE id = :id");
                $stmt->bindParam(':id', $usuarioId);
                if ($stmt->execute()) {
                    $mensaje = 'Usuario desactivado exitosamente';
                    $tipoMensaje = 'success';
                }
                break;
        }
    } catch (Exception $e) {
        $mensaje = 'Error: ' . $e->getMessage();
        $tipoMensaje = 'error';
    }
}

// parámetros de búsqueda y paginación
$busqueda = $_GET['busqueda'] ?? '';
$estado = $_GET['estado'] ?? 'todos';
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$limite = 20;
$offset = ($pagina - 1) * $limite;

try {
    $db = getDatabase();
    $conn = $db->getConnection();
    
    // construir consulta
    $sql = "SELECT u.id, u.nombre_usuario, u.email, u.nombre_completo, 
                   u.fecha_registro, u.fecha_ultimo_acceso, u.activo,
                   COUNT(DISTINCT p.id) as total_peliculas,
                   COUNT(DISTINCT r.id) as total_resenas
            FROM usuarios u
            LEFT JOIN peliculas p ON u.id = p.id_usuario_creador AND p.activo = true
            LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = true
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($busqueda)) {
        $sql .= " AND (u.nombre_usuario LIKE :busqueda OR u.email LIKE :busqueda OR u.nombre_completo LIKE :busqueda)";
        $params[':busqueda'] = "%{$busqueda}%";
    }
    
    if ($estado === 'activos') {
        $sql .= " AND u.activo = true";
    } elseif ($estado === 'inactivos') {
        $sql .= " AND u.activo = false";
    }
    
    $sql .= " GROUP BY u.id ORDER BY u.fecha_registro DESC";
    
    // contar total
    $countSql = str_replace(
        "SELECT u.id, u.nombre_usuario, u.email, u.nombre_completo, u.fecha_registro, u.fecha_ultimo_acceso, u.activo, COUNT(DISTINCT p.id) as total_peliculas, COUNT(DISTINCT r.id) as total_resenas",
        "SELECT COUNT(DISTINCT u.id) as total",
        $sql
    );
    $countSql = preg_replace('/GROUP BY.*ORDER BY.*$/', '', $countSql);
    
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalUsuarios = $countStmt->fetch()['total'];
    
    // obtener usuarios paginados
    $sql .= " LIMIT :limite OFFSET :offset";
    $params[':limite'] = $limite;
    $params[':offset'] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll();
    
    $totalPaginas = ceil($totalUsuarios / $limite);
    
} catch (Exception $e) {
    $error = "Error al cargar usuarios: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - CineFan Admin</title>
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>Gestión de Usuarios</h1>
                <p>Administrar usuarios registrados en CineFan</p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipoMensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>
            
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <input type="text" name="busqueda" placeholder="Buscar usuario..." 
                               value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <select name="estado">
                            <option value="todos" <?php echo $estado === 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="activos" <?php echo $estado === 'activos' ? 'selected' : ''; ?>>Activos</option>
                            <option value="inactivos" <?php echo $estado === 'inactivos' ? 'selected' : ''; ?>>Inactivos</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="usuarios.php" class="btn btn-secondary">Limpiar</a>
                </form>
            </div>
            
            <div class="table-card">
                <div class="table-header">
                    <h3>Usuarios (<?php echo number_format($totalUsuarios); ?>)</h3>
                    <a href="reportes.php?tipo=usuarios" class="btn btn-outline">Exportar PDF</a>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Nombre Completo</th>
                                <th>Contenido</th>
                                <th>Registro</th>
                                <th>Último Acceso</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($usuario['nombre_usuario']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                                    <td>
                                        <div class="content-stats">
                                            <span class="badge"><?php echo $usuario['total_peliculas']; ?> películas</span>
                                            <span class="badge"><?php echo $usuario['total_resenas']; ?> reseñas</span>
                                        </div>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></td>
                                    <td>
                                        <?php if ($usuario['fecha_ultimo_acceso']): ?>
                                            <?php echo date('d/m/Y', strtotime($usuario['fecha_ultimo_acceso'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Nunca</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $usuario['activo'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($usuario['activo']): ?>
                                                <form method="POST" class="inline-form" onsubmit="return confirm('¿Desactivar este usuario?')">
                                                    <input type="hidden" name="accion" value="desactivar">
                                                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">Desactivar</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="accion" value="activar">
                                                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">Activar</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalPaginas > 1): ?>
                    <div class="pagination">
                        <?php if ($pagina > 1): ?>
                            <a href="?pagina=<?php echo $pagina - 1; ?>&busqueda=<?php echo urlencode($busqueda); ?>&estado=<?php echo urlencode($estado); ?>" 
                               class="btn btn-sm">Anterior</a>
                        <?php endif; ?>
                        
                        <span class="pagination-info">
                            Página <?php echo $pagina; ?> de <?php echo $totalPaginas; ?>
                        </span>
                        
                        <?php if ($pagina < $totalPaginas): ?>
                            <a href="?pagina=<?php echo $pagina + 1; ?>&busqueda=<?php echo urlencode($busqueda); ?>&estado=<?php echo urlencode($estado); ?>" 
                               class="btn btn-sm">Siguiente</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="js/admin.js"></script>
</body>
</html>