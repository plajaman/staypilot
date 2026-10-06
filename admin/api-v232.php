<?php
declare(strict_types=1);

/**
 * StayPilot V2.3.2 – Kommunikationsnachvollzug, Gastportal-Links und
 * konsistente Angebots-/Buchungsstatus-Hinweise.
 */

function communications_v232(): never
{
    if (function_exists('ensure_delete_center_v23663')) ensure_delete_center_v23663();
    ensure_communication_body_v232();
    $channel = trim((string)($_GET['channel'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    $entity = trim((string)($_GET['entity_type'] ?? ''));
    $q = trim((string)($_GET['q'] ?? ''));
    $limit = max(20, min(500, (int)($_GET['limit'] ?? 250)));

    $where = ["(l.deleted_at IS NULL OR l.deleted_at='0000-00-00 00:00:00')"];
    $params = [];
    if ($channel !== '') { $where[] = "l.channel COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci"; $params[] = $channel; }
    if ($status !== '') { $where[] = "l.status COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci"; $params[] = $status; }
    if ($entity !== '') { $where[] = "l.entity_type COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci"; $params[] = $entity; }
    if ($q !== '') {
        $where[] = '(l.recipient_name COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci
            OR l.recipient_address COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci
            OR l.subject COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci
            OR l.detail COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci
            OR l.message_excerpt COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci
            OR b.reference COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci
            OR o.offer_number COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci
            OR CONCAT(g.first_name," ",g.last_name) COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
    }

    $sql = "SELECT l.*,u.name created_by_name,
        b.reference booking_reference,b.id booking_id,
        o.offer_number offer_number,o.id offer_id,
        g.id guest_id,TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) related_guest_name,
        g.email related_guest_email
        FROM communication_log l
        LEFT JOIN users u ON u.id=l.created_by
        LEFT JOIN bookings b ON (
            l.entity_type COLLATE utf8mb4_unicode_ci = 'booking' COLLATE utf8mb4_unicode_ci
            AND l.entity_id COLLATE utf8mb4_unicode_ci = CAST(b.id AS CHAR) COLLATE utf8mb4_unicode_ci
        )
        LEFT JOIN offers o ON (
            l.entity_type COLLATE utf8mb4_unicode_ci = 'offer' COLLATE utf8mb4_unicode_ci
            AND l.entity_id COLLATE utf8mb4_unicode_ci = CAST(o.id AS CHAR) COLLATE utf8mb4_unicode_ci
        )
        LEFT JOIN guests bg ON bg.id=b.guest_id
        LEFT JOIN guests g ON (
            g.id = b.guest_id
            OR g.email COLLATE utf8mb4_unicode_ci = l.recipient_address COLLATE utf8mb4_unicode_ci
            OR g.email COLLATE utf8mb4_unicode_ci = bg.email COLLATE utf8mb4_unicode_ci
            OR g.email COLLATE utf8mb4_unicode_ci = o.guest_email COLLATE utf8mb4_unicode_ci
        )";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY l.id DESC LIMIT ' . $limit;

    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $entries = $stmt->fetchAll();
        $fallback = false;
        $warning = '';
    } catch (Throwable $e) {
        // V2.3.6.48: Die Kommunikationsseite darf bei gemischten Tabellen-Collations nicht mehr komplett ausfallen.
        // Falls ein Server trotz COLLATE-Erzwingung noch eine Abfrage ablehnt, wird eine einfache Log-Ansicht geladen.
        $fallback = true;
        $warning = 'Detailverknüpfungen wurden wegen gemischter Datenbank-Collations vereinfacht geladen: ' . $e->getMessage();
        $entries = communication_fallback_entries_v232($channel, $status, $entity, $q, $limit);
    }

    foreach ($entries as &$entry) {
        $entry['message_body_available'] = isset($entry['message_body']) && trim((string)$entry['message_body']) !== '';
        $entry['message_display'] = trim((string)($entry['message_body'] ?? '')) !== '' ? (string)$entry['message_body'] : (string)($entry['message_excerpt'] ?? '');
        $entry['context_label'] = communication_context_label_v232($entry);
    }
    unset($entry);
    json_response([
        'ok' => true,
        'entries' => $entries,
        'body_storage' => communication_body_available_v232(),
        'fallback' => $fallback,
        'warning' => $warning,
    ]);
}

function communication_fallback_entries_v232(string $channel, string $status, string $entity, string $q, int $limit): array
{
    $where = ["(l.deleted_at IS NULL OR l.deleted_at='0000-00-00 00:00:00')"];
    $params = [];
    if ($channel !== '') { $where[] = "l.channel COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci"; $params[] = $channel; }
    if ($status !== '') { $where[] = "l.status COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci"; $params[] = $status; }
    if ($entity !== '') { $where[] = "l.entity_type COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci"; $params[] = $entity; }
    if ($q !== '') {
        $where[] = '(l.recipient_name COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci
            OR l.recipient_address COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci
            OR l.subject COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci
            OR l.detail COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci
            OR l.message_excerpt COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    $sql = "SELECT l.*,u.name created_by_name,
        NULL booking_reference,NULL booking_id,NULL offer_number,NULL offer_id,NULL guest_id,
        l.recipient_name related_guest_name,l.recipient_address related_guest_email
        FROM communication_log l
        LEFT JOIN users u ON u.id=l.created_by";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY l.id DESC LIMIT ' . $limit;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function guest_portal_links_v232(): never
{
    $guestId = (int)($_GET['id'] ?? 0);
    if (!$guestId) throw new ValidationException('Gast fehlt.');
    $stmt = db()->prepare("SELECT b.id booking_id,b.reference,b.arrival,b.departure,b.status,b.payment_status,b.total_price,b.paid_amount,
        a.code apartment_code,a.name apartment_name,ca.active,ca.valid_until,ca.last_viewed_at,ca.token_encrypted
        FROM bookings b
        JOIN booking_customer_access ca ON ca.booking_id=b.id
        LEFT JOIN apartments a ON a.id=b.apartment_id
        WHERE b.guest_id=? AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00')
        ORDER BY b.arrival DESC,b.id DESC");
    $stmt->execute([$guestId]);
    $links = [];
    foreach ($stmt->fetchAll() as $row) {
        $token = Crypto::decrypt($row['token_encrypted'] ?? null);
        $row['customer_url'] = $token !== '' ? HousekeepingWorkflow::applicationUrl('kunde.php?token=' . rawurlencode($token)) : '';
        unset($row['token_encrypted']);
        $links[] = $row;
    }
    json_response(['ok' => true, 'links' => $links]);
}

function bookings_v232(): never
{
    if (function_exists('ensure_delete_center_v23663')) ensure_delete_center_v23663();
    $from = (string)($_GET['from'] ?? date('Y-m-01', strtotime('-6 months')));
    $to = (string)($_GET['to'] ?? date('Y-m-d', strtotime('+18 months')));
    $q = trim((string)($_GET['q'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    $base = booking_select();
    $base = preg_replace('/^SELECT b\.\*,/u', "SELECT b.*,so.offer_number source_offer_number,so.status source_offer_status,so.internal_notes source_offer_internal_notes,(SELECT e.event_type FROM offer_events e WHERE e.offer_id=so.id AND e.event_type LIKE 'no_availability_%' ORDER BY e.id DESC LIMIT 1) source_offer_availability_event,(SELECT e.created_at FROM offer_events e WHERE e.offer_id=so.id AND e.event_type LIKE 'no_availability_%' ORDER BY e.id DESC LIMIT 1) source_offer_availability_at,", $base, 1);
    $sql = $base . " LEFT JOIN offers so ON so.id=b.source_offer_id WHERE b.arrival<? AND b.departure>? AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00')";
    $params = [$to, $from];
    if ($status !== '') { $sql .= ' AND b.status=?'; $params[] = $status; }
    if ($q !== '') {
        $sql .= " AND (b.reference LIKE ? OR CONCAT(g.first_name,' ',g.last_name) LIKE ? OR a.name LIKE ? OR COALESCE(at.name,aat.name) LIKE ? OR b.source LIKE ? OR so.offer_number LIKE ? OR b.upgrade_note LIKE ? OR b.internal_notes LIKE ?)";
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
    }
    $sql .= ' ORDER BY b.arrival DESC LIMIT 2000';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['workflow_status_label'] = booking_workflow_label_v232($row);
        $row['workflow_status_class'] = booking_workflow_class_v232($row);
    }
    unset($row);
    json_response(['ok' => true, 'bookings' => $rows]);
}

function ensure_communication_body_v232(): void
{
    if (communication_body_available_v232()) return;
    try { db()->exec('ALTER TABLE communication_log ADD COLUMN message_body LONGTEXT NULL AFTER message_excerpt'); } catch (Throwable $ignored) {}
}

function communication_body_available_v232(): bool
{
    try { db()->query('SELECT message_body FROM communication_log LIMIT 0'); return true; } catch (Throwable $e) { return false; }
}

function communication_context_label_v232(array $entry): string
{
    if (!empty($entry['booking_reference'])) return 'Buchung ' . $entry['booking_reference'];
    if (!empty($entry['offer_number'])) return 'Angebot ' . $entry['offer_number'];
    if (!empty($entry['related_guest_name'])) return 'Gast ' . $entry['related_guest_name'];
    return (string)($entry['entity_type'] ?? 'Vorgang') . (!empty($entry['entity_id']) ? ' #' . $entry['entity_id'] : '');
}

function booking_workflow_label_v232(array $row): string
{
    $note = mb_strtolower((string)($row['upgrade_note'] ?? '') . ' ' . (string)($row['internal_notes'] ?? ''));
    if ((int)($row['is_upgrade'] ?? 0) === 1 || str_contains($note, 'alternative wohnungszuweisung') || str_contains($note, 'alternative wohnung')) {
        return 'Alternative / Upgrade übernommen';
    }
    $event = (string)($row['source_offer_availability_event'] ?? '');
    return match ($event) {
        'no_availability_notify' => 'Gast wegen fehlender Verfügbarkeit informiert',
        'no_availability_hold' => 'Rückfrage / Warteliste wegen Verfügbarkeit',
        'no_availability_cancel' => 'Anfrage wegen Verfügbarkeit archiviert',
        default => '',
    };
}

function booking_workflow_class_v232(array $row): string
{
    $label = booking_workflow_label_v232($row);
    if ($label === '') return '';
    if (str_contains($label, 'archiviert')) return 'danger';
    if (str_contains($label, 'übernommen')) return 'success';
    return 'warning';
}
