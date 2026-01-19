<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';

class MotorizadoService {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->conectar();
    }

    public function index() {
        try {
            Logger::logGlobal("📦 Listando tipos de empresas");

            $query = "SELECT st.idTransportista, st.razonSocial, st.ruc 
                    FROM SALES_TRANSPORTISTA st 
                    WHERE st.stsTransportista = 'ACT'";
            
            Logger::logGlobal("El query es: $query");

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode([
                "status" => "success",
                "message" => "Empresas transportistas activas",
                "empresas" => $empresas
            ]);
        } catch (PDOException $e) {
            Logger::logGlobal("❌ Error al obtener empresas: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Error al obtener empresas",
                "error" => $e->getMessage()
            ]);
        }
    }

    public function buscarMotorizados($codigoMotorizado, $idEmpresa, $numeroDocumento) {
        Logger::logGlobal("📦 codigoMotorizado ---> $codigoMotorizado");
        Logger::logGlobal("📦 idEmpresa ---> $idEmpresa");


        $query = "SELECT sm.idMotorizado, sm.codigoMotorizado,
                    st.idTransportista , st.razonSocial ,
                    sm.idPersona , sp.apellidos ,sp.apePaterno, sp.apeMaterno, sp.nombre ,
                    sp.numeroDocumento , sp.idTipoDocumentoIdentidad,
                    sm.stsMotorizado , stdi.descripcion as tipoDocumento,
                    spe.email 
                    FROM SALES_MOTORIZADO sm 
                    INNER JOIN SALES_PERSONA sp
                    ON sm.idPersona = sp.idPersona 
                    INNER JOIN SALES_TRANSPORTISTA st 
                    ON st.idTransportista = sm.idTransportista 
                    INNER JOIN SALES_TIPO_DOCUMENTO_IDENTIDAD stdi 
                    ON stdi.idTipoDocumentoIdentidad = sp.idTipoDocumentoIdentidad 
                    INNER JOIN SALES_PERSONA_EMAIL spe 
                    ON spe.idPersona = sp.idPersona 
                    WHERE 1=1
                    ";

        if($codigoMotorizado) {
            $query .= " AND sm.codigoMotorizado = '$codigoMotorizado'";
        }            

        if($idEmpresa) {
            $query .= " AND sm.idTransportista = $idEmpresa";
        }

        if($numeroDocumento) {
            $query .= " AND sp.numeroDocumento = '$numeroDocumento'";
        }

        Logger::logGlobal("El query es $query");
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $motorizado = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ( $motorizado && count($motorizado) > 0 ) {
            echo json_encode([
                "status" => "success",
                "message" => "Motorizado encontrado",
                "motorizado" => $motorizado
            ]);
        } else {
            http_response_code(404);
            echo json_encode(["mensaje" => "Motorizado no encontrado"]);
        }
    }

    public function registrarEmpresa($input) {
        try {
            Logger::logGlobal("📦 registrarEmpresa");

            $query1 = "SELECT idTransportista from db_ventas_logistica.SALES_TRANSPORTISTA
                        ORDER BY idTransportista desc limit 1;";
            
            Logger::logGlobal("El query es: $query1");

            $stmt1 = $this->conn->prepare($query1);
            $stmt1->execute();
            $empresa = $stmt1->fetch(PDO::FETCH_ASSOC);

            $id = $empresa['idTransportista'] + 1;

            $query = "INSERT INTO db_ventas_logistica.SALES_TRANSPORTISTA
                        (idTransportista, razonSocial, ruc, codigoOdoo, stsTransportista)
                        VALUES( $id ,?, ?, $id , 'ACT');";
            
            Logger::logGlobal("El query es: $query");

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $input['razonSocial'],
                $input['ruc']
            ]);

            header('Content-Type: application/json');
            echo json_encode([
                "status" => "success",
                "message" => "Empresas creada!"
            ]);

        } catch (PDOException $e) {
            Logger::logGlobal("❌ Error al obtener empresas: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo crear!"
            ]);
        }
    }
}
