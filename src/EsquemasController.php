<?php
require_once __DIR__ . '/../config/database2.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/ProductController.php';

class EsquemasController {
    private $conn;

    public function __construct() {
        $database = new Database2();
        $this->conn = $database->conectar();
    }

   public function index($estado = 'ACT') {
        Logger::logGlobal("📦 Listando de estados válidos");

        // Consulta de estados
        $query1 = "select idEstadoInterno, descripcion  from PSP_ESTADO pe 
        WHERE pe.stsEstado = :estado AND pe.idCanalVenta = 2";
        Logger::logGlobal("El query es: $query1");
        $stmt1 = $this->conn->prepare($query1);
        $stmt1->bindParam(':estado', $estado);
        $stmt1->execute();
        $listadoEstados = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        //Canales
        $query2 = "SELECT idCanalVenta, descripcion FROM PSP_PBI_CANAL_VENTA pcv WHERE pcv.stsCanalVenta = 'ACT'";
        Logger::logGlobal("El query es: $query2");
        $stmt2 = $this->conn->prepare($query2);
        $stmt2->bindParam(':estado', $estado);
        $stmt2->execute();
        $canales = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        //Diagnosticos
        $query3 = "SELECT idCIE10, dscCIE10 FROM PSP_CIE10 pc WHERE indEliminado = 'N'";
        Logger::logGlobal("El query es: $query3");
        $stmt3 = $this->conn->prepare($query3);
        $stmt3->bindParam(':estado', $estado);
        $stmt3->execute();
        $diagnosticos = $stmt3->fetchAll(PDO::FETCH_ASSOC);

        //Frecuencias
        $query4 = "SELECT idFrecuencia, descripcion FROM PSP_FRECUENCIA pf WHERE stsFrecuencia = 'ACT'";
        Logger::logGlobal("El query es: $query4");
        $stmt4 = $this->conn->prepare($query4);
        $stmt4->bindParam(':estado', $estado);
        $stmt4->execute();
        $frecuencias = $stmt4->fetchAll(PDO::FETCH_ASSOC);

        // Salida JSON
        echo json_encode([
            'listadoEstados' => $listadoEstados,
            'canales' => $canales,
            'diagnosticos' => $diagnosticos,
            'frecuencias' => $frecuencias
        ]);
    }


