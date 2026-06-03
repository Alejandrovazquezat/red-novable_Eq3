<?php
// ==========================
// 1. Cargar dependencias
// ==========================
require_once __DIR__ . '/../../config/Conexion.php';
require_once __DIR__ . '/../../backend/models/Like.php';
require_once __DIR__ . '/../../backend/controllers/ComentarioController.php';

// ==========================
// 2. Conexión e instancias
// ==========================
$db = (new Conexion())->getConexion();
$comentarioController = new ComentarioController($db);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$usuarioLogueado = isset($_SESSION['usuario_id']);

// ==========================
// 3. Obtener ID de la publicación
// ==========================
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

// ==========================
// 4. Consultar Publicación en la BD (Modificado para traer foto e ID de autor)
// ==========================
$query = "SELECT p.*, u.id as autor_id, u.nombre as autor_nombre, u.foto_perfil as autor_foto, c.nombre as categoria_nombre, c.id as cat_id 
          FROM publicaciones p 
          LEFT JOIN usuarios u ON p.usuario_id = u.id 
          LEFT JOIN categorias c ON p.categoria_id = c.id 
          WHERE p.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$id]);
$pub = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pub) {
    $error = "La publicación que buscas no existe o ha sido eliminada.";
} else {
    // Obtener Likes si existe
    $likeModel = new Like($db);
    $likesCount = $likeModel->contarLikes($pub['id']);
    $yaLiked = false;
    
    if ($usuarioLogueado) {
        $likedPorUsuario = $likeModel->obtenerIdsPublicacionesLikedPorUsuario($_SESSION['usuario_id']);
        $yaLiked = in_array($pub['id'], $likedPorUsuario);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pub) ? htmlspecialchars($pub['titulo']) : 'Publicación no encontrada' ?> - Red-novable</title>
    <link rel="stylesheet" href="../css/navbar-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* =========================================
           ESTILOS GENERALES DEL POST
           ========================================= */
        .post-container {
            max-width: 900px;
            margin: 40px auto 80px auto;
            padding: 0 20px;
            font-family: 'Inter', sans-serif;
            /* Para que no se desborde en celular */
            box-sizing: border-box; 
            width: 100%;
        }

        .volver {
            display: inline-block;
            margin-bottom: 25px;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: 0.3s;
        }
        .volver:hover { text-decoration: underline; }

        .post-header { margin-bottom: 30px; text-align: center; }
        
        .post-category {
            display: inline-block;
            background: #cfd0d0; color: #1e3a8a;
            padding: 6px 15px; border-radius: 20px;
            font-weight: 700; font-size: 0.85rem;
            margin-bottom: 15px; text-decoration: none;
        }
        body.dark-mode .post-category { background: rgba(0, 102, 255, 0.2); color: #4ade80; }

        .post-title { font-size: 3rem; color: var(--texto-titulos); margin: 0 0 20px 0; line-height: 1.2; font-weight: 900; word-break: break-word; }
        
        .post-meta { color: #64748b; font-size: 1rem; display: flex; justify-content: center; align-items: center; gap: 20px; flex-wrap: wrap; }
        body.dark-mode .post-meta { color: #9ca3af; }

        .post-image-container { width: 100%; border-radius: 20px; overflow: hidden; margin-bottom: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .post-image-container img { width: 100%; max-height: 550px; object-fit: cover; display: block; }

        /* AJUSTES RESPONSIVOS PARA EL CONTENIDO HTML  */
        .post-content {
            font-size: 1.15rem;
            color: var(--texto-titulos);
            line-height: 1.8;
            margin-bottom: 50px;
            word-wrap: break-word; /* Evita que palabras largas rompan el diseño en celular */
            overflow-wrap: break-word;
            width: 100%;
        }
        
        /* Evitar que las imágenes o videos insertados desde el editor desborden la pantalla */
        .post-content img, .post-content video, .post-content iframe {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: block;
        }
        
        .post-content a { color: #3b82f6; text-decoration: none; font-weight: 600; word-break: break-all; }
        .post-content a:hover { text-decoration: underline; }
        .post-content ul, .post-content ol { padding-left: 20px; margin-bottom: 20px; }
        .post-content h1, .post-content h2, .post-content h3 { margin-top: 30px; margin-bottom: 15px; color: var(--texto-titulos); word-break: break-word; }

        .interaction-bar { display: flex; align-items: center; gap: 15px; padding-top: 20px; border-top: 1px solid #e2e8f0; margin-bottom: 40px; flex-wrap: wrap; }
        body.dark-mode .interaction-bar { border-color: #30363d; }

        /* =========================================
           ESTILOS PARA FOTOS DE PERFIL (NUEVO)
           ========================================= */
        .avatar-mini {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            background-color: var(--fondo-suave);
        }
        body.dark-mode .avatar-mini { border-color: #30363d; }
        
        .author-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s, color 0.2s;
            font-weight: 700;
        }
        .author-link:hover { transform: scale(1.03); color: #3b82f6; }

        /* =========================================
           BOTONES NEUMÓRFICOS UIVERSE (Sombras Grises)
           ========================================= */
        .btn-uiverse {
            --hover-shadows: 8px 8px 16px #a3a3a3, -8px -8px 16px #ffffff;
            --accent: #3b82f6; 
            font-weight: bold;
            letter-spacing: 0.05em;
            border: none;
            border-radius: 1.1em;
            background-color: #e2e8f0; 
            cursor: pointer;
            color: #475569;
            padding: 12px 24px;
            transition: box-shadow ease-in-out 0.3s, background-color ease-in-out 0.1s,
                        letter-spacing ease-in-out 0.1s, transform ease-in-out 0.1s, color ease-in-out 0.1s;
            box-shadow: 5px 5px 10px #b8c1cc, -5px -5px 10px #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 1rem;
        }

        .btn-uiverse:hover { box-shadow: var(--hover-shadows); }

        .btn-uiverse:active {
            box-shadow: var(--hover-shadows), var(--accent) 0px 0px 20px 2px;
            background-color: var(--accent);
            color: white;
            transform: scale(0.95);
        }

        body.dark-mode .btn-uiverse {
            background-color: #1e293b;
            color: #cbd5e1;
            --hover-shadows: 8px 8px 16px #0f172a, -8px -8px 16px #2d3b52;
            box-shadow: 5px 5px 10px #131a26, -5px -5px 10px #293850;
        }

        body.dark-mode .btn-uiverse:active { background-color: var(--accent); color: white; }

        .btn-uiverse.like-btn .like-icon { fill: #94a3b8; transition: 0.3s; width: 22px; height: 22px; }
        body.dark-mode .btn-uiverse.like-btn .like-icon { fill: #64748b; }
        .btn-uiverse.like-btn .like-count { background: rgba(0,0,0,0.1); padding: 3px 10px; border-radius: 8px; font-weight: bold; }
        body.dark-mode .btn-uiverse.like-btn .like-count { background: rgba(255,255,255,0.1); }

        .btn-uiverse.like-btn.liked { --accent: #ef4444; color: #ef4444; }
        
        .btn-uiverse.like-btn.liked .like-icon,
        body.dark-mode .btn-uiverse.like-btn.liked .like-icon {
            fill: #ef4444;
            filter: drop-shadow(0 0 5px rgba(239, 68, 68, 0.5));
            transform: scale(1.2);
        }

        .btn-uiverse.btn-send { --accent: #10b981; padding: 12px 20px; font-size: 1.2rem; color: var(--accent); }
        .btn-uiverse.btn-send:active { color: white; }

        /* =========================================
           COMENTARIOS CON EFECTO UIVERSE CARD
           ========================================= */
        .comments-section {
            background: #f8fafc; padding: 30px; border-radius: 16px; border: 1px solid #e2e8f0;
        }
        body.dark-mode .comments-section { background: #010409; border-color: #30363d; }

        .comments-list { max-height: 400px; overflow-y: auto; overflow-x: hidden; margin-bottom: 20px; padding: 10px; }
        .comments-list::-webkit-scrollbar { width: 6px; }
        .comments-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        .comment-item {
            box-sizing: border-box; width: 100%; background: rgba(217, 217, 217, 0.4); 
            border: 1px solid white; box-shadow: 12px 17px 51px rgba(0, 0, 0, 0.1); 
            backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); border-radius: 17px;
            transition: all 0.5s; cursor: pointer; display: flex; flex-direction: column;
            align-items: flex-start; justify-content: flex-start; text-align: left; padding: 20px;
            margin-bottom: 15px; color: #334155; font-size: 0.95rem; word-break: break-word;
        }

        .comment-item:hover { border: 1px solid #94a3b8; transform: scale(1.02); }
        .comment-item:active { transform: scale(0.95) rotateZ(1.7deg); }

        body.dark-mode .comment-item { background: rgba(22, 27, 34, 0.58); border: 1px solid #30363d; color: #c9d1d9; box-shadow: 12px 17px 51px rgba(0, 0, 0, 0.4); }
        body.dark-mode .comment-item:hover { border: 1px solid var(--color-accion); }

        .comment-user { font-weight: bold; color: #3b82f6; font-size: 0.9rem; margin-bottom: 12px; }
        
        .form-control { position: relative; width: 100%; }
        .input { color: inherit; font-size: 1rem; background: transparent; width: 100%; padding: 12px; border: none; border-bottom: 2px solid #cbd5e1; transition: 0.3s; box-sizing: border-box; }
        body.dark-mode .input { border-bottom-color: #30363d; }
        .input:focus { outline: none; }
        .input-border-alt { position: absolute; background: linear-gradient(90deg, #FF6464 0%, #FFBF59 50%, #47C9FF 100%); width: 0%; height: 3px; bottom: 0; left: 0; transition: 0.4s; }
        .input:focus + .input-border-alt { width: 100%; }

        .comment-form-container { display: flex; gap: 15px; align-items: flex-end; }

        /* =========================================
           MODAL DE SEGURIDAD (DISEÑO CRISTAL 3D GRIS) 
           ========================================= */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            display: flex; justify-content: center; align-items: center; z-index: 3000;
        }

        .modal-card {
            background: rgba(255, 255, 255, 0.5) !important; backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.8) !important; padding: 40px; border-radius: 24px;
            max-width: 450px; width: 90%; text-align: center; 
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15), 0 10px 25px rgba(0, 0, 0, 0.05) !important;
            transform: translateY(30px) scale(0.96); transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            animation: slideUpModal 0.4s forwards;
        }

        body.dark-mode .modal-card {
            background: rgba(22, 27, 34, 0.75) !important; border: 1px solid rgba(255, 255, 255, 0.05) !important;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.6) !important;
        }

        @keyframes slideUpModal {
            to { transform: translateY(0) scale(1); }
        }

        .modal-icon {
            width: 75px; height: 75px; border-radius: 50%; display: flex; justify-content: center; align-items: center; 
            margin: 0 auto 20px auto; background: rgba(56, 139, 253, 0.15) !important; color: #58a6ff !important;
            font-size: 2.2rem;
        }
        body.dark-mode .modal-icon { color: #58a6ff !important; }

        .modal-card h3 { color: var(--texto-titulos); font-size: 1.6rem; font-weight: 800; margin-bottom: 12px; }
        .modal-card p { color: var(--texto-oscuro); font-size: 1rem; margin-bottom: 25px; line-height: 1.6; }

        .modal-btns { display: flex !important; justify-content: center !important; align-items: center; gap: 15px; width: 100% !important; box-sizing: border-box; flex-wrap: wrap; }

        /* Botones de Modal Cristal */
        .btn-secondary {
            background: rgba(255, 255, 255, 0.6); color: #334155; border: 1px solid rgba(0,0,0,0.05); padding: 12px 24px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; font-family: 'Inter', sans-serif; font-size: 0.95rem; width: 100%;
        }
        .btn-secondary:hover { background: rgba(255, 255, 255, 0.9); color: #0f172a; transform: translateY(-2px); }
        body.dark-mode .btn-secondary { background: rgba(255,255,255,0.05); color: #cbd5e1; border-color: rgba(255,255,255,0.1); }
        
        .btn-primary-modal {
            background: #3b82f6; color: white; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.3s; font-family: 'Inter', sans-serif; font-size: 0.95rem; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); width: 100%;
        }
        .btn-primary-modal:hover { transform: translateY(-2px); filter: brightness(1.1); }

        .btn-close-modal {
            background: transparent; color: #94a3b8; border: none; font-size: 1rem; cursor: pointer; transition: 0.3s; padding: 10px; font-weight: 600; margin-top: 10px;
        }
        .btn-close-modal:hover { color: #ef4444; }

        /* =========================================
           RESPONSIVIDAD PARA CELULAR (iPhone 15 Pro Max)
           ========================================= */
        @media (max-width: 768px) {
            .post-title { font-size: 2.2rem; }
            .interaction-bar { flex-direction: column; align-items: stretch; gap: 10px; }
            .btn-uiverse { width: 100%; }
            .comment-form-container { flex-direction: column; align-items: stretch; gap: 10px; }
            .btn-send { width: 100%; }
            
            .modal-card { padding: 30px 20px; width: 95%; }
            .modal-btns { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="post-container">
        
        <?php if (isset($error)): ?>
            <a href="index.php" class="volver"><i class="fas fa-arrow-left"></i> Volver al inicio</a>
            <div style="background: #fee2e2; color: #ef4444; padding: 30px; border-radius: 16px; text-align: center; border: 1px solid #fca5a5;">
                <i class="fas fa-heart-broken" style="font-size: 4rem; margin-bottom: 20px;"></i>
                <h2><?= htmlspecialchars($error) ?></h2>
            </div>
        <?php else: ?>

            <a href="categoria.php?id=<?= $pub['cat_id'] ?>" class="volver">
                <i class="fas fa-arrow-left"></i> Volver a <?= htmlspecialchars($pub['categoria_nombre']) ?>
            </a>

            <header class="post-header">
                <a href="categoria.php?id=<?= $pub['cat_id'] ?>" class="post-category"><?= htmlspecialchars($pub['categoria_nombre']) ?></a>
                <h1 class="post-title"><?= htmlspecialchars($pub['titulo']) ?></h1>
                
                <div class="post-meta">
                    <span>
                        <a href="perfil.php?id=<?= $pub['autor_id'] ?? $pub['usuario_id'] ?>" class="author-link">
                            <?php if (!empty($pub['autor_foto'])): ?>
                                <img src="../../assets/<?= htmlspecialchars($pub['autor_foto']) ?>" class="avatar-mini" alt="Foto">
                            <?php else: ?>
                                <i class="fas fa-user-circle" style="font-size: 1.5rem; color: #94a3b8;"></i>
                            <?php endif; ?>
                            Escrito por <strong><?= htmlspecialchars($pub['autor_nombre'] ?? 'Anónimo') ?></strong>
                        </a>
                    </span>
                    <span><i class="fas fa-calendar-alt"></i> <?= date('d M, Y', strtotime($pub['fecha_creacion'])) ?></span>
                </div>
            </header>

            <?php if($pub['imagen']): ?>
                <div class="post-image-container">
                    <img src="../../assets/<?= htmlspecialchars($pub['imagen']) ?>" alt="Imagen de <?= htmlspecialchars($pub['titulo']) ?>">
                </div>
            <?php endif; ?>

            <div class="post-content">
                <?= strip_tags(html_entity_decode($pub['contenido']), '<p><br><a><b><strong><i><em><u><s><ul><ol><li><img><h1><h2><h3><h4><h5><h6><blockquote><span><div>') ?>
            </div>

            <div class="interaction-bar">
                <button class="btn-uiverse like-btn <?= $yaLiked ? 'liked' : '' ?>" data-pubid="<?= $pub['id'] ?>">
                    <svg class="like-icon" viewBox="0 0 24 24">
                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                    </svg>
                    <span class="like-text"><?= $yaLiked ? 'Te gusta' : 'Me gusta' ?></span>
                    <span class="like-count"><?= $likesCount ?></span>
                </button>
                
                <button class="btn-uiverse comment-trigger-btn" onclick="document.getElementById('comments-section').style.display='block'; document.getElementById('comentario-input').focus();">
                    <i class="fas fa-comment"></i> Escribir un comentario
                </button>
            </div>

            <div class="comments-section" id="comments-section">
                <h3 style="margin-top: 0; color: var(--texto-titulos); margin-bottom: 20px;"><i class="fas fa-comments"></i> Comentarios</h3>
                
                <div class="comments-list" id="comments-list-<?= $pub['id'] ?>">
                    <?php 
                    // Ejecutamos la consulta manual para garantizar traer ID y Foto para los comentarios
                    $stmtComs = $db->prepare("SELECT c.*, u.nombre as autor_nombre, u.foto_perfil, u.id as usuario_id FROM comentarios c LEFT JOIN usuarios u ON c.usuario_id = u.id WHERE c.publicacion_id = ? ORDER BY c.fecha_creacion ASC");
                    $stmtComs->execute([$pub['id']]);
                    $comentarios_pub = $stmtComs->fetchAll(PDO::FETCH_ASSOC);

                    if (count($comentarios_pub) > 0): 
                        foreach($comentarios_pub as $comentario): 
                    ?>
                        <div class="comment-item">
                            <div class="comment-user">
                                <a href="perfil.php?id=<?= $comentario['usuario_id'] ?? 0 ?>" class="author-link" style="color: #3b82f6;">
                                    <?php if (!empty($comentario['foto_perfil'])): ?>
                                        <img src="../../assets/<?= htmlspecialchars($comentario['foto_perfil']) ?>" class="avatar-mini" alt="Foto" style="width: 24px; height: 24px;">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($comentario['autor_nombre'] ?? 'Usuario') ?>
                                </a>
                            </div>
                            <p style="margin: 0;"><?= nl2br(htmlspecialchars($comentario['contenido'])) ?></p>
                        </div>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <p style="font-size: 0.9rem; color: #64748b; text-align: center; margin-bottom: 10px;" class="no-comments-msg">Aún no hay comentarios. ¡Sé el primero en opinar!</p>
                    <?php endif; ?>
                </div>

                <form class="comment-form" data-pubid="<?= $pub['id'] ?>">
                    <div class="comment-form-container">
                        <div class="form-control" style="flex-grow: 1; margin-bottom: 0;">
                            <input id="comentario-input" class="input input-alt" placeholder="Escribe tu opinión..." required="" type="text" name="comentario" autocomplete="off" style="padding-bottom: 15px;">
                            <span class="input-border-alt"></span>
                        </div>
                        
                        <button type="submit" class="btn-uiverse btn-send" title="Enviar comentario">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>

        <?php endif; ?>

        <div id="modalAuthRequired" class="modal-overlay" style="display: none;">
            <div class="modal-card">
                <div class="modal-icon info">
                    <i class="fas fa-user-lock"></i>
                </div>
                <h3>Acción requerida</h3>
                <p>Para interactuar con las publicaciones y dejar tus opiniones, necesitas iniciar sesión o crear una cuenta gratuita en Red-novable.</p>
                
                <div class="modal-btns">
                    <button type="button" onclick="window.location.href='inicioSesion.php'" class="btn-secondary">
                        <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                    </button>
                    <button type="button" onclick="window.location.href='registro.php'" class="btn-primary-modal">
                        <i class="fas fa-user-plus"></i> Registrarse
                    </button>
                </div>
                <div style="margin-top: 15px;">
                    <button type="button" onclick="document.getElementById('modalAuthRequired').style.display='none'" class="btn-close-modal">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>

    </main>

    <?php include 'footer.php'; ?>

    <script>
        const usuarioLogueado = <?= json_encode($usuarioLogueado) ?>;
        // Obtenemos el ID del usuario en JS para que los comentarios en tiempo real tengan enlace correcto
        const miUsuarioId = <?= $_SESSION['usuario_id'] ?? 0 ?>;
        
        // El script de comentarios adaptado específicamente para esta vista
        document.querySelectorAll('.comment-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (!usuarioLogueado) {
                    document.getElementById('modalAuthRequired').style.display = 'flex';
                    return;
                }

                const pubId = this.getAttribute('data-pubid');
                const inputComentario = this.querySelector('input[name="comentario"]');
                const texto = inputComentario.value;

                if (texto.trim() === '') return;
                
                // Deshabilitamos el input y el botón mientras se envía para que no le den doble clic
                const submitBtn = this.querySelector('button[type="submit"]');
                inputComentario.disabled = true;
                submitBtn.disabled = true;

                fetch('../ajax/guardar_comentario.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `publicacion_id=${pubId}&contenido=${encodeURIComponent(texto)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.reset();
                        
                        // Quitar el mensaje de "no hay comentarios" si existe
                        const noMsg = document.querySelector('.no-comments-msg');
                        if(noMsg) noMsg.remove();

                        const listaComentarios = document.getElementById(`comments-list-${pubId}`);
                        const nuevoComentario = document.createElement('div');
                        nuevoComentario.className = 'comment-item';
                        nuevoComentario.style.animation = 'fadeIn 0.4s ease';
                        
                        // Enlace con foto (por defecto ícono ya que es AJAX, al recargar agarra su foto)
                        nuevoComentario.innerHTML = `
                            <div class="comment-user">
                                <a href="perfil.php?id=${miUsuarioId}" class="author-link" style="color: #3b82f6;">
                                    <i class="fas fa-user-circle"></i> ${data.autor}
                                </a>
                            </div>
                            <p style="margin: 0;">${data.contenido}</p>
                        `;
                        listaComentarios.appendChild(nuevoComentario);
                        listaComentarios.scrollTop = listaComentarios.scrollHeight;
                    }
                })
                .catch(error => console.error('Error:', error))
                .finally(() => {
                    inputComentario.disabled = false;
                    submitBtn.disabled = false;
                    inputComentario.focus();
                });
            });
        });

        // Cerrar modal al dar clic fuera
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('modalAuthRequired');
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    </script>
    <script src="../js/like-logic.js"></script>
</body>
</html>