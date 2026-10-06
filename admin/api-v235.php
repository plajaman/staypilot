<?php
declare(strict_types=1);

/**
 * StayPilot V2.3.5 – Datenreparatur & Statuslogik.
 * Repariert keine Daten automatisch im Hintergrund, sondern bietet klare,
 * nachvollziehbare Aktionen für nicht zugeordnete Buchungen, Alternativen/
 * Upgrades und unlogische Zahlungsziele.
 */

function data_repair_overview_v235(): never
{
    BookingWorkflowService::syncBillingState();
    $unassigned = v235_unassigned_bookings();
    $paymentIssues = v235_payment_plan_issues();
    $acceptedOffers = v235_accepted_offer_clarifications();
    json_response([
        'ok' => true,
        'summary' => [
            'unassigned_bookings' => count($unassigned),
            'payment_plan_issues' => count($paymentIssues),
            'accepted_offer_clarifications' => count($acceptedOffers),
        ],
        'unassigned_bookings' => $unassigned,
        'payment_plan_issues' => $paymentIssues,
        'accepted_offer_clarifications' => $acceptedOffers,
    ]);
}


function bookings_v235(): never
{
    if (function_exists('ensure_delete_center_v23663')) ensure_delete_center_v23663();
    BookingWorkflowService::syncBillingState();
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
        $statusLine = v235_status_line($row);
        $row['workflow_status_label'] = $statusLine;
        $row['workflow_status_class'] = v235_status_class($row);
        $row['needs_repair'] = empty($row['apartment_id']) && !in_array((string)($row['status'] ?? ''), ['cancelled','rejected'], true);
        $row['payment_issue'] = v235_payment_issue_for_booking((int)$row['id']);
        if (!$row['needs_repair'] && $statusLine === status_text((string)($row['status'] ?? ''))) {
            $legacy = function_exists('booking_workflow_label_v232') ? booking_workflow_label_v232($row) : '';
            if ($legacy !== '') {
                $row['workflow_status_label'] = $legacy;
                $row['workflow_status_class'] = function_exists('booking_workflow_class_v232') ? booking_workflow_class_v232($row) : 'warning';
            }
        }
    }
    unset($row);
    json_response(['ok' => true, 'bookings' => $rows]);
}

function booking_repair_context_v235(): never
{
    $id = (int)($_GET['id'] ?? 0);
    $booking = v235_booking_full($id);
    BookingWorkflowService::syncBillingState($id);
    $booking = v235_booking_full($id);
    $sameType = v235_free_apartments_for_booking($booking, true, 80);
    $alternatives = v235_free_apartments_for_booking($booking, false, 120);
    $suggestions = v235_date_suggestions_for_booking($booking);
    $schedule = v235_payment_schedule($id);
    $paymentIssue = v235_payment_issue_for_booking($id);
    json_response([
        'ok' => true,
        'booking' => $booking,
        'same_type' => $sameType,
        'alternatives' => $alternatives,
        'date_suggestions' => $suggestions,
        'payment_schedule' => $schedule,
        'payment_issue' => $paymentIssue,
        'customer_url' => function_exists('v223_customer_url') ? v223_customer_url($id) : '',
    ]);
}

