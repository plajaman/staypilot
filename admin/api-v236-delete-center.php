<?php
declare(strict_types=1);

function dc_table_exists_v23663(string $table): bool
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function dc_column_exists_v23663(string $table, string $column): bool
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function dc_add_column_v23663(string $table, string $column, string $definition): void
{
    if (!dc_table_exists_v23663($table) || dc_column_exists_v23663($table, $column)) return;
    db()->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
}

function ensure_delete_center_v23663(): void
{
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS logical_delete_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(60) NOT NULL,
        entity_id BIGINT UNSIGNED NOT NULL,
        entity_label VARCHAR(255) NULL,
        action VARCHAR(30) NOT NULL,
        reason TEXT NULL,
        old_status VARCHAR(60) NULL,
        new_status VARCHAR(60) NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_logical_delete_entity (entity_type, entity_id, created_at),
        INDEX idx_logical_delete_action (action, created_at)
    ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach (['guests','bookings','offers','internal_tasks','booking_documents','communication_log'] as $table) {
        if (!dc_table_exists_v23663($table)) continue;
        dc_add_column_v23663($table, 'deleted_at', 'DATETIME NULL');
        dc_add_column_v23663($table, 'deleted_by', 'INT UNSIGNED NULL');
        dc_add_column_v23663($table, 'delete_reason', 'TEXT NULL');
        dc_add_column_v23663($table, 'restored_at', 'DATETIME NULL');
        dc_add_column_v23663($table, 'restored_by', 'INT UNSIGNED NULL');
    }
}

function dc_pin_hash_v23663(string $pin): string
{
    return hash('sha256', 'staypilot-delete-center-v23663|' . $pin);
}

function dc_pin_is_configured_v23663(): bool
{
    return (string)setting('delete_center_pin_hash_v23663','') !== '';
}

function dc_verify_pin_v23663(?string $pin = null): void
{
    $pin = trim((string)($pin ?? (request_data()['pin'] ?? $_GET['pin'] ?? '')));
    if ($pin === '') throw new ForbiddenException('PIN für das Löschcenter fehlt.');
    $hash = (string)setting('delete_center_pin_hash_v23663','');
    if ($hash === '') {
        $hash = dc_pin_hash_v23663('9630');
    }
    if (!hash_equals($hash, dc_pin_hash_v23663($pin))) {
        throw new ForbiddenException('PIN für das Löschcenter ist falsch.');
    }
}

function dc_user_v23663(): array
{
    $user = Auth::requireLogin();
    $role = (string)($user['role'] ?? '');
    if (!in_array($role, ['admin','manager'], true)) {
        throw new ForbiddenException('Das Löschcenter ist nur für Admin/Manager freigegeben.');
    }
    return $user;
}

function dc_label_v23663(string $type, array $row): string
{
    return match ($type) {
        'guest' => trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? '')) . ((string)($row['email'] ?? '') !== '' ? ' · ' . (string)$row['email'] : ''),
        'booking' => trim(((string)($row['reference'] ?? '') ?: 'Buchung #' . (string)$row['id']) . ' · ' . (string)($row['guest_name'] ?? '') . ' · ' . (string)($row['arrival'] ?? '') . '–' . (string)($row['departure'] ?? '')),
        'offer' => trim(((string)($row['offer_number'] ?? '') ?: 'Angebot #' . (string)$row['id']) . ' · ' . (string)($row['guest_name'] ?? '') . ' · ' . (string)($row['arrival'] ?? '') . '–' . (string)($row['departure'] ?? '')),
        'task' => trim((string)($row['title'] ?? ('Aufgabe #' . (string)$row['id']))),
        'document' => trim(((string)($row['document_number'] ?? '') ?: 'Dokument #' . (string)$row['id']) . ' · ' . (string)($row['title'] ?? '')),
        'communication' => trim(((string)($row['subject'] ?? '') ?: 'Nachricht #' . (string)$row['id']) . ' · ' . (string)($row['recipient_address'] ?? '')),
        default => '#' . (string)($row['id'] ?? ''),
    };
}


