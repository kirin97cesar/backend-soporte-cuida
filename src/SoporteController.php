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

    public function obtenerReporte($filtroMes = null, $filtroAnio = null, $filtroUsuario = null) {
        try {
            Logger::logGlobal("📦 Generando reporte de soportes");

            $params = [];
            $condiciones = [];

            $hoy = new DateTime();
            $ultimoMes = (int)$hoy->format('m');
            $ultimoAnio = (int)$hoy->format('Y');

            // Filtro por año
            if (!$filtroAnio || $filtroAnio === "todos") {
                $anio = $ultimoAnio;
            } else {
                $anio = (int)$filtroAnio;
            }
            $condiciones[] = "YEAR(fechaCreacion) = :anio";
            $params[':anio'] = $anio;

            // Filtro por mes
            if ($filtroMes && $filtroMes !== "todos") {
                $condiciones[] = "MONTH(fechaCreacion) = :mes";
                $params[':mes'] = (int)$filtroMes;
            }

            // Filtro por usuario
            if ($filtroUsuario && $filtroUsuario !== "todos") {
                $condiciones[] = "usuario = :usuario";
                $params[':usuario'] = $filtroUsuario;
            }

            $where = count($condiciones) ? "WHERE ".implode(" AND ", $condiciones) : "";

            // Tipos y nombres de meses
            $tipos = ["Soporte", "Pase a producción", "Incidencia", "Mejora"];
            $mesesNombre = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $diaSemana = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];

            // Query
            $query = "
                SELECT 
                    usuario,
                    CASE 
                        WHEN tipo NOT IN ('MEJORA','PASE A PRODUCCION','INCIDENCIA') THEN 'Soporte'
                        ELSE tipo
                    END AS tipo,
                    fechaCreacion
                FROM SALES_SOPORTES
                $where
                ORDER BY fechaCreacion ASC
            ";
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Inicializar estructuras
            $dataTicketsPorTipo = ["total"=>array_fill_keys($tipos,0),"porMes"=>[]];
            foreach ($mesesNombre as $mes) $dataTicketsPorTipo["porMes"][$mes] = array_fill(0,count($tipos),0);

            $dataDevs = [];
            $dataMeses = [];
            foreach ($tipos as $tipo) $dataMeses[$tipo] = array_fill(0,12,0);

            $dataSoportes = [];

            foreach ($rows as $row) {
                $tipo = $row['tipo'];
                $usuario = $row['usuario'];
                $fecha = new DateTime($row['fechaCreacion']);
                $mesNombre = $mesesNombre[$fecha->format('n')-1];
                $diaHora = $fecha->format('Y-m-d'); // heatmap key
                $hora = (int)$fecha->format('G'); // 0-23

                // Tickets por Tipo
                $idx = array_search($tipo, $tipos);
                if ($idx !== false) {
                    $dataTicketsPorTipo["total"][$tipo]++;
                    $dataTicketsPorTipo["porMes"][$mesNombre][$idx]++;
                }

                // Tickets por Usuario
                if (!isset($dataDevs[$usuario])) $dataDevs[$usuario]=0;
                $dataDevs[$usuario]++;

                // Tickets por Mes y Tipo
                $dataMeses[$tipo][$fecha->format('n')-1]++;

                // Heatmap solo del último mes si no se filtró mes
                if ((!$filtroMes || $filtroMes === "todos") && $fecha->format('n') == $ultimoMes) {
                    if(!isset($dataSoportes[$diaHora])) $dataSoportes[$diaHora] = array_fill(0,24,0);
                    $dataSoportes[$diaHora][$hora]++;
                }
                // Si se filtró mes, agregar todos los días de ese mes
                elseif ($filtroMes && $filtroMes !== "todos") {
                    if(!isset($dataSoportes[$diaHora])) $dataSoportes[$diaHora] = array_fill(0,24,0);
                    $dataSoportes[$diaHora][$hora]++;
                }
            }

            // Convertir heatmap a ApexCharts
            $heatmapSeries = [];
            ksort($dataSoportes);
            foreach ($dataSoportes as $fecha => $horas) {
                $fechaObj = new DateTime($fecha);
                $nombreDia = $diaSemana[$fechaObj->format('w')];
                $nombreMes = $mesesNombre[$fechaObj->format('n') - 1];
                $x = "$nombreDia {$fechaObj->format('d')} $nombreMes";
                $heatmapSeries[] = ["name"=>$x, "data"=>$horas];
            }

            arsort($dataDevs);

            echo json_encode([
                "ticketsPorTipo"=>$dataTicketsPorTipo,
                "devs"=>$dataDevs,
                "meses"=>$dataMeses,
                "soportes"=>$heatmapSeries
            ]);

        } catch (PDOException $e) {
            Logger::logGlobal("❌ Error: ".$e->getMessage());
            http_response_code(500);
            echo json_encode(["status"=>"error","message"=>"No se pudo generar el reporte!"]);
        }
    }


}
