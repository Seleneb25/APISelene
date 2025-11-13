## Manejo de Excepciones (Try/Catch)

### Implementación en Controladores

Todos los controladores implementan try/catch para capturar y registrar errores:

**AuthController.php:**
```php
try {
    $user = $this->userModel->findByUsername($username);
    if (!$user) {
        Logger::warn("Intento de login fallido - Usuario no encontrado: $username");
        http_response_code(401);
        echo json_encode(["error" => "Credenciales inválidas"]);
        return;
    }
} catch (Exception $e) {
    Logger::error("Error en login: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Error interno del servidor"]);
}
```

**AlumnosController.php:**
```php
try {
    $res = $this->model->create($input);
    Logger::info('POST /alumnos result: ' . json_encode($res));
} catch (PDOException $e) {
    Logger::error("Error al crear alumno - SQL Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error en la base de datos"]);
}
```

**StatsController.php:**
```php
public static function handler() {
    try {
        $uptime = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2);
        $memory = round(memory_get_usage() / 1024 / 1024, 2);
        echo json_encode([
            "uptime_seconds" => $uptime,
            "memory_MB" => $memory,
            "fecha" => date("Y-m-d H:i:s")  
        ]);
    } catch (Exception $e) {
        Logger::error("Error en estadísticas: " . $e->getMessage());
        http_response_code(500);
    }
}
```

**Alumnos.php (Modelo):**
```php
try {
    $stmt = $this->db->query("SELECT id, nombre, edad, correo, rol, created_at 
                            FROM alumnos 
                            WHERE activo = 1 
                            ORDER BY id DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    Logger::error("Error en Alumnos::getAllActive - " . $e->getMessage());
    return [];
}
```

**Ventajas del Try/Catch:**
- Evita que la aplicación se bloquee
- Registra errores detallados en logs
- Proporciona mensajes de error seguros al usuario
- Facilita debugging
- Mejora estabilidad del sistema

---

## Monitoreo de Peticiones (server.log)

### Archivo de Logs Estructurado

Cada petición se registra automáticamente con:
- Timestamp exacto
- Dirección IP del cliente
- Método HTTP (GET, POST, PATCH, DELETE)
- URI de la petición
- Tipo de operación (AUDIT, SECURITY, DATABASE, API)
- Contexto JSON con datos relevantes

**Ejemplo de log completo:**
```
[2024-01-15 14:32:45] [INFO] [IP:127.0.0.1] [POST /api/auth/login] [AUDIT] Usuario logueado | Context: {"user":"admin","action":"login"}
[2024-01-15 14:33:12] [INFO] [IP:127.0.0.1] [POST /api/alumnos] Alumno insertado correctamente con ID: 5
[2024-01-15 14:33:45] [WARN] [IP:127.0.0.1] [DELETE /api/alumnos] [SECURITY] Intento de acceso no autorizado | Context: {"ip":"127.0.0.1"}
[2024-01-15 14:35:20] [ERROR] [IP:127.0.0.1] [DELETE /api/alumnos] Database error | Context: {"error":"SQLSTATE[23000]","query":"DELETE FROM alumnos WHERE id = 999"}
```

### Características de Monitoreo

- **Rotación Automática:** Cuando `server.log` alcanza 5000 líneas, se comprime a gzip en `logs/archive/`
- **Archivo Principal:** `logs/server.log` siempre contiene los eventos recientes
- **Archivo Archivado:** `logs/archive/server.log.YYYYMMDD_HHMMSS.gz` guarda historial comprimido
- **Gestión de Almacenamiento:** Evita que los logs ocupen demasiado espacio
- **Búsqueda de Auditoría:** Es fácil encontrar qué pasó en una fecha/hora específica

### Acceso a Logs

Para ver los logs en tiempo real:
1. Abre `C:\xampp\htdocs\APISelene\logs\server.log` con un editor de texto
2. O accede via navegador: `http://localhost/APISelene/logs/server.log` (si no está protegido)
3. Las líneas más recientes están al final del archivo

---