function assign_booking_apartment_v235(): never
{
    $d = request_data();
    $bookingId = (int)($d['booking_id'] ?? $d['id'] ?? 0);
    $apartmentId = (int)($d['apartment_id'] ?? 0);
    if ($bookingId <= 0 || $apartmentId <= 0) throw new ValidationException('Buchung und Apartment sind erforderlich.');
    $note = mb_substr(trim((string)($d['note'] ?? '')), 0, 1000);
    if ($note === '') $note = 'Konkrete Wohnung aus Datenreparatur/Statusklärung zugewiesen.';
    $notify = normalize_bool($d['notify_customer'] ?? 0);

    $old = fetch_booking_row($bookingId);
    if (!$old) throw new NotFoundException('Buchung nicht gefunden.');
    if (in_array((string)$old['status'], ['cancelled','rejected'], true)) throw new ValidationException('Stornierte/abgelehnte Buchungen werden nicht zugeordnet.');

    $stmt = db()->prepare("SELECT a.*,at.name apartment_type_name,at.max_occupancy type_max_occupancy FROM apartments a LEFT JOIN apartment_types at ON at.id=a.apartment_type_id WHERE a.id=? LIMIT 1");
    $stmt->execute([$apartmentId]);
    $apartment = $stmt->fetch();
    if (!$apartment) throw new NotFoundException('Apartment nicht gefunden.');
    if ((string)$apartment['status'] !== 'active' || (int)($apartment['out_of_service'] ?? 0) === 1) throw new ConflictException('Das Apartment ist nicht aktiv oder außer Betrieb.');
    if (booking_conflict($apartmentId, (string)$old['arrival'], (string)$old['departure'], $bookingId)) throw new ConflictException('Das Apartment ist in diesem Zeitraum bereits belegt oder gesperrt.');

    $oldTypeId = (int)($old['apartment_type_id'] ?? 0);
    $newTypeId = (int)($apartment['apartment_type_id'] ?? 0) ?: null;
    $isAlternative = $oldTypeId > 0 && $newTypeId !== null && $oldTypeId !== $newTypeId;
    $upgradeNote = trim((string)($old['upgrade_note'] ?? ''));
    if ($isAlternative) {
        $upgradeNote = $upgradeNote !== '' ? $upgradeNote . ' · ' . $note : 'Alternative/Upgrade: ' . $note;
    }
    $internalNotes = trim((string)($old['internal_notes'] ?? ''));
    $line = '[' . date('Y-m-d H:i') . '] Statusklärung: Apartment ' . ($apartment['code'] ?: $apartment['name']) . ' zugewiesen. ' . $note;
    $internalNotes = $internalNotes !== '' ? $internalNotes . "\n\n" . $line : $line;

    db()->beginTransaction();
    try {
        db()->prepare("UPDATE bookings SET apartment_id=?, apartment_type_id=COALESCE(?,apartment_type_id), is_upgrade=CASE WHEN ?=1 THEN 1 ELSE is_upgrade END, upgrade_note=CASE WHEN ?=1 THEN ? ELSE upgrade_note END, internal_notes=? WHERE id=?")
            ->execute([$apartmentId, $newTypeId, $isAlternative ? 1 : 0, $isAlternative ? 1 : 0, $upgradeNote ?: null, $internalNotes, $bookingId]);
        log_booking_change($bookingId, $isAlternative ? 'alternative_assigned' : 'apartment_assigned', $old, fetch_booking_row($bookingId), $isAlternative ? 'Alternative/Upgrade zugewiesen: ' . $note : 'Apartment zugewiesen: ' . $note);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }

    AuditLogger::record('booking', $bookingId, $isAlternative ? 'alternative_assigned' : 'apartment_assigned', $old, fetch_booking_row($bookingId), $note);
    try { HousekeepingWorkflow::upsertDepartureTask($bookingId); } catch (Throwable $e) { AppLogger::error($e, ['booking_id' => $bookingId], 'v235-housekeeping-upsert'); }
    BookingWorkflowService::syncBillingState($bookingId);

    $mail = ['sent' => false, 'message' => 'Benachrichtigung deaktiviert.'];
    if ($notify) {
        $message = 'Ihre Buchung wurde intern geklärt. Die Unterkunft wurde zugeordnet. Den aktuellen Stand finden Sie im Kundenbereich.';
        if ($isAlternative) $message = 'Ihre Buchung wurde intern mit einer passenden Alternative/einem Upgrade geklärt. Den aktuellen Stand finden Sie im Kundenbereich.';
        $mail = function_exists('v223_notify_customer') ? v223_notify_customer($bookingId, 'status', $message, []) : ['sent'=>false,'message'=>'E-Mail-Funktion nicht verfügbar.'];
    }

    json_response(['ok' => true, 'message' => ($isAlternative ? 'Alternative/Upgrade wurde zugewiesen.' : 'Apartment wurde zugewiesen.') . ($mail['sent'] ? ' Der Kunde wurde informiert.' : ''), 'email' => $mail, 'booking' => v235_booking_full($bookingId)]);
}

