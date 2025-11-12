<?php
// api/middleware/RateLimitDB.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/logger.php';

class RateLimitDB {
    private $db;
    private $table = 'rate_limits';
    
    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->createTable();
    }
    
    private function createTable() {
        $query = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            attempts INT DEFAULT 0,
            first_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            blocked_until TIMESTAMP NULL,
            KEY ip_index (ip),
            KEY blocked_index (blocked_until)
        )";
        $this->db->exec($query);
    }
    
    public function checkRateLimit($maxAttempts = 3, $windowMinutes = 1, $blockMinutes = 5) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Limpiar registros antiguos
        $this->cleanOldRecords($windowMinutes);
        
        // Verificar si está bloqueado
        $stmt = $this->db->prepare("SELECT blocked_until FROM {$this->table} WHERE ip = ? AND blocked_until > NOW()");
        $stmt->execute([$ip]);
        $blocked = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($blocked) {
            http_response_code(429);
            echo json_encode([
                "error" => "IP bloqueada temporalmente",
                "message" => "Demasiados intentos. Intenta nuevamente en 5 minutos."
            ]);
            return false;
        }
        
        // Obtener o crear registro
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE ip = ?");
        $stmt->execute([$ip]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) {
            // Primer intento
            $stmt = $this->db->prepare("INSERT INTO {$this->table} (ip, attempts) VALUES (?, 1)");
            $stmt->execute([$ip]);
            return true;
        }
        
        // Verificar si la ventana de tiempo expiró
        $firstAttempt = strtotime($record['first_attempt']);
        $windowSeconds = $windowMinutes * 60;
        
        if ((time() - $firstAttempt) > $windowSeconds) {
            // Reiniciar contador
            $stmt = $this->db->prepare("UPDATE {$this->table} SET attempts = 1, first_attempt = NOW() WHERE ip = ?");
            $stmt->execute([$ip]);
            return true;
        }
        
        // Incrementar intentos
        $newAttempts = $record['attempts'] + 1;
        $stmt = $this->db->prepare("UPDATE {$this->table} SET attempts = ? WHERE ip = ?");
        $stmt->execute([$newAttempts, $ip]);
        
        // Verificar si excede el límite
        if ($newAttempts >= $maxAttempts) {
            // Bloquear IP
            $blockUntil = date('Y-m-d H:i:s', time() + ($blockMinutes * 60));
            $stmt = $this->db->prepare("UPDATE {$this->table} SET blocked_until = ? WHERE ip = ?");
            $stmt->execute([$blockUntil, $ip]);
            
            http_response_code(429);
            echo json_encode([
                "error" => "Demasiados intentos",
                "message" => "IP bloqueada por 5 minutos. Límite: 3 intentos en 1 minuto."
            ]);
            return false;
        }
        
        return true;
    }
    
    public function recordSuccess($ip) {
        // Limpiar intentos en éxito
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE ip = ?");
        $stmt->execute([$ip]);
    }
    
    private function cleanOldRecords($windowMinutes) {
        $cutoff = date('Y-m-d H:i:s', time() - ($windowMinutes * 60 * 2));
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE last_attempt < ? AND blocked_until IS NULL");
        $stmt->execute([$cutoff]);
    }
}
?>