## Control de Roles y Respuesta 403 Forbidden

### Implementación de RoleMiddleware

El middleware de roles verifica permisos en cada ruta sensible:

```php
// En routes.php
case $resource === 'alumnos' && $method === 'POST':
    RoleMiddleware::handleAdmin(); // Verifica rol admin
    $alumnosController->create();
    break;

case $resource === 'alumnos' && $method === 'DELETE':
    RoleMiddleware::handleAdmin(); // Solo admin puede eliminar
    $alumnosController->delete();
    break;
```

### RoleMiddleware.php

```php
public static function checkRole($requiredRole) {
    $user = AuthMiddleware::getUser();
    
    if (!$user) {
        http_response_code(401); // No autenticado
        echo json_encode(["error" => "No autenticado"]);
        return false;
    }
    
    if ($user['rol'] !== $requiredRole) {
        http_response_code(403); // Forbidden - Sin permisos
        echo json_encode([
            "error" => "Acceso denegado",
            "message" => "No tienes permisos para realizar esta acción",
            "required_role" => $requiredRole,
            "current_role" => $user['rol']
        ]);
        return false;
    }
    
    return true;
}
```

### Respuestas Posibles

**Usuario no autenticado intenta POST:**
```json
{
    "error": "No autenticado",
    "message": "Debes iniciar sesión para acceder a este recurso"
}
```
Código HTTP: **401 Unauthorized**

**Usuario regular (sin admin) intenta DELETE:**
```json
{
    "error": "Acceso denegado",
    "message": "No tienes permisos para realizar esta acción",
    "required_role": "admin",
    "current_role": "user"
}
```
Código HTTP: **403 Forbidden**

**Usuario admin hace DELETE exitoso:**
```json
{
    "success": true,
    "message": "Alumno eliminado correctamente (soft delete)"
}
```
Código HTTP: **200 OK**

### Endpoints Protegidos

| Ruta | Método | Rol Requerido | Error si falla |
|------|--------|---------------|----------------|
| `/api/alumnos` | POST | admin | 403 Forbidden |
| `/api/alumnos` | PATCH | admin | 403 Forbidden |
| `/api/alumnos` | DELETE | admin | 403 Forbidden |
| `/api/alumnos/deleted` | GET | admin | 403 Forbidden |
| `/api/alumnos/restore` | POST | admin | 403 Forbidden |
| `/api/alumnos/force-delete` | DELETE | admin | 403 Forbidden |
| `/api/alumnos` | GET | Cualquiera autenticado | 401 Unauthorized |

---

## Endpoint /stats - Métricas del Servidor

### Respuesta del Endpoint

```json
GET /api/stats

{
    "uptime_seconds": 45.23,
    "memory_MB": 2.5,
    "fecha": "2024-01-15 14:35:20"
}
```

### Implementación

**StatsController.php:**
```php
class StatsController {
    public static function handler() {
        try {
            $uptime = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2);
            $memory = round(memory_get_usage() / 1024 / 1024, 2);
            echo json_encode([
                "uptime_seconds" => $uptime,
                "memory_MB" => $memory,
                "fecha" => date("Y-m-d H:i:s")  
            ]);
        } catch (Exception $e) {
            Logger::error("Error en estadísticas: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["error" => "Error al obtener estadísticas"]);
        }
    }
}
```

### En routes.php

```php
case $resource === 'stats' && $method === 'GET':
    AuthMiddleware::handle(); // Requiere autenticación
    StatsController::handler();
    break;
```

### Métricas Disponibles

- **uptime_seconds:** Tiempo en segundos desde que se inició la petición
- **memory_MB:** Memoria RAM usada en MB
- **fecha:** Timestamp del servidor en formato Y-m-d H:i:s

### Cómo Usar

1. Abre el dashboard
2. Haz clic en botón "Estadísticas"
3. Se mostrará en la consola JSON similar a:
```
[14:35:20] GET /api/stats:
{
  "uptime_seconds": 0.15,
  "memory_MB": 2.34,
  "fecha": "2024-01-15 14:35:20"
}
```

