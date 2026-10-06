<?php
declare(strict_types=1);

function checkin_overview_v228(): never
{
    $from = trim((string)($_GET['from'] ?? date('Y-m-d')));
    $to = trim((string)($_GET['to'] ?? date('Y-m-d', strtotime('+45 days'))));
    $status = trim((string)($_GET['status'] ?? ''));
    $q = trim((string)($_GET['q'] ?? ''));
    if (!valid_date($from) || !valid_date($to) || $from > $to) throw new ValidationException('Zeitraum ungültig.');
    $sql = booking_select() . " LEFT JOIN booking_checkins ci ON ci.booking_id=b.id WHERE b.status NOT IN ('cancelled','rejected') AND b.arrival BETWEEN ? AND ?";
    $params = [$from, $to];
    if ($q !== '') {
        $like = '%' . $q . '%';
        $sql .= " AND (b.reference LIKE ? OR CONCAT(g.first_name,' ',g.last_name) LIKE ? OR g.email LIKE ? OR g.phone LIKE ? OR a.name LIKE ? OR a.code LIKE ? OR at.name LIKE ?)";
        array_push($params,$like,$like,$like,$like,$like,$like,$like);
    }
    $sql .= ' ORDER BY b.arrival ASC,b.reference ASC LIMIT 800';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $out = [];
    $stats = ['open'=>0,'incomplete'=>0,'submitted'=>0,'reviewed'=>0,'missing'=>0,'uploads'=>0];
    foreach ($rows as $row) {
        $bundle = CheckinService::bundle((int)$row['id']);
        $summary = $bundle['checkin_summary'];
        $row['checkin_status'] = $summary['status'];
        $row['checkin_missing_fields'] = $summary['missing_fields'];
        $row['checkin_traveller_count'] = $summary['traveller_count'];
        $row['checkin_minor_count'] = $summary['minor_count'];
        $row['checkin_submitted_at'] = $bundle['checkin']['submitted_at'] ?? null;
        $row['checkin_reviewed_at'] = $bundle['checkin']['reviewed_at'] ?? null;
        $row['checkin_upload_count'] = count($bundle['checkin_uploads']);
        $row['checkin_missing_details'] = $summary['missing_details'];
        $stats[$summary['status']] = (int)($stats[$summary['status']] ?? 0) + 1;
        if ((int)$summary['missing_fields'] > 0) $stats['missing']++;
        $stats['uploads'] += count($bundle['checkin_uploads']);
        if ($status !== '' && $status !== 'all') {
            if ($status === 'missing' && (int)$summary['missing_fields'] === 0) continue;
            if ($status !== 'missing' && $summary['status'] !== $status) continue;
        }
        $out[] = $row;
    }
    json_response(['ok'=>true,'from'=>$from,'to'=>$to,'status'=>$status,'q'=>$q,'rows'=>$out,'stats'=>$stats]);
}

function checkin_detail_v228(): never
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) throw new ValidationException('Buchung fehlt.');
    $stmt = db()->prepare(booking_select() . " WHERE b.id=? AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') LIMIT 1");
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    if (!$booking) throw new NotFoundException('Buchung nicht gefunden.');
    $bundle = CheckinService::bundle($id);
    $smartArrivalSettings = [
        'enabled' => normalize_bool(setting('smart_arrival_enabled', 1)),
        'document_capture' => normalize_bool(setting('smart_arrival_document_capture', 1)),
        'keep_document_images' => normalize_bool(setting('smart_arrival_keep_document_images', 0)),
        'auto_fill_registration' => normalize_bool(setting('smart_arrival_auto_fill_registration', 1)),
        'housekeeping_link' => normalize_bool(setting('smart_arrival_housekeeping_link', 0)),
        'vehicle_enabled' => normalize_bool(setting('smart_arrival_vehicle_enabled', 1)),
        'pets_enabled' => normalize_bool(setting('smart_arrival_pets_enabled', 0)),
        'mrz_helper' => normalize_bool(setting('smart_arrival_mrz_helper', 1)),
        'ocr_prepare' => normalize_bool(setting('smart_arrival_ocr_prepare', 0)),
        'legal_note' => (string)setting('smart_arrival_legal_note', 'Ausweisbilder nur erfassen, wenn sie im Betrieb wirklich benoetigt werden. Datenschutzfreundlich ist: Daten uebernehmen, pruefen und Bildkopien nicht dauerhaft speichern.')
    ];
    json_response(['ok'=>true,'booking'=>$booking,'bundle'=>$bundle,'customer_url'=>v228_customer_url($id),'checkin_url'=>v228_checkin_url($id),'smart_arrival_settings'=>$smartArrivalSettings]);
}