function dc_sync_housekeeping_after_booking_delete_v23689(int $bookingId, string $reason): int
{
    if (!dc_table_exists_v23663('housekeeping_tasks')) return 0;
    $stmt = db()->prepare("UPDATE housekeeping_tasks
        SET status='cancelled',
            notes=CONCAT(COALESCE(notes,''),CASE WHEN COALESCE(notes,'')='' THEN '' ELSE '\n' END,?),
            updated_at=NOW()
        WHERE booking_id=? AND status IN ('open','planned','assigned','accepted')");
    $stmt->execute(['Automatisch durch Löschcenter storniert: '.$reason, $bookingId]);
    return $stmt->rowCount();
}

function dc_fetch_entity_v23663(string $type, int $id): ?array
{
    ensure_delete_center_v23663();
    switch ($type) {
        case 'guest':
            $stmt = db()->prepare('SELECT * FROM guests WHERE id=? LIMIT 1');
            break;
        case 'booking':
            $stmt = db()->prepare("SELECT b.*, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest_name FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id WHERE b.id=? LIMIT 1");
            break;
        case 'offer':
            $stmt = db()->prepare('SELECT * FROM offers WHERE id=? LIMIT 1');
            break;
        case 'task':
            $stmt = db()->prepare('SELECT * FROM internal_tasks WHERE id=? LIMIT 1');
            break;
        case 'document':
            $stmt = db()->prepare('SELECT * FROM booking_documents WHERE id=? LIMIT 1');
            break;
        case 'communication':
            $stmt = db()->prepare('SELECT * FROM communication_log WHERE id=? LIMIT 1');
            break;
        default:
            throw new ValidationException('Unbekannter Bereich.');
    }
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function dc_table_for_type_v23663(string $type): string
{
    return match ($type) {
        'guest' => 'guests',
        'booking' => 'bookings',
        'offer' => 'offers',
        'task' => 'internal_tasks',
        'document' => 'booking_documents',
        'communication' => 'communication_log',
        default => throw new ValidationException('Unbekannter Bereich.'),
    };
}

function dc_status_column_v23663(string $table): ?string
{
    return dc_column_exists_v23663($table, 'status') ? 'status' : null;
}

function delete_center_data_v23663(): never
{
    $user = dc_user_v23663();
    ensure_delete_center_v23663();
    dc_verify_pin_v23663();
    $q = trim((string)($_GET['q'] ?? ''));
    $like = '%' . $q . '%';
    $sections = [];

    if (dc_table_exists_v23663('guests')) {
        $where = "g.deleted_at IS NULL"; $params=[];
        if ($q !== '') { $where .= " AND (g.first_name LIKE ? OR g.last_name LIKE ? OR g.email LIKE ? OR g.phone LIKE ?)"; $params=[$like,$like,$like,$like]; }
        $stmt = db()->prepare("SELECT 'guest' entity_type,g.id,CONCAT(g.first_name,' ',g.last_name) title,g.email subtitle,g.deleted_at,g.delete_reason,(SELECT COUNT(*) FROM bookings b WHERE b.guest_id=g.id) relation_count FROM guests g WHERE $where ORDER BY g.last_name,g.first_name LIMIT 60");
        $stmt->execute($params); $activeGuests=$stmt->fetchAll();
        $stmt = db()->query("SELECT 'guest' entity_type,g.id,CONCAT(g.first_name,' ',g.last_name) title,g.email subtitle,g.deleted_at,g.delete_reason,(SELECT COUNT(*) FROM bookings b WHERE b.guest_id=g.id) relation_count FROM guests g WHERE g.deleted_at IS NOT NULL ORDER BY g.deleted_at DESC LIMIT 80");
        $sections['guests']=['label'=>'Gäste','active'=>$activeGuests,'deleted'=>$stmt->fetchAll()];
    }
    if (dc_table_exists_v23663('bookings')) {
        $where = "b.deleted_at IS NULL"; $params=[];
        if ($q !== '') { $where .= " AND (b.reference LIKE ? OR CONCAT(g.first_name,' ',g.last_name) LIKE ? OR a.name LIKE ?)"; $params=[$like,$like,$like]; }
        $stmt = db()->prepare("SELECT 'booking' entity_type,b.id,COALESCE(b.reference,CONCAT('Buchung #',b.id)) title,CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,''),' · ',b.arrival,'–',b.departure) subtitle,b.status,b.deleted_at,b.delete_reason,0 relation_count FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id LEFT JOIN apartments a ON a.id=b.apartment_id WHERE $where ORDER BY b.arrival DESC LIMIT 60");
        $stmt->execute($params); $activeBookings=$stmt->fetchAll();
        $stmt = db()->query("SELECT 'booking' entity_type,b.id,COALESCE(b.reference,CONCAT('Buchung #',b.id)) title,CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,''),' · ',b.arrival,'–',b.departure) subtitle,b.status,b.deleted_at,b.delete_reason,0 relation_count FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id WHERE b.deleted_at IS NOT NULL ORDER BY b.deleted_at DESC LIMIT 80");
        $sections['bookings']=['label'=>'Buchungen','active'=>$activeBookings,'deleted'=>$stmt->fetchAll()];
    }
    if (dc_table_exists_v23663('offers')) {
        $where = "o.deleted_at IS NULL"; $params=[];
        if ($q !== '') { $where .= " AND (o.offer_number LIKE ? OR o.guest_name LIKE ? OR o.guest_email LIKE ?)"; $params=[$like,$like,$like]; }
        $stmt = db()->prepare("SELECT 'offer' entity_type,o.id,COALESCE(o.offer_number,CONCAT('Angebot #',o.id)) title,CONCAT(o.guest_name,' · ',o.arrival,'–',o.departure) subtitle,o.status,o.deleted_at,o.delete_reason,0 relation_count FROM offers o WHERE $where ORDER BY o.updated_at DESC,o.id DESC LIMIT 60");
        $stmt->execute($params); $active=$stmt->fetchAll();
        $stmt = db()->query("SELECT 'offer' entity_type,o.id,COALESCE(o.offer_number,CONCAT('Angebot #',o.id)) title,CONCAT(o.guest_name,' · ',o.arrival,'–',o.departure) subtitle,o.status,o.deleted_at,o.delete_reason,0 relation_count FROM offers o WHERE o.deleted_at IS NOT NULL ORDER BY o.deleted_at DESC LIMIT 80");
        $sections['offers']=['label'=>'Angebote','active'=>$active,'deleted'=>$stmt->fetchAll()];
    }
    if (dc_table_exists_v23663('internal_tasks')) {
        $stmt = db()->prepare("SELECT 'task' entity_type,id,title,CONCAT(category,' · ',COALESCE(due_date,'ohne Datum')) subtitle,status,deleted_at,delete_reason,0 relation_count FROM internal_tasks WHERE deleted_at IS NULL" . ($q!=='' ? " AND (title LIKE ? OR description LIKE ? OR category LIKE ?)" : "") . " ORDER BY COALESCE(due_date,'9999-12-31'), due_time LIMIT 60");
        $stmt->execute($q!==''?[$like,$like,$like]:[]); $active=$stmt->fetchAll();
        $stmt = db()->query("SELECT 'task' entity_type,id,title,CONCAT(category,' · ',COALESCE(due_date,'ohne Datum')) subtitle,status,deleted_at,delete_reason,0 relation_count FROM internal_tasks WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 80");
        $sections['tasks']=['label'=>'Aufgaben','active'=>$active,'deleted'=>$stmt->fetchAll()];
    }
    if (dc_table_exists_v23663('booking_documents')) {
        $stmt = db()->prepare("SELECT 'document' entity_type,id,COALESCE(document_number,title) title,CONCAT(document_type,' · Buchung ',booking_id) subtitle,status,deleted_at,delete_reason,0 relation_count FROM booking_documents WHERE deleted_at IS NULL" . ($q!=='' ? " AND (title LIKE ? OR document_number LIKE ? OR document_type LIKE ?)" : "") . " ORDER BY id DESC LIMIT 60");
        $stmt->execute($q!==''?[$like,$like,$like]:[]); $active=$stmt->fetchAll();
        $stmt = db()->query("SELECT 'document' entity_type,id,COALESCE(document_number,title) title,CONCAT(document_type,' · Buchung ',booking_id) subtitle,status,deleted_at,delete_reason,0 relation_count FROM booking_documents WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 80");
        $sections['documents']=['label'=>'Dokumente','active'=>$active,'deleted'=>$stmt->fetchAll()];
    }
    if (dc_table_exists_v23663('communication_log')) {
        $stmt = db()->prepare("SELECT 'communication' entity_type,id,COALESCE(subject,channel) title,CONCAT(recipient_address,' · ',status) subtitle,status,deleted_at,delete_reason,0 relation_count FROM communication_log WHERE deleted_at IS NULL" . ($q!=='' ? " AND (subject LIKE ? OR recipient_address LIKE ? OR message_excerpt LIKE ?)" : "") . " ORDER BY id DESC LIMIT 60");
        $stmt->execute($q!==''?[$like,$like,$like]:[]); $active=$stmt->fetchAll();
        $stmt = db()->query("SELECT 'communication' entity_type,id,COALESCE(subject,channel) title,CONCAT(recipient_address,' · ',status) subtitle,status,deleted_at,delete_reason,0 relation_count FROM communication_log WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT 80");
        $sections['communications']=['label'=>'Kommunikation','active'=>$active,'deleted'=>$stmt->fetchAll()];
    }
    if (function_exists('dc_file_section_v23664')) {
        $sections['files'] = dc_file_section_v23664($q);
    }
    $log = db()->query("SELECT l.*,u.name user_name FROM logical_delete_log l LEFT JOIN users u ON u.id=l.created_by ORDER BY l.id DESC LIMIT 80")->fetchAll();
    json_response(['ok'=>true,'pin_configured'=>dc_pin_is_configured_v23663(),'sections'=>$sections,'log'=>$log,'user_role'=>$user['role'] ?? '']);
}

function delete_center_soft_delete_v23663(): never
{
    $user = dc_user_v23663();
    ensure_delete_center_v23663();
    $d = request_data();
    dc_verify_pin_v23663((string)($d['pin'] ?? ''));
    $type = (string)($d['entity_type'] ?? ''); $id = (int)($d['id'] ?? 0); $reason = trim((string)($d['reason'] ?? ''));
    if (!$id) throw new ValidationException('Eintrag fehlt.');
    if ($reason === '') throw new ValidationException('Bitte einen Löschgrund angeben.');
    $row = dc_fetch_entity_v23663($type,$id); if (!$row) throw new NotFoundException('Eintrag nicht gefunden.');
    $table = dc_table_for_type_v23663($type); $oldStatus = $row['status'] ?? null; $newStatus = $oldStatus;
    $sql = "UPDATE `$table` SET deleted_at=COALESCE(deleted_at,NOW()), deleted_by=?, delete_reason=?, restored_at=NULL, restored_by=NULL";
    $params = [(int)($user['id'] ?? 0), $reason];
    if ($table === 'bookings' && dc_column_exists_v23663('bookings','status')) { $sql .= ", status='cancelled'"; $newStatus='cancelled'; }
    elseif ($table === 'offers' && dc_column_exists_v23663('offers','status')) { $sql .= ", status='archived', archived_at=COALESCE(archived_at,NOW())"; $newStatus='archived'; }
    elseif ($table === 'internal_tasks' && dc_column_exists_v23663('internal_tasks','status')) { $sql .= ", status='archived'"; $newStatus='archived'; }
    elseif ($table === 'booking_documents' && dc_column_exists_v23663('booking_documents','status')) { $sql .= ", status='deleted'"; $newStatus='deleted'; }
    elseif ($table === 'communication_log' && dc_column_exists_v23663('communication_log','status')) { $sql .= ", status='hidden'"; $newStatus='hidden'; }
    $sql .= " WHERE id=?"; $params[] = $id;
    db()->prepare($sql)->execute($params);
    if ($type === 'booking') { dc_sync_housekeeping_after_booking_delete_v23689($id, $reason); }
    $label = dc_label_v23663($type,$row);
    db()->prepare('INSERT INTO logical_delete_log(entity_type,entity_id,entity_label,action,reason,old_status,new_status,created_by) VALUES(?,?,?,?,?,?,?,?)')->execute([$type,$id,$label,'delete',$reason,(string)$oldStatus,(string)$newStatus,(int)($user['id'] ?? 0)]);
    AuditLogger::record($type,$id,'logical_delete',$row,dc_fetch_entity_v23663($type,$id),'Logisch gelöscht: '.$reason);
    json_response(['ok'=>true,'message'=>'Eintrag wurde logisch gelöscht und bleibt im Löschcenter wiederherstellbar.']);
}

function delete_center_restore_v23663(): never
{
    $user = dc_user_v23663();
    ensure_delete_center_v23663();
    $d = request_data();
    dc_verify_pin_v23663((string)($d['pin'] ?? ''));
    $type = (string)($d['entity_type'] ?? ''); $id = (int)($d['id'] ?? 0); $reason = trim((string)($d['reason'] ?? 'Wiederhergestellt'));
    if (!$id) throw new ValidationException('Eintrag fehlt.');
    $row = dc_fetch_entity_v23663($type,$id); if (!$row) throw new NotFoundException('Eintrag nicht gefunden.');
    $table = dc_table_for_type_v23663($type); $oldStatus = $row['status'] ?? null; $newStatus = $oldStatus;
    $sql = "UPDATE `$table` SET deleted_at=NULL, deleted_by=NULL, delete_reason=NULL, restored_at=NOW(), restored_by=?";
    $params = [(int)($user['id'] ?? 0)];
    if ($table === 'bookings' && (string)($row['status'] ?? '') === 'cancelled') { $sql .= ", status='inquiry'"; $newStatus='inquiry'; }
    elseif ($table === 'offers' && (string)($row['status'] ?? '') === 'archived') { $sql .= ", status='draft'"; $newStatus='draft'; }
    elseif ($table === 'internal_tasks' && (string)($row['status'] ?? '') === 'archived') { $sql .= ", status='open'"; $newStatus='open'; }
    elseif ($table === 'booking_documents' && (string)($row['status'] ?? '') === 'deleted') { $sql .= ", status='generated'"; $newStatus='generated'; }
    elseif ($table === 'communication_log' && (string)($row['status'] ?? '') === 'hidden') { $sql .= ", status='restored'"; $newStatus='restored'; }
    $sql .= " WHERE id=?"; $params[] = $id;
    db()->prepare($sql)->execute($params);
    $label = dc_label_v23663($type,$row);
    db()->prepare('INSERT INTO logical_delete_log(entity_type,entity_id,entity_label,action,reason,old_status,new_status,created_by) VALUES(?,?,?,?,?,?,?,?)')->execute([$type,$id,$label,'restore',$reason,(string)$oldStatus,(string)$newStatus,(int)($user['id'] ?? 0)]);
    AuditLogger::record($type,$id,'logical_restore',$row,dc_fetch_entity_v23663($type,$id),'Wiederhergestellt: '.$reason);
    json_response(['ok'=>true,'message'=>'Eintrag wurde wiederhergestellt.']);
}

function delete_center_change_pin_v23663(): never
{
    dc_user_v23663(); ensure_delete_center_v23663();
    $d = request_data(); $old = trim((string)($d['old_pin'] ?? '')); $new = trim((string)($d['new_pin'] ?? ''));
    dc_verify_pin_v23663($old);
    if (strlen($new) < 4) throw new ValidationException('Neue PIN muss mindestens 4 Zeichen haben.');
    save_setting('delete_center_pin_hash_v23663', dc_pin_hash_v23663($new));
    json_response(['ok'=>true,'message'=>'PIN wurde geändert.']);
}


function dc_count_sql_v23697(string $sql): int
{
    try { return (int)db()->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; }
}

function dc_sync_report_v23697(): array
{
    ensure_delete_center_v23663();
    $checks=[];
    if (dc_table_exists_v23663('bookings') && dc_table_exists_v23663('guests')) {
        $checks['bookings_of_deleted_guests']=['label'=>'Aktive Buchungen zu gelöschten Gästen','count'=>dc_count_sql_v23697("SELECT COUNT(*) FROM bookings b JOIN guests g ON g.id=b.guest_id WHERE g.deleted_at IS NOT NULL AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND COALESCE(b.status,'') NOT IN ('cancelled','rejected')")];
    }
    if (dc_table_exists_v23663('offers') && dc_table_exists_v23663('guests')) {
        $checks['offers_of_deleted_guests']=['label'=>'Aktive Angebote zu gelöschten Gästen','count'=>dc_count_sql_v23697("SELECT COUNT(*) FROM offers o JOIN guests g ON g.id=o.guest_id WHERE g.deleted_at IS NOT NULL AND (o.deleted_at IS NULL OR o.deleted_at='0000-00-00 00:00:00') AND COALESCE(o.status,'') NOT IN ('archived','declined','expired','converted')")];
        $checks['offers_by_deleted_guest_email']=['label'=>'Aktive Angebote mit E-Mail gelöschter Gäste','count'=>dc_count_sql_v23697("SELECT COUNT(*) FROM offers o WHERE (o.deleted_at IS NULL OR o.deleted_at='0000-00-00 00:00:00') AND COALESCE(o.status,'') NOT IN ('archived','declined','expired','converted') AND EXISTS (SELECT 1 FROM guests g WHERE g.deleted_at IS NOT NULL AND g.email IS NOT NULL AND g.email<>'' AND o.guest_email IS NOT NULL AND o.guest_email<>'' AND LOWER(TRIM(g.email))=LOWER(TRIM(o.guest_email)))")];
    }
    if (dc_table_exists_v23663('offers') && dc_table_exists_v23663('bookings')) {
        $checks['offers_of_deleted_bookings']=['label'=>'Aktive Angebote mit gelöschter/stornierter Buchung','count'=>dc_count_sql_v23697("SELECT COUNT(*) FROM offers o JOIN bookings b ON b.id=o.booking_id WHERE (b.deleted_at IS NOT NULL OR COALESCE(b.status,'') IN ('cancelled','rejected')) AND (o.deleted_at IS NULL OR o.deleted_at='0000-00-00 00:00:00') AND COALESCE(o.status,'') NOT IN ('archived','declined','expired')")];
        $checks['accepted_offers_without_active_guest']=['label'=>'Angenommene Angebote ohne aktiven Gastbezug','count'=>dc_count_sql_v23697("SELECT COUNT(*) FROM offers o LEFT JOIN guests g ON g.id=o.guest_id WHERE o.status='accepted' AND o.booking_id IS NULL AND (o.deleted_at IS NULL OR o.deleted_at='0000-00-00 00:00:00') AND ((o.guest_id IS NOT NULL AND (g.id IS NULL OR g.deleted_at IS NOT NULL)) OR EXISTS (SELECT 1 FROM guests gd WHERE gd.deleted_at IS NOT NULL AND gd.email IS NOT NULL AND gd.email<>'' AND o.guest_email IS NOT NULL AND o.guest_email<>'' AND LOWER(TRIM(gd.email))=LOWER(TRIM(o.guest_email))))")];
    }
    if (dc_table_exists_v23663('housekeeping_tasks') && dc_table_exists_v23663('bookings')) {
        $checks['housekeeping_of_deleted_bookings']=['label'=>'Aktive Putzaufgaben zu gelöschten/stornierten Buchungen','count'=>dc_count_sql_v23697("SELECT COUNT(*) FROM housekeeping_tasks h JOIN bookings b ON b.id=h.booking_id WHERE (b.deleted_at IS NOT NULL OR COALESCE(b.status,'') IN ('cancelled','rejected')) AND COALESCE(h.status,'') IN ('open','planned','assigned','accepted','in_progress','cleaning_done','inspection_required','ready_reported')")];
    }
    if (dc_table_exists_v23663('booking_payment_schedule') && dc_table_exists_v23663('bookings')) {
        $checks['open_payment_schedule_deleted_bookings']=['label'=>'Offene Zahlungsziele zu gelöschten/stornierten Buchungen','count'=>dc_count_sql_v23697("SELECT COUNT(*) FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id WHERE (b.deleted_at IS NOT NULL OR COALESCE(b.status,'') IN ('cancelled','rejected')) AND COALESCE(s.status,'') NOT IN ('received','waived','cancelled')")];
    }
    if (dc_table_exists_v23663('booking_documents') && dc_table_exists_v23663('bookings')) {
        $checks['documents_deleted_bookings']=['label'=>'Aktive Dokumente zu gelöschten/stornierten Buchungen','count'=>dc_count_sql_v23697("SELECT COUNT(*) FROM booking_documents d JOIN bookings b ON b.id=d.booking_id WHERE (b.deleted_at IS NOT NULL OR COALESCE(b.status,'') IN ('cancelled','rejected')) AND (d.deleted_at IS NULL OR d.deleted_at='0000-00-00 00:00:00')")];
    }
    return $checks;
}

function dc_upload_report_v23697(): array
{
    $root = dirname(__DIR__);
    $assets = $root . '/assets';
    $expected=(string)(config()['app_version'] ?? '');
    $files=[
        'VERSION.txt'=>$root.'/VERSION.txt',
        'admin/VERSION.txt'=>$root.'/admin/VERSION.txt',
        'config/config.php'=>$root.'/config/config.php',
        'assets/admin-v236-studio-page-actions.js'=>$assets.'/admin-v236-studio-page-actions.js',
        'assets/admin-v236-website-templates.js'=>$assets.'/admin-v236-website-templates.js',
        'admin/api-v236-delete-center.php'=>$root.'/admin/api-v236-delete-center.php',
    ];
    $rows=[];
    foreach($files as $label=>$path){
        $rows[]=['file'=>$label,'exists'=>is_file($path),'mtime'=>is_file($path)?date('Y-m-d H:i:s',(int)filemtime($path)):null,'size'=>is_file($path)?(int)filesize($path):0];
    }
    return ['expected_version'=>$expected,'version_txt'=>is_file($root.'/VERSION.txt')?trim((string)@file_get_contents($root.'/VERSION.txt')):'fehlt','admin_version_txt'=>is_file($root.'/admin/VERSION.txt')?trim((string)@file_get_contents($root.'/admin/VERSION.txt')):'fehlt','files'=>$rows];
}

function delete_center_sync_status_v23697(): never
{
    dc_user_v23663(); ensure_delete_center_v23663(); dc_verify_pin_v23663();
    json_response(['ok'=>true,'checks'=>dc_sync_report_v23697(),'upload'=>dc_upload_report_v23697()]);
}

function delete_center_global_sync_v23697(): never
{
    $user=dc_user_v23663(); ensure_delete_center_v23663(); $d=request_data(); dc_verify_pin_v23663((string)($d['pin']??''));
    $reason=trim((string)($d['reason']??'Systemweiter Abgleich gelöschter Daten'));
    $changed=[]; $pdo=db(); $pdo->beginTransaction();
    try{
        if (dc_table_exists_v23663('bookings') && dc_table_exists_v23663('guests')) {
            $stmt=$pdo->prepare("UPDATE bookings b JOIN guests g ON g.id=b.guest_id SET b.deleted_at=COALESCE(b.deleted_at,NOW()), b.deleted_by=?, b.delete_reason=CONCAT(COALESCE(b.delete_reason,''),CASE WHEN COALESCE(b.delete_reason,'')='' THEN '' ELSE '\n' END,?), b.status='cancelled' WHERE g.deleted_at IS NOT NULL AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND COALESCE(b.status,'') NOT IN ('cancelled','rejected')");
            $stmt->execute([(int)($user['id']??0),'Automatisch durch Löschcenter-Gesamtabgleich: Gast ist gelöscht. '.$reason]); $changed['Buchungen zu gelöschten Gästen']=$stmt->rowCount();
        }
        if (dc_table_exists_v23663('offers') && dc_table_exists_v23663('guests')) {
            $stmt=$pdo->prepare("UPDATE offers o JOIN guests g ON g.id=o.guest_id SET o.deleted_at=COALESCE(o.deleted_at,NOW()), o.deleted_by=?, o.delete_reason=CONCAT(COALESCE(o.delete_reason,''),CASE WHEN COALESCE(o.delete_reason,'')='' THEN '' ELSE '\n' END,?), o.status='archived', o.archived_at=COALESCE(o.archived_at,NOW()) WHERE g.deleted_at IS NOT NULL AND (o.deleted_at IS NULL OR o.deleted_at='0000-00-00 00:00:00') AND COALESCE(o.status,'') NOT IN ('archived','declined','expired','converted')");
            $stmt->execute([(int)($user['id']??0),'Automatisch durch Löschcenter-Gesamtabgleich: Gast ist gelöscht. '.$reason]); $changed['Angebote zu gelöschten Gästen']=$stmt->rowCount();
            $stmt=$pdo->prepare("UPDATE offers o SET o.deleted_at=COALESCE(o.deleted_at,NOW()), o.deleted_by=?, o.delete_reason=CONCAT(COALESCE(o.delete_reason,''),CASE WHEN COALESCE(o.delete_reason,'')='' THEN '' ELSE '
' END,?), o.status='archived', o.archived_at=COALESCE(o.archived_at,NOW()) WHERE EXISTS (SELECT 1 FROM guests g WHERE g.deleted_at IS NOT NULL AND g.email IS NOT NULL AND g.email<>'' AND o.guest_email IS NOT NULL AND o.guest_email<>'' AND LOWER(TRIM(g.email))=LOWER(TRIM(o.guest_email))) AND (o.deleted_at IS NULL OR o.deleted_at='0000-00-00 00:00:00') AND COALESCE(o.status,'') NOT IN ('archived','declined','expired','converted')");
            $stmt->execute([(int)($user['id']??0),'Automatisch durch Löschcenter-Gesamtabgleich: Angebots-E-Mail gehört zu gelöschtem Gast. '.$reason]); $changed['Angebote nach gelöschter Gast-E-Mail']=$stmt->rowCount();
        }
        if (dc_table_exists_v23663('offers') && dc_table_exists_v23663('bookings')) {
            $stmt=$pdo->prepare("UPDATE offers o JOIN bookings b ON b.id=o.booking_id SET o.deleted_at=COALESCE(o.deleted_at,NOW()), o.deleted_by=?, o.delete_reason=CONCAT(COALESCE(o.delete_reason,''),CASE WHEN COALESCE(o.delete_reason,'')='' THEN '' ELSE '\n' END,?), o.status='archived', o.archived_at=COALESCE(o.archived_at,NOW()) WHERE (b.deleted_at IS NOT NULL OR COALESCE(b.status,'') IN ('cancelled','rejected')) AND (o.deleted_at IS NULL OR o.deleted_at='0000-00-00 00:00:00') AND COALESCE(o.status,'') NOT IN ('archived','declined','expired')");
            $stmt->execute([(int)($user['id']??0),'Automatisch durch Löschcenter-Gesamtabgleich: Buchung ist gelöscht/storniert. '.$reason]); $changed['Angebote zu gelöschten Buchungen']=$stmt->rowCount();
            $stmt=$pdo->prepare("UPDATE offers o LEFT JOIN guests g ON g.id=o.guest_id SET o.deleted_at=COALESCE(o.deleted_at,NOW()), o.deleted_by=?, o.delete_reason=CONCAT(COALESCE(o.delete_reason,''),CASE WHEN COALESCE(o.delete_reason,'')='' THEN '' ELSE '
' END,?), o.status='archived', o.archived_at=COALESCE(o.archived_at,NOW()) WHERE o.status='accepted' AND o.booking_id IS NULL AND (o.deleted_at IS NULL OR o.deleted_at='0000-00-00 00:00:00') AND ((o.guest_id IS NOT NULL AND (g.id IS NULL OR g.deleted_at IS NOT NULL)) OR EXISTS (SELECT 1 FROM guests gd WHERE gd.deleted_at IS NOT NULL AND gd.email IS NOT NULL AND gd.email<>'' AND o.guest_email IS NOT NULL AND o.guest_email<>'' AND LOWER(TRIM(gd.email))=LOWER(TRIM(o.guest_email))))");
            $stmt->execute([(int)($user['id']??0),'Automatisch durch Löschcenter-Gesamtabgleich: angenommenes Angebot ohne aktiven Gastbezug. '.$reason]); $changed['Angenommene Angebote ohne aktiven Gast']=$stmt->rowCount();
        }
        if (dc_table_exists_v23663('housekeeping_tasks') && dc_table_exists_v23663('bookings')) {
            $stmt=$pdo->prepare("UPDATE housekeeping_tasks h JOIN bookings b ON b.id=h.booking_id SET h.status='cancelled', h.notes=CONCAT(COALESCE(h.notes,''),CASE WHEN COALESCE(h.notes,'')='' THEN '' ELSE '\n' END,?), h.updated_at=NOW() WHERE (b.deleted_at IS NOT NULL OR COALESCE(b.status,'') IN ('cancelled','rejected')) AND COALESCE(h.status,'') IN ('open','planned','assigned','accepted','in_progress','cleaning_done','inspection_required','ready_reported')");
            $stmt->execute(['Automatisch durch Löschcenter-Gesamtabgleich storniert: '.$reason]); $changed['Putzaufgaben']=$stmt->rowCount();
        }
        if (dc_table_exists_v23663('booking_payment_schedule') && dc_table_exists_v23663('bookings')) {
            $stmt=$pdo->prepare("UPDATE booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id SET s.status='waived', s.waived_reason=CONCAT(COALESCE(s.waived_reason,''),CASE WHEN COALESCE(s.waived_reason,'')='' THEN '' ELSE '\n' END,?), s.updated_at=NOW() WHERE (b.deleted_at IS NOT NULL OR COALESCE(b.status,'') IN ('cancelled','rejected')) AND COALESCE(s.status,'') NOT IN ('received','waived','cancelled')");
            $stmt->execute(['Automatisch durch Löschcenter-Gesamtabgleich ausgeblendet: '.$reason]); $changed['Offene Zahlungsziele']=$stmt->rowCount();
        }
        if (dc_table_exists_v23663('booking_documents') && dc_table_exists_v23663('bookings')) {
            $stmt=$pdo->prepare("UPDATE booking_documents d JOIN bookings b ON b.id=d.booking_id SET d.deleted_at=COALESCE(d.deleted_at,NOW()), d.deleted_by=?, d.delete_reason=CONCAT(COALESCE(d.delete_reason,''),CASE WHEN COALESCE(d.delete_reason,'')='' THEN '' ELSE '\n' END,?), d.status='deleted' WHERE (b.deleted_at IS NOT NULL OR COALESCE(b.status,'') IN ('cancelled','rejected')) AND (d.deleted_at IS NULL OR d.deleted_at='0000-00-00 00:00:00')");
            $stmt->execute([(int)($user['id']??0),'Automatisch durch Löschcenter-Gesamtabgleich: Buchung ist gelöscht/storniert. '.$reason]); $changed['Dokumente']=$stmt->rowCount();
        }
        $pdo->prepare('INSERT INTO logical_delete_log(entity_type,entity_id,entity_label,action,reason,old_status,new_status,created_by) VALUES(?,?,?,?,?,?,?,?)')->execute(['system',0,'Löschcenter-Gesamtabgleich','sync',$reason,'mixed','synced',(int)($user['id']??0)]);
        $pdo->commit();
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
    json_response(['ok'=>true,'message'=>'Systemweiter Löschcenter-Abgleich abgeschlossen.','changed'=>$changed,'checks'=>dc_sync_report_v23697(),'upload'=>dc_upload_report_v23697()]);
}


/* V2.3.6.64 – Datei-Papierkorb / verwaiste Dateien
 * Sicherer Datei-Papierkorb: Dateien werden niemals endgültig gelöscht, sondern nur in einen
 * internen Papierkorb verschoben und in einer Manifestdatei protokolliert.
 */
function dc_root_v23664(): string
{
    return realpath(dirname(__DIR__)) ?: dirname(__DIR__);
}

function dc_trash_dir_v23664(): string
{
    $root = dc_root_v23664();
    $dir = $root . '/storage/delete_center_trash';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}

function dc_manifest_path_v23664(): string
{
    $dir = dc_trash_dir_v23664();
    return $dir . '/manifest.json';
}

function dc_read_manifest_v23664(): array
{
    $path = dc_manifest_path_v23664();
    if (!is_file($path)) return [];
    $json = file_get_contents($path);
    $data = json_decode((string)$json, true);
    return is_array($data) ? $data : [];
}

function dc_write_manifest_v23664(array $data): void
{
    $path = dc_manifest_path_v23664();
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}

function dc_b64url_v23664(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function dc_unb64url_v23664(string $value): string
{
    $pad = strlen($value) % 4;
    if ($pad) $value .= str_repeat('=', 4 - $pad);
    return (string)base64_decode(strtr($value, '-_', '+/'), true);
}

function dc_relative_path_v23664(string $absolute): string
{
    $root = rtrim(str_replace('\\','/', dc_root_v23664()), '/') . '/';
    $absolute = str_replace('\\','/', $absolute);
    return str_starts_with($absolute, $root) ? substr($absolute, strlen($root)) : basename($absolute);
}

function dc_file_size_label_v23664(int $bytes): string
{
    if ($bytes >= 1073741824) return number_format($bytes/1073741824, 1, ',', '.') . ' GB';
    if ($bytes >= 1048576) return number_format($bytes/1048576, 1, ',', '.') . ' MB';
    if ($bytes >= 1024) return number_format($bytes/1024, 1, ',', '.') . ' KB';
    return $bytes . ' B';
}

function dc_file_allowed_v23664(string $path): bool
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ['php','phtml','phar','js','css','htaccess','sql','ini','env'], true)) return false;
    if (str_contains(str_replace('\\','/',$path), '/storage/delete_center_trash/')) return false;
    return true;
}

function dc_known_file_references_v23664(): array
{
    $refs = [];
    $tables = [
        'booking_documents' => ['file_path','pdf_path','path','filename','document_path'],
        'checkin_files' => ['file_path','path','filename'],
        'site_media' => ['file_path','path','filename','url'],
        'apartment_type_images' => ['file_path','path','filename'],
        'guest_uploads' => ['file_path','path','filename'],
    ];
    foreach ($tables as $table => $cols) {
        if (!dc_table_exists_v23663($table)) continue;
        $existing = [];
        foreach ($cols as $col) if (dc_column_exists_v23663($table, $col)) $existing[] = '`'.$col.'`';
        if (!$existing) continue;
        try {
            $stmt = db()->query('SELECT '.implode(',', $existing).' FROM `'.$table.'` LIMIT 5000');
            while ($row = $stmt->fetch()) {
                foreach ($row as $value) {
                    $value = trim((string)$value);
                    if ($value === '') continue;
                    $value = ltrim(parse_url($value, PHP_URL_PATH) ?: $value, '/');
                    $refs[$value] = true;
                    $refs[basename($value)] = true;
                }
            }
        } catch (Throwable $e) {
            // Referenzprüfung darf den Löschcenter nicht blockieren.
        }
    }
    return $refs;
}

function dc_file_candidates_v23664(string $q = ''): array
{
    $root = dc_root_v23664();
    $scanDirs = ['uploads','storage','documents','generated','exports','import','imports','tmp','cache'];
    $refs = dc_known_file_references_v23664();
    $items = [];
    $qLower = mb_strtolower($q);
    foreach ($scanDirs as $relDir) {
        $base = $root . '/' . $relDir;
        if (!is_dir($base)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            $abs = $file->getPathname();
            if (!dc_file_allowed_v23664($abs)) continue;
            $rel = dc_relative_path_v23664($abs);
            if ($qLower !== '' && !str_contains(mb_strtolower($rel), $qLower)) continue;
            $isReferenced = isset($refs[$rel]) || isset($refs[basename($rel)]);
            $ageDays = max(0, (int)floor((time() - $file->getMTime()) / 86400));
            // Sehr frische und sicher referenzierte Dateien werden nicht als Löschkandidat angeboten.
            if ($isReferenced && $ageDays < 30) continue;
            $label = $isReferenced ? 'referenziert / prüfen' : 'möglicherweise verwaist';
            $items[] = [
                'entity_type' => 'file',
                'id' => dc_b64url_v23664($rel),
                'title' => basename($rel),
                'subtitle' => $rel . ' · ' . dc_file_size_label_v23664((int)$file->getSize()) . ' · ' . $label,
                'status' => $isReferenced ? 'referenced_check' : 'orphan_candidate',
                'deleted_at' => null,
                'delete_reason' => null,
                'relation_count' => $isReferenced ? 1 : 0,
            ];
            if (count($items) >= 120) break 2;
        }
    }
    usort($items, fn($a,$b)=>strcmp((string)$a['subtitle'], (string)$b['subtitle']));
    return $items;
}

function dc_file_section_v23664(string $q = ''): array
{
    $manifest = dc_read_manifest_v23664();
    $deleted = [];
    foreach (array_reverse($manifest, true) as $id => $r) {
        if ($q !== '' && !str_contains(mb_strtolower((string)($r['original_rel'] ?? '')), mb_strtolower($q))) continue;
        $deleted[] = [
            'entity_type' => 'file',
            'id' => (string)$id,
            'title' => basename((string)($r['original_rel'] ?? 'Datei')),
            'subtitle' => (string)($r['original_rel'] ?? '') . ' · Datei-Papierkorb',
            'status' => 'file_trash',
            'deleted_at' => (string)($r['trashed_at'] ?? ''),
            'delete_reason' => (string)($r['reason'] ?? ''),
            'relation_count' => 0,
        ];
        if (count($deleted) >= 120) break;
    }
    return ['label'=>'Dateien','active'=>dc_file_candidates_v23664($q),'deleted'=>$deleted];
}

function delete_center_file_trash_v23664(): never
{
    $user = dc_user_v23663();
    ensure_delete_center_v23663();
    $d = request_data();
    dc_verify_pin_v23663((string)($d['pin'] ?? ''));
    $fileId = (string)($d['file_id'] ?? '');
    $reason = trim((string)($d['reason'] ?? ''));
    if ($reason === '') throw new ValidationException('Bitte einen Grund angeben.');
    $rel = dc_unb64url_v23664($fileId);
    $rel = ltrim(str_replace('\\','/',$rel), '/');
    if ($rel === '' || str_contains($rel, '..')) throw new ValidationException('Ungültiger Dateipfad.');
    $root = dc_root_v23664();
    $abs = realpath($root . '/' . $rel);
    if (!$abs || !str_starts_with(str_replace('\\','/',$abs), rtrim(str_replace('\\','/',$root),'/').'/')) throw new NotFoundException('Datei nicht gefunden.');
    if (!is_file($abs) || !dc_file_allowed_v23664($abs)) throw new ValidationException('Diese Datei darf über das Löschcenter nicht verschoben werden.');
    $manifest = dc_read_manifest_v23664();
    $id = date('YmdHis') . '_' . substr(sha1($rel . '|' . microtime(true)), 0, 10);
    $targetDir = dc_trash_dir_v23664() . '/' . date('Y/m/d');
    if (!is_dir($targetDir)) @mkdir($targetDir, 0775, true);
    $target = $targetDir . '/' . $id . '_' . basename($rel);
    if (!@rename($abs, $target)) throw new RuntimeException('Datei konnte nicht in den Papierkorb verschoben werden. Bitte Schreibrechte prüfen.');
    $manifest[$id] = [
        'original_rel' => $rel,
        'trash_rel' => dc_relative_path_v23664($target),
        'size' => @filesize($target) ?: 0,
        'reason' => $reason,
        'trashed_by' => (int)($user['id'] ?? 0),
        'trashed_at' => date('Y-m-d H:i:s'),
    ];
    dc_write_manifest_v23664($manifest);
    db()->prepare('INSERT INTO logical_delete_log(entity_type,entity_id,entity_label,action,reason,old_status,new_status,created_by) VALUES(?,?,?,?,?,?,?,?)')->execute(['file',0,$rel,'file_trash',$reason,'active','file_trash',(int)($user['id'] ?? 0)]);
    json_response(['ok'=>true,'message'=>'Datei wurde in den Datei-Papierkorb verschoben.']);
}

function delete_center_file_restore_v23664(): never
{
    $user = dc_user_v23663();
    ensure_delete_center_v23663();
    $d = request_data();
    dc_verify_pin_v23663((string)($d['pin'] ?? ''));
    $fileId = (string)($d['file_id'] ?? '');
    $reason = trim((string)($d['reason'] ?? 'Wiederhergestellt'));
    $manifest = dc_read_manifest_v23664();
    if (!isset($manifest[$fileId])) throw new NotFoundException('Datei nicht im Papierkorb gefunden.');
    $r = $manifest[$fileId];
    $root = dc_root_v23664();
    $trashAbs = realpath($root . '/' . (string)$r['trash_rel']);
    if (!$trashAbs || !is_file($trashAbs)) throw new NotFoundException('Papierkorb-Datei fehlt.');
    $originalRel = ltrim(str_replace('\\','/', (string)$r['original_rel']), '/');
    if ($originalRel === '' || str_contains($originalRel, '..')) throw new ValidationException('Ungültiger Originalpfad.');
    $target = $root . '/' . $originalRel;
    if (file_exists($target)) throw new ValidationException('Am ursprünglichen Speicherort existiert bereits eine Datei. Wiederherstellung wurde gestoppt.');
    $targetDir = dirname($target);
    if (!is_dir($targetDir)) @mkdir($targetDir, 0775, true);
    if (!@rename($trashAbs, $target)) throw new RuntimeException('Datei konnte nicht wiederhergestellt werden. Bitte Schreibrechte prüfen.');
    unset($manifest[$fileId]);
    dc_write_manifest_v23664($manifest);
    db()->prepare('INSERT INTO logical_delete_log(entity_type,entity_id,entity_label,action,reason,old_status,new_status,created_by) VALUES(?,?,?,?,?,?,?,?)')->execute(['file',0,$originalRel,'file_restore',$reason,'file_trash','active',(int)($user['id'] ?? 0)]);
    json_response(['ok'=>true,'message'=>'Datei wurde aus dem Papierkorb wiederhergestellt.']);
}
