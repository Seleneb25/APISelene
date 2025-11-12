<?php
// api/middleware/LoginAttemptMiddleware.php
require_once __DIR__ . '/RateLimitMiddleware.php';
require_once __DIR__ . '/../config/logger.php';

class LoginAttemptMiddleware {
    /**
     * Verificar intentos de login antes de permitir acceso
     */
    public static function checkLoginAttempts() {
        return RateLimitMiddleware::checkRateLimit(3, 15); // 3 intentos en 15 min
    }
    
    /**
     * Registrar intento de login (éxito o fallo)
     */
    public static function recordLoginAttempt($username, $success) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        RateLimitMiddleware::recordAttempt($ip, $success);
        
        // Log del intento
        if (!$success) {
            Logger::security("Intento de login fallido", [
                "ip" => $ip,
                "username" => $username,
                "timestamp" => date('Y-m-d H:i:s')
            ]);
        } else {
            Logger::info("Login exitoso", [
                "ip" => $ip,
                "username" => $username
            ]);
        }
    }
    
    /**
     * Obtener estadísticas de intentos de login
     */
    public static function getLoginStats() {
        return RateLimitMiddleware::getStats();
    }
}
?>