function send_checkin_request_v228(): never
{
    $d = request_data();
    $bookingId = (int)($d['booking_id'] ?? 0);
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    $result = v228_send_checkin_email($bookingId, trim((string)($d['message'] ?? '')));
    json_response(['ok'=>true,'message'=>$result['sent'] ? 'Check-in-Link wurde per E-Mail gesendet.' : 'Check-in-E-Mail nicht bestätigt: '.$result['message'],'email'=>$result]);
}

function whatsapp_checkin_open_v228(): never
{
    $d = request_data();
    $bookingId = (int)($d['booking_id'] ?? 0);
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    $booking = v228_booking_row($bookingId);
    $phone = v228_phone((string)($booking['guest_phone'] ?? ''));
    if ($phone === '') throw new ValidationException('Für diesen Gast ist keine gültige WhatsApp-/Telefonnummer mit Landesvorwahl hinterlegt.');
    $url = v228_checkin_url($bookingId);
    if ($url === '') throw new ValidationException('Für diese Buchung gibt es noch keinen aktiven Kundenlink.');
    $message = trim((string)($d['message'] ?? ''));
    if ($message === '') {
        $message = "Guten Tag " . $booking['guest_name'] . ",\n\nbitte füllen Sie vor Ihrer Anreise den Online-Check-in für die Buchung " . $booking['reference'] . " aus.\n\nOnline-Check-in: " . $url . "\n\nMit freundlichen Grüßen\n" . (string)setting('property_name','StayPilot');
    }
    $wa = 'https://api.whatsapp.com/send?phone=' . $phone . '&text=' . rawurlencode($message);
    CommunicationLogger::record('whatsapp','booking',$bookingId,(string)$booking['guest_name'],(string)$booking['guest_phone'],'Online-Check-in',$message,'opened','WhatsApp-Link mit vorbereitetem Check-in-Text geöffnet; Versand erfolgt manuell.');
    json_response(['ok'=>true,'url'=>$wa,'message'=>'WhatsApp wurde mit vorbereitetem Check-in-Text geöffnet.']);
}

function upload_checkin_file_v228(): never
{
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    $stmt = db()->prepare('SELECT id FROM bookings WHERE id=? LIMIT 1');
    $stmt->execute([$bookingId]);
    if (!$stmt->fetchColumn()) throw new NotFoundException('Buchung nicht gefunden.');
    $upload = CheckinService::storeUpload($bookingId, $_FILES['checkin_file'] ?? [], (int)(Auth::user()['id'] ?? 0), false, trim((string)($_POST['upload_note'] ?? '')));
    json_response(['ok'=>true,'message'=>'Check-in-Datei wurde gespeichert.','upload'=>$upload]);
}

function delete_checkin_file_v228(): never
{
    $d = request_data();
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) throw new ValidationException('Datei fehlt.');
    CheckinService::deleteUpload($id, (int)(Auth::user()['id'] ?? 0));
    json_response(['ok'=>true,'message'=>'Check-in-Datei wurde gelöscht.']);
}

function review_checkin_v228(): never
{
    $d = request_data();
    $bookingId = (int)($d['booking_id'] ?? 0);
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    $bundle = CheckinService::markReviewed($bookingId, trim((string)($d['review_note'] ?? '')), (int)(Auth::user()['id'] ?? 0));
    $mail = ['sent'=>false,'message'=>'Benachrichtigung deaktiviert.'];
    if (normalize_bool($d['notify_customer'] ?? 0)) {
        $mail = v228_notify_checkin_reviewed($bookingId);
    }
    json_response(['ok'=>true,'message'=>'Check-in wurde geprüft markiert.'.($mail['sent']?' Der Kunde wurde informiert.':''),'bundle'=>$bundle,'email'=>$mail]);
}

