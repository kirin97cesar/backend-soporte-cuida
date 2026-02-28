<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/ProductService.php';

class EsquemasServiceV2 {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->conectar();
    }

    public function index($estado = 'ACT') {
        Logger::logGlobal("📦 Listando datos para formularios (estado = $estado)");


        // 2. Canales de venta
        $query2 = "select idCanalVenta, descripcion from SALES_CANAL_VENTA
                WHERE stsCanalVenta = 'ACT'";
        Logger::logGlobal("🔍 Query Canales: $query2");
        $stmt2 = $this->conn->prepare($query2);
        $stmt2->execute();
        $canales = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // 3. Diagnósticos CIE10
        $query3 = "SELECT idCIE10, dscCIE10 
                FROM SALES_CIE10 
                WHERE indEliminado = 'N'";
        Logger::logGlobal("🔍 Query Diagnósticos: $query3");
        $stmt3 = $this->conn->prepare($query3);
        $stmt3->execute();
        $diagnosticos = $stmt3->fetchAll(PDO::FETCH_ASSOC);

        // 4. Frecuencias
        $query4 = "SELECT idFrecuencia, descripcion 
                FROM SALES_ESQUEMA_FRECUENCIA 
                WHERE stsFrecuencia = 'ACT'";
        Logger::logGlobal("🔍 Query Frecuencias: $query4");
        $stmt4 = $this->conn->prepare($query4);
        $stmt4->execute();
        $frecuencias = $stmt4->fetchAll(PDO::FETCH_ASSOC);

        // 5. Salida final
        echo json_encode([
            'listadoEstados' => [],
            'canales' => $canales,
            'diagnosticos' => $diagnosticos,
            'frecuencias' => $frecuencias
        ]);
    }

    public function buscarEsquema($codigoCie, $idCanalVenta) {
        Logger::logGlobal("📦 codigoCie ---> $codigoCie");
        Logger::logGlobal("📦 idCanalVenta ---> $idCanalVenta");

        $query = "SELECT 
            se.idEsquema,
            se.idSubCanalVenta,
            GROUP_CONCAT(sp.skuWMS) as skus,
            scv.descripcion as canalVenta,
            se.idCIE10,
            se.usuarioCreacion,
            se.fechaCreacion,
            se.descripcionDosis as textoAyuda,
            sef.descripcion as frecuencia,
            se.idTipoDosis,
            std.descripcion as tipoDosis,
            se.idFrecuenciaDosis,
            sfd.descripcion as frecuenciaDosis
        FROM SALES_ESQUEMA se
        INNER JOIN SALES_ESQUEMA_PRODUCTO sep 
            ON se.idEsquema = sep.idEsquema 
        INNER JOIN SALES_ESQUEMA_FRECUENCIA sef 
            ON sef.idFrecuencia = se.idFrecuencia 
        INNER JOIN SALES_PRODUCTO sp 
            ON sp.idProducto = sep.idProducto 
        INNER JOIN SALES_TIPO_DOSIS std 
            ON std.idTipoDosis = se.idTipoDosis 
        INNER JOIN SALES_FRECUENCIA_DOSIS sfd 
            ON sfd.idFrecuenciaDosis = se.idFrecuenciaDosis 
        INNER JOIN SALES_CANAL_VENTA scv 
            ON scv.idCanalVenta = se.idSubCanalVenta 
        WHERE se.idCIE10 = ? 
        AND se.idSubCanalVenta = ?
        GROUP BY se.idEsquema;";

        Logger::logGlobal("El query es $query");
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $codigoCie,
            $idCanalVenta
        ]);
        $esquemas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($esquemas && count($esquemas) > 0) {
            echo json_encode($esquemas);
        } else {
            http_response_code(404);
            echo json_encode(["mensaje" => "Cie no encontrado"]);
        }
    }

    public function actualizarEsquema($data) {
        Logger::logGlobal("✏️ Actualizando esquema" . json_encode($data));
        $query = "UPDATE PSP_PACIENTE_ESTADO SET idEstado = IFNULL(?, idEstado) where idPacienteEstado = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $data['idEstado'],
            $data['idPacienteEstado'] ?? null
        ]);
        Logger::logGlobal("query $query");
        echo json_encode(["mensaje" => "Esquema actualizado"]);
    }

    public function registrarEsquema($data, $email) {

        Logger::logGlobal("✏️ RegistrarEsquema " . json_encode($data));

        try {
            $this->conn->beginTransaction();
            // 1️⃣ Insertar esquema
            $stmt = $this->conn->prepare("
                INSERT INTO db_ventas_logistica.SALES_ESQUEMA
                (idSubCanalVenta, idCIE10, usuarioCreacion, fechaCreacion, stsEsquema,
                idFrecuencia, idTipoDosis, idFrecuenciaDosis, descripcionDosis)
                VALUES (?, ?, ?, NOW(), 'ACT', ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['idSubCanalVenta'] ?? null,
                $data['idCIE10'] ?? null,
                $email,
                $data['idFrecuencia'] ?? null,
                $data['idTipoDosis'] ?? null,
                $data['idFrecuenciaDosis'] ?? null,
                $data['textoAyuda'] ?? null
            ]);

            $idEsquema = $this->conn->lastInsertId();

            // 2️⃣ Obtener productos activos
            $productos = [];
            if (!empty($data['productos'])) {

                $skus = array_column($data['productos'], 'sku');

                $placeholders = implode(',', array_fill(0, count($skus), '?'));

                $stmt = $this->conn->prepare("
                    SELECT idProducto, skuWMS as sku
                    FROM SALES_PRODUCTO
                    WHERE skuWMS IN ($placeholders)
                    AND stsProducto = 'ACT'
                ");

                $stmt->execute($skus);
                $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // 3️⃣ Mapear productos encontrados
            $mapProductos = [];
            foreach ($productos as $p) {
                $mapProductos[$p['sku']] = $p['idProducto'];
            }

            // 4️⃣ Insertar en SALES_ESQUEMA_PRODUCTO
            if (!empty($mapProductos)) {

                $values = [];
                $params = [];

                foreach ($data['productos'] as $prod) {
                    $sku = $prod['sku'];
                    $cantidad = $prod['cantidad'] ?? 1;

                    if (isset($mapProductos[$sku])) {
                        $values[] = "(?, ?, ?, NOW(), ?, NULL, NULL, 'ACT')";
                        array_push($params, $idEsquema, $mapProductos[$sku], $cantidad, $email);
                    }
                }
                if (!empty($values)) {
                    $query = "
                        INSERT INTO SALES_ESQUEMA_PRODUCTO
                        (idEsquema, idProducto, cantidad, fechaCreacion,
                        usuarioCreacion, usuarioModificacion,
                        fechaModificacion, stsTratamientoProducto)
                        VALUES " . implode(', ', $values);

                    $stmt = $this->conn->prepare($query);
                    $stmt->execute($params);
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

            Logger::logError("❌ Error registrarEsquema: " . $e->getMessage());

             echo json_encode([
                'success' => false,
                'message' => 'Error al registrar esquema: ' . $e->getMessage()
            ]);
        }
    }

}