function set_booking_clarification_v235(): never
{
    $d = request_data();
    $bookingId = (int)($d['booking_id'] ?? $d['id'] ?? 0);
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    $note = mb_substr(trim((string)($d['note'] ?? '')), 0, 1500);
    if ($note === '') $note = 'Interne Klärung erforderlich: noch keine konkrete Wohnung zugeordnet.';
    $notify = normalize_bool($d['notify_customer'] ?? 0);
    $old = fetch_booking_row($bookingId);
    if (!$old) throw new NotFoundException('Buchung nicht gefunden.');
    $internalNotes = trim((string)($old['internal_notes'] ?? ''));
    $line = '[' . date('Y-m-d H:i') . '] Klärung erforderlich: ' . $note;
    $internalNotes = $internalNotes !== '' ? $internalNotes . "\n\n" . $line : $line;
    db()->prepare('UPDATE bookings SET internal_notes=? WHERE id=?')->execute([$internalNotes, $bookingId]);
    log_booking_change($bookingId, 'clarification_required', $old, fetch_booking_row($bookingId), $note);
    AuditLogger::record('booking', $bookingId, 'clarification_required', $old, fetch_booking_row($bookingId), $note);
    $mail = ['sent' => false, 'message' => 'Benachrichtigung deaktiviert.'];
    if ($notify) {
        $mail = function_exists('v223_notify_customer') ? v223_notify_customer($bookingId, 'status', 'Wir prüfen Ihre Buchung intern noch einmal und melden uns kurzfristig mit dem endgültigen Status. Der aktuelle Stand ist im Kundenbereich sichtbar.', []) : ['sent'=>false,'message'=>'E-Mail-Funktion nicht verfügbar.'];
    }
    json_response(['ok' => true, 'message' => 'Buchung wurde als „Klärung erforderlich“ dokumentiert.' . ($mail['sent'] ? ' Der Kunde wurde informiert.' : ''), 'email' => $mail, 'booking' => v235_booking_full($bookingId)]);
}

function reject_booking_clarification_v235(): never
{
    $d = request_data();
    $bookingId = (int)($d['booking_id'] ?? $d['id'] ?? 0);
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    $note = mb_substr(trim((string)($d['note'] ?? '')), 0, 1500);
    if ($note === '') $note = 'Buchung wegen fehlender Verfügbarkeit abgelehnt/archiviert.';
    $notify = normalize_bool($d['notify_customer'] ?? 0);
    $old = fetch_booking_row($bookingId);
    if (!$old) throw new NotFoundException('Buchung nicht gefunden.');
    db()->prepare("UPDATE bookings SET status='rejected', internal_notes=CONCAT(COALESCE(NULLIF(internal_notes,''),''), CASE WHEN COALESCE(internal_notes,'')<>'' THEN '\n\n' ELSE '' END, ?) WHERE id=?")
        ->execute(['[' . date('Y-m-d H:i') . '] Abgelehnt: ' . $note, $bookingId]);
    log_booking_change($bookingId, 'rejected_no_availability', $old, fetch_booking_row($bookingId), $note);
    AuditLogger::record('booking', $bookingId, 'rejected_no_availability', $old, fetch_booking_row($bookingId), $note);
    try { HousekeepingWorkflow::upsertDepartureTask($bookingId); } catch (Throwable $e) { AppLogger::error($e, ['booking_id' => $bookingId], 'v235-housekeeping-reject'); }
    $mail = ['sent' => false, 'message' => 'Benachrichtigung deaktiviert.'];
    if ($notify) {
        $mail = function_exists('v223_notify_customer') ? v223_notify_customer($bookingId, 'status', 'Leider können wir die Buchung im gewünschten Zeitraum nicht verbindlich bestätigen. Wir melden uns gerne mit einem neuen Vorschlag.', []) : ['sent'=>false,'message'=>'E-Mail-Funktion nicht verfügbar.'];
    }
    json_response(['ok' => true, 'message' => 'Buchung wurde abgelehnt/archiviert.' . ($mail['sent'] ? ' Der Kunde wurde informiert.' : ''), 'email' => $mail]);
}

