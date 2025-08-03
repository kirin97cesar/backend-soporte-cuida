<?php
require_once __DIR__ . '/../config/cargarEnv.php'; 
require_once __DIR__ . '/../utils/Logger.php';

cargarEnv(__DIR__ . '/../.env');


class JWTUtils {

    private static $secret = null;
    private static $expire = 3600; // valor por defecto

    public static function iniciar() {
        self::$secret = $_ENV["JWT_SECRET"] ?: "default_secret";
        self::$expire = $_ENV["JWT_EXPIRE"] ?: 3600;

        Logger::logGlobal("🔐 Secret y expiración cargados (expira en " . self::$expire . "s)");
    }

    public static function generarToken($payload) {
        if (self::$secret === null) self::iniciar();

        // Agregar fecha de expiración
        $payload['exp'] = time() + (int)self::$expire;

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $base64UrlHeader = self::base64UrlEncode(json_encode($header));
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function verificarToken($token) {
        if (self::$secret === null) self::iniciar();

        $partes = explode('.', $token);
        if (count($partes) !== 3) return false;

        list($header, $payload, $firmaRecibida) = $partes;

        $firmaEsperada = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", self::$secret, true)
        );

        if (!hash_equals($firmaEsperada, $firmaRecibida)) return false;

        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

        if (isset($data['exp']) && $data['exp'] < time()) {
            Logger::logGlobal("⏱ Token expirado");
            return false;
        }

        return $data;
    }

    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function obtenerEmail($token) {
        $datos = self::verificarToken($token);
        return $datos && isset($datos['usuario']) ? $datos['usuario'] : null;
    }
}
