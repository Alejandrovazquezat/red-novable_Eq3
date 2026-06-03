<?php
session_start();

// Validar privilegios estrictos de Administrador (rol_id = 1)
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) { 
    header("Location: ../pages/index.php");
    exit;
}

require_once '../../config/Conexion.php';
$db = (new Conexion())->getConexion();

// ==========================================
// ACCIÓN: APROBAR PETICIÓN
// ==========================================
if (isset($_GET['aprobar'])) {
    $peticion_id = intval($_GET['aprobar']);
    
    $db->beginTransaction();
    try {
        // Obtener datos de la petición
        $stmt = $db->prepare("SELECT usuario_id, rol_solicitado_id FROM peticiones_rol WHERE id = ?");
        $stmt->execute([$peticion_id]);
        $peticion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($peticion) {
            // Actualizar rol del usuario
            $upUser = $db->prepare("UPDATE usuarios SET rol_id = ? WHERE id = ?");
            $upUser->execute([$peticion['rol_solicitado_id'], $peticion['usuario_id']]);
            
            // Marcar petición como aprobada
            $upPet = $db->prepare("UPDATE peticiones_rol SET estado = 'aprobado' WHERE id = ?");
            $upPet->execute([$peticion_id]);
            
            $db->commit();
            header("Location: peticiones_rol.php?msg=aprobado");
            exit;
        }
    } catch (Exception $e) {
        $db->rollBack();
    }
}

// ==========================================
// ACCIÓN: RECHAZAR PETICIÓN
// ==========================================
if (isset($_GET['rechazar'])) {
    $peticion_id = intval($_GET['rechazar']);
    $stmt = $db->prepare("UPDATE peticiones_rol SET estado = 'rechazado' WHERE id = ?");
    $stmt->execute([$peticion_id]);
    header("Location: peticiones_rol.php?msg=rechazado");
    exit;
}

// ==========================================
// OBTENER SOLICITUDES PENDIENTES
// ==========================================
$query = "SELECT pr.id, pr.motivo, pr.fecha_creacion, pr.rol_solicitado_id, u.nombre AS usuario_nombre, r.nombre AS rol_solicitado_nombre 
          FROM peticiones_rol pr 
          JOIN usuarios u ON pr.usuario_id = u.id 
          JOIN roles r ON pr.rol_solicitado_id = r.id 
          WHERE pr.estado = 'pendiente' 
          ORDER BY pr.fecha_creacion ASC";
