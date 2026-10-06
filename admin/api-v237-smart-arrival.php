<?php
declare(strict_types=1);

/* StayPilot V2.3.6.140 – Smart Arrival Phase 5
   Manuelle Meldeschein-Erfassung fuer an der Rezeption von Hand ausgefuellte Formulare.
   Keine zweite Meldeschein-Logik: erfasst in vorhandene booking_travellers/booking_checkins und protokolliert nur die Quelle. */

function smart_arrival_v237_ensure_schema(): void
{
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS smart_arrival_exports (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        export_type VARCHAR(40) NOT NULL DEFAULT 'prepared_csv',
        region VARCHAR(40) NOT NULL DEFAULT 'catalonia_mossos',
        status VARCHAR(30) NOT NULL DEFAULT 'prepared',
        date_from DATE NULL,
        date_to DATE NULL,
        file_path VARCHAR(500) NULL,
        json_path VARCHAR(500) NULL,
        row_count INT UNSIGNED NOT NULL DEFAULT 0,
        prepared_by INT UNSIGNED NULL,
        prepared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        marked_reported_at DATETIME NULL,
        marked_reported_by INT UNSIGNED NULL,
        note VARCHAR(1000) NULL,
        CONSTRAINT fk_smart_arrival_exports_prepared_by FOREIGN KEY(prepared_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_smart_arrival_exports_reported_by FOREIGN KEY(marked_reported_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_smart_arrival_exports_status(status,prepared_at),
        INDEX idx_smart_arrival_exports_period(date_from,date_to)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS smart_arrival_export_items (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        export_id BIGINT UNSIGNED NOT NULL,
        booking_id INT UNSIGNED NOT NULL,
        traveller_id INT UNSIGNED NULL,
        item_status VARCHAR(30) NOT NULL DEFAULT 'prepared',
        validation_json LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_smart_arrival_export_items_export FOREIGN KEY(export_id) REFERENCES smart_arrival_exports(id) ON DELETE CASCADE,
        CONSTRAINT fk_smart_arrival_export_items_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        CONSTRAINT fk_smart_arrival_export_items_traveller FOREIGN KEY(traveller_id) REFERENCES booking_travellers(id) ON DELETE SET NULL,
        INDEX idx_smart_arrival_export_items_booking(booking_id,traveller_id),
        INDEX idx_smart_arrival_export_items_status(item_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS smart_arrival_manual_entries (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        booking_id INT UNSIGNED NOT NULL,
        traveller_id INT UNSIGNED NULL,
        source_type VARCHAR(40) NOT NULL DEFAULT 'paper_form',
        status VARCHAR(30) NOT NULL DEFAULT 'captured',
        missing_fields LONGTEXT NULL,
        captured_by INT UNSIGNED NULL,
        captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        note VARCHAR(1000) NULL,
        CONSTRAINT fk_smart_arrival_manual_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        CONSTRAINT fk_smart_arrival_manual_traveller FOREIGN KEY(traveller_id) REFERENCES booking_travellers(id) ON DELETE SET NULL,
        CONSTRAINT fk_smart_arrival_manual_user FOREIGN KEY(captured_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_smart_arrival_manual_booking(booking_id,captured_at),
        INDEX idx_smart_arrival_manual_status(status,captured_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $dir = root_path('storage/smart-arrival-exports');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Deny from all\n");
}

function smart_arrival_export_rows_v237(string $from, string $to, string $scope = 'arrivals'): array
{
    smart_arrival_v237_ensure_schema();
    if (!valid_date($from) || !valid_date($to) || $from > $to) {
        throw new ValidationException('Gueltiger Zeitraum erforderlich.');
    }
    $dateColumn = $scope === 'departures' ? 'b.departure' : 'b.arrival';
    $sql = "SELECT b.id booking_id,b.reference,b.arrival,b.departure,b.planned_arrival_time booking_planned_arrival_time,
                   g.name guest_name,g.email guest_email,g.phone guest_phone,
                   bc.status checkin_status,bc.planned_arrival_time checkin_planned_arrival_time,bc.vehicle_plate,bc.consent_privacy,bc.consent_house_rules,bc.signature_name,bc.submitted_at,bc.reviewed_at,
                   t.id traveller_id,t.is_primary,t.first_name,t.last_name,t.second_last_name,t.gender,t.document_type,t.document_number,t.document_support_number,t.document_issue_date,t.document_country,t.place_of_birth,t.province,t.nationality,t.date_of_birth,t.address,t.city,t.country,t.postal_code,t.fixed_phone,t.mobile_phone,t.email,t.relationship_to_primary,t.minor,t.signature_status,t.notes traveller_notes
            FROM bookings b
            LEFT JOIN guests g ON g.id=b.guest_id
            LEFT JOIN booking_checkins bc ON bc.booking_id=b.id
            LEFT JOIN booking_travellers t ON t.booking_id=b.id
            WHERE {$dateColumn} BETWEEN ? AND ?
              AND b.deleted_at IS NULL
            ORDER BY {$dateColumn} ASC,b.id ASC,t.is_primary DESC,t.id ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $missing = [];
        foreach (['first_name'=>'Vorname','last_name'=>'Nachname','date_of_birth'=>'Geburtsdatum','nationality'=>'Nationalitaet','document_type'=>'Dokumenttyp','document_number'=>'Dokumentnummer'] as $key=>$label) {
            if (trim((string)($r[$key] ?? '')) === '') $missing[] = $label;
        }
        if (trim((string)($r['address'] ?? '')) === '') $missing[] = 'Adresse';
        if (trim((string)($r['city'] ?? '')) === '') $missing[] = 'Ort';
        if (trim((string)($r['country'] ?? '')) === '') $missing[] = 'Land';
        $status = count($missing) === 0 ? 'ready' : 'incomplete';
        if (empty($r['traveller_id'])) {
            $status = 'missing_traveller';
            $missing[] = 'Reisendendatensatz';
        }
        $out[] = [
            'booking_id'=>(int)$r['booking_id'],
            'traveller_id'=>isset($r['traveller_id']) ? (int)$r['traveller_id'] : 0,
            'reference'=>(string)($r['reference'] ?? ''),
            'arrival'=>(string)($r['arrival'] ?? ''),
            'departure'=>(string)($r['departure'] ?? ''),
            'planned_arrival_time'=>(string)($r['checkin_planned_arrival_time'] ?: $r['booking_planned_arrival_time'] ?: ''),
            'guest_name'=>(string)($r['guest_name'] ?? ''),
            'checkin_status'=>(string)($r['checkin_status'] ?? 'open'),
            'submitted_at'=>(string)($r['submitted_at'] ?? ''),
            'reviewed_at'=>(string)($r['reviewed_at'] ?? ''),
            'is_primary'=>(int)($r['is_primary'] ?? 0),
            'first_name'=>(string)($r['first_name'] ?? ''),
            'last_name'=>(string)($r['last_name'] ?? ''),
            'second_last_name'=>(string)($r['second_last_name'] ?? ''),
            'gender'=>(string)($r['gender'] ?? ''),
            'date_of_birth'=>(string)($r['date_of_birth'] ?? ''),
            'place_of_birth'=>(string)($r['place_of_birth'] ?? ''),
            'nationality'=>(string)($r['nationality'] ?? ''),
            'document_type'=>(string)($r['document_type'] ?? ''),
            'document_number'=>(string)($r['document_number'] ?? ''),
            'document_support_number'=>(string)($r['document_support_number'] ?? ''),
            'document_issue_date'=>(string)($r['document_issue_date'] ?? ''),
            'document_country'=>(string)($r['document_country'] ?? ''),
            'address'=>(string)($r['address'] ?? ''),
            'postal_code'=>(string)($r['postal_code'] ?? ''),
            'city'=>(string)($r['city'] ?? ''),
            'country'=>(string)($r['country'] ?? ''),
            'phone'=>(string)($r['mobile_phone'] ?: $r['fixed_phone'] ?: $r['guest_phone'] ?: ''),
            'email'=>(string)($r['email'] ?: $r['guest_email'] ?: ''),
            'vehicle_plate'=>(string)($r['vehicle_plate'] ?? ''),
            'signature_name'=>(string)($r['signature_name'] ?? ''),
            'validation_status'=>$status,
            'missing_fields'=>$missing,
        ];
    }
    return $out;
}

function smart_arrival_export_preview_v237(): never
{
    $from = (string)($_GET['from'] ?? date('Y-m-d'));
    $to = (string)($_GET['to'] ?? date('Y-m-d', strtotime('+7 days')));
    $scope = (string)($_GET['scope'] ?? 'arrivals');
    $rows = smart_arrival_export_rows_v237($from, $to, $scope);
    $ready = 0; $incomplete = 0; $missing = 0;
    foreach ($rows as $r) {
        if ($r['validation_status'] === 'ready') $ready++;
        elseif ($r['validation_status'] === 'missing_traveller') $missing++;
        else $incomplete++;
    }
    $exports = db()->query('SELECT id,export_type,region,status,date_from,date_to,row_count,prepared_at,marked_reported_at,note FROM smart_arrival_exports ORDER BY id DESC LIMIT 20')->fetchAll();
    json_response(['ok'=>true,'from'=>$from,'to'=>$to,'scope'=>$scope,'summary'=>['rows'=>count($rows),'ready'=>$ready,'incomplete'=>$incomplete,'missing_traveller'=>$missing],'rows'=>array_slice($rows,0,250),'exports'=>$exports,'message'=>'Vorschau erzeugt. Noch keine behördliche Übermittlung.']);
}

function smart_arrival_csv_v237(array $rows): string
{
    $headers = ['reference','arrival','departure','planned_arrival_time','first_name','last_name','second_last_name','gender','date_of_birth','place_of_birth','nationality','document_type','document_number','document_support_number','document_issue_date','document_country','address','postal_code','city','country','phone','email','vehicle_plate','checkin_status','submitted_at','reviewed_at','validation_status','missing_fields'];
    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, $headers, ';');
    foreach ($rows as $r) {
        $line = [];
        foreach ($headers as $h) {
            $value = (string)($h === 'missing_fields' ? implode('|', $r['missing_fields'] ?? []) : ($r[$h] ?? ''));
            $line[] = $value !== '' && in_array($value[0], ['=','+','-','@'], true) ? "'".$value : $value;
        }
        fputcsv($fh, $line, ';');
    }
    rewind($fh);
    return (string)stream_get_contents($fh);
}

function smart_arrival_export_prepare_v237(): never
{
    $d = request_data();
    $from = (string)($d['from'] ?? date('Y-m-d'));
    $to = (string)($d['to'] ?? date('Y-m-d', strtotime('+7 days')));
    $scope = (string)($d['scope'] ?? 'arrivals');
    $region = (string)setting('smart_arrival_export_region', 'catalonia_mossos');
    $rows = smart_arrival_export_rows_v237($from, $to, $scope);
    db()->exec("CREATE TABLE IF NOT EXISTS smart_arrival_manual_entries (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        booking_id INT UNSIGNED NOT NULL,
        traveller_id INT UNSIGNED NULL,
        source_type VARCHAR(40) NOT NULL DEFAULT 'paper_form',
        status VARCHAR(30) NOT NULL DEFAULT 'captured',
        missing_fields LONGTEXT NULL,
        captured_by INT UNSIGNED NULL,
        captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        note VARCHAR(1000) NULL,
        CONSTRAINT fk_smart_arrival_manual_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        CONSTRAINT fk_smart_arrival_manual_traveller FOREIGN KEY(traveller_id) REFERENCES booking_travellers(id) ON DELETE SET NULL,
        CONSTRAINT fk_smart_arrival_manual_user FOREIGN KEY(captured_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_smart_arrival_manual_booking(booking_id,captured_at),
        INDEX idx_smart_arrival_manual_status(status,captured_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $dir = root_path('storage/smart-arrival-exports');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $stamp = date('Ymd-His');
    $base = 'smart-arrival-'.$stamp.'-'.$from.'-'.$to;
    $csvRel = 'storage/smart-arrival-exports/'.$base.'.csv';
    $jsonRel = 'storage/smart-arrival-exports/'.$base.'.json';
    file_put_contents(root_path($csvRel), smart_arrival_csv_v237($rows));
    file_put_contents(root_path($jsonRel), json_encode(['region'=>$region,'scope'=>$scope,'from'=>$from,'to'=>$to,'created_at'=>date('c'),'rows'=>$rows], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
    $user = Auth::user();
    $stmt = db()->prepare('INSERT INTO smart_arrival_exports(export_type,region,status,date_from,date_to,file_path,json_path,row_count,prepared_by,note) VALUES(?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute(['prepared_csv',$region,'prepared',$from,$to,$csvRel,$jsonRel,count($rows),(int)($user['id'] ?? 0) ?: null,'Vorbereiteter Export ohne automatische Behoerdenuebermittlung.']);
    $exportId = (int)db()->lastInsertId();
    $item = db()->prepare('INSERT INTO smart_arrival_export_items(export_id,booking_id,traveller_id,item_status,validation_json) VALUES(?,?,?,?,?)');
    foreach ($rows as $r) {
        $item->execute([$exportId,(int)$r['booking_id'],(int)$r['traveller_id'] ?: null,$r['validation_status'],json_encode(['missing_fields'=>$r['missing_fields']], JSON_UNESCAPED_UNICODE)]);
    }
    AuditLogger::record('smart_arrival_export',$exportId,'prepare',null,['from'=>$from,'to'=>$to,'rows'=>count($rows),'region'=>$region],'Smart-Arrival-Export vorbereitet');
    json_response(['ok'=>true,'message'=>'Export vorbereitet. Keine automatische Behördenübermittlung durchgeführt.','export_id'=>$exportId,'row_count'=>count($rows),'csv_url'=>'smart-arrival-export.php?id='.$exportId.'&type=csv','json_url'=>'smart-arrival-export.php?id='.$exportId.'&type=json']);
}

function smart_arrival_export_mark_reported_v237(): never
{
    smart_arrival_v237_ensure_schema();
    $d = request_data();
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) throw new ValidationException('Export-ID fehlt.');
    $user = Auth::user();
    db()->prepare("UPDATE smart_arrival_exports SET status='reported_manually', marked_reported_at=NOW(), marked_reported_by=?, note=? WHERE id=?")->execute([(int)($user['id'] ?? 0) ?: null, trim((string)($d['note'] ?? 'Manuell als gemeldet markiert.')), $id]);
    db()->prepare("UPDATE smart_arrival_export_items SET item_status='reported_manually' WHERE export_id=?")->execute([$id]);
    AuditLogger::record('smart_arrival_export',$id,'mark_reported',null,['note'=>(string)($d['note'] ?? '')],'Smart-Arrival-Export manuell als gemeldet markiert');
    json_response(['ok'=>true,'message'=>'Export wurde manuell als gemeldet markiert.']);
}


function smart_arrival_readiness_v238(): never
{
    $from = (string)($_GET['from'] ?? date('Y-m-d'));
    $to = (string)($_GET['to'] ?? date('Y-m-d', strtotime('+7 days')));
    if (!valid_date($from) || !valid_date($to) || $from > $to) {
        throw new ValidationException('Gueltiger Zeitraum erforderlich.');
    }
    $rows = smart_arrival_export_rows_v237($from, $to, 'arrivals');
    $bookings = [];
    foreach ($rows as $r) {
        $bid = (int)$r['booking_id'];
        if (!isset($bookings[$bid])) {
            $bookings[$bid] = [
                'booking_id'=>$bid,
                'reference'=>$r['reference'],
                'arrival'=>$r['arrival'],
                'departure'=>$r['departure'],
                'guest_name'=>$r['guest_name'],
                'planned_arrival_time'=>$r['planned_arrival_time'],
                'checkin_status'=>$r['checkin_status'],
                'submitted_at'=>$r['submitted_at'],
                'reviewed_at'=>$r['reviewed_at'],
                'travellers'=>0,
                'ready_travellers'=>0,
                'missing_traveller'=>0,
                'missing_fields'=>[],
                'status'=>'ready',
                'next_action'=>'Pruefen und bei Bedarf Meldeschein im bestehenden Ablauf erzeugen.'
            ];
        }
        $bookings[$bid]['travellers']++;
        if ($r['validation_status'] === 'ready') {
            $bookings[$bid]['ready_travellers']++;
        } elseif ($r['validation_status'] === 'missing_traveller') {
            $bookings[$bid]['missing_traveller']++;
            $bookings[$bid]['status'] = 'missing_traveller';
        } else {
            if ($bookings[$bid]['status'] === 'ready') $bookings[$bid]['status'] = 'incomplete';
        }
        foreach (($r['missing_fields'] ?? []) as $mf) {
            $bookings[$bid]['missing_fields'][$mf] = true;
        }
    }
    $summary = ['bookings'=>count($bookings),'ready'=>0,'incomplete'=>0,'missing_traveller'=>0,'submitted'=>0,'reviewed'=>0];
    foreach ($bookings as &$b) {
        $b['missing_fields'] = array_keys($b['missing_fields']);
        if ($b['missing_traveller'] > 0 || $b['travellers'] === 0) {
            $b['status'] = 'missing_traveller';
            $b['next_action'] = 'Reisende im vorhandenen Online-Check-in erfassen oder Gast erneut um Check-in bitten.';
        } elseif ($b['status'] === 'incomplete') {
            $b['next_action'] = 'Fehlende Pflichtfelder im bestehenden Reisenden-/Meldeschein-Ablauf nachtragen.';
        } elseif (trim((string)$b['reviewed_at']) === '') {
            $b['status'] = 'needs_review';
            $b['next_action'] = 'Daten in der bestehenden Check-in-Pruefung freigeben.';
        }
        if (trim((string)$b['submitted_at']) !== '') $summary['submitted']++;
        if (trim((string)$b['reviewed_at']) !== '') $summary['reviewed']++;
        if ($b['status'] === 'ready') $summary['ready']++;
        elseif ($b['status'] === 'missing_traveller') $summary['missing_traveller']++;
        else $summary['incomplete']++;
    }
    unset($b);
    json_response(['ok'=>true,'from'=>$from,'to'=>$to,'summary'=>$summary,'items'=>array_values($bookings),'message'=>'Smart-Arrival-Anreisepruefung erzeugt. Keine neuen Meldescheine oder Housekeeping-Ablaeufe angelegt.']);
}


function smart_arrival_required_missing_v239(array $r): array
{
    $map = ['first_name'=>'Vorname','last_name'=>'Nachname','gender'=>'Geschlecht','date_of_birth'=>'Geburtsdatum','nationality'=>'Nationalitaet','document_type'=>'Dokumenttyp','document_number'=>'Dokumentnummer','address'=>'Adresse','postal_code'=>'Postleitzahl','city'=>'Ort','country'=>'Land'];
    $missing = [];
    foreach ($map as $key=>$label) if (trim((string)($r[$key] ?? '')) === '') $missing[] = $label;
    return $missing;
}

function smart_arrival_manual_entry_save_v239(): never
{
    if (!normalize_bool(setting('smart_arrival_manual_form_enabled', 1))) throw new ValidationException('Manuelle Meldeschein-Erfassung ist deaktiviert.');
    smart_arrival_v237_ensure_schema();
    $d = request_data();
    $bookingId = (int)($d['booking_id'] ?? 0);
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    if (!fetch_booking_row($bookingId)) throw new ValidationException('Buchung nicht gefunden.');
    $row = is_array($d['traveller'] ?? null) ? $d['traveller'] : $d;
    $first = trim((string)($row['first_name'] ?? ''));
    $last = trim((string)($row['last_name'] ?? ''));
    if ($first === '' || $last === '') throw new ValidationException('Vor- und Nachname sind erforderlich.');
    $dob = nullable_date($row['date_of_birth'] ?? null);
    $minor = normalize_bool($row['minor'] ?? 0);
    if ($dob) { $age = (new DateTimeImmutable($dob))->diff(new DateTimeImmutable('today'))->y; if ($age < 18) $minor = 1; }
    $existingId = (int)($row['id'] ?? 0);
    $isPrimary = normalize_bool($row['is_primary'] ?? 0);
    $payload = [
        'is_primary'=>$isPrimary,'first_name'=>$first,'last_name'=>$last,
        'second_last_name'=>trim((string)($row['second_last_name'] ?? '')) ?: null,
        'gender'=>trim((string)($row['gender'] ?? '')) ?: null,
        'document_number'=>trim((string)($row['document_number'] ?? '')) ?: null,
        'document_support_number'=>trim((string)($row['document_support_number'] ?? '')) ?: null,
        'document_type'=>trim((string)($row['document_type'] ?? '')) ?: null,
        'document_issue_date'=>nullable_date($row['document_issue_date'] ?? null),
        'document_country'=>trim((string)($row['document_country'] ?? '')) ?: null,
        'place_of_birth'=>trim((string)($row['place_of_birth'] ?? '')) ?: null,
        'province'=>trim((string)($row['province'] ?? '')) ?: null,
        'nationality'=>trim((string)($row['nationality'] ?? '')) ?: null,
        'date_of_birth'=>$dob,
        'address'=>trim((string)($row['address'] ?? '')) ?: null,
        'city'=>trim((string)($row['city'] ?? '')) ?: null,
        'country'=>trim((string)($row['country'] ?? '')) ?: null,
        'postal_code'=>trim((string)($row['postal_code'] ?? '')) ?: null,
        'fixed_phone'=>trim((string)($row['fixed_phone'] ?? '')) ?: null,
        'mobile_phone'=>trim((string)($row['mobile_phone'] ?? '')) ?: null,
        'email'=>trim((string)($row['email'] ?? '')) ?: null,
        'relationship_to_primary'=>trim((string)($row['relationship_to_primary'] ?? '')) ?: null,
        'minor'=>$minor,
        'signature_status'=>trim((string)($row['signature_status'] ?? 'paper_signed')) ?: 'paper_signed',
        'notes'=>trim((string)($row['notes'] ?? 'Von handschriftlichem Meldeschein übernommen'))
    ];
    $missing = smart_arrival_required_missing_v239($payload);
    db()->beginTransaction();
    try {
        db()->prepare('INSERT IGNORE INTO booking_checkins(booking_id,status,updated_by) VALUES(?,?,?)')->execute([$bookingId,'open',(int)(Auth::user()['id'] ?? 0) ?: null]);
        if ($existingId > 0) {
            $stmt = db()->prepare('UPDATE booking_travellers SET is_primary=?,first_name=?,last_name=?,second_last_name=?,gender=?,document_number=?,document_support_number=?,document_type=?,document_issue_date=?,document_country=?,place_of_birth=?,province=?,nationality=?,date_of_birth=?,address=?,city=?,country=?,postal_code=?,fixed_phone=?,mobile_phone=?,email=?,relationship_to_primary=?,minor=?,signature_status=?,notes=? WHERE id=? AND booking_id=?');
            $stmt->execute([$payload['is_primary'],$payload['first_name'],$payload['last_name'],$payload['second_last_name'],$payload['gender'],$payload['document_number'],$payload['document_support_number'],$payload['document_type'],$payload['document_issue_date'],$payload['document_country'],$payload['place_of_birth'],$payload['province'],$payload['nationality'],$payload['date_of_birth'],$payload['address'],$payload['city'],$payload['country'],$payload['postal_code'],$payload['fixed_phone'],$payload['mobile_phone'],$payload['email'],$payload['relationship_to_primary'],$payload['minor'],$payload['signature_status'],$payload['notes'],$existingId,$bookingId]);
            $travellerId = $existingId;
        } else {
            $stmt = db()->prepare('INSERT INTO booking_travellers(booking_id,is_primary,first_name,last_name,second_last_name,gender,document_number,document_support_number,document_type,document_issue_date,document_country,place_of_birth,province,nationality,date_of_birth,address,city,country,postal_code,fixed_phone,mobile_phone,email,relationship_to_primary,minor,signature_status,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$bookingId,$payload['is_primary'],$payload['first_name'],$payload['last_name'],$payload['second_last_name'],$payload['gender'],$payload['document_number'],$payload['document_support_number'],$payload['document_type'],$payload['document_issue_date'],$payload['document_country'],$payload['place_of_birth'],$payload['province'],$payload['nationality'],$payload['date_of_birth'],$payload['address'],$payload['city'],$payload['country'],$payload['postal_code'],$payload['fixed_phone'],$payload['mobile_phone'],$payload['email'],$payload['relationship_to_primary'],$payload['minor'],$payload['signature_status'],$payload['notes']]);
            $travellerId = (int)db()->lastInsertId();
        }
        if ($isPrimary) db()->prepare('UPDATE booking_travellers SET is_primary=0 WHERE booking_id=? AND id<>?')->execute([$bookingId,$travellerId]);
        $all = db()->prepare('SELECT * FROM booking_travellers WHERE booking_id=?'); $all->execute([$bookingId]);
        $totalMissing = 0; foreach ($all->fetchAll() as $tr) $totalMissing += count(smart_arrival_required_missing_v239($tr));
        db()->prepare('UPDATE bookings SET police_status=?, police_sent_at=NULL WHERE id=?')->execute([$totalMissing === 0 ? 'ready' : 'open', $bookingId]);
        db()->prepare('UPDATE booking_checkins SET status=?, updated_by=?, updated_at=NOW() WHERE booking_id=?')->execute([$totalMissing === 0 ? 'manual_ready' : 'manual_incomplete', (int)(Auth::user()['id'] ?? 0) ?: null, $bookingId]);
        $user = Auth::user();
        $entry = db()->prepare('INSERT INTO smart_arrival_manual_entries(booking_id,traveller_id,source_type,status,missing_fields,captured_by,note) VALUES(?,?,?,?,?,?,?)');
        $entry->execute([$bookingId,$travellerId,'paper_form',count($missing) ? 'incomplete' : 'captured',json_encode($missing, JSON_UNESCAPED_UNICODE),(int)($user['id'] ?? 0) ?: null,trim((string)($d['note'] ?? 'Von handschriftlichem Meldeschein uebernommen.'))]);
        AuditLogger::record('booking_traveller',$travellerId,'manual_paper_capture',null,['booking_id'=>$bookingId,'missing_fields'=>$missing],'Daten aus handschriftlichem Meldeschein übernommen');
        db()->commit();
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
    json_response(['ok'=>true,'message'=>count($missing) ? 'Person gespeichert, aber Angaben fehlen: '.implode(', ', $missing) : 'Person aus handschriftlichem Meldeschein gespeichert. Bestehender Meldeschein kann nun im normalen Ablauf erzeugt/geprüft werden.','traveller_id'=>$travellerId,'missing_fields'=>$missing]);
}

function smart_arrival_manual_entries_v239(): never
{
    smart_arrival_v237_ensure_schema();
    $bookingId = (int)($_GET['booking_id'] ?? 0);
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    $stmt = db()->prepare('SELECT e.*,u.name captured_by_name,t.first_name,t.last_name FROM smart_arrival_manual_entries e LEFT JOIN users u ON u.id=e.captured_by LEFT JOIN booking_travellers t ON t.id=e.traveller_id WHERE e.booking_id=? ORDER BY e.id DESC LIMIT 30');
    $stmt->execute([$bookingId]);
    json_response(['ok'=>true,'entries'=>$stmt->fetchAll()]);
}


function smart_arrival_booking_customer_token_v240(int $bookingId): string
{
    $stmt = db()->prepare('SELECT token_encrypted FROM booking_customer_access WHERE booking_id=? AND active=1 LIMIT 1');
    $stmt->execute([$bookingId]);
    $enc = $stmt->fetchColumn();
    if (!$enc) return '';
    try { return Crypto::decrypt((string)$enc); } catch (Throwable) { return ''; }
}

function smart_arrival_paper_scan_link_v240(): never
{
    if (!normalize_bool(setting('smart_arrival_paper_scan_enabled', 1))) {
        throw new ValidationException('Der optionale Papierformular-Handy-Scan ist deaktiviert. Der normale Check-in bleibt unveraendert moeglich.');
    }
    $bookingId = (int)($_GET['booking_id'] ?? 0);
    if ($bookingId <= 0) throw new ValidationException('Buchung fehlt.');
    if (!fetch_booking_row($bookingId)) throw new ValidationException('Buchung nicht gefunden.');
    $token = smart_arrival_booking_customer_token_v240($bookingId);
    if ($token === '') throw new ValidationException('Fuer diese Buchung gibt es noch keinen aktiven Kunden-/Check-in-Link. Bitte zuerst den normalen Check-in-Link erzeugen/senden.');
    $url = HousekeepingWorkflow::applicationUrl('papier-scan.php?token=' . rawurlencode($token));
    json_response(['ok'=>true,'url'=>$url,'message'=>'Optionaler Handy-Link fuer Papierformular-Foto erzeugt. Der normale Rezeption- und Gast-Check-in bleibt unveraendert moeglich.']);
}
