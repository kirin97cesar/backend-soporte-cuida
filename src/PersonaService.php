<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';

class PersonaService {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->conectar();
    }

    public function index($estado = 'ACT') {
        Logger::logGlobal("📦 Listando tipos de documento");
        $query = "SELECT idTipoDocumentoIdentidad, descripcion 
                  FROM SALES_TIPO_DOCUMENTO_IDENTIDAD 
                  WHERE stsTipoDocumentoIdentidad = ?";
        Logger::logGlobal("El query es $query");

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$estado]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function buscarPersona($numeroDocumento, $tipoDocumento) {
        Logger::logGlobal("📦 buscarPersona ---> $numeroDocumento");

        $query = "
            SELECT 
                sp.idPersona,
                sp.nombre,
                sp.apellidos,
                sp.numeroDocumento,
                sp.idTipoDocumentoIdentidad,
                sp.apePaterno,
                sp.apeMaterno,
                stdi.descripcion 
            FROM SALES_PERSONA sp
            INNER JOIN SALES_TIPO_DOCUMENTO_IDENTIDAD stdi
                ON stdi.idTipoDocumentoIdentidad = sp.idTipoDocumentoIdentidad
            WHERE sp.numeroDocumento = ?
            AND sp.idTipoDocumentoIdentidad = ?
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$numeroDocumento, $tipoDocumento]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$persona) {
            http_response_code(404);
            echo json_encode(["mensaje" => "Persona no encontrada"]);
            return;
        }

        $query2 = "
            SELECT telefono, idTelefono
            FROM SALES_PERSONA_TELEFONO
            WHERE idPersona = ?
        ";

        $stmt2 = $this->conn->prepare($query2);
        $stmt2->execute([$persona['idPersona']]);
        $telefonos = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $persona['telefonos'] = $telefonos;

        echo json_encode([$persona]);
    }

    public function listadoUsuarios($page = 1, $limit = 10) {
        Logger::logGlobal("📦 Listando usuarios (paginado)");

        $offset = ($page - 1) * $limit;

        // Total de registros
        $countQuery = "
            SELECT COUNT(*) AS total
            FROM SALES_USUARIOS_PBI
            WHERE stsEstado = 'ACT'
        ";
        $countStmt = $this->conn->prepare($countQuery);
        $countStmt->execute();
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Query paginada
        $query = "
            SELECT 
                idUsuarioPbi,
                correo,
                tipoDocumento,
                numeroDocumento,
                nombres,
                apellidoPaterno,
                apellidoMaterno,
                grupos,
                permisoSoporte,
                rol,
                stsEstado,
                fechaCreacion,
                usuarioCreacion,
                fechaActualizacion,
                usuarioActualizacion
            FROM SALES_USUARIOS_PBI
            WHERE stsEstado = 'ACT'
            ORDER BY fechaCreacion DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $personas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'page'       => $page,
            'perPage'    => $limit,
            'total'      => $total,
            'totalPages' => ceil($total / $limit),
            'data'       => $personas
        ]);
    }

    public function buscarCuenta($correoCuenta) {
        Logger::logGlobal("📦 correo cuenta ---> $correoCuenta");

        $query = "SELECT sp.idPersona, sp.nombre, sp.apellidos, sp.apePaterno, 
                sp.apeMaterno, sp.idTipoDocumentoIdentidad, sp.numeroDocumento
                FROM SALES_USUARIO_LOGIN spe
                INNER JOIN SALES_PERSONA sp ON sp.idPersona = spe.idPersona 
                AND sp.indEliminado = 'N'
                WHERE spe.usuarioLogin = ?";

        Logger::logGlobal("El query es $query");
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$correoCuenta]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($persona) {
            echo json_encode($persona);
        } else {
            http_response_code(404);
            echo json_encode(["mensaje" => "Cuenta no encontrada"]);
        }
    }

    public function tiposDeAgente() {
        Logger::logGlobal("📦 tiposDeAgente");

        $query = "select sta.idTipoAgente, sta.descripcion FROM SALES_TIPO_AGENTE sta
                    WHERE sta.stsParametro = 'ACT' ";

        Logger::logGlobal("El query es $query");
        $stmt = $this->conn->prepare($query);
        $stmt->execute([]);
        $tiposAgente = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($tiposAgente) {
            echo json_encode($tiposAgente);
        } else {
            http_response_code(404);
            echo json_encode(["mensaje" => "Tipo de agentes no encontrados"]);
        }
    }

    public function buscarAgentexCorreo($correoAgente) {
        Logger::logGlobal("📦 buscarAgentexCorreo ---> $correoAgente");

        $query = "SELECT sa.idAgente, sa.codigoAgente, sa.idPersona, sa.correoAgente, sa.idTipoAgente,
                    sp.nombre as nombres,
                    sp.apellidos as apellidos,
                    sta.descripcion as tipoAgente
                    from SALES_AGENTE sa 
                    LEFT JOIN SALES_PERSONA sp ON sp.idPersona = sa.idPersona
                    LEFT JOIN SALES_TIPO_AGENTE sta ON sta.idTipoAgente = sa.idTipoAgente 
                    WHERE sa.stsAgente = 'ACT'
                    AND correoAgente = ?";

        Logger::logGlobal("El query es $query");
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$correoAgente]);
        $persona = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($persona) {
            echo json_encode($persona);
        } else {
            http_response_code(404);
            echo json_encode(["mensaje" => "Agente no encontrado"]);
        }
    }

    public function listadoAgentes($correoCuenta) {
        Logger::logGlobal("📦 correo cuenta ---> $correoCuenta");

        $query = "SELECT sp.idPersona, sp.nombre, sp.apellidos, sp.apePaterno, 
                sp.apeMaterno, sp.idTipoDocumentoIdentidad, sp.numeroDocumento
                FROM SALES_USUARIO_LOGIN spe
                INNER JOIN SALES_PERSONA sp ON sp.idPersona = spe.idPersona 
                AND sp.indEliminado = 'N'
                WHERE spe.usuarioLogin = ?";

        Logger::logGlobal("El query es $query");
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$correoCuenta]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($persona) {
            echo json_encode($persona);
        } else {
            http_response_code(404);
            echo json_encode(["mensaje" => "Cuenta no encontrada"]);
        }
    }

    public function eliminarAgente($idAgente) {
        Logger::logGlobal("📦 eliminarAgente ---> $idAgente");

        $query = "UPDATE SALES_AGENTE SET stsAgente = 'INA' WHERE idAgente=?";

        Logger::logGlobal("El query es $query");
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$idAgente]);
        echo json_encode(["mensaje" => "Agente actualizado"]);

    }

    public function registrarAgente($data) {
        Logger::logGlobal("🆕 Registrar Agente " . json_encode($data));

        $apellidos    = $data['apellidos'] ?? null;
        $codigoAgente = $data['codigoAgente'] ?? null;
        $correoAgente = $data['correoAgente'] ?? null;
        $idTipoAgente = $data['idTipoAgente'] ?? null;
        $nombres      = $data['nombres'] ?? null;

        try {
            // Iniciar transacción
            $this->conn->beginTransaction();

            // 1) Insertar en SALES_PERSONA
            $queryPersona = "INSERT INTO SALES_PERSONA (
                                idTipoDocumentoIdentidad, numeroDocumento,
                                apePaterno, apeMaterno, apellidos, nombre,
                                genero, fecNacimiento, stsPersona,
                                usuarioCreacion, fechaCreacion,
                                usuarioModificacion, fechaModificacion,
                                indEliminado, idDistrito, idPaisNacimientoExtranjero,
                                idPertenenciaEtnica, idGrupoSanguineo, idEstadoCivil,
                                idGradoInstruccion, idOcupacionPrincipal, codigoReferenciador,
                                idPedido, categoriaUsuario, idEmpresaPlazoPago,
                                tipoPersona, domicilioFiscal
                            )
                            VALUES (
                                2, ?, NULL, '', ?, ?,
                                NULL, NULL, 'ACT',
                                'SYSTEM', NOW(),
                                NULL, NULL,
                                'N', NULL, NULL,
                                NULL, NULL, NULL,
                                NULL, NULL, NULL,
                                NULL, 0, NULL,
                                'N', ''
                            )";

            $stmt = $this->conn->prepare($queryPersona);
            $stmt->execute([$codigoAgente, $apellidos, $nombres]);

            // Recuperar el idPersona generado
            $idPersona = $this->conn->lastInsertId();

            // 2) Insertar en SALES_AGENTE
            $queryAgente = "INSERT INTO SALES_AGENTE (
                                codigoAgente, idPersona,
                                fechaCreacion, stsAgente,
                                idTipoAgente, correoAgente
                            )
                            VALUES (?, ?, NOW(), 'ACT', ?, ?)";
            $stmt = $this->conn->prepare($queryAgente);
            $stmt->execute([$codigoAgente, $idPersona, $idTipoAgente, $correoAgente]);

            // Confirmar transacción
            $this->conn->commit();

            http_response_code(201);
            echo json_encode([
                "mensaje" => "Agente registrado correctamente",
                "idPersona" => $idPersona
            ]);

        } catch (Exception $e) {
            // Revertir en caso de error
            $this->conn->rollBack();
            Logger::logGlobal("❌ Error al registrar agente: " . $e->getMessage());

            http_response_code(500);
            echo json_encode(["error" => "No se pudo registrar el agente"]);
        }
    }

    public function actualizarAgente($data) {
        Logger::logGlobal("✏️ Actualizar Agente " . json_encode($data));

        $apellidos     = $data['apellidos'] ?? null;
        $codigoAgente  = $data['codigoAgente'] ?? null;
        $correoAgente  = $data['correoAgente'] ?? null;
        $idAgente      = $data['idAgente'] ?? null;
        $idTipoAgente  = $data['idTipoAgente'] ?? null;
        $nombres       = $data['nombres'] ?? null;

        if (!$idAgente) {
            http_response_code(400);
            echo json_encode(["error" => "Falta idAgente"]);
            return;
        }

        // Buscar persona vinculada al agente
        $queryBusqueda = "SELECT idPersona
                        FROM SALES_AGENTE
                        WHERE idAgente = ?";

        Logger::logGlobal("El query es $queryBusqueda");
        $stmt = $this->conn->prepare($queryBusqueda);
        $stmt->execute([$idAgente]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($persona) {
            $query = "UPDATE SALES_PERSONA SET 
                            nombre = IFNULL(?, nombre),
                            apellidos = IFNULL(?, apellidos)
                    WHERE idPersona = ?";
            Logger::logGlobal("El query es ---> $query");
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $nombres,
                $apellidos,
                $persona['idPersona']
            ]);
        }

        $query = "UPDATE SALES_AGENTE SET 
                        codigoAgente  = IFNULL(?, codigoAgente),
                        correoAgente  = IFNULL(?, correoAgente),
                        idTipoAgente  = IFNULL(?, idTipoAgente)
                WHERE idAgente = ?";
        Logger::logGlobal("El query es x2---> $query");
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $codigoAgente,
            $correoAgente,
            $idTipoAgente,
            $idAgente
        ]);

        http_response_code(200);
        echo json_encode(["mensaje" => "Agente actualizado correctamente"]);
    }

    public function actualizarPersona($data) {
        Logger::logGlobal("✏️ Actualizando persona: " . json_encode($data));

        try {
            $this->conn->beginTransaction();

            // 1️⃣ Actualizar datos de la persona
            $queryPersona = "
                UPDATE SALES_PERSONA SET 
                    nombre = IFNULL(?, nombre),
                    apellidos = IFNULL(?, apellidos),
                    apePaterno = IFNULL(?, apePaterno),
                    apeMaterno = IFNULL(?, apeMaterno),
                    idTipoDocumentoIdentidad = IFNULL(?, idTipoDocumentoIdentidad),
                    numeroDocumento = IFNULL(?, numeroDocumento)
                WHERE idPersona = ?
            ";

            $stmt = $this->conn->prepare($queryPersona);
            $stmt->execute([
                $data['nombre'] ?? null,
                $data['apellidos'] ?? null,
                $data['apellidosPaterno'] ?? null,
                $data['apellidosMaterno'] ?? null,
                $data['idTipoDocumentoIdentidad'] ?? null,
                $data['numeroDocumento'] ?? null,
                $data['idPersona']
            ]);

            // 2️⃣ Solo actualizar teléfonos existentes
            if (!empty($data['telefonos']) && is_array($data['telefonos'])) {

                $queryUpdateTelefono = "
                    UPDATE SALES_PERSONA_TELEFONO
                    SET telefono = ?
                    WHERE idTelefono = ?
                    AND idPersona = ?
                ";

                $stmtUpdateTelefono = $this->conn->prepare($queryUpdateTelefono);

                foreach ($data['telefonos'] as $tel) {
                    // ⛔ Si no tiene idTelefono, NO se hace nada
                    if (empty($tel['idTelefono'])) {
                        Logger::logGlobal("⚠️ Teléfono ignorado (sin idTelefono)");
                        continue;
                    }

                    $stmtUpdateTelefono->execute([
                        $tel['telefono'],
                        $tel['idTelefono'],
                        $data['idPersona']
                    ]);
                }
            }

            $this->conn->commit();

            echo json_encode(["mensaje" => "Persona y teléfonos actualizados correctamente"]);

        } catch (Exception $e) {
            $this->conn->rollBack();
            Logger::logGlobal("❌ Error al actualizar persona: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["mensaje" => "Error al actualizar persona"]);
        }
    }

    public function actualizarPersonaConPedidos($data) {
        Logger::logGlobal("✏️ Actualizando nombres con pedidos: " . json_encode($data));

        // Obtener datos anteriores
        $queryOld = "SELECT idTipoDocumentoIdentidad, numeroDocumento FROM SALES_PERSONA WHERE idPersona = ?";
        $stmtOld = $this->conn->prepare($queryOld);
        $stmtOld->execute([$data['idPersona']]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        $data['idTipoDocumentoIdentidadAnterior'] = $oldData['idTipoDocumentoIdentidad'] ?? 2;
        $data['numeroDocumentoAnterior'] = $oldData['numeroDocumento'] ?? '';

        // Actualizar persona
        $query = "UPDATE SALES_PERSONA SET 
                    nombre = IFNULL(?, nombre),
                    apellidos = IFNULL(?, apellidos),
                    apePaterno = IFNULL(?, apePaterno),
                    apeMaterno = IFNULL(?, apeMaterno),
                    idTipoDocumentoIdentidad = IFNULL(?, idTipoDocumentoIdentidad),
                    numeroDocumento = IFNULL(?, numeroDocumento)
                WHERE idPersona = ?";
        Logger::logGlobal("El query es $query");
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $data['nombre'] ?? null,
            $data['apellidos'] ?? null,
            $data['apePaterno'] ?? null,
            $data['apeMaterno'] ?? null,
            $data['idTipoDocumentoIdentidad'] ?? null,
            $data['numeroDocumento'] ?? null,
            $data['idPersona']
        ]);

        // ======================
        // Actualizar según tipo
        // ======================
        if ($data['tipo'] === 'SOLICITUD') {
            $query2 = "UPDATE SALES_DETALLE_SOLICITUD SET 
                            nombresEnvio = IFNULL(?, nombresEnvio),
                            apellidosEnvio = IFNULL(?, apellidosEnvio),
                            idTipoDocumentoEnvio = IFNULL(?, idTipoDocumentoEnvio),
                            numeroDocumentoEnvio = IFNULL(?, numeroDocumentoEnvio)
                    WHERE idSolicitud IN (SELECT idSolicitud FROM SALES_SOLICITUD WHERE idCliente = ?)";
            $params2 = [
                $data['nombre'] ?? null,
                $data['apellidos'] ?? null,
                $data['idTipoDocumentoEnvio'] ?? null,
                $data['numeroDocumentoEnvio'] ?? null,
                $data['idPersona']
            ];

            Logger::logGlobal("El query es $query2");
            $stmt2 = $this->conn->prepare($query2);
            $stmt2->execute($params2);

        } else if ($data['tipo'] === 'OM') {
            // Actualizar formulario
            $query2 = "UPDATE SALES_FORMULARIO_AUTOGESTION SET 
                            nombres = IFNULL(?, nombres),
                            apellidos = IFNULL(?, apellidos),
                            tipoDocumento = IFNULL(?, tipoDocumento),
                            nroDocumento = IFNULL(?, nroDocumento)
                    WHERE nroDocumento = ? AND tipoDocumento = ?";
            $params2 = [
                $data['nombre'] ?? null,
                $data['apellidos'] ?? null,
                $data['idTipoDocumentoEnvio'] ?? null,
                $data['numeroDocumentoEnvio'] ?? null,
                $data['numeroDocumentoAnterior'],
                $data['idTipoDocumentoIdentidadAnterior']
            ];

            Logger::logGlobal("El query es $query2");
            $stmt2 = $this->conn->prepare($query2);
            $stmt2->execute($params2);

            // Obtener IDs de formularios actualizados
            $query3 = "SELECT idFormularioAutogestion 
                    FROM SALES_FORMULARIO_AUTOGESTION 
                    WHERE nroDocumento = ? AND tipoDocumento = ?";
            $stmt3 = $this->conn->prepare($query3);
            $stmt3->execute([
                $data['numeroDocumento'] ?? null, 
                $data['idTipoDocumentoIdentidad'] ?? null
            ]);
            $formularios = $stmt3->fetchAll(PDO::FETCH_ASSOC);

            foreach ($formularios as $formulario) {
                $nombreCompleto = trim(($data['nombre'] ?? '') . " " . ($data['apellidos'] ?? ''));

                $query4 = "UPDATE SALES_ORDEN_MEDICA_AUTOGESTION SET 
                                nombreCompletoPaciente = IFNULL(?, nombreCompletoPaciente),
                                tipoDocumento = IFNULL(?, tipoDocumento),
                                numeroDocumento = IFNULL(?, numeroDocumento)
                        WHERE idFormAutogestion = ?";
                $params4 = [
                    $nombreCompleto ?? null,
                    $data['idTipoDocumentoEnvio'] ?? null,
                    $data['numeroDocumentoEnvio'] ?? null,
                    $formulario['idFormularioAutogestion']
                ];
                $stmt4 = $this->conn->prepare($query4);
                $stmt4->execute($params4);
            }

        } else { // PEDIDO
            $query2 = "UPDATE SALES_PEDIDO SET 
                            nombresEnvio = IFNULL(?, nombresEnvio),
                            apellidosEnvio = IFNULL(?, apellidosEnvio),
                            idTipoDocumentoEnvio = IFNULL(?, idTipoDocumentoEnvio),
                            numeroDocumentoEnvio = IFNULL(?, numeroDocumentoEnvio)
                    WHERE idCliente = ?";
            $params2 = [
                $data['nombre'] ?? null,
                $data['apellidos'] ?? null,
                $data['idTipoDocumentoEnvio'] ?? null,
                $data['numeroDocumentoEnvio'] ?? null,
                $data['idPersona']
            ];

            Logger::logGlobal("El query es $query2");
            $stmt2 = $this->conn->prepare($query2);
            $stmt2->execute($params2);
        }

        echo json_encode(["mensaje" => "Persona y om/solicitudes/pedidos actualizados"]);
    }


}
