<?php
// api/models/Alumnos.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../traits/SoftDelete.php'; // NUEVO: Trait soft delete

class Alumnos {
    private $db;
    private $table_name = "alumnos"; // Cambiado a tabla alumnos
    use SoftDelete; // NUEVO: Usar trait SoftDelete

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    /**
     * Obtener todos los alumnos activos (excluye eliminados)
     */
    public function getAllActive() {
        try {
            $stmt = $this->db->query("SELECT id, nombre, edad, correo, rol, created_at 
                                    FROM " . $this->table_name . " 
                                    WHERE activo = 1 
                                    ORDER BY id DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error("Error en Alumnos::getAllActive - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener todos los alumnos (alias para compatibilidad)
     */
    public function getAll() {
        return $this->getAllActive();
    }

    /**
     * Crear nuevo alumno
     */

    // ESTE ARCHIVO YA TIENE TRY-CATCH IMPLEMENTADO
    public function create($data) {
        try {
            $nombre = isset($data['nombre']) ? $data['nombre'] : null;
            $rol = isset($data['rol']) ? $data['rol'] : 'Alumno';
            $edad = isset($data['edad']) ? $data['edad'] : null;
            $correo = isset($data['correo']) ? $data['correo'] : null;
            
            Logger::info("Intentando insertar alumno: " . json_encode([
                'nombre' => $nombre, 
                'rol' => $rol, 
                'edad' => $edad,
                'correo' => $correo
            ]));

            $stmt = $this->db->prepare("INSERT INTO " . $this->table_name . " 
                                      (nombre, rol, edad, correo, activo) 
                                      VALUES (:nombre, :rol, :edad, :correo, 1)");
            
            $stmt->execute([
                ':nombre' => $nombre,
                ':rol' => $rol,
                ':edad' => $edad,
                ':correo' => $correo
            ]);
            
            $id = $this->db->lastInsertId();
            Logger::info("Alumno insertado correctamente con ID: " . $id);
            return ["success" => true, "id" => $id];
            
        } catch (PDOException $e) {
            $error = $e->getMessage();
            Logger::error("Error al crear alumno - SQL Error: " . $error);
            Logger::error("Datos intentados: " . json_encode($data));
            return [
                "success" => false, 
                "error" => "Error al crear alumno en la base de datos",
                "debug" => $error
            ];
        }
    }

    /**
     * Actualizar alumno
     */
    public function update($data) {
        if (!isset($data['id'])) {
            return ["success" => false, "error" => "Falta el campo 'id' para actualizar"];
        }
        
        try {
            // Construir query dinámicamente basado en los campos proporcionados
            $updates = [];
            $params = [':id' => $data['id']];
            
            if (isset($data['nombre'])) {
                $updates[] = "nombre = :nombre";
                $params[':nombre'] = $data['nombre'];
            }
            if (isset($data['edad'])) {
                $updates[] = "edad = :edad";
                $params[':edad'] = $data['edad'];
            }
            if (isset($data['correo'])) {
                $updates[] = "correo = :correo";
                $params[':correo'] = $data['correo'];
            }
            if (isset($data['rol'])) {
                $updates[] = "rol = :rol";
                $params[':rol'] = $data['rol'];
            }
            
            if (empty($updates)) {
                return ["success" => false, "error" => "No hay campos para actualizar"];
            }
            
            $updates[] = "updated_at = CURRENT_TIMESTAMP";
            
            $query = "UPDATE " . $this->table_name . " 
                     SET " . implode(', ', $updates) . " 
                     WHERE id = :id AND activo = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return ["success" => true, "affected_rows" => $stmt->rowCount()];
            
        } catch (PDOException $e) {
            Logger::error('Error en Alumnos::update - ' . $e->getMessage());
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    /**
     * Soft Delete - Marcar alumno como eliminado
     */
    public function softDelete($id) {
        try {
            $stmt = $this->db->prepare("UPDATE " . $this->table_name . " 
                                      SET activo = 0, deleted_at = CURRENT_TIMESTAMP 
                                      WHERE id = :id AND activo = 1");
            $stmt->execute([':id' => $id]);
            
            $success = $stmt->rowCount() > 0;
            
            if ($success) {
                Logger::info("Alumno ID $id marcado como eliminado (soft delete)");
            } else {
                Logger::warn("Intento de soft delete fallido para alumno ID $id");
            }
            
            return $success;
            
        } catch (PDOException $e) {
            Logger::error('Error en Alumnos::softDelete - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Restaurar alumno eliminado
     */
    public function restore($id) {
        try {
            $stmt = $this->db->prepare("UPDATE " . $this->table_name . " 
                                      SET activo = 1, deleted_at = NULL 
                                      WHERE id = :id AND activo = 0");
            $stmt->execute([':id' => $id]);
            
            $success = $stmt->rowCount() > 0;
            
            if ($success) {
                Logger::info("Alumno ID $id restaurado correctamente");
            }
            
            return $success;
            
        } catch (PDOException $e) {
            Logger::error('Error en Alumnos::restore - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminación permanente
     */
    public function forceDelete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM " . $this->table_name . " WHERE id = :id");
            $stmt->execute([':id' => $id]);
            
            $success = $stmt->rowCount() > 0;
            
            if ($success) {
                Logger::info("Alumno ID $id eliminado permanentemente");
            }
            
            return $success;
            
        } catch (PDOException $e) {
            Logger::error('Error en Alumnos::forceDelete - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener alumnos eliminados
     */
    public function getDeleted() {
        try {
            $stmt = $this->db->query("SELECT id, nombre, edad, correo, rol, deleted_at 
                                    FROM " . $this->table_name . " 
                                    WHERE activo = 0 
                                    ORDER BY deleted_at DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error("Error en Alumnos::getDeleted - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar alumno por ID
     */
    public function findById($id) {
        try {
            $stmt = $this->db->prepare("SELECT id, nombre, edad, correo, rol, created_at 
                                      FROM " . $this->table_name . " 
                                      WHERE id = :id AND activo = 1 
                                      LIMIT 1");
            $stmt->execute([':id' => $id]);
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return null;
            
        } catch (PDOException $e) {
            Logger::error("Error en Alumnos::findById - " . $e->getMessage());
            return null;
        }
    }
}
?>