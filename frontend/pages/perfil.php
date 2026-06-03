<?php
session_start();

// Verificación de seguridad...
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php?error=nologin");
    exit;
}

require_once __DIR__ . '/../../config/Conexion.php';
require_once __DIR__ . '/../../backend/models/Like.php';
require_once __DIR__ . '/../../backend/models/Usuario.php';
require_once __DIR__ . '/../../backend/controllers/PublicacionController.php'; 
require_once __DIR__ . '/../../backend/controllers/CategoriesController.php'; 

$db = (new Conexion())->getConexion();
$usuario_logueado_id = $_SESSION['usuario_id'];

// ARQUITECTURA DE PERFIL PÚBLICO/PRIVADO 
// Si viene un id por GET, estamos viendo el perfil de alguien más. Si no, el nuestro.
$usuario_id = isset($_GET['id']) ? intval($_GET['id']) : $usuario_logueado_id;
$es_mi_perfil = ($usuario_id === $usuario_logueado_id);

$mensaje_perfil = "";
$error_perfil = "";

// ==========================================
// PROCESAR PETICIÓN DE CAMBIO DE ROL
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'solicitar_rol') {
    $rol_solicitado = intval($_POST['rol_solicitado']);
    $motivo = trim($_POST['motivo']);

    // Verificar si ya tiene una petición pendiente
    $stmtCheck = $db->prepare("SELECT id FROM peticiones_rol WHERE usuario_id = ? AND estado = 'pendiente'");
    $stmtCheck->execute([$usuario_logueado_id]);
    
    if ($stmtCheck->fetch()) {
        $error_perfil = "Ya cuentas con una solicitud de rol en espera de revisión.";
    } else {
        $stmtIns = $db->prepare("INSERT INTO peticiones_rol (usuario_id, rol_solicitado_id, motivo) VALUES (?, ?, ?)");
        if ($stmtIns->execute([$usuario_logueado_id, $rol_solicitado, $motivo])) {
            header("Location: perfil.php?msg=peticion_enviada");
            exit;
        } else {
            $error_perfil = "Ocurrió un error al registrar la solicitud.";
        }
    }
}

// ==========================================
// SOLUCIÓN ERROR 1: CANCELAR Y LIMPIAR ESTADO
// ==========================================
if (isset($_GET['accion']) && $_GET['accion'] == 'cancelar_edicion') {
    unset($_SESSION['edicion_desbloqueada']);
    header("Location: perfil.php");
    exit;
}

// ==========================================
// SOLUCIÓN ERROR 2: SOLICITAR CONTRASEÑA SOLO PARA DATOS SENSIBLES
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'desbloquear_sensible') {
    $pass_actual = $_POST['password_actual'] ?? '';
    
    $stmtPass = $db->prepare("SELECT password FROM usuarios WHERE id = ?");
    $stmtPass->execute([$usuario_logueado_id]);
    $hash_guardado = $stmtPass->fetchColumn();

    if (password_verify($pass_actual, $hash_guardado)) {
        $_SESSION['edicion_desbloqueada'] = true;
        $error_perfil = ""; 
    } else {
        $error_perfil = "Contraseña actual incorrecta. No se pudo habilitar la edición de datos sensibles.";
    }
}

