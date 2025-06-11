<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar" id="adminSidebar">
    <nav class="sidebar-nav">
        <ul>
            <li class="<?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
                <a href="index.php">📊 Dashboard</a>
            </li>
            <li class="<?php echo $currentPage === 'usuarios.php' ? 'active' : ''; ?>">
                <a href="usuarios.php">👥 Usuarios</a>
            </li>
            <li class="<?php echo $currentPage === 'peliculas.php' ? 'active' : ''; ?>">
                <a href="peliculas.php">🎬 Películas</a>
            </li>
            <li class="<?php echo $currentPage === 'resenas.php' ? 'active' : ''; ?>">
                <a href="resenas.php">📝 Reseñas</a>
            </li>
            <li class="<?php echo $currentPage === 'reportes.php' ? 'active' : ''; ?>">
                <a href="reportes.php">📄 Reportes</a>
            </li>
        </ul>
    </nav>
</aside>