---

## Rate Limiting y Límite de Intentos

### LoginAttemptMiddleware.php

Limita a 3 intentos fallidos de login en 1 minuto:

```php
class LoginAttemptMiddleware {
    private static $maxAttempts = 3;
    private static $lockoutTime = 60; // segundos
    
    public static function checkLoginAttempts($username) {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            // Verificar si usuario está bloqueado
            $stmt = $conn->prepare("SELECT * FROM login_attempts 
                                   WHERE username = :username 
                                   AND locked_until > NOW()");
            $stmt->execute([':username' => $username]);
            
            if ($stmt->rowCount() > 0) {
                http_response_code(429); // Too Many Requests
                echo json_encode([
                    "error" => "Demasiados intentos fallidos",
                    "message" => "Tu cuenta ha sido bloqueada por 1 minuto"
                ]);
                return false;
            }
        } catch (Exception $e) {
            Logger::error("Error en rate limiting: " . $e->getMessage());
        }
        
        return true;
    }
    
    public static function recordFailedAttempt($username) {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("INSERT INTO login_attempts (username, attempt_count, locked_until)
                                   VALUES (:username, 1, DATE_ADD(NOW(), INTERVAL 1 MINUTE))
                                   ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1");
            $stmt->execute([':username' => $username]);
            
            Logger::security("Intento de login fallido registrado", ["user" => $username]);
        } catch (Exception $e) {
            Logger::error("Error al registrar intento fallido: " . $e->getMessage());
        }
    }
    
    public static function clearAttempts($username) {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("DELETE FROM login_attempts WHERE username = :username");
            $stmt->execute([':username' => $username]);
        } catch (Exception $e) {
            Logger::error("Error al limpiar intentos: " . $e->getMessage());
        }
    }
}
```

### Tabla login_attempts

```sql
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    attempt_count INT DEFAULT 1,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Flujo de Rate Limiting

1. Usuario intenta login con credenciales incorrectas
2. Se registra en tabla `login_attempts`
3. Si hay 3 intentos en 1 minuto, cuenta se bloquea
4. API devuelve código **429 Too Many Requests**
5. Después de 1 minuto, se desbloquea automáticamente

### Mensajes al Usuario

**Intento 1 o 2 fallidos:**
```json
{
    "error": "Credenciales inválidas",
    "message": "Intento 2 de 3"
}
```

**Bloqueado después de 3 intentos:**
```json
{
    "error": "Demasiados intentos fallidos",
    "message": "Tu cuenta ha sido bloqueada por 1 minuto",
    "code": 429
}
```

---

## Logs de Actividad y Errores

### Sistema de Logging

El logger implementado en `config/logger.php` incluye:

**Niveles de Log:**
- DEBUG - Información detallada para desarrollo
- INFO - Eventos normales del sistema
- WARN - Advertencias y situaciones inusuales
- ERROR - Errores recuperables
- FATAL - Errores críticos

**Características:**
- Rotación automática de logs (comprime a gzip cada 5000 líneas)
- Archivo principal: `logs/server.log`
- Archivos archivados: `logs/archive/`
- Información completa: timestamp, IP, método HTTP, contexto# APISelene - API REST

## Objetivo General

Desarrollar una API REST completa y modular para la gestión de alumnos, conectada a base de datos MySQL, con sistema robusto de autenticación, control de roles basado en sesiones activas, y operaciones CRUD seguras que incluyen soft delete para preservar la integridad de datos.

---

## Requisitos Previos

### Software a Descargar e Instalar

1. **XAMPP** (incluye Apache, MySQL y PHP)
   - Descargar desde: https://www.apachefriends.org/
   - Instalar en: `C:\xampp\`
   - Iniciar Apache y MySQL desde el Panel de Control de XAMPP

2. **Git** (para clonar el repositorio)
   - Descargar desde: https://git-scm.com/
   - Instalar con opciones por defecto

3. **Editor de Texto** (Visual Studio Code recomendado)
   - Descargar desde: https://code.visualstudio.com/

### Requisitos del Sistema

- Windows, Mac o Linux
- PHP 7.4 o superior (incluido en XAMPP)
- MySQL 5.7 o superior (incluido en XAMPP)
- Navegador web (Chrome, Firefox, Edge)

---

## Instalación Paso a Paso (Para Usuarios Sin Conocimientos Previos)

### Paso 1: Descargar e Instalar XAMPP

1. Ve a https://www.apachefriends.org/
2. Descarga XAMPP para tu sistema operativo
3. Ejecuta el instalador
4. Instala en la ruta por defecto `C:\xampp\`
5. Al terminar, abre "XAMPP Control Panel"
6. Haz clic en "Start" junto a Apache
7. Haz clic en "Start" junto a MySQL
8. Espera a que ambos muestren "Running" en verde

### Paso 2: Clonar el Repositorio

1. Abre CMD (Símbolo del Sistema) o Git Bash
2. Escribe estos comandos uno por uno:

```bash
cd C:\xampp\htdocs
git clone https://github.com/Seleneb25/APISelene.git
cd APISelene
```

3. Presiona Enter después de cada línea

### Paso 3: Crear la Base de Datos

1. En el navegador, ve a `http://localhost/phpmyadmin/`
2. Busca el lado izquierdo donde dice "Nueva" o "New Database"
3. Escribe el nombre: `rest_api_selene`
4. Haz clic en "Crear" o "Create"
5. Ahora haz clic en la base de datos `rest_api_selene` que aparece en la izquierda
6. Busca la pestaña "SQL" en la parte superior
7. Copia y pega este código:

```sql
CREATE TABLE usuarios_auth (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'user') DEFAULT 'user',
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE alumnos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    edad INT,
    correo VARCHAR(100),
    rol VARCHAR(50) DEFAULT 'Alumno',
    activo TINYINT(1) DEFAULT 1,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO usuarios_auth (username, email, password_hash, rol) VALUES 
('admin', 'admin@apiselene.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('selene', 'selene@apiselene.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');
```

8. Haz clic en "Ejecutar" o el botón de Play

### Paso 4: Verificar que Funciona

1. Abre el navegador
2. Ve a `http://localhost/APISelene/login.html`
3. Deberías ver un formulario de login
4. Intenta iniciar sesión con:
   - Usuario: `admin`
   - Contraseña: `password`
5. Si aparece un dashboard, funcionó correctamente

---

## Instrucciones Claras para Usuarios Finales

### Para Administrador

1. Abre `http://localhost/APISelene/login.html`
2. Inicia sesión con `admin` / `password`
3. En el dashboard verás:
   - Botón "Ver Alumnos" - muestra la lista de todos los alumnos
   - Botón "Crear Alumno" - abre un formulario para agregar estudiantes
   - Botón "Ver Eliminados" - muestra alumnos en papelera
   - Tabla con alumnos donde puedes eliminar cualquiera

### Para Estudiante/Usuario Regular

1. Abre `http://localhost/APISelene/login.html`
2. Inicia sesión con `selene` / `password`
3. En el dashboard verás:
   - Botón "Ver Alumnos" - muestra la lista
   - NO verás botón "Crear Alumno"
   - NO verás botón "Ver Eliminados"
   - NO puedes eliminar alumnos
4. Solo puedes consultar información (ver datos)

---

## Descripción del Diseño y Solución de Problemas

### Diseño de la Interfaz

El dashboard fue diseñado con Tailwind CSS para ser moderno y profesional.

**Nota sobre botones:** Si los botones se ven desactivados:
- El botón "Cerrar Sesión" es funcional (color gris es el diseño intencional)
- El botón "Eliminar" de cada alumno solo aparece para administradores
- Si eres usuario regular, no verás estos botones en absoluto

### Soft Delete vs Eliminación Permanente

El sistema tiene dos opciones:

1. **Eliminar** (Soft Delete) - El alumno va a papelera
   - El alumno NO se borra realmente
   - Se marca como inactivo con fecha de eliminación
   - El admin puede restaurarlo después
   - Los datos se conservan en la base de datos