// ==========================================
// LÓGICA DE ACTUALIZACIÓN DE PERFIL (COMBINADA)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'actualizar_perfil') {
    $nuevo_nombre = trim($_POST['nombre']);
    $nueva_descripcion = trim($_POST['descripcion']); 
    $nuevo_email = trim($_POST['email'] ?? '');
    $nueva_pass = $_POST['password'] ?? '';
    
    $stmtActual = $db->prepare("SELECT foto_perfil FROM usuarios WHERE id = ?");
    $stmtActual->execute([$usuario_logueado_id]);
    $usuarioActual = $stmtActual->fetch(PDO::FETCH_ASSOC);
    $ruta_foto_final = $usuarioActual['foto_perfil'];

    $pubController = new PublicacionController($db);

    // 1. Procesar Foto
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == 0) {
        $resultado_subida = $pubController->subirImagenFisica($_FILES['foto_perfil'], 'uploads/perfiles/');
        
        if (strpos($resultado_subida, 'Error') === 0) {
            $error_perfil = $resultado_subida;
        } else {
            $ruta_foto_final = $resultado_subida;
        }
    }

    if (isset($_POST['quitar_foto'])) {
        $ruta_foto_final = null; 
    }

    if (empty($error_perfil)) {
        // 2. Procesar Datos Sensibles
        if (isset($_SESSION['edicion_desbloqueada'])) {
            if (empty($nuevo_email) || !filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
                $error_perfil = "Por favor, ingresa un correo electrónico válido.";
            } else {
                $stmtCheckEmail = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmtCheckEmail->execute([$nuevo_email, $usuario_logueado_id]);
                if ($stmtCheckEmail->fetch()) {
                    $error_perfil = "El correo electrónico ya está registrado por otra cuenta.";
                }
            }

            if (empty($error_perfil) && !empty($nueva_pass)) {
                $uppercase = preg_match('@[A-Z]@', $nueva_pass);
                $number    = preg_match('@[0-9]@', $nueva_pass);
                $specialChars = preg_match('@[^\w]@', $nueva_pass);

                if (!$uppercase || !$number || !$specialChars || strlen($nueva_pass) < 8) {
                    $error_perfil = "La nueva contraseña debe tener al menos 8 caracteres, incluir una mayúscula, un número y un carácter especial.";
                }
            }
        }

        // 3. Guardar Cambios
        if (empty($error_perfil)) {
            $db->beginTransaction();
            try {
                $stmtBase = $db->prepare("UPDATE usuarios SET nombre = ?, foto_perfil = ?, descripcion = ? WHERE id = ?");
                $stmtBase->execute([$nuevo_nombre, $ruta_foto_final, $nueva_descripcion, $usuario_logueado_id]);

                if (isset($_SESSION['edicion_desbloqueada'])) {
                    $stmtUpEmail = $db->prepare("UPDATE usuarios SET email = ? WHERE id = ?");
                    $stmtUpEmail->execute([$nuevo_email, $usuario_logueado_id]);

                    if (!empty($nueva_pass)) {
                        $pass_hash = password_hash($nueva_pass, PASSWORD_DEFAULT);
                        $stmtUpPass = $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                        $stmtUpPass->execute([$pass_hash, $usuario_logueado_id]);
                    }
                }

                $db->commit();
                $_SESSION['nombre'] = $nuevo_nombre;
                unset($_SESSION['edicion_desbloqueada']);
                header("Location: perfil.php?msg=perfil_actualizado");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $error_perfil = "Error al actualizar los datos: " . $e->getMessage();
            }
        }
    }
}

// ==========================================
// LÓGICA DE EDICIÓN DE POSTS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'editar') {
    $pubController = new PublicacionController($db);
    $id_editar = intval($_POST['pub_id']);
    $titulo = trim($_POST['titulo']);
    $contenido = trim($_POST['contenido']);
    $categoria_id = intval($_POST['categoria']);
    $imagen_archivo = (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) ? $_FILES['imagen'] : null;
    
    $pubController->editar($id_editar, $titulo, $contenido, $categoria_id, $usuario_logueado_id, $imagen_archivo);
    
    $stmtReenviar = $db->prepare("UPDATE publicaciones SET estado = 'pendiente', observacion = NULL WHERE id = ?");
    $stmtReenviar->execute([$id_editar]);
    
    header("Location: perfil.php?msg=editado");
    exit;
}

$catController = new CategoriesController($db);
$categorias_stmt = $catController->obtenerTodas();
$categorias = is_object($categorias_stmt) ? $categorias_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

if (isset($_GET['eliminar_com'])) {
    $id_com = intval($_GET['eliminar_com']);
    $stmtDel = $db->prepare("DELETE FROM comentarios WHERE id = ? AND usuario_id = ?");
    $stmtDel->execute([$id_com, $usuario_logueado_id]);
    header("Location: perfil.php");
    exit;
}

// Cargar la información del perfil correspondiente (Mío o de Terceros)
$stmtUser = $db->prepare("SELECT u.id, u.rol_id, u.nombre, u.email, u.fecha_creacion, u.foto_perfil, u.descripcion, r.nombre as rol_nombre FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.id = ?");
$stmtUser->execute([$usuario_id]);
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    header("Location: index.php"); 
    exit;
}

// Cargar publicaciones del usuario del perfil
$queryMisPubs = "SELECT p.id, p.titulo, p.fecha_creacion, p.estado, p.imagen, p.contenido, c.nombre as categoria_nombre, p.categoria_id, (SELECT COUNT(*) FROM likes l WHERE l.publicacion_id = p.id) as total_likes, (SELECT COUNT(*) FROM comentarios com WHERE com.publicacion_id = p.id) as total_comentarios FROM publicaciones p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE p.usuario_id = ? AND p.estado = 'publicado' ORDER BY p.fecha_creacion DESC";
// Si es mi propio perfil, permito ver las mías que estén en revisión/pendientes
if ($es_mi_perfil) {
    $queryMisPubs = "SELECT p.id, p.titulo, p.fecha_creacion, p.estado, p.imagen, p.contenido, c.nombre as categoria_nombre, p.categoria_id, (SELECT COUNT(*) FROM likes l WHERE l.publicacion_id = p.id) as total_likes, (SELECT COUNT(*) FROM comentarios com WHERE com.publicacion_id = p.id) as total_comentarios FROM publicaciones p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE p.usuario_id = ? AND p.estado != 'rechazado' ORDER BY p.fecha_creacion DESC";
}
$stmtMisPubs = $db->prepare($queryMisPubs);
$stmtMisPubs->execute([$usuario_id]);
$misPublicaciones = $stmtMisPubs->fetchAll(PDO::FETCH_ASSOC);

