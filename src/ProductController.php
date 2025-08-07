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
        try {
            Logger::logGlobal("📦 Listando canales de venta");

            $queries = [
                "canales" => "
                    SELECT scv1.idCanalVenta, scv1.descripcion, scv2.prefijo, 
                        scv2.descripcion AS descripcionCanalPadre,
                        scv2.idCanalVenta AS idCanalVentaPadre 
                    FROM SALES_CANAL_VENTA scv1
                    LEFT JOIN SALES_CANAL_VENTA scv2
                    ON scv2.idCanalVenta = scv1.idCanalVentaPadre
                    AND scv1.nivel = 2
                    WHERE scv1.stsCanalVenta = 'ACT'
                ",
                "convenios" => "
                    SELECT sp.idPetitorio, sp.descripcion 
                    FROM SALES_PETITORIO sp
                ",
                "clasificaciones" => "
                    SELECT spcv.id, spcv.valor 
                    FROM SALES_PRODUCTO_CLASIFICACION_VALOR spcv
                "
            ];

            $resultados = [];
            foreach ($queries as $key => $sql) {
                $stmt = $this->conn->prepare($sql);
                $stmt->execute();
                $resultados[$key] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode($resultados);

        } catch (PDOException $e) {
            Logger::logError("Error al listar datos: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["error" => "Error al obtener los datos"]);
        }
    }

    public function buscarProducto($idPetitorio, $sku, $idCanalVenta) {
        Logger::logGlobal("📦 Consultando producto sku $sku");
        Logger::logGlobal("📦 Consultando producto idPetitorio $idPetitorio");
        Logger::logGlobal("📦 Consultando producto idCanalVenta $idCanalVenta");

        if ((is_null($idPetitorio) || $idPetitorio === 'null')
            && (is_null($idCanalVenta) || $idCanalVenta === 'null')
        ) {
            $query = "SELECT sp.idProducto, sp.skuWMS, sp.nombreComercial, sp.idClasificacionValor, 
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
        } else if ((!is_null($idCanalVenta) || $idCanalVenta !== 'null')
            && (is_null($idPetitorio) || $idPetitorio === 'null')
        ) {
            $query = "SELECT sp.idProducto, sp.skuWMS, sp.nombreComercial, sp.idClasificacionValor,
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
                        sp.idClasificacionValor,
                        sppp.precioNormal, spt.idPetitorio
            FROM SALES_PRODUCTO sp 
            LEFT JOIN SALES_PRODUCTO_PRESENTACION_PETITORIO sppp
            ON sppp.idProducto = sp.idProducto
            AND sppp.idPetitorio = ?
            AND sppp.stsPetitorioProductoPresentacion = 'ACT'
            LEFT JOIN SALES_PETITORIO spt 
            ON spt.idPetitorio = sppp.idPetitorio
            WHERE sp.skuWMS = ?";

            Logger::logGlobal("El query es $query2");
            $stmt2 = $this->conn->prepare($query2);
            $stmt2->execute([$idPetitorio, $sku]);
            $productos = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }

        if (!empty($productos)) {
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
                // Producto no existe → crear/vincular mínimo nombre y presentaciones si corresponde
                $this->actualizarSoloNombreYCrearVinculos($data);
            } else {
                // Producto existe → siempre actualizamos nombreComercial e idClasificacionValor (si vienen)
                $this->actualizarNombreYClasificacion($data, $producto['idProducto']);

                // Después procesamos petitorio o no
                if (!empty($data['idPetitorio'])) {
                    $this->actualizarConPetitorio($data, $producto);
                } else {
                    $this->actualizarSinPetitorio($data);
                }
            }

            // Actualizar beneficios si viene idCanalVenta
            if (!empty($data['idCanalVenta'])) {
                $this->actualizarBeneficios($data);
            }

            $this->conn->commit();
            echo json_encode(["mensaje" => "Producto actualizado"]);
        } catch (Throwable $e) {
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

    /**
     * Obtiene producto según data.
     * Maneja caso con petitorio o sin petitorio.
     */
    private function obtenerProducto($data) {
        // Evitar notices
        $hasPetitorio = !empty($data['idPetitorio']);
        $idProducto = $data['idProducto'] ?? null;
        $idCanalVenta = $data['idCanalVenta'] ?? null;

        if (!$hasPetitorio) {
            $query = "SELECT sp.idProducto, sp.skuWMS, sp.nombreComercial, sp.precioBase, 
                            sppc.precioNormal, spcb.porcentajeDescuento, spcb.montoDescuentoMinimo, sppc.idPresentacion,
                            spcb.porcentajeDescuentoAnterior, spcb.montoDescuentoMinimoAnterior
                      FROM SALES_PRODUCTO sp
                      LEFT JOIN SALES_PRODUCTO_PRESENTACION_CANAL sppc ON sp.idProducto = sppc.idProducto
                      LEFT JOIN SALES_PRODUCTO_CANAL_BENEFICIO spcb 
                        ON spcb.idProducto = sp.idProducto AND spcb.idCanalVenta = sppc.idCanalVenta
                      WHERE sp.idProducto = ? AND sppc.idCanalVenta = ?";
            $params = [$idProducto, $idCanalVenta];
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
            $params = [$idProducto, $data['idPetitorio']];
        }

        Logger::logGlobal("El query es $query");
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza nombreComercial e idClasificacionValor para producto existente.
     * Si el valor no viene, mantiene el existente (usando IFNULL).
     */
    private function actualizarNombreYClasificacion($data, $idProducto) {
        if (empty($idProducto)) return;

        $sql = "UPDATE SALES_PRODUCTO
                SET nombreComercial = IFNULL(?, nombreComercial),
                    idClasificacionValor = IFNULL(?, idClasificacionValor),
                    fechaModificacion = NOW()
                WHERE idProducto = ?";

        $nombre = $data['nombreComercial'] ?? null;
        $idClas = $data['idClasificacionValor'] ?? null;

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$nombre, $idClas, $idProducto]);

        Logger::logGlobal("✅ Nombre y clasificación del producto actualizados para idProducto=$idProducto");
    }

    private function actualizarSinPetitorio($data) {
        // Actualizar nombreComercial si viene (redundante con actualizarNombreYClasificacion pero seguro)
        if (!empty($data['nombreComercial']) || isset($data['idClasificacionValor'])) {
            $this->actualizarNombreYClasificacion($data, $data['idProducto'] ?? null);
        }

        // Actualizar precio en presentacion canal
        $query = "UPDATE SALES_PRODUCTO_PRESENTACION_CANAL 
                   SET precioNormal = IFNULL(?, precioNormal)
                 WHERE idProducto = ? AND idCanalVenta = ? AND stsProductoPresentacionCanal = 'ACT'";
        $this->conn->prepare($query)->execute([
            $data['precio'] ?? null,
            $data['idProducto'] ?? null,
            $data['idCanalVenta'] ?? null
        ]);
    }

    private function actualizarConPetitorio($data, $producto) {
        // Actualizar nombre/comercial/clasificacion si vienen
        if (!empty($data['nombreComercial']) || isset($data['idClasificacionValor'])) {
            $this->actualizarNombreYClasificacion($data, $data['idProducto'] ?? $producto['idProducto'] ?? null);
        }

        $query = "SELECT idProducto FROM SALES_PRODUCTO_PRESENTACION_PETITORIO WHERE idPetitorio = ? AND idProducto = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$data['idPetitorio'], $data['idProducto']]);
        $existe = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existe) {
            $query = "UPDATE SALES_PRODUCTO_PRESENTACION_PETITORIO 
                        SET precioNormal = IFNULL(?, precioNormal), precioRimac = IFNULL(?, precioRimac), stsPetitorioProductoPresentacion = 'ACT'
                      WHERE idPetitorio = ? AND idProducto = ?";
            $params = [$data['precio'] ?? null, $data['precio'] ?? null, $data['idPetitorio'], $data['idProducto']];
        } else {
            $query = "INSERT INTO SALES_PRODUCTO_PRESENTACION_PETITORIO 
                        (idProducto, idPetitorio, idPresentacion, sku, precioNormal, precioRimac, stsPetitorioProductoPresentacion) 
                      VALUES (?, ?, ?, ?, ?, ?, 'ACT')";
            $params = [
                $data['idProducto'],
                $data['idPetitorio'],
                $producto['idPresentacion'] ?? null,
                $data['sku'] ?? null,
                $data['precio'] ?? null,
                $data['precio'] ?? null
            ];
        }

        $this->conn->prepare($query)->execute($params);
    }

    private function actualizarNombreComercial($data) {
        // Mantengo por compatibilidad: si llega idProducto se actualiza
        if (empty($data['idProducto'])) return;

        $query = "UPDATE SALES_PRODUCTO SET 
                    nombreComercial = IFNULL(?, nombreComercial),
                    idClasificacionValor = IFNULL(?, idClasificacionValor)
                  WHERE idProducto = ?";
        $nombre = $data['nombreComercial'] ?? null;
        $idClas = $data['idClasificacionValor'] ?? null;
        $this->conn->prepare($query)->execute([$nombre, $idClas, $data['idProducto']]);
    }

    private function actualizarSoloNombreYCrearVinculos($data) {
        // Si viene idProducto, intentamos actualizar nombre/comercial/clasificacion
        if (!empty($data['idProducto']) && (!empty($data['nombreComercial']) || isset($data['idClasificacionValor']))) {
            $this->actualizarNombreComercial($data);
        }

        // Si no tenemos SKU o precio no podemos crear vínculos de presentaciones -> retorno temprano
        if (empty($data['sku']) || !isset($data['precio'])) {
            echo json_encode(["mensaje" => "Producto actualizado"]);
            return;
        }

        // Limpiar temporal y preparar datos
        $this->conn->exec("DELETE FROM SALES_DATA_PRODUCTO_PETITORIO_TEMPORAL");

        $query = "INSERT INTO SALES_DATA_PRODUCTO_PETITORIO_TEMPORAL (sku, descripcionProducto, precioNormal, precioRimac) VALUES (?, NULL, ?, ?)";
        $this->conn->prepare($query)->execute([$data['sku'], $data['precio'], $data['precio']]);

        $productoCanal = null;
        if (!empty($data['idCanalVenta']) && !empty($data['idProducto'])) {
            $query = "SELECT idProducto FROM SALES_PRODUCTO_CANAL WHERE idProducto = ? AND idCanalVenta = ? AND stsProductoCanal = 'ACT'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$data['idProducto'], $data['idCanalVenta']]);
            $productoCanal = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$productoCanal && !empty($data['idCanalVenta'])) {
            $query = "INSERT INTO SALES_PRODUCTO_CANAL 
                        (idProducto, idCanalVenta, unidadMinimaVenta, stsProductoCanal)
                      SELECT sp.idProducto, ?, NULL, 'ACT'
                      FROM SALES_DATA_PRODUCTO_PETITORIO_TEMPORAL sd
                      JOIN SALES_PRODUCTO sp ON sd.sku = sp.skuWMS
                      WHERE sp.stsProducto = 'ACT'";
            $this->conn->prepare($query)->execute([$data['idCanalVenta']]);
        }

        if (!$productoCanal && !empty($data['idCanalVenta'])) {
            $query = "INSERT INTO SALES_PRODUCTO_PRESENTACION_CANAL
                        (idProducto, idCanalVenta, idPresentacion, sku, precioNormal, stsProductoPresentacionCanal, tipoDispensacion)
                    SELECT sppc.idProducto, ?, sppc.idPresentacion, sppc.sku, sd.precioNormal, sppc.stsProductoPresentacionCanal, sppc.tipoDispensacion
                    FROM SALES_DATA_PRODUCTO_PETITORIO_TEMPORAL sd
                    JOIN SALES_PRODUCTO_PRESENTACION_CANAL sppc ON sd.sku = sppc.sku
                    WHERE sppc.idCanalVenta = 46 AND sppc.stsProductoPresentacionCanal = 'ACT'";
            $this->conn->prepare($query)->execute([$data['idCanalVenta']]);
        } else if (!empty($data['idCanalVenta'])) {
            $query = "UPDATE SALES_PRODUCTO_PRESENTACION_CANAL
                        SET precioNormal = IFNULL(?, precioNormal)
                    WHERE idCanalVenta = ? AND idProducto = ?
                    AND stsProductoPresentacionCanal = 'ACT'";
            $this->conn->prepare($query)->execute([$data['precio'] ?? null, $data['idCanalVenta'], $data['idProducto'] ?? null]);
        }

        if (!empty($data['idPetitorio'])) {
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
        $idProducto = $data['idProducto'] ?? null;
        $idCanal = $data['idCanalVenta'] ?? null;
        $porc = isset($data['porcentajeDescuento']) ? (float)$data['porcentajeDescuento'] : 0;
        $monto = isset($data['montoDescuentoMinimo']) ? (float)$data['montoDescuentoMinimo'] : 0;

        if (empty($idProducto) || empty($idCanal)) {
            Logger::logGlobal("🔔 actualizarBeneficios: faltan idProducto o idCanalVenta");
            return;
        }

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
