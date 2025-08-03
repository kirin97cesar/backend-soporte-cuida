<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';

class SoporteController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->conectar();
    }

    public function registrarSoporte($input, $email) {
        Logger::logGlobal("📦 registrarSoporte");
        Logger::logGlobal("📦 email $email");
        try {
            Logger::logGlobal("📦 registrarSoporte");

            $query1 = "INSERT INTO SALES_SOPORTES(tipo, payload, usuario, fechaCreacion) VALUES (?, ?, ?, NOW())";
            
            Logger::logGlobal("El query es: $query1");

            $stmt1 = $this->conn->prepare($query1);
            $stmt1->execute([
                $input['tipo'],
                $input['payload'],
                $email
            ]);

            header('Content-Type: application/json');
            echo json_encode([
                "status" => "success",
                "message" => "Soporte registrado!"
            ]);

        } catch (PDOException $e) {
            Logger::logGlobal("❌ Error al registrar soporte: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo registrar el soporte!"
            ]);
        }
    }
}