$queryRechazados = "SELECT p.*, c.nombre as categoria_nombre FROM publicaciones p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE p.usuario_id = ? AND p.estado = 'rechazado' ORDER BY p.fecha_creacion DESC";
$stmtRechazados = $db->prepare($queryRechazados);
$stmtRechazados->execute([$usuario_id]);
$misRechazados = $stmtRechazados->fetchAll(PDO::FETCH_ASSOC);

$queryLikes = "SELECT p.*, u.nombre as autor, c.nombre as categoria_nombre FROM publicaciones p JOIN likes l ON p.id = l.publicacion_id LEFT JOIN usuarios u ON p.usuario_id = u.id LEFT JOIN categorias c ON p.categoria_id = c.id WHERE l.usuario_id = ? ORDER BY l.fecha DESC";
$stmtLikes = $db->prepare($queryLikes);
$stmtLikes->execute([$usuario_id]);
$misLikes = $stmtLikes->fetchAll(PDO::FETCH_ASSOC);

$likeModel = new Like($db);
$likedPorUsuario = $likeModel->obtenerIdsPublicacionesLikedPorUsuario($usuario_logueado_id);

$queryComentarios = "SELECT c.*, p.titulo as pub_titulo FROM comentarios c JOIN publicaciones p ON c.publicacion_id = p.id WHERE c.usuario_id = ? ORDER BY c.fecha_creacion DESC";
$stmtComentarios = $db->prepare($queryComentarios);
$stmtComentarios->execute([$usuario_id]);
$misComentarios = $stmtComentarios->fetchAll(PDO::FETCH_ASSOC);

// Verificar estado de peticiones de rol si es mi perfil
$peticionPendiente = false;
$rol_actual = intval($userData['rol_id']);

if ($es_mi_perfil) {
    $stmtPet = $db->prepare("SELECT estado FROM peticiones_rol WHERE usuario_id = ? ORDER BY fecha_creacion DESC LIMIT 1");
    $stmtPet->execute([$usuario_logueado_id]);
    $resPet = $stmtPet->fetch(PDO::FETCH_ASSOC);
    if ($resPet && $resPet['estado'] == 'pendiente') {
        $peticionPendiente = true;
    }
}

