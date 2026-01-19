<?php

// CORS (esto debe ir primero)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../utils/Logger.php';

// Manejo de preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


require_once __DIR__ . '/../src/ProductService.php';
require_once __DIR__ . '/../src/JWTUtils.php';

// Determinar la URI relativa (después de /api/public)
$basePath = '/api/public';
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace($basePath, '', $requestUri);
Logger::logGlobal("🧪 path: $path ");
$sku = $_GET['sku'] ?? null;
$idPetitorio = $_GET['idPetitorio'] ?? null;
$idCanalVenta = $_GET['idCanalVenta'] ?? null;
Logger::logGlobal("🧪 ID: $id ");
$method = $_SERVER['REQUEST_METHOD'];

Logger::logGlobal("🌐 $method $requestUri");


Logger::logGlobal("🧪 path: $path ");

function getRequestHeaders() {
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (str_starts_with($name, 'HTTP_')) {
            $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$header] = $value;
        }
    }
    return $headers;
}

$headers = getRequestHeaders();
Logger::logGlobal("🧪 headers --->: ". json_encode($headers));

if (isset($headers['X-Auth-Token'])) {
    $token = str_replace('Bearer ', '', $headers['X-Auth-Token']);
} elseif (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
} elseif (isset($headers['authorization'])) {
    $token = str_replace('Bearer ', '', $headers['authorization']);
}

if (!$token) {
    http_response_code(401);
    echo json_encode(["error" => "Token no proporcionado"]);
    exit;
}

$usuario = JWTUtils::verificarToken($token);
if (!$usuario) {
    http_response_code(401);
    echo json_encode(["error" => "Token inválido o expirado"]);
    exit;
}


// Procesar la solicitud
$service = new ProductService();
$input = json_decode(file_get_contents("php://input"), true);

switch ($method) {
    case 'GET':
        $sku ? $service->buscarProducto($idPetitorio, $sku, $idCanalVenta) : $service->index();
        break;
    case 'POST':
        $service->actualizarProducto($input);
        break;
    default:
        http_response_code(405);
        echo json_encode(["mensaje" => "Método no permitido"]);
        break;
}
