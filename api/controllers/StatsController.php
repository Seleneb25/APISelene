<?php
require_once __DIR__ . '/../config/logger.php';

class StatsController
{
    public static function handler()
    {
        try {
            $uptime = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2);
            $memory = round(memory_get_usage() / 1024 / 1024, 2);
            
            Logger::info('GET /stats - uptime: ' . $uptime . 's, memory: ' . $memory . 'MB');
            
            echo json_encode([
                "uptime_seconds" => $uptime,
                "memory_MB" => $memory,
                "fecha" => date("Y-m-d H:i:s")  
            ]);
        } catch (Exception $e) {
            Logger::error("Error en stats: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["error" => "Error generando estadísticas"]);
        }
    }
}
?>