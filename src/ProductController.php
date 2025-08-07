<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';

class ProductController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->conectar();
    }

    public function index() {
        Logger::logGlobal("📦 Listando canales de venta");
        $query = "SELECT scv1.idCanalVenta, scv1.descripcion, scv2.prefijo, 
                         scv2.descripcion AS descripcionCanalPadre,
                         scv2.idCanalVenta AS idCanalVentaPadre 
                  FROM SALES_CANAL_VENTA scv1
                  LEFT JOIN SALES_CANAL_VENTA scv2
                    ON scv2.idCanalVenta = scv1.idCanalVentaPadre
                   AND scv1.nivel = 2
                 WHERE scv1.stsCanalVenta = 'ACT'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        $query2 = "SELECT sp.idPetitorio, sp.descripcion FROM SALES_PETITORIO sp";
        $stmt2 = $this->conn->prepare($query2);
        $stmt2->execute();

        echo json_encode([
            "canales" => $stmt->fetchAll(PDO::FETCH_ASSOC),
            "convenios" => $stmt2->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public function buscarProducto($idPetitorio, $sku, $idCanalVenta) {
        Logger::logGlobal("📦 Consultando producto sku $sku");
        Logger::logGlobal("📦 Consultando producto idPetitorio $idPetitorio");
        Logger::logGlobal("📦 Consultando producto idCanalVenta $idCanalVenta");

        if((is_null($idPetitorio) || $idPetitorio == 'null')
        && (is_null($idCanalVenta) || $idCanalVenta == 'null')
        ) {
            $query = "SELECT sp.idProducto, sp.skuWMS, sp.nombreComercial, 
                            sp.precioBase, sppc.precioNormal, sppc.idCanalVenta,
                            spcb.porcentajeDescuento, spcb.montoDescuentoMinimo,
                            spcb.porcentajeDescuentoAnterior, spcb.montoDescuentoMinimoAnterior,
                            scv2.descripcion as descripcionCanalVenta
                    FROM SALES_PRODUCTO sp 
                    LEFT JOIN SALES_PRODUCTO_PRESENTACION_CANAL sppc 
                        ON sp.idProducto = sppc.idProducto
                    LEFT JOIN SALES_PRODUCTO_CANAL_BENEFICIO spcb 
                        ON spcb.idProducto = sp.idProducto 
                    AND spcb.idCanalVenta = sppc.idCanalVenta 
                    LEFT JOIN SALES_CANAL_VENTA scv2
                    ON scv2.idCanalVenta = sppc.idCanalVenta
                    WHERE sp.skuWMS = ?";
            Logger::logGlobal("El query es $query");
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$sku]);
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else if ((!is_null($idCanalVenta) || $idCanalVenta != 'null') &&
        (is_null($idPetitorio) || $idPetitorio == 'null')
        ) {
            $query = "SELECT sp.idProducto, sp.skuWMS, sp.nombreComercial, 
                    sp.precioBase, sppc.precioNormal, sppc.idCanalVenta,
                    spcb.porcentajeDescuento, spcb.montoDescuentoMinimo,
                    spcb.porcentajeDescuentoAnterior, spcb.montoDescuentoMinimoAnterior,
                    scv2.descripcion as descripcionCanalVenta
            FROM SALES_PRODUCTO sp 
            LEFT JOIN SALES_PRODUCTO_PRESENTACION_CANAL sppc 
                ON sp.idProducto = sppc.idProducto
            LEFT JOIN SALES_PRODUCTO_CANAL_BENEFICIO spcb 
                ON spcb.idProducto = sp.idProducto 
            AND spcb.idCanalVenta = sppc.idCanalVenta 
            LEFT JOIN SALES_CANAL_VENTA scv2
            ON scv2.idCanalVenta = sppc.idCanalVenta
            WHERE sp.skuWMS = ?
            AND sppc.idCanalVenta = ?";
            Logger::logGlobal("El query es $query");
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$sku, $idCanalVenta]);
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $query2 = "SELECT sp.idProducto, sp.skuWMS, sp.nombreComercial, 
                        sp.precioBase, spt.descripcion as descripcionConvenio,
                        sppp.precioNormal, spt.idPetitorio
            FROM SALES_PRODUCTO sp 
            LEFT JOIN SALES_PRODUCTO_PRESENTACION_PETITORIO sppp
            ON sppp.idProducto = sp.idProducto
            AND sppp.idPetitorio = ?
            LEFT JOIN SALES_PETITORIO spt 
            ON spt.idPetitorio = sppp.idPetitorio
            WHERE sp.skuWMS = ?";

            Logger::logGlobal("El query es $query2");
            $stmt2 = $this->conn->prepare($query2);
            $stmt2->execute([$idPetitorio, $sku]);
            $productos = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }


        if ($productos && count($productos) > 0) {
            echo json_encode($productos);
        } else {
            http_response_code(404);
            echo json_encode(["mensaje" => "Producto no encontrado"]);
        }
    }

    public function actualizarProducto($data) {
        Logger::logGlobal("✏️ Actualizando producto " . json_encode($data));

        try {
            $this->conn->beginTransaction();

            $producto = $this->obtenerProducto($data);
            Logger::logGlobal("El producto es " . json_encode($producto));

            if (!$producto) {
                $this->actualizarSoloNombreYCrearVinculos($data);
            } elseif ($data['idPetitorio']) {
                $this->actualizarConPetitorio($data, $producto);
            } else {
                $this->actualizarSinPetitorio($data);
            }
            if($data['idCanalVenta']) {
                $this->actualizarBeneficios($data);
            }
            
            $this->conn->commit();
            echo json_encode(["mensaje" => "Producto actualizado"]);
        } catch (Exception $e) {
            $this->conn->rollBack();
            Logger::logGlobal("❌ Error al actualizar producto: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["error" => "Error al actualizar producto"]);
        }
    }

    public function obtenerProductoXSku($skus) {
        Logger::logGlobal("📦 Listando productos por SKU: " . json_encode($skus));

        if (empty($skus) || !is_array($skus)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($skus), '?'));

        $query = "SELECT sp.skuWMS AS sku, sp.nombreComercial AS descripcion, sp.idPresentacion 
                FROM SALES_PRODUCTO sp 
                WHERE sp.stsProducto = 'ACT' AND sp.skuWMS IN ($placeholders)";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($skus);

        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $productos;
    }

    private function obtenerProducto($data) {
        if (!$data['idPetitorio']) {
            $query = "SELECT sp.idProducto, sp.skuWMS, sp.nombreComercial, sp.precioBase, 
                            sppc.precioNormal, spcb.porcentajeDescuento, spcb.montoDescuentoMinimo, sppc.idPresentacion,
                            spcb.porcentajeDescuentoAnterior, spcb.montoDescuentoMinimoAnterior
                      FROM SALES_PRODUCTO sp
                      LEFT JOIN SALES_PRODUCTO_PRESENTACION_CANAL sppc ON sp.idProducto = sppc.idProducto
                      LEFT JOIN SALES_PRODUCTO_CANAL_BENEFICIO spcb 
                        ON spcb.idProducto = sp.idProducto AND spcb.idCanalVenta = sppc.idCanalVenta
                      WHERE sp.idProducto = ? AND sppc.idCanalVenta = ?";
            $params = [$data['idProducto'], $data['idCanalVenta']];
        } else {
            $query = "SELECT sp.idProducto, sp.skuWMS, sp.nombreComercial, sp.precioBase, 
                            sppc.precioNormal, spcb.porcentajeDescuento, spcb.montoDescuentoMinimo, sppc.idPresentacion,
                            spcb.porcentajeDescuentoAnterior, spcb.montoDescuentoMinimoAnterior
                      FROM SALES_PRODUCTO sp
                      LEFT JOIN SALES_PRODUCTO_PRESENTACION_CANAL sppc ON sp.idProducto = sppc.idProducto
                      LEFT JOIN SALES_PRODUCTO_CANAL_BENEFICIO spcb 
                        ON spcb.idProducto = sp.idProducto AND spcb.idCanalVenta = sppc.idCanalVenta
                      LEFT JOIN SALES_PRODUCTO_PRESENTACION_PETITORIO x ON x.idProducto = sp.idProducto
                      WHERE sp.idProducto = ? AND x.idPetitorio = ?";
            $params = [$data['idProducto'], $data['idPetitorio']];
        }

        Logger::logGlobal("El query es $query");
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function actualizarSinPetitorio($data) {
        if (!empty($data['nombreComercial'])) {
            $query = "UPDATE SALES_PRODUCTO SET nombreComercial = IFNULL(?, nombreComercial) WHERE idProducto = ?";
            $this->conn->prepare($query)->execute([$data['nombreComercial'], $data['idProducto']]);
        }

        $query = "UPDATE SALES_PRODUCTO_PRESENTACION_CANAL 
                   SET precioNormal = IFNULL(?, precioNormal)
                 WHERE idProducto = ? AND idCanalVenta = ?";
        $this->conn->prepare($query)->execute([
            $data['precio'],
            $data['idProducto'],
            $data['idCanalVenta']
        ]);
    }

    private function actualizarConPetitorio($data, $producto) {

        $this->actualizarNombreComercial($data);

        $query = "SELECT idProducto FROM SALES_PRODUCTO_PRESENTACION_PETITORIO WHERE idPetitorio = ? AND idProducto = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$data['idPetitorio'], $data['idProducto']]);
        $existe = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existe) {
            $query = "UPDATE SALES_PRODUCTO_PRESENTACION_PETITORIO 
                        SET precioNormal = IFNULL(?, precioNormal), precioRimac = IFNULL(?, precioRimac)
                      WHERE idPetitorio = ? AND idProducto = ?";
            $params = [$data['precio'], $data['precio'], $data['idPetitorio'], $data['idProducto']];
        } else {
            $query = "INSERT INTO SALES_PRODUCTO_PRESENTACION_PETITORIO 
                        (idProducto, idPetitorio, idPresentacion, sku, precioNormal, precioRimac, stsPetitorioProductoPresentacion) 
                      VALUES (?, ?, ?, ?, ?, ?, 'ACT')";
            $params = [
                $data['idProducto'],
                $data['idPetitorio'],
                $producto['idPresentacion'],
                $data['sku'],
                $data['precio'],
                $data['precio']
            ];
        }

        $this->conn->prepare($query)->execute($params);
    }

    private function actualizarNombreComercial($data) {
        if (!empty($data['nombreComercial'])) {
            $query = "UPDATE SALES_PRODUCTO SET nombreComercial = IFNULL(?, nombreComercial) WHERE idProducto = ?";
            $this->conn->prepare($query)->execute([$data['nombreComercial'], $data['idProducto']]);
        }
    }

    private function actualizarSoloNombreYCrearVinculos($data) {

        $this->actualizarNombreComercial($data);
        if (!$data['sku'] || !$data['precio']) {
            echo json_encode(["mensaje" => "Producto actualizado"]);
            return;
        }

        $this->conn->exec("DELETE FROM SALES_DATA_PRODUCTO_PETITORIO_TEMPORAL");

        $query = "INSERT INTO SALES_DATA_PRODUCTO_PETITORIO_TEMPORAL (sku, descripcionProducto, precioNormal, precioRimac) VALUES (?, NULL, ?, ?)";
        $this->conn->prepare($query)->execute([$data['sku'], $data['precio'], $data['precio']]);

        $productoCanal = null;
        if ($data['idCanalVenta']) {
            $query = "SELECT idProducto FROM SALES_PRODUCTO_CANAL WHERE idProducto = ? AND idCanalVenta = ? AND stsProductoCanal = 'ACT'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$data['idProducto'], $data['idCanalVenta']]);
            $productoCanal = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$productoCanal && $data['idCanalVenta']) {
            $query = "INSERT INTO SALES_PRODUCTO_CANAL 
                        (idProducto, idCanalVenta, unidadMinimaVenta, stsProductoCanal)
                      SELECT sp.idProducto, ?, NULL, 'ACT'
                      FROM SALES_DATA_PRODUCTO_PETITORIO_TEMPORAL sd
                      JOIN SALES_PRODUCTO sp ON sd.sku = sp.skuWMS
                      WHERE sp.stsProducto = 'ACT'";
            $this->conn->prepare($query)->execute([$data['idCanalVenta']]);
        }

        if (!$productoCanal && $data['idCanalVenta']) {
            $query = "INSERT INTO SALES_PRODUCTO_PRESENTACION_CANAL
                        (idProducto, idCanalVenta, idPresentacion, sku, precioNormal, stsProductoPresentacionCanal, tipoDispensacion)
                    SELECT sppc.idProducto, ?, sppc.idPresentacion, sppc.sku, sd.precioNormal, sppc.stsProductoPresentacionCanal, sppc.tipoDispensacion
                    FROM SALES_DATA_PRODUCTO_PETITORIO_TEMPORAL sd
                    JOIN SALES_PRODUCTO_PRESENTACION_CANAL sppc ON sd.sku = sppc.sku
                    WHERE sppc.idCanalVenta = 46 AND sppc.stsProductoPresentacionCanal = 'ACT'";
            $this->conn->prepare($query)->execute([$data['idCanalVenta']]);
        } else if ($data['idCanalVenta']) {
            $query = "UPDATE SALES_PRODUCTO_PRESENTACION_CANAL
                        SET precioNormal = IFNULL(?, precioNormal)
                    WHERE idCanalVenta = ? AND idProducto = ?
                    AND stsProductoPresentacionCanal = 'ACT'";
            $this->conn->prepare($query)->execute([$data['precio'],$data['idCanalVenta'], $data['idProducto']]);
        }
        if ($data['idPetitorio']) {
            $query = "INSERT INTO SALES_PRODUCTO_PRESENTACION_PETITORIO
                        (idProducto, idPetitorio, idPresentacion, sku, precioNormal, precioRimac, stsPetitorioProductoPresentacion)
                      SELECT sppc.idProducto, ?, sppc.idPresentacion, sppc.sku, sd.precioNormal, sd.precioNormal, sppc.stsProductoPresentacionCanal
                      FROM SALES_DATA_PRODUCTO_PETITORIO_TEMPORAL sd
                      JOIN SALES_PRODUCTO_PRESENTACION_CANAL sppc ON sd.sku = sppc.sku
                      WHERE sppc.idCanalVenta = 46 AND sppc.stsProductoPresentacionCanal = 'ACT'";
            $this->conn->prepare($query)->execute([$data['idPetitorio']]);
        }
    }

    private function actualizarBeneficios($data) {
        $idProducto = $data['idProducto'];
        $idCanal = $data['idCanalVenta'];
        $porc = $data['porcentajeDescuento'] ?? 0;
        $monto = $data['montoDescuentoMinimo'] ?? 0;

        // Buscar si ya existe el registro activo
        $query = "SELECT porcentajeDescuento, montoDescuentoMinimo 
                FROM SALES_PRODUCTO_CANAL_BENEFICIO
                WHERE idProducto = ? AND idCanalVenta = ? AND stsProductoCanalBeneficio = 'ACT'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$idProducto, $idCanal]);
        $row = $stmt->fetch();

        if ($row) {
            $actualPorc = (float)$row['porcentajeDescuento'];
            $actualMonto = (float)$row['montoDescuentoMinimo'];

            // Solo actualizar si hubo cambios reales
            if ($actualPorc != $porc || $actualMonto != $monto) {
                $query = "UPDATE SALES_PRODUCTO_CANAL_BENEFICIO 
                        SET 
                            porcentajeDescuentoAnterior = porcentajeDescuento,
                            montoDescuentoMinimoAnterior = montoDescuentoMinimo,
                            porcentajeDescuento = ?, 
                            montoDescuentoMinimo = ?,
                            fechaModificacion = NOW()
                        WHERE idProducto = ? AND idCanalVenta = ? AND stsProductoCanalBeneficio = 'ACT'";
                $this->conn->prepare($query)->execute([$porc, $monto, $idProducto, $idCanal]);

                Logger::logGlobal("Beneficio actualizado con cambios (de $actualPorc a $porc)");
            } else {
                Logger::logGlobal("Beneficio no actualizado (sin cambios)");
            }
        } else {
            // Insertar nuevo registro si no existe, dejando los campos *_Anterior como NULL
            $query = "INSERT INTO SALES_PRODUCTO_CANAL_BENEFICIO (
                        idProducto,
                        idCanalVenta,
                        idTipoBeneficio,
                        porcentajeDescuento,
                        montoDescuentoMinimo,
                        porcentajeDescuentoAnterior,
                        montoDescuentoMinimoAnterior,
                        fechaCreacion,
                        fechaModificacion,
                        stsProductoCanalBeneficio
                    )
                    VALUES (?, ?, 2, ?, ?, NULL, NULL, NOW(), NULL, 'ACT')";
            $this->conn->prepare($query)->execute([$idProducto, $idCanal, $porc, $monto]);

            Logger::logGlobal("Beneficio insertado");
        }
    }

}
