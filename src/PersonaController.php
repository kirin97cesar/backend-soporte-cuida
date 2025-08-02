<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';

class PersonaController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->conectar();
    }

    public function index($estado = 'ACT') {
        Logger::logGlobal("📦 Listando tipos de documento");
        $query = "SELECT idTipoDocumentoIdentidad, descripcion 
                  FROM SALES_TIPO_DOCUMENTO_IDENTIDAD 
                  WHERE stsTipoDocumentoIdentidad = ?";
        Logger::logGlobal("El query es $query");

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$estado]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function buscarPersona($numeroDocumento, $tipoDocumento) {
        Logger::logGlobal("📦 buscarPersona ---> $numeroDocumento");

        $query = "SELECT sp.idPersona, sp.nombre, sp.apellidos, sp.numeroDocumento, sp.idTipoDocumentoIdentidad,
                         stdi.descripcion 
                  FROM SALES_PERSONA sp 
                  INNER JOIN SALES_TIPO_DOCUMENTO_IDENTIDAD stdi 
                      ON stdi.idTipoDocumentoIdentidad = sp.idTipoDocumentoIdentidad 
                  WHERE sp.numeroDocumento = ? AND sp.idTipoDocumentoIdentidad = ?";

        Logger::logGlobal("El query es $query");
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$numeroDocumento, $tipoDocumento]);
        $persona = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($persona) {
            echo json_encode($persona);
        } else {
            http_response_code(404);
            echo json_encode(["mensaje" => "Persona no encontrada"]);
        }
    }

    public function actualizarPersona($data) {
        Logger::logGlobal("✏️ Actualizando persona: " . json_encode($data));

        $query = "UPDATE SALES_PERSONA SET 
                    nombre = IFNULL(?, nombre),
                    apellidos = IFNULL(?, apellidos),
                    idTipoDocumentoIdentidad = IFNULL(?, idTipoDocumentoIdentidad),
                    numeroDocumento = IFNULL(?, numeroDocumento)
                  WHERE idPersona = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $data['nombre'] ?? null,
            $data['apellidos'] ?? null,
            $data['idTipoDocumentoIdentidad'] ?? null,
            $data['numeroDocumento'] ?? null,
            $data['idPersona']
        ]);

        echo json_encode(["mensaje" => "Persona actualizada"]);
    }

    public function actualizarPersonaConPedidos($data) {
        Logger::logGlobal("✏️ Actualizando nombres con pedidos: " . json_encode($data));

        $query = "UPDATE SALES_PERSONA SET 
                    nombre = IFNULL(?, nombre),
                    apellidos = IFNULL(?, apellidos),
                    idTipoDocumentoIdentidad = IFNULL(?, idTipoDocumentoIdentidad),
                    numeroDocumento = IFNULL(?, numeroDocumento)
                  WHERE idPersona = ?";
        Logger::logGlobal("El query es $query");
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $data['nombre'] ?? null,
            $data['apellidos'] ?? null,
            $data['idTipoDocumentoIdentidad'] ?? null,
            $data['numeroDocumento'] ?? null,
            $data['idPersona']
        ]);

        // Determina si actualizar solicitudes o pedidos
        if ($data['tipo'] === 'SOLICITUD') {
            $query2 = "UPDATE SALES_DETALLE_SOLICITUD SET 
                            nombresEnvio = IFNULL(?, nombresEnvio),
                            apellidosEnvio = IFNULL(?, apellidosEnvio),
                            idTipoDocumentoEnvio =  IFNULL(?, idTipoDocumentoEnvio),
                            numeroDocumentoEnvio = IFNULL(?, numeroDocumentoEnvio)
                       WHERE idSolicitud IN (SELECT idSolicitud from SALES_SOLICITUD WHERE idCliente = ?)";
        } else {
            $query2 = "UPDATE SALES_PEDIDO SET 
                            nombresEnvio = IFNULL(?, nombresEnvio),
                            apellidosEnvio = IFNULL(?, apellidosEnvio),
                            idTipoDocumentoEnvio =  IFNULL(?, idTipoDocumentoEnvio),
                            numeroDocumentoEnvio = IFNULL(?, numeroDocumentoEnvio)
                       WHERE idCliente = ?";
        }
        Logger::logGlobal("El query es $query2");
        Logger::logGlobal("✏️ Actualizando nombres con pedidos: " . json_encode($data));
        $stmt = $this->conn->prepare($query2);
        $stmt->execute([
            $data['nombre'] ?? null,
            $data['apellidos'] ?? null,
            $data['idTipoDocumentoEnvio'] ?? null,
            $data['numeroDocumentoEnvio'] ?? null,
            $data['idPersona']
        ]);

        echo json_encode(["mensaje" => "Persona y solicitudes/pedidos actualizados"]);
    }
}
