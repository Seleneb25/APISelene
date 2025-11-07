<?php
// api/routes.php (CON MIDDLEWARE)

// TODOS los archivos están dentro de api/
require_once __DIR__ . '/controllers/UsuariosController.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/config/logger.php';
require_once __DIR__ . '/controllers/StatsController.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php'; // NUEVO
require_once __DIR__ . '/middleware/RoleMiddleware.php'; // NUEVO

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = preg_replace('#^.*/api/#', '', $uri);
$uri = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

$controller = new UsuariosController();
$authController = new AuthController();

// Mapear alias: aceptar tanto /usuarios como /alumnos
$aliases = [
    'alumnos' => 'usuarios'
];

$resource = $aliases[$uri] ?? $uri;

Logger::info("Request: $method /$uri -> resolved to resource '$resource'");

switch (true) {
    // ==================== RUTAS PÚBLICAS (SIN AUTENTICACIÓN) ====================
    case $resource === 'auth/login' && $method === 'POST':
        $authController->login();
        break;
        
    case $resource === 'auth/logout' && $method === 'POST':
        $authController->logout();
        break;
        
    case $resource === 'auth/check' && $method === 'GET':
        $authController->checkAuth();
        break;

    // ==================== RUTAS PROTEGIDAS (REQUIEREN AUTENTICACIÓN) ====================
    case $resource === 'auth/profile' && $method === 'GET':
        AuthMiddleware::handle(); // Verificar autenticación
        $authController->getProfile();
        break;

    case $resource === 'usuarios' && $method === 'GET':
        AuthMiddleware::handle(); // Cualquier usuario autenticado puede ver
        $controller->getAll();
        break;

    case $resource === 'stats' && $method === 'GET':
        AuthMiddleware::handle(); // Cualquier usuario autenticado puede ver stats
        StatsController::handler();
        break;

    // ==================== RUTAS SOLO ADMIN ====================
    case $resource === 'usuarios' && $method === 'POST':
        RoleMiddleware::handleAdmin(); // Solo admin puede crear
        $controller->create();
        break;

    case $resource === 'usuarios' && $method === 'PATCH':
        RoleMiddleware::handleAdmin(); // Solo admin puede actualizar
        $controller->update();
        break;

    case $resource === 'usuarios' && $method === 'DELETE':
        RoleMiddleware::handleAdmin(); // Solo admin puede eliminar
        $controller->delete();
        break;

    case $resource === 'logevent' && $method === 'POST':
        AuthMiddleware::handle(); // Cualquier usuario autenticado puede log events
        $input = json_decode(file_get_contents('php://input'), true);
        $nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
        if ($nombre === '' || !preg_match('/^[\p{L}\s]+$/u', $nombre)) {
            http_response_code(400);
            if (function_exists('log_event')) {
                log_event("Intento de log_event invalido: $nombre", "WARN");
            }
            echo json_encode(["error" => "Nombre invalido"]);
        } else {
            if (function_exists('log_event')) {
                log_event("Evento usuario: " . json_encode($input), "INFO");
            }
            echo json_encode(["success" => true]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["error" => "Ruta no encontrada", "ruta" => $uri]);
        break;
}
?>