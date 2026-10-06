<?php
declare(strict_types=1);

final class HttpClient
{
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 25): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Die PHP-cURL-Erweiterung fehlt auf dem Server.');
        }
        self::assertPublicUrl($url);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'StayPilot/1.0',
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException($error ?: 'HTTP-Anfrage fehlgeschlagen.');
        }
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseHeaders = substr($raw, 0, $headerSize);
        $responseBody = substr($raw, $headerSize);
        curl_close($ch);
        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $responseBody];
    }

    /**
     * Blockiert Anfragen an private/interne Adressen (SSRF-Schutz). Integrations-URLs
     * (base_url, health_path etc.) sind frei konfigurierbar von Admins/Managern -
     * ohne diese Pruefung koennte darueber z. B. http://169.254.169.254/ oder
     * http://localhost/... intern angefragt werden.
     */
    private static function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = (string)($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('Ungueltige Integrations-URL.');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (@gethostbynamel($host) ?: []);
        if (!$ips) {
            throw new RuntimeException('Host der Integrations-URL konnte nicht aufgeloest werden.');
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Integrations-URL zeigt auf eine interne/nicht oeffentliche Adresse und wird aus Sicherheitsgruenden abgelehnt.');
            }
        }
    }
}