2. **Eliminar Permanentemente** - Eliminación física real
   - Solo aparece en la vista de papelera
   - Aquí sí se borra completamente de la base de datos
   - No se puede recuperar
   - Se usa solo cuando se está seguro

El mensaje "Eliminar Permanentemente" solo aparece en la papelera y es cierto en ese contexto - allí sí se elimina de verdad.

---

## Sistema de Autenticación y Seguridad

### Autenticación por Sesiones

La API utiliza sesiones PHP seguras. Al hacer login, se almacenan datos en `$_SESSION`:

```php
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['rol'] = $user['rol'];
$_SESSION['logged_in'] = true;
```

### Control de Roles

| Rol | Permisos |
|-----|----------|
| Administrador | Acceso completo (GET, POST, PATCH, DELETE) |
| Usuario/Estudiante | Solo lectura (GET) |

### Flujo de Autenticación

1. Usuario ingresa credenciales en login.html
2. Se envía POST a `/api/auth/login`
3. Se valida contra `usuarios_auth`
4. Si es válido, se inicia sesión y se redirige a dashboard.html
5. Cada petición a la API pasa por `AuthMiddleware` que verifica `$_SESSION['logged_in']`

### Protección contra Vulnerabilidades

**Inyección SQL:** Uso exclusivo de prepared statements con PDO

**Validación Server-Side:** Validación de tipos y formatos con expresiones regulares

**Sanitización:** HTML encoding para prevenir XSS, limpieza de caracteres especiales

---

## Operaciones CRUD con Soft Delete

### Endpoints Disponibles

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/auth/login` | Iniciar sesión |
| POST | `/api/auth/logout` | Cerrar sesión |
| GET | `/api/alumnos` | Listar alumnos activos |
| POST | `/api/alumnos` | Crear alumno (solo admin) |
| PATCH | `/api/alumnos` | Actualizar alumno (solo admin) |
| DELETE | `/api/alumnos` | Soft delete alumno (solo admin) |
| GET | `/api/alumnos/deleted` | Ver alumnos eliminados (solo admin) |
| POST | `/api/alumnos/restore` | Restaurar alumno (solo admin) |
| DELETE | `/api/alumnos/force-delete` | Eliminar permanentemente (solo admin) |

### Implementación de Soft Delete

El soft delete marca registros como eliminados sin borrarlos:

```php
UPDATE alumnos SET activo = 0, deleted_at = CURRENT_TIMESTAMP WHERE id = 1;
```

Para restaurar:

```php
UPDATE alumnos SET activo = 1, deleted_at = NULL WHERE id = 1;
```

Para eliminar definitivamente:

```php
DELETE FROM alumnos WHERE id = 1;
```

**Ventajas:**
- Recuperación de datos accidental
- Auditoría y cumplimiento normativo
- Integridad referencial preservada
- Historial de cambios disponible

---

## Validación y Sanitización

### Validadores Implementados

```php
Validator::validateNombre($nombre)        // Solo letras y espacios
Validator::validateEmail($email)          // Formato email válido
Validator::validateEdad($edad)            // Número entre 1-120
Validator::validateId($id)                // Número positivo
Validator::validateAlumnoData($data)      // Validación completa
```

### Sanitizadores Implementados

```php
Sanitizer::sanitizeString($input)         // HTML encode y trim
Sanitizer::sanitizeEmail($email)          // Formato email limpio
Sanitizer::sanitizeInt($input)            // Número entero
Sanitizer::sanitizeAlumnoData($data)      // Sanitización por reglas
```

---

## Logs de Actividad y Errores

### Sistema de Logging

El logger implementado en `config/logger.php` incluye:

**Niveles de Log:**
- DEBUG - Información detallada para desarrollo
- INFO - Eventos normales del sistema
- WARN - Advertencias y situaciones inusuales
- ERROR - Errores recuperables
- FATAL - Errores críticos

**Características:**
- Rotación automática de logs (comprime a gzip cada 5000 líneas)
- Archivo principal: `logs/server.log`
- Archivos archivados: `logs/archive/`
- Información completa: timestamp, IP, método HTTP, contexto

---

## Demostración del Funcionamiento

### Flujo Completo CRUD

**Paso 1: Login**
```
POST /api/auth/login
{
    "username": "admin",
    "password": "password"
}

