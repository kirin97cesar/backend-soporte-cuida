<?php
require_once __DIR__ . '/../utils/Logger.php';
function cargarEnv($ruta)
{
    if (!file_exists($ruta)) {
        Logger::logGlobal("⚠️ Archivo .env no encontrado en: $ruta");
        return;
    }

    $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        if (strpos(trim($linea), '#') === 0 || strpos(trim($linea), '=') === false) {
            continue;
        }

        list($nombre, $valor) = explode('=', $linea, 2);
        $nombre = trim($nombre);
        $valor = trim($valor, " \t\n\r\0\x0B\"'");

        putenv("$nombre=$valor");
        $_ENV[$nombre] = $valor;
        $_SERVER[$nombre] = $valor;

        //Logger::logGlobal("✅ Cargado: $nombre=$valor");
    }
}