$peticiones = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peticiones de Rol - Red-novable</title>
    <link rel="stylesheet" href="../css_dash/style.css"> 
    <link rel="stylesheet" href="../css_dash/peticiones_styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body style="display: flex;">

    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <?php include 'sidebar.php'; ?>
    
    <main class="main-content">
        <div class="container">
            <div class="header-admin">
                <h1 style="color: var(--text-dark);"><i class="fas fa-user-shield"></i> Peticiones de Cambio de Rol</h1>
            </div>

            <?php if(isset($_GET['msg'])): ?>
                <?php if($_GET['msg'] == 'aprobado'): ?>
                    <div class="alert-review alert-success-review"><i class="fas fa-check-circle"></i> Petición aprobada. El usuario ahora cuenta con el nuevo rol.</div>
                <?php elseif($_GET['msg'] == 'rechazado'): ?>
                    <div class="alert-review alert-danger-review"><i class="fas fa-times-circle"></i> Petición rechazada exitosamente.</div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="filtro-publicaciones-container-card">
                <div class="filtro-categoria-box">
                    <div class="filtro-label">
                        <i class="fas fa-user-tag"></i>
                        <label>Filtrar por Rol Solicitado:</label>
                    </div>
                    <div class="filtro-form">
                        <select id="select-filtro-rol">
                            <option value="todos">Todos los roles</option>
                            <option value="2">Editor</option>
                            <option value="3">Autor</option>
                            </select>
                    </div>
                </div>

                <div class="search-box-uiverse">
                    <input type="text" id="input-buscar-peticion" placeholder="Buscar por usuario o motivo..." class="input-search-uiverse">
                </div>

                <div class="total-publicaciones-box">
                    Pendientes: <span id="total-count-badge"><?= count($peticiones) ?></span> solicitudes
                </div>
            </div>

            <div class="table-cristal-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr><th>Usuario</th><th>Rol Solicitado</th><th>Motivo / Justificación</th><th>Fecha</th><th>Acciones</th></tr>
                    </thead>
                    <tbody id="table-peticiones-body">
                        <?php if(count($peticiones) > 0): ?>
                            <?php foreach($peticiones as $p): ?>
                                <tr class="peticion-row-item" data-rol-id="<?= $p['rol_solicitado_id'] ?>">
                                    <td><strong class="pet-user-cell"><?= htmlspecialchars($p['usuario_nombre']) ?></strong></td>
                                    <td>
                                        <span class="cat-tag" style="background:#3b82f6; color:white;">
                                            <?= htmlspecialchars($p['rol_solicitado_nombre']) ?>
                                        </span>
                                    </td>
                                    <td class="pet-motivo-cell" style="max-width: 350px; word-break: break-word; line-height: 1.5;">
                                        <?= nl2br(htmlspecialchars($p['motivo'])) ?>
                                    </td>
                                    <td style="color: var(--text-light); font-size: 0.9rem; font-weight: 500;">
                                        <?= date('d/m/Y H:i', strtotime($p['fecha_creacion'])) ?>
                                    </td>
                                    <td>
                                        <div class="actions-group">
                                            <button class="btn-success" onclick="abrirModalAccion('aprobar', <?= $p['id'] ?>, '<?= htmlspecialchars($p['usuario_nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['rol_solicitado_nombre'], ENT_QUOTES) ?>')" title="Aprobar cambio">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                            <button class="btn-danger" onclick="abrirModalAccion('rechazar', <?= $p['id'] ?>, '<?= htmlspecialchars($p['usuario_nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['rol_solicitado_nombre'], ENT_QUOTES) ?>')" title="Rechazar petición">
                                                <i class="fas fa-user-times"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="no-initial-data-row"><td colspan="5" style="text-align:center; padding:50px; color: var(--text-light);">No se registran solicitudes de rol pendientes de evaluación.</td></tr>
                        <?php endif; ?>
                        <tr id="js-empty-pet-row" style="display: none;">
                            <td colspan="5" style="text-align: center; padding: 50px; color: var(--text-light);">
                                <i class="fas fa-search" style="font-size: 2.5rem; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
                                No se encontraron solicitudes con los criterios seleccionados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="modal-accion" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon-container" id="modal-icon-bg">
                <i id="modal-icon" class="fas fa-question"></i>
            </div>
            <h2 class="modal-title" id="modal-title"></h2>
            <p class="modal-text" id="modal-mensaje"></p>
            <div class="modal-buttons">
                <button class="btn-modal-cancel" onclick="cerrarModal('modal-accion')">Cancelar</button>
                <button class="btn-modal-confirm" id="btn-confirmar-accion">Sí, confirmar</button>
            </div>
        </div>
    </div>

    <script>
        function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }

        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                cerrarModal(e.target.id);
            }
        });

        // 🔥 LÓGICA DE FILTRADO EN TIEMPO REAL 🔥
        const inputBuscar = document.getElementById('input-buscar-peticion');
        const selectRol = document.getElementById('select-filtro-rol');
        const rows = document.querySelectorAll('.peticion-row-item');
        const emptyRow = document.getElementById('js-empty-pet-row');

        function filtrarPeticiones() {
            const searchText = inputBuscar.value.toLowerCase().trim();
            const selectedRol = selectRol.value;
            let visibles = 0;

            rows.forEach(row => {
                const usuario = row.querySelector('.pet-user-cell').textContent.toLowerCase();
                const motivo = row.querySelector('.pet-motivo-cell').textContent.toLowerCase();
                const rowRolId = row.getAttribute('data-rol-id');

                const coincideTexto = usuario.includes(searchText) || motivo.includes(searchText);
                const coincideRol = (selectedRol === 'todos') || (rowRolId === selectedRol);

                if (coincideTexto && coincideRol) {
                    row.style.display = '';
                    visibles++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (emptyRow) {
                emptyRow.style.display = (visibles === 0 && rows.length > 0) ? '' : 'none';
            }
        }

        if(inputBuscar) inputBuscar.addEventListener('input', filtrarPeticiones);
        if(selectRol) selectRol.addEventListener('change', filtrarPeticiones);

        // 🔥 MODALES DE ACCIÓN 🔥
        let accionSeleccionada = null;
        let peticionIdSeleccionada = null;

        function abrirModalAccion(accion, id, usuario, rol) {
            accionSeleccionada = accion; 
            peticionIdSeleccionada = id;
            
            const title = document.getElementById('modal-title');
            const msg = document.getElementById('modal-mensaje');
            const btn = document.getElementById('btn-confirmar-accion');
            const iconBg = document.getElementById('modal-icon-bg');
            const icon = document.getElementById('modal-icon');
            
            if(accion === 'aprobar'){
                title.textContent = '¿Aprobar solicitud?'; 
                msg.innerHTML = `Estás a punto de convertir a <strong>${usuario}</strong> en <strong>${rol}</strong>. Tendrá todos los permisos de este rol inmediatamente.`; 
                btn.className = "btn-modal-confirm confirm-green";
                icon.className = "fas fa-user-check";
                icon.style.color = "#10b981";
            } else if (accion === 'rechazar') {
                title.textContent = '¿Rechazar solicitud?'; 
                msg.innerHTML = `La solicitud de <strong>${usuario}</strong> para ser <strong>${rol}</strong> será descartada y eliminada de la lista.`; 
                btn.className = "btn-modal-confirm confirm-red";
                icon.className = "fas fa-user-times";
                icon.style.color = "#ef4444";
            }

            document.getElementById('modal-accion').classList.add('active');
        }

        document.getElementById('btn-confirmar-accion').addEventListener('click', () => {
            if(accionSeleccionada === 'aprobar'){
                window.location.href = `peticiones_rol.php?aprobar=${peticionIdSeleccionada}`;
            } else if(accionSeleccionada === 'rechazar'){
                window.location.href = `peticiones_rol.php?rechazar=${peticionIdSeleccionada}`;
            }
        });
    </script>
</body>
</html>