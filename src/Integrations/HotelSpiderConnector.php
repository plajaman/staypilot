<?php
declare(strict_types=1);

final class HotelSpiderConnector implements IntegrationContract
{
    public function __construct(private array $integration)
    {
    }

    private function settings(): array
    {
        $decoded = json_decode((string)($this->integration['settings_json'] ?? '{}'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function headers(): array
    {
        $settings = $this->settings();
        $secret = Crypto::decrypt($this->integration['secret_encrypted'] ?? null);
        $headerName = trim((string)($settings['auth_header'] ?? 'Authorization'));
        $prefix = (string)($settings['auth_prefix'] ?? 'Bearer ');
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($secret !== '') {
            $headers[] = $headerName . ': ' . $prefix . $secret;
        }
        if (!empty($this->integration['username'])) {
            $headers[] = 'X-PMS-User: ' . $this->integration['username'];
        }
        return $headers;
    }

    private function url(string $settingKey, string $fallback = ''): string
    {
        $settings = $this->settings();
        $base = rtrim((string)($this->integration['base_url'] ?? ''), '/');
        $path = (string)($settings[$settingKey] ?? $fallback);
        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }
        return $base . '/' . ltrim($path, '/');
    }

    public function test(): array
    {
        $url = $this->url('health_path', '');
        if ($url === '' || !preg_match('~^https?://~i', $url)) {
            throw new RuntimeException('Hotel-Spider Base-URL fehlt.');
        }
        $response = HttpClient::request('GET', $url, $this->headers());
        $ok = $response['status'] >= 200 && $response['status'] < 500;
        return [
            'ok' => $ok,
            'message' => 'Hotel-Spider-Endpunkt antwortet mit HTTP ' . $response['status'] . '. Die konkrete PMS-Freigabe muss Hotel-Spider bestätigen.',
            'http_code' => $response['status'],
        ];
    }

    public function pullReservations(): array
    {
        if (($this->integration['mode'] ?? 'test') !== 'live') {
            throw new RuntimeException('Hotel-Spider-Abruf ist nur im Live-Modus aktiv.');
        }
        $url = $this->url('reservations_path', 'reservations');
        $query = http_build_query([
            'property_id' => $this->integration['property_id'] ?? '',
            'updated_since' => $this->integration['last_sync_at'] ?? date('Y-m-d H:i:s', strtotime('-7 days')),
        ]);
        $response = HttpClient::request('GET', $url . (str_contains($url, '?') ? '&' : '?') . $query, $this->headers(), null, 35);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('Hotel-Spider antwortete mit HTTP ' . $response['status'] . '.');
        }
        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            throw new RuntimeException('Hotel-Spider-Antwort ist kein gültiges JSON.');
        }
        $rows = $data['reservations'] ?? $data['data'] ?? $data;
        if (!is_array($rows)) {
            $rows = [];
        }
        $count = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($this->importNormalized($row)) {
                $count++;
            }
        }
        return ['ok' => true, 'imported' => $count, 'message' => $count . ' Reservierung(en) verarbeitet.', 'raw' => $response['body']];
    }

    public function importNormalized(array $r): bool
    {
        $externalId = trim((string)($r['external_id'] ?? $r['id'] ?? $r['reservation_id'] ?? ''));
        $arrival = substr((string)($r['arrival'] ?? $r['check_in'] ?? ''), 0, 10);
        $departure = substr((string)($r['departure'] ?? $r['check_out'] ?? ''), 0, 10);
        if ($externalId === '' || !valid_date($arrival) || !valid_date($departure) || $arrival >= $departure) {
            return false;
        }
        $guest = $r['guest'] ?? [];
        $guestName = trim((string)($r['guest_name'] ?? $guest['name'] ?? (($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? '')))) ?: 'Hotel-Spider Gast';
        $email = trim((string)($r['email'] ?? $guest['email'] ?? ''));
        $phone = trim((string)($r['phone'] ?? $guest['phone'] ?? ''));
        $roomId = trim((string)($r['external_room_id'] ?? $r['room_id'] ?? $r['unit_id'] ?? ''));
        $statusRaw = mb_strtolower((string)($r['status'] ?? 'confirmed'));
        $status = in_array($statusRaw, ['cancelled','canceled','storniert'], true) ? 'cancelled' : (in_array($statusRaw, ['inquiry','request'], true) ? 'inquiry' : 'confirmed');
        $guestId = $this->findOrCreateGuest($guestName, $email, $phone);
        $apartmentId = $this->apartmentFromExternal($roomId);
        $stmt = db()->prepare("SELECT id FROM bookings WHERE external_provider='hotel_spider' AND external_id=?");
        $stmt->execute([$externalId]);
        $id = $stmt->fetchColumn();
        $values = [
            $guestId,$apartmentId,$arrival,$departure,max(1,(int)($r['adults'] ?? 1)),max(0,(int)($r['children'] ?? 0)),
            $status,(string)($r['source'] ?? 'Hotel-Spider'),BookingAccountingService::MODE_EXTERNAL,'Hotel-Spider – extern abgerechnet',(float)($r['total_price'] ?? $r['amount'] ?? 0),
            (float)($r['paid_amount'] ?? 0),normalize_bool($r['breakfast'] ?? false),normalize_bool($r['half_board'] ?? false),
            (string)($r['notes'] ?? 'Importiert über Hotel-Spider PMS-Schnittstelle.')
        ];
        if ($id) {
            $stmt = db()->prepare('UPDATE bookings SET guest_id=?,apartment_id=?,arrival=?,departure=?,adults=?,children=?,status=?,source=?,accounting_mode=?,billing_excluded_reason=?,total_price=?,paid_amount=?,breakfast=?,half_board=?,notes=?,updated_at=NOW() WHERE id=?');
            $stmt->execute([...$values,(int)$id]);
            BookingAccountingService::neutralizeInternalBilling(db(), (int)$id, BookingAccountingService::MODE_EXTERNAL, 'Hotel-Spider – extern abgerechnet');
        } else {
            $stmt = db()->prepare('INSERT INTO bookings(reference,guest_id,apartment_id,arrival,departure,adults,children,status,source,accounting_mode,billing_excluded_reason,total_price,paid_amount,breakfast,half_board,notes,external_provider,external_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([generate_reference(),...$values,'hotel_spider',$externalId]);
        }
        return true;
    }

    private function findOrCreateGuest(string $name, string $email, string $phone): int
    {
        if ($email !== '') {
            $stmt = db()->prepare('SELECT id FROM guests WHERE email=? LIMIT 1');
            $stmt->execute([$email]);
            if ($id = $stmt->fetchColumn()) {
                return (int)$id;
            }
        }
        $parts = preg_split('/\s+/', trim($name), 2);
        $stmt = db()->prepare('INSERT INTO guests(first_name,last_name,email,phone,language) VALUES(?,?,?,?,?)');
        $stmt->execute([$parts[0] ?: 'Gast',$parts[1] ?? '-', $email ?: null, $phone ?: null,'Deutsch']);
        return (int)db()->lastInsertId();
    }

    private function apartmentFromExternal(string $externalId): ?int
    {
        if ($externalId === '') {
            return null;
        }
        $stmt = db()->prepare("SELECT local_id FROM integration_mappings WHERE provider='hotel_spider' AND entity_type='apartment' AND external_id=? LIMIT 1");
        $stmt->execute([$externalId]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    public function pushAvailability(string $startDate, string $endDate): array
    {
        if (($this->integration['mode'] ?? 'test') !== 'live') {
            throw new RuntimeException('Hotel-Spider-Push ist nur im Live-Modus aktiv.');
        }
        $url = $this->url('availability_path', 'availability');
        $mappings = db()->query("SELECT m.*,a.code,a.name,a.base_price FROM integration_mappings m JOIN apartments a ON a.id=m.local_id WHERE m.provider='hotel_spider' AND m.entity_type='apartment'")->fetchAll();
        $payload = ['property_id' => $this->integration['property_id'] ?? '', 'start_date' => $startDate, 'end_date' => $endDate, 'units' => []];
        foreach ($mappings as $map) {
            $stmt = db()->prepare("SELECT arrival,departure FROM bookings WHERE apartment_id=? AND status NOT IN ('cancelled','rejected') AND arrival<? AND departure>?");
            $stmt->execute([(int)$map['local_id'],$endDate,$startDate]);
            $payload['units'][] = [
                'external_room_id' => $map['external_id'],
                'external_rate_id' => $map['secondary_external_id'],
                'base_price' => (float)$map['base_price'],
                'reservations' => $stmt->fetchAll(),
            ];
        }
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response = HttpClient::request('POST', $url, $this->headers(), $body, 35);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('Hotel-Spider antwortete mit HTTP ' . $response['status'] . '.');
        }
        return ['ok' => true, 'message' => 'Verfügbarkeit übertragen.', 'raw' => $response['body'], 'request' => $body];
    }
}
