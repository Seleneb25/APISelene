<?php
// api/middleware/RoleMiddleware.php

class RoleMiddleware {
    
    /**
     * Verificar si el usuario tiene el rol requerido
     */
    public static function checkRole($requiredRole) {
        // Primero verificar autenticación
        require_once __DIR__ . '/AuthMiddleware.php';
        $user = AuthMiddleware::getUser();
        
        if (!$user) {
            http_response_code(401);
            echo json_encode([
                "error" => "No autenticado",
                "message" => "Debes iniciar sesión para acceder a este recurso"
            ]);
            return false;
        }
        
        // Verificar rol
        if ($user['rol'] !== $requiredRole) {
            http_response_code(403);
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
    
    /**
     * Verificar si el usuario es administrador
     */
    public static function requireAdmin() {
        return self::checkRole('admin');
    }
    
    /**
     * Verificar si el usuario tiene al menos rol user
     */
    public static function requireUser() {
        return self::checkRole('user') || self::checkRole('admin');
    }
    
    /**
     * Manejar verificación de admin
     */
    public static function handleAdmin() {
        if (!self::requireAdmin()) {
            exit;
        }
    }
    
    /**
     * Manejar verificación de user
     */
    public static function handleUser() {
        if (!self::requireUser()) {
            exit;
        }
    }
}
?>