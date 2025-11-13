<?php
require_once __DIR__ . '/../config/db.php'; // NUEVO: Incluir Database
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../models/Alumnos.php';
require_once __DIR__ . '/../models/User.php';

class StatsController
{
    public static function handler()
    {
        try {
            // Métricas del servidor
            $uptime = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2);
            $memory_usage = round(memory_get_usage() / 1024 / 1024, 2);
            $memory_peak = round(memory_get_peak_usage() / 1024 / 1024, 2);
            
            // Métricas de la aplicación
            $alumnosModel = new Alumnos();
            
            // CORREGIDO: Crear conexión para User
            $database = new Database();
            $db = $database->getConnection();
            $userModel = new User($db);
            
            $total_alumnos = $alumnosModel->getAllActive() ? count($alumnosModel->getAllActive()) : 0;
            $alumnos_eliminados = $alumnosModel->getDeleted() ? count($alumnosModel->getDeleted()) : 0;
            
            // CORREGIDO: Verificar si el método existe
            $total_usuarios = 0;
            if (method_exists($userModel, 'getAllUsers')) {
                $usuarios = $userModel->getAllUsers();
                $total_usuarios = $usuarios ? count($usuarios) : 0;
            }
            
            // Métricas del sistema
            $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
            $php_version = PHP_VERSION;
            $server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido';
            
            Logger::info('GET /stats - métricas generadas correctamente');
            
            echo json_encode([
                // MÉTRICAS DEL SERVIDOR
                "servidor" => [
                    "uptime_segundos" => $uptime,
                    "memoria_uso_MB" => $memory_usage,
                    "memoria_pico_MB" => $memory_peak,
                    "carga_sistema" => [
                        "1_minuto" => $load[0],
                        "5_minutos" => $load[1], 
                        "15_minutos" => $load[2]
                    ],
                    "software" => $server_software,
                    "php_version" => $php_version
                ],
                
                // MÉTRICAS DE LA APLICACIÓN
                "aplicacion" => [
                    "total_alumnos" => $total_alumnos,
                    "alumnos_eliminados" => $alumnos_eliminados,
                    "total_usuarios" => $total_usuarios,
                    "fecha_actual" => date("Y-m-d H:i:s")
                ],
                
                // MÉTRICAS DE RENDIMIENTO
                "rendimiento" => [
                    "timestamp" => time(),
                    "timezone" => date_default_timezone_get()
                ]
            ]);
            
        } catch (Exception $e) {
            Logger::error("Error en stats: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["error" => "Error generando estadísticas"]);
        }
    }
}
?>