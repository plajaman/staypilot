<?php
declare(strict_types=1);

final class CheckinDocumentService
{
    private const CHECKIN_TYPES = ['checkin_summary','registration_form'];

    public static function documentsForBooking(int $bookingId): array
    {
        $stmt = db()->prepare("SELECT id,document_type,document_number,language,title,pdf_path,status,generated_at,sent_at,created_at FROM booking_documents WHERE booking_id=? AND document_type IN ('checkin_summary','registration_form') ORDER BY id DESC");
        $stmt->execute([$bookingId]);
        return $stmt->fetchAll();
    }

    public static function generate(int $bookingId, array $options = [], ?int $userId = null): array
    {
        $booking = self::bookingRow($bookingId);
        $bundle = CheckinService::bundle($bookingId);
        $summary = $bundle['checkin_summary'] ?? [];
        $language = self::language((string)($booking['public_language'] ?? 'de'));
        $created = [];
        $createSummary = self::bool($options['create_summary'] ?? 1);
        $createRegistration = self::bool($options['create_registration'] ?? 1);
        $force = self::bool($options['force'] ?? 0);

        if ($createSummary && ($force || !self::hasRecentDocument($bookingId, 'checkin_summary'))) {
            $title = 'Check-in-Zusammenfassung · ' . (string)$booking['reference'];
            $number = self::nextNumber('checkin_summary', 'CHK');
            $html = self::renderCheckinSummaryHtml($booking, $bundle, $language);
            $lines = self::checkinSummaryPdfLines($booking, $bundle, $language);
            $created[] = self::insertDocument($bookingId, 'checkin_summary', $number, $language, $title, $html, $lines, $userId);
        }
        if ($createRegistration && ($force || !self::hasRecentDocument($bookingId, 'registration_form'))) {
            $title = 'Meldeschein / Gästeregister · ' . (string)$booking['reference'];
            $number = self::nextNumber('registration_form', 'MELD');
            $html = self::renderRegistrationHtml($booking, $bundle, $language);
            $lines = self::registrationPdfLines($booking, $bundle, $language);
            $created[] = self::insertDocument($bookingId, 'registration_form', $number, $language, $title, $html, $lines, $userId);
        }

        db()->prepare('INSERT INTO booking_change_log(booking_id,action,new_values_json,note,created_by) VALUES(?,?,?,?,?)')
            ->execute([$bookingId, 'checkin_documents_generated', json_encode(['created_document_ids'=>array_column($created,'id'),'missing_fields'=>(int)($summary['missing_fields'] ?? 0)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 'Check-in-/Meldeschein-Dokumente erzeugt', $userId]);

        return ['created' => $created, 'documents' => self::documentsForBooking($bookingId), 'summary' => $summary];
    }

    public static function sendToCustomer(int $bookingId, array $documentIds = [], string $message = ''): array
    {
        $booking = self::bookingRow($bookingId);
        $email = trim((string)($booking['guest_email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['sent'=>false,'message'=>'Keine gültige Kunden-E-Mail-Adresse hinterlegt.'];
        if (!$documentIds) {
            $docs = self::documentsForBooking($bookingId);
            if (!$docs) $docs = self::generate($bookingId, [], (int)(Auth::user()['id'] ?? 0))['documents'];
        } else {
            $placeholders = implode(',', array_fill(0, count($documentIds), '?'));
            $params = array_merge([$bookingId], array_map('intval', $documentIds));
            $stmt = db()->prepare("SELECT * FROM booking_documents WHERE booking_id=? AND id IN ($placeholders) AND document_type IN ('checkin_summary','registration_form') ORDER BY id DESC");
            $stmt->execute($params);
            $docs = $stmt->fetchAll();
        }
        if (!$docs) return ['sent'=>false,'message'=>'Es sind noch keine Check-in-Dokumente vorhanden.'];
        $attachments = [];
        foreach ($docs as $doc) {
            $path = trim((string)($doc['pdf_path'] ?? ''));
            if ($path !== '' && is_file(root_path($path))) {
                $attachments[] = ['path'=>root_path($path),'name'=>self::safeFilename((string)($doc['document_number'] ?: $doc['title'])) . '.pdf','mime'=>'application/pdf'];
            }
        }
        if (!$attachments) return ['sent'=>false,'message'=>'Für die ausgewählten Dokumente sind keine lesbaren PDF-Dateien vorhanden. Bitte Dokumente neu erzeugen.'];
        $url = self::customerUrl($bookingId);
        $subject = 'Check-in-Dokumente zu Ihrer Buchung ' . (string)$booking['reference'];
        $intro = trim($message) !== '' ? trim($message) : 'anbei erhalten Sie die aktuellen Check-in-/Meldeschein-Dokumente zu Ihrer Buchung. Die Dokumente stehen zusätzlich im sicheren Kundenbereich bereit.';
        $text = "Guten Tag " . (string)$booking['guest_name'] . ",\n\n" . $intro . "\n\nBuchung: " . (string)$booking['reference'] . "\nAufenthalt: " . (string)$booking['arrival'] . " bis " . (string)$booking['departure'] . ($url !== '' ? "\n\nKundenbereich:\n" . $url : '') . "\n\nMit freundlichen Grüßen\n" . (string)setting('property_name','StayPilot');
        $html = '<p>Guten Tag ' . e((string)$booking['guest_name']) . ',</p><p>' . nl2br(e($intro)) . '</p><p><b>Buchung:</b> ' . e((string)$booking['reference']) . '<br><b>Aufenthalt:</b> ' . e((string)$booking['arrival']) . ' – ' . e((string)$booking['departure']) . '</p>' . ($url !== '' ? '<p><a href="' . e($url) . '">Kundenbereich öffnen</a></p>' : '') . '<p>Mit freundlichen Grüßen<br>' . e((string)setting('property_name','StayPilot')) . '</p>';
        try {
            $result = SmtpMailer::send($email, $subject, $text, $html, $attachments);
            CommunicationLogger::record('email','booking',$bookingId,(string)$booking['guest_name'],$email,$subject,$text,'sent',(string)($result['message_id'] ?? ''));
            $ids = array_map(static fn(array $d): int => (int)$d['id'], $docs);
            if ($ids) {
                $marks = implode(',', array_fill(0, count($ids), '?'));
                db()->prepare("UPDATE booking_documents SET sent_at=NOW(),status='sent' WHERE id IN ($marks)")->execute($ids);
            }
            return ['sent'=>true,'message'=>'gesendet','attachments'=>count($attachments)];
        } catch (Throwable $e) {
            CommunicationLogger::record('email','booking',$bookingId,(string)$booking['guest_name'],$email,$subject,$text,'failed',$e->getMessage());
            AppLogger::error($e, ['booking_id'=>$bookingId], 'checkin-documents-email');
            return ['sent'=>false,'message'=>$e->getMessage()];
        }
    }

    public static function communicationStatus(int $bookingId): array
    {
        $booking = self::bookingRow($bookingId);
        $smtp = SmtpMailer::settings(false);
        $customerUrl = self::customerUrl($bookingId);
        $checkinUrl = self::checkinUrl($bookingId);
        $hints = [];
        $ok = true;
        if (!(int)($smtp['active'] ?? 0)) { $ok = false; $hints[] = 'SMTP ist nicht aktiviert.'; }
        if (trim((string)($smtp['host'] ?? '')) === '') { $ok = false; $hints[] = 'SMTP-Server fehlt.'; }
        if (trim((string)($smtp['from_email'] ?? '')) === '' || !filter_var((string)$smtp['from_email'], FILTER_VALIDATE_EMAIL)) { $ok = false; $hints[] = 'SMTP-Absenderadresse fehlt oder ist ungültig.'; }
        if (trim((string)($booking['guest_email'] ?? '')) === '' || !filter_var((string)$booking['guest_email'], FILTER_VALIDATE_EMAIL)) { $ok = false; $hints[] = 'Beim Gast ist keine gültige E-Mail-Adresse hinterlegt.'; }
        if ($customerUrl === '') { $ok = false; $hints[] = 'Für diese Buchung gibt es noch keinen aktiven Kundenlink.'; }
        if (trim((string)($booking['guest_phone'] ?? '')) === '') $hints[] = 'Keine Telefonnummer für WhatsApp hinterlegt.';
        $fromDomain = self::domain((string)($smtp['from_email'] ?? ''));
        if ($fromDomain !== '') $hints[] = 'Spam-Prüfung: SPF, DKIM und DMARC für ' . $fromDomain . ' beim Domain-/Mailanbieter prüfen.';
        $stmt = db()->prepare("SELECT channel,subject,status,detail,created_at FROM communication_log WHERE entity_type='booking' AND entity_id=? ORDER BY id DESC LIMIT 8");
        $stmt->execute([(string)$bookingId]);
        return [
            'ok' => $ok,
            'booking_id' => $bookingId,
            'guest_email' => (string)($booking['guest_email'] ?? ''),
            'guest_phone' => (string)($booking['guest_phone'] ?? ''),
            'customer_url' => $customerUrl,
            'checkin_url' => $checkinUrl,
            'smtp' => [
                'active' => (int)($smtp['active'] ?? 0),
                'host' => (string)($smtp['host'] ?? ''),
                'port' => (int)($smtp['port'] ?? 0),
                'encryption' => (string)($smtp['encryption'] ?? ''),
                'from_email' => (string)($smtp['from_email'] ?? ''),
                'reply_to' => (string)($smtp['reply_to'] ?? ''),
                'has_password' => !empty($smtp['has_password']),
            ],
            'hints' => $hints,
            'recent_log' => $stmt->fetchAll(),
        ];
    }

    public static function bookingRow(int $bookingId): array
    {
        $stmt = db()->prepare("SELECT b.*,TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,g.email guest_email,g.phone guest_phone,g.language guest_language,g.country guest_country,
            at.name apartment_type_name,a.name apartment_name,a.code apartment_code,o.currency
            FROM bookings b JOIN guests g ON g.id=b.guest_id LEFT JOIN apartment_types at ON at.id=b.apartment_type_id LEFT JOIN apartments a ON a.id=b.apartment_id LEFT JOIN offers o ON o.id=b.source_offer_id WHERE b.id=? LIMIT 1");
        $stmt->execute([$bookingId]);
        $row = $stmt->fetch();
        if (!$row) throw new NotFoundException('Buchung nicht gefunden.');
        return $row;
    }

    public static function renderCheckinSummaryHtml(array $booking, array $bundle, string $language = 'de'): string
    {
        $summary = $bundle['checkin_summary'] ?? [];
        $checkin = $bundle['checkin'] ?? [];
        $travellers = $bundle['travellers'] ?? [];
        $missing = $summary['missing_details'] ?? [];
        $rows = '';
        foreach ($travellers as $idx => $t) {
            $rows .= '<tr><td>' . ($idx + 1) . '</td><td>' . e(trim((string)($t['first_name'] ?? '') . ' ' . (string)($t['last_name'] ?? '') . ' ' . (string)($t['second_last_name'] ?? ''))) . '</td><td>' . e((string)($t['date_of_birth'] ?? '')) . '</td><td>' . e((string)($t['nationality'] ?? '')) . '</td><td>' . e((string)($t['document_type'] ?? '') . ' ' . (string)($t['document_number'] ?? '')) . '</td><td>' . ((int)($t['minor'] ?? 0) ? 'Ja' : 'Nein') . '</td></tr>';
        }
        if ($rows === '') $rows = '<tr><td colspan="6">Noch keine Reisenden erfasst.</td></tr>';
        $missingHtml = $missing ? '<ul><li>' . implode('</li><li>', array_map('e', array_map('strval', $missing))) . '</li></ul>' : '<p class="ok">Keine fehlenden Pflichtangaben erkannt.</p>';
        return self::htmlShell('Check-in-Zusammenfassung', '<section class="hero"><small>Buchung ' . e((string)$booking['reference']) . '</small><h1>Check-in-Zusammenfassung</h1><p>' . e((string)$booking['guest_name']) . ' · ' . e((string)$booking['arrival']) . ' – ' . e((string)$booking['departure']) . '</p></section><section class="grid"><div><b>Status</b><span>' . e((string)($summary['status'] ?? 'open')) . '</span></div><div><b>Reisende</b><span>' . e((string)($summary['traveller_count'] ?? 0)) . '</span></div><div><b>Fehlend</b><span>' . e((string)($summary['missing_fields'] ?? 0)) . '</span></div><div><b>Anreisezeit</b><span>' . e(self::time5((string)($checkin['planned_arrival_time'] ?? $booking['planned_arrival_time'] ?? ''))) . '</span></div><div><b>Kennzeichen</b><span>' . e((string)($checkin['vehicle_plate'] ?? $booking['vehicle_plate'] ?? '–')) . '</span></div><div><b>Geprüft</b><span>' . e((string)($checkin['reviewed_at'] ?? '–')) . '</span></div></section><section><h2>Fehlende Angaben</h2>' . $missingHtml . '</section><section><h2>Reisende Personen</h2><table><thead><tr><th>#</th><th>Name</th><th>Geburt</th><th>Nationalität</th><th>Dokument</th><th>Kind</th></tr></thead><tbody>' . $rows . '</tbody></table></section><section><h2>Hinweise</h2><p>' . nl2br(e((string)($checkin['special_requests'] ?? $booking['special_requests'] ?? $booking['guest_request'] ?? ''))) . '</p></section>');
    }

    public static function renderRegistrationHtml(array $booking, array $bundle, string $language = 'de'): string
    {
        $travellers = $bundle['travellers'] ?? [];
        $checkin = $bundle['checkin'] ?? [];
        $cards = '';
        foreach ($travellers as $idx => $t) {
            $cards .= '<article class="traveller"><h2>Reisende Person ' . ($idx + 1) . ((int)($t['is_primary'] ?? 0) ? ' · Hauptgast' : '') . '</h2><div class="fields"><p><b>Name</b>' . e(trim((string)($t['first_name'] ?? '') . ' ' . (string)($t['last_name'] ?? '') . ' ' . (string)($t['second_last_name'] ?? ''))) . '</p><p><b>Geburtsdatum / Geschlecht</b>' . e((string)($t['date_of_birth'] ?? '') . ' · ' . (string)($t['gender'] ?? '')) . '</p><p><b>Dokument</b>' . e((string)($t['document_type'] ?? '') . ' · ' . (string)($t['document_number'] ?? '')) . '</p><p><b>Nationalität</b>' . e((string)($t['nationality'] ?? '')) . '</p><p><b>Anschrift</b>' . e((string)($t['address'] ?? '') . ', ' . (string)($t['postal_code'] ?? '') . ' ' . (string)($t['city'] ?? '') . ', ' . (string)($t['country'] ?? '')) . '</p><p><b>Kontakt</b>' . e((string)($t['mobile_phone'] ?? '') . ' · ' . (string)($t['email'] ?? '')) . '</p></div><div class="sign"><div><b>Unterschrift</b><span></span></div><div><b>Datum</b><span></span></div></div></article>';
        }
        if ($cards === '') $cards = '<p>Noch keine Reisenden erfasst.</p>';
        return self::htmlShell('Meldeschein / Gästeregister', '<section class="hero"><small>' . e((string)setting('property_name','StayPilot')) . '</small><h1>Meldeschein / Gästeregister</h1><p>Buchung ' . e((string)$booking['reference']) . ' · ' . e((string)$booking['arrival']) . ' – ' . e((string)$booking['departure']) . '</p></section><section class="grid"><div><b>Gast</b><span>' . e((string)$booking['guest_name']) . '</span></div><div><b>Unterkunft</b><span>' . e((string)($booking['apartment_code'] ?: '–') . ' · ' . (string)($booking['apartment_name'] ?: $booking['apartment_type_name'])) . '</span></div><div><b>Anreisezeit</b><span>' . e(self::time5((string)($checkin['planned_arrival_time'] ?? $booking['planned_arrival_time'] ?? ''))) . '</span></div><div><b>Kennzeichen</b><span>' . e((string)($checkin['vehicle_plate'] ?? $booking['vehicle_plate'] ?? '–')) . '</span></div></section>' . $cards . '<section class="legal">Interne Druck- und Dokumentationshilfe. Vor behördlicher Übermittlung sind Vollständigkeit und aktuell geltende lokale Vorgaben zu prüfen.</section>');
    }

    private static function insertDocument(int $bookingId, string $type, string $number, string $language, string $title, string $html, array $lines, ?int $userId): array
    {
        $dir = root_path('storage/documents/checkin');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Dokumentenordner konnte nicht angelegt werden.');
        if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
        db()->prepare('INSERT INTO booking_documents(booking_id,document_type,document_number,language,title,html_snapshot,status,created_by) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$bookingId, $type, $number, $language, $title, $html, 'generated', $userId]);
        $id = (int)db()->lastInsertId();
        $pdf = SimplePdf::create($title, $lines);
        $relative = 'storage/documents/checkin/' . self::safeFilename($number) . '.pdf';
        if (@file_put_contents(root_path($relative), $pdf, LOCK_EX) === false) throw new RuntimeException('PDF konnte nicht gespeichert werden.');
        db()->prepare('UPDATE booking_documents SET pdf_path=?,checksum_sha256=? WHERE id=?')->execute([$relative, hash_file('sha256', root_path($relative)) ?: null, $id]);
        $stmt = db()->prepare('SELECT id,document_type,document_number,language,title,pdf_path,status,generated_at,sent_at,created_at FROM booking_documents WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: ['id'=>$id,'document_type'=>$type,'document_number'=>$number,'title'=>$title,'pdf_path'=>$relative];
    }

    private static function checkinSummaryPdfLines(array $booking, array $bundle, string $language): array
    {
        $s = $bundle['checkin_summary'] ?? [];
        $c = $bundle['checkin'] ?? [];
        $lines = [
            ['text'=>'Check-in-Zusammenfassung', 'size'=>15, 'bold'=>true, 'space'=>5],
            ['text'=>'Buchung: ' . (string)$booking['reference'], 'size'=>12, 'bold'=>true, 'space'=>3],
            'Gast: ' . (string)$booking['guest_name'],
            'Aufenthalt: ' . (string)$booking['arrival'] . ' bis ' . (string)$booking['departure'],
            'Wohnung: ' . (string)($booking['apartment_code'] ?: '–') . ' ' . (string)($booking['apartment_name'] ?: $booking['apartment_type_name']),
            'Check-in-Status: ' . (string)($s['status'] ?? 'open') . ' · fehlende Angaben: ' . (string)($s['missing_fields'] ?? 0),
            'Anreisezeit: ' . self::time5((string)($c['planned_arrival_time'] ?? $booking['planned_arrival_time'] ?? '')) . ' · Kennzeichen: ' . (string)($c['vehicle_plate'] ?? $booking['vehicle_plate'] ?? '–'),
            ['text'=>'', 'space'=>4],
            ['text'=>'Fehlende Angaben', 'bold'=>true],
        ];
        $missing = $s['missing_details'] ?? [];
        if (!$missing) $lines[] = 'Keine fehlenden Pflichtangaben erkannt.';
        foreach ($missing as $m) $lines[] = '- ' . (string)$m;
        $lines[] = ['text'=>'', 'space'=>4];
        $lines[] = ['text'=>'Reisende Personen', 'bold'=>true];
        foreach (($bundle['travellers'] ?? []) as $idx => $t) {
            $lines[] = ($idx + 1) . '. ' . trim((string)($t['first_name'] ?? '') . ' ' . (string)($t['last_name'] ?? '')) . ' · ' . (string)($t['date_of_birth'] ?? '') . ' · ' . (string)($t['nationality'] ?? '') . ' · ' . (string)($t['document_type'] ?? '') . ' ' . (string)($t['document_number'] ?? '');
        }
        return $lines;
    }

    private static function registrationPdfLines(array $booking, array $bundle, string $language): array
    {
        $c = $bundle['checkin'] ?? [];
        $lines = [
            ['text'=>'Meldeschein / Gästeregister', 'size'=>15, 'bold'=>true, 'space'=>5],
            'Buchung: ' . (string)$booking['reference'],
            'Aufenthalt: ' . (string)$booking['arrival'] . ' bis ' . (string)$booking['departure'],
            'Unterkunft: ' . (string)($booking['apartment_code'] ?: '–') . ' ' . (string)($booking['apartment_name'] ?: $booking['apartment_type_name']),
            'Anreisezeit: ' . self::time5((string)($c['planned_arrival_time'] ?? $booking['planned_arrival_time'] ?? '')),
            'Kennzeichen: ' . (string)($c['vehicle_plate'] ?? $booking['vehicle_plate'] ?? '–'),
            ['text'=>'', 'space'=>4],
        ];
        foreach (($bundle['travellers'] ?? []) as $idx => $t) {
            $lines[] = ['text'=>'Reisende Person ' . ($idx + 1) . ((int)($t['is_primary'] ?? 0) ? ' · Hauptgast' : ''), 'bold'=>true, 'space'=>2];
            $lines[] = 'Name: ' . trim((string)($t['first_name'] ?? '') . ' ' . (string)($t['last_name'] ?? '') . ' ' . (string)($t['second_last_name'] ?? ''));
            $lines[] = 'Geburtsdatum / Geschlecht: ' . (string)($t['date_of_birth'] ?? '') . ' · ' . (string)($t['gender'] ?? '');
            $lines[] = 'Dokument: ' . (string)($t['document_type'] ?? '') . ' · ' . (string)($t['document_number'] ?? '');
            $lines[] = 'Nationalität: ' . (string)($t['nationality'] ?? '');
            $lines[] = 'Anschrift: ' . (string)($t['address'] ?? '') . ', ' . (string)($t['postal_code'] ?? '') . ' ' . (string)($t['city'] ?? '') . ', ' . (string)($t['country'] ?? '');
            $lines[] = 'Unterschrift: ________________________________   Datum: ________________';
            $lines[] = ['text'=>'', 'space'=>4];
        }
        $lines[] = 'Interne Druck- und Dokumentationshilfe. Vor behördlicher Übermittlung bitte Vollständigkeit und lokale Vorgaben prüfen.';
        return $lines;
    }

    private static function htmlShell(string $title, string $body): string
    {
        return '<!doctype html><html lang="de"><head><meta charset="utf-8"><title>' . e($title) . '</title><style>@page{size:A4;margin:18mm}body{font-family:Arial,sans-serif;margin:0;background:#eef3f8;color:#172033;line-height:1.45}.wrap{max-width:940px;margin:24px auto;background:white;border-radius:18px;padding:34px;box-shadow:0 18px 50px rgba(15,23,42,.12)}.hero{border-bottom:3px solid #2563eb;margin-bottom:18px;padding-bottom:14px}.hero small{color:#64748b;text-transform:uppercase;letter-spacing:.08em}.hero h1{margin:4px 0;color:#1d4ed8;font-size:30px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:14px 0}.grid div,.legal,.traveller{border:1px solid #dbe3ee;background:#f8fafc;border-radius:14px;padding:14px;break-inside:avoid}.grid b{display:block;color:#64748b;font-size:12px;text-transform:uppercase}.grid span{font-weight:bold}section{break-inside:avoid;margin:16px 0}table{width:100%;border-collapse:collapse;font-size:13px}th,td{border:1px solid #dbe3ee;padding:8px;text-align:left;vertical-align:top}th{background:#eef4ff;color:#1d4ed8}.ok{color:#15803d;font-weight:bold}.fields{display:grid;grid-template-columns:1fr 1fr;gap:10px}.fields p{margin:0;background:white;border:1px solid #dbe3ee;border-radius:10px;padding:9px}.fields b{display:block;color:#64748b;font-size:12px}.sign{display:grid;grid-template-columns:1fr 180px;gap:30px;margin-top:20px}.sign span{display:block;height:42px;border-bottom:1px solid #111}.legal{font-size:12px;color:#64748b}.created{color:#64748b;margin-top:24px;border-top:1px solid #e2e8f0;padding-top:10px;font-size:12px}@media print{body{background:white}.wrap{box-shadow:none;margin:0;max-width:none;border-radius:0;padding:0}.grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:760px){.wrap{margin:0;border-radius:0;padding:18px}.grid,.fields{grid-template-columns:1fr}.sign{grid-template-columns:1fr}}</style></head><body><main class="wrap">' . $body . '<p class="created">Erstellt mit StayPilot am ' . e(date('d.m.Y H:i')) . '</p></main></body></html>';
    }

    private static function hasRecentDocument(int $bookingId, string $type): bool
    {
        $stmt = db()->prepare('SELECT id FROM booking_documents WHERE booking_id=? AND document_type=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$bookingId, $type]);
        return (bool)$stmt->fetchColumn();
    }

    private static function nextNumber(string $type, string $prefix): string
    {
        $year = (int)date('Y');
        $pdo = db();
        $pdo->prepare('INSERT INTO document_sequences(document_type,document_year,current_value) VALUES(?,?,0) ON DUPLICATE KEY UPDATE current_value=current_value')->execute([$type,$year]);
        $pdo->prepare('UPDATE document_sequences SET current_value=LAST_INSERT_ID(current_value+1) WHERE document_type=? AND document_year=?')->execute([$type,$year]);
        $value = (int)$pdo->lastInsertId();
        return $prefix . '-' . $year . '-' . str_pad((string)$value, 4, '0', STR_PAD_LEFT);
    }

    private static function customerUrl(int $bookingId): string
    {
        $stmt = db()->prepare('SELECT token_encrypted FROM booking_customer_access WHERE booking_id=? AND active=1 LIMIT 1');
        $stmt->execute([$bookingId]);
        $token = Crypto::decrypt($stmt->fetchColumn() ?: null);
        return $token !== '' ? HousekeepingWorkflow::applicationUrl('kunde.php?token=' . rawurlencode($token)) : '';
    }

    private static function checkinUrl(int $bookingId): string
    {
        $stmt = db()->prepare('SELECT token_encrypted FROM booking_customer_access WHERE booking_id=? AND active=1 LIMIT 1');
        $stmt->execute([$bookingId]);
        $token = Crypto::decrypt($stmt->fetchColumn() ?: null);
        return $token !== '' ? HousekeepingWorkflow::applicationUrl('checkin.php?token=' . rawurlencode($token)) : '';
    }

    private static function bool(mixed $value): bool { return normalize_bool($value) === 1; }
    private static function language(string $language): string { return in_array($language, ['de','en','es','fr','it','pt','ca'], true) ? $language : 'de'; }
    private static function time5(string $time): string { return $time !== '' ? substr($time, 0, 5) : '–'; }
    private static function safeFilename(string $name): string { return preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($name)) ?: 'document'; }
    private static function domain(string $email): string { return substr(strrchr($email, '@') ?: '', 1) ?: ''; }
}