    public function buscarEsquema($numero) {
        Logger::logGlobal("📦 buscarEsquema ---> $numero");

        $query = "SELECT * FROM (SELECT pc.idPaciente, ppd.idPacienteDiagnostico, ppef2.idPacienteSeguimiento, ppef2.idPacienteEstado,  pp.numeroDocumento,
        ppr.sku, ptd.idTipoDocumento , ptd.descripcion descTipoDocumento, pp.nombre, pp.apellidos, pp.celular, pp.apePaterno, pp.apeMaterno,
        pspe.idEstadoInterno idEstado, pspe.descripcion descStatus, pspe.idEstadoInterno, pte.idTratamientoEstado, pte.descripcion descEstadoTratamiento,
        CASE
        WHEN pspe.idEstado in (2,3,4) AND pte.idTratamientoEstado NOT IN(2,3) THEN DATEDIFF(NOW(),pps.fechaCreacion)
        WHEN pspe.idEstado in (1,5,6,7) OR pte.idTratamientoEstado IN(3) THEN null
        END diasPasados,
        CASE
        WHEN pspe.idEstado in (2,3,4) AND pte.idTratamientoEstado NOT IN(2,3) THEN (CASE
                                                WHEN DATEDIFF(NOW(),pps.fechaCreacion) < 3 THEN 'VERDE'
                                                WHEN DATEDIFF(NOW(),pps.fechaCreacion) < 5 THEN 'NARANJA'
                                                WHEN DATEDIFF(NOW(),pps.fechaCreacion) >= 5 THEN 'ROJO'
                                            END)
        WHEN pspe.idEstado in (1,5,6,7) OR pte.idTratamientoEstado IN(3) THEN null
        END alertaDias,
        CASE
        WHEN pspe.idEstadoInterno in (1,6,7, 13) AND pte.idTratamientoEstado NOT IN(3) THEN DATE_FORMAT(pps.fechaProximoContacto, '%Y-%m-%d')
        WHEN pspe.idEstadoInterno in (5) AND pte.idTratamientoEstado NOT IN(3) THEN DATE_FORMAT(COALESCE(COALESCE(pps.fechaProgramada, fechaAplicacion),pps.fechaProximoContacto), '%Y-%m-%d')   
        WHEN pspe.idEstadoInterno in (4) AND pte.idTratamientoEstado NOT IN(3) AND pps.idAccionSugerida in(6) THEN DATE_FORMAT(pps.fechaProgramada, '%Y-%m-%d')
        WHEN pspe.idEstadoInterno in (4) AND pte.idTratamientoEstado NOT IN(3) AND pps.idAccionSugerida in(1,3,5) THEN DATE_FORMAT(pps.fechaProximoContacto, '%Y-%m-%d')
        WHEN pspe.idEstadoInterno in (4) AND pte.idTratamientoEstado NOT IN(3) AND pps.idAccionSugerida in(2) THEN DATE_FORMAT(pps.fechaContactoFarmacia, '%Y-%m-%d')
        WHEN pspe.idEstadoInterno in (3) AND pte.idTratamientoEstado NOT IN(3) AND pps.idAccionSugerida in(1,2,3) THEN DATE_FORMAT(pps.fechaProximoContacto, '%Y-%m-%d')
        WHEN pspe.idEstadoInterno in (2) AND pte.idTratamientoEstado NOT IN(3) AND pps.idAccionSugerida in(1,2,3) AND pspe.idCanalVenta = 2 THEN DATE_FORMAT(pps.fechaProximoContacto, '%Y-%m-%d')
        WHEN pspe.idEstadoInterno in (2,3) OR pte.idTratamientoEstado IN(3) THEN null
        END llamarPaciente,
        pci.idCIE10 cie10, pps.fechaCreacion, ppd.fechaCreacion fecCreacion,
        pps.idAccionSugerida, pas.descripcion descAccionSugerida,
        (SELECT GROUP_CONCAT(pp.sku SEPARATOR '; ') as descProductos FROM PSP_PACIENTE_DIAGNOSTICO_PRODUCTO ppdp
                        INNER JOIN PSP_PRODUCTO pp ON ppdp.idProducto = pp.idProducto
            WHERE ppdp.idPacienteDiagnostico = ppd.idPacienteDiagnostico
            GROUP BY idPacienteDiagnostico) as grupo_sku,
        (SELECT GROUP_CONCAT(pp.descripcion SEPARATOR '; ') as descProductos FROM PSP_PACIENTE_DIAGNOSTICO_PRODUCTO ppdp
                        INNER JOIN PSP_PRODUCTO pp ON ppdp.idProducto = pp.idProducto
            WHERE ppdp.idPacienteDiagnostico = ppd.idPacienteDiagnostico
            GROUP BY idPacienteDiagnostico) as grupo_productos,
        (SELECT GROUP_CONCAT(ppdp.unidad SEPARATOR '; ') as descProductos FROM PSP_PACIENTE_DIAGNOSTICO_PRODUCTO ppdp                    
            WHERE ppdp.idPacienteDiagnostico = ppd.idPacienteDiagnostico
            GROUP BY idPacienteDiagnostico) as grupo_unidad,
        (SELECT GROUP_CONCAT(pp.idPresentacion SEPARATOR '; ') as idsPresentacion FROM PSP_PACIENTE_DIAGNOSTICO_PRODUCTO ppdp           
                        INNER JOIN PSP_PRODUCTO pp ON ppdp.idProducto = pp.idProducto
            WHERE ppdp.idPacienteDiagnostico = ppd.idPacienteDiagnostico
            GROUP BY idPacienteDiagnostico) as grupo_presentacion,
        (SELECT idFrecuencia FROM PSP_PACIENTE_DIAGNOSTICO_PRODUCTO
            WHERE idPacienteDiagnostico = ppd.idPacienteDiagnostico
            order by idFrecuencia 
            limit 1) idFrecuencia,
        pci.dscCIE10Estandar descCie10, pesq.idSubCanalVenta, pps.idRangoHorario, prh.descripcion descRangoHorario,
        pc.usuarioCreacion, ppd.idCanalVenta
        FROM PSP_PACIENTE pc
        INNER JOIN PSP_PERSONA pp ON pp.idPersona = pc.idPersona
        INNER JOIN PSP_PACIENTE_DIAGNOSTICO ppd ON ppd.idPaciente = pc.idPaciente
        LEFT JOIN PSP_PRODUCTO ppr ON ppd.idProducto = ppr.idProducto
        INNER JOIN PSP_TIPO_DOCUMENTO ptd ON ptd.idTipoDocumento = pp.idTipoDocumento
        LEFT JOIN (SELECT idPacienteDiagnostico, MAX(idPacienteEstado) maxIdPacienteEstado
        FROM PSP_PACIENTE_ESTADO
        GROUP BY idPacienteDiagnostico
        ) ppef ON ppef.idPacienteDiagnostico = ppd.idPacienteDiagnostico
        LEFT JOIN PSP_PACIENTE_ESTADO ppef2 ON ppef.maxIdPacienteEstado = ppef2.idPacienteEstado
        LEFT JOIN PSP_ESTADO pspe ON ppef2.idEstado = pspe.idEstadoInterno AND pspe.idCanalVenta = 2
        LEFT JOIN (SELECT idPacienteDiagnostico, MAX(idPacienteTratamientoEstado) maxIdPacienteTratamientoEstado
        FROM PSP_PACIENTE_TRATAMIENTO_ESTADO
        GROUP BY idPacienteDiagnostico
        ) ppetf ON ppetf.idPacienteDiagnostico = ppd.idPacienteDiagnostico
        INNER JOIN PSP_PACIENTE_TRATAMIENTO_ESTADO ppetf2 ON ppetf.maxIdPacienteTratamientoEstado = ppetf2.idPacienteTratamientoEstado
        INNER JOIN PSP_TRATAMIENTO_ESTADO pte ON pte.idTratamientoEstado = ppetf2.idTratamientoEstado
        LEFT JOIN (SELECT idPacienteDiagnostico, MAX(idPacienteSeguimiento) maxIdPacienteSeguimiento
        FROM PSP_PACIENTE_SEGUIMIENTO
        GROUP BY idPacienteDiagnostico
        ) ppst ON ppst.idPacienteDiagnostico  = ppd.idPacienteDiagnostico
        LEFT JOIN PSP_PACIENTE_SEGUIMIENTO pps ON pps.idPacienteSeguimiento = ppst.maxIdPacienteSeguimiento
        LEFT JOIN PSP_ACCION_SUGERIDA pas ON pps.idAccionSugerida = pas.idAccionSugerida
        LEFT JOIN PSP_ESQUEMA pesq ON ppd.idEsquema = pesq.idEsquema
        LEFT JOIN PSP_CIE10 pci ON pesq.idCIE10 = pci.idCIE10
        LEFT JOIN PSP_RANGO_HORARIO prh ON prh.idRangoHorario = pps.idRangoHorario ) AS tb
        WHERE 1 = 1
        AND ('%$numero%' IS NULL OR (
            upper(numeroDocumento) LIKE upper('%$numero%')
            OR (upper(concat(nombre,' ', apellidos)) LIKE upper('%$numero%'))
            OR upper(concat(cie10)) LIKE upper('%$numero%')
            OR upper(concat(grupo_sku)) LIKE upper('%$numero%')
        ))
        AND idCanalVenta = 2
        ORDER BY idTratamientoEstado, fecCreacion desc,(DATEDIFF(NOW(),fecCreacion)) desc
        LIMIT 0, 50";

