<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';

class SoporteController {
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

    public function obtenerReporte($filtroMes = null, $filtroAnio = null, $filtroUsuario = null) {
    try {
        Logger::logGlobal("📦 Generando reporte de soportes");

        $params = [];
        $condiciones = [];

        $hoy = new DateTime();
        $ultimoMes = (int)$hoy->format('m');
        $ultimoAnio = (int)$hoy->format('Y');

        // Filtros
        if (!$filtroAnio || $filtroAnio === "todos") {
            $anio = $ultimoAnio;
        } else {
            $anio = (int)$filtroAnio;
        }
        $condiciones[] = "YEAR(fechaCreacion) = :anio";
        $params[':anio'] = $anio;

        if ($filtroMes && $filtroMes !== "todos") {
            $condiciones[] = "MONTH(fechaCreacion) = :mes";
            $params[':mes'] = (int)$filtroMes;
        }

        if ($filtroUsuario && $filtroUsuario !== "todos") {
            $condiciones[] = "usuario = :usuario";
            $params[':usuario'] = $filtroUsuario;
        }

        $where = count($condiciones) ? "WHERE ".implode(" AND ", $condiciones) : "";

        // Tipos y nombres
        $tipos = ["Soporte", "Pase a producción", "Incidencia", "Mejora"];
        $mesesNombre = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $diaSemana = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];

        // Query normalizada (para gráficos agrupados)
        $query = "
            SELECT 
                usuario,
                CASE 
                    WHEN tipo NOT IN ('MEJORA','PASE A PRODUCCION','INCIDENCIA') THEN 'Soporte'
                    WHEN tipo = 'MEJORA' THEN 'Mejora'
                    WHEN tipo = 'PASE A PRODUCCION' THEN 'Pase a producción'
                    WHEN tipo = 'INCIDENCIA' THEN 'Incidencia'
                END AS tipo,
                fechaCreacion
            FROM SALES_SOPORTES
            $where
            ORDER BY fechaCreacion ASC;
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Query RAW (sin normalizar) — la usamos para ticketsPorTodosLosTipos y tiposPorDia
        $queryRaw = "
            SELECT usuario, tipo, fechaCreacion
            FROM SALES_SOPORTES
            $where
            ORDER BY fechaCreacion ASC;
        ";
        $stmtRaw = $this->conn->prepare($queryRaw);
        $stmtRaw->execute($params);
        $rowsRaw = $stmtRaw->fetchAll(PDO::FETCH_ASSOC);

        // ======== GRÁFICOS EXISTENTES (a partir de la query normalizada) ========
        $dataTicketsPorTipo = ["total"=>array_fill_keys($tipos,0),"porMes"=>[]];
        foreach ($mesesNombre as $mes) $dataTicketsPorTipo["porMes"][$mes] = array_fill(0,count($tipos),0);

        $dataDevs = [];
        $dataMeses = [];
        foreach ($tipos as $tipo) $dataMeses[$tipo] = array_fill(0,12,0);

        $dataSoportes = [];
        $mapTipos = array_flip($tipos); // para evitar array_search en cada iteración

        foreach ($rows as $row) {
            $tipo = $row['tipo'];
            $usuario = $row['usuario'];
            $fecha = new DateTime($row['fechaCreacion']);
            $mesNombre = $mesesNombre[$fecha->format('n')-1];
            $diaHora = $fecha->format('Y-m-d');
            $hora = (int)$fecha->format('G');

            $idx = $mapTipos[$tipo] ?? null;
            if ($idx !== null) {
                $dataTicketsPorTipo["total"][$tipo]++;
                $dataTicketsPorTipo["porMes"][$mesNombre][$idx]++;
            }

            if (!isset($dataDevs[$usuario])) $dataDevs[$usuario] = 0;
            $dataDevs[$usuario]++;

            if (isset($dataMeses[$tipo])) $dataMeses[$tipo][$fecha->format('n')-1]++;

            if ((!$filtroMes || $filtroMes === "todos") && $fecha->format('n') == $ultimoMes) {
                if(!isset($dataSoportes[$diaHora])) $dataSoportes[$diaHora] = array_fill(0,24,0);
                $dataSoportes[$diaHora][$hora]++;
            } elseif ($filtroMes && $filtroMes !== "todos") {
                if(!isset($dataSoportes[$diaHora])) $dataSoportes[$diaHora] = array_fill(0,24,0);
                $dataSoportes[$diaHora][$hora]++;
            }
        }

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

        // ======== CORRECCIÓN: tiposPorDia (A PARTIR DE LA QUERY RAW) ========
        $tiposPorDia = [];
        foreach ($rowsRaw as $row) {
            $tipoRaw = $row['tipo'];
            $fechaYmd = (new DateTime($row['fechaCreacion']))->format('Y-m-d');

            if (!isset($tiposPorDia[$tipoRaw])) $tiposPorDia[$tipoRaw] = [];
            if (!isset($tiposPorDia[$tipoRaw][$fechaYmd])) $tiposPorDia[$tipoRaw][$fechaYmd] = 0;
            $tiposPorDia[$tipoRaw][$fechaYmd]++;
        }

        // ======== NUEVO: ticketsPorTodosLosTipos (también a partir de rowsRaw) ========
        $ticketsPorTodosLosTipos = ["total"=>[],"porMes"=>[]];
        foreach ($rowsRaw as $row) {
            $tipoRaw = $row['tipo'];
            $fecha = new DateTime($row['fechaCreacion']);
            $mesNombre = $mesesNombre[$fecha->format('n')-1];

            if (!isset($ticketsPorTodosLosTipos["total"][$tipoRaw])) $ticketsPorTodosLosTipos["total"][$tipoRaw] = 0;
            if (!isset($ticketsPorTodosLosTipos["porMes"][$mesNombre])) $ticketsPorTodosLosTipos["porMes"][$mesNombre] = [];
            if (!isset($ticketsPorTodosLosTipos["porMes"][$mesNombre][$tipoRaw])) $ticketsPorTodosLosTipos["porMes"][$mesNombre][$tipoRaw] = 0;

            $ticketsPorTodosLosTipos["total"][$tipoRaw]++;
            $ticketsPorTodosLosTipos["porMes"][$mesNombre][$tipoRaw]++;
        }

        // ======== Respuesta final ========
        echo json_encode([
            "ticketsPorTipo" => $dataTicketsPorTipo,
            "ticketsPorTodosLosTipos" => $ticketsPorTodosLosTipos,
            "devs" => $dataDevs,
            "meses" => $dataMeses,
            "soportes" => $heatmapSeries,
            "tiposPorDia" => $tiposPorDia
        ]);

    } catch (PDOException $e) {
        Logger::logGlobal("❌ Error: ".$e->getMessage());
        http_response_code(500);
        echo json_encode(["status"=>"error","message"=>"No se pudo generar el reporte!"]);
    }
}


}
