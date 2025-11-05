# API REST Selene 🌙

Sistema de gestión de usuarios desarrollado con PHP, MySQL y una interfaz web moderna con diseño glassmorphism.

---

## 📋 Descripción

API REST completa para la gestión de usuarios con operaciones CRUD (Crear, Leer, Actualizar, Eliminar). Incluye validaciones robustas, sistema de logging para auditoría y una interfaz web interactiva.

---

## ✨ Características

- ✅ **CRUD completo** de usuarios (Create, Read, Update, Delete)
- 🔒 **Validación de datos** en servidor (nombres solo con letras y espacios, soporte para acentos y ñ)
- 📝 **Sistema de logging** completo para auditoría de operaciones
- 🎨 **Interfaz web moderna** con efectos glassmorphism
- 📱 **Diseño responsive** adaptable a todos los dispositivos
- 🔄 **Endpoints RESTful** bien estructurados
- ⚠️ **Manejo de errores** robusto con códigos HTTP apropiados
- 🌐 **Soporte de alias** (usuarios/alumnos)

---

## 🛠️ Tecnologías Utilizadas

- **Backend:** PHP 7.4+
- **Base de datos:** MySQL/MariaDB
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Estilos:** Tailwind CSS (CDN)
- **Arquitectura:** MVC (Modelo-Vista-Controlador)

---

## 📦 Estructura del Proyecto

```
rest-api-selene/
├── api/
│   ├── config/
│   │   ├── db.php                    # Configuración de base de datos
│   │   └── logger.php                # Sistema de logging
│   ├── controllers/
│   │   ├── UsuariosController.php    # Controlador de usuarios
│   │   └── StatsController.php       # Controlador de estadísticas
│   ├── models/
│   │   └── Usuarios.php              # Modelo de usuarios
│   └── routes.php                    # Enrutador principal
├── index.html                         # Interfaz web
├── script.js                          # Lógica del cliente
└── README.md                          # Este archivo
```

---

## 🚀 Instalación

### Requisitos Previos

- PHP 7.4 o superior
- MySQL 5.7+ o MariaDB 10.3+
- Servidor web (Apache/Nginx) o PHP CLI
- Extensiones PHP: PDO, pdo_mysql

### Pasos de Instalación

1. **Descargar el proyecto**
   - Descarga y extrae el archivo ZIP del proyecto en tu directorio de trabajo

2. **Crear la base de datos**
   ```sql
   CREATE DATABASE rest_api_selene CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE rest_api_selene;

   CREATE TABLE usuarios (
       id INT AUTO_INCREMENT PRIMARY KEY,
       nombre VARCHAR(100) NOT NULL,
       edad INT NOT NULL,
       rol VARCHAR(50) NOT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   );
   ```

3. **Configurar la conexión a la base de datos**
   
   Editar `api/config/db.php`:
   ```php
   private $host = "localhost";
   private $db_name = "rest_api_selene";
   private $username = "root";      // Tu usuario
   private $password = "";          // Tu contraseña
   ```

4. **Iniciar el servidor**

   **Opción A - PHP Built-in Server:**
   ```bash
   php -S localhost:8000
   ```

   **Opción B - Apache/Nginx:**
   - Colocar el proyecto en el directorio web (htdocs, www, etc.)
   - Acceder mediante: `http://localhost/rest-api-selene/`

5. **Abrir la aplicación**
   
   Navegar a: `http://localhost:8000/index.html`

---

## 📡 API Endpoints

### Base URL
```
http://localhost:8000/rest-api-selene
```

### Endpoints Disponibles

#### 1. Obtener todos los usuarios
```http
GET /api/usuarios
GET /api/alumnos  (alias)
```

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nombre": "Ana García",
      "edad": 25,
      "rol": "Desarrollador",
      "created_at": "2024-01-15 10:30:00",
      "updated_at": "2024-01-15 10:30:00"
    }
  ]
}
```

#### 2. Crear un usuario
```http
POST /api/usuarios
Content-Type: application/json
```

**Body:**
```json
{
  "nombre": "Ana García",
  "edad": 25,
  "rol": "Desarrollador"
}
```

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "message": "Usuario creado exitosamente",
  "id": 1
}
```

**Respuesta de error (400):**
```json
{
  "error": "Nombre invalido"
}
```

#### 3. Actualizar un usuario
```http
PATCH /api/usuarios
Content-Type: application/json
```

**Body:**
```json
{
  "id": 1,
  "nombre": "Ana María García",
  "edad": 26,
  "rol": "Senior Developer"
}
```

**Nota:** Todos los campos excepto `id` son opcionales.

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "message": "Usuario actualizado exitosamente"
}
```

#### 4. Eliminar un usuario
```http
DELETE /api/usuarios
Content-Type: application/json
```

**Body:**
```json
{
  "id": 1
}
```

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "message": "Usuario eliminado exitosamente"
}
```

#### 5. Registrar evento
```http
POST /api/logevent
Content-Type: application/json
```

**Body:**
```json
{
  "nombre": "Evento de prueba",
  "accion": "test"
}
```

#### 6. Obtener estadísticas
```http
GET /api/stats
```

---

## 🔐 Validaciones

### Validación de Nombres
- Solo se permiten **letras** (incluyendo acentos, ñ, ü, etc.) y **espacios**
- No se permiten números ni caracteres especiales
- El campo no puede estar vacío
- Expresión regular utilizada: `/^[\p{L}\s]+$/u`

### Ejemplos Válidos ✅
- "Ana García"
- "José María Pérez"
- "Ñoño Hernández"
- "María José"

