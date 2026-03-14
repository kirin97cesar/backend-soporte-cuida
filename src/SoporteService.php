<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';

class SoporteService {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->conectar();
    }
    
    public function obtenerSoportes($filtros = [], $pagina = 1, $limite = 20) {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $offset = ($pagina - 1) * $limite;

            $sqlBase = "FROM SALES_SOPORTES WHERE 1=1";
            $params = [];

            // Filtros existentes
            if (!empty($filtros['tipo'])) {
                $sqlBase .= " AND tipo = :tipo";
                $params[':tipo'] = $filtros['tipo'];
            }
            if (!empty($filtros['usuario'])) {
                $sqlBase .= " AND usuario = :usuario";
                $params[':usuario'] = $filtros['usuario'];
            }
            if (!empty($filtros['fecha'])) {
                $sqlBase .= " AND DATE(fechaCreacion) = :fecha";
                $params[':fecha'] = $filtros['fecha'];
            }

            // Nuevo: búsqueda global
            // Búsqueda global (DataTables search) incluyendo fecha
            if (!empty($filtros['search'])) {
                $sqlBase .= " AND (
                    tipo LIKE :search OR 
                    usuario LIKE :search OR 
                    descripcion LIKE :search OR 
                    DATE_FORMAT(fechaCreacion, '%Y-%m-%d') LIKE :search
                )";
                $params[':search'] = '%' . $filtros['search'] . '%';
            }

            // 1. Contar total de registros
            $sqlCount = "SELECT COUNT(*) as total " . $sqlBase;
            $stmtCount = $this->conn->prepare($sqlCount);
            $stmtCount->execute($params);
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            // 2. Obtener los registros con paginación
            $sqlData = "
                SELECT idSoporte, tipo, payload, usuario, fechaCreacion, descripcion, minutos, horas 
                $sqlBase
                ORDER BY idSoporte DESC
                LIMIT :limite OFFSET :offset
            ";
            $stmtData = $this->conn->prepare($sqlData);

            // Vincular filtros
            foreach ($params as $key => $val) {
                $stmtData->bindValue($key, $val);
            }
            $stmtData->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
            $stmtData->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

            $stmtData->execute();
            $registros = $stmtData->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'pagina' => $pagina,
                'limite' => $limite,
                'total' => (int)$total,
                'totalPaginas' => ceil($total / $limite),
                'data' => $registros
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function obtenerTiposSoportes() {
        header('Content-Type: application/json; charset=utf-8');
        Logger::logGlobal("📦 Listando tipos soportes válidos");

        try {
            $query = "SELECT DISTINCT tipo FROM SALES_SOPORTES";
            Logger::logGlobal("El query es: $query");

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'tipos' => $tipos
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }



    public function registrarSoporte($input, $email) {
        Logger::logGlobal("📦 registrarSoporte");
        Logger::logGlobal("📦 email $email");
        try {
            Logger::logGlobal("📦 registrarSoporte");

            $query1 = "INSERT INTO SALES_SOPORTES(tipo, payload, usuario, fechaCreacion, horas, minutos, descripcion) VALUES (?, ?, ?, NOW(), ? , ?, ?)";
            
            Logger::logGlobal("El query es: $query1");

            $stmt1 = $this->conn->prepare($query1);
            $stmt1->execute([
                $input['tipo'],
                $input['payload'],
                $email,
                $input['horas'] ?? null,
                $input['minutos'] ?? null,
                $input['descripcion'] ?? null
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

    public function obtenerReporte($filtroMes = null, $filtroAnio = null, $filtroUsuario = null)
    {
        try {

            Logger::logGlobal("📦 Generando reporte de soportes");

            $params = [];
            $condiciones = [];

            $hoy = new DateTime();
            $ultimoAnio = (int)$hoy->format('Y');

            $anio = (!$filtroAnio || $filtroAnio === "todos") ? $ultimoAnio : (int)$filtroAnio;

            $fechaInicio = "$anio-01-01 00:00:00";
            $fechaFin = ($anio + 1) . "-01-01 00:00:00";

            if ($filtroMes && $filtroMes !== "todos") {

                $mes = str_pad($filtroMes,2,"0",STR_PAD_LEFT);
                $fechaInicio = "$anio-$mes-01 00:00:00";
                $fechaFin = date("Y-m-d H:i:s", strtotime("$fechaInicio +1 month"));

            }

            $condiciones[] = "fechaCreacion >= :inicio AND fechaCreacion < :fin";
            $params[':inicio'] = $fechaInicio;
            $params[':fin'] = $fechaFin;

            if ($filtroUsuario && $filtroUsuario !== "todos") {
                $condiciones[] = "usuario = :usuario";
                $params[':usuario'] = $filtroUsuario;
            }

            $where = "WHERE " . implode(" AND ", $condiciones);

            /*
            ============================
            QUERY UNICA
            ============================
            */

            $query = "
            SELECT 
                usuario,
                tipo,
                DATE(fechaCreacion) fecha,
                HOUR(fechaCreacion) hora,
                MONTH(fechaCreacion) mes,
                COUNT(*) total
            FROM SALES_SOPORTES
            $where
            GROUP BY usuario, tipo, fecha, hora
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            /*
            ============================
            CONFIG
            ============================
            */

            $tiposNormalizados = [
                "MEJORA" => "Mejora",
                "PASE A PRODUCCION" => "Pase a producción",
                "INCIDENCIA" => "Incidencia"
            ];

            $tipos = ["Soporte","Pase a producción","Incidencia","Mejora"];

            $mesesNombre = [
                'Enero','Febrero','Marzo','Abril','Mayo','Junio',
                'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'
            ];

            $diaSemana = [
                'Domingo','Lunes','Martes','Miércoles',
                'Jueves','Viernes','Sábado'
            ];

            /*
            ============================
            ESTRUCTURAS
            ============================
            */

            $dataTicketsPorTipo = [
                "total" => array_fill_keys($tipos,0),
                "porMes" => []
            ];

            foreach ($mesesNombre as $mes) {
                $dataTicketsPorTipo["porMes"][$mes] = array_fill(0,count($tipos),0);
            }

            $ticketsPorTodosLosTipos = [
                "total"=>[],
                "porMes"=>[]
            ];

            $dataDevs = [];

            $dataMeses = [];
            foreach ($tipos as $tipo) {
                $dataMeses[$tipo] = array_fill(0,12,0);
            }

            $dataSoportes = [];
            $tiposPorDia = [];

            $mapTipos = array_flip($tipos);

            /*
            ============================
            PROCESAMIENTO
            ============================
            */

            foreach ($rows as $row) {

                $tipoRaw = $row['tipo'];

                $tipo = $tiposNormalizados[$tipoRaw] ?? "Soporte";

                $usuario = $row['usuario'];
                $hora = (int)$row['hora'];
                $fecha = $row['fecha'];
                $mesIndex = $row['mes'] - 1;
                $mesNombre = $mesesNombre[$mesIndex];
                $total = (int)$row['total'];

                /*
                ticketsPorTipo
                */

                $idx = $mapTipos[$tipo] ?? null;

                if ($idx !== null) {

                    $dataTicketsPorTipo["total"][$tipo] += $total;
                    $dataTicketsPorTipo["porMes"][$mesNombre][$idx] += $total;

                }

                /*
                devs
                */

                if (!isset($dataDevs[$usuario])) {
                    $dataDevs[$usuario] = 0;
                }

                $dataDevs[$usuario] += $total;

                /*
                meses
                */

                $dataMeses[$tipo][$mesIndex] += $total;

                /*
                heatmap
                */

                if (!isset($dataSoportes[$fecha])) {
                    $dataSoportes[$fecha] = array_fill(0,24,0);
                }

                $dataSoportes[$fecha][$hora] += $total;

                /*
                tiposPorDia
                */

                if (!isset($tiposPorDia[$tipoRaw])) {
                    $tiposPorDia[$tipoRaw] = [];
                }

                if (!isset($tiposPorDia[$tipoRaw][$fecha])) {
                    $tiposPorDia[$tipoRaw][$fecha] = 0;
                }

                $tiposPorDia[$tipoRaw][$fecha] += $total;

                /*
                todos los tipos
                */

                if (!isset($ticketsPorTodosLosTipos["total"][$tipoRaw])) {
                    $ticketsPorTodosLosTipos["total"][$tipoRaw] = 0;
                }

                $ticketsPorTodosLosTipos["total"][$tipoRaw] += $total;

                if (!isset($ticketsPorTodosLosTipos["porMes"][$mesNombre])) {
                    $ticketsPorTodosLosTipos["porMes"][$mesNombre] = [];
                }

                if (!isset($ticketsPorTodosLosTipos["porMes"][$mesNombre][$tipoRaw])) {
                    $ticketsPorTodosLosTipos["porMes"][$mesNombre][$tipoRaw] = 0;
                }

                $ticketsPorTodosLosTipos["porMes"][$mesNombre][$tipoRaw] += $total;

            }

            /*
            ============================
            HEATMAP
            ============================
            */

            $heatmapSeries = [];

            ksort($dataSoportes);

            foreach ($dataSoportes as $fecha => $horas) {

                $fechaObj = new DateTime($fecha);

                $nombreDia = $diaSemana[$fechaObj->format('w')];
                $nombreMes = $mesesNombre[$fechaObj->format('n') - 1];

                $heatmapSeries[] = [
                    "name" => "$nombreDia {$fechaObj->format('d')} $nombreMes",
                    "data" => $horas
                ];
            }

            arsort($dataDevs);

            /*
            ============================
            RESPUESTA
            ============================
            */

            echo json_encode([
                "ticketsPorTipo"=>$dataTicketsPorTipo,
                "ticketsPorTodosLosTipos"=>$ticketsPorTodosLosTipos,
                "devs"=>$dataDevs,
                "meses"=>$dataMeses,
                "soportes"=>$heatmapSeries,
                "tiposPorDia"=>$tiposPorDia
            ]);

        } catch (PDOException $e) {

            Logger::logGlobal("❌ Error: " . $e->getMessage());

            http_response_code(500);

            echo json_encode([
                "status"=>"error",
                "message"=>"No se pudo generar el reporte"
            ]);
        }
    }

}
