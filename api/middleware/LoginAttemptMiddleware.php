<?php
// api/middleware/LoginAttemptMiddleware.php
require_once __DIR__ . '/RateLimitDB.php';
require_once __DIR__ . '/../config/logger.php';

class LoginAttemptMiddleware {
    private static $rateLimit;
    
    public static function checkLoginAttempts() {
        if (!self::$rateLimit) {
            self::$rateLimit = new RateLimitDB();
        }
        return self::$rateLimit->checkRateLimit(3, 1, 5); // 3 intentos en 1 min, bloqueo 5 min
    }
    
    public static function recordLoginAttempt($username, $success) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        if (!self::$rateLimit) {
            self::$rateLimit = new RateLimitDB();
        }
        
        if ($success) {
            self::$rateLimit->recordSuccess($ip);
            Logger::info("Login exitoso", ["ip" => $ip, "username" => $username]);
        } else {
            Logger::security("Intento de login fallido", [
                "ip" => $ip, 
                "username" => $username,
                "timestamp" => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    // NUEVO MÉTODO: Obtener información de bloqueo
    public static function getBlockedTime() {
        if (!self::$rateLimit) {
            self::$rateLimit = new RateLimitDB();
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $secondsLeft = self::$rateLimit->getBlockedTime($ip);
        
        return [
            'blocked' => $secondsLeft > 0,
            'seconds_left' => $secondsLeft,
            'minutes_left' => ceil($secondsLeft / 60),
            'ip' => $ip
        ];
    }
}
?>