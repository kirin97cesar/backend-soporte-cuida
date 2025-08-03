<?php
class Logger {
    public static function logGlobal($mensaje) {
        date_default_timezone_set('America/Lima'); // UTC-5
        $fecha = date("Y-m-d H:i:s");
        $ruta = __DIR__ . '/mi-log.txt';
        error_log("[$fecha UTC-5] $mensaje\n", 3, $ruta);
    }
}
