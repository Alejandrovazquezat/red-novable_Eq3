<?php
// Obtenemos el nombre del archivo actual para marcarlo como 'active'
$current_page = basename($_SERVER['PHP_SELF']);
$rol = $_SESSION['rol_id'] ?? 0;
?>
<nav class="sidebar">
    <div class="logo-box" onclick="window.location.href='../pages/index.php'">
        <img src="../image/LogotipoSinfondo.png" alt="Logo">
        <div class="logo-name">Red-novable</div>
    </div>
    <div class="menu-groups">
        
        <a href="../pages/index.php" class="nav-link">🏠 Volver al inicio</a>
        <?php if ($rol == 1): /* == ADMINISTRADOR == */ ?>
            <a href="dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">📊 Dashboard general</a>
            <a href="gestionar_publicaciones.php" class="nav-link <?= $current_page == 'gestionar_publicaciones.php' ? 'active' : '' ?>">📝 Gestión de Publicaciones</a>
            <a href="gestionar_categorias.php" class="nav-link <?= $current_page == 'gestionar_categorias.php' ? 'active' : '' ?>">🏷️ Gestión de Categorías</a>
            <a href="revisar.php" class="nav-link <?= $current_page == 'revisar.php' ? 'active' : '' ?>">✅ Pendientes de revisión</a>
            <a href="usuarios.php" class="nav-link <?= $current_page == 'usuarios.php' ? 'active' : '' ?>">👥 Usuarios</a>
            <a href="peticiones_rol.php" class="nav-link <?= $current_page == 'peticiones_rol.php' ? 'active' : '' ?>">📈 Peticiones rol</a>
            <a href="comentarios.php" class="nav-link <?= $current_page == 'comentarios.php' ? 'active' : '' ?>">💬 Comentarios</a>
            
        <?php elseif ($rol == 2): /* == EDITOR == */ ?>
            <a href="gestionar_publicaciones.php" class="nav-link <?= $current_page == 'gestionar_publicaciones.php' ? 'active' : '' ?>">📝 Gestión de Publicaciones</a>
            <a href="gestionar_categorias.php" class="nav-link <?= $current_page == 'gestionar_categorias.php' ? 'active' : '' ?>">🏷️ Gestión de Categorías</a>
            <a href="revisar.php" class="nav-link <?= $current_page == 'revisar.php' ? 'active' : '' ?>">✅ Pendientes de revisión</a>
        <?php endif; ?>
        
    </div>

    <div class="franxx-sidebar-container" data-page="<?= $current_page ?>">
        <div id="franxx-sidebar-globo">
            <p id="franxx-sidebar-mensaje"></p>
        </div>

        <div id="franxx-sidebar-bot">
            <img id="franxx-img-sidebar" src="../image/franxx_base.png" alt="Franxx Sidebar">
        </div>
    </div>

    <script>
        // Sincronizar el modo oscuro
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
        }
    </script>
    <script src="../js/franxx-sidebar.js"></script>
</nav>

<style>
    /* =========================================
       🔥 OVERRIDES DE BOTONES CRISTAL GRIS 3D 🔥
       ========================================= */
    .nav-link {
        display: flex;
        align-items: center;
        color: var(--text-light) !important;
        text-decoration: none;
        padding: 12px 18px;
        margin-bottom: 8px;
        border-radius: 12px;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid transparent !important;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
        box-shadow: none !important;
    }

    /* Estado Hover: Vidrio Grisáceo Esmerilado Flotante */
    .nav-link:hover {
        background: rgba(217, 217, 217, 0.4) !important;
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        border: 1px solid rgba(255, 255, 255, 0.8) !important;
        color: var(--text-dark) !important;
        transform: translateY(-3px) !important;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08) !important;
    }

    /* Estado Activo (Página actual): Vidrio con mayor elevación tridimensional */
    .nav-link.active {
        background: rgba(217, 217, 217, 0.65) !important;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border: 1px solid white !important;
        color: var(--text-dark) !important;
        font-weight: 800;
        transform: translateY(-1px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12) !important;
    }

    .nav-link:active {
        transform: translateY(0) scale(0.97) !important;
    }

    /* Ajustes para el Modo Oscuro */
    body.dark-mode .nav-link:hover {
        background: rgba(22, 27, 34, 0.6) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.5) !important;
    }

    body.dark-mode .nav-link.active {
        background: rgba(22, 27, 34, 0.85) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        color: #ffffff !important;
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.6) !important;
    }

    /* =========================================
       ESTILOS ASISTENTE FRANXX Y GLOBO
       ========================================= */
    .franxx-sidebar-container {
        margin-top: auto; 
        padding-bottom: 30px;
        display: flex;
        justify-content: center;
        position: relative;
    }

    #franxx-sidebar-bot {
        display: flex;
        justify-content: center;
        align-items: center;
        cursor: pointer;
        animation: flotar-sidebar 3s ease-in-out infinite;
    }

    @keyframes flotar-sidebar {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-8px); }
    }

    #franxx-img-sidebar {
        width: 80px;
        height: auto;
        filter: drop-shadow(2px 4px 0px rgba(0,0,0,0.2));
        transition: transform 0.2s ease;
    }

    #franxx-img-sidebar:hover {
        transform: scale(1.15);
    }

    #franxx-sidebar-globo {
        position: absolute;
        left: 75%; 
        top: -20px;
        background-color: var(--bg-color, #ffffff);
        color: var(--text-color, #000000);
        padding: 12px 18px;
        border: 3px solid #000000;
        box-shadow: 4px 4px 0px #000000;
        border-radius: 16px 16px 16px 0px; 
        width: 220px;
        font-weight: bold;
        font-size: 13px;
        display: none;
        z-index: 1000;
    }
</style>