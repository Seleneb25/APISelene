<?php
// api/utils/Validator.php

class Validator {
    
    /**
     * Validar nombre (solo letras, espacios y acentos)
     */
    public static function validateNombre($nombre) {
        if (empty($nombre)) {
            return ["valid" => false, "error" => "El nombre no puede estar vacío"];
        }
        
        if (strlen($nombre) < 2) {
            return ["valid" => false, "error" => "El nombre debe tener al menos 2 caracteres"];
        }
        
        if (strlen($nombre) > 100) {
            return ["valid" => false, "error" => "El nombre no puede tener más de 100 caracteres"];
        }
        
        // Permitir letras, espacios y caracteres acentuados
        if (!preg_match('/^[\p{L}\s\'-]+$/u', $nombre)) {
            return ["valid" => false, "error" => "El nombre solo puede contener letras, espacios y acentos"];
        }
        
        return ["valid" => true];
    }
    
    /**
     * Validar email
     */
    public static function validateEmail($email) {
        if (empty($email)) {
            return ["valid" => false, "error" => "El email no puede estar vacío"];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ["valid" => false, "error" => "El formato del email no es válido"];
        }
        
        if (strlen($email) > 100) {
            return ["valid" => false, "error" => "El email no puede tener más de 100 caracteres"];
        }
        
        return ["valid" => true];
    }
    
    /**
     * Validar edad
     */
    public static function validateEdad($edad) {
        if ($edad === null || $edad === '') {
            return ["valid" => true]; // Edad es opcional
        }
        
        if (!is_numeric($edad)) {
            return ["valid" => false, "error" => "La edad debe ser un número"];
        }
        
        $edad = (int)$edad;
        
        if ($edad < 1) {
            return ["valid" => false, "error" => "La edad debe ser mayor a 0"];
        }
        
        if ($edad > 120) {
            return ["valid" => false, "error" => "La edad no puede ser mayor a 120"];
        }
        
        return ["valid" => true];
    }
    
    /**
     * Validar rol
     */
    public static function validateRol($rol) {
        $rolesPermitidos = ['admin', 'user', 'Alumno', 'Profesor', 'Estudiante'];
        
        if (empty($rol)) {
            return ["valid" => false, "error" => "El rol no puede estar vacío"];
        }
        
        if (!in_array($rol, $rolesPermitidos)) {
            return ["valid" => false, "error" => "Rol no válido. Roles permitidos: " . implode(', ', $rolesPermitidos)];
        }
        
        return ["valid" => true];
    }
    
    /**
     * Validar ID numérico
     */
    public static function validateId($id) {
        if (empty($id)) {
            return ["valid" => false, "error" => "El ID no puede estar vacío"];
        }
        
        if (!is_numeric($id)) {
            return ["valid" => false, "error" => "El ID debe ser un número"];
        }
        
        $id = (int)$id;
        
        if ($id <= 0) {
            return ["valid" => false, "error" => "El ID debe ser mayor a 0"];
        }
        
        return ["valid" => true];
    }
    
    /**
     * Validar datos de alumno para creación
     */
    public static function validateAlumnoData($data) {
        $errors = [];
        
        // Validar nombre
        if (isset($data['nombre'])) {
            $nombreValidation = self::validateNombre($data['nombre']);
            if (!$nombreValidation['valid']) {
                $errors['nombre'] = $nombreValidation['error'];
            }
        } else {
            $errors['nombre'] = "El campo nombre es requerido";
        }
        
        // Validar edad (opcional)
        if (isset($data['edad'])) {
            $edadValidation = self::validateEdad($data['edad']);
            if (!$edadValidation['valid']) {
                $errors['edad'] = $edadValidation['error'];
            }
        }
        
        // Validar email (opcional)
        if (isset($data['correo']) && !empty($data['correo'])) {
            $emailValidation = self::validateEmail($data['correo']);
            if (!$emailValidation['valid']) {
                $errors['correo'] = $emailValidation['error'];
            }
        }
        
        // Validar rol (opcional, por defecto 'Alumno')
        if (isset($data['rol'])) {
            $rolValidation = self::validateRol($data['rol']);
            if (!$rolValidation['valid']) {
                $errors['rol'] = $rolValidation['error'];
            }
        }
        
        if (empty($errors)) {
            return ["valid" => true];
        } else {
            return ["valid" => false, "errors" => $errors];
        }
    }
    
