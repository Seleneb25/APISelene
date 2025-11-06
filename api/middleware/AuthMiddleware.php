<?php
// api/middleware/AuthMiddleware.php

class AuthMiddleware {
    
    /**
     * Verificar si el usuario está autenticado
     * Retorna true si está autenticado, false si no
     */
    public static function checkAuth() {
        session_start();
        
        // Verificar si hay una sesión activa
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            http_response_code(401);
            echo json_encode([
                "error" => "No autenticado",
                "message" => "Debes iniciar sesión para acceder a este recurso"
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtener información del usuario autenticado
     */
    public static function getUser() {
        session_start();
        
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'rol' => $_SESSION['rol']
            ];
        }
        
        return null;
    }
    
    /**
     * Verificar autenticación y continuar si es válida
     */
    public static function handle() {
        if (!self::checkAuth()) {
            exit; // Detener ejecución si no está autenticado
        }
    }
}
?>