        Logger::logGlobal("El query es $query");
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $esquemas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($esquemas && count($esquemas) > 0) {
            echo json_encode($esquemas);
        } else {
            http_response_code(404);
            echo json_encode(["mensaje" => "Pedido no encontrado"]);
        }
    }

    public function actualizarEsquema($data) {
        Logger::logGlobal("✏️ Actualizando esquema" . json_encode($data));
        $query = "UPDATE PSP_PACIENTE_ESTADO SET idEstado = IFNULL(?, idEstado) where idPacienteDiagnostico = ? and idPacienteSeguimiento = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $data['idEstado'],
            $data['idPacienteDiagnostico'] ?? null, 
            $data['idPacienteSeguimiento'] ?? null
        ]);
        Logger::logGlobal("query $query");
        echo json_encode(["mensaje" => "Esquema actualizado"]);
    }

    public function registrarEsquema($data) {
        Logger::logGlobal("✏️ RegistrarEsquema " . json_encode($data));

        try {
            $this->conn->beginTransaction();

            // 1. Insertar esquema
            $query = "INSERT INTO db_psp_sma.PSP_ESQUEMA 
                        (idSubCanalVenta, idCIE10, stsEsquema, idFrecuencia)
                    VALUES (?, ?, 'ACT', ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $data['idSubCanalVenta'] ?? null,
                $data['idCIE10'] ?? null,
                $data['idFrecuencia'] ?? null
            ]);

            $idEsquema = $this->conn->lastInsertId();
            Logger::logGlobal("✅ Esquema registrado correctamente (ID: $idEsquema)");

            // 2. Obtener productos activos
            $productos = [];
            $productosNoRegistrados = [];

            if (!empty($data['skus']) && is_array($data['skus'])) {
                $placeholders = implode(',', array_fill(0, count($data['skus']), '?'));
                $query1 = "SELECT idProducto, idPresentacion, sku 
                            FROM PSP_PRODUCTO 
                            WHERE sku IN ($placeholders) AND stsProducto = 'ACT'";
                $stmt1 = $this->conn->prepare($query1);
                $stmt1->execute($data['skus']);
                $productos = $stmt1->fetchAll(PDO::FETCH_ASSOC);

                $skusEncontrados = array_column($productos, 'sku');
                foreach ($data['skus'] as $sku) {
                    if (!in_array($sku, $skusEncontrados)) {
                        $productosNoRegistrados[] = $sku;
                    }
                }
            }

            Logger::logGlobal("✅ productos =>". json_encode($productos));
            // 3. Si hay productos no registrados, obtener sus datos y registrarlos
            if (!empty($productosNoRegistrados)) {
                $controller = new ProductController();

                Logger::logGlobal("✅ productosNoRegistrados =>". json_encode($productosNoRegistrados));

                $productosExternos = $controller->obtenerProductoXSku($productosNoRegistrados);
                Logger::logGlobal("✅ productosExternos =>". json_encode($productosExternos));

                if (!empty($productosExternos)) {
                    $values = [];
                    foreach ($productosExternos as $p) {
                        $sku = $p['sku'];
                        $desc = addslashes($p['descripcion']);
                        $idPres = $p['idPresentacion'] ?? 'NULL';
                        $values[] = "('$sku', '$desc', 2, 'ACT', 'SYSTEM', NOW(), $idPres)";
                    }

                    $query5 = "INSERT INTO db_psp_sma.PSP_PRODUCTO
                                (sku, descripcion, idLaboratorio, stsProducto, usuarioCreacion, fechaCreacion, idPresentacion)
                                VALUES " . implode(',', $values);
                    $stmt5 = $this->conn->prepare($query5);
                    $stmt5->execute();
                    Logger::logGlobal("✅ Productos registrados correctamente: $query5");
                }
            }

            // 4. Reobtener todos los productos después de registrar los nuevos
            if (!empty($data['skus']) && is_array($data['skus'])) {
                $placeholders = implode(',', array_fill(0, count($data['skus']), '?'));
                $query1 = "SELECT idProducto, idPresentacion, sku 
                            FROM PSP_PRODUCTO 
                            WHERE sku IN ($placeholders) AND stsProducto = 'ACT'";
                $stmt1 = $this->conn->prepare($query1);
                $stmt1->execute($data['skus']);
                $productos = $stmt1->fetchAll(PDO::FETCH_ASSOC);
            }

            // 5. Actualizar productos sin presentacion
            $productosSinPresentacion = array_filter($productos, fn($p) => empty($p['idPresentacion']));
            if (!empty($productosSinPresentacion)) {
                $idsActualizar = array_column($productosSinPresentacion, 'idProducto');
                $placeholders = implode(',', array_fill(0, count($idsActualizar), '?'));
                $query2 = "UPDATE PSP_PRODUCTO SET idPresentacion = 1 WHERE idProducto IN ($placeholders)";
                $stmt2 = $this->conn->prepare($query2);
                $stmt2->execute($idsActualizar);
                Logger::logGlobal("✅ Productos actualizados correctamente");
            }

            // 6. Insertar en PSP_ESQUEMA_PRODUCTO
            if (!empty($productos) && !empty($data['productos'])) {
                $mapProductos = [];
                foreach ($productos as $p) {
                    $mapProductos[$p['sku']] = $p['idProducto'];
                }

                $values = [];
                $params = [];
                foreach ($data['productos'] as $prod) {
                    $sku = $prod['sku'];
                    $cantidad = $prod['cantidad'] ?? 1;
                    if (isset($mapProductos[$sku])) {
                        $values[] = "(?, ?, ?, NOW(), NULL, 'ACT')";
                        array_push($params, $idEsquema, $mapProductos[$sku], $cantidad);
                    }
                }

                if (!empty($values)) {
                    $query3 = "INSERT INTO db_psp_sma.PSP_ESQUEMA_PRODUCTO
                                (idEsquema, idProducto, cantidad, fechaCreacion, fechaModificacion, stsTratamientoProducto)
                            VALUES " . implode(', ', $values);
                    $stmt3 = $this->conn->prepare($query3);
                    $stmt3->execute($params);
                    Logger::logGlobal("✅ Productos agregados al esquema");
                }
            }

            $this->conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Esquema y productos registrados correctamente',
                'idEsquema' => $idEsquema
            ]);

        } catch (PDOException $e) {
            $this->conn->rollBack();
            Logger::logError("❌ Error al registrar esquema: " . $e->getMessage());

            echo json_encode([
                'success' => false,
                'message' => 'Error al registrar esquema',
                'error' => $e->getMessage()
            ]);
        }
    }

}
