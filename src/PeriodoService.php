<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';

class PeriodoService {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->conectar();
    }

   public function index($estado = 'ACT') {
        Logger::logGlobal("📦 Listando periodos válidos");

        // Consulta de periodos
        $query = "SELECT x.idAfiliadoPeriodo, x.descripcion, x.stsAfiliadoPeriodo 
                FROM db_ventas_logistica.SALES_PEDIDO_AFILIADO_PERIODO x
                WHERE x.stsAfiliadoPeriodo = :estado";
        Logger::logGlobal("El query es: $query");
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':estado', $estado);
        $stmt->execute();
        $periodos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Consulta de estados
        $query1 = "SELECT idSolicitudEstado, descripcion 
                FROM SALES_SOLICITUD_ESTADO sse 
                WHERE stsSolicitudEstado = :estado";
        Logger::logGlobal("El query es: $query1");
        $stmt1 = $this->conn->prepare($query1);
        $stmt1->bindParam(':estado', $estado);
        $stmt1->execute();
        $estados = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        $query2 = "select idPedidoEstado, descripcion from SALES_PEDIDO_ESTADO spe 
                WHERE stsPedidoEstado = :estado";
        Logger::logGlobal("El query es: $query2");
        $stmt2 = $this->conn->prepare($query2);
        $stmt2->bindParam(':estado', $estado);
        $stmt2->execute();
        $estadosPedidos = $stmt2->fetchAll(PDO::FETCH_ASSOC);


        $query5 = "select idEstadoOM, descripcion from SALES_ORDEN_MEDICA_AUTOGESTION_ESTADO spe 
                WHERE stsEstado = 1";
        Logger::logGlobal("El query es: $query5");
        $stmt5 = $this->conn->prepare($query5);
        $stmt5->execute();
        $estadosOM = $stmt5->fetchAll(PDO::FETCH_ASSOC);

        $query3= "select idRangoHorario, idCanalVenta, descripcion, tipoRangoHorario from SALES_RANGO_HORARIO srh 
                WHERE stsRangoHorario = :estado";
        Logger::logGlobal("El query es: $query3");
        $stmt3 = $this->conn->prepare($query3);
        $stmt3->bindParam(':estado', $estado);
        $stmt3->execute();
        $rangosHorarios = $stmt3->fetchAll(PDO::FETCH_ASSOC);

        $query4= "select idPagoEstado, descripcion, stsPagoEstado from SALES_PAGO_ESTADO  
                WHERE stsPagoEstado = :estado";
        Logger::logGlobal("El query es: $query4");
        $stmt4 = $this->conn->prepare($query4);
        $stmt4->bindParam(':estado', $estado);
        $stmt4->execute();
        $estadoPago = $stmt4->fetchAll(PDO::FETCH_ASSOC);


        // Salida JSON
        echo json_encode([
            'periodos' => $periodos,
            'estados'  => $estados,
            'estadosOM' => $estadosOM,
            'estadosPedidos' => $estadosPedidos,
            'rangosHorarios' => $rangosHorarios,
            'estadosPago' => $estadoPago
        ]);
    }


    public function buscarPedidoPorPeriodo($tipo = 'SOLICITUD', $numero, $id) {
        Logger::logGlobal("📦 buscarPedidoPorPeriodo ---> $numero");
        Logger::logGlobal("📦 id ---> $id");
        if($tipo === 'PEDIDO') {
            $query = "SELECT sp.idAfiliadoPeriodo, spap.descripcion, sp.idPedido, sp.numeroPedido as numero,
                        sp.nombresEnvio , sp.apellidosEnvio , sp.numeroDocumentoEnvio , sp.idTipoDocumentoEnvio,
                        stdi.descripcion as descripcionDocumento, sp.idPedidoEstado, sp.canalNumeroPedido,
                        sp.fechaEnvio , sp.fechaEnvioFin , sp.idRangoHorario , sp.descripcionRangoHorario,
                        sp.idMotorizado , sm.codigoMotorizado, sp.idCanalVenta, sp.idTipoEnvio, sp.idPagoEstado, spp.orden,
                        sp.telefonoEnvio, sp.telefonoEnvio2 
                        FROM SALES_PEDIDO sp
                        LEFT JOIN SALES_PEDIDO_PROGRAMACION spp
                        ON spp.idPedido = sp.idPedido
                        LEFT JOIN SALES_MOTORIZADO sm 
                        ON sm.idMotorizado = sp.idMotorizado 
                        AND sm.stsMotorizado = 'ACT'
                        LEFT JOIN SALES_TIPO_DOCUMENTO_IDENTIDAD stdi 
                        ON stdi.idTipoDocumentoIdentidad = sp.idTipoDocumentoEnvio
                        LEFT JOIN SALES_PEDIDO_AFILIADO_PERIODO spap 
                        ON sp.idAfiliadoPeriodo = spap.idAfiliadoPeriodo 
                        ";
            if(!is_null($id) && $id != 'null') {
                $query .= "WHERE sp.idPedido = ?";
            } else {
                 $query .= "WHERE sp.numeroPedido = ?";
            }
        } else if($tipo === 'OM') {
            $query = "SELECT  
                            sp.idOMAutogestion AS idOmAutogestion, sp.numeroOM as numero,
                            sfa.nombres as nombresEnvio, sfa.apellidos as apellidosEnvio, 
                            sfa.nroDocumento as numeroDocumentoEnvio , 
                            sfa.tipoDocumento as idTipoDocumentoEnvio, sp.idOMAutogestion,
                            sp.idEstadoOM as idOmEstado, stdi.descripcion as descripcionDocumento,
                            '-' as descripcion,
                            sfa.telefono as telefonoEnvio, null as telefonoEnvio2
                        FROM SALES_ORDEN_MEDICA_AUTOGESTION sp
                        INNER JOIN SALES_FORMULARIO_AUTOGESTION sfa 
                        ON sfa.idFormularioAutogestion = sp.idFormAutogestion 
                        LEFT JOIN SALES_TIPO_DOCUMENTO_IDENTIDAD stdi 
                        ON stdi.idTipoDocumentoIdentidad = sfa.tipoDocumento 
                        ";
            if(!is_null($id) && $id != 'null') {
                $query .= "WHERE sp.idOMAutogestion = ?";                  
            } else {                                    
                $query .= "WHERE sp.numeroOM = ?";
            }
        }
        else {
            $query = "SELECT sp.idAfiliadoPeriodo, spap.descripcion, sp.idSolicitud, sp.numeroSolicitud as numero,
                        sds.nombresEnvio, sds.apellidosEnvio, sds.numeroDocumentoEnvio,
                        sds.idTipoDocumentoEnvio, stdi.descripcion as descripcionDocumento, sp.idSolicitudEstado,
                        sp.canalNumeroSolicitud, sp.idCanalVenta,
                        sds.telefonoEnvio, sds.telefonoEnvio2
                        FROM SALES_SOLICITUD sp
                        LEFT JOIN SALES_DETALLE_SOLICITUD sds 
						ON sds.idSolicitud = sp.idSolicitud 
                        LEFT JOIN SALES_TIPO_DOCUMENTO_IDENTIDAD stdi 
                        ON stdi.idTipoDocumentoIdentidad = sds.idTipoDocumentoEnvio
                        LEFT JOIN SALES_PEDIDO_AFILIADO_PERIODO spap 
                        ON sp.idAfiliadoPeriodo = spap.idAfiliadoPeriodo 
                        ";
            if(!is_null($id) && $id != 'null') {
               $query .= "WHERE sp.idSolicitud = ?";
            } else {
               $query .= "WHERE sp.numeroSolicitud = ?";
            }
        }

        Logger::logGlobal("El query es $query");
        $stmt = $this->conn->prepare($query);
        if(!is_null($id) && $id != 'null') {
            $stmt->execute([$id]);
        } else {
            $stmt->execute([$numero]);
        }
        $producto = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($producto && count($producto) > 0) {
            echo json_encode($producto);
        } else {
            http_response_code(404);
            echo json_encode(["mensaje" => "Pedido no encontrado"]);
        }
    }

    public function actualizarPeriodo($data) {
        Logger::logGlobal("✏️ Actualizando periodo $id: " . json_encode($data));
        if($data['tipo'] === 'SOLICITUD') {
            $query = "UPDATE SALES_SOLICITUD SET idAfiliadoPeriodo = IFNULL(?, idAfiliadoPeriodo) ,
            idSolicitudEstado = IFNULL(?, idSolicitudEstado),
            canalNumeroSolicitud = IFNULL(? , canalNumeroSolicitud),
            idCanalVenta = IFNULL(? ,idCanalVenta)
            WHERE idSolicitud = ?";
            $stmt->execute([$data['idAfiliadoPeriodo'], $data['idEstadoSolicitud'], $data['numeroCanal'], $data['idCanalVenta'], $data['id']]);

            $query2 = "UPDATE SALES_DETALLE_SOLICITUD
                    SET 
                    telefonoEnvio=IFNULL(?, telefonoEnvio),
                    telefonoEnvio2=IFNULL(?, telefonoEnvio2)
                    WHERE idSolicitud = ?";
            $stmt2 = $this->conn->prepare($query2);
            $stmt2->execute([$data['telefonoEnvio'], $data['telefonoEnvio2'], $data['id']]);

        } elseif ($data['tipo'] === 'OM') {
            // Actualizar la orden médica
            $query = "UPDATE SALES_ORDEN_MEDICA_AUTOGESTION SET 
                idEstadoOM = IFNULL(?, idEstadoOM), 
                nombreCompletoPaciente = IFNULL(?, nombreCompletoPaciente), 
                tipoDocumento = IFNULL(?, tipoDocumento),
                numeroDocumento = IFNULL(?, numeroDocumento)
                WHERE idOMAutogestion = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $data['idOmEstado'] ?? null, 
                trim(($data['nombresEnvio'] ?? '') . ' ' . ($data['apellidosEnvio'] ?? '')), 
                $data['idTipoDocumentoEnvio'] ?? null, 
                $data['numeroDocumentoEnvio'] ?? null, 
                $data['id'] ?? null
            ]);

            // Obtener idFormAutogestion real desde SALES_ORDEN_MEDICA_AUTOGESTION
            $stmtForm = $this->conn->prepare("SELECT idFormAutogestion FROM SALES_ORDEN_MEDICA_AUTOGESTION WHERE idOMAutogestion = ?");
            $stmtForm->execute([$data['id'] ?? null]);
            $formResult = $stmtForm->fetch(PDO::FETCH_ASSOC);
            $idFormAutogestion = $formResult['idFormAutogestion'] ?? null;

            // Actualizar formulario solo si existe idFormAutogestion
            if ($idFormAutogestion) {
                $query2 = "UPDATE SALES_FORMULARIO_AUTOGESTION SET 
                    nombres = IFNULL(?, nombres), 
                    apellidos = IFNULL(?, apellidos),
                    nroDocumento = IFNULL(?, nroDocumento), 
                    tipoDocumento = IFNULL(?, tipoDocumento)
                    WHERE idFormularioAutogestion = ?";
                
                $stmt2 = $this->conn->prepare($query2);
                $stmt2->execute([
                    $data['nombresEnvio'] ?? null, 
                    $data['apellidosEnvio'] ?? null, 
                    $data['numeroDocumentoEnvio'] ?? null, 
                    $data['idTipoDocumentoEnvio'] ?? null, 
                    $idFormAutogestion
                ]);
            }
        }
        else {
            $query = "UPDATE SALES_PEDIDO SET idAfiliadoPeriodo = IFNULL(?, idAfiliadoPeriodo),
            idPedidoEstado = IFNULL(? , idPedidoEstado),
            canalNumeroPedido= IFNULL(? ,canalNumeroPedido),
            idPagoEstado=  IFNULL(?, idPagoEstado),
            telefonoEnvio=  IFNULL(?, telefonoEnvio),
            telefonoEnvio2=  IFNULL(?, telefonoEnvio2),
            descripcionRangoHorario= IFNULL(? ,descripcionRangoHorario),
            idRangoHorario= IFNULL(? ,idRangoHorario),
            fechaEnvio= IFNULL(? ,fechaEnvio),
            fechaEnvioFin= IFNULL(? ,fechaEnvioFin),
            idTipoEnvio = IFNULL(? ,idTipoEnvio),
            idCanalVenta = IFNULL(? ,idCanalVenta)
            WHERE idPedido = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $data['idAfiliadoPeriodo'],
                $data['idEstadoPedido'], 
                $data['numeroCanal'], 
                $data['idPagoEstado'], 
                $data['telefonoEnvio'],
                $data['telefonoEnvio2'],
                $data['descripcionRangoHorario'],
                $data['idRangoHorario'],
                $data['fechaEnvio'],
                $data['fechaEnvioFin'],
                $data['idTipoEnvio'],
                $data['idCanalVenta'],
                $data['id']
            ]);

            if($data['orden'] && $data['orden'] != 'NULL') {
                $query2 = "UPDATE SALES_PEDIDO_PROGRAMACION SET orden = IFNULL(?, orden)
                WHERE idPedido = ?";
                $stmt2 = $this->conn->prepare($query2);
                $stmt2->execute([
                    $data['orden'],
                    $data['id']
                ]);
            }
        }
        Logger::logGlobal("query $query");
        echo json_encode(["mensaje" => "Periodo actualizado"]);
    }

    public function actualizarNombresEnvio($data) {
        Logger::logGlobal("✏️ Actualizando periodo");

        if($data['codigoMotorizado'] == 'NULL') {
            $query2 = "UPDATE SALES_PEDIDO SET idMotorizado = ? WHERE idPedido = ?";
            Logger::logGlobal("query $query2");
            $stmt2 = $this->conn->prepare($query2);
            $stmt2->execute([null, $data['id']]);
        }

        if($data['codigoMotorizado'] && $data['codigoMotorizado'] != 'NULL') {
            $query1 = "SELECT idMotorizado FROM SALES_MOTORIZADO WHERE codigoMotorizado = ? AND stsMotorizado = 'ACT'";
            Logger::logGlobal("query $query1");
            $stmt1 = $this->conn->prepare($query1);
            $stmt1->execute([$data['codigoMotorizado']]);
            $motorizadoEncontrado = $stmt1->fetch(PDO::FETCH_ASSOC);

            if($motorizadoEncontrado) {
                $query2 = "UPDATE SALES_PEDIDO SET idMotorizado = IFNULL(?, idMotorizado) WHERE idPedido = ?";
                Logger::logGlobal("query $query2");
                $stmt2 = $this->conn->prepare($query2);
                $stmt2->execute([$motorizadoEncontrado['idMotorizado'], $data['id']]);
            }

        }
        
        if($data['tipo'] === 'SOLICITUD') {
            $query1 = "UPDATE SALES_SOLICITUD SET idAfiliadoPeriodo = IFNULL(?, idAfiliadoPeriodo) ,
            idSolicitudEstado = IFNULL(?, idSolicitudEstado),
            canalNumeroSolicitud = IFNULL(? , canalNumeroSolicitud),
            idCanalVenta = IFNULL(? ,idCanalVenta)
            WHERE idSolicitud = ?";
            Logger::logGlobal("query $query1");
            $stmt1 = $this->conn->prepare($query1);
            $stmt1->execute([
                $data['idAfiliadoPeriodo'],
                $data['idEstadoSolicitud'],
                $data['numeroCanal'], 
                $data['idCanalVenta'], 
                $data['id']
            ]);

            $query3 = "UPDATE SALES_DETALLE_SOLICITUD
            SET 
            telefonoEnvio=IFNULL(?, telefonoEnvio),
            telefonoEnvio2=IFNULL(?, telefonoEnvio2)
            WHERE idSolicitud = ?";
            Logger::logGlobal("query $query3");
            $stmt3 = $this->conn->prepare($query3);
            $stmt3->execute([$data['telefonoEnvio'], $data['telefonoEnvio2'], $data['id']]);
            
        } else if ($data['tipo'] === 'OM') {
            // Actualizar orden médica
            $query1 = "UPDATE SALES_ORDEN_MEDICA_AUTOGESTION SET 
                idEstadoOM = IFNULL(?, idEstadoOM), 
                nombreCompletoPaciente = IFNULL(?, nombreCompletoPaciente), 
                tipoDocumento = IFNULL(?, tipoDocumento),
                numeroDocumento = IFNULL(?, numeroDocumento)
                WHERE idOMAutogestion = ?";
            
            Logger::logGlobal("query $query1");
            $stmt1 = $this->conn->prepare($query1);
            $stmt1->execute([
                $data['idOmEstado'] ?? null, 
                trim(($data['nombresEnvio'] ?? '') . ' ' . ($data['apellidosEnvio'] ?? '')), 
                $data['idTipoDocumentoEnvio'] ?? null, 
                $data['numeroDocumentoEnvio'] ?? null, 
                $data['id'] ?? null
            ]);

            // Actualizar formulario autogestión usando idFormAutogestion real
            $stmtForm = $this->conn->prepare("SELECT idFormAutogestion FROM SALES_ORDEN_MEDICA_AUTOGESTION WHERE idOMAutogestion = ?");
            $stmtForm->execute([$data['id'] ?? null]);
            $formResult = $stmtForm->fetch(PDO::FETCH_ASSOC);
            $idFormAutogestion = $formResult['idFormAutogestion'] ?? null;
            Logger::logGlobal("formResult -->" . json_encode($formResult));


            if ($idFormAutogestion) {
                $query2 = "UPDATE SALES_FORMULARIO_AUTOGESTION SET 
                    nombres = IFNULL(?, nombres), 
                    apellidos = IFNULL(?, apellidos),
                    nroDocumento = IFNULL(?, nroDocumento), 
                    tipoDocumento = IFNULL(?, tipoDocumento),
                    telefono = IFNULL(?, telefono)
                    WHERE idFormularioAutogestion = ?";
                
                Logger::logGlobal("query $query2");
                $stmt2 = $this->conn->prepare($query2);
                $stmt2->execute([
                    $data['nombresEnvio'] ?? null, 
                    $data['apellidosEnvio'] ?? null, 
                    $data['numeroDocumentoEnvio'] ?? null, 
                    $data['idTipoDocumentoEnvio'] ?? null, 
                    $data['telefonoEnvio'] ?? null,
                    $idFormAutogestion
                ]);
            }
        }
        else {
            $query1 = "UPDATE SALES_PEDIDO SET idAfiliadoPeriodo = IFNULL(?, idAfiliadoPeriodo),
            idPedidoEstado = IFNULL(? , idPedidoEstado),
            canalNumeroPedido= IFNULL(? ,canalNumeroPedido),
            idPagoEstado=  IFNULL(?, idPagoEstado),
            descripcionRangoHorario= IFNULL(? ,descripcionRangoHorario),
            idRangoHorario= IFNULL(? ,idRangoHorario),
            fechaEnvio= IFNULL(? ,fechaEnvio),
            fechaEnvioFin= IFNULL(? ,fechaEnvioFin),
            idTipoEnvio = IFNULL(? ,idTipoEnvio),
            telefonoEnvio = IFNULL(? ,telefonoEnvio),
            telefonoEnvio2 = IFNULL(? ,telefonoEnvio2),
            idCanalVenta = IFNULL(? ,idCanalVenta)
            WHERE idPedido = ?";
            Logger::logGlobal("query $query1");
            $stmt1 = $this->conn->prepare($query1);
            $stmt1->execute([
                $data['idAfiliadoPeriodo'],
                $data['idEstadoPedido'], 
                $data['numeroCanal'], 
                $data['idPagoEstado'], 
                $data['descripcionRangoHorario'],
                $data['idRangoHorario'],
                $data['fechaEnvio'],
                $data['fechaEnvioFin'],
                $data['idTipoEnvio'],
                $data['telefonoEnvio'],
                $data['telefonoEnvio2'],
                $data['idCanalVenta'],
                $data['id']
            ]);

        }
        
        Logger::logGlobal("✏️ Actualizando nombres " . json_encode($data));
        if($data['tipo'] === 'SOLICITUD') {
            $query = "UPDATE SALES_DETALLE_SOLICITUD
                    SET nombresEnvio=IFNULL(?, nombresEnvio),
                    apellidosEnvio=IFNULL(?, apellidosEnvio),
                    numeroDocumentoEnvio=IFNULL(?, numeroDocumentoEnvio),
                    telefonoEnvio=IFNULL(?, telefonoEnvio),
                    telefonoEnvio2=IFNULL(?, telefonoEnvio2),
                    idTipoDocumentoEnvio=IFNULL(?, idTipoDocumentoEnvio)
                    WHERE idSolicitud = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$data['nombresEnvio'], $data['apellidosEnvio'], $data['numeroDocumentoEnvio'], $data['telefonoEnvio'], $data['telefonoEnvio2'], $data['idTipoDocumentoEnvio'], $data['id']]);
        } else if ($data['tipo'] === 'OM') {
            // Actualizar orden médica
            $query = "UPDATE SALES_ORDEN_MEDICA_AUTOGESTION
                SET nombreCompletoPaciente = IFNULL(?, nombreCompletoPaciente),
                    numeroDocumento = IFNULL(?, numeroDocumento),
                    tipoDocumento = IFNULL(?, tipoDocumento)
                WHERE idOMAutogestion = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                trim(($data['nombresEnvio'] ?? '') . ' ' . ($data['apellidosEnvio'] ?? '')),
                $data['numeroDocumentoEnvio'] ?? null,
                $data['idTipoDocumentoEnvio'] ?? null,
                $data['id'] ?? null
            ]);

            // Obtener idFormAutogestion real
            $stmtForm = $this->conn->prepare("SELECT idFormAutogestion FROM SALES_ORDEN_MEDICA_AUTOGESTION WHERE idOMAutogestion = ?");
            $stmtForm->execute([$data['id'] ?? null]);
            $formResult = $stmtForm->fetch(PDO::FETCH_ASSOC);
            Logger::logGlobal("formResult -->" . json_encode($formResult));
            $idFormAutogestion = $formResult['idFormAutogestion'] ?? null;

            // Actualizar formulario solo si existe idFormAutogestion
            if ($idFormAutogestion) {
                $query2 = "UPDATE SALES_FORMULARIO_AUTOGESTION
                    SET nombres = IFNULL(?, nombres),
                        apellidos = IFNULL(?, apellidos),
                        nroDocumento = IFNULL(?, nroDocumento),
                        tipoDocumento = IFNULL(?, tipoDocumento)
                    WHERE idFormularioAutogestion = ?";
                
                $stmt2 = $this->conn->prepare($query2);
                $stmt2->execute([
                    $data['nombresEnvio'] ?? null,
                    $data['apellidosEnvio'] ?? null,
                    $data['numeroDocumentoEnvio'] ?? null,
                    $data['idTipoDocumentoEnvio'] ?? null,
                    $idFormAutogestion
                ]);
            }
        }
        else {
            $query = "UPDATE SALES_PEDIDO 
                    SET nombresEnvio=IFNULL(?, nombresEnvio),
                    apellidosEnvio=IFNULL(?, apellidosEnvio),
                    telefonoEnvio=IFNULL(?, telefonoEnvio),
                    telefonoEnvio2=IFNULL(?, telefonoEnvio2),
                    numeroDocumentoEnvio=IFNULL(?, numeroDocumentoEnvio),
                    idTipoDocumentoEnvio=IFNULL(?, idTipoDocumentoEnvio)
                    WHERE idPedido = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$data['nombresEnvio'], $data['apellidosEnvio'], $data['telefonoEnvio'], $data['telefonoEnvio2'], $data['numeroDocumentoEnvio'], $data['idTipoDocumentoEnvio'], $data['id']]);
        }

        if($data['orden'] && $data['orden'] != 'NULL') {
            $query2 = "UPDATE SALES_PEDIDO_PROGRAMACION SET orden = IFNULL(?, orden)
            WHERE idPedido = ?";
            $stmt2 = $this->conn->prepare($query2);
            $stmt2->execute([
                $data['orden'],
                $data['id']
            ]);
        }
        echo json_encode(["mensaje" => "Nombres actualizado"]);
    } 

}
