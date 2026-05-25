# Red-novable

## Descripción:

Red-novable es una plataforma web de publicación y gestión de contenidos digitales enfocada en la difusión de información sobre energías asequibles y no contaminantes.

La aplicación funciona como un CMS completo que permite crear, administrar y publicar contenidos mediante un sistema de roles, control de acceso, interacciones sociales (comentarios y "me gusta") y una experiencia de usuario enriquecida con asistente virtual (mascota interactiva).

Proyecto desarrollado como parte del **Proyecto Integrador de Ingeniería de Software** — Universidad de Colima.

## Objetivo

Diseñar e implementar una plataforma web que permita la creación, gestión y moderación de contenidos digitales, fomentando la interacción comunitaria y la difusión de energías sostenibles.

---

### Página Principal
![Página principal con la mascota Franxx](/assets/pagina_principal.png)

### Dashboard de Administración
![Dashboard de administración](/assets/dashboard.png)

### Panel de Editor
![Panel de editor](/assets/panel_editor.png)

### Registro con Google
![Formulario de registro con botón de Google](/assets/registro_google.png)

---

## Funcionalidades del proyecto

### Módulo de Autenticación y Usuarios
- Registro e inicio de sesión con validación segura (contraseña hasheada con `password_hash`).
- **Inicio de sesión con Google OAuth** (registro automático como rol usuario).
- Recuperación de sesión mediante variables de sesión PHP.
- Perfil de usuario: edición de nombre y foto de perfil, visualización de estadísticas personales.
- Sistema de roles con permisos granulares:
  - **Administrador (rol_id=1)**: Control total (usuarios, roles, publicaciones, categorías, comentarios, estadísticas).
  - **Editor (rol_id=2)**: Revisa y aprueba/rechaza publicaciones pendientes, modera comentarios, gestiona contenido.
  - **Autor (rol_id=3)**: Crea y edita sus propias publicaciones (requieren aprobación si no es editor/admin).
  - **Usuario (rol_id=4)**: Visualiza contenido, da "me gusta" y comenta.
- Panel de administración con:
  - Gestión de usuarios (cambio de roles, eliminación, filtrado por rol).
  - Gestión de comentarios (eliminación).
  - Gestión de contenido (editar/eliminar publicaciones, gestionar categorías).
  - Revisión de publicaciones pendientes con previsualización y edición previa a la aprobación.
  - Dashboard con estadísticas generales (counters de usuarios, publicaciones, comentarios, likes).

### Módulo de Publicaciones
- CRUD completo de publicaciones con soporte para imágenes (subida física a `assets/uploads/`).
- Editor de texto enriquecido (textarea básico con soporte para saltos de línea).
- Estados de publicación: `pendiente`, `publicado`, `rechazado`, `borrador`.
- Asignación de categorías existentes o **creación dinámica de nuevas categorías** desde el formulario de publicación.
- Visualización en página principal:
  - Carrusel/grid horizontal de publicaciones destacadas (primeras 4).
  - Lista vertical infinita con el resto.
- Página por categoría: muestra todas las publicaciones aprobadas de una categoría específica.

### Interacciones Sociales (AJAX)
- **Sistema de "Me gusta"**: 
  - Implementado con JavaScript fetch y endpoints PHP (`toggle_like.php`).
  - Actualización en tiempo real del contador y cambio visual del botón (rojo/negro).
  - Los usuarios solo pueden dar un like por publicación.
- **Comentarios**:
  - Envío asíncrono mediante AJAX (`guardar_comentario.php`).
  - Visualización instantánea del nuevo comentario sin recargar la página.
  - Los usuarios pueden eliminar sus propios comentarios desde su perfil.

### Asistente Virtual Interactivo (Mascota "Franxx")
- Personaje animado que aparece en la esquina inferior izquierda/derecha (cambia de lado aleatoriamente).
- **Mensajes contextuales** según la página visitada (saludos, tips técnicos, recordatorios).
- **Consejos sobre energías renovables** (base de datos de 50+ mensajes sobre ODS 7).
- **Modo oscuro sincronizado**: la mascota detecta y respeta la preferencia del usuario.
- **Interacción**: hacer clic en la mascota muestra un nuevo tip; arrastrarla (drag & drop) permite reubicarla.
- Animaciones de parpadeo y cambio de expresión al hablar.

### Sidebar Inteligente (Panel Admin)
- Contiene enlaces según el rol del usuario.
- Franxx integrado en la sidebar con mensajes técnicos para administradores/ editores (tips de base de datos, buenas prácticas, seguridad).

## Tecnologías Utilizadas