Respuesta:
{
    "success": true,
    "message": "Login exitoso",
    "user": {
        "id": 1,
        "username": "admin",
        "rol": "admin"
    }
}
```

**Paso 2: Crear Alumno**
```
POST /api/alumnos
{
    "nombre": "Juan Pérez",
    "edad": 20,
    "correo": "juan@ejemplo.com",
    "rol": "Alumno"
}

Respuesta:
{
    "success": true,
    "id": 5
}
```

**Paso 3: Listar Alumnos**
```
GET /api/alumnos

Respuesta:
[
    {
        "id": 5,
        "nombre": "Juan Pérez",
        "edad": 20,
        "correo": "juan@ejemplo.com",
        "rol": "Alumno"
    }
]
```

**Paso 4: Actualizar Alumno**
```
PATCH /api/alumnos
{
    "id": 5,
    "edad": 21
}

Respuesta:
{
    "success": true,
    "affected_rows": 1
}
```

**Paso 5: Soft Delete (Alumno a Papelera)**
```
DELETE /api/alumnos
{
    "id": 5
}

Respuesta:
{
    "success": true,
    "message": "Alumno eliminado correctamente (soft delete)"
}
```

**Paso 6: Ver Papelera**
```
GET /api/alumnos/deleted

Respuesta:
[
    {
        "id": 5,
        "nombre": "Juan Pérez",
        "deleted_at": "2024-01-15 14:35:20"
    }
]
```

**Paso 7: Restaurar desde Papelera**
```
POST /api/alumnos/restore
{
    "id": 5
}

