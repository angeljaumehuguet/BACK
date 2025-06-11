<header class="admin-header">
    <div class="header-content">
        <div class="header-left">
            <button class="sidebar-toggle" id="sidebarToggle">☰</button>
            <h1>🎬 CineFan Admin</h1>
        </div>
        
        <div class="header-right">
            <span class="user-info">
                Bienvenido, <?php echo htmlspecialchars($_SESSION['admin_nombre']); ?>
                <small>(<?php echo htmlspecialchars($_SESSION['admin_nivel']); ?>)</small>
            </span>
            <a href="logout.php" class="btn btn-outline btn-sm">Cerrar Sesión</a>
        </div>
    </div>
</header>