// Definir el mensaje dinámico según el rol
$mensaje_solicitud = "¿Deseas redactar artículos o moderar el contenido del portal?";
if ($rol_actual === 3) {
    $mensaje_solicitud = "¿Deseas un cambio de rol a editor para moderar el contenido?";
} elseif ($rol_actual === 2) {
    $mensaje_solicitud = "¿Deseas colaborar en la administración técnica de la plataforma?";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($userData['nombre']) ?> - Red-novable</title>
    <link rel="stylesheet" href="../css/navbar-style.css"> 
    <link rel="stylesheet" href="../css/categoria-styles.css">
    <link rel="stylesheet" href="../css/perfil-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
</head>
<body>
    
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <?php include 'navbar.php'; ?>

    <header class="perfil-header-full">
        <div class="perfil-banner-full"></div>
        
        <div class="perfil-info-wrapper-full">
            
            <div class="profile-main-card">
                <?php if ($userData['foto_perfil']): ?>
                    <img src="../../assets/<?= htmlspecialchars($userData['foto_perfil']) ?>" alt="Foto de perfil">
                <?php else: ?>
                    <div style="z-index: 2; width: 100%; height: 100%; display: flex; justify-content: center; align-items: center;">
                        <i class="fas fa-user" style="font-size: 6rem; color: #cbd5e1;"></i>
                    </div>
                <?php endif; ?>
            </div>

            <div class="user-details-full">
                <h1 class="user-name"><?= htmlspecialchars($userData['nombre']) ?></h1>
                <span class="user-rol"><?= htmlspecialchars($userData['rol_nombre']) ?></span>
                
                <p class="user-description">
                    <?= $userData['descripcion'] ? nl2br(htmlspecialchars($userData['descripcion'])) : '<em>Sin descripción cargada en el sistema.</em>' ?>
                </p>

                <p class="user-joined"><i class="far fa-calendar-alt"></i> Se unió al portal en <?= date('M Y', strtotime($userData['fecha_creacion'])) ?></p>
                
                <?php if($es_mi_perfil): ?>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 20px;">
                        <button class="btn-p-edit" onclick="abrirModalPerfil()" style="margin: 0;">
                            <i class="fas fa-user-cog"></i> Configuración
                        </button>
                        
                        <?php if($rol_actual != 1): ?>
                            <?php if($peticionPendiente): ?>
                                <button type="button" class="btn-glass-gray" disabled title="Solicitud en revisión" style="opacity: 0.7; cursor: not-allowed; margin: 0;">
                                    <i class="fas fa-clock"></i> Solicitud en revisión
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-glass-gray" onclick="document.getElementById('modal-solicitar-rol').classList.add('active')" style="margin: 0;">
                                    <i class="fas fa-user-tag"></i> Solicitar Rol
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="perfil-container">
        
        <div class="profile-nav">
            <button class="tab-btn active" onclick="openTab(event, 'Posts')">Artículos (<?= count($misPublicaciones) ?>)</button>
            <?php if($es_mi_perfil): ?>
                <button class="tab-btn" onclick="openTab(event, 'Likes')">Me gusta (<?= count($misLikes) ?>)</button>
                <button class="tab-btn" onclick="openTab(event, 'Comentarios')">Comentarios (<?= count($misComentarios) ?>)</button>
                
                <?php if (strtolower($userData['rol_nombre']) === 'autor' || count($misRechazados) > 0 || $userData['rol_id'] == 3): ?>
                    <button class="tab-btn" onclick="openTab(event, 'Rechazados')">Devueltos (<?= count($misRechazados) ?>)</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'perfil_actualizado'): ?>
            <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center; position: relative; z-index: 5;">
                <i class="fas fa-check-circle"></i> La cuenta ha sido actualizada de forma correcta.
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'peticion_enviada'): ?>
            <div style="background: #dbeafe; color: #1e40af; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center; position: relative; z-index: 5;">
                <i class="fas fa-paper-plane"></i> Solicitud de cambio de rol registrada. Pendiente de evaluación de administración.
            </div>
        <?php endif; ?>

        <div id="Posts" class="tab-content" style="display: block; position: relative; z-index: 5;">
            <div class="lista-vertical">
                <?php if (count($misPublicaciones) > 0): ?>
                    <?php foreach($misPublicaciones as $pub): 
                        $likesCount = $likeModel->contarLikes($pub['id']);
                        $yaLiked = in_array($pub['id'], $likedPorUsuario);
                    ?>
                        <div class="vertical-card-wrapper" data-pub-id="<?= $pub['id'] ?>" onclick="if(!event.target.closest('button') && !event.target.closest('a')) window.location.href='publicacion.php?id=<?= $pub['id'] ?>'">
                            <div class="vertical-card-inner">
                                <?php if($pub['imagen']): ?>
                                    <div class="vertical-imagen">
                                        <img src="../../assets/<?= htmlspecialchars($pub['imagen']) ?>" alt="Imagen de publicación">
                                    </div>
                                <?php endif; ?>
                                
                                <div class="vertical-contenido">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                        <span class="vertical-categoria"><?= htmlspecialchars($pub['categoria_nombre'] ?? 'General') ?></span>
                                        <?php if($es_mi_perfil): ?>
                                            <button onclick="abrirModalEditarPost(<?= $pub['id'] ?>)" class="btn-p-edit" style="padding: 0.5em 1em; font-size: 0.8rem;">
                                                <i class="fas fa-pencil-alt"></i> Editar
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <div id="data-titulo-<?= $pub['id'] ?>" style="display:none;"><?= htmlspecialchars($pub['titulo']) ?></div>
                                    <div id="data-contenido-<?= $pub['id'] ?>" style="display:none;"><?= htmlspecialchars($pub['contenido']) ?></div>
                                    <div id="data-cat-id-<?= $pub['id'] ?>" style="display:none;"><?= $pub['categoria_id'] ?></div>

                                    <h2 class="vertical-titulo">
                                        <?= htmlspecialchars($pub['titulo']) ?>
                                    </h2>
                                    
                                    <div class="vertical-meta">
                                        <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($pub['fecha_creacion'])) ?>
                                        <?php if($es_mi_perfil && $pub['estado'] == 'pendiente'): ?>
                                            <span style="background: rgba(245, 158, 11, 0.15); color: #d97706; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; margin-left:10px; font-weight: 700;">
                                                <i class="fas fa-clock"></i> En revisión
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <p class="vertical-resumen">
                                        <?= htmlspecialchars(mb_substr(strip_tags(html_entity_decode($pub['contenido'])), 0, 200)) ?>...
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-pubs vertical-card-wrapper" style="padding: 50px; background: rgba(255,255,255,0.4); text-align: center;">
                        <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
                        <h3 style="margin-bottom: 10px;">No se registran artículos compartidos.</h3>
                        
                        <?php if($es_mi_perfil && $rol_actual != 1): ?>
                            <?php if($peticionPendiente): ?>
                                <p style="color: #d97706; font-weight: 600; margin-top: 15px;"><i class="fas fa-clock"></i> Cuentas con una solicitud de colaboración en proceso de evaluación.</p>
                            <?php else: ?>
                                <p style="color: var(--texto-oscuro); margin-bottom: 20px; margin-top: 15px; font-weight: 500;"><?= $mensaje_solicitud ?></p>
                                <button type="button" class="btn-glass-gray" onclick="document.getElementById('modal-solicitar-rol').classList.add('active')" style="margin: 0 auto;">
                                    <i class="fas fa-user-plus"></i> Solicitar cambio de rol
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if($es_mi_perfil): ?>
            <div id="Likes" class="tab-content" style="position: relative; z-index: 5;">
                <div class="lista-vertical">
                    <?php if (count($misLikes) > 0): ?>
                        <?php foreach($misLikes as $pub): ?>
                            <div class="vertical-card-wrapper" onclick="window.location.href='publicacion.php?id=<?= $pub['id'] ?>'">
                                <div class="vertical-card-inner">
                                    <?php if($pub['imagen']): ?>
                                        <div class="vertical-imagen">
                                            <img src="../../assets/<?= htmlspecialchars($pub['imagen']) ?>" alt="Imagen de publicación">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="vertical-contenido">
                                        <span class="vertical-categoria"><?= htmlspecialchars($pub['categoria_nombre'] ?? 'General') ?></span>
                                        <h2 class="vertical-titulo"><?= htmlspecialchars($pub['titulo']) ?></h2>
                                        <div class="vertical-meta">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($pub['autor'] ?? 'Desconocido') ?> &nbsp;|&nbsp; 
                                            <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($pub['fecha_creacion'])) ?>
                                        </div>
                                        <p class="vertical-resumen">
                                            <?= htmlspecialchars(mb_substr(strip_tags(html_entity_decode($pub['contenido'])), 0, 200)) ?>...
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-pubs vertical-card-wrapper" style="padding: 40px; background: rgba(255,255,255,0.4);">
                            <i class="fas fa-heart-broken"></i>
                            <h3>No se registran marcas de 'Me gusta'.</h3>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="Comentarios" class="tab-content" style="position: relative; z-index: 5;">
                <?php if (count($misComentarios) > 0): ?>
                    <?php foreach($misComentarios as $com): ?>
                        <div class="mi-comentario-item vertical-card-wrapper" style="margin-bottom: 20px; padding: 0;" onclick="if(!event.target.closest('a') && !event.target.closest('button')) window.location.href='publicacion.php?id=<?= $com['publicacion_id'] ?>'">
                            <div style="padding: 25px;">
                                <div class="comentario-header">
                                    <div class="comentario-meta">
                                        Aporte en: <strong><?= htmlspecialchars($com['pub_titulo']) ?></strong> 
                                        <br><small><?= date('d M Y, H:i', strtotime($com['fecha_creacion'])) ?></small>
                                    </div>
                                    <?php if($es_mi_perfil): ?>
                                        <a href="perfil.php?eliminar_com=<?= $com['id'] ?>" class="btn-delete-com" onclick="return confirm('¿Confirmas la remoción de esta opinión?');">
                                            <i class="fas fa-trash-alt"></i> Quitar
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="comentario-texto">
                                    "<?= nl2br(htmlspecialchars($com['contenido'])) ?>"
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-pubs vertical-card-wrapper" style="padding: 40px; background: rgba(255,255,255,0.4);">
                        <i class="fas fa-comment-slash"></i>
                        <h3>No se registran comentarios en el sistema.</h3>
                    </div>
                <?php endif; ?>
            </div>

            <div id="Rechazados" class="tab-content" style="position: relative; z-index: 5;">
                <div class="lista-vertical">
                    <?php if (count($misRechazados) > 0): ?>
                        <?php foreach($misRechazados as $pub): ?>
                            <div class="vertical-card-wrapper publicacion-rechazada" data-pub-id="<?= $pub['id'] ?>">
                                <div class="vertical-card-inner">
                                    <?php if($pub['imagen']): ?>
                                        <div class="vertical-imagen" style="opacity: 0.8;">
                                            <img src="../../assets/<?= htmlspecialchars($pub['imagen']) ?>" alt="Imagen">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="vertical-contenido">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                            <span class="vertical-categoria" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;"><?= htmlspecialchars($pub['categoria_nombre'] ?? 'General') ?></span>
                                            <button onclick="abrirModalEditarPost(<?= $pub['id'] ?>)" class="btn-p-edit" style="padding: 0.5em 1em; font-size: 0.8rem; color: #ef4444; border-color: #ef4444;">
                                                <i class="fas fa-pencil-alt"></i> Corregir
                                            </button>
                                        </div>

                                        <!-- ========================================== -->
                                        <!-- DIVs OCULTOS NECESARIOS PARA EDITAR (CORRECCIÓN) -->
                                        <!-- ========================================== -->
                                        <div id="data-titulo-<?= $pub['id'] ?>" style="display:none;"><?= htmlspecialchars($pub['titulo']) ?></div>
                                        <div id="data-contenido-<?= $pub['id'] ?>" style="display:none;"><?= htmlspecialchars($pub['contenido']) ?></div>
                                        <div id="data-cat-id-<?= $pub['id'] ?>" style="display:none;"><?= $pub['categoria_id'] ?></div>

                                        <h2 class="vertical-titulo" style="color: #64748b;"><?= htmlspecialchars($pub['titulo']) ?></h2>
                                        <div class="publicacion-meta" style="margin-bottom: 15px; font-size: 0.8rem; color: var(--texto-secundario);">
                                            <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($pub['fecha_creacion'])) ?>
                                        </div>
                                        
                                        <div class="observacion-box">
                                            <strong style="display:block; margin-bottom:5px;"><i class="fas fa-exclamation-circle"></i> Nota de moderación:</strong>
                                            <?= nl2br(htmlspecialchars($pub['observacion'] ?? 'Ajustes requeridos no especificados.')) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <div id="modal-solicitar-rol" class="modal-overlay">
        <div class="modal-box modal-profile-wide" style="max-width: 600px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; color: var(--texto-titulos); font-weight: 800;">
                    <i class="fas fa-user-tag" style="color: #3b82f6;"></i> Postular nuevo rol
                </h2>
                <button type="button" onclick="cerrarModal('modal-solicitar-rol')" style="background:none; border:none; font-size: 1.8rem; cursor:pointer; color: #94a3b8; line-height: 1;">&times;</button>
            </div>
            
            <form action="perfil.php" method="POST">
                <input type="hidden" name="accion" value="solicitar_rol">
                
                <div class="form-group">
                    <label>Selecciona el perfil deseado:</label>
                    <select name="rol_solicitado" class="input-expand-glass" required>
                        <?php if ($rol_actual == 4 || $rol_actual > 3): ?>
                            <option value="3">Autor (Redactar artículos sobre ODS 7)</option>
                            <option value="2">Editor (Evaluar y gestionar contenido del portal)</option>
                        <?php elseif ($rol_actual == 3): ?>
                            <option value="2">Editor (Evaluar y gestionar contenido del portal)</option>
                        <?php elseif ($rol_actual == 2): ?>
                            <option value="1">Administrador (Gestión total de la plataforma)</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label>Expón detalladamente tus motivos o justificación:</label>
                    <textarea name="motivo" class="input-expand-glass" style="min-height: 120px; resize: vertical;" placeholder="Escribe aquí las razones por las que deseas cambiar de rol..." required></textarea>
                </div>
                
                <div class="modal-buttons" style="margin-top: 25px;">
                    <button type="button" class="btn-modal-cancel" onclick="cerrarModal('modal-solicitar-rol')">Cancelar</button>
                    <button type="submit" class="btn-glass-gray">
                        <i class="fas fa-paper-plane" style="color: #3b82f6;"></i> Enviar solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-perfil" class="modal-overlay <?= !empty($error_perfil) || isset($_SESSION['edicion_desbloqueada']) ? 'active' : '' ?>">
        <div class="modal-box modal-profile-wide">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; color: var(--color-accion);">
                    <i class="fas <?= isset($_SESSION['edicion_desbloqueada']) ? 'fa-user-edit' : 'fa-user-lock' ?>"></i> 
                    <?= isset($_SESSION['edicion_desbloqueada']) ? 'Modificar Información Sensible' : 'Datos de la Cuenta' ?>
                </h2>
                <button type="button" onclick="window.location.href='perfil.php?accion=cancelar_edicion'" style="background:none; border:none; font-size: 1.8rem; cursor:pointer; color: #94a3b8; line-height: 1;">&times;</button>
            </div>

            <?php if(!empty($error_perfil)): ?>
                <div style="background: #fee2e2; color: #ef4444; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; text-align: left; border: 1px solid #fca5a5;">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_perfil) ?>
                </div>
            <?php endif; ?>

            <div class="profile-modal-split">
                <div class="profile-modal-left">
                    <div class="uiverse-photo-card" onclick="document.getElementById('input_foto_oculto').click();" title="Haz clic para subir foto">
                        <?php if ($userData['foto_perfil']): ?>
                            <img src="../../assets/<?= htmlspecialchars($userData['foto_perfil']) ?>" alt="Foto de perfil" id="perfil-preview">
                        <?php else: ?>
                            <div id="perfil-placeholder" style="z-index: 2; width: 100%; height: 100%; display: flex; justify-content: center; align-items: center;">
                                <i class="fas fa-user" style="font-size: 5rem; color: #cbd5e1;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="photo-overlay">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2.5rem;"></i>
                            <span>Cambiar</span>
                        </div>
                    </div>

                    <div style="width: 100%; max-width: 240px; text-align: center;">
                        <input form="form-editar" type="file" id="input_foto_oculto" name="foto_perfil" accept="image/jpeg, image/png, image/webp" style="display:none;" onchange="previewImagenPerfil(this)">
                        
                        <?php if ($userData['foto_perfil']): ?>
                            <div style="background: #fee2e2; padding: 10px; border-radius: 8px; border: 1px dashed #fca5a5; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <input form="form-editar" type="checkbox" name="quitar_foto" id="quitar_foto" style="cursor: pointer; transform: scale(1.2);">
                                <label for="quitar_foto" style="color: #ef4444; font-size: 0.9rem; font-weight: 600; cursor: pointer; margin: 0;">Quitar foto actual</label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-modal-right">
                    <?php if (!isset($_SESSION['edicion_desbloqueada'])): ?>
                        <div class="readonly-data">
                            <i class="fas fa-user-circle"></i> <?= htmlspecialchars($userData['nombre']) ?>
                        </div>
                        <div class="readonly-data">
                            <i class="fas fa-id-card"></i> <?= $userData['descripcion'] ? htmlspecialchars($userData['descripcion']) : '<em>Sin descripción.</em>' ?>
                        </div>

                        <div style="border-top: 1px dashed var(--borde-tarjeta); margin: 20px 0;"></div>

                        <div class="readonly-data" style="opacity: 0.6; margin-bottom: 25px;">
                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($userData['email']) ?>
                            <i class="fas fa-lock" style="margin-left: auto; color: #f59e0b;"></i>
                        </div>

                        <form action="perfil.php" method="POST" style="background: var(--fondo-suave); padding: 20px; border-radius: 12px; border: 1px dashed var(--borde-tarjeta);">
                            <input type="hidden" name="accion" value="desbloquear_sensible">
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label style="color: #f59e0b;"><i class="fas fa-key"></i> Autorización requerida para datos sensibles:</label>
                                <p style="font-size: 0.85rem; color: var(--texto-secundario); margin-bottom: 10px;">Introduce tu contraseña actual para habilitar la modificación de Correo y Contraseña.</p>
                                <div style="position: relative; display: flex; align-items: center;">
                                    <input type="password" name="password_actual" id="password_actual" class="input-expand-glass" placeholder="Tu contraseña actual" required style="margin-bottom: 0;">
                                </div>
                            </div>
                            <div style="text-align: right; margin-top: 15px;">
                                <button type="submit" class="btn-p-action" style="background: #f59e0b;">
                                    <i class="fas fa-unlock-alt"></i> Habilitar Edición Sensible
                                </button>
                            </div>
                        </form>

                    <?php else: ?>
                        <form id="form-editar" action="perfil.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="accion" value="actualizar_perfil">

                            <div class="form-group">
                                <label>Nombre Completo:</label>
                                <input type="text" name="nombre" value="<?= htmlspecialchars($userData['nombre']) ?>" class="input-expand-glass" required>
                            </div>

                            <div class="form-group">
                                <label>Breve Descripción:</label>
                                <textarea name="descripcion" class="input-expand-glass" placeholder="Cuéntanos un poco sobre ti (máx 255 caracteres)" maxlength="255" style="resize:vertical; min-height:80px;"><?= htmlspecialchars($userData['descripcion']) ?></textarea>
                            </div>

                            <div style="border-top: 1px dashed var(--color-accion); margin: 20px 0;"></div>

                            <div class="form-group">
                                <label style="color: var(--color-accion);"><i class="fas fa-envelope"></i> Correo Electrónico (Sensible):</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($userData['email']) ?>" class="input-expand-glass" required style="border-color: var(--color-accion) !important;">
                            </div>

                            <div class="form-group">
                                <label style="color: var(--color-accion);"><i class="fas fa-key"></i> Nueva Contraseña (Sensible):</label>
                                <div style="position: relative; display: flex; align-items: center; width: 100%;">
                                    <input type="password" name="password" id="perfil-pass" class="input-expand-glass" placeholder="Mínimo 8 caracteres" style="margin-bottom: 0; padding-right: 45px; border-color: var(--color-accion) !important;">
                                    <i class="fas fa-eye" onclick="togglePass('perfil-pass', this)" style="position: absolute; right: 25px; cursor: pointer; color: #64748b; font-size: 1.1rem; z-index: 10;"></i>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>

                </div>
            </div>

            <div class="profile-modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="window.location.href='perfil.php?accion=cancelar_edicion'">
                    <?= isset($_SESSION['edicion_desbloqueada']) ? 'Cancelar' : 'Cerrar' ?>
                </button>
                <?php if (isset($_SESSION['edicion_desbloqueada'])): ?>
                    <button type="submit" form="form-editar" class="btn-p-action">
                        <i class="fas fa-save"></i> Guardar Cambios Sensibles
                    </button>
                <?php endif; ?>
                <?php if (!isset($_SESSION['edicion_desbloqueada'])): ?>
                    <button type="submit" form="form-editar" class="btn-p-action">
                        <i class="fas fa-save"></i> Guardar Datos
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="modal-editar-post" class="modal-overlay">
        <div class="modal-box modal-edit-wide">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; color: #f59e0b;"><i class="fas fa-pencil-alt"></i> Editar Publicación</h2>
                <button type="button" onclick="cerrarModal('modal-editar-post')" style="background:none; border:none; font-size: 1.8rem; cursor:pointer; color: #94a3b8; line-height: 1;">&times;</button>
            </div>

            <form action="perfil.php" method="POST" enctype="multipart/form-data" id="form-editar-post-perfil">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="pub_id" id="edit-id-post">

                <div class="form-group">
                    <label>Título:</label>
                    <input type="text" name="titulo" id="edit-titulo-post" class="input-expand-glass" required>
                </div>

                <div class="form-group">
                    <label>Categoría:</label>
                    <select name="categoria" id="edit-cat-post" class="input-expand-glass" required>
                        <?php foreach($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Actualizar Imagen (Opcional):</label>
                    <input type="file" name="imagen" class="input-expand-glass" accept="image/jpeg, image/png, image/webp" style="padding: 10px;">
                </div>

                <div class="form-group">
                    <label>Contenido:</label>
                    <div class="quill-wrapper input-expand-glass" style="padding: 0 !important; width: 100%;">
                        <div id="editor-container-perfil"></div>
                    </div>
                    <input type="hidden" name="contenido" id="edit-contenido-post-hidden">
                </div>

                <div class="modal-buttons" style="margin-top: 25px;">
                    <button type="button" class="btn-modal-cancel" onclick="cerrarModal('modal-editar-post')">Cancelar</button>
                    <button type="submit" class="btn-p-edit" style="color: var(--blanco); background: #f59e0b;">
                        <i class="fas fa-paper-plane"></i> Enviar a revisión
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const usuarioLogueado = true;

        function togglePass(id, el) {
            const input = document.getElementById(id);
            if (input.type === "password") {
                input.type = "text";
                el.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = "password";
                el.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }

        function abrirModalPerfil() {
            document.getElementById('modal-perfil').classList.add('active');
            const passInput = document.getElementById('password_actual');
            if(passInput) passInput.focus();
        }

        // QUILL.JS CONFIGURACIÓN PARA PERFIL 
        function imageHandlerPerfil() {
            const input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');
            input.click();

            input.onchange = async () => {
                const file = input.files[0];
                const formData = new FormData();
                formData.append('imagen_quill', file);

                try {
                    const response = await fetch('../../backend/controllers/upload_quill.php', { method: 'POST', body: formData });
                    const data = await response.json();
                    
                    if(data.success) {
                        const range = quillPerfil.getSelection(true);
                        quillPerfil.insertEmbed(range.index, 'image', data.url);
                        quillPerfil.setSelection(range.index + 1);
                    } else {
                        alert("Error al subir imagen: " + data.error);
                    }
                } catch(e) {
                    console.error(e);
                    alert("Error de conexión al subir la imagen.");
                }
            };
        }

        var quillPerfil = new Quill('#editor-container-perfil', {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'color': [] }, { 'background': [] }],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'align': [] }],
                        ['link', 'image', 'video'],
                        ['clean']
                    ],
                    handlers: { image: imageHandlerPerfil }
                }
            }
        });

        function abrirModalEditarPost(id) {
            document.getElementById('edit-id-post').value = id;
            document.getElementById('edit-titulo-post').value = document.getElementById('data-titulo-' + id).textContent;
            
            // Cargar el HTML crudo al editor Quill
            quillPerfil.root.innerHTML = document.getElementById('data-contenido-' + id).textContent;
            
            const catId = document.getElementById('data-cat-id-' + id).textContent;
            document.getElementById('edit-cat-post').value = catId;

            document.getElementById('modal-editar-post').classList.add('active');
        }

        // Sincronizar contenido Quill antes de enviar
        document.getElementById('form-editar-post-perfil').onsubmit = function() {
            document.getElementById('edit-contenido-post-hidden').value = document.querySelector('#editor-container-perfil .ql-editor').innerHTML;
        };

        function cerrarModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function previewImagenPerfil(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imgPreview = document.getElementById('perfil-preview');
                    const placeholder = document.getElementById('perfil-placeholder');
                    
                    if (imgPreview) {
                        imgPreview.src = e.target.result;
                    } else if (placeholder) {
                        placeholder.innerHTML = `<img src="${e.target.result}" alt="Foto de perfil">`;
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                if(e.target.id === 'modal-perfil'){
                    window.location.href = 'perfil.php?accion=cancelar_edicion';
                } else {
                    cerrarModal(e.target.id);
                }
            }
        });
    </script>
    
    <script src="../js/like-logic.js"></script>
</body>
</html>