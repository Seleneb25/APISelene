<?php
// api/middleware/RateLimitMiddleware.php

class RateLimitMiddleware {
    private static $attempts = [];
    private static $blockedIPs = [];
    
    /**
     * Verificar límite de intentos para una IP
     */
    public static function checkRateLimit($maxAttempts = 5, $windowMinutes = 15) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Limpiar intentos antiguos
        self::cleanOldAttempts($windowMinutes);
        
        // Verificar si IP está bloqueada
        if (isset(self::$blockedIPs[$ip]) && self::$blockedIPs[$ip] > time()) {
            http_response_code(429);
            echo json_encode([
                "error" => "Demasiados intentos",
                "message" => "IP temporalmente bloqueada"
            ]);
            return false;
        }
        
        // Contar intentos recientes
        $recentAttempts = array_filter(self::$attempts[$ip] ?? [], 
            function($time) use ($windowMinutes) {
                return $time > (time() - ($windowMinutes * 60));
            });
        
        if (count($recentAttempts) >= $maxAttempts) {
           // Bloquear por 5 minutos
             self::$blockedIPs[$ip] = time() + (5 * 60);
            http_response_code(429);
            echo json_encode([
                "error" => "Demasiados intentos",
                "message" => "Demasiados intentos fallidos. IP bloqueada por 5 minutos."
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Registrar intento de acceso
     */
    public static function recordAttempt($ip, $success = true) {
        if (!isset(self::$attempts[$ip])) {
            self::$attempts[$ip] = [];
        }
        
        self::$attempts[$ip][] = time();
        
        if ($success) {
            // Limpiar intentos en login exitoso
            self::$attempts[$ip] = [];
        }
    }
    
    /**
     * Limpiar intentos antiguos
     */
    private static function cleanOldAttempts($windowMinutes) {
        $cutoff = time() - ($windowMinutes * 60);
        foreach (self::$attempts as $ip => $attempts) {
            self::$attempts[$ip] = array_filter($attempts, 
                function($time) use ($cutoff) {
                    return $time > $cutoff;
                });
        }
    }
    
    /**
     * Obtener estadísticas de rate limiting
     */
    public static function getStats() {
        self::cleanOldAttempts(60); // Limpiar antes de mostrar stats
        return [
            'attempts_by_ip' => self::$attempts,
            'blocked_ips' => self::$blockedIPs
        ];
    }
}
?>