Respuesta:
{
    "success": true,
    "message": "Alumno restaurado correctamente"
}
```

---

## Video de Funcionamiento

### Demostración Completa de la API

![Video README](./VideoReadme.mp4)


https://github.com/user-attachments/assets/1d226c8c-1bd7-4c19-ad65-6a50740e90e2


El video demuestra:
- Autenticación exitosa
- Control de roles funcionando
- CRUD completo (Create - Read - Update - Delete)
- Soft delete en acción
- Logs registrando cada operación

---

## Capturas del Funcionamiento

### 1. Base de Datos - Tabla alumnos

![Base de Datos Alumnos](./screenshots/01_db_alumnos.png)

Estructura de la tabla alumnos con campos: id, nombre, edad, correo, rol, activo, deleted_at, created_at, updated_at. Muestra la implementación del soft delete.

---

### 2. Base de Datos - Tabla usuarios_auth

![Base de Datos Usuarios](./screenshots/02_db_usuarios_auth.png)

Estructura de la tabla usuarios_auth con campos: id, username, email, password_hash, rol (ENUM), activo. Evidencia la estructura de autenticación y control de roles.

---

### 3. Interfaz de Login

![Login Interface](./screenshots/03_login_interface.png)

Formulario responsivo de login con credenciales de prueba (admin/password y selene/password).

---

### 4. Dashboard - Usuario Normal (Acceso Limitado)

![Dashboard Usuario Normal](./screenshots/04_dashboard_usuario_normal.png)

Dashboard del usuario regular logueado como "selene". Sin acceso a: crear alumno, ver eliminados, ni sección de gestión de alumnos. Solo puede hacer consultas (GET).

---

### 5. Dashboard - Admin (Acceso Completo)

![Dashboard Admin Completo](./screenshots/05_dashboard_admin_completo.png)

Dashboard del administrador logueado como "admin". Muestra acceso completo con todos los botones y secciones habilitadas.

---

### 6. Admin - Formulario de Crear Alumno

![Crear Alumno Form](./screenshots/06_admin_crear_alumno_form.png)

Formulario desplegable para crear nuevo alumno con campos: Nombre, Edad, Correo. Funcionalidad de CREATE (POST).

---

### 7. Admin - Lista de Alumnos

![Lista Alumnos](./screenshots/07_admin_lista_alumnos.png)

Tabla con alumnos listados mostrando: Nombre, Edad, Correo, Rol y botón "Eliminar". Funcionalidad de READ (GET) con datos reales.

---

### 8. Admin - Confirmación de Soft Delete

![Soft Delete Confirmation](./screenshots/08_admin_soft_delete_confirmation.png)

Diálogo de confirmación antes de eliminar: "¿Estás seguro de que quieres eliminar este alumno?". Confirmación de acción destructiva.

---

### 9. Admin - Papelera (Alumnos Eliminados)

![Papelera Eliminados](./screenshots/09_admin_papelera_eliminados.png)

Vista de papelera mostrando alumnos eliminados con: Nombre, Edad, Fecha de eliminación, botones "Restaurar" y "Eliminar permanentemente". Soft delete y papelera en funcionamiento.

---

### 10. Admin - Restaurar Alumno desde Papelera

![Restaurar Alumno](./screenshots/10_admin_papelera_eliminados.png)

Pantallazo de papelera donde se pueden ver botones "Restaurar" para recuperar alumnos eliminados. Funcionalidad de restauración.

---

### 11. Consola de Logs - Peticiones JSON

![Consola Logs JSON](./screenshots/11_consola_logs_json.png)

Sección "Consola de Salida" mostrando peticiones GET, POST, DELETE con timestamps y respuestas JSON formateadas.

---

## Reflexión y Conclusiones del Proyecto

### Aprendizajes Principales

Este proyecto permitió implementar conceptos empresariales reales en una API REST:

1. **Arquitectura Modular MVC** - Separación clara de responsabilidades hace el código mantenible y escalable
2. **Seguridad en Capas** - Autenticación + Autorización + Validación + Sanitización = protección integral
3. **Soft Delete** - Concepto crítico en sistemas reales donde NO se puede perder datos
4. **Logging Profundo** - Auditoría completa facilita debugging y cumplimiento normativo
5. **Control de Roles** - Admin vs User no es cosmético, es seguridad real

### Desafíos Enfrentados

| Desafío | Solución |
|---------|----------|
| Inyección SQL | Prepared statements con PDO |
| Validación insuficiente | Regex Unicode para nombres acentuados + sanitización |
| Sin control de acceso | Middleware de roles en cada ruta sensible |
| Datos perdidos al eliminar | Soft delete con timestamp - recuperación garantizada |
| Logs no controlados | Rotación automática con gzip |
| XSS potencial | HTML encoding en todas las entradas y salidas |

### Por Qué Soft Delete

**Ventajas Implementadas:**

- Recuperación Accidental - El admin puede restaurar datos borrados por error
- Auditoría Completa - Campo deleted_at registra cuándo se eliminó
- Cumplimiento Legal - Muchas leyes requieren historial de cambios
- Integridad Referencial - Las relaciones en BD no se rompen
- Reversibilidad - Cambio lógico, no físico - fácil de deshacer

### Mejoras Futuras

1. API Gateway - Kong o similar para rate limiting
2. JWT Tokens - Reemplazar sesiones por tokens para API mobile
3. Paginación - Limitar resultados de listas grandes
4. Filtros Avanzados - GET /alumnos?edad=20&rol=Alumno
5. Notificaciones - Email cuando se crea/elimina alumno
6. Versionado - Historial completo de cambios
7. Caché - Redis para mejorar rendimiento
8. Tests Automáticos - PHPUnit para validar lógica crítica
9. API Documentation - Swagger/OpenAPI
10. Soft Delete Automático - Archivado después de X días

### Puntos Fuertes del Proyecto

1. Estructura Profesional - Se parece a un proyecto real de empresa
2. Seguridad Robusta - Va en profundidad, no es superficial
3. UX Intuitiva - Dashboard claro con feedback visual inmediato
4. Escalabilidad - Fácil agregar nuevas tablas/funciones
5. Mantenibilidad - Código limpio y comentado
6. Soft Delete Completo - No solo elimina, restaura y limpia papelera
7. Logging Empresarial - Rotación automática y múltiples niveles

### Conclusión Final

APISelene es una demostración práctica de que la seguridad y la arquitectura son fundamentales en desarrollo web profesional. Cada decisión (prepared statements, soft delete, logging) tiene justificación empresarial real.

El proyecto muestra que se pueden implementar características de nivel profesional siguiendo principios SOLID, patrones de diseño y estándares de seguridad reconocidos internacionalmente.

**Resultado:** API REST funcional con autenticación, autorización, auditoría y recuperación de datos.

---

## Requisitos del Proyecto

| Requisito | Estado |
|-----------|--------|
| Try/Catch implementado en controladores | Completado |
| Monitoreo de peticiones (server.log) | Completado |
| Control de roles con 403 Forbidden | Completado |
| Endpoint /stats con métricas | Completado |
| Documentación README ampliada | Completado |
| Video funcionamiento API | Completado |
| Rate limiting intentos login | Completado |
| Código comentado | Completado |

---

## Archivos Clave del Proyecto

### Backend (API)
- `api/config/db.php` - Conexión PDO con try/catch
- `api/config/logger.php` - Sistema de logging con rotación
- `api/controllers/AuthController.php` - Autenticación con excepciones
- `api/controllers/AlumnosController.php` - CRUD con manejo de errores
- `api/controllers/StatsController.php` - Métricas del servidor
- `api/middleware/AuthMiddleware.php` - Verificación de sesiones
- `api/middleware/RoleMiddleware.php` - Control de roles (403 Forbidden)
- `api/middleware/LoginAttemptMiddleware.php` - Rate limiting
- `api/models/Alumnos.php` - Modelo con soft delete y try/catch
- `api/models/User.php` - Modelo de usuarios
- `api/routes.php` - Enrutamiento protegido
- `api/utils/Validator.php` - Validación de datos
- `api/utils/Sanitizer.php` - Sanitización de entradas
- `api/traits/SoftDelete.php` - Trait reutilizable

### Frontend
- `login.html` - Interfaz de login
- `dashboard.html` - Dashboard con control de roles

### Logging
- `logs/server.log` - Archivo de logs
- `logs/archive/` - Logs comprimidos

### Multimedia
- `screenshots/` - Capturas de pantalla
- `VideoReadme.mp4` - Video de demostración

---

## Acceso al Proyecto

**Local:** http://localhost/APISelene/

**Credenciales de Prueba:**
- Admin: admin / password
- Usuario: selene / password

---

## Estructura de Carpetas Completa

```
APISelene/
├── api/
│   ├── config/
│   │   ├── db.php
│   │   └── logger.php
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── AlumnosController.php
│   │   └── StatsController.php
│   ├── middleware/
│   │   ├── AuthMiddleware.php
│   │   ├── RoleMiddleware.php
│   │   └── LoginAttemptMiddleware.php
│   ├── models/
│   │   ├── Alumnos.php
│   │   └── User.php
│   ├── utils/
│   │   ├── Validator.php
│   │   └── Sanitizer.php
│   ├── traits/
│   │   └── SoftDelete.php
│   ├── logs/
│   │   ├── server.log
│   │   └── archive/
│   ├── screenshots/
│   └── routes.php
├── login.html
├── dashboard.html
├── VideoReadme.mp4
├── README.md
└── .htaccess
```

---

## Tecnologías Utilizadas

- **Backend:** PHP 7.4+
- **Base de Datos:** MySQL 5.7+
- **Server:** Apache (XAMPP)
- **Frontend:** HTML, CSS (Tailwind), JavaScript
- **Patrones:** MVC, Middleware, Traits
- **Seguridad:** PDO, Prepared Statements, Sessions, RBAC
- **Logging:** Archivo con rotación automática