### Ejemplos Inválidos ❌
- "Ana123" (contiene números)
- "Ana@García" (contiene caracteres especiales)
- "Ana_García" (contiene guión bajo)
- "" (vacío)

---

## 📊 Sistema de Logging

El sistema registra todas las operaciones en archivos de log ubicados en `api/logs/`:

```
api/logs/
├── app_2024-01-15.log
├── app_2024-01-16.log
└── ...
```

### Formato de Log
```
[2024-01-15 10:30:45] [INFO] GET /usuarios
[2024-01-15 10:31:12] [INFO] POST /usuarios payload: {"nombre":"Ana García","edad":25,"rol":"Desarrollador"}
[2024-01-15 10:31:12] [INFO] POST /usuarios result: {"success":true,"id":1}
[2024-01-15 10:32:00] [WARN] Intento de insercion invalida: Ana123
[2024-01-15 10:33:45] [ERROR] Error de conexión a BD: SQLSTATE[HY000] [1045] Access denied
```

### Niveles de Log
- **INFO:** Operaciones normales
- **WARN:** Advertencias (validaciones fallidas)
- **ERROR:** Errores críticos

---

## 🎨 Interfaz Web

La interfaz incluye:

### Paneles Principales
1. **📊 Panel de Control**
   - Botón para ver lista de usuarios
   - Consola de salida de requests
   - Área de resultados

2. **➕ Crear Usuario**
   - Formulario con validación
   - Campos: nombre, edad, rol
   - Botones de crear y limpiar

3. **✏️ Actualizar Usuario**
   - Búsqueda por ID
   - Campos opcionales para actualización
   - Validación en tiempo real

4. **🗑️ Eliminar Usuario**
   - Eliminación por ID
   - Confirmación visual

5. **📝 Registro de Eventos**
   - Log de eventos personalizados
   - Campos: nombre y acción

### Características de Diseño
- **Glassmorphism:** Efecto de cristal esmerilado
- **Gradientes vibrantes:** Colores morados y azules
- **Animaciones suaves:** Transiciones en hover
- **Responsive:** Adaptable a móviles y tablets

---

## 🧪 Ejemplos de Uso

### Ejemplo con cURL

**Crear un usuario:**
```bash
curl -X POST http://localhost:8100/api/usuarios \
  -H "Content-Type: application/json" \
  -d '{"nombre":"Ana García","edad":25,"rol":"Desarrollador"}'
```

**Obtener todos los usuarios:**
```bash
curl http://localhost:8000/api/usuarios
```

**Actualizar un usuario:**
```bash
curl -X PATCH http://localhost:8000/api/usuarios \
  -H "Content-Type: application/json" \
  -d '{"id":1,"edad":26}'
```

**Eliminar un usuario:**
```bash
curl -X DELETE http://localhost:8000/api/usuarios \
  -H "Content-Type: application/json" \
  -d '{"id":1}'
```

### Ejemplo con JavaScript (Fetch API)

```javascript
// Crear usuario
async function crearUsuario() {
  const response = await fetch('http://localhost:8000/api/usuarios', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      nombre: 'Ana García',
      edad: 25,
      rol: 'Desarrollador'
    })
  });
  
  const data = await response.json();
  console.log(data);
}

// Obtener usuarios
async function obtenerUsuarios() {
  const response = await fetch('http://localhost:8000/api/usuarios');
  const data = await response.json();
  console.log(data);
}
```

---

## ⚠️ Manejo de Errores

### Códigos HTTP Utilizados

| Código | Significado | Uso |
|--------|-------------|-----|
| 200 | OK | Operación exitosa |
| 400 | Bad Request | Datos inválidos o faltantes |
| 404 | Not Found | Ruta no encontrada |
| 500 | Internal Server Error | Error en el servidor o BD |

### Formato de Respuestas de Error

```json
{
  "success": false,
  "error": "Descripción del error",
  "message": "Mensaje adicional"
}
```

---

## 🔧 Configuración Adicional

### Configurar CORS (si es necesario)

Agregar en `api/routes.php`:

```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
```

### Habilitar/Deshabilitar Logging

Editar `api/config/logger.php` y modificar el nivel de logging según necesidad.

---

## 🐛 Solución de Problemas

### La conexión a la base de datos falla
- ✅ Verificar que MySQL esté corriendo
- ✅ Confirmar credenciales en `api/config/db.php`
- ✅ Verificar que la base de datos `rest_api_selene` existe
- ✅ Revisar logs en `api/logs/`

### Error 404 en todas las rutas
- ✅ Verificar que el servidor esté corriendo
- ✅ Confirmar la ruta base correcta
- ✅ Revisar configuración de Apache/Nginx si aplica

### Los datos no se validan correctamente
- ✅ Verificar que el charset de la BD sea `utf8mb4`
- ✅ Confirmar que PHP tenga soporte para Unicode
- ✅ Revisar logs de validación

---

## 📝 Notas Importantes

1. **Seguridad:** Este es un proyecto educativo. Para producción:
   - Implementar autenticación (JWT, OAuth)
   - Sanitizar todas las entradas
   - Usar HTTPS
   - Implementar rate limiting
   - Hash de contraseñas si se agregan

2. **Performance:** Para grandes volúmenes de datos:
   - Implementar paginación
   - Agregar índices en la BD
   - Considerar caché (Redis, Memcached)

3. **Mantenimiento:**
   - Los logs pueden crecer significativamente
   - Implementar rotación de logs
   - Hacer backups regulares de la BD