| Capa          | Tecnologías |
|---------------|-------------|
| Frontend      | HTML5, CSS3, JavaScript|
| Backend       | PHP 8.0 (POO, PDO, sesiones) |
| Base de Datos | MySQL |
| Autenticación | Google OAuth 2.0 (API cliente de Google) |
| Servidor Local| XAMPP (Apache, MySQL) |
| Herramientas  | Git, GitHub, Composer (para la librería de Google Client) |

## Estructura del Proyecto (Actualizada)
-Eq3_PI_web_ODS7/
-├── assets/ # Archivos subidos (imágenes de publicaciones/perfil)
-│ └── uploads/
-├── backend/
-│ ├── controllers/ # Controladores (Auth, Publicacion, Categorias, Comentario, Like, Usuario)
-│ └── models/ # Modelos (Usuario, Publicacion, Categorias, Comentario, Like)
-├── config/ # Configuración (conexión DB, google_config.php)
-├── database/ # Script SQL (schema.sql)
-├── frontend/
-│ ├── admin/ # Panel de administración (dashboard, usuarios, comentarios, revisar, gestionar_contenido, sidebar)
-│ ├── ajax/ # Endpoints AJAX (guardar_comentario.php, toggle_like.php)
-│ ├── css/ # Estilos globales (navbar, index, categorías, perfil, mascota, etc.)
-│ ├── css_dash/ # Estilos del panel admin
-│ ├── image/ # Logos, ilustraciones y sprites de la mascota Franxx
-│ ├── js/ # Scripts del lado del cliente (comentarios.js, like-logic.js, mascota.js, franxx-sidebar.js, usuarios.js)
-│ └── pages/ # Vistas públicas (index, navbar, footer, inicioSesion, registro, categorias, categoria, perfil, logout, google_callback)
-├── .gitignore
-├── composer.json # Dependencias PHP (Google Client)
-├── composer.lock
-└── README.md

## Roles del Sistema

| Rol               | Permisos |
|-------------------|----------------------------------------------------------------------------------------------------------------|
| **Administrador** | Control total: gestionar usuarios (cambiar roles, eliminar), gestionar contenido (editar/eliminar publicaciones y categorías)moderar         comentarios, aprobar/rechazar publicaciones, ver dashboard.
| **Editor**        | Revisar publicaciones pendientes (aprobar/rechazar), moderar comentarios, gestionar categorías, editar cualquier publicación. |
| **Autor**         | Crear nuevas publicaciones, editar/eliminar sus propias publicaciones (quedan pendientes de aprobación), subir imágenes. |
| **Usuario**       | Visualizar publicaciones aprobadas, dar "me gusta", comentar, editar su perfil (nombre y foto). |

## Pasos para la instalación Local

### Requisitos Previos
- XAMPP (o cualquier servidor con PHP 8.0+, MySQL).
- Composer (para instalar la librería de Google API Client).
- Cuenta de Google Developer para obtener `client_id` y `client_secret` (opcional si no se usa Google Login).

### Pasos:

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/Alejandrovazquezat/red-novable_Eq3.git
   ```

2. Mover la carpeta a htdocs de XAMPP
   ```bash
   C:\xampp\htdocs\nombre_de_la_carpeta
   ```

3. Instalar dependencias de Composer (desde la raíz del proyecto)
   ```bash
   composer install
   ```
   Esto descargará google/apiclient necesario para la autenticación con Google.

4. Configurar la base de datos

   Abrir phpMyAdmin: http://localhost/phpmyadmin

   Crear la base de datos:
   ```sql
   CREATE DATABASE plataforma_contenidos;
   ```
   Importar el archivo database/schema.sql.

5. Configurar credenciales de base de datos
   Editar config/Conexion.php:
   ```php
   private $host = "localhost";
   private $db_name = "plataforma_contenidos";
   private $username = "root";
   private $password = "";
   ```

6. Configurar Google OAuth (opcional)

   Ir a Google Cloud Console, crear un proyecto y habilitar la API de Google+ (o People API).

   Crear credenciales de tipo "ID de cliente de OAuth" para aplicación web.

   Autorizar la URI de redirección: http://localhost/Eq3_PI_web_ODS7/frontend/pages/google_callback.php.

   Copiar el client_id y client_secret en config/google_config.php.

7. Asegurar permisos de escritura en la carpeta assets/uploads/ (para subir imágenes).

8. Acceder a la aplicación
   ```
   http://localhost/Eq3_PI_web_ODS7/frontend/pages/index.php
   ```


## Estado del Proyecto
En desarrollo activo.


## Equipo de Desarrollo - Equipo 3
- Carlos Arturo Argüellez Ruiz
- Jesús Enrique Ibarra Figueroa
- Alan Yakxel Juárez Cano
- Fernando Franco Juárez Lara
- Diego Alejandro Vazquez Atanacio