    /**
     * Validar datos de alumno para actualización
     */
    public static function validateAlumnoUpdate($data) {
        $errors = [];
        
        // Validar ID (requerido para actualización)
        if (!isset($data['id'])) {
            $errors['id'] = "El campo ID es requerido para actualizar";
        } else {
            $idValidation = self::validateId($data['id']);
            if (!$idValidation['valid']) {
                $errors['id'] = $idValidation['error'];
            }
        }
        
        // Validar nombre (si se proporciona)
        if (isset($data['nombre']) && !empty($data['nombre'])) {
            $nombreValidation = self::validateNombre($data['nombre']);
            if (!$nombreValidation['valid']) {
                $errors['nombre'] = $nombreValidation['error'];
            }
        }
        
        // Validar edad (si se proporciona)
        if (isset($data['edad'])) {
            $edadValidation = self::validateEdad($data['edad']);
            if (!$edadValidation['valid']) {
                $errors['edad'] = $edadValidation['error'];
            }
        }
        
        // Validar email (si se proporciona)
        if (isset($data['correo']) && !empty($data['correo'])) {
            $emailValidation = self::validateEmail($data['correo']);
            if (!$emailValidation['valid']) {
                $errors['correo'] = $emailValidation['error'];
            }
        }
        
        // Validar rol (si se proporciona)
        if (isset($data['rol']) && !empty($data['rol'])) {
            $rolValidation = self::validateRol($data['rol']);
            if (!$rolValidation['valid']) {
                $errors['rol'] = $rolValidation['error'];
            }
        }
        
        // Verificar que al menos un campo sea proporcionado para actualizar
        $camposActualizables = ['nombre', 'edad', 'correo', 'rol'];
        $tieneCamposParaActualizar = false;
        
        foreach ($camposActualizables as $campo) {
            if (isset($data[$campo]) && $data[$campo] !== '') {
                $tieneCamposParaActualizar = true;
                break;
            }
        }
        
        if (!$tieneCamposParaActualizar) {
            $errors['general'] = "Debe proporcionar al menos un campo para actualizar (nombre, edad, correo o rol)";
        }
        
        if (empty($errors)) {
            return ["valid" => true];
        } else {
            return ["valid" => false, "errors" => $errors];
        }
    }
    
    /**
     * Validar datos de login
     */
    public static function validateLogin($data) {
        $errors = [];
        
        // Validar username
        if (!isset($data['username']) || empty($data['username'])) {
            $errors['username'] = "El nombre de usuario es requerido";
        } else if (strlen($data['username']) < 3) {
            $errors['username'] = "El nombre de usuario debe tener al menos 3 caracteres";
        } else if (strlen($data['username']) > 50) {
            $errors['username'] = "El nombre de usuario no puede tener más de 50 caracteres";
        }
        
        // Validar password
        if (!isset($data['password']) || empty($data['password'])) {
            $errors['password'] = "La contraseña es requerida";
        } else if (strlen($data['password']) < 1) {
            $errors['password'] = "La contraseña es requerida";
        }
        
        if (empty($errors)) {
            return ["valid" => true];
        } else {
            return ["valid" => false, "errors" => $errors];
        }
    }
    
    /**
     * Validar datos de usuario para creación
     */
    public static function validateUserData($data) {
        $errors = [];
        
        // Validar username
        if (!isset($data['username']) || empty($data['username'])) {
            $errors['username'] = "El nombre de usuario es requerido";
        } else if (strlen($data['username']) < 3) {
            $errors['username'] = "El nombre de usuario debe tener al menos 3 caracteres";
        } else if (strlen($data['username']) > 50) {
            $errors['username'] = "El nombre de usuario no puede tener más de 50 caracteres";
        } else if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            $errors['username'] = "El nombre de usuario solo puede contener letras, números y guiones bajos";
        }
        
        // Validar email
        if (!isset($data['email']) || empty($data['email'])) {
            $errors['email'] = "El email es requerido";
        } else {
            $emailValidation = self::validateEmail($data['email']);
            if (!$emailValidation['valid']) {
                $errors['email'] = $emailValidation['error'];
            }
        }
        
        // Validar password
        if (!isset($data['password']) || empty($data['password'])) {
            $errors['password'] = "La contraseña es requerida";
        } else if (strlen($data['password']) < 6) {
            $errors['password'] = "La contraseña debe tener al menos 6 caracteres";
        }
        
        // Validar rol
        if (isset($data['rol'])) {
            $rolValidation = self::validateRol($data['rol']);
            if (!$rolValidation['valid']) {
                $errors['rol'] = $rolValidation['error'];
            }
        }
        
        if (empty($errors)) {
            return ["valid" => true];
        } else {
            return ["valid" => false, "errors" => $errors];
        }
    }
    
    /**
     * Validar que el array tenga las claves requeridas
     */
    public static function validateRequiredFields($data, $requiredFields) {
        $errors = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $errors[$field] = "El campo $field es requerido";
            }
        }
        
        if (empty($errors)) {
            return ["valid" => true];
        } else {
            return ["valid" => false, "errors" => $errors];
        }
    }
    
    /**
     * Sanitizar string básico
     */
    public static function sanitizeString($string) {
        if ($string === null) {
            return null;
        }
        
        $string = trim($string);
        $string = stripslashes($string);
        $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        
        return $string;
    }
    
    /**
     * Sanitizar número
     */
    public static function sanitizeNumber($number) {
        if ($number === null || $number === '') {
            return null;
        }
        
        // Remover cualquier caracter que no sea número
        $number = preg_replace('/[^0-9]/', '', $number);
        
        return $number ? (int)$number : null;
    }
}
?>