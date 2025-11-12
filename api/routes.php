// api/routes.php (ACTUALIZADO PARA ALUMNOS)

// TODOS los archivos están dentro de api/
require_once __DIR__ . '/controllers/AlumnosController.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/config/logger.php';
require_once __DIR__ . '/controllers/StatsController.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/middleware/RoleMiddleware.php';
require_once __DIR__ . '/middleware/LoginAttemptMiddleware.php'; // NUEVO: Para rate limiting

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = preg_replace('#^.*/api/#', '', $uri);
$uri = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

$alumnosController = new AlumnosController();
$authController = new AuthController();

// Mapear alias: aceptar tanto /alumnos como /usuarios para compatibilidad
$aliases = [
    'usuarios' => 'alumnos',
    'alumnos' => 'alumnos'
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

    // NUEVA RUTA: Consultar tiempo de bloqueo
    case $resource === 'auth/blocked-time' && $method === 'GET':
        $blockedInfo = LoginAttemptMiddleware::getBlockedTime();
        echo json_encode($blockedInfo);
        break;

    // ==================== RUTAS PROTEGIDAS (REQUIEREN AUTENTICACIÓN) ====================
    case $resource === 'auth/profile' && $method === 'GET':
        AuthMiddleware::handle();
        $authController->getProfile();
        break;

    case $resource === 'alumnos' && $method === 'GET':
        AuthMiddleware::handle();
        $alumnosController->getAll();
        break;

    case $resource === 'stats' && $method === 'GET':
        AuthMiddleware::handle();
        StatsController::handler();
        break;

    // ==================== RUTAS SOLO ADMIN ====================
    case $resource === 'alumnos' && $method === 'POST':
        RoleMiddleware::handleAdmin();
        $alumnosController->create();
        break;

    case $resource === 'alumnos' && $method === 'PATCH':
        RoleMiddleware::handleAdmin();
        $alumnosController->update();
        break;

    case $resource === 'alumnos' && $method === 'DELETE':
        RoleMiddleware::handleAdmin();
        $alumnosController->delete();
        break;

    // ==================== NUEVAS RUTAS SOFT DELETE ====================
    case $resource === 'alumnos/deleted' && $method === 'GET':
        RoleMiddleware::handleAdmin();
        $alumnosController->getDeleted();
        break;

    case $resource === 'alumnos/restore' && $method === 'POST':
        RoleMiddleware::handleAdmin();
        $alumnosController->restore();
        break;

    case $resource === 'alumnos/force-delete' && $method === 'DELETE':
        RoleMiddleware::handleAdmin();
        $alumnosController->forceDelete();
        break;

    case $resource === 'logevent' && $method === 'POST':
        AuthMiddleware::handle();
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