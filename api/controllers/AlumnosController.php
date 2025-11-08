<?php
// controllers/AlumnosController.php
require_once __DIR__ . '/../models/Alumnos.php';
require_once __DIR__ . '/../config/logger.php';

class AlumnosController {
    private $model;

    public function __construct() {
        $this->model = new Alumnos();
    }

    public function getAll() {
        Logger::info('GET /alumnos');
        $result = $this->model->getAllActive(); // Usar getAllActive para soft delete
        echo json_encode($result);
    }

    public function create() {
        $input = json_decode(file_get_contents("php://input"), true);
        Logger::info('POST /alumnos payload: ' . json_encode($input));

        // Validación: el nombre sólo debe contener letras (incluye acentos) y espacios.
        $nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
        if ($nombre === '' || !preg_match('/^[\p{L}\s]+$/u', $nombre)) {
            http_response_code(400);
            Logger::warn("Intento de insercion invalida: $nombre");
            echo json_encode(["error" => "Nombre invalido"]);
            return;
        }

        $res = $this->model->create($input);
        Logger::info('POST /alumnos result: ' . json_encode($res));
        if (isset($res['success']) && $res['success'] === false) {
            http_response_code(500);
            echo json_encode($res);
            return;
        }
        echo json_encode($res);
    }

    public function update() {
        $input = json_decode(file_get_contents("php://input"), true);
        Logger::info('PATCH /alumnos payload: ' . json_encode($input));
        
        // Validar que tenga ID
        if (!isset($input['id'])) {
            http_response_code(400);
            Logger::warn("Intento de actualizacion sin ID");
            echo json_encode(["error" => "Falta el campo 'id'"]);
            return;
        }
        
        // Si se proporciona 'nombre' en el payload, validarlo
        if (isset($input['nombre'])) {
            $nombre = trim($input['nombre']);
            if ($nombre === '' || !preg_match('/^[\p{L}\s]+$/u', $nombre)) {
                http_response_code(400);
                Logger::warn("Intento de actualizacion invalida: $nombre");
                echo json_encode(["error" => "Nombre invalido"]);
                return;
            }
            $input['nombre'] = $nombre;
        }

        $res = $this->model->update($input);
        Logger::info('PATCH /alumnos result: ' . json_encode($res));
        if (isset($res['success']) && $res['success'] === false) {
            http_response_code(400);
            echo json_encode($res);
            return;
        }
        echo json_encode($res);
    }

    public function delete() {
        $input = json_decode(file_get_contents("php://input"), true);
        Logger::info('DELETE /alumnos payload: ' . json_encode($input));
        
        if (!isset($input['id'])) {
            http_response_code(400);
            $err = ["success" => false, "error" => "Falta el campo 'id'"];
            Logger::warn('DELETE /alumnos faltó id en payload');
            echo json_encode($err);
            return;
        }

        // Usar softDelete en lugar de delete permanente
        $res = $this->model->softDelete($input['id']);
        Logger::info('DELETE /alumnos result: ' . json_encode($res));
        
        if ($res) {
            echo json_encode([
                "success" => true,
                "message" => "Alumno eliminado correctamente (soft delete)"
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                "success" => false, 
                "error" => "Error al eliminar alumno"
            ]);
        }
    }

    /**
     * NUEVO: Restaurar alumno eliminado (solo admin)
     */
    public function restore() {
        $input = json_decode(file_get_contents("php://input"), true);
        Logger::info('POST /alumnos/restore payload: ' . json_encode($input));
        
        if (!isset($input['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Falta el campo 'id'"]);
            return;
        }

        $res = $this->model->restore($input['id']);
        
        if ($res) {
            echo json_encode([
                "success" => true,
                "message" => "Alumno restaurado correctamente"
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                "success" => false, 
                "error" => "Error al restaurar alumno"
            ]);
        }
    }

    /**
     * NUEVO: Obtener alumnos eliminados (solo admin)
     */
    public function getDeleted() {
        Logger::info('GET /alumnos/deleted');
        $result = $this->model->getDeleted();
        echo json_encode($result);
    }

    /**
     * NUEVO: Eliminación permanente (solo admin)
     */
    public function forceDelete() {
        $input = json_decode(file_get_contents("php://input"), true);
        Logger::info('DELETE /alumnos/force payload: ' . json_encode($input));
        
        if (!isset($input['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Falta el campo 'id'"]);
            return;
        }

        $res = $this->model->forceDelete($input['id']);
        
        if ($res) {
            echo json_encode([
                "success" => true,
                "message" => "Alumno eliminado permanentemente"
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                "success" => false, 
                "error" => "Error al eliminar alumno permanentemente"
            ]);
        }
    }
}
?>