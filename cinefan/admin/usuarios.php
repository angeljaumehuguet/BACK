<?php
/**
 * Aqui va toda la gestion de usuarios del sistema
 * Los admins pueden crear editar eliminar y ver usuarios
 */

require_once 'includes/inicializar.php';

// verificar autenticacion
if (!Seguridad::estaAutenticado()) {
    header('Location: login.php?mensaje=acceso_denegado');
    exit;
}

// obtener parametros de la URL
$accion = $_GET['accion'] ?? 'listar';
$idUsuario = $_GET['id'] ?? null;
$pagina = intval($_GET['pagina'] ?? 1);
$busqueda = trim($_GET['busqueda'] ?? '');
$filtroRol = $_GET['rol'] ?? '';
$filtroEstado = $_GET['estado'] ?? '';

// variables para formularios y mensajes
$usuario = null;
$error = '';
$mensaje = '';
$usuarios = [];
$totalUsuarios = 0;
$totalPaginas = 0;

try {
    $bd = new BaseDatosAdmin();
    
    // obtener roles disponibles
    $roles = $bd->obtenerTodos("SELECT * FROM roles WHERE activo = 1 ORDER BY nombre");
    
    // procesar acciones
    switch ($accion) {
        case 'crear':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // procesar creacion de usuario
                $datos = [
                    'nombre_usuario' => trim($_POST['nombre_usuario'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'password' => $_POST['password'] ?? '',
                    'confirmar_password' => $_POST['confirmar_password'] ?? '',
                    'nombre_completo' => trim($_POST['nombre_completo'] ?? ''),
                    'biografia' => trim($_POST['biografia'] ?? ''),
                    'rol_id' => intval($_POST['rol_id'] ?? 0),
                    'activo' => isset($_POST['activo']) ? 1 : 0
                ];
                
                // validaciones basicas
                if (empty($datos['nombre_usuario']) || empty($datos['email']) || empty($datos['password'])) {
                    $error = 'Nombre usuario email y contraseña son obligatorios';
                } elseif ($datos['password'] !== $datos['confirmar_password']) {
                    $error = 'Las contraseñas no coinciden';
                } elseif (strlen($datos['password']) < LONGITUD_MIN_CLAVE) {
                    $error = 'La contraseña debe tener al menos ' . LONGITUD_MIN_CLAVE . ' caracteres';
                } else {
                    // verificar que no exista el usuario
                    $existe = $bd->obtenerUno(
                        "SELECT id FROM usuarios WHERE nombre_usuario = ? OR email = ?",
                        [$datos['nombre_usuario'], $datos['email']]
                    );
                    
                    if ($existe) {
                        $error = 'Ya existe un usuario con ese nombre o email';
                    } else {
                        // crear el usuario
                        $passwordHash = password_hash($datos['password'], PASSWORD_DEFAULT);
                        
                        $bd->ejecutar(
                            "INSERT INTO usuarios (nombre_usuario, email, password, nombre_completo, biografia, rol_id, activo, fecha_registro)
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                            [
                                $datos['nombre_usuario'],
                                $datos['email'],
                                $passwordHash,
                                $datos['nombre_completo'],
                                $datos['biografia'],
                                $datos['rol_id'],
                                $datos['activo']
                            ]
                        );
                        
                        $mensaje = 'Usuario creado correctamente';
                        
                        // registrar en logs
                        file_put_contents('logs/usuarios.log', 
                            date('Y-m-d H:i:s') . " - Usuario creado: {$datos['nombre_usuario']} por admin\n", 
                            FILE_APPEND
                        );
                        
                        // limpiar datos del formulario
                        $datos = [];
                    }
                }
                
                $usuario = $datos; // para mantener datos en caso de error
            }
            break;
            
        case 'editar':
            if (!$idUsuario) {
                $error = 'ID de usuario no especificado';
                $accion = 'listar';
                break;
            }
            
            // obtener datos del usuario
            $usuario = $bd->obtenerUno(
                "SELECT u.*, r.nombre as rol_nombre FROM usuarios u 
                 LEFT JOIN roles r ON u.rol_id = r.id 
                 WHERE u.id = ?",
                [$idUsuario]
            );
            
            if (!$usuario) {
                $error = 'Usuario no encontrado';
                $accion = 'listar';
                break;
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // procesar actualizacion
                $datos = [
                    'nombre_usuario' => trim($_POST['nombre_usuario'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'nombre_completo' => trim($_POST['nombre_completo'] ?? ''),
                    'biografia' => trim($_POST['biografia'] ?? ''),
                    'rol_id' => intval($_POST['rol_id'] ?? 0),
                    'activo' => isset($_POST['activo']) ? 1 : 0
                ];
                
                // validaciones
                if (empty($datos['nombre_usuario']) || empty($datos['email'])) {
                    $error = 'Nombre usuario y email son obligatorios';
                } else {
                    // verificar que no exista otro usuario con mismo nombre/email
                    $existe = $bd->obtenerUno(
                        "SELECT id FROM usuarios WHERE (nombre_usuario = ? OR email = ?) AND id != ?",
                        [$datos['nombre_usuario'], $datos['email'], $idUsuario]
                    );
                    
                    if ($existe) {
                        $error = 'Ya existe otro usuario con ese nombre o email';
                    } else {
                        // actualizar usuario
                        $bd->ejecutar(
                            "UPDATE usuarios SET nombre_usuario = ?, email = ?, nombre_completo = ?, 
                             biografia = ?, rol_id = ?, activo = ? WHERE id = ?",
                            [
                                $datos['nombre_usuario'],
                                $datos['email'],
                                $datos['nombre_completo'],
                                $datos['biografia'],
                                $datos['rol_id'],
                                $datos['activo'],
                                $idUsuario
                            ]
                        );
                        
                        $mensaje = 'Usuario actualizado correctamente';
                        
                        // actualizar datos del usuario en pantalla
                        $usuario = array_merge($usuario, $datos);
                        
                        // cambiar contraseña si se proporciono
                        if (!empty($_POST['nueva_password'])) {
                            if ($_POST['nueva_password'] === $_POST['confirmar_nueva_password']) {
                                if (strlen($_POST['nueva_password']) >= LONGITUD_MIN_CLAVE) {
                                    $nuevaPasswordHash = password_hash($_POST['nueva_password'], PASSWORD_DEFAULT);
                                    $bd->ejecutar(
                                        "UPDATE usuarios SET password = ? WHERE id = ?",
                                        [$nuevaPasswordHash, $idUsuario]
                                    );
                                    $mensaje .= ' Contraseña actualizada';
                                } else {
                                    $error = 'La nueva contraseña debe tener al menos ' . LONGITUD_MIN_CLAVE . ' caracteres';
                                }
                            } else {
                                $error = 'Las contraseñas nuevas no coinciden';
                            }
                        }
                        
                        // registrar en logs
                        file_put_contents('logs/usuarios.log', 
                            date('Y-m-d H:i:s') . " - Usuario actualizado: {$datos['nombre_usuario']} por admin\n", 
                            FILE_APPEND
                        );
                    }
                }
            }
            break;
            
        case 'eliminar':
            if (!$idUsuario) {
                $error = 'ID de usuario no especificado';
                break;
            }
            
            // obtener datos del usuario antes de eliminar
            $usuarioEliminar = $bd->obtenerUno("SELECT nombre_usuario FROM usuarios WHERE id = ?", [$idUsuario]);
            
            if (!$usuarioEliminar) {
                $error = 'Usuario no encontrado';
            } else {
                // eliminar usuario (soft delete - marcarlo como inactivo)
                $bd->ejecutar("UPDATE usuarios SET activo = 0 WHERE id = ?", [$idUsuario]);
                $mensaje = 'Usuario eliminado correctamente';
                
                // registrar en logs
                file_put_contents('logs/usuarios.log', 
                    date('Y-m-d H:i:s') . " - Usuario eliminado: {$usuarioEliminar['nombre_usuario']} por admin\n", 
                    FILE_APPEND
                );
            }
            
            $accion = 'listar';
            break;
            
        case 'activar':
            if (!$idUsuario) {
                $error = 'ID de usuario no especificado';
                break;
            }
            
            $bd->ejecutar("UPDATE usuarios SET activo = 1 WHERE id = ?", [$idUsuario]);
            $mensaje = 'Usuario activado correctamente';
            $accion = 'listar';
            break;
    }
    
    // obtener listado de usuarios para mostrar
    if ($accion === 'listar') {
        // construir consulta con filtros
        $where = ['1=1'];
        $params = [];
        
        if (!empty($busqueda)) {
            $where[] = "(u.nombre_usuario LIKE ? OR u.email LIKE ? OR u.nombre_completo LIKE ?)";
            $params[] = "%$busqueda%";
            $params[] = "%$busqueda%";
            $params[] = "%$busqueda%";
        }
        
        if (!empty($filtroRol)) {
            $where[] = "u.rol_id = ?";
            $params[] = $filtroRol;
        }
        
        if ($filtroEstado !== '') {
            $where[] = "u.activo = ?";
            $params[] = intval($filtroEstado);
        }
        
        $whereClause = implode(' AND ', $where);
        
        // contar total de usuarios
        $totalUsuarios = $bd->obtenerUno(
            "SELECT COUNT(*) as total FROM usuarios u WHERE $whereClause",
            $params
        )['total'];
        
        // calcular paginacion
        $limite = TAMAÑO_PAGINA_DEFECTO;
        $offset = ($pagina - 1) * $limite;
        $totalPaginas = ceil($totalUsuarios / $limite);
        
        // obtener usuarios con paginacion
        $usuarios = $bd->obtenerTodos(
            "SELECT u.*, r.nombre as rol_nombre,
                    COUNT(DISTINCT res.id) as total_resenas,
                    AVG(res.puntuacion) as puntuacion_promedio
             FROM usuarios u
             LEFT JOIN roles r ON u.rol_id = r.id
             LEFT JOIN resenas res ON u.id = res.id_usuario
             WHERE $whereClause
             GROUP BY u.id
             ORDER BY u.fecha_registro DESC
             LIMIT $limite OFFSET $offset",
            $params
        );
    }
    
} catch (Exception $e) {
    $error = 'Error en la base de datos: ' . $e->getMessage();
    file_put_contents('logs/errores_admin.log', 
        date('Y-m-d H:i:s') . " - Error usuarios: " . $e->getMessage() . "\n", 
        FILE_APPEND
    );
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - CineFan Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* usar los mismos estilos del panel principal */
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
        .badge-rol { font-size: 0.75em; padding: 4px 8px; }
        .avatar-usuario { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .filtros-form { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <!-- sidebar igual que en index.php -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-film fa-2x mb-2"></i>
            <h4>CineFan Admin</h4>
            <small>v<?= VERSION_APP ?></small>
        </div>
        
        <nav class="nav flex-column">
            <a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a class="nav-link active" href="usuarios.php"><i class="fas fa-users"></i> Usuarios</a>
            <a class="nav-link" href="peliculas.php"><i class="fas fa-film"></i> Películas</a>
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
        <!-- cabecera de la seccion -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-users me-2"></i> Gestión de Usuarios</h2>
            <a href="usuarios.php?accion=crear" class="btn btn-primary">
                <i class="fas fa-user-plus me-2"></i> Nuevo Usuario
            </a>
        </div>
        
        <!-- mostrar mensajes -->
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
            <!-- filtros de busqueda -->
            <div class="card mb-4">
                <div class="card-body filtros-form">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Buscar usuario</label>
                            <input type="text" class="form-control" name="busqueda" 
                                   value="<?= htmlspecialchars($busqueda) ?>" 
                                   placeholder="Nombre usuario email...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Rol</label>
                            <select class="form-select" name="rol">
                                <option value="">Todos los roles</option>
                                <?php foreach ($roles as $rol): ?>
                                    <option value="<?= $rol['id'] ?>" <?= $filtroRol == $rol['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($rol['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado">
                                <option value="">Todos</option>
                                <option value="1" <?= $filtroEstado === '1' ? 'selected' : '' ?>>Activos</option>
                                <option value="0" <?= $filtroEstado === '0' ? 'selected' : '' ?>>Inactivos</option>
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
            
            <!-- tabla de usuarios -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        Lista de Usuarios 
                        <span class="badge bg-secondary"><?= number_format($totalUsuarios) ?> total</span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($usuarios)): ?>
                        <div class="text-center p-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No se encontraron usuarios</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Email</th>
                                        <th>Rol</th>
                                        <th>Actividad</th>
                                        <th>Estado</th>
                                        <th>Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-usuario bg-primary text-white d-flex align-items-center justify-content-center me-3">
                                                        <?= strtoupper(substr($user['nombre_usuario'], 0, 2)) ?>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($user['nombre_usuario']) ?></strong><br>
                                                        <small class="text-muted"><?= htmlspecialchars($user['nombre_completo'] ?: 'Sin nombre') ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td>
                                                <span class="badge badge-rol bg-info">
                                                    <?= htmlspecialchars($user['rol_nombre'] ?: 'Sin rol') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    <?= $user['total_resenas'] ?> reseñas<br>
                                                    <?php if ($user['puntuacion_promedio']): ?>
                                                        <span class="text-warning">
                                                            <i class="fas fa-star"></i> <?= round($user['puntuacion_promedio'], 1) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($user['activo']): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?= date('d/m/Y', strtotime($user['fecha_registro'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="usuarios.php?accion=editar&id=<?= $user['id'] ?>" 
                                                       class="btn btn-outline-primary btn-admin">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($user['activo']): ?>
                                                        <a href="usuarios.php?accion=eliminar&id=<?= $user['id'] ?>" 
                                                           class="btn btn-outline-danger btn-admin"
                                                           onclick="return confirm('¿Eliminar este usuario?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="usuarios.php?accion=activar&id=<?= $user['id'] ?>" 
                                                           class="btn btn-outline-success btn-admin">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php endif; ?>
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
                                        <a class="page-link" href="usuarios.php?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&rol=<?= $filtroRol ?>&estado=<?= $filtroEstado ?>">
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
            <!-- formulario de crear/editar usuario -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-<?= $accion === 'crear' ? 'user-plus' : 'user-edit' ?> me-2"></i>
                        <?= $accion === 'crear' ? 'Crear Usuario' : 'Editar Usuario' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre de Usuario <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nombre_usuario" 
                                       value="<?= htmlspecialchars($usuario['nombre_usuario'] ?? '') ?>" 
                                       required maxlength="<?= LONGITUD_MAX_NOMBRE_USUARIO ?>">
                                <div class="invalid-feedback">El nombre de usuario es obligatorio</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" 
                                       required maxlength="<?= LONGITUD_MAX_EMAIL ?>">
                                <div class="invalid-feedback">El email es obligatorio y debe ser válido</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Nombre Completo</label>
                                <input type="text" class="form-control" name="nombre_completo" 
                                       value="<?= htmlspecialchars($usuario['nombre_completo'] ?? '') ?>" 
                                       maxlength="<?= LONGITUD_MAX_NOMBRE_COMPLETO ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Rol</label>
                                <select class="form-select" name="rol_id">
                                    <option value="">Sin rol específico</option>
                                    <?php foreach ($roles as $rol): ?>
                                        <option value="<?= $rol['id'] ?>" 
                                                <?= ($usuario['rol_id'] ?? '') == $rol['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($rol['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Biografía</label>
                            <textarea class="form-control" name="biografia" rows="3" 
                                      maxlength="500"><?= htmlspecialchars($usuario['biografia'] ?? '') ?></textarea>
                            <div class="form-text">Descripción opcional del usuario</div>
                        </div>
                        
                        <?php if ($accion === 'crear'): ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="password" 
                                           required minlength="<?= LONGITUD_MIN_CLAVE ?>">
                                    <div class="invalid-feedback">La contraseña debe tener al menos <?= LONGITUD_MIN_CLAVE ?> caracteres</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="confirmar_password" required>
                                    <div class="invalid-feedback">Debe confirmar la contraseña</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nueva Contraseña</label>
                                    <input type="password" class="form-control" name="nueva_password" 
                                           minlength="<?= LONGITUD_MIN_CLAVE ?>">
                                    <div class="form-text">Dejar vacío para mantener la actual</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirmar Nueva Contraseña</label>
                                    <input type="password" class="form-control" name="confirmar_nueva_password">
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="activo" 
                                       <?= ($usuario['activo'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label">Usuario activo</label>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>
                                <?= $accion === 'crear' ? 'Crear Usuario' : 'Actualizar Usuario' ?>
                            </button>
                            <a href="usuarios.php" class="btn btn-secondary">
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
        // validacion del formulario
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
        
        // confirmar eliminacion de usuarios
        function confirmarEliminacion(nombreUsuario) {
            return confirm(`¿Estás seguro de eliminar al usuario "${nombreUsuario}"?\n\nEsta acción marcará al usuario como inactivo.`);
        }
    </script>
</body>
</html>