<?php
// api/utils/Sanitizer.php

class Sanitizer {
    
    /**
     * Sanitizar string - Limpieza básica
     */
    public static function sanitizeString($input) {
        if ($input === null || $input === '') {
            return $input;
        }
        
        // Convertir a string si no lo es
        $input = (string)$input;
        
        // Trim espacios en blanco
        $input = trim($input);
        
        // Eliminar caracteres de control (excepto tab, newline, carriage return)
        $input = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        // Convertir caracteres especiales a entidades HTML
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $input;
    }
    
    /**
     * Sanitizar string pero permitir algunos HTML seguro (para descripciones, etc.)
     */
    public static function sanitizeHTML($input, $allowedTags = '<p><br><strong><em><ul><ol><li>') {
        if ($input === null || $input === '') {
            return $input;
        }
        
        $input = self::sanitizeString($input);
        
        // Permitir solo las etiquetas HTML especificadas
        $input = strip_tags($input, $allowedTags);
        
        return $input;
    }
    
    /**
     * Sanitizar email
     */
    public static function sanitizeEmail($email) {
        if ($email === null || $email === '') {
            return null;
        }
        
        $email = self::sanitizeString($email);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        $email = strtolower($email);
        
        return $email;
    }
    
    /**
     * Sanitizar número entero
     */
    public static function sanitizeInt($input) {
        if ($input === null || $input === '') {
            return null;
        }
        
        // Remover cualquier caracter que no sea número o signo negativo
        $input = preg_replace('/[^-0-9]/', '', (string)$input);
        
        // Convertir a entero
        $input = (int)$input;
        
        return $input;
    }
    
    /**
     * Sanitizar número flotante
     */
    public static function sanitizeFloat($input) {
        if ($input === null || $input === '') {
            return null;
        }
        
        // Remover cualquier caracter que no sea número, punto decimal o signo negativo
        $input = preg_replace('/[^-0-9.]/', '', (string)$input);
        
        // Convertir a float
        $input = (float)$input;
        
        return $input;
    }
    
    /**
     * Sanitizar número positivo (para IDs, edades, etc.)
     */
    public static function sanitizePositiveInt($input) {
        $input = self::sanitizeInt($input);
        
        if ($input !== null && $input < 0) {
            return null;
        }
        
        return $input;
    }
    
    /**
     * Sanitizar booleano
     */
    public static function sanitizeBoolean($input) {
        if ($input === null) {
            return false;
        }
        
        if (is_bool($input)) {
            return $input;
        }
        
        if (is_numeric($input)) {
            return (bool)$input;
        }
        
        if (is_string($input)) {
            $input = strtolower(trim($input));
            $trueValues = ['true', '1', 'yes', 'on', 'si', 'sí'];
            return in_array($input, $trueValues);
        }
        
        return false;
    }
    
    /**
     * Sanitizar array completo
     */
    public static function sanitizeArray($array, $rules = []) {
        if (!is_array($array)) {
            return [];
        }
        
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            // Si hay reglas específicas para este campo, usarlas
            if (isset($rules[$key])) {
                $sanitized[$key] = self::applySanitizationRule($value, $rules[$key]);
            } else {
                // Sanitización por defecto según el tipo
                $sanitized[$key] = self::sanitizeByType($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Aplicar regla de sanitización específica
     */
    private static function applySanitizationRule($value, $rule) {
        switch ($rule) {
            case 'string':
                return self::sanitizeString($value);
                
            case 'email':
                return self::sanitizeEmail($value);
                
            case 'int':
                return self::sanitizeInt($value);
                
            case 'positive_int':
                return self::sanitizePositiveInt($value);
                
            case 'float':
                return self::sanitizeFloat($value);
                
            case 'boolean':
                return self::sanitizeBoolean($value);
                
            case 'html':
                return self::sanitizeHTML($value);
                
            default:
                return self::sanitizeByType($value);
        }
    }
    
    /**
     * Sanitizar por tipo de dato automáticamente
     */
    private static function sanitizeByType($value) {
        if ($value === null) {
            return null;
        }
        
        if (is_int($value)) {
            return self::sanitizeInt($value);
        }
        
        if (is_float($value)) {
            return self::sanitizeFloat($value);
        }
        
        if (is_bool($value)) {
            return self::sanitizeBoolean($value);
        }
        
        if (is_numeric($value)) {
            return self::sanitizeFloat($value);
        }
        
        if (is_string($value)) {
            // Detectar si es email
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return self::sanitizeEmail($value);
            }
            
            return self::sanitizeString($value);
        }
        
        if (is_array($value)) {
            return self::sanitizeArray($value);
        }
        
        // Para cualquier otro tipo, convertirlo a string y sanitizar
        return self::sanitizeString((string)$value);
    }
    
    /**
     * Sanitizar datos de alumno
     */
    public static function sanitizeAlumnoData($data) {
        $rules = [
            'nombre' => 'string',
            'edad' => 'positive_int',
            'correo' => 'email',
            'rol' => 'string'
        ];
        
        return self::sanitizeArray($data, $rules);
    }
    
    /**
     * Sanitizar datos de usuario (login/registro)
     */
    public static function sanitizeUserData($data) {
        $rules = [
            'username' => 'string',
            'email' => 'email',
            'password' => 'string', // No sanitizar password demasiado para no afectar hash
            'rol' => 'string'
        ];
        
        return self::sanitizeArray($data, $rules);
    }
    
    /**
     * Sanitizar datos de login
     */
    public static function sanitizeLoginData($data) {
        $rules = [
            'username' => 'string',
            'password' => 'string'
        ];
        
        return self::sanitizeArray($data, $rules);
    }
    
    /**
     * Sanitizar para búsqueda en base de datos (prevenir SQL injection)
     */
    public static function sanitizeForDatabase($input) {
        if ($input === null || $input === '') {
            return $input;
        }
        
        // Para uso en consultas, solo caracteres alfanuméricos básicos y algunos especiales
        $input = preg_replace('/[^a-zA-Z0-9_\-@\. ]/', '', (string)$input);
        $input = trim($input);
        
        return $input;
    }
    
    /**
     * Sanitizar para uso en URLs
     */
    public static function sanitizeForURL($input) {
        if ($input === null || $input === '') {
            return '';
        }
        
        $input = self::sanitizeString($input);
        $input = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $input);
        $input = preg_replace('/-+/', '-', $input);
        $input = trim($input, '-');
        
        return $input;
    }
    
    /**
     * Limpiar y normalizar texto (para nombres, títulos, etc.)
     */
    public static function normalizeText($input) {
        if ($input === null || $input === '') {
            return $input;
        }
        
        $input = self::sanitizeString($input);
        
        // Convertir múltiples espacios en uno solo
        $input = preg_replace('/\s+/', ' ', $input);
        
        // Capitalizar primera letra de cada palabra (para nombres)
        $input = mb_convert_case($input, MB_CASE_TITLE, 'UTF-8');
        
        return $input;
    }
    
    /**
     * Sanitizar y validar que no esté vacío después de la limpieza
     */
    public static function sanitizeRequired($input, $fieldName = 'campo') {
        $sanitized = self::sanitizeString($input);
        
        if ($sanitized === null || $sanitized === '') {
            throw new Exception("El campo $fieldName no puede estar vacío después de la sanitización");
        }
        
        return $sanitized;
    }
}
?>