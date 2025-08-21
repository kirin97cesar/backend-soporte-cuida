<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';

class PeriodoController {
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
                        sp.idMotorizado , sm.codigoMotorizado, sp.idCanalVenta, sp.idTipoEnvio, sp.idPagoEstado
                        FROM SALES_PEDIDO sp
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
        } else {
            $query = "SELECT sp.idAfiliadoPeriodo, spap.descripcion, sp.idSolicitud, sp.numeroSolicitud as numero,
                        sds.nombresEnvio, sds.apellidosEnvio, sds.numeroDocumentoEnvio,
                        sds.idTipoDocumentoEnvio, stdi.descripcion as descripcionDocumento, sp.idSolicitudEstado,
                        sp.canalNumeroSolicitud, sp.idCanalVenta
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
        } else {
            $query = "UPDATE SALES_PEDIDO SET idAfiliadoPeriodo = IFNULL(?, idAfiliadoPeriodo),
            idPedidoEstado = IFNULL(? , idPedidoEstado),
            canalNumeroPedido= IFNULL(? ,canalNumeroPedido),
            idPagoEstado=  IFNULL(?, idPagoEstado),
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
                $data['descripcionRangoHorario'],
                $data['idRangoHorario'],
                $data['fechaEnvio'],
                $data['fechaEnvioFin'],
                $data['idTipoEnvio'],
                $data['idCanalVenta'],
                $data['id']
            ]);
        }
        Logger::logGlobal("query $query");
        echo json_encode(["mensaje" => "Periodo actualizado"]);
    }

    public function actualizarNombresEnvio($data) {
        Logger::logGlobal("✏️ Actualizando periodo $id: " . json_encode($data));

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
            canalNumeroSolicitud = IFNULL(? , canalNumeroSolicitud)
            WHERE idSolicitud = ?";
            Logger::logGlobal("query $query1");
            $stmt1 = $this->conn->prepare($query1);
            $stmt1->execute([$data['idAfiliadoPeriodo'], $data['idEstadoSolicitud'], $data['numeroCanal'], $data['id']]);
            
        } else {
            $query1 = "UPDATE SALES_PEDIDO SET idAfiliadoPeriodo = IFNULL(?, idAfiliadoPeriodo),
            idPedidoEstado = IFNULL(? , idPedidoEstado),
            canalNumeroPedido= IFNULL(? ,canalNumeroPedido),
            idPagoEstado=  IFNULL(?, idPagoEstado),
            descripcionRangoHorario= IFNULL(? ,descripcionRangoHorario),
            idRangoHorario= IFNULL(? ,idRangoHorario),
            fechaEnvio= IFNULL(? ,fechaEnvio),
            fechaEnvioFin= IFNULL(? ,fechaEnvioFin),
            idTipoEnvio = IFNULL(? ,idTipoEnvio),
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
                    idTipoDocumentoEnvio=IFNULL(?, idTipoDocumentoEnvio)
                    WHERE idSolicitud = ?";
        } else {
            $query = "UPDATE SALES_PEDIDO 
                    SET nombresEnvio=IFNULL(?, nombresEnvio),
                    apellidosEnvio=IFNULL(?, apellidosEnvio),
                    numeroDocumentoEnvio=IFNULL(?, numeroDocumentoEnvio),
                    idTipoDocumentoEnvio=IFNULL(?, idTipoDocumentoEnvio)
                    WHERE idPedido = ?";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$data['nombresEnvio'], $data['apellidosEnvio'], $data['numeroDocumentoEnvio'], $data['idTipoDocumentoEnvio'], $data['id']]);
        echo json_encode(["mensaje" => "Nombres actualizado"]);
    }

}