function v228_booking_row(int $bookingId): array
{
    $stmt = db()->prepare("SELECT b.*,TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,g.email guest_email,g.phone guest_phone,g.language guest_language,
        at.name apartment_type_name,a.name apartment_name,a.code apartment_code,o.currency
        FROM bookings b JOIN guests g ON g.id=b.guest_id LEFT JOIN apartment_types at ON at.id=b.apartment_type_id LEFT JOIN apartments a ON a.id=b.apartment_id LEFT JOIN offers o ON o.id=b.source_offer_id WHERE b.id=? AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') LIMIT 1");
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch();
    if (!$row) throw new NotFoundException('Buchung nicht gefunden.');
    return $row;
}

function v228_customer_url(int $bookingId): string
{
    $stmt = db()->prepare('SELECT token_encrypted FROM booking_customer_access WHERE booking_id=? AND active=1 LIMIT 1');
    $stmt->execute([$bookingId]);
    $token = Crypto::decrypt($stmt->fetchColumn() ?: null);
    return $token !== '' ? HousekeepingWorkflow::applicationUrl('kunde.php?token=' . rawurlencode($token)) : '';
}

function v228_checkin_url(int $bookingId): string
{
    $stmt = db()->prepare('SELECT token_encrypted FROM booking_customer_access WHERE booking_id=? AND active=1 LIMIT 1');
    $stmt->execute([$bookingId]);
    $token = Crypto::decrypt($stmt->fetchColumn() ?: null);
    return $token !== '' ? HousekeepingWorkflow::applicationUrl('checkin.php?token=' . rawurlencode($token)) : '';
}

function v228_send_checkin_email(int $bookingId, string $message = ''): array
{
    $booking = v228_booking_row($bookingId);
    $email = trim((string)($booking['guest_email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['sent'=>false,'message'=>'Keine gültige Kunden-E-Mail-Adresse hinterlegt.'];
    $url = v228_checkin_url($bookingId);
    if ($url === '') return ['sent'=>false,'message'=>'Für diese Buchung gibt es noch keinen aktiven Kundenlink.'];
    $subject = 'Online-Check-in für Ihre Buchung ' . $booking['reference'];
    $intro = $message !== '' ? $message : 'Bitte füllen Sie den Online-Check-in vor Ihrer Anreise aus. Sie können dort auch ein Foto oder PDF eines handschriftlichen Formulars hochladen.';
    $text = "Guten Tag " . $booking['guest_name'] . ",\n\n" . $intro . "\n\nBuchung: " . $booking['reference'] . "\nAufenthalt: " . $booking['arrival'] . " bis " . $booking['departure'] . "\n\nOnline-Check-in:\n" . $url . "\n\nMit freundlichen Grüßen\n" . (string)setting('property_name','StayPilot');
    $html = '<p>Guten Tag ' . e((string)$booking['guest_name']) . ',</p><p>' . nl2br(e($intro)) . '</p><p><b>Buchung:</b> ' . e((string)$booking['reference']) . '<br><b>Aufenthalt:</b> ' . e((string)$booking['arrival']) . ' – ' . e((string)$booking['departure']) . '</p><p><a href="' . e($url) . '" style="display:inline-block;background:#2563eb;color:white;padding:12px 18px;border-radius:12px;text-decoration:none;font-weight:bold">Online-Check-in öffnen</a></p><p>Mit freundlichen Grüßen<br>' . e((string)setting('property_name','StayPilot')) . '</p>';
    try {
        $result = SmtpMailer::send($email, $subject, $text, $html);
        CommunicationLogger::record('email','booking',$bookingId,(string)$booking['guest_name'],$email,$subject,$text,'sent',(string)($result['message_id'] ?? ''));
        return ['sent'=>true,'message'=>'gesendet'];
    } catch (Throwable $e) {
        CommunicationLogger::record('email','booking',$bookingId,(string)$booking['guest_name'],$email,$subject,$text,'failed',$e->getMessage());
        AppLogger::error($e,['booking_id'=>$bookingId],'checkin-email');
        return ['sent'=>false,'message'=>$e->getMessage()];
    }
}

function v228_notify_checkin_reviewed(int $bookingId): array
{
    $booking = v228_booking_row($bookingId);
    $email = trim((string)($booking['guest_email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['sent'=>false,'message'=>'Keine gültige Kunden-E-Mail-Adresse hinterlegt.'];
    $url = v228_customer_url($bookingId);
    $subject = 'Check-in geprüft – Buchung ' . $booking['reference'];
    $text = "Guten Tag " . $booking['guest_name'] . ",\n\nIhr Online-Check-in wurde geprüft. Der aktuelle Status ist im Kundenbereich sichtbar." . ($url ? "\n\nKundenbereich:\n" . $url : '') . "\n\nMit freundlichen Grüßen\n" . (string)setting('property_name','StayPilot');
    $html = '<p>Guten Tag ' . e((string)$booking['guest_name']) . ',</p><p>Ihr Online-Check-in wurde geprüft. Der aktuelle Status ist im Kundenbereich sichtbar.</p>' . ($url ? '<p><a href="' . e($url) . '">Kundenbereich öffnen</a></p>' : '') . '<p>Mit freundlichen Grüßen<br>' . e((string)setting('property_name','StayPilot')) . '</p>';
    try {
        $result = SmtpMailer::send($email, $subject, $text, $html);
        CommunicationLogger::record('email','booking',$bookingId,(string)$booking['guest_name'],$email,$subject,$text,'sent',(string)($result['message_id'] ?? ''));
        return ['sent'=>true,'message'=>'gesendet'];
    } catch (Throwable $e) {
        CommunicationLogger::record('email','booking',$bookingId,(string)$booking['guest_name'],$email,$subject,$text,'failed',$e->getMessage());
        AppLogger::error($e,['booking_id'=>$bookingId],'checkin-reviewed-email');
        return ['sent'=>false,'message'=>$e->getMessage()];
    }
}

function v228_phone(string $phone): string
{
    $n = preg_replace('/[^0-9+]/','',$phone) ?? '';
    if (str_starts_with($n,'00')) $n = substr($n,2);
    $n = ltrim($n,'+');
    return strlen($n) >= 7 ? $n : '';
}
