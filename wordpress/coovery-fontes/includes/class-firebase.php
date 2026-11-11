<?php

if (!defined('ABSPATH')) {
    exit;
}

class Coovery_Fontes_Firebase {
    const CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    /**
     * @param string $jwt Firebase ID token from the app.
     * @param string $project_id Firebase project id, for example coovery.
     * @return array|WP_Error
     */
    public static function verify($jwt, $project_id) {
        $project_id = trim((string) $project_id);
        if ($project_id === '' || !preg_match('/^[a-z0-9-]{4,60}$/', $project_id)) {
            return new WP_Error('coovery_fontes_project', 'O id do projeto Firebase não está configurado.', array('status' => 401));
        }
        if (!function_exists('openssl_verify')) {
            return new WP_Error('coovery_fontes_openssl', 'O PHP deste site não tem OpenSSL.', array('status' => 500));
        }

        $jwt = trim((string) $jwt);
        if ($jwt === '' || strlen($jwt) > 8192) {
            return new WP_Error('coovery_fontes_token', 'Entre com uma conta do Firebase.', array('status' => 401));
        }

        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return new WP_Error('coovery_fontes_token', 'Entre com uma conta do Firebase.', array('status' => 401));
        }

        $header = json_decode(self::base64url_decode($parts[0]), true);
        $claims = json_decode(self::base64url_decode($parts[1]), true);
        $signature = self::base64url_decode($parts[2]);
        if (!is_array($header) || !is_array($claims) || $signature === '') {
            return new WP_Error('coovery_fontes_token', 'Entre com uma conta do Firebase.', array('status' => 401));
        }
        if (($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
            return new WP_Error('coovery_fontes_token', 'Entre com uma conta do Firebase.', array('status' => 401));
        }

        $verified = self::signature_matches($parts[0] . '.' . $parts[1], $signature, (string) $header['kid'], false);
        if (!$verified) {
            $verified = self::signature_matches($parts[0] . '.' . $parts[1], $signature, (string) $header['kid'], true);
        }
        if (!$verified) {
            return new WP_Error('coovery_fontes_token', 'Entre com uma conta do Firebase.', array('status' => 401));
        }

        $now = time();
        $exp = isset($claims['exp']) ? (int) $claims['exp'] : 0;
        $iat = isset($claims['iat']) ? (int) $claims['iat'] : 0;
        if ($exp < ($now - 60) || $iat > ($now + 60)) {
            return new WP_Error('coovery_fontes_token', 'A sessão do Firebase expirou. Entre de novo.', array('status' => 401));
        }
        if (($claims['aud'] ?? '') !== $project_id) {
            return new WP_Error('coovery_fontes_token', 'Essa conta não é do Firebase deste app.', array('status' => 401));
        }
        if (($claims['iss'] ?? '') !== 'https://securetoken.google.com/' . $project_id) {
            return new WP_Error('coovery_fontes_token', 'Essa conta não é do Firebase deste app.', array('status' => 401));
        }
        if (empty($claims['sub']) || !is_string($claims['sub'])) {
            return new WP_Error('coovery_fontes_token', 'Entre com uma conta do Firebase.', array('status' => 401));
        }

        return $claims;
    }

    /**
     * @return array<string,string>|WP_Error
     */
    public static function certificates($force) {
        if (!$force) {
            $cached = get_transient('coovery_fontes_firebase_certs');
            if (is_array($cached) && $cached !== array()) {
                return $cached;
            }
        }

        $response = wp_remote_get(self::CERTS_URL, array('timeout' => 8));
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code !== 200 || !is_array($body) || $body === array()) {
            return new WP_Error('coovery_fontes_certs', 'Não foi possível buscar as chaves do Firebase.');
        }

        $certs = array();
        foreach ($body as $kid => $pem) {
            if (is_string($kid) && is_string($pem) && strpos($pem, 'BEGIN CERTIFICATE') !== false) {
                $certs[$kid] = $pem;
            }
        }
        if ($certs === array()) {
            return new WP_Error('coovery_fontes_certs', 'Não foi possível buscar as chaves do Firebase.');
        }

        set_transient('coovery_fontes_firebase_certs', $certs, 6 * HOUR_IN_SECONDS);
        return $certs;
    }

    private static function signature_matches($signed, $signature, $kid, $force_refresh) {
        $certs = self::certificates($force_refresh);
        if (is_wp_error($certs) || empty($certs[$kid])) {
            return false;
        }
        $result = openssl_verify($signed, $signature, $certs[$kid], OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    private static function base64url_decode($data) {
        $replaced = strtr((string) $data, '-_', '+/');
        $pad = strlen($replaced) % 4;
        if ($pad > 0) {
            $replaced .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode($replaced, true);
        return $raw === false ? '' : $raw;
    }
}
