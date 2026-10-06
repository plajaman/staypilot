<?php
declare(strict_types=1);

/**
 * StayPilot V2.3.6.134 – Kommunikation erweitert ohne Parallelmodul.
 * Erweitert den bestehenden Bereich Versandprotokoll um Zustellprüfung,
 * WhatsApp-Vorlagen und Kundenstatus-Hinweise.
 */

function communication_center_v236(): never
{
    $smtp = SmtpMailer::settings(false);
    $imap = class_exists('ImapMailbox') ? ImapMailbox::settings(false) : [];
    $fromEmail = trim((string)($smtp['from_email'] ?? ''));
    $domain = '';
    if ($fromEmail !== '' && str_contains($fromEmail, '@')) {
        $domain = strtolower(substr(strrchr($fromEmail, '@') ?: '', 1));
    }
    $dns = v236_dns_report($domain);
    $recentFailures = 0;
    try {
        $recentFailures = (int)db()->query("SELECT COUNT(*) FROM communication_log WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND status COLLATE utf8mb4_unicode_ci IN ('failed' COLLATE utf8mb4_unicode_ci,'error' COLLATE utf8mb4_unicode_ci) AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn();
    } catch (Throwable $ignored) {}
    json_response([
        'ok' => true,
        'smtp' => [
            'active' => (int)($smtp['active'] ?? 0),
            'host' => (string)($smtp['host'] ?? ''),
            'from_name' => (string)($smtp['from_name'] ?? ''),
            'from_email' => $fromEmail,
            'reply_to' => (string)($smtp['reply_to'] ?? ''),
            'has_password' => (int)($smtp['has_password'] ?? 0),
        ],
        'imap' => [
            'active' => (int)($imap['active'] ?? 0),
            'host' => (string)($imap['host'] ?? ''),
            'email_address' => (string)($imap['email_address'] ?? ''),
            'folder' => (string)($imap['folder'] ?? 'INBOX'),
            'has_password' => (int)($imap['has_password'] ?? 0),
        ],
        'domain' => $domain,
        'dns' => $dns,
        'recent_failures' => $recentFailures,
        'status_settings' => v236_status_notification_settings(),
    ]);
}

function v236_dns_report(string $domain): array
{
    $report = [
        'domain' => $domain,
        'spf' => ['status' => 'info', 'label' => 'Keine Absenderdomain erkannt', 'records' => []],
        'dmarc' => ['status' => 'info', 'label' => 'Keine Absenderdomain erkannt', 'records' => []],
        'dkim' => ['status' => 'info', 'label' => 'DKIM kann StayPilot nur als Hinweis prüfen; der Selector liegt beim Hosting.', 'records' => []],
    ];
    if ($domain === '' || !preg_match('/^[a-z0-9.-]+$/', $domain)) return $report;
    if (!function_exists('dns_get_record')) {
        $report['spf'] = ['status'=>'warning','label'=>'DNS-Prüfung ist auf diesem Server nicht verfügbar. SPF bitte im Hosting prüfen.','records'=>[]];
        $report['dmarc'] = ['status'=>'warning','label'=>'DNS-Prüfung ist auf diesem Server nicht verfügbar. DMARC bitte im Hosting prüfen.','records'=>[]];
        return $report;
    }
    try {
        $txt = dns_get_record($domain, DNS_TXT) ?: [];
        $spfRecords = [];
        foreach ($txt as $row) {
            $txtVal = (string)($row['txt'] ?? '');
            if (stripos($txtVal, 'v=spf1') !== false) $spfRecords[] = $txtVal;
        }
        $report['spf'] = $spfRecords
            ? ['status'=>'ok','label'=>'SPF-Eintrag gefunden','records'=>$spfRecords]
            : ['status'=>'warning','label'=>'Kein SPF-Eintrag gefunden oder nicht lesbar. Das kann Spam verursachen.','records'=>[]];
    } catch (Throwable $e) {
        $report['spf'] = ['status'=>'warning','label'=>'SPF konnte nicht geprüft werden: '.$e->getMessage(),'records'=>[]];
    }
    try {
        $txt = dns_get_record('_dmarc.' . $domain, DNS_TXT) ?: [];
        $records = [];
        foreach ($txt as $row) {
            $txtVal = (string)($row['txt'] ?? '');
            if (stripos($txtVal, 'v=DMARC1') !== false) $records[] = $txtVal;
        }
        $report['dmarc'] = $records
            ? ['status'=>'ok','label'=>'DMARC-Eintrag gefunden','records'=>$records]
            : ['status'=>'warning','label'=>'Kein DMARC-Eintrag gefunden oder nicht lesbar. Empfehlung: mindestens p=none setzen.','records'=>[]];
    } catch (Throwable $e) {
        $report['dmarc'] = ['status'=>'warning','label'=>'DMARC konnte nicht geprüft werden: '.$e->getMessage(),'records'=>[]];
    }
    $report['dkim'] = ['status'=>'info','label'=>'DKIM muss im Hosting/SMTP-Konto aktiviert sein. StayPilot kennt den Selector nicht automatisch.','records'=>[]];
    return $report;
}

function whatsapp_templates_v236(): never
{
    json_response(['ok' => true, 'templates' => v236_whatsapp_templates()]);
}

function save_whatsapp_templates_v236(): never
{
    $data = request_data();
    $rows = $data['templates'] ?? [];
    if (!is_array($rows)) throw new ValidationException('WhatsApp-Vorlagen fehlen.');
    $templates = [];
    foreach ($rows as $key => $row) {
        if (!is_array($row)) continue;
        $safeKey = preg_replace('/[^a-z0-9_\-]/i', '', (string)$key) ?: 'custom';
        $templates[$safeKey] = [
            'label' => mb_substr(trim((string)($row['label'] ?? $safeKey)), 0, 120),
            'text' => mb_substr(trim((string)($row['text'] ?? '')), 0, 4000),
            'active' => normalize_bool($row['active'] ?? 1),
        ];
    }
    save_setting('communication_whatsapp_templates_v236', $templates ?: v236_default_whatsapp_templates());
    AuditLogger::record('settings', 'communication_whatsapp', 'update', null, ['count'=>count($templates)], 'WhatsApp-Vorlagen gespeichert');
    json_response(['ok' => true, 'message' => 'WhatsApp-Vorlagen gespeichert.', 'templates' => v236_whatsapp_templates()]);
}


function whatsapp_settings_v236(): never
{
    json_response([
        'ok' => true,
        'settings' => v236_whatsapp_settings(),
        'help' => [
            'default_country_code' => 'Wird nur verwendet, wenn eine gespeicherte Telefonnummer keine Landesvorwahl enthält. Für Spanien z. B. 34, für Deutschland 49.',
            'preferred_open' => 'Legt fest, welcher Link nach der Vorbereitung zuerst geöffnet wird. Der Text bleibt immer zusätzlich kopierbar.',
        ],
    ]);
}

function save_whatsapp_settings_v236(): never
{
    $d = request_data();
    $prefix = preg_replace('/\D+/', '', (string)($d['default_country_code'] ?? '')) ?: '';
    if ($prefix !== '' && (strlen($prefix) < 1 || strlen($prefix) > 4)) {
        throw new ValidationException('Die Landesvorwahl ist ungültig. Beispiel: 34 oder 49.');
    }
    $preferred = (string)($d['preferred_open'] ?? 'auto');
    if (!in_array($preferred, ['auto','app','web','wa'], true)) $preferred = 'auto';
    $settings = [
        'default_country_code' => $prefix,
        'preferred_open' => $preferred,
        'copy_hint' => normalize_bool($d['copy_hint'] ?? 1),
    ];
    save_setting('whatsapp_default_country_code', $prefix);
    save_setting('communication_whatsapp_settings_v236', $settings);
    AuditLogger::record('settings', 'communication_whatsapp_settings', 'update', null, $settings, 'WhatsApp-Einstellungen gespeichert');
    json_response(['ok'=>true,'message'=>'WhatsApp-Einstellungen gespeichert.','settings'=>v236_whatsapp_settings()]);
}

function v236_whatsapp_settings(): array
{
    $stored = setting('communication_whatsapp_settings_v236', []);
    $defaults = [
        'default_country_code' => preg_replace('/\D+/', '', (string)setting('whatsapp_default_country_code', '34')) ?: '34',
        'preferred_open' => 'auto',
        'copy_hint' => 1,
    ];
    return is_array($stored) ? array_merge($defaults, $stored) : $defaults;
}

function communication_readiness_v236(): never
{
    $out = [
        'open_bookings' => 0,
        'missing_email' => 0,
        'missing_phone' => 0,
        'invalid_phone' => 0,
        'failed_30_days' => 0,
        'prepared_whatsapp_30_days' => 0,
        'sent_email_30_days' => 0,
        'sample_missing' => [],
    ];
    try {
        $statusSql = "COALESCE(b.status,'') COLLATE utf8mb4_unicode_ci NOT IN ('cancelled' COLLATE utf8mb4_unicode_ci,'rejected' COLLATE utf8mb4_unicode_ci,'storno' COLLATE utf8mb4_unicode_ci)";
        $out['open_bookings'] = (int)db()->query("SELECT COUNT(*) FROM bookings b WHERE (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND {$statusSql}")->fetchColumn();
        $out['missing_email'] = (int)db()->query("SELECT COUNT(*) FROM bookings b JOIN guests g ON g.id=b.guest_id WHERE (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND {$statusSql} AND (g.email IS NULL OR g.email='' OR g.email NOT LIKE '%@%')")->fetchColumn();
        $out['missing_phone'] = (int)db()->query("SELECT COUNT(*) FROM bookings b JOIN guests g ON g.id=b.guest_id WHERE (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND {$statusSql} AND (g.phone IS NULL OR g.phone='')")->fetchColumn();
        $stmt = db()->query("SELECT b.id,b.reference,b.arrival,b.departure,TRIM(CONCAT(g.first_name,' ',g.last_name)) guest_name,g.email guest_email,g.phone guest_phone FROM bookings b JOIN guests g ON g.id=b.guest_id WHERE (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND {$statusSql} ORDER BY b.arrival DESC LIMIT 250");
        foreach ($stmt->fetchAll() as $row) {
            $phone = trim((string)($row['guest_phone'] ?? ''));
            $email = trim((string)($row['guest_email'] ?? ''));
            $badPhone = $phone !== '' && v236_normalize_whatsapp_phone($phone) === '';
            if ($badPhone) $out['invalid_phone']++;
            if (($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '' || $badPhone) && count($out['sample_missing']) < 10) {
                $out['sample_missing'][] = [
                    'booking_id'=>(int)$row['id'],
                    'reference'=>(string)($row['reference'] ?? ''),
                    'guest_name'=>(string)($row['guest_name'] ?? ''),
                    'arrival'=>(string)($row['arrival'] ?? ''),
                    'departure'=>(string)($row['departure'] ?? ''),
                    'email'=>$email,
                    'phone'=>$phone,
                    'problem'=>trim(($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) ? 'E-Mail fehlt/ungültig ' : '') . ($phone === '' ? 'Telefon fehlt ' : '') . ($badPhone ? 'Telefonformat prüfen' : '')),
                ];
            }
        }
    } catch (Throwable $e) { $out['booking_warning'] = $e->getMessage(); }
    try {
        $out['failed_30_days'] = (int)db()->query("SELECT COUNT(*) FROM communication_log WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND status COLLATE utf8mb4_unicode_ci IN ('failed' COLLATE utf8mb4_unicode_ci,'error' COLLATE utf8mb4_unicode_ci) AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn();
        $out['prepared_whatsapp_30_days'] = (int)db()->query("SELECT COUNT(*) FROM communication_log WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND channel COLLATE utf8mb4_unicode_ci='whatsapp' COLLATE utf8mb4_unicode_ci AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn();
        $out['sent_email_30_days'] = (int)db()->query("SELECT COUNT(*) FROM communication_log WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND channel COLLATE utf8mb4_unicode_ci='email' COLLATE utf8mb4_unicode_ci AND status COLLATE utf8mb4_unicode_ci='sent' COLLATE utf8mb4_unicode_ci AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn();
    } catch (Throwable $e) { $out['log_warning'] = $e->getMessage(); }
    json_response(['ok'=>true,'readiness'=>$out,'settings'=>v236_whatsapp_settings()]);
}

function v236_whatsapp_templates(): array
{
    $stored = setting('communication_whatsapp_templates_v236', null);
    if (!is_array($stored) || !$stored) return v236_default_whatsapp_templates();
    return array_replace_recursive(v236_default_whatsapp_templates(), $stored);
}

function v236_default_whatsapp_templates(): array
{
    return [
        'booking_link' => [
            'label' => 'Kundenbereich senden', 'active' => 1,
            'text' => "Hallo {guest_name},\n\nhier ist Ihr Kundenbereich zu Ihrer Buchung {booking_reference}:\n{customer_url}\n\nViele Grüße\n{property_name}",
        ],
        'checkin' => [
            'label' => 'Online-Check-in anfordern', 'active' => 1,
            'text' => "Hallo {guest_name},\n\nbitte füllen Sie vor Ihrer Anreise den Online-Check-in aus:\n{checkin_url}\n\nVielen Dank\n{property_name}",
        ],
        'payment' => [
            'label' => 'Zahlungshinweis', 'active' => 1,
            'text' => "Hallo {guest_name},\n\nzu Ihrer Buchung {booking_reference} ist noch ein Betrag offen. Alle Informationen finden Sie hier:\n{customer_url}\n\nViele Grüße\n{property_name}",
        ],
        'arrival' => [
            'label' => 'Anreiseinformation', 'active' => 1,
            'text' => "Hallo {guest_name},\n\nIhre Anreise ist am {arrival}. Weitere Informationen finden Sie hier:\n{customer_url}\n\nViele Grüße\n{property_name}",
        ],
    ];
}

function whatsapp_booking_prepare_v236(): never
{
    $data = request_data();
    $bookingId = (int)($data['booking_id'] ?? 0);
    $templateKey = (string)($data['template_key'] ?? 'booking_link');
    $customText = trim((string)($data['custom_text'] ?? ''));
    $booking = v236_booking_comm_context($bookingId);
    $templates = v236_whatsapp_templates();
    $template = $customText !== '' ? $customText : (string)($templates[$templateKey]['text'] ?? $templates['booking_link']['text']);
    $message = trim(v236_replace_comm_placeholders($template, $booking));
    if ($message === '') throw new ValidationException('Der WhatsApp-Text ist leer. Bitte Vorlage oder eigenen Text prüfen.');
    $phoneRaw = (string)($booking['guest_phone'] ?? '');
    $phone = v236_normalize_whatsapp_phone($phoneRaw);
    if ($phone === '') throw new ValidationException('Für diesen Gast ist keine gültige WhatsApp-/Telefonnummer hinterlegt. Bitte mit Landesvorwahl speichern, z. B. +34… oder +49….');
    $encoded = rawurlencode($message);
    $url = 'https://wa.me/' . rawurlencode($phone) . '?text=' . $encoded;
    $webUrl = 'https://web.whatsapp.com/send?phone=' . rawurlencode($phone) . '&text=' . $encoded;
    $appUrl = 'whatsapp://send?phone=' . rawurlencode($phone) . '&text=' . $encoded;
    $settings = v236_whatsapp_settings();
    $preferredUrl = match ((string)($settings['preferred_open'] ?? 'auto')) {
        'app' => $appUrl,
        'web' => $webUrl,
        'wa' => $url,
        default => $url,
    };
    CommunicationLogger::record('whatsapp', 'booking', $bookingId, (string)$booking['guest_name'], $phoneRaw, (string)($templates[$templateKey]['label'] ?? 'WhatsApp'), $message, 'prepared', 'WhatsApp-Text vorbereitet; Versand erfolgt manuell in WhatsApp.');
    json_response([
        'ok'=>true,
        'url'=>$url,
        'url_web'=>$webUrl,
        'url_app'=>$appUrl,
        'preferred_url'=>$preferredUrl,
        'whatsapp_settings'=>$settings,
        'message'=>$message,
        'recipient_name'=>$booking['guest_name'],
        'recipient_number'=>$phoneRaw,
        'normalized_number'=>$phone,
        'notice'=>'Falls WhatsApp den Text nicht automatisch übernimmt, kann er direkt aus StayPilot kopiert werden.'
    ]);
}

function v236_normalize_whatsapp_phone(string $value): string
{
    $raw = trim($value);
    if ($raw === '') return '';
    $raw = str_replace(['(0)', '/', '-', ' ', '.', '\t', '\n', '\r'], '', $raw);
    if (str_starts_with($raw, '+')) {
        $raw = substr($raw, 1);
    } elseif (str_starts_with($raw, '00')) {
        $raw = substr($raw, 2);
    }
    $digits = preg_replace('/\D+/', '', $raw) ?: '';
    if ($digits === '') return '';
    $defaultPrefix = preg_replace('/\D+/', '', (string)setting('whatsapp_default_country_code', ''));
    if ($defaultPrefix !== '' && strlen($digits) <= 10 && !str_starts_with($digits, $defaultPrefix)) {
        $digits = $defaultPrefix . ltrim($digits, '0');
    }
    return strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : '';
}

function v236_booking_comm_context(int $bookingId): array
{
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    $stmt = db()->prepare("SELECT b.*,TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,g.email guest_email,g.phone guest_phone,a.name apartment_name,a.code apartment_code,COALESCE(at.name,aat.name) apartment_type_name
        FROM bookings b JOIN guests g ON g.id=b.guest_id
        LEFT JOIN apartments a ON a.id=b.apartment_id
        LEFT JOIN apartment_types at ON at.id=b.apartment_type_id
        LEFT JOIN apartment_types aat ON aat.id=a.apartment_type_id
        WHERE b.id=? LIMIT 1");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();
    if (!$booking) throw new NotFoundException('Buchung nicht gefunden.');
    $booking['customer_url'] = v236_customer_url_for_booking($bookingId);
    $booking['checkin_url'] = v236_checkin_url_for_booking($bookingId);
    return $booking;
}

function v236_customer_url_for_booking(int $bookingId): string
{
    try {
        $stmt = db()->prepare("SELECT token_encrypted FROM booking_customer_access WHERE booking_id=? AND active=1 ORDER BY id DESC LIMIT 1");
        $stmt->execute([$bookingId]);
        $token = Crypto::decrypt($stmt->fetchColumn() ?: null);
        return $token !== '' ? HousekeepingWorkflow::applicationUrl('kunde.php?token=' . rawurlencode($token)) : '';
    } catch (Throwable $e) { return ''; }
}

function v236_checkin_url_for_booking(int $bookingId): string
{
    try {
        $stmt = db()->prepare("SELECT token_encrypted FROM booking_customer_access WHERE booking_id=? AND active=1 ORDER BY id DESC LIMIT 1");
        $stmt->execute([$bookingId]);
        $token = Crypto::decrypt($stmt->fetchColumn() ?: null);
        return $token !== '' ? HousekeepingWorkflow::applicationUrl('checkin.php?token=' . rawurlencode($token)) : '';
    } catch (Throwable $e) { return ''; }
}

function v236_replace_comm_placeholders(string $text, array $booking): string
{
    return strtr($text, v236_direct_customer_placeholder_values($booking));
}

function v236_direct_customer_placeholder_values(array $booking): array
{
    $currency = (string)setting('currency','EUR');
    $money = static fn($v): string => number_format((float)($v ?? 0), 2, ',', '.') . ' ' . $currency;
    $date = static fn($v): string => v236_de_date((string)($v ?? ''));
    $arrivalRaw = (string)($booking['arrival'] ?? $booking['start_date'] ?? '');
    $departureRaw = (string)($booking['departure'] ?? $booking['end_date'] ?? '');
    $nights = '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $arrivalRaw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $departureRaw)) {
        $nights = (string)max(0, (int)((strtotime($departureRaw)-strtotime($arrivalRaw))/86400));
    }
    $adults = (int)($booking['adults'] ?? $booking['adult_count'] ?? $booking['persons_adults'] ?? 0);
    $children = (int)($booking['children'] ?? $booking['child_count'] ?? $booking['persons_children'] ?? 0);
    $babies = (int)($booking['babies'] ?? $booking['baby_count'] ?? $booking['infants'] ?? 0);
    $total = (float)($booking['total_price'] ?? $booking['total_amount'] ?? $booking['price_total'] ?? 0);
    $paid = (float)($booking['paid_amount'] ?? $booking['amount_paid'] ?? 0);
    $deposit = (float)($booking['deposit_amount'] ?? $booking['down_payment_amount'] ?? 0);
    $rest = (float)($booking['remaining_amount'] ?? max(0, $total - $paid));
    $apartment = trim((string)($booking['apartment_code'] ?? $booking['apartment_number'] ?? '') . ' ' . (string)($booking['apartment_name'] ?? ''));
    $guestName = trim((string)($booking['guest_name'] ?? ''));
    $firstName = trim((string)($booking['first_name'] ?? ''));
    $lastName = trim((string)($booking['last_name'] ?? ''));
    if ($firstName === '' && $guestName !== '') $firstName = trim((string)preg_replace('/\s+.*/u', '', $guestName));
    if ($lastName === '' && $guestName !== '') $lastName = trim((string)preg_replace('/^\S+\s*/u', '', $guestName));
    $companyName = (string)setting('property_name', (string)setting('company_name', 'StayPilot'));
    $companyEmail = (string)setting('company_email', (string)setting('smtp_from_email', ''));
    $companyPhone = (string)setting('company_phone', '');
    $companyAddress = trim((string)setting('company_address', '') . ' ' . (string)setting('company_zip_city', ''));
    $companyWebsite = (string)setting('company_website', '');
    $checkinStatus = (string)($booking['checkin_status'] ?? $booking['online_checkin_status'] ?? '');
    $breakfast = (string)($booking['breakfast'] ?? $booking['breakfast_option'] ?? $booking['meal_breakfast'] ?? '');
    $halfBoard = (string)($booking['half_board'] ?? $booking['halfboard'] ?? $booking['meal_half_board'] ?? '');
    $source = (string)($booking['source'] ?? $booking['booking_source'] ?? '');
    $reference = (string)($booking['reference'] ?? $booking['booking_reference'] ?? '');
    return [
        '{property_name}' => $companyName,
        '{company_name}' => $companyName,
        '{company_email}' => $companyEmail,
        '{company_phone}' => $companyPhone,
        '{company_address}' => $companyAddress,
        '{company_website}' => $companyWebsite,
        '{company_signature}' => trim($companyName . "
" . $companyAddress . "
" . $companyPhone . "
" . $companyEmail . "
" . $companyWebsite),
        '{guest_name}' => $guestName,
        '{guest_first_name}' => $firstName,
        '{guest_last_name}' => $lastName,
        '{guest_email}' => (string)($booking['guest_email'] ?? $booking['email'] ?? ''),
        '{guest_phone}' => (string)($booking['guest_phone'] ?? $booking['phone'] ?? ''),
        '{guest_language}' => (string)($booking['language'] ?? $booking['guest_language'] ?? ''),
        '{guest_country}' => (string)($booking['country'] ?? $booking['guest_country'] ?? ''),
        '{guest_address}' => trim((string)($booking['address'] ?? '') . ' ' . (string)($booking['zip'] ?? '') . ' ' . (string)($booking['city'] ?? '')),
        '{booking_id}' => (string)($booking['id'] ?? ''),
        '{booking_reference}' => $reference,
        '{booking_number}' => $reference,
        '{booking_status}' => (string)($booking['status'] ?? ''),
        '{booking_source}' => $source,
        '{booking_created_at}' => $date($booking['created_at'] ?? ''),
        '{arrival}' => $date($arrivalRaw),
        '{departure}' => $date($departureRaw),
        '{arrival_raw}' => $arrivalRaw,
        '{departure_raw}' => $departureRaw,
        '{nights}' => $nights,
        '{adults}' => (string)$adults,
        '{children}' => (string)$children,
        '{babies}' => (string)$babies,
        '{persons_total}' => (string)($adults + $children + $babies),
        '{apartment}' => $apartment,
        '{apartment_name}' => (string)($booking['apartment_name'] ?? ''),
        '{apartment_number}' => (string)($booking['apartment_code'] ?? $booking['apartment_number'] ?? ''),
        '{apartment_code}' => (string)($booking['apartment_code'] ?? ''),
        '{apartment_type}' => (string)($booking['apartment_type_name'] ?? ''),
        '{house_name}' => (string)($booking['house_name'] ?? $booking['building_name'] ?? ''),
        '{total_price}' => $money($total),
        '{total_amount}' => $money($total),
        '{paid_amount}' => $money($paid),
        '{open_amount}' => $money(max(0, $total - $paid)),
        '{deposit_amount}' => $money($deposit),
        '{deposit_due}' => $date($booking['deposit_due'] ?? $booking['deposit_due_date'] ?? ''),
        '{remaining_amount}' => $money($rest),
        '{remaining_due}' => $date($booking['remaining_due'] ?? $booking['remaining_due_date'] ?? ''),
        '{payment_status}' => (string)($booking['payment_status'] ?? ''),
        '{customer_url}' => (string)($booking['customer_url'] ?? ''),
        '{checkin_url}' => (string)($booking['checkin_url'] ?? ''),
        '{payment_url}' => (string)($booking['payment_url'] ?? $booking['customer_url'] ?? ''),
        '{booking_url}' => (string)($booking['customer_url'] ?? ''),
        '{invoice_link}' => (string)($booking['customer_url'] ?? ''),
        '{receipt_link}' => (string)($booking['customer_url'] ?? ''),
        '{document_list}' => (string)($booking['document_list'] ?? 'siehe Anhang/Kundenbereich'),
        '{checkin_status}' => $checkinStatus,
        '{arrival_time}' => (string)($booking['arrival_time'] ?? $booking['estimated_arrival_time'] ?? ''),
        '{checkin_missing_fields}' => (string)($booking['checkin_missing_fields'] ?? ''),
        '{breakfast}' => $breakfast,
        '{half_board}' => $halfBoard,
        '{board}' => trim($breakfast . ' ' . $halfBoard),
        '{special_requests}' => (string)($booking['special_requests'] ?? $booking['notes'] ?? ''),
        '{cleaning_notes}' => (string)($booking['cleaning_notes'] ?? $booking['cleaning_instructions'] ?? ''),
        '{today}' => date('d.m.Y'),
        '{current_date}' => date('d.m.Y'),
    ];
}

function v236_de_date(string $date): string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return $date;
    return date('d.m.Y', strtotime($date));
}

function communication_status_settings_v236(): never
{
    json_response(['ok' => true, 'settings' => v236_status_notification_settings()]);
}

function save_communication_status_settings_v236(): never
{
    $d = request_data();
    $defaults = v236_default_status_notification_settings();
    $inputTemplates = is_array($d['templates'] ?? null) ? $d['templates'] : [];
    $templates = [];
    foreach ($defaults['templates'] as $key => $def) {
        $row = is_array($inputTemplates[$key] ?? null) ? $inputTemplates[$key] : [];
        $templates[$key] = [
            'key' => $key,
            'label' => (string)$def['label'],
            'active' => normalize_bool($row['active'] ?? ($def['active'] ?? 1)),
            'subject' => mb_substr(trim((string)($row['subject'] ?? $def['subject'])), 0, 180),
            'text' => mb_substr(trim((string)($row['text'] ?? $def['text'])), 0, 5000),
            'customer_visible' => normalize_bool($row['customer_visible'] ?? ($def['customer_visible'] ?? 1)),
        ];
    }
    $settings = [
        'email_on_payment_change' => normalize_bool($d['email_on_payment_change'] ?? 1),
        'email_on_document_change' => normalize_bool($d['email_on_document_change'] ?? 1),
        'email_on_checkin_change' => normalize_bool($d['email_on_checkin_change'] ?? 1),
        'show_in_customer_area' => normalize_bool($d['show_in_customer_area'] ?? 1),
        'prepare_before_send' => normalize_bool($d['prepare_before_send'] ?? 1),
        'default_subject' => mb_substr(trim((string)($d['default_subject'] ?? $defaults['default_subject'])), 0, 180),
        'default_text' => mb_substr(trim((string)($d['default_text'] ?? $defaults['default_text'])), 0, 5000),
        'templates' => $templates,
    ];
    save_setting('communication_status_notifications_v236', $settings);
    AuditLogger::record('settings', 'communication_status', 'update', null, ['templates'=>array_keys($templates)], 'Kundenstatus-Benachrichtigungen gespeichert');
    json_response(['ok'=>true,'message'=>'Status-Benachrichtigungen und Vorlagen gespeichert.','settings'=>$settings]);
}

function v236_status_notification_settings(): array
{
    $defaults = v236_default_status_notification_settings();
    $stored = setting('communication_status_notifications_v236', []);
    if (!is_array($stored)) return $defaults;
    $settings = array_merge($defaults, $stored);
    $settings['templates'] = $defaults['templates'];
    if (is_array($stored['templates'] ?? null)) {
        foreach ($defaults['templates'] as $key => $def) {
            $row = is_array($stored['templates'][$key] ?? null) ? $stored['templates'][$key] : [];
            $settings['templates'][$key] = array_merge($def, $row, ['key'=>$key, 'label'=>(string)$def['label']]);
        }
    }
    return $settings;
}

function v236_default_status_notification_settings(): array
{
    $baseSubject = 'Aktualisierung zu Ihrer Buchung {booking_reference}';
    $baseText = "Hallo {guest_name},

es gibt eine Aktualisierung zu Ihrer Buchung {booking_reference}.

Alle Informationen finden Sie in Ihrem Kundenbereich:
{customer_url}

Viele Grüße
{property_name}";
    return [
        'email_on_payment_change' => 1,
        'email_on_document_change' => 1,
        'email_on_checkin_change' => 1,
        'show_in_customer_area' => 1,
        'prepare_before_send' => 1,
        'default_subject' => $baseSubject,
        'default_text' => $baseText,
        'templates' => [
            'manual' => ['key'=>'manual','label'=>'Allgemeine Statusmeldung','active'=>1,'customer_visible'=>1,'subject'=>$baseSubject,'text'=>$baseText],
            'payment' => ['key'=>'payment','label'=>'Zahlungsstatus geändert','active'=>1,'customer_visible'=>1,'subject'=>'Zahlungsstatus zu Ihrer Buchung {booking_reference}','text'=>"Hallo {guest_name},

der Zahlungsstatus Ihrer Buchung {booking_reference} wurde aktualisiert.

Gesamtbetrag: {total_amount}
Bereits bezahlt: {paid_amount}
Offen: {open_amount}

Details finden Sie im Kundenbereich:
{customer_url}

Viele Grüße
{property_name}"],
            'document' => ['key'=>'document','label'=>'Neue Dokumente verfügbar','active'=>1,'customer_visible'=>1,'subject'=>'Neue Dokumente zu Ihrer Buchung {booking_reference}','text'=>"Hallo {guest_name},

für Ihre Buchung {booking_reference} wurden neue Dokumente bereitgestellt.

Bitte öffnen Sie Ihren Kundenbereich:
{customer_url}

Viele Grüße
{property_name}"],
            'checkin' => ['key'=>'checkin','label'=>'Check-in-Status geändert','active'=>1,'customer_visible'=>1,'subject'=>'Check-in-Status zu Ihrer Buchung {booking_reference}','text'=>"Hallo {guest_name},

der Check-in-Status Ihrer Buchung wurde aktualisiert.

Aktueller Status: {checkin_status}
Online-Check-in:
{checkin_url}

Viele Grüße
{property_name}"],
            'arrival' => ['key'=>'arrival','label'=>'Anreise-Hinweis','active'=>1,'customer_visible'=>1,'subject'=>'Informationen zu Ihrer Anreise am {arrival}','text'=>"Hallo {guest_name},

Ihre Anreise ist am {arrival}.

Unterkunft: {apartment_type} {apartment}
Kundenbereich: {customer_url}

Viele Grüße
{property_name}"],
        ],
    ];
}

function send_customer_status_notification_v236(): never
{
    $d = request_data();
    $bookingId = (int)($d['booking_id'] ?? 0);
    $event = trim((string)($d['event'] ?? 'manual')) ?: 'manual';
    $booking = v236_booking_comm_context($bookingId);
    if (!filter_var((string)$booking['guest_email'], FILTER_VALIDATE_EMAIL)) throw new ValidationException('Für den Gast ist keine gültige E-Mail-Adresse hinterlegt.');
    $settings = v236_status_notification_settings();
    $templates = is_array($settings['templates'] ?? null) ? $settings['templates'] : [];
    $tpl = is_array($templates[$event] ?? null) ? $templates[$event] : ($templates['manual'] ?? []);
    if (isset($tpl['active']) && !normalize_bool($tpl['active'])) throw new ValidationException('Diese Statusvorlage ist deaktiviert.');
    $subject = v236_replace_comm_placeholders((string)($tpl['subject'] ?? $settings['default_subject']), $booking);
    $text = v236_replace_comm_placeholders((string)($tpl['text'] ?? $settings['default_text']), $booking);
    $html = '<div style="font-family:Arial,sans-serif;line-height:1.55"><p>' . nl2br(e($text)) . '</p></div>';
    try {
        $result = SmtpMailer::send((string)$booking['guest_email'], $subject, $text, $html);
        CommunicationLogger::record('email','booking',$bookingId,(string)$booking['guest_name'],(string)$booking['guest_email'],$subject,$text,'sent','Statusbenachrichtigung: '.$event.' · '.($result['message_id'] ?? ''));
        json_response(['ok'=>true,'message'=>'Status-E-Mail wurde gesendet.','event'=>$event]);
    } catch (Throwable $e) {
        CommunicationLogger::record('email','booking',$bookingId,(string)$booking['guest_name'],(string)$booking['guest_email'],$subject,$text,'failed','Statusbenachrichtigung fehlgeschlagen: '.$e->getMessage());
        throw $e;
    }
}

/* V2.3.6.44 – Automatisierungscenter im bestehenden Kommunikationsbereich. */
function communication_automation_rules_v236(): never
{
    $rules = v236_automation_rules();
    $preview = v236_automation_preview($rules);
    json_response(['ok' => true, 'rules' => $rules, 'preview' => $preview]);
}

function save_communication_automation_rules_v236(): never
{
    $data = request_data();
    $input = $data['rules'] ?? [];
    if (!is_array($input)) throw new ValidationException('Automatisierungsregeln fehlen.');
    $defaults = v236_default_automation_rules();
    $rules = [];
    foreach ($defaults as $key => $def) {
        $row = is_array($input[$key] ?? null) ? $input[$key] : [];
        $days = (int)($row['days'] ?? $def['days']);
        $days = max(-60, min(365, $days));
        $mode = (string)($row['mode'] ?? $def['mode']);
        if (!in_array($mode, ['manual', 'prepare', 'auto'], true)) $mode = 'manual';
        $rules[$key] = [
            'key' => $key,
            'label' => (string)$def['label'],
            'description' => (string)$def['description'],
            'event' => (string)$def['event'],
            'active' => normalize_bool($row['active'] ?? $def['active']),
            'days' => $days,
            'mode' => $mode,
            'template_code' => mb_substr(trim((string)($row['template_code'] ?? $def['template_code'])), 0, 120),
            'language_mode' => in_array((string)($row['language_mode'] ?? $def['language_mode']), ['guest', 'template', 'fixed'], true) ? (string)($row['language_mode'] ?? $def['language_mode']) : 'guest',
            'fixed_language' => mb_substr(trim((string)($row['fixed_language'] ?? $def['fixed_language'])), 0, 8),
        ];
    }
    save_setting('communication_automation_rules_v236', $rules);
    AuditLogger::record('settings', 'communication_automation', 'update', null, ['count' => count($rules)], 'Kommunikations-Automatisierungen gespeichert');
    json_response(['ok' => true, 'message' => 'Automatisierungsregeln gespeichert.', 'rules' => $rules, 'preview' => v236_automation_preview($rules)]);
}

function communication_automation_preview_v236(): never
{
    $rules = v236_automation_rules();
    json_response(['ok' => true, 'preview' => v236_automation_preview($rules)]);
}

function v236_automation_rules(): array
{
    $defaults = v236_default_automation_rules();
    $stored = setting('communication_automation_rules_v236', []);
    if (!is_array($stored) || !$stored) return $defaults;
    $out = [];
    foreach ($defaults as $key => $def) {
        $out[$key] = array_merge($def, is_array($stored[$key] ?? null) ? $stored[$key] : []);
        $out[$key]['key'] = $key;
        $out[$key]['label'] = $def['label'];
        $out[$key]['description'] = $def['description'];
        $out[$key]['event'] = $def['event'];
    }
    return $out;
}

function v236_default_automation_rules(): array
{
    return [
        'offer_reminder' => ['key'=>'offer_reminder','label'=>'Angebot erinnern','description'=>'Erinnert an versendete Angebote, die noch nicht angenommen oder abgelehnt wurden.','event'=>'offer_sent_age','active'=>0,'days'=>2,'mode'=>'manual','template_code'=>'offer_reminder','language_mode'=>'guest','fixed_language'=>'de'],
        'deposit_reminder' => ['key'=>'deposit_reminder','label'=>'Anzahlung erinnern','description'=>'Erinnert an offene oder überfällige Anzahlungen.','event'=>'deposit_due','active'=>0,'days'=>0,'mode'=>'manual','template_code'=>'payment_reminder','language_mode'=>'guest','fixed_language'=>'de'],
        'balance_reminder' => ['key'=>'balance_reminder','label'=>'Restzahlung erinnern','description'=>'Erinnert vor Anreise an offene Restzahlungen.','event'=>'balance_before_arrival','active'=>0,'days'=>7,'mode'=>'manual','template_code'=>'payment_reminder','language_mode'=>'guest','fixed_language'=>'de'],
        'checkin_request' => ['key'=>'checkin_request','label'=>'Check-in-Link senden','description'=>'Sendet/plant den Online-Check-in-Link vor Anreise.','event'=>'checkin_before_arrival','active'=>0,'days'=>14,'mode'=>'manual','template_code'=>'checkin_request','language_mode'=>'guest','fixed_language'=>'de'],
        'arrival_info' => ['key'=>'arrival_info','label'=>'Anreiseinformation','description'=>'Sendet Anreiseinformationen kurz vor Ankunft.','event'=>'arrival_before_arrival','active'=>0,'days'=>3,'mode'=>'manual','template_code'=>'arrival_info','language_mode'=>'guest','fixed_language'=>'de'],
        'review_request' => ['key'=>'review_request','label'=>'Bewertungsanfrage','description'=>'Fragt nach Abreise freundlich nach einer Bewertung.','event'=>'after_departure','active'=>0,'days'=>2,'mode'=>'manual','template_code'=>'review_request','language_mode'=>'guest','fixed_language'=>'de'],
        'thank_you' => ['key'=>'thank_you','label'=>'Dankesmail','description'=>'Sendet nach Abreise eine Dankesmail.','event'=>'after_departure','active'=>0,'days'=>1,'mode'=>'manual','template_code'=>'thank_you','language_mode'=>'guest','fixed_language'=>'de'],
    ];
}

function v236_automation_preview(array $rules): array
{
    $today = date('Y-m-d');
    $out = [];
    foreach ($rules as $key => $rule) {
        if (!normalize_bool($rule['active'] ?? 0)) { $out[$key] = ['count'=>0,'items'=>[],'note'=>'Regel ist deaktiviert.']; continue; }
        $event = (string)($rule['event'] ?? '');
        try {
            if ($event === 'offer_sent_age') {
                $days = max(0, (int)($rule['days'] ?? 2));
                $stmt = db()->prepare("SELECT id,reference,guest_name,guest_email,status,sent_at FROM offers WHERE status COLLATE utf8mb4_unicode_ci IN ('sent' COLLATE utf8mb4_unicode_ci,'viewed' COLLATE utf8mb4_unicode_ci) AND sent_at IS NOT NULL AND sent_at <= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY sent_at ASC LIMIT 25");
                $stmt->execute([$days]);
                $items = array_map(fn($r) => ['type'=>'offer','id'=>(int)$r['id'],'label'=>'Angebot '.($r['reference'] ?: '#'.$r['id']),'guest'=>$r['guest_name'] ?? '', 'email'=>$r['guest_email'] ?? '', 'date'=>$r['sent_at'] ?? '', 'status'=>$r['status'] ?? ''], $stmt->fetchAll());
            } elseif (in_array($event, ['deposit_due','balance_before_arrival'], true)) {
                $whereType = $event === 'deposit_due' ? " AND LOWER(COALESCE(s.label,s.type,'')) LIKE '%anzahl%'" : " AND LOWER(COALESCE(s.label,s.type,'')) NOT LIKE '%anzahl%'";
                $dateFilter = $event === 'deposit_due' ? "s.due_date <= CURDATE()" : "b.arrival <= DATE_ADD(CURDATE(), INTERVAL ".max(0,(int)($rule['days'] ?? 7))." DAY)";
                $sql = "SELECT b.id,b.reference,b.arrival,b.departure,TRIM(CONCAT(g.first_name,' ',g.last_name)) guest_name,g.email guest_email,SUM(GREATEST(0,COALESCE(s.amount,0)-COALESCE(s.paid_amount,0))) open_amount,MIN(s.due_date) due_date FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id JOIN guests g ON g.id=b.guest_id WHERE {$dateFilter} {$whereType} AND COALESCE(s.status,'open') COLLATE utf8mb4_unicode_ci NOT IN ('paid' COLLATE utf8mb4_unicode_ci,'cancelled' COLLATE utf8mb4_unicode_ci) GROUP BY b.id ORDER BY MIN(s.due_date) ASC LIMIT 25";
                $items = array_map(fn($r) => ['type'=>'booking','id'=>(int)$r['id'],'label'=>'Buchung '.($r['reference'] ?: '#'.$r['id']),'guest'=>$r['guest_name'] ?? '', 'email'=>$r['guest_email'] ?? '', 'date'=>$r['due_date'] ?? $r['arrival'] ?? '', 'status'=>'offen: '.number_format((float)($r['open_amount']??0),2,',','.').' '.setting('currency','EUR')], db()->query($sql)->fetchAll());
            } elseif (in_array($event, ['checkin_before_arrival','arrival_before_arrival'], true)) {
                $days = max(0, (int)($rule['days'] ?? 7));
                $stmt = db()->prepare("SELECT b.id,b.reference,b.arrival,b.departure,TRIM(CONCAT(g.first_name,' ',g.last_name)) guest_name,g.email guest_email FROM bookings b JOIN guests g ON g.id=b.guest_id WHERE (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND b.arrival BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) AND COALESCE(b.status,'') COLLATE utf8mb4_unicode_ci NOT IN ('cancelled' COLLATE utf8mb4_unicode_ci,'rejected' COLLATE utf8mb4_unicode_ci) ORDER BY b.arrival ASC LIMIT 25");
                $stmt->execute([$days]);
                $items = array_map(fn($r) => ['type'=>'booking','id'=>(int)$r['id'],'label'=>'Buchung '.($r['reference'] ?: '#'.$r['id']),'guest'=>$r['guest_name'] ?? '', 'email'=>$r['guest_email'] ?? '', 'date'=>$r['arrival'] ?? '', 'status'=>'Anreise'], $stmt->fetchAll());
            } elseif ($event === 'after_departure') {
                $days = max(0, (int)($rule['days'] ?? 1));
                $stmt = db()->prepare("SELECT b.id,b.reference,b.arrival,b.departure,TRIM(CONCAT(g.first_name,' ',g.last_name)) guest_name,g.email guest_email FROM bookings b JOIN guests g ON g.id=b.guest_id WHERE (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND b.departure = DATE_SUB(CURDATE(), INTERVAL ? DAY) AND COALESCE(b.status,'') COLLATE utf8mb4_unicode_ci NOT IN ('cancelled' COLLATE utf8mb4_unicode_ci,'rejected' COLLATE utf8mb4_unicode_ci) ORDER BY b.departure DESC LIMIT 25");
                $stmt->execute([$days]);
                $items = array_map(fn($r) => ['type'=>'booking','id'=>(int)$r['id'],'label'=>'Buchung '.($r['reference'] ?: '#'.$r['id']),'guest'=>$r['guest_name'] ?? '', 'email'=>$r['guest_email'] ?? '', 'date'=>$r['departure'] ?? '', 'status'=>'Abreise erledigt'], $stmt->fetchAll());
            } else { $items = []; }
            $out[$key] = ['count'=>count($items),'items'=>$items,'note'=>count($items).' passende Vorgänge gefunden.'];
        } catch (Throwable $e) {
            $out[$key] = ['count'=>0,'items'=>[],'note'=>'Prüfung nicht möglich: '.$e->getMessage()];
        }
    }
    return $out;
}

/* V2.3.6.50 – Direkte Kunden-E-Mail aus Admin im bestehenden Kommunikationsbereich. */
function direct_customer_email_context_v236(): never
{
    $bookingId = (int)($_GET['booking_id'] ?? 0);
    $q = trim((string)($_GET['q'] ?? ''));
    $bookings = v236_direct_customer_email_booking_options($q, $bookingId);
    $selected = null;
    if ($bookingId > 0) {
        $selected = v236_booking_comm_context($bookingId);
        $selected['documents'] = v236_booking_document_options($bookingId);
        $selected['mail_history'] = v236_booking_mail_history($bookingId);
    }
    json_response([
        'ok' => true,
        'bookings' => $bookings,
        'selected' => $selected,
        'placeholders' => v236_direct_customer_placeholders(),
        'templates' => v236_direct_customer_email_templates(),
        'defaults' => [
            'copy_to_self' => 1,
            'admin_email' => (string)(Auth::user()['email'] ?? ''),
            'bcc_email' => (string)setting('communication_admin_copy_email', (string)(Auth::user()['email'] ?? '')),
        ],
    ]);
}

function direct_customer_email_preview_v236(): never
{
    $d = request_data();
    $bookingId = (int)($d['booking_id'] ?? 0);
    $ctx = v236_booking_comm_context($bookingId);
    $subject = trim((string)($d['subject'] ?? ''));
    $html = (string)($d['html'] ?? '');
    if ($subject === '') throw new ValidationException('Betreff fehlt.');
    if (trim(strip_tags($html)) === '' && trim($html) === '') throw new ValidationException('Nachrichtentext fehlt.');
    $subject = v236_replace_comm_placeholders($subject, $ctx);
    $html = v236_sanitize_admin_mail_html(v236_replace_comm_placeholders($html, $ctx));
    $text = v236_html_to_text($html);
    $documentIds = v236_normalize_document_ids($d['document_ids'] ?? []);
    $documents = v236_booking_document_options($bookingId);
    $selectedDocs = array_values(array_filter($documents, fn($doc) => in_array((int)$doc['id'], $documentIds, true)));
    json_response([
        'ok' => true,
        'recipient' => [
            'name' => (string)($ctx['guest_name'] ?? ''),
            'email' => (string)($ctx['guest_email'] ?? ''),
        ],
        'subject' => $subject,
        'html' => $html,
        'text' => $text,
        'attachments' => $selectedDocs,
        'unresolved_placeholders' => v236_unresolved_placeholders($subject . "\n" . $html),
    ]);
}

function send_direct_customer_email_v236(): never
{
    $user = Auth::user();
    if (!v236_can_send_direct_customer_email($user)) {
        throw new ForbiddenException('Für direkte Kunden-E-Mails fehlt die Berechtigung.');
    }
    $d = request_data();
    $bookingId = (int)($d['booking_id'] ?? 0);
    $ctx = v236_booking_comm_context($bookingId);
    $email = trim((string)($ctx['guest_email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ValidationException('Für diesen Gast ist keine gültige E-Mail-Adresse hinterlegt.');
    $subject = trim((string)($d['subject'] ?? ''));
    $html = (string)($d['html'] ?? '');
    if ($subject === '') throw new ValidationException('Betreff fehlt.');
    if (trim(strip_tags($html)) === '' && trim($html) === '') throw new ValidationException('Nachrichtentext fehlt.');
    if (!normalize_bool($d['confirmed'] ?? 0)) throw new ValidationException('Bitte Empfänger und Inhalt in der Vorschau prüfen und bestätigen.');

    $subject = v236_replace_comm_placeholders($subject, $ctx);
    $html = v236_sanitize_admin_mail_html(v236_replace_comm_placeholders($html, $ctx));
    $text = v236_html_to_text($html);
    $documentIds = v236_normalize_document_ids($d['document_ids'] ?? []);
    $attachments = v236_attachments_from_booking_documents($bookingId, $documentIds);
    $attachmentInfo = count($attachments) ? (' · Anhänge: ' . count($attachments)) : '';
    try {
        $cfg = SmtpMailer::settings(false);
        if (!(int)($cfg['active'] ?? 0)) {
            throw new RuntimeException('SMTP ist nicht aktiviert. Bitte in Kommunikation / SMTP zuerst aktivieren.');
        }
        $result = SmtpMailer::send($email, $subject, $text, $html, $attachments);
        $logId = CommunicationLogger::record('email','booking',$bookingId,(string)($ctx['guest_name'] ?? ''),$email,$subject,$text,'sent','Direkte Kunden-E-Mail aus Admin' . $attachmentInfo . ' · ' . (string)($result['message_id'] ?? ''));
        v236_update_booking_documents_sent_at($documentIds);
        $copies = [];
        $copyEmail = trim((string)($d['copy_email'] ?? ''));
        $copyToSelf = normalize_bool($d['copy_to_self'] ?? 0);
        $userEmail = trim((string)($user['email'] ?? ''));
        if ($copyToSelf && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) $copies[] = $userEmail;
        if ($copyEmail !== '' && filter_var($copyEmail, FILTER_VALIDATE_EMAIL)) $copies[] = $copyEmail;
        $copies = array_values(array_unique($copies));
        $copyFailures = [];
        foreach ($copies as $copy) {
            try {
                $copySubject = '[Kopie] ' . $subject;
                $copyText = "Kopie der Kunden-E-Mail an {$email}\n\n" . $text;
                $copyHtml = '<div style="font-family:Arial,sans-serif;line-height:1.55"><p><b>Kopie der Kunden-E-Mail an ' . e($email) . '</b></p><hr>' . $html . '</div>';
                $copyResult = SmtpMailer::send($copy, $copySubject, $copyText, $copyHtml, $attachments);
                CommunicationLogger::record('email','booking',$bookingId,(string)($user['name'] ?? 'Admin'),$copy,$copySubject,$copyText,'sent','Kopie der direkten Kunden-E-Mail · Original-Log #' . $logId . ' · ' . (string)($copyResult['message_id'] ?? ''));
            } catch (Throwable $copyError) {
                $copyFailures[] = $copy . ': ' . $copyError->getMessage();
                try {
                    CommunicationLogger::record('email','booking',$bookingId,(string)($user['name'] ?? 'Admin'),$copy,'[Kopie fehlgeschlagen] '.$subject,$text,'failed','Kopie der direkten Kunden-E-Mail fehlgeschlagen: '.$copyError->getMessage());
                } catch (Throwable) {}
            }
        }
        $message = 'E-Mail wurde gesendet und im Versandprotokoll gespeichert.';
        if ($copyFailures) $message .= ' Hinweis: Kopie konnte nicht an alle Adressen gesendet werden.';
        json_response(['ok'=>true,'message'=>$message,'log_id'=>$logId,'copies'=>count($copies),'copy_failures'=>$copyFailures]);
    } catch (Throwable $e) {
        try {
            CommunicationLogger::record('email','booking',$bookingId,(string)($ctx['guest_name'] ?? ''),$email,$subject,$text,'failed','Direkte Kunden-E-Mail fehlgeschlagen: '.$e->getMessage() . $attachmentInfo);
        } catch (Throwable) {}
        throw new RuntimeException('Manuelle Kunden-E-Mail konnte nicht gesendet werden: '.$e->getMessage());
    }
}

function v236_can_send_direct_customer_email(array $user): bool
{
    $role = (string)($user['role'] ?? '');
    return in_array($role, ['admin','manager','reception'], true)
        || Auth::canForUser($user, 'communications_manage')
        || Auth::canForUser($user, 'bookings_manage');
}

function v236_direct_customer_email_booking_options(string $q = '', int $selectedId = 0): array
{
    $params = [];
    $where = "WHERE (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND b.status COLLATE utf8mb4_unicode_ci NOT IN ('deleted' COLLATE utf8mb4_unicode_ci,'archived' COLLATE utf8mb4_unicode_ci)";
    if ($q !== '') {
        $where .= " AND (b.reference COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci OR TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci OR g.email COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci OR a.name COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci OR a.code COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci)";
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if ($selectedId > 0 && $q === '') {
        $where .= " AND (b.id=? OR b.arrival>=DATE_SUB(CURDATE(), INTERVAL 60 DAY))";
        $params[] = $selectedId;
    }
    $sql = "SELECT b.id,b.reference,b.arrival,b.departure,b.status,TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,g.email guest_email,a.code apartment_code,a.name apartment_name
        FROM bookings b JOIN guests g ON g.id=b.guest_id
        LEFT JOIN apartments a ON a.id=b.apartment_id
        $where
        ORDER BY CASE WHEN b.id=? THEN 0 ELSE 1 END,b.arrival DESC,b.id DESC LIMIT 80";
    $params[] = $selectedId;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function v236_booking_document_options(int $bookingId): array
{
    try {
        $stmt = db()->prepare("SELECT id,document_type,document_number,language,title,pdf_path,status,generated_at,sent_at FROM booking_documents WHERE booking_id=? ORDER BY generated_at DESC,id DESC");
        $stmt->execute([$bookingId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $path = (string)($row['pdf_path'] ?? '');
            $row['file_exists'] = $path !== '' && is_file(root_path($path));
            $row['label'] = trim((string)($row['title'] ?? '') . (!empty($row['document_number']) ? ' · ' . (string)$row['document_number'] : ''));
        }
        unset($row);
        return $rows;
    } catch (Throwable $e) { return []; }
}

function v236_booking_mail_history(int $bookingId): array
{
    try {
        $stmt = db()->prepare("SELECT id,created_at,recipient_address,subject,status,detail,message_excerpt,message_body FROM communication_log WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND entity_type COLLATE utf8mb4_unicode_ci = 'booking' COLLATE utf8mb4_unicode_ci AND entity_id COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci AND channel COLLATE utf8mb4_unicode_ci = 'email' COLLATE utf8mb4_unicode_ci ORDER BY id DESC LIMIT 20");
        $stmt->execute([(string)$bookingId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['message_display'] = trim((string)($row['message_body'] ?? '')) !== '' ? (string)$row['message_body'] : (string)($row['message_excerpt'] ?? '');
        }
        unset($row);
        return $rows;
    } catch (Throwable $e) { return []; }
}

function v236_direct_customer_placeholders(): array
{
    return [
        'Gast' => ['{guest_name}','{guest_first_name}','{guest_last_name}','{guest_email}','{guest_phone}','{guest_language}','{guest_country}','{guest_address}'],
        'Buchung' => ['{booking_id}','{booking_reference}','{booking_number}','{booking_status}','{booking_source}','{booking_created_at}','{special_requests}'],
        'Aufenthalt' => ['{arrival}','{departure}','{arrival_raw}','{departure_raw}','{nights}','{adults}','{children}','{babies}','{persons_total}','{arrival_time}'],
        'Wohnung / Haus' => ['{apartment}','{apartment_name}','{apartment_number}','{apartment_code}','{apartment_type}','{house_name}','{cleaning_notes}'],
        'Zahlung / Rechnung' => ['{total_price}','{total_amount}','{paid_amount}','{open_amount}','{deposit_amount}','{deposit_due}','{remaining_amount}','{remaining_due}','{payment_status}'],
        'Dokumente / Links' => ['{customer_url}','{checkin_url}','{payment_url}','{booking_url}','{invoice_link}','{receipt_link}','{document_list}'],
        'Check-in' => ['{checkin_status}','{checkin_missing_fields}','{arrival_time}','{checkin_url}'],
        'Bistro / Frühstück / HP' => ['{breakfast}','{half_board}','{board}'],
        'Firma' => ['{property_name}','{company_name}','{company_email}','{company_phone}','{company_address}','{company_website}','{company_signature}'],
        'Datum' => ['{today}','{current_date}'],
    ];
}

function v236_direct_customer_email_templates(): array
{
    return [
        'free' => ['label'=>'Freie Nachricht','subject'=>'Ihre Buchung {booking_reference}','html'=>'<p>Hallo {guest_first_name},</p><p></p><p>Viele Grüße<br>{property_name}</p>'],
        'personal' => ['label'=>'Persönliche Nachricht','subject'=>'Nachricht zu Ihrem Aufenthalt {arrival} – {departure}','html'=>'<p>Hallo {guest_first_name},</p><p>wir melden uns zu Ihrem Aufenthalt vom <b>{arrival}</b> bis <b>{departure}</b>.</p><p></p><p>Viele Grüße<br>{company_signature}</p>'],
        'payment' => ['label'=>'Zahlungsinformation','subject'=>'Zahlungsinformation zu Ihrer Buchung {booking_reference}','html'=>'<p>Hallo {guest_first_name},</p><p>zu Ihrer Buchung <b>{booking_reference}</b> ist aktuell noch folgender Betrag offen: <b>{open_amount}</b>.</p><p>Gesamtbetrag: {total_amount}<br>Bereits bezahlt: {paid_amount}<br>Restbetrag: {remaining_amount}</p><p>Weitere Informationen finden Sie in Ihrem Kundenbereich:<br><a href="{customer_url}">{customer_url}</a></p><p>Viele Grüße<br>{property_name}</p>'],
        'checkin' => ['label'=>'Online-Check-in','subject'=>'Online-Check-in zu Ihrer Buchung {booking_reference}','html'=>'<p>Hallo {guest_first_name},</p><p>bitte füllen Sie vor Ihrer Anreise den Online-Check-in aus:</p><p><a href="{checkin_url}">{checkin_url}</a></p><p>Aktueller Check-in-Status: <b>{checkin_status}</b></p><p>Vielen Dank<br>{property_name}</p>'],
        'arrival' => ['label'=>'Anreiseinformation','subject'=>'Anreiseinformation {arrival}','html'=>'<h2>Informationen zu Ihrer Anreise</h2><p>Hallo {guest_first_name},</p><p>Ihre Anreise ist am <b>{arrival}</b>. Ihre gebuchte Unterkunft: <b>{apartment_type}</b> {apartment}.</p><p>Alle wichtigen Informationen finden Sie hier:</p><p><a href="{customer_url}">{customer_url}</a></p><p>Viele Grüße<br>{property_name}</p>'],
        'document' => ['label'=>'Dokument-Hinweis','subject'=>'Neue Dokumente zu Ihrer Buchung {booking_reference}','html'=>'<p>Hallo {guest_first_name},</p><p>wir haben neue Dokumente zu Ihrer Buchung bereitgestellt. Sie finden diese im Kundenbereich:</p><p><a href="{customer_url}">{customer_url}</a></p><p>Viele Grüße<br>{property_name}</p>'],
        'bistro' => ['label'=>'Frühstück / Halbpension','subject'=>'Frühstück / Halbpension zu Ihrer Buchung {booking_reference}','html'=>'<p>Hallo {guest_first_name},</p><p>hier sind die aktuellen Informationen zu Frühstück und Halbpension für Ihren Aufenthalt:</p><ul><li>Frühstück: {breakfast}</li><li>Halbpension: {half_board}</li><li>Personen: {persons_total}</li></ul><p>Viele Grüße<br>{property_name}</p>'],
    ];
}

function v236_normalize_document_ids(mixed $ids): array
{
    if (is_string($ids)) $ids = array_filter(array_map('trim', explode(',', $ids)));
    if (!is_array($ids)) return [];
    $out = [];
    foreach ($ids as $id) { $i = (int)$id; if ($i > 0) $out[] = $i; }
    return array_values(array_unique($out));
}

function v236_attachments_from_booking_documents(int $bookingId, array $ids): array
{
    if (!$ids) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$bookingId], $ids);
    $stmt = db()->prepare("SELECT id,title,document_number,pdf_path FROM booking_documents WHERE booking_id=? AND id IN ($placeholders)");
    $stmt->execute($params);
    $attachments = [];
    foreach ($stmt->fetchAll() as $doc) {
        $path = (string)($doc['pdf_path'] ?? '');
        if ($path === '' || !is_file(root_path($path))) continue;
        $base = trim((string)($doc['document_number'] ?: $doc['title'] ?: 'Dokument'));
        $base = preg_replace('/[^A-Za-z0-9._ -]+/u', '-', $base) ?: 'Dokument';
        $attachments[] = ['path'=>root_path($path),'name'=>mb_substr($base,0,150).'.pdf','mime'=>'application/pdf'];
    }
    return $attachments;
}

function v236_update_booking_documents_sent_at(array $ids): void
{
    if (!$ids) return;
    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        db()->prepare("UPDATE booking_documents SET sent_at=COALESCE(sent_at,NOW()) WHERE id IN ($placeholders)")->execute($ids);
    } catch (Throwable $ignored) {}
}

function v236_sanitize_admin_mail_html(string $html): string
{
    $html = preg_replace('#<\s*(script|style|iframe|object|embed)[^>]*>.*?<\s*/\s*\1\s*>#isu', '', $html) ?? $html;
    $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html) ?? $html;
    $html = preg_replace('/javascript\s*:/iu', '', $html) ?? $html;
    return $html;
}

function v236_html_to_text(string $html): string
{
    $html = preg_replace('#<\s*br\s*/?>#i', "\n", $html) ?? $html;
    $html = preg_replace('#<\s*/\s*(p|div|li|h[1-6]|tr)\s*>#i', "\n", $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    return trim($text);
}

function v236_unresolved_placeholders(string $text): array
{
    preg_match_all('/\{[a-zA-Z0-9_]+\}/', $text, $m);
    return array_values(array_unique($m[0] ?? []));
}


function mail_account_v236125(): never
{
    $smtp = SmtpMailer::settings(false);
    $imap = ImapMailbox::settings(false);
    json_response([
        'ok' => true,
        'smtp' => [
            'active' => (int)($smtp['active'] ?? 0),
            'host' => (string)($smtp['host'] ?? ''),
            'port' => (int)($smtp['port'] ?? 465),
            'encryption' => (string)($smtp['encryption'] ?? 'ssl'),
            'username' => (string)($smtp['username'] ?? ''),
            'from_name' => (string)($smtp['from_name'] ?? ''),
            'from_email' => (string)($smtp['from_email'] ?? ''),
            'reply_to' => (string)($smtp['reply_to'] ?? ''),
            'has_password' => (int)($smtp['has_password'] ?? 0),
        ],
        'imap' => [
            'active' => (int)($imap['active'] ?? 0),
            'host' => (string)($imap['host'] ?? ''),
            'port' => (int)($imap['port'] ?? 993),
            'encryption' => (string)($imap['encryption'] ?? 'ssl'),
            'username' => (string)($imap['username'] ?? ''),
            'email_address' => (string)($imap['email_address'] ?? ''),
            'folder' => (string)($imap['folder'] ?? 'INBOX'),
            'has_password' => (int)($imap['has_password'] ?? 0),
        ],
        'defaults' => [
            'host' => '',
            'imap_port' => 993,
            'smtp_port' => 465,
            'encryption' => 'ssl',
            'email' => '',
            'login_hint' => 'Meist der volle E-Mail-Login oder die Postfach-Kennung des Hosting-Anbieters – je nachdem, was beim Mailkonto funktioniert.',
        ],
    ]);
}

function save_mail_account_v236125(): never
{
    $d = request_data();
    $smtpData = [
        'active' => $d['smtp_active'] ?? 0,
        'host' => $d['smtp_host'] ?? '',
        'port' => $d['smtp_port'] ?? 465,
        'encryption' => $d['smtp_encryption'] ?? 'ssl',
        'username' => $d['smtp_username'] ?? '',
        'password' => $d['smtp_password'] ?? '',
        'auth_method' => $d['smtp_auth_method'] ?? 'login',
        'from_name' => $d['smtp_from_name'] ?? setting('property_name','StayPilot'),
        'from_email' => $d['smtp_from_email'] ?? '',
        'reply_to' => $d['smtp_reply_to'] ?? '',
        'timeout_seconds' => $d['smtp_timeout_seconds'] ?? 15,
    ];
    $imapData = [
        'imap_active' => $d['imap_active'] ?? 0,
        'imap_host' => $d['imap_host'] ?? '',
        'imap_port' => $d['imap_port'] ?? 993,
        'imap_encryption' => $d['imap_encryption'] ?? 'ssl',
        'imap_username' => $d['imap_username'] ?? '',
        'imap_password' => $d['imap_password'] ?? '',
        'imap_email_address' => $d['imap_email_address'] ?? '',
        'imap_folder' => $d['imap_folder'] ?? 'INBOX',
        'imap_timeout_seconds' => $d['imap_timeout_seconds'] ?? 15,
    ];
    $smtp = SmtpMailer::save($smtpData);
    $imap = ImapMailbox::save($imapData);
    AuditLogger::record('mail_account', 1, 'update', null, ['smtp_active'=>$smtp['active'] ?? 0, 'imap_active'=>$imap['active'] ?? 0], 'E-Mail-Konto gespeichert; Passwörter geschützt');
    json_response(['ok'=>true, 'message'=>'E-Mail-Konto gespeichert.', 'smtp'=>$smtp, 'imap'=>$imap]);
}

function test_imap_v236125(): never
{
    $result = ImapMailbox::test();
    CommunicationLogger::record('imap_test', 'mail_account', 1, 'IMAP-Test', (string)(ImapMailbox::settings(false)['email_address'] ?? ''), 'IMAP-Verbindungstest', (string)($result['message'] ?? 'IMAP-Test'), 'success', 'Posteingang getestet');
    json_response($result);
}

function mail_inbox_v236125(): never
{
    $limit = max(5, min(50, (int)($_GET['limit'] ?? 20)));
    $result = ImapMailbox::fetchInbox($limit);
    json_response($result);
}


/* V2.3.6.126 – Outlook-ähnliches Mailcenter: Mail lesen und freie E-Mail schreiben. */
function mail_message_v236126(): never
{
    $seq = (int)($_GET['seq'] ?? 0);
    if ($seq <= 0) throw new ValidationException('Nachrichten-Nummer fehlt.');
    json_response(ImapMailbox::fetchMessage($seq));
}


function mail_sent_log_v236127(): never
{
    $limit = max(5, min(100, (int)($_GET['limit'] ?? 50)));
    try {
        $stmt = db()->prepare("SELECT id, created_at, recipient_name, recipient_address, subject, status, detail, message_excerpt, message_body, entity_type, entity_id FROM communication_log WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND channel COLLATE utf8mb4_unicode_ci = 'email' COLLATE utf8mb4_unicode_ci ORDER BY id DESC LIMIT " . $limit);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $rows = [];
    }
    json_response(['ok' => true, 'messages' => $rows]);
}

function send_free_email_v236126(): never
{
    $user = Auth::user();
    if (!v236_can_send_direct_customer_email($user)) throw new ForbiddenException('Für E-Mail-Versand fehlt die Berechtigung.');
    $d = request_data();
    $toRaw = trim((string)($d['to'] ?? ''));
    $ccRaw = trim((string)($d['cc'] ?? ''));
    $bccRaw = trim((string)($d['bcc'] ?? ''));
    $subject = trim((string)($d['subject'] ?? ''));
    $html = (string)($d['html'] ?? '');
    $bookingId = max(0, (int)($d['booking_id'] ?? 0));
    $entityType = $bookingId > 0 ? 'booking' : 'free_email';
    $entityId = $bookingId > 0 ? $bookingId : 0;
    if ($subject === '') throw new ValidationException('Betreff fehlt.');
    if (trim(strip_tags($html)) === '' && trim($html) === '') throw new ValidationException('Nachrichtentext fehlt.');
    $toList = v236_email_list($toRaw);
    if (!$toList) throw new ValidationException('Mindestens eine gültige Empfängeradresse ist erforderlich.');
    $ccList = v236_email_list($ccRaw);
    $bccList = v236_email_list($bccRaw);
    $text = v236_html_to_text(v236_sanitize_admin_mail_html($html));
    $html = v236_sanitize_admin_mail_html($html);
    $sent = []; $failed = [];
    foreach (array_merge($toList, $ccList, $bccList) as $addr) {
        try {
            $result = SmtpMailer::send($addr, $subject, $text, $html);
            $sent[] = $addr;
            CommunicationLogger::record('email', $entityType, $entityId, '', $addr, $subject, $text, 'sent', 'Freie E-Mail aus Kommunikationscenter · ' . (string)($result['message_id'] ?? ''));
        } catch (Throwable $e) {
            $failed[] = $addr . ': ' . $e->getMessage();
            try { CommunicationLogger::record('email', $entityType, $entityId, '', $addr, $subject, $text, 'failed', 'Freie E-Mail fehlgeschlagen: ' . $e->getMessage()); } catch (Throwable) {}
        }
    }
    if (!$sent && $failed) throw new RuntimeException('Keine E-Mail konnte gesendet werden: ' . implode(' · ', $failed));
    json_response(['ok'=>true, 'message'=>'E-Mail gesendet.', 'sent'=>$sent, 'failed'=>$failed]);
}

function v236_email_list(string $raw): array
{
    $items = preg_split('/[,;\s]+/', $raw) ?: [];
    $out = [];
    foreach ($items as $item) {
        $item = trim($item);
        if ($item === '') continue;
        if (preg_match('/<([^>]+)>/', $item, $m)) $item = trim($m[1]);
        if (!filter_var($item, FILTER_VALIDATE_EMAIL)) throw new ValidationException('Ungültige E-Mail-Adresse: ' . $item);
        $out[] = $item;
    }
    return array_values(array_unique($out));
}


/* V2.3.6.134 – IMAP-Ordner und bewusste Mail-Aktionen: gelesen, verschieben, archivieren, Papierkorb. */
function mail_folders_v236132(): never
{
    json_response(ImapMailbox::listFolders());
}

function mail_action_v236132(): never
{
    $user = Auth::user();
    if (!v236_can_send_direct_customer_email($user)) throw new ForbiddenException('Für Mail-Aktionen fehlt die Berechtigung.');
    $d = request_data();
    $seq = (int)($d['seq'] ?? 0);
    $action = (string)($d['mail_action'] ?? $d['type'] ?? '');
    $folder = (string)($d['folder'] ?? '');
    $confirm = (string)($d['confirm'] ?? '');
    if ($seq <= 0) throw new ValidationException('Nachrichten-Nummer fehlt.');
    if ($action === 'delete') $action = 'trash';
    json_response(ImapMailbox::messageAction($seq, $action, $folder));
}


/* V2.3.6.134 – zentrale E-Mail-Vorlagenverwaltung für das Kommunikationscenter. */
function email_templates_v236133(): never
{
    json_response(['ok'=>true,'templates'=>v236_email_templates()]);
}

function save_email_template_v236133(): never
{
    $user = Auth::user();
    if (!v236_can_send_direct_customer_email($user)) throw new ForbiddenException('Für E-Mail-Vorlagen fehlt die Berechtigung.');
    $d = request_data();
    $oldKey = preg_replace('/[^a-z0-9_\-]/i', '', (string)($d['key'] ?? ''));
    $newKey = preg_replace('/[^a-z0-9_\-]/i', '', strtolower((string)($d['new_key'] ?? $oldKey ?? '')));
    if ($newKey === '') $newKey = 'vorlage_' . date('Ymd_His');
    $label = mb_substr(trim((string)($d['label'] ?? '')), 0, 160);
    if ($label === '') throw new ValidationException('Name der Vorlage fehlt.');
    $subject = mb_substr(trim((string)($d['subject'] ?? '')), 0, 240);
    $html = v236_sanitize_admin_mail_html((string)($d['html'] ?? ''));
    if (trim(strip_tags($html)) === '') throw new ValidationException('Vorlagentext fehlt.');
    $group = mb_substr(trim((string)($d['group'] ?? 'Allgemein')), 0, 80) ?: 'Allgemein';
    $templates = v236_email_templates(false);
    if ($oldKey !== '' && $oldKey !== $newKey) unset($templates[$oldKey]);
    $templates[$newKey] = [
        'label' => $label,
        'subject' => $subject,
        'html' => $html,
        'group' => $group,
        'custom' => 1,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    save_setting('communication_email_templates_v236133', $templates);
    AuditLogger::record('settings', 'email_templates', 'update', null, ['key'=>$newKey,'label'=>$label], 'E-Mail-Vorlage gespeichert');
    json_response(['ok'=>true,'message'=>'E-Mail-Vorlage gespeichert.','templates'=>v236_email_templates()]);
}

function delete_email_template_v236133(): never
{
    $user = Auth::user();
    if (!v236_can_send_direct_customer_email($user)) throw new ForbiddenException('Für E-Mail-Vorlagen fehlt die Berechtigung.');
    $d = request_data();
    $key = preg_replace('/[^a-z0-9_\-]/i', '', (string)($d['key'] ?? ''));
    if ($key === '') throw new ValidationException('Vorlage fehlt.');
    $templates = v236_email_templates(false);
    unset($templates[$key]);
    save_setting('communication_email_templates_v236133', $templates);
    AuditLogger::record('settings', 'email_templates', 'delete', ['key'=>$key], null, 'E-Mail-Vorlage gelöscht');
    json_response(['ok'=>true,'message'=>'E-Mail-Vorlage gelöscht.','templates'=>v236_email_templates()]);
}

function v236_email_templates(bool $withDefaults = true): array
{
    $stored = setting('communication_email_templates_v236133', []);
    if (!is_array($stored)) $stored = [];
    if (!$withDefaults) return $stored;
    return array_replace_recursive(v236_default_email_templates_v236133(), $stored);
}

function v236_default_email_templates_v236133(): array
{
    return [
        'freie_nachricht' => ['label'=>'Freie Nachricht','group'=>'Allgemein','subject'=>'Ihre Buchung {booking_reference}','html'=>'<p>Hallo {guest_first_name},</p><p></p><p>Viele Grüße<br>{company_signature}</p>','custom'=>0],
        'buchungsbestaetigung' => ['label'=>'Buchungsbestätigung','group'=>'Buchung','subject'=>'Ihre Buchungsbestätigung {booking_reference}','html'=>'<p>Hallo {guest_first_name},</p><p>vielen Dank für Ihre Buchung <b>{booking_reference}</b> vom <b>{arrival}</b> bis <b>{departure}</b>.</p><p>Unterkunft: {apartment_type} {apartment_name}</p><p><a href="{customer_portal_link}">Kundenbereich öffnen</a></p><p>Viele Grüße<br>{company_signature}</p>','custom'=>0],
        'angebot_nachfassen' => ['label'=>'Angebot nachfassen','group'=>'Angebot','subject'=>'Haben Sie noch Fragen zu unserem Angebot {offer_reference}','html'=>'<p>Hallo {guest_first_name},</p><p>wir wollten kurz nachfragen, ob Sie zu unserem Angebot noch Fragen haben.</p><p>Viele Grüße<br>{company_signature}</p>','custom'=>0],
        'zahlungserinnerung' => ['label'=>'Zahlungserinnerung','group'=>'Zahlung','subject'=>'Freundliche Erinnerung zur Zahlung {booking_reference}','html'=>'<p>Hallo {guest_first_name},</p><p>zu Ihrer Buchung <b>{booking_reference}</b> ist noch ein Betrag von <b>{amount_due}</b> offen.</p><p>Fällig am: {payment_due_date}</p><p>Viele Grüße<br>{company_signature}</p>','custom'=>0],
        'checkin_fehlt' => ['label'=>'Check-in-Daten fehlen','group'=>'Check-in','subject'=>'Online-Check-in fehlt noch {booking_reference}','html'=>'<p>Hallo {guest_first_name},</p><p>für Ihre Anreise am <b>{arrival}</b> fehlen uns noch die Check-in-Daten.</p><p><a href="{checkin_link}">Online-Check-in öffnen</a></p><p>Vielen Dank<br>{company_signature}</p>','custom'=>0],
        'anreiseinformation' => ['label'=>'Anreiseinformation','group'=>'Anreise','subject'=>'Informationen zu Ihrer Anreise am {arrival}','html'=>'<p>Hallo {guest_first_name},</p><p>hier erhalten Sie die wichtigsten Informationen für Ihre Anreise.</p><ul><li>Anreise: {arrival}</li><li>Abreise: {departure}</li><li>Unterkunft: {apartment_type}</li></ul><p>{map_link}</p><p>Viele Grüße<br>{company_signature}</p>','custom'=>0],
        'storno_absage' => ['label'=>'Storno / Absage','group'=>'Storno','subject'=>'Ihre Anfrage/Buchung {booking_reference}','html'=>'<p>Hallo {guest_first_name},</p><p>wir melden uns zu Ihrer Anfrage/Buchung <b>{booking_reference}</b>.</p><p></p><p>Viele Grüße<br>{company_signature}</p>','custom'=>0],
        'de_freie_rueckfrage' => ['label'=>'DE · Freie Rückfrage','group'=>'DE','subject'=>'Rückfrage zu Ihrer Buchung {booking_reference}','html'=>'<p>Hallo {guest_first_name},</p><p>wir haben eine kurze Rückfrage zu Ihrer Buchung <b>{booking_reference}</b>.</p><p></p><p>Viele Grüße<br>{company_signature}</p>','custom'=>0],
        'en_booking_confirm' => ['label'=>'EN · Booking confirmation','group'=>'EN','subject'=>'Your booking confirmation {booking_reference}','html'=>'<p>Hello {guest_first_name},</p><p>thank you for your booking <b>{booking_reference}</b> from <b>{arrival}</b> to <b>{departure}</b>.</p><p>You can open your guest area here: <a href="{customer_portal_link}">Guest area</a></p><p>Kind regards<br>{company_signature}</p>','custom'=>0],
        'en_checkin_missing' => ['label'=>'EN · Check-in reminder','group'=>'EN','subject'=>'Online check-in missing {booking_reference}','html'=>'<p>Hello {guest_first_name},</p><p>we still need your online check-in details for your arrival on <b>{arrival}</b>.</p><p><a href="{checkin_link}">Open online check-in</a></p><p>Kind regards<br>{company_signature}</p>','custom'=>0],
        'es_confirmacion' => ['label'=>'ES · Confirmación de reserva','group'=>'ES','subject'=>'Confirmación de su reserva {booking_reference}','html'=>'<p>Hola {guest_first_name},</p><p>gracias por su reserva <b>{booking_reference}</b> del <b>{arrival}</b> al <b>{departure}</b>.</p><p>Puede abrir su área de cliente aquí: <a href="{customer_portal_link}">Área de cliente</a></p><p>Un saludo<br>{company_signature}</p>','custom'=>0],
        'es_pago' => ['label'=>'ES · Información de pago','group'=>'ES','subject'=>'Información de pago {booking_reference}','html'=>'<p>Hola {guest_first_name},</p><p>para su reserva <b>{booking_reference}</b> queda pendiente el importe de <b>{amount_due}</b>.</p><p>Muchas gracias<br>{company_signature}</p>','custom'=>0],
        'fr_confirmation' => ['label'=>'FR · Confirmation de réservation','group'=>'FR','subject'=>'Confirmation de votre réservation {booking_reference}','html'=>'<p>Bonjour {guest_first_name},</p><p>merci pour votre réservation <b>{booking_reference}</b> du <b>{arrival}</b> au <b>{departure}</b>.</p><p>Votre espace client: <a href="{customer_portal_link}">ouvrir</a></p><p>Cordialement<br>{company_signature}</p>','custom'=>0],
        'nl_boeking' => ['label'=>'NL · Boekingsbevestiging','group'=>'NL','subject'=>'Uw boekingsbevestiging {booking_reference}','html'=>'<p>Hallo {guest_first_name},</p><p>bedankt voor uw boeking <b>{booking_reference}</b> van <b>{arrival}</b> tot <b>{departure}</b>.</p><p>Gastomgeving: <a href="{customer_portal_link}">openen</a></p><p>Met vriendelijke groet<br>{company_signature}</p>','custom'=>0],
    ];
}
