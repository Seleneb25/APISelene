<?php
// config/logger.php

class Logger {
    private static $logFile = null;
    private static $logLevels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARN' => 2,
        'ERROR' => 3,
        'FATAL' => 4
    ];
    private static $currentLevel = 'INFO';

    private static function ensureInit() {
        if (self::$logFile === null) {
            // Forzar uso del log dentro del repositorio `logs`.
            $projectLogDir = __DIR__ . '/../logs';
            if (!is_dir($projectLogDir)) {
                @mkdir($projectLogDir, 0755, true);
            }
            self::$logFile = $projectLogDir . DIRECTORY_SEPARATOR . 'server.log';
            
            // Configurar nivel de log desde variable de entorno
            $envLevel = getenv('LOG_LEVEL');
            if ($envLevel && in_array(strtoupper($envLevel), array_keys(self::$logLevels))) {
                self::$currentLevel = strtoupper($envLevel);
            }
        }
    }

    /**
     * Verifica si el log alcanza el número máximo de líneas y, si es así,
     * comprime el archivo (gzip) en `logs/archive/` y vacía el original.
     */
    private static function rotateIfNeeded() {
        self::ensureInit();

        $maxLines = getenv('LOG_MAX_LINES');
        $maxLines = ($maxLines && is_numeric($maxLines)) ? intval($maxLines) : 5000;

        if (!file_exists(self::$logFile)) {
            return;
        }

        $lineCount = 0;
        $fp = @fopen(self::$logFile, 'r');
        if ($fp) {
            while (!feof($fp)) {
                fgets($fp);
                $lineCount++;
                if ($lineCount >= $maxLines) {
                    break;
                }
            }
            fclose($fp);
        }

        if ($lineCount < $maxLines) {
            return; // no es necesario rotar
        }

        // Crear directorio de archivos archivados
        $archiveDir = dirname(self::$logFile) . DIRECTORY_SEPARATOR . 'archive';
        if (!is_dir($archiveDir)) {
            @mkdir($archiveDir, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $archivePath = $archiveDir . DIRECTORY_SEPARATOR . 'server.log.' . $timestamp . '.gz';

        // Comprimir el archivo actual en chunks y escribir el .gz
        $in = @fopen(self::$logFile, 'rb');
        if ($in) {
            $out = @gzopen($archivePath, 'wb9');
            if ($out) {
                while (!feof($in)) {
                    $chunk = fread($in, 1024 * 512);
                    if ($chunk === false) break;
                    gzwrite($out, $chunk);
                }
                gzclose($out);
            }
            fclose($in);

            // Truncar el archivo original para empezar uno nuevo
            $fp2 = @fopen(self::$logFile, 'w');
            if ($fp2) {
                // Escribir una entrada indicando rotación
                $note = "[" . date('Y-m-d H:i:s') . "] [INFO] Rotated log to " . basename($archivePath) . PHP_EOL;
                fwrite($fp2, $note);
                fclose($fp2);
            }
        }
    }

    /**
     * Verificar si el nivel de log permite escribir este mensaje
     */
    private static function shouldLog($level) {
        $messageLevel = self::$logLevels[$level] ?? 1;
        $currentLevel = self::$logLevels[self::$currentLevel] ?? 1;
        return $messageLevel >= $currentLevel;
    }

    /**
     * Escribir en el log con formato mejorado
     */
    private static function write($level, $message, $context = []) {
        self::ensureInit();
        
        // Verificar nivel de log
        if (!self::shouldLog($level)) {
            return;
        }
        
        // Rotar/comprimir si es necesario antes de escribir
        self::rotateIfNeeded();
        
        $time = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? 'CLI';
        
        // Formato mejorado del mensaje
        $entry = "[$time] [$level] [IP:$ip] [$method $uri] $message";
        
        // Agregar contexto si existe
        if (!empty($context)) {
            $contextStr = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $entry .= " | Context: $contextStr";
        }
        
        $entry .= PHP_EOL;
        
        // Usar bloqueo para evitar escrituras concurrentes
        @file_put_contents(self::$logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log para debugging (nivel más bajo)
     */
    public static function debug($message, $context = []) {
        self::write('DEBUG', $message, $context);
    }

    /**
     * Log informativo
     */
    public static function info($message, $context = []) {
        self::write('INFO', $message, $context);
    }

    /**
     * Log de advertencias
     */
    public static function warn($message, $context = []) {
        self::write('WARN', $message, $context);
    }

    /**
     * Log de errores
     */
    public static function error($message, $context = []) {
        self::write('ERROR', $message, $context);
    }

    /**
     * Log de errores fatales
     */
    public static function fatal($message, $context = []) {
        self::write('FATAL', $message, $context);
    }

    /**
     * Log de actividad de usuario
     */
    public static function audit($message, $user = null, $action = null) {
        $context = [];
        if ($user) {
            $context['user'] = $user;
        }
        if ($action) {
            $context['action'] = $action;
        }
        
        self::write('INFO', "[AUDIT] $message", $context);
    }

    /**
     * Log de actividad de seguridad
     */
    public static function security($message, $context = []) {
        self::write('WARN', "[SECURITY] $message", $context);
    }

    /**
     * Log de actividad de base de datos
     */
    public static function database($message, $context = []) {
        self::write('DEBUG', "[DATABASE] $message", $context);
    }

    /**
     * Log de actividad de API
     */
    public static function api($message, $context = []) {
        self::write('INFO', "[API] $message", $context);
    }

    /**
     * Cambiar nivel de log dinámicamente
     */
    public static function setLevel($level) {
        $level = strtoupper($level);
        if (in_array($level, array_keys(self::$logLevels))) {
            self::$currentLevel = $level;
            self::info("Log level changed to: $level");
        }
    }

    /**
     * Obtener nivel de log actual
     */
    public static function getLevel() {
        return self::$currentLevel;
    }

    /**
     * Obtener estadísticas del log
     */
    public static function getStats() {
        self::ensureInit();
        
        $stats = [
            'log_file' => self::$logFile,
            'log_level' => self::$currentLevel,
            'file_size' => 0,
            'file_exists' => false
        ];
        
        if (file_exists(self::$logFile)) {
            $stats['file_size'] = filesize(self::$logFile);
            $stats['file_exists'] = true;
            $stats['last_modified'] = date('Y-m-d H:i:s', filemtime(self::$logFile));
        }
        
        return $stats;
    }
}

date_default_timezone_set('America/Mexico_City');

/**
 * Función procedural mejorada para compatibilidad
 */
function log_event($message, $type = 'INFO', $context = []) {
    // Si la clase Logger existe, reutilizar sus métodos para mantener consistencia
    if (class_exists('Logger')) {
        $t = strtoupper($type);
        switch ($t) {
            case 'DEBUG':
                Logger::debug($message, $context);
                break;
            case 'ERROR':
                Logger::error($message, $context);
                break;
            case 'WARN':
            case 'WARNING':
                Logger::warn($message, $context);
                break;
            case 'FATAL':
                Logger::fatal($message, $context);
                break;
            case 'AUDIT':
                Logger::audit($message);
                break;
            case 'SECURITY':
                Logger::security($message, $context);
                break;
            case 'DATABASE':
                Logger::database($message, $context);
                break;
            case 'API':
                Logger::api($message, $context);
                break;
            default:
                Logger::info($message, $context);
        }
        return;
    }

    // Fallback si Logger no está disponible
    $envDir = getenv('LOG_DIR');
    $defaultExternal = 'C:\\xampp\\logs\\rest-api-selene';
    $useDir = null;
    if ($envDir) {
        $useDir = $envDir;
    } elseif (is_dir($defaultExternal) || @mkdir($defaultExternal, 0755, true)) {
        $useDir = $defaultExternal;
    }

    if ($useDir && is_dir($useDir)) {
        $logFile = rtrim($useDir, '/\\') . DIRECTORY_SEPARATOR . 'server.log';
    } else {
        $logFile = __DIR__ . "/../logs/server.log";
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    $date = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $entry = "[$date] [$type] [IP:$ip] $message";
    
    if (!empty($context)) {
        $contextStr = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $entry .= " | Context: $contextStr";
    }
    
    $entry .= PHP_EOL;
    
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

/**
 * Función helper para log rápido de errores de base de datos
 */
function log_db_error($error, $query = null) {
    $context = ['error' => $error];
    if ($query) {
        $context['query'] = $query;
    }
    Logger::database("Database error", $context);
}

/**
 * Función helper para log de actividad de usuario
 */
function log_user_action($username, $action, $details = []) {
    $context = array_merge(['user' => $username, 'action' => $action], $details);
    Logger::audit("User action: $action", $username, $action);
}
?>