function recalculate_payment_schedule_v235(): never
{
    $d = request_data();
    $bookingId = (int)($d['booking_id'] ?? $d['id'] ?? 0);
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    $booking = fetch_booking_row($bookingId);
    if (!$booking) throw new NotFoundException('Buchung nicht gefunden.');
    $oldSchedule = v235_payment_schedule($bookingId);
    $total = max(0.0, round((float)($booking['total_price'] ?? 0), 2));
    $deposit = 0.0;
    foreach ($oldSchedule as $row) if ((string)$row['installment_type'] === 'deposit') $deposit = max(0.0, round((float)$row['amount'], 2));
    if ($deposit <= 0.005) $deposit = max(0.0, min($total, round((float)($booking['deposit_amount'] ?? 0), 2)));
    if ($deposit > $total) $deposit = $total;
    $remaining = max(0.0, round($total - $deposit, 2));

    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $depositDue = valid_date((string)($booking['deposit_due_date'] ?? '')) ? (string)$booking['deposit_due_date'] : $today;
    $remainingDue = valid_date((string)($booking['remaining_due_date'] ?? '')) ? (string)$booking['remaining_due_date'] : v235_default_remaining_due((string)$booking['arrival']);
    if (valid_date($depositDue) && valid_date($remainingDue) && $remainingDue < $depositDue) $remainingDue = $depositDue;

    db()->beginTransaction();
    try {
        v235_upsert_schedule($bookingId, 'deposit', 'Anzahlung', $deposit, $depositDue, 10, $deposit <= 0.005 ? 'waived' : 'open');
        v235_upsert_schedule($bookingId, 'remaining', 'Restbetrag', $remaining, $remainingDue, 20, $remaining <= 0.005 ? 'received' : 'open');
        db()->prepare('UPDATE bookings SET deposit_amount=?,deposit_due_date=?,remaining_due_date=? WHERE id=?')->execute([$deposit, $depositDue, $remainingDue, $bookingId]);
        BookingWorkflowService::syncBillingState($bookingId);
        $newSchedule = v235_payment_schedule($bookingId);
        log_booking_change($bookingId, 'payment_schedule_repaired', ['schedule' => $oldSchedule], ['schedule' => $newSchedule], 'Zahlungsplan logisch neu abgeglichen');
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
    AuditLogger::record('booking', $bookingId, 'payment_schedule_repaired', ['schedule' => $oldSchedule], ['schedule' => v235_payment_schedule($bookingId)], 'Zahlungsplan repariert');
    json_response(['ok' => true, 'message' => 'Zahlungsplan wurde logisch abgeglichen.', 'booking' => v235_booking_full($bookingId), 'payment_schedule' => v235_payment_schedule($bookingId)]);
}

function send_booking_status_v235(): never
{
    $d = request_data();
    $bookingId = (int)($d['booking_id'] ?? $d['id'] ?? 0);
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    BookingWorkflowService::syncBillingState($bookingId);
    $booking = v235_booking_full($bookingId);
    $custom = trim((string)($d['message'] ?? ''));
    $statusLine = v235_status_line($booking);
    $message = $custom !== '' ? $custom : 'Der aktuelle Stand Ihrer Buchung wurde aktualisiert: ' . $statusLine . '. Den vollständigen Status finden Sie in Ihrem Kundenbereich.';
    $mail = function_exists('v223_notify_customer') ? v223_notify_customer($bookingId, 'status', $message, []) : ['sent'=>false,'message'=>'E-Mail-Funktion nicht verfügbar.'];
    json_response(['ok' => true, 'message' => $mail['sent'] ? 'Status-E-Mail wurde gesendet.' : 'Status-E-Mail konnte nicht gesendet werden: ' . $mail['message'], 'email' => $mail]);
}

function repair_offer_events_note_v235(): never
{
    try { db()->exec('ALTER TABLE offer_events ADD COLUMN note VARCHAR(255) NULL AFTER event_type'); } catch (Throwable $ignored) {}
    $ok = v235_column_exists('offer_events', 'note');
    json_response(['ok' => $ok, 'message' => $ok ? 'offer_events.note ist vorhanden.' : 'offer_events.note konnte nicht automatisch ergänzt werden.']);
}

function v235_unassigned_bookings(): array
{
    $base = booking_select();
    $base = preg_replace('/^SELECT b\.\*,/u', "SELECT b.*,so.offer_number source_offer_number,so.status source_offer_status,", $base, 1);
    $sql = $base . " LEFT JOIN offers so ON so.id=b.source_offer_id WHERE b.status NOT IN ('cancelled','rejected') AND b.apartment_id IS NULL ORDER BY b.arrival,b.id LIMIT 100";
    $rows = db()->query($sql)->fetchAll();
    foreach ($rows as &$row) {
        $row['workflow_status_label'] = v235_status_line($row);
        $row['workflow_status_class'] = 'warning';
    }
    unset($row);
    return $rows;
}

function v235_accepted_offer_clarifications(): array
{
    if (!v235_table_exists('offers')) return [];
    $sql = "SELECT o.id,o.offer_number,o.guest_name,o.guest_email,o.arrival,o.departure,o.status,o.accepted_at,o.internal_notes,at.name apartment_type_name,
            (SELECT e.event_type FROM offer_events e WHERE e.offer_id=o.id AND e.event_type LIKE 'no_availability_%' ORDER BY e.id DESC LIMIT 1) availability_event_type,
            (SELECT e.created_at FROM offer_events e WHERE e.offer_id=o.id AND e.event_type LIKE 'no_availability_%' ORDER BY e.id DESC LIMIT 1) availability_event_at
            FROM offers o
            LEFT JOIN apartment_types at ON at.id=o.apartment_type_id
            LEFT JOIN guests g ON g.id=o.guest_id
            WHERE o.status='accepted' AND o.booking_id IS NULL
              AND (o.deleted_at IS NULL OR o.deleted_at='0000-00-00 00:00:00')
              AND (g.id IS NULL OR g.deleted_at IS NULL OR g.deleted_at='0000-00-00 00:00:00')
              AND NOT EXISTS (SELECT 1 FROM guests gd WHERE gd.deleted_at IS NOT NULL AND gd.email IS NOT NULL AND gd.email<>'' AND o.guest_email IS NOT NULL AND o.guest_email<>'' AND LOWER(TRIM(gd.email))=LOWER(TRIM(o.guest_email)))
              AND COALESCE((SELECT e.event_type FROM offer_events e WHERE e.offer_id=o.id AND e.event_type LIKE 'no_availability_%' ORDER BY e.id DESC LIMIT 1),'')<>'no_availability_cancel'
            ORDER BY o.accepted_at,o.id LIMIT 100";
    try {
        $rows = db()->query($sql)->fetchAll();
        foreach ($rows as &$row) {
            $event = (string)($row['availability_event_type'] ?? '');
            $row['workflow_status_label'] = match ($event) {
                'no_availability_notify' => 'Gast informiert · Alternativen prüfen',
                'no_availability_hold' => 'Rückfrage / Warteliste',
                'no_availability_cancel' => 'Fehlende Verfügbarkeit · archiviert',
                default => 'Angenommen · interne Wohnungsprüfung offen',
            };
            $row['workflow_status_class'] = $event === 'no_availability_cancel' ? 'danger' : 'warning';
        }
        unset($row);
        return $rows;
    } catch (Throwable) { return []; }
}

function v235_payment_plan_issues(): array
{
    if (!v235_table_exists('booking_payment_schedule')) return [];
    $sql = "SELECT b.id,b.reference,b.arrival,b.departure,b.total_price,b.paid_amount,b.payment_status,
            d.due_date deposit_due,r.due_date remaining_due,d.amount deposit_amount,r.amount remaining_amount,d.status deposit_status,r.status remaining_status,
            TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name
            FROM bookings b JOIN guests g ON g.id=b.guest_id
            LEFT JOIN booking_payment_schedule d ON d.booking_id=b.id AND d.installment_type='deposit'
            LEFT JOIN booking_payment_schedule r ON r.booking_id=b.id AND r.installment_type='remaining'
            WHERE b.status NOT IN ('cancelled','rejected') AND d.due_date IS NOT NULL AND r.due_date IS NOT NULL AND r.due_date < d.due_date
            ORDER BY b.arrival,b.id LIMIT 100";
    try { return db()->query($sql)->fetchAll(); } catch (Throwable) { return []; }
}

function v235_payment_issue_for_booking(int $bookingId): ?array
{
    $stmt = db()->prepare("SELECT d.due_date deposit_due,r.due_date remaining_due,d.amount deposit_amount,r.amount remaining_amount,d.status deposit_status,r.status remaining_status
        FROM bookings b LEFT JOIN booking_payment_schedule d ON d.booking_id=b.id AND d.installment_type='deposit'
        LEFT JOIN booking_payment_schedule r ON r.booking_id=b.id AND r.installment_type='remaining' WHERE b.id=? LIMIT 1");
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $issue = !empty($row['deposit_due']) && !empty($row['remaining_due']) && (string)$row['remaining_due'] < (string)$row['deposit_due'];
    $row['has_issue'] = $issue;
    $row['message'] = $issue ? 'Restbetrag ist vor der Anzahlung fällig. Empfehlung: Restbetrag auf oder nach die Anzahlung setzen.' : 'Zahlungsplan wirkt logisch.';
    return $row;
}

function v235_booking_full(int $id): array
{
    if ($id <= 0) throw new ValidationException('Buchung fehlt.');
    $stmt = db()->prepare(booking_select() . ' WHERE b.id=? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) throw new NotFoundException('Buchung nicht gefunden.');
    $row['workflow_status_label'] = v235_status_line($row);
    $row['workflow_status_class'] = v235_status_class($row);
    return $row;
}

function v235_payment_schedule(int $bookingId): array
{
    $stmt = db()->prepare('SELECT * FROM booking_payment_schedule WHERE booking_id=? ORDER BY sort_order,id');
    $stmt->execute([$bookingId]);
    return $stmt->fetchAll();
}

function v235_free_apartments_for_booking(array $booking, bool $sameTypeOnly, int $limit = 80): array
{
    $people = (int)($booking['adults'] ?? 0) + (int)($booking['children'] ?? 0) + (int)($booking['babies'] ?? 0);
    $typeId = (int)($booking['apartment_type_id'] ?? $booking['effective_apartment_type_id'] ?? 0);
    $oldType = null;
    if ($typeId > 0) {
        $st = db()->prepare('SELECT id,name,standard_price,max_occupancy FROM apartment_types WHERE id=? LIMIT 1');
        $st->execute([$typeId]);
        $oldType = $st->fetch() ?: null;
    }
    $sql = "SELECT a.id,a.name,a.code,a.apartment_number,a.apartment_type_id,a.max_guests,h.name house_name,at.name apartment_type_name,at.standard_price,at.max_occupancy type_max_occupancy,at.bedrooms,at.beds,at.living_area
            FROM apartments a LEFT JOIN houses h ON h.id=a.house_id LEFT JOIN apartment_types at ON at.id=a.apartment_type_id
            WHERE a.status='active' AND a.out_of_service=0";
    $params = [];
    if ($sameTypeOnly && $typeId > 0) { $sql .= ' AND a.apartment_type_id=?'; $params[] = $typeId; }
    elseif (!$sameTypeOnly && $typeId > 0) { $sql .= ' AND a.apartment_type_id<>?'; $params[] = $typeId; }
    $sql .= ' ORDER BY at.sort_order,at.name,h.sort_order,h.name,a.sort_order,a.name LIMIT 300';
    $stmt = db()->prepare($sql); $stmt->execute($params);
    $rows = [];
    $nights = max(1, nights((string)$booking['arrival'], (string)$booking['departure']));
    foreach ($stmt->fetchAll() as $apartment) {
        if (booking_conflict((int)$apartment['id'], (string)$booking['arrival'], (string)$booking['departure'], (int)$booking['id'])) continue;
        $maxGuests = max(0, (int)($apartment['max_guests'] ?? 0));
        if ($maxGuests > 0 && $people > $maxGuests && !(int)($booking['capacity_override'] ?? 0)) continue;
        $mode = 'passend';
        if (!$sameTypeOnly) {
            $oldMax = max(0, (int)($oldType['max_occupancy'] ?? 0));
            $newMax = max((int)($apartment['type_max_occupancy'] ?? 0), $maxGuests);
            $mode = ($oldMax === 0 || $newMax >= $oldMax) ? 'upgrade' : 'alternative';
        }
        $oldPrice = (float)($oldType['standard_price'] ?? 0);
        $diff = round(((float)($apartment['standard_price'] ?? 0) - $oldPrice) * $nights, 2);
        $apartment['mode'] = $mode;
        $apartment['nights'] = $nights;
        $apartment['estimated_standard_diff'] = $diff;
        $rows[] = $apartment;
        if (count($rows) >= $limit) break;
    }
    return $rows;
}

function v235_date_suggestions_for_booking(array $booking, int $limit = 8): array
{
    $typeId = (int)($booking['apartment_type_id'] ?? 0);
    if ($typeId <= 0) return [];
    $arrival = new DateTimeImmutable((string)$booking['arrival']);
    $departure = new DateTimeImmutable((string)$booking['departure']);
    $nights = max(1, $arrival->diff($departure)->days);
    $people = (int)($booking['adults'] ?? 0) + (int)($booking['children'] ?? 0) + (int)($booking['babies'] ?? 0);
    $stmt = db()->prepare("SELECT a.id,a.name,a.code,a.apartment_number,a.max_guests,h.name house_name FROM apartments a LEFT JOIN houses h ON h.id=a.house_id WHERE a.apartment_type_id=? AND a.status='active' AND a.out_of_service=0 ORDER BY h.sort_order,h.name,a.sort_order,a.name");
    $stmt->execute([$typeId]);
    $apartments = $stmt->fetchAll();
    $today = new DateTimeImmutable('today');
    $suggestions = [];
    foreach (array_merge(range(-7, -1), range(1, 28)) as $offset) {
        $from = $arrival->modify(($offset >= 0 ? '+' : '') . $offset . ' days');
        if ($from < $today) continue;
        $to = $from->modify('+' . $nights . ' days');
        $free = [];
        foreach ($apartments as $apartment) {
            $maxGuests = max(0, (int)($apartment['max_guests'] ?? 0));
            if ($maxGuests > 0 && $people > $maxGuests && !(int)($booking['capacity_override'] ?? 0)) continue;
            if (!booking_conflict((int)$apartment['id'], $from->format('Y-m-d'), $to->format('Y-m-d'), (int)$booking['id'])) {
                $free[] = $apartment;
                if (count($free) >= 3) break;
            }
        }
        if ($free) {
            $suggestions[] = ['arrival' => $from->format('Y-m-d'), 'departure' => $to->format('Y-m-d'), 'offset_days' => $offset, 'free_count' => count($free), 'apartments' => $free];
            if (count($suggestions) >= $limit) break;
        }
    }
    return $suggestions;
}

function v235_upsert_schedule(int $bookingId, string $type, string $label, float $amount, ?string $dueDate, int $sortOrder, string $defaultStatus): void
{
    $stmt = db()->prepare('SELECT id FROM booking_payment_schedule WHERE booking_id=? AND installment_type=? LIMIT 1');
    $stmt->execute([$bookingId, $type]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    if ($id > 0) {
        db()->prepare('UPDATE booking_payment_schedule SET label=?,amount=?,due_date=?,sort_order=? WHERE id=?')->execute([$label, $amount, $dueDate, $sortOrder, $id]);
    } else {
        db()->prepare('INSERT INTO booking_payment_schedule(booking_id,installment_type,label,amount,due_date,status,paid_amount,sort_order) VALUES(?,?,?,?,?,?,0,?)')->execute([$bookingId, $type, $label, $amount, $dueDate, $defaultStatus, $sortOrder]);
    }
}

function v235_default_remaining_due(string $arrival): string
{
    $days = max(0, (int)setting('booking_default_remaining_due_days', 14));
    $candidate = (new DateTimeImmutable($arrival))->modify('-' . $days . ' days')->format('Y-m-d');
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    return $candidate < $today ? $today : $candidate;
}

function v235_status_line(array $booking): string
{
    $status = (string)($booking['status'] ?? '');
    if ($status === 'cancelled') return 'Storniert';
    if ($status === 'rejected') return 'Abgelehnt';
    if (empty($booking['apartment_id'])) {
        return 'Klärung erforderlich · keine konkrete Wohnung zugeordnet';
    }
    if ((int)($booking['is_upgrade'] ?? 0) === 1) return 'Alternative / Upgrade zugeordnet';
    return status_text($status);
}

function v235_status_class(array $booking): string
{
    $status = (string)($booking['status'] ?? '');
    if (in_array($status, ['cancelled','rejected'], true)) return 'danger';
    if (empty($booking['apartment_id'])) return 'warning';
    if ((int)($booking['is_upgrade'] ?? 0) === 1) return 'success';
    return 'ok';
}

function v235_table_exists(string $table): bool
{
    try { $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'); $stmt->execute([$table]); return (int)$stmt->fetchColumn() > 0; } catch (Throwable) { return false; }
}

function v235_column_exists(string $table, string $column): bool
{
    try { $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'); $stmt->execute([$table, $column]); return (int)$stmt->fetchColumn() > 0; } catch (Throwable) { return false; }
}
