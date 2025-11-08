<?php
// api/traits/SoftDelete.php

trait SoftDelete {
    
    /**
     * Realizar soft delete (marcar como eliminado)
     */
    public function softDelete($id) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET activo = 0, deleted_at = CURRENT_TIMESTAMP 
                     WHERE id = :id AND activo = 1";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("Error en softDelete: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Restaurar registro eliminado
     */
    public function restore($id) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET activo = 1, deleted_at = NULL 
                     WHERE id = :id AND activo = 0";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("Error en restore: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener todos los registros activos (excluye eliminados)
     */
    public function getAllActive() {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE activo = 1 
                     ORDER BY id DESC";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error en getAllActive: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener registros eliminados
     */
    public function getDeleted() {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE activo = 0 
                     ORDER BY deleted_at DESC";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error en getDeleted: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Eliminación permanente (hard delete)
     */
    public function forceDelete($id) {
        try {
            $query = "DELETE FROM " . $this->table_name . " 
                     WHERE id = :id";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("Error en forceDelete: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Buscar por ID incluyendo eliminados
     */
    public function findWithTrashed($id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE id = :id";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            return null;
            
        } catch (PDOException $e) {
            error_log("Error en findWithTrashed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Vaciar papelera (eliminar permanentemente todos los registros borrados)
     */
    public function emptyTrash() {
        try {
            $query = "DELETE FROM " . $this->table_name . " 
                     WHERE activo = 0";
                     
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            error_log("Error en emptyTrash: " . $e->getMessage());
            return 0;
        }
    }
}
?>