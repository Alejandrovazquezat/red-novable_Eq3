<?php

require_once __DIR__ . "/../models/Usuario.php";
// Agregamos los namespaces de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Requerimos el autoload de Composer
require_once __DIR__ . "/../../vendor/autoload.php";

class AuthController {

    private $db;

    public function __construct($db){
        $this->db = $db;
    }


    // REGISTRO DE USUARIO

    public function registrar($nombre, $email, $password, $is_google = false){
        
        // Validaciones básicas (se saltan si viene de Google porque Google ya validó todo)
        if (!$is_google) {
            if (empty($nombre) || empty($email) || empty($password)) {
                return "Todos los campos son obligatorios";
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return "Email no válido";
            }
            
            if (strlen($password) < 6) {
                return "La contraseña debe tener al menos 6 caracteres";
            }
        }

        $usuario = new Usuario($this->db);

        $usuario->nombre = $nombre;
        $usuario->email = $email;
        $usuario->password = $password;

        // Obtener el ID del rol 'usuario' (rol normal)
        $query = "SELECT id FROM roles WHERE nombre = 'usuario'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $rol = $stmt->fetch(PDO::FETCH_ASSOC);
        $usuario->rol_id = $rol['id'] ?? 4; // Si no encuentra, usa 4 como fallback

        // verificar si el email ya existe
        $stmt = $usuario->buscarPorEmail();

        if($stmt->rowCount() > 0){
            return "El email ya está registrado";
        }

        // --- INICIO LÓGICA DE VERIFICACIÓN ---
        if ($is_google) {
            // Si viene de Google, entra automáticamente verificado
            $query_reg = "INSERT INTO usuarios (nombre, email, password, rol_id, verificado) VALUES (:nombre, :email, :password, :rol_id, 1)";
            $stmt_reg = $this->db->prepare($query_reg);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt_reg->bindParam(":nombre", $nombre);
            $stmt_reg->bindParam(":email", $email);
            $stmt_reg->bindParam(":password", $hash);
            $stmt_reg->bindParam(":rol_id", $usuario->rol_id);
            
            if($stmt_reg->execute()){
                return "Registro exitoso";
            }
            return "Error al registrar usuario";
        } else {
            // Si es registro normal, requiere verificación
            $codigo_verificacion = sprintf("%06d", mt_rand(1, 999999)); // Genera 6 dígitos
            
            $query_reg = "INSERT INTO usuarios (nombre, email, password, rol_id, codigo_verificacion, verificado) VALUES (:nombre, :email, :password, :rol_id, :codigo, 0)";
            $stmt_reg = $this->db->prepare($query_reg);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt_reg->bindParam(":nombre", $nombre);
            $stmt_reg->bindParam(":email", $email);
            $stmt_reg->bindParam(":password", $hash);
            $stmt_reg->bindParam(":rol_id", $usuario->rol_id);
            $stmt_reg->bindParam(":codigo", $codigo_verificacion);

            if($stmt_reg->execute()){
                // ENVIAR EL CORREO
                $envio_correo = $this->enviarCorreoVerificacion($email, $nombre, $codigo_verificacion);
                
                if ($envio_correo === true) {
                    // Retornamos esta bandera exacta para que registro.php sepa qué hacer
                    return "requiere_verificacion|" . $email;
                } else {
                    return "Registro guardado, pero falló el envío del correo: " . $envio_correo;
                }
            }
            return "Error al registrar usuario";
        }
    }

    // MÉTODO PRIVADO PARA ENVIAR CORREO
    private function enviarCorreoVerificacion($destinatario, $nombre, $codigo) {
        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor SMTP de IONOS
            $mail->isSMTP();
            // CAMBIA ESTO: Confirma tu host en el panel de IONOS, usualmente es smtp.ionos.mx o smtp.ionos.com
            $mail->Host       = 'smtp.ionos.mx'; 
            $mail->SMTPAuth   = true;
            // CAMBIA ESTO: Tu correo creado en IONOS
            $mail->Username   = 'no-reply@red-novable.com'; 
            // La contraseña del correo de ionos
            $mail->Password   = 'a65981bn32h9ri0sdsdadadda'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
            $mail->Port       = 587; // Puerto para TLS
            $mail->CharSet    = 'UTF-8';

            // Destinatarios
            $mail->setFrom('no-reply@red-novable.com', 'Red-novable');
            $mail->addAddress($destinatario, $nombre);

            // Contenido
            $mail->isHTML(true);
            $mail->Subject = 'Verifica tu cuenta en Red-novable';
            
            // Plantilla HTML básica del correo
            $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f1f5f9; text-align: center;'>
                <div style='background-color: white; padding: 30px; border-radius: 10px; max-width: 500px; margin: 0 auto; border-top: 5px solid #06b6d4;'>
                    <h2 style='color: #1e293b;'>¡Bienvenido a Red-novable, $nombre!</h2>
                    <p style='color: #475569; font-size: 16px;'>Gracias por unirte a nuestra comunidad. Para completar tu registro y poder iniciar sesión, ingresa el siguiente código de verificación:</p>
                    <div style='margin: 30px 0;'>
                        <span style='background-color: #f8fafc; border: 2px dashed #06b6d4; padding: 15px 30px; font-size: 24px; font-weight: bold; color: #06b6d4; letter-spacing: 5px; border-radius: 8px;'>$codigo</span>
                    </div>
                    <p style='color: #94a3b8; font-size: 12px;'>Si no solicitaste este registro, ignora este correo.</p>
                </div>
            </div>
            ";

            $mail->send();
            return true;
        } catch (Exception $e) {
            return $mail->ErrorInfo;
        }
    }


    // VERIFICAR CÓDIGO
    public function verificarCodigo($email, $codigo) {
        $query = "SELECT id, codigo_verificacion FROM usuarios WHERE email = :email AND verificado = 0";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            if ($usuario['codigo_verificacion'] === $codigo) {
                // Actualizar estado a verificado
                $update = "UPDATE usuarios SET verificado = 1, codigo_verificacion = NULL WHERE id = :id";
                $stmtUpdate = $this->db->prepare($update);
                $stmtUpdate->bindParam(":id", $usuario['id']);
                
                if ($stmtUpdate->execute()) {
                    return true;
                }
            }
        }
        return false;
    }

    // LOGIN (Modificado para revisar si está verificado)
    public function login($email, $password){
        
        if (empty($email) || empty($password)) {
            return "Email y contraseña son obligatorios";
        }

        $usuario = new Usuario($this->db);
        $usuario->email = $email;

        $stmt = $usuario->buscarPorEmail();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$row){
            return "Usuario no encontrado";
        }

        // Revisar verificación
        if (isset($row['verificado']) && $row['verificado'] == 0) {
            return "no_verificado|" . $email;
        }

        if(password_verify($password, $row['password'])){

            // --- INICIO: Unificación de sesión ---
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Claves canónicas
            $_SESSION['usuario_id'] = $row['id'];
            $_SESSION['nombre'] = $row['nombre'];
            $_SESSION['rol_id'] = $row['rol_id'];
            $_SESSION['logueado'] = true; // para chequeos rápidos
            
            // Obtener nombre del rol para facilitar
            $query = "SELECT nombre FROM roles WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":id", $row['rol_id']);
            $stmt->execute();
            $rol = $stmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['rol_nombre'] = $rol['nombre'] ?? 'usuario';
            // --- FIN unificación ---

            return "Login correcto";

        }else{
            return "Contraseña incorrecta";
        }
    }


    // LOGOUT
    public function logout(){
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        return "Sesion cerrada";
    }

    // VERIFICAR PERMISOS POR ROL
    public function tienePermiso($usuario_id, $accion_requerida) {
        
        if (!$usuario_id) return false;
        
        // Obtener el rol del usuario
        $query = "SELECT r.nombre as rol_nombre, r.id as rol_id 
                  FROM usuarios u 
                  JOIN roles r ON u.rol_id = r.id 
                  WHERE u.id = :usuario_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":usuario_id", $usuario_id);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) return false;
        
        $rol = $usuario['rol_nombre']; // 'admin', 'editor', 'autor', 'usuario'
        
        // Definir permisos por rol
        $permisos = [
            'admin' => [
            'crear_usuario', 'editar_usuario', 'eliminar_usuario',
            'gestionar_categorias', 'ver_estadisticas',
            'ver_publicaciones_pendientes', 'aprobar_publicacion', 'rechazar_publicacion',
            'editar_cualquier_publicacion', 'publicar_publicacion',
            'moderar_comentarios', 'publicar_directo', 'ver_todas_publicaciones',
            'crear_publicacion', 'editar_mis_publicaciones', 'eliminar_mis_publicaciones',
            'ver_mis_publicaciones', 'subir_imagenes',
            'ver_publicaciones_publicadas', 'comentar', 'dar_like'
            ],
            'editor' => [
            'crear_publicacion',
            'ver_publicaciones_pendientes', 'gestionar_categorias', 'aprobar_publicacion', 'rechazar_publicacion',
            'editar_cualquier_publicacion', 'publicar_publicacion',
            'ver_estadisticas', 'moderar_comentarios',
            'publicar_directo', 'ver_todas_publicaciones'
            ],
            'autor' => [
            'crear_publicacion', 'editar_mis_publicaciones', 'eliminar_mis_publicaciones',
            'ver_mis_publicaciones', 'subir_imagenes'
            ],
            'usuario' => [
            'ver_publicaciones_publicadas', 'comentar', 'dar_like'
            ]
        ];
        
        return in_array($accion_requerida, $permisos[$rol] ?? []);
    }

    // OBTENER ROL DEL USUARIO ACTUAL
    public function obtenerRolActual() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['usuario_id'])) {
            return null;
        }
        
        return [
            'id' => $_SESSION['rol_id'],
            'nombre' => $_SESSION['rol_nombre']
        ];
    }
}
