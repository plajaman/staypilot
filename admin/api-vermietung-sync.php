<?php
declare(strict_types=1);

function generate_staypilot_export_key_v1(): never
{
    $plain = bin2hex(random_bytes(24));
    save_setting('staypilot_export_key_hash', hash('sha256', $plain));
    AuditLogger::record('settings', 'staypilot_export_key', 'update', null, null, 'Neuer StayPilot-Export-Schlüssel erzeugt (für Vermietung-Doppelbuchungs-Warnung)');
    json_response(['ok' => true, 'message' => 'Neuer Schlüssel erzeugt.', 'key' => $plain]);
}

function vermietung_sync_fetch_v1(): ?array
{
    if (!normalize_bool(setting('vermietung_sync_enabled', 0))) return null;
    $url = trim((string)setting('vermietung_sync_url', ''));
    $key = trim((string)setting('vermietung_sync_key', ''));
    if ($url === '' || $key === '') return null;
    $endpoint = rtrim($url, '/') . '/?route=api-export&key=' . rawurlencode($key);
    try {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false || $status !== 200) {
            AppLogger::error(new RuntimeException($error ?: ('HTTP ' . $status)), ['endpoint' => $url], 'vermietung-sync');
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['apartments']) || !is_array($data['apartments'])) return null;
        return $data['apartments'];
    } catch (Throwable $e) {
        AppLogger::error($e, ['endpoint' => $url], 'vermietung-sync');
        return null;
    }
}

function vermietung_sync_base_code_v1(string $code): string
{
    return (string)preg_replace('/-\d+$/', '', $code);
}

function task_center_vermietung_conflicts_v1(): array
{
    $items = [];
    $remote = vermietung_sync_fetch_v1();
    if ($remote === null) return $items;

    $stmt = db()->query('SELECT id, code, name FROM apartments');
    $localByCode = [];
    $localByBaseCode = [];
    foreach ($stmt->fetchAll() as $row) {
        $code = (string)($row['code'] ?? '');
        if ($code === '') continue;
        $localByCode[$code][] = $row;
        $localByBaseCode[vermietung_sync_base_code_v1($code)][] = $row;
    }

    foreach ($remote as $apt) {
        if ((string)($apt['status'] ?? '') !== 'vermietet') continue;
        $tenant = $apt['tenant'] ?? null;
        if (!is_array($tenant)) continue;
        $moveIn = (string)($tenant['move_in'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $moveIn)) continue;
        $leaseEnd = (string)($tenant['lease_end'] ?? '');
        $vCode = (string)($apt['code'] ?? '');
        if ($vCode === '') continue;

        $matches = $localByCode[$vCode] ?? $localByBaseCode[vermietung_sync_base_code_v1($vCode)] ?? [];
        foreach ($matches as $local) {
            $sql = 'SELECT id, reference, arrival, departure, status FROM bookings WHERE apartment_id=? AND status NOT IN (\'cancelled\',\'rejected\') AND departure > ? ORDER BY arrival LIMIT 10';
            $bstmt = db()->prepare($sql);
            $bstmt->execute([$local['id'], $moveIn]);
            foreach ($bstmt->fetchAll() as $b) {
                if ($leaseEnd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $leaseEnd) && (string)$b['arrival'] >= $leaseEnd) continue;
                $tenantName = trim(((string)($tenant['first_name'] ?? '')) . ' ' . ((string)($tenant['last_name'] ?? '')));
                $desc = ($local['name'] ?: $vCode) . ' · Vermietung-Einzug ' . $moveIn . ($tenantName !== '' ? ' (' . $tenantName . ')' : '') . ' · StayPilot-Buchung ' . ($b['reference'] ?? '') . ' ' . $b['arrival'] . '–' . $b['departure'];
                $items[] = task_center_item_v23656('vermietung_conflict', (string)$local['id'] . '_' . (string)$b['id'], 'Möglicher Doppelbezug: ' . ($local['name'] ?: $vCode), $desc, $b['arrival'], null, 'communication', 'urgent', 'open', ['booking_id' => $b['id']]);
            }
        }
    }
    return $items;
}
