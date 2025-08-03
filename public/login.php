<?php
// CORS (esto debe ir primero)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../src/JWTUtils.php';
require_once __DIR__ . '/../config/cargarEnv.php'; 
require_once __DIR__ . '/../utils/Logger.php';


cargarEnv(__DIR__ . '/../.env');



// Manejo de preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


$input = json_decode(file_get_contents("php://input"), true);
$usuario = $input['usuario'] ?? null;
$attributes = $input['attributes'] ?? null;

// Autenticación
if ($usuario && $attributes) {
    $payload = ['usuario' => $usuario];
    $token = JWTUtils::generarToken($payload);
    echo json_encode(["token" => $token]);
} else {
    http_response_code(401);
    echo json_encode(["error" => "Credenciales incorrectas"]);
}
