<?php
// api/controllers/AuthController.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $userModel;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->userModel = new User($db);
    }

    /**
     * Iniciar sesión de usuario
     */
    public function login() {
        // Verificar que sea método POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(["error" => "Método no permitido"]);
            return;
        }

        // Obtener y validar datos del request
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($input['username']) || !isset($input['password'])) {
            http_response_code(400);
            echo json_encode(["error" => "Username y password requeridos"]);
            return;
        }

        $username = trim($input['username']);
        $password = $input['password'];

        // Validaciones básicas
        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(["error" => "Username y password no pueden estar vacíos"]);
            return;
        }

        try {
            // Buscar usuario en la base de datos
            $user = $this->userModel->findByUsername($username);
            
            if (!$user) {
                Logger::warn("Intento de login fallido - Usuario no encontrado: $username");
                http_response_code(401);
                echo json_encode(["error" => "Credenciales inválidas"]);
                return;
            }

            // Verificar contraseña
            if (!password_verify($password, $user['password_hash'])) {
                Logger::warn("Intento de login fallido - Password incorrecto para: $username");
                http_response_code(401);
                echo json_encode(["error" => "Credenciales inválidas"]);
                return;
            }

            // Verificar si el usuario está activo
            if (!$user['activo']) {
                Logger::warn("Intento de login - Usuario inactivo: $username");
                http_response_code(403);
                echo json_encode(["error" => "Usuario desactivado"]);
                return;
            }

            // Iniciar sesión
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['rol'] = $user['rol'];
            $_SESSION['logged_in'] = true;

            Logger::info("Login exitoso - Usuario: $username, Rol: {$user['rol']}");

            // Respuesta exitosa
            echo json_encode([
                "success" => true,
                "message" => "Login exitoso",
                "user" => [
                    "id" => $user['id'],
                    "username" => $user['username'],
                    "email" => $user['email'],
                    "rol" => $user['rol']
                ]
            ]);

        } catch (Exception $e) {
            Logger::error("Error en login: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["error" => "Error interno del servidor"]);
        }
    }

    /**
     * Cerrar sesión
     */
    public function logout() {
        session_start();
        
        // Registrar logout
        if (isset($_SESSION['username'])) {
            Logger::info("Logout - Usuario: {$_SESSION['username']}");
        }

        // Destruir sesión
        session_unset();
        session_destroy();
        session_write_close();

        echo json_encode([
            "success" => true,
            "message" => "Logout exitoso"
        ]);
    }

    /**
     * Verificar sesión activa
     */
    public function checkAuth() {
        session_start();
        
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            echo json_encode([
                "authenticated" => true,
                "user" => [
                    "id" => $_SESSION['user_id'],
                    "username" => $_SESSION['username'],
                    "rol" => $_SESSION['rol']
                ]
            ]);
        } else {
            echo json_encode([
                "authenticated" => false
            ]);
        }
    }

    /**
     * Obtener perfil del usuario autenticado
     */
    public function getProfile() {
        session_start();
        
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            http_response_code(401);
            echo json_encode(["error" => "No autenticado"]);
            return;
        }

        try {
            $user = $this->userModel->findById($_SESSION['user_id']);
            
            if (!$user) {
                http_response_code(404);
                echo json_encode(["error" => "Usuario no encontrado"]);
                return;
            }

            // No enviar el password hash por seguridad
            unset($user['password_hash']);

            echo json_encode([
                "success" => true,
                "user" => $user
            ]);

        } catch (Exception $e) {
            Logger::error("Error obteniendo perfil: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["error" => "Error interno del servidor"]);
        }
    }
}
?>