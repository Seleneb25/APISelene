<?php
// api/models/User.php (modificado desde tu Usuarios.php)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/logger.php';

class User {
    private $db;
    private $table_name = "usuarios_auth"; // Cambiamos a la nueva tabla

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    /**
     * Buscar usuario por username para login
     */
    public function findByUsername($username) {
        try {
            $stmt = $this->db->prepare("SELECT id, username, email, password_hash, rol, activo 
                                      FROM " . $this->table_name . " 
                                      WHERE username = :username AND activo = 1 
                                      LIMIT 1");
            $stmt->execute([':username' => $username]);
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return false;
        } catch (PDOException $e) {
            Logger::error("Error en findByUsername: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Buscar usuario por ID
     */
    public function findById($id) {
        try {
            $stmt = $this->db->prepare("SELECT id, username, email, rol, activo, created_at 
                                      FROM " . $this->table_name . " 
                                      WHERE id = :id AND activo = 1 
                                      LIMIT 1");
            $stmt->execute([':id' => $id]);
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return false;
        } catch (PDOException $e) {
            Logger::error("Error en findById: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si email existe
     */
    public function emailExists($email) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM " . $this->table_name . " WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            Logger::error("Error en emailExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crear nuevo usuario (para registro)
     */
    public function create($data) {
        try {
            $nombre = isset($data['nombre']) ? $data['nombre'] : null;
            $rol = isset($data['rol']) ? $data['rol'] : 'user';
            $edad = isset($data['edad']) ? $data['edad'] : null;
            
            Logger::info("Intentando crear usuario: " . json_encode(['nombre' => $nombre, 'rol' => $rol, 'edad' => $edad]));

            // Para la nueva estructura, necesitamos username, email y password
            $username = isset($data['username']) ? $data['username'] : (isset($data['nombre']) ? strtolower(str_replace(' ', '', $data['nombre'])) : null);
            $email = isset($data['email']) ? $data['email'] : null;
            $password_hash = isset($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : password_hash('password', PASSWORD_DEFAULT);

            $stmt = $this->db->prepare("INSERT INTO " . $this->table_name . " 
                                      (username, email, password_hash, rol) 
                                      VALUES (:username, :email, :password_hash, :rol)");
            
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => $password_hash,
                ':rol' => $rol
            ]);
            
            $id = $this->db->lastInsertId();
            Logger::info("Usuario auth creado correctamente con ID: " . $id);
            return ["success" => true, "id" => $id];
            
        } catch (PDOException $e) {
            $error = $e->getMessage();
            Logger::error("Error al crear usuario auth - SQL Error: " . $error);
            Logger::error("Datos intentados: " . json_encode($data));
            return [
                "success" => false, 
                "error" => "Error al crear usuario en la base de datos",
                "debug" => $error
            ];
        }
    }

    /**
     * Obtener todos los usuarios (solo para admin)
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("SELECT id, username, email, rol, activo, created_at FROM " . $this->table_name . " WHERE activo = 1");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error("Error en getAll usuarios: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Actualizar usuario
     */
    public function update($data) {
        if (!isset($data['id'])) {
            return ["success" => false, "error" => "Falta el campo 'id' para actualizar"];
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE " . $this->table_name . " 
                                      SET username = :username, email = :email, rol = :rol 
                                      WHERE id = :id");
            $stmt->execute([
                ':username' => isset($data['username']) ? $data['username'] : null,
                ':email' => isset($data['email']) ? $data['email'] : null,
                ':rol' => isset($data['rol']) ? $data['rol'] : null,
                ':id' => $data['id']
            ]);
            return ["success" => true];
        } catch (PDOException $e) {
            Logger::error('Error en User::update - ' . $e->getMessage());
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    /**
     * Eliminar usuario (soft delete)
     */
    public function delete($id) {
        if (empty($id)) {
            return ["success" => false, "error" => "Falta el 'id' para eliminar"];
        }
        try {
            $stmt = $this->db->prepare("UPDATE " . $this->table_name . " SET activo = 0 WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return ["success" => true];
        } catch (PDOException $e) {
            Logger::error('Error en User::delete - ' . $e->getMessage());
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}
?>