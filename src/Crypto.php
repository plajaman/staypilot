<?php
declare(strict_types=1);

final class Crypto
{
    private static function key(): string
    {
        $raw = (string)(local_config()['app_key'] ?? '');
        if (str_starts_with($raw, 'base64:')) {
            $decoded = base64_decode(substr($raw, 7), true);
            if ($decoded !== false) {
                return hash('sha256', $decoded, true);
            }
        }
        return hash('sha256', $raw, true);
    }

    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new RuntimeException('Verschlüsselung fehlgeschlagen.');
        }
        $mac = hash_hmac('sha256', $iv . $cipher, self::key(), true);
        return base64_encode($iv . $mac . $cipher);
    }

    public static function decrypt(?string $payload): string
    {
        if (!$payload) {
            return '';
        }
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 49) {
            return '';
        }
        $iv = substr($raw, 0, 16);
        $mac = substr($raw, 16, 32);
        $cipher = substr($raw, 48);
        $calc = hash_hmac('sha256', $iv . $cipher, self::key(), true);
        if (!hash_equals($mac, $calc)) {
            return '';
        }
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : $plain;
    }
}
