<?php
declare(strict_types=1);

final class BookingComConnector implements IntegrationContract
{
    public function __construct(private array $integration)
    {
    }

    private function settings(): array
    {
        $decoded = json_decode((string)($this->integration['settings_json'] ?? '{}'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function credentials(): array
    {
        return [(string)($this->integration['username'] ?? ''), Crypto::decrypt($this->integration['secret_encrypted'] ?? null)];
    }

    private function authHeader(): string
    {
        [$user, $pass] = $this->credentials();
        return 'Authorization: Basic ' . base64_encode($user . ':' . $pass);
    }

    public function test(): array
    {
        $base = rtrim((string)($this->integration['base_url'] ?: 'https://supply-xml.booking.com'), '/');
        $response = HttpClient::request('GET', $base, ['Accept: application/xml', $this->authHeader()]);
        $reachable = $response['status'] > 0 && $response['status'] < 600;
        return [
            'ok' => $reachable,
            'message' => $reachable
                ? 'Booking.com-Server erreichbar (HTTP ' . $response['status'] . '). Dies bestätigt noch keine Partnerfreigabe oder Hotelzuordnung.'
                : 'Booking.com-Server nicht erreichbar.',
            'http_code' => $response['status'],
        ];
    }

    public function pullReservations(): array
    {
        if (($this->integration['mode'] ?? 'test') !== 'live') {
            throw new RuntimeException('Direkter Booking.com-Abruf ist nur im ausdrücklich aktivierten Live-Modus erlaubt.');
        }
        $hotelId = trim((string)($this->integration['property_id'] ?? ''));
        if ($hotelId === '') {
            throw new RuntimeException('Booking.com Hotel-ID fehlt.');
        }
        $url = 'https://secure-supply-xml.booking.com/hotels/xml/reservations';
        $body = '<request><hotel_id>' . htmlspecialchars($hotelId, ENT_XML1) . '</hotel_id></request>';
        $response = HttpClient::request('POST', $url, [
            'Content-Type: application/xml; charset=utf-8',
            'Accept: application/xml',
            $this->authHeader(),
        ], $body, 35);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('Booking.com antwortete mit HTTP ' . $response['status'] . '.');
        }
        $imported = $this->importXmlReservations($response['body']);
        return ['ok' => true, 'imported' => $imported, 'message' => $imported . ' Reservierung(en) verarbeitet.', 'raw' => $response['body']];
    }

    private function importXmlReservations(string $xml): int
    {
        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml);
        if (!$root) {
            throw new RuntimeException('Booking.com-Antwort konnte nicht als XML gelesen werden.');
        }
        $count = 0;
        $nodes = $root->xpath('//reservation') ?: [];
        foreach ($nodes as $node) {
            $externalId = trim((string)($node->id ?? $node['id'] ?? ''));
            if ($externalId === '') {
                continue;
            }
            $arrival = trim((string)($node->date_from ?? $node->arrival_date ?? ''));
            $departure = trim((string)($node->date_to ?? $node->departure_date ?? ''));
            if (!valid_date($arrival) || !valid_date($departure) || $arrival >= $departure) {
                continue;
            }
            $guestName = trim((string)($node->customer->first_name ?? '') . ' ' . (string)($node->customer->last_name ?? '')) ?: 'Booking.com Gast';
            $email = trim((string)($node->customer->email ?? ''));
            $phone = trim((string)($node->customer->telephone ?? ''));
            $roomId = trim((string)($node->room->id ?? $node->room->room_id ?? ''));
            $status = mb_strtolower(trim((string)($node->status ?? ''))) === 'cancelled' ? 'cancelled' : 'confirmed';
            $this->upsertNormalizedReservation([
                'external_id' => $externalId,
                'guest_name' => $guestName,
                'email' => $email,
                'phone' => $phone,
                'arrival' => $arrival,
                'departure' => $departure,
                'external_room_id' => $roomId,
                'adults' => max(1, (int)($node->numberofguests ?? 1)),
                'children' => 0,
                'status' => $status,
                'total_price' => (float)($node->totalprice ?? 0),
                'notes' => 'Importiert über Booking.com Connectivity API.',
            ]);
            $count++;
        }
        return $count;
    }

    private function upsertNormalizedReservation(array $r): void
    {
        $stmt = db()->prepare("SELECT id FROM bookings WHERE external_provider='booking_com' AND external_id=?");
        $stmt->execute([$r['external_id']]);
        $existingId = $stmt->fetchColumn();
        $guestId = $this->findOrCreateGuest($r['guest_name'], $r['email'], $r['phone']);
        $apartmentId = $this->apartmentFromExternal((string)$r['external_room_id']);
        if ($existingId) {
            $stmt = db()->prepare('UPDATE bookings SET guest_id=?,apartment_id=?,arrival=?,departure=?,adults=?,children=?,status=?,source=?,accounting_mode=?,billing_excluded_reason=?,total_price=?,notes=?,updated_at=NOW() WHERE id=?');
            $stmt->execute([$guestId,$apartmentId,$r['arrival'],$r['departure'],$r['adults'],$r['children'],$r['status'],'Booking.com',BookingAccountingService::MODE_EXTERNAL,'Booking.com – extern abgerechnet',$r['total_price'],$r['notes'],(int)$existingId]);
            BookingAccountingService::neutralizeInternalBilling(db(), (int)$existingId, BookingAccountingService::MODE_EXTERNAL, 'Booking.com – extern abgerechnet');
        } else {
            $stmt = db()->prepare('INSERT INTO bookings(reference,guest_id,apartment_id,arrival,departure,adults,children,status,source,accounting_mode,billing_excluded_reason,total_price,notes,external_provider,external_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([generate_reference(),$guestId,$apartmentId,$r['arrival'],$r['departure'],$r['adults'],$r['children'],$r['status'],'Booking.com',BookingAccountingService::MODE_EXTERNAL,'Booking.com – extern abgerechnet',$r['total_price'],$r['notes'],'booking_com',$r['external_id']]);
        }
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
        $first = $parts[0] ?: 'Gast';
        $last = $parts[1] ?? '-';
        $stmt = db()->prepare('INSERT INTO guests(first_name,last_name,email,phone,language) VALUES(?,?,?,?,?)');
        $stmt->execute([$first,$last,$email ?: null,$phone ?: null,'Deutsch']);
        return (int)db()->lastInsertId();
    }

    private function apartmentFromExternal(string $externalId): ?int
    {
        if ($externalId === '') {
            return null;
        }
        $stmt = db()->prepare("SELECT local_id FROM integration_mappings WHERE provider='booking_com' AND entity_type='apartment' AND external_id=? LIMIT 1");
        $stmt->execute([$externalId]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    public function pushAvailability(string $startDate, string $endDate): array
    {
        throw new RuntimeException('Der Booking.com-ARI-Push muss nach Partnerfreigabe auf das genehmigte Preis- und Ratenmodell abgestimmt werden. Die App speichert dafür bereits Zimmer- und Raten-Mappings.');
    }
}
