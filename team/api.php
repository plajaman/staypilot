<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
require_once dirname(__DIR__) . '/src/bootstrap.php';

$user = Auth::requireLogin();
$role = (string)($user['role'] ?? 'readonly');
$previewRequest = in_array($role, ['admin','manager','reception'], true) && !empty($_GET['preview_member_id']);
if ($role !== 'housekeeping' && !$previewRequest) {
    json_response(['ok'=>false,'message'=>'Diese Rolle verwendet eine andere Startseite.','code'=>'wrong_portal','redirect'=>Auth::landingPath($user,'../')],403);
}
$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'tasks');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    verify_csrf();
}

try {
    $isPreview = $previewRequest;
    $member = null;
    if ($isPreview && !empty($_GET['preview_member_id'])) {
        $stmt = db()->prepare("SELECT hm.*,ht.name team_name
            FROM housekeeping_members hm
            LEFT JOIN housekeeping_teams ht ON ht.id=hm.team_id
            WHERE hm.id=? AND hm.active=1 LIMIT 1");
        $stmt->execute([(int)$_GET['preview_member_id']]);
        $member = $stmt->fetch() ?: null;
    } elseif (!$isPreview) {
        $member = HousekeepingWorkflow::memberForUser((int)$user['id']);
    }

    if ($action === 'notifications') {
        json_response([
            'ok' => true,
            'notifications' => HousekeepingWorkflow::unreadNotifications(
                (int)$user['id'],
                max(0, (int)($_GET['after_id'] ?? 0))
            ),
        ]);
    }
    if ($action === 'notifications_read') {
        HousekeepingWorkflow::markNotificationsRead((int)$user['id'], request_data()['ids'] ?? []);
        json_response(['ok' => true]);
    }

    if (!$member) {
        json_response(['ok' => true, 'identity' => null, 'tasks' => [], 'privacy' => []]);
    }

    switch ($action) {
        case 'tasks':
            team_tasks($member);

        case 'progress':
            if ($isPreview) throw new ForbiddenException('In der Vorschau können keine Mitarbeiteraktionen gespeichert werden.');
            team_progress($user, $member);

        case 'inspect':
            if ($isPreview) throw new ForbiddenException('In der Vorschau können keine Kontrollen gespeichert werden.');
            team_inspect($user, $member);

        case 'mark_ready':
            if ($isPreview) throw new ForbiddenException('In der Vorschau kann keine Bezugsbereitschaft gemeldet werden.');
            team_mark_ready($user, $member);

        case 'incident':
            if ($isPreview) throw new ForbiddenException('In der Vorschau können keine Meldungen erstellt werden.');
            team_incident($user, $member);

        default:
            throw new NotFoundException('Aktion nicht gefunden.');
    }
} catch (HttpException $e) {
    json_response(['ok' => false, 'message' => $e->getMessage(), 'code' => $e->errorCode()], $e->status());
} catch (RuntimeException $e) {
    json_response(['ok' => false, 'message' => $e->getMessage(), 'code' => 'validation_error'], 422);
} catch (Throwable $e) {
    $requestId = AppLogger::error($e, ['portal' => 'team', 'action' => $action], 'portal');
    json_response(['ok' => false, 'message' => 'Aktion fehlgeschlagen.', 'request_id' => $requestId], 500);
}

function team_tasks(array $member): never
{
    $from = (string)($_GET['from'] ?? date('Y-m-d'));
    $to = (string)($_GET['to'] ?? date('Y-m-d', strtotime('+7 days')));
    if (!valid_date($from) || !valid_date($to) || $from > $to) {
        throw new ValidationException('Zeitraum ungültig.');
    }

    $canControl = HousekeepingWorkflow::memberCan($member, 'inspect')
        || HousekeepingWorkflow::memberCan($member, 'mark_ready');
    $controlStatuses = "'cleaning_done','inspection_required','inspection_passed','rework_required','ready_reported','blocked'";
    $accessSql = "(h.member_id=? OR (h.member_id IS NULL AND h.team_id=?))";
    $params = [$from, $to, $from, (int)$member['id'], (int)($member['team_id'] ?? 0)];
    if ($canControl && (int)($member['team_id'] ?? 0) > 0) {
        $accessSql .= " OR (h.team_id=? AND h.status IN ({$controlStatuses}))";
        $params[] = (int)$member['team_id'];
    }

    $sql = "SELECT h.*,a.code apartment_code,a.name apartment_name,a.key_number,a.parking_number,
        a.cleaning_instructions AS cleaning_notes,b.reference,b.guest_request,b.adults,b.children,b.babies,b.pets,
        TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,
        hm.name member_name,ht.name team_name,
        (SELECT COUNT(*) FROM housekeeping_incidents i WHERE i.task_id=h.id AND i.status IN ('open','review')) incident_count
        FROM housekeeping_tasks h
        JOIN apartments a ON a.id=h.apartment_id
        LEFT JOIN bookings b ON b.id=h.booking_id
        LEFT JOIN guests g ON g.id=b.guest_id
        LEFT JOIN housekeeping_members hm ON hm.id=h.member_id
        LEFT JOIN housekeeping_teams ht ON ht.id=COALESCE(h.team_id,hm.team_id)
        WHERE ((h.task_date BETWEEN ? AND ?) OR (h.task_date < ? AND h.status NOT IN ('released','cancelled'))) AND ({$accessSql})
        ORDER BY h.task_date,COALESCE(h.due_time,'23:59:59'),FIELD(h.priority,'urgent','high','normal','low'),a.name";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();

    foreach ($tasks as &$task) {
        $task['checklist'] = json_decode((string)($task['checklist_json'] ?? ''), true) ?: [];
        $task['checklist_done'] = json_decode((string)($task['checklist_done_json'] ?? ''), true) ?: [];
        $task['guest_name'] = PrivacyService::housekeepingName($task['guest_name'] ?? '');
        $task['reference'] = PrivacyService::housekeepingReference($task['reference'] ?? '');
        $task['guest_request'] = PrivacyService::housekeepingRequest($task['guest_request'] ?? '');
    }
    unset($task);

    json_response([
        'ok' => true,
        'identity' => $member,
        'tasks' => $tasks,
        'privacy' => ['name_mode' => setting('housekeeping_guest_name_mode', 'initials')],
        'from' => $from,
        'to' => $to,
    ]);
}

function team_progress(array $user, array $member): never
{
    $data = request_data();
    $taskId = (int)($data['id'] ?? 0);
    $old = HousekeepingWorkflow::taskForUser($taskId, $user);
    $newStatus = (string)($data['status'] ?? $old['status']);
    if (!in_array($newStatus, ['accepted', 'in_progress', 'cleaning_done'], true)) {
        throw new ValidationException('Ungültiger Status.');
    }

    $currentStatus = (string)$old['status'];
    $transitions = [
        'open' => ['accepted', 'in_progress'],
        'assigned' => ['accepted', 'in_progress'],
        'accepted' => ['accepted', 'in_progress', 'cleaning_done'],
        'in_progress' => ['in_progress', 'cleaning_done'],
        'rework_required' => ['accepted', 'in_progress', 'cleaning_done'],
    ];
    if (!in_array($newStatus, $transitions[$currentStatus] ?? [], true)) {
        throw new ConflictException('Dieser Statuswechsel ist nicht möglich.');
    }

    $done = $data['checklist_done'] ?? [];
    if (!is_array($done)) $done = [];
    $done = array_values(array_unique(array_map('strval', $done)));
    $checklist = json_decode((string)($old['checklist_json'] ?? ''), true) ?: [];
    $validIndexes = array_map('strval', array_keys($checklist));
    $done = array_values(array_intersect($done, $validIndexes));
    $notes = Validator::text($data, 'completion_notes', 'Abschlussnotiz', 10000);
    if ($newStatus === 'cleaning_done' && count($done) < count($checklist) && trim($notes) === '') {
        throw new ValidationException('Bitte fehlende Checklistenpunkte in der Abschlussnotiz begründen.');
    }
    $actualMinutes = ($data['actual_minutes'] ?? '') === ''
        ? null
        : max(0, min(1440, (int)$data['actual_minutes']));

    $sets = [
        'status=?',
        'actual_minutes=?',
        'checklist_done_json=?',
        'completion_notes=?',
    ];
    $params = [
        $newStatus,
        $actualMinutes,
        json_encode($done, JSON_UNESCAPED_UNICODE),
        $notes,
    ];

    // Ein Teamauftrag wird bei Annahme eindeutig durch den handelnden Mitarbeiter übernommen.
    if (!(int)($old['member_id'] ?? 0)) {
        $sets[] = 'member_id=?';
        $params[] = (int)$member['id'];
        $sets[] = 'team_id=?';
        $params[] = (int)($member['team_id'] ?? 0) ?: null;
        $sets[] = 'assigned_to=?';
        $params[] = (string)$member['name'];
    }
    if ($newStatus === 'accepted') {
        $sets[] = 'accepted_at=COALESCE(accepted_at,NOW())';
    }
    if ($newStatus === 'in_progress') {
        $sets[] = 'accepted_at=COALESCE(accepted_at,NOW())';
        $sets[] = 'started_at=COALESCE(started_at,NOW())';
    }
    if ($currentStatus === 'rework_required') {
        $sets[] = 'inspected_at=NULL';
        $sets[] = 'inspected_by=NULL';
        $sets[] = 'inspection_result=NULL';
        $sets[] = 'ready_reported_at=NULL';
        $sets[] = 'ready_reported_by=NULL';
    }
    if ($newStatus === 'cleaning_done') {
        $sets[] = 'accepted_at=COALESCE(accepted_at,NOW())';
        $sets[] = 'started_at=COALESCE(started_at,NOW())';
        $sets[] = 'cleaning_completed_at=NOW()';
        $sets[] = 'cleaning_completed_by=?';
        $params[] = (int)$user['id'];
        $sets[] = 'completed_at=NOW()';
        $sets[] = 'inspection_result=NULL';
    }

    $params[] = $taskId;
    db()->prepare('UPDATE housekeeping_tasks SET '.implode(',', $sets).' WHERE id=?')->execute($params);

    if ($newStatus === 'cleaning_done') {
        if (!empty($old['booking_id'])) {
            db()->prepare("UPDATE bookings SET cleaning_status='cleaning_done' WHERE id=?")
                ->execute([(int)$old['booking_id']]);
        }
        HousekeepingWorkflow::notifyRoles(
            ['housekeeping_manager', 'admin', 'manager'],
            'cleaning_done',
            'Reinigung abgeschlossen',
            (string)$old['apartment_code'].' wartet auf Kontrolle.',
            'housekeeping_task',
            $taskId,
            '../team-manager/'
        );
    }

    AuditLogger::record('housekeeping_task', $taskId, 'team_progress', $old, HousekeepingWorkflow::taskDetail($taskId), 'Mitarbeiterportal aktualisierte Auftrag');
    json_response(['ok' => true, 'message' => 'Auftrag gespeichert.']);
}

function team_inspect(array $user, array $member): never
{
    if (!HousekeepingWorkflow::memberCan($member, 'inspect')) {
        throw new ForbiddenException('Für dieses Konto ist die Kontrolle nicht freigegeben.');
    }
    $data = request_data();
    $taskId = (int)($data['id'] ?? 0);
    $old = HousekeepingWorkflow::taskForUser($taskId, $user);
    $result = (string)($data['result'] ?? 'passed');
    if (!in_array($result, ['passed', 'rework'], true)) {
        throw new ValidationException('Ungültiges Kontrollergebnis.');
    }
    if (!in_array((string)$old['status'], ['cleaning_done', 'inspection_required', 'rework_required'], true)) {
        throw new ConflictException('Dieser Auftrag ist noch nicht bereit für die Kontrolle.');
    }
    $note = Validator::text($data, 'note', 'Kontrollnotiz', 10000);

    if ($result === 'passed') {
        $blocking = db()->prepare("SELECT COUNT(*) FROM housekeeping_incidents
            WHERE task_id=? AND status IN ('open','review')
            AND (severity IN ('high','critical') OR apartment_usable=0)");
        $blocking->execute([$taskId]);
        if ((int)$blocking->fetchColumn() > 0) {
            throw new ConflictException('Die Kontrolle kann wegen eines offenen schweren Problems nicht bestanden werden.');
        }
        db()->prepare("UPDATE housekeeping_tasks
            SET status='inspection_passed',inspected_at=NOW(),inspected_by=?,inspection_result='passed',release_note=?
            WHERE id=?")
            ->execute([(int)$user['id'], $note ?: null, $taskId]);
        HousekeepingWorkflow::notifyRoles(
            ['housekeeping_manager', 'admin', 'manager'],
            'inspection_passed',
            'Kontrolle bestanden',
            (string)$old['apartment_code'].' wurde kontrolliert.',
            'housekeeping_task',
            $taskId,
            '../team-manager/'
        );
        $message = 'Kontrolle bestanden.';
    } else {
        db()->prepare("UPDATE housekeeping_tasks
            SET status='rework_required',inspected_at=NOW(),inspected_by=?,inspection_result='rework',release_note=?
            WHERE id=?")
            ->execute([(int)$user['id'], $note ?: null, $taskId]);
        HousekeepingWorkflow::notifyTaskAssignee(
            $taskId,
            'Nachreinigung erforderlich',
            (string)$old['apartment_code'].' wurde zur Nachreinigung zurückgegeben.'
        );
        $message = 'Nachreinigung wurde angefordert.';
    }

    AuditLogger::record('housekeeping_task', $taskId, 'inspection', $old, HousekeepingWorkflow::taskDetail($taskId), 'Autorisierter Mitarbeiter kontrollierte Wohnung');
    json_response(['ok' => true, 'message' => $message]);
}

function team_mark_ready(array $user, array $member): never
{
    if (!HousekeepingWorkflow::memberCan($member, 'mark_ready')) {
        throw new ForbiddenException('Für dieses Konto ist „bezugsbereit melden“ nicht freigegeben.');
    }
    $data = request_data();
    $taskId = (int)($data['id'] ?? 0);
    $old = HousekeepingWorkflow::taskForUser($taskId, $user);
    if ((int)($old['release_blocked'] ?? 0) === 1) {
        throw new ConflictException('Die Freigabe ist wegen eines offenen Problems blockiert.');
    }
    if ((string)$old['status'] !== 'inspection_passed' || (string)($old['inspection_result'] ?? '') !== 'passed') {
        throw new ConflictException('Zuerst muss die Kontrolle bestanden werden.');
    }

    $note = Validator::text($data, 'note', 'Hinweis', 5000);
    db()->prepare("UPDATE housekeeping_tasks
        SET status='ready_reported',ready_reported_at=NOW(),ready_reported_by=?,release_note=?
        WHERE id=?")
        ->execute([(int)$user['id'], $note ?: null, $taskId]);
    if (!empty($old['booking_id'])) {
        db()->prepare("UPDATE bookings SET cleaning_status='ready_reported' WHERE id=?")
            ->execute([(int)$old['booking_id']]);
    }
    HousekeepingWorkflow::notifyRoles(
        ['admin', 'reception', 'manager'],
        'ready_for_release',
        'Wohnung wartet auf Freigabe',
        (string)$old['apartment_code'].' wurde als bezugsbereit gemeldet.',
        'housekeeping_task',
        $taskId,
        '../admin/'
    );
    AuditLogger::record('housekeeping_task', $taskId, 'ready_reported', $old, HousekeepingWorkflow::taskDetail($taskId), 'Autorisierter Mitarbeiter meldete bezugsbereit');
    json_response(['ok' => true, 'message' => 'Wohnung wurde als bezugsbereit gemeldet.']);
}

function team_incident(array $user, array $member): never
{
    if (!HousekeepingWorkflow::memberCan($member, 'report_incident')) {
        throw new ForbiddenException('Für dieses Konto ist die Problemmeldung nicht freigegeben.');
    }

    $taskId = (int)($_POST['task_id'] ?? 0);
    $task = HousekeepingWorkflow::taskForUser($taskId, $user);
    $category = (string)($_POST['category'] ?? 'other');
    if (!in_array($category, ['defect', 'damage', 'vandalism', 'theft', 'missing_inventory', 'heavy_soiling', 'safety', 'other'], true)) {
        $category = 'other';
    }
    $severity = (string)($_POST['severity'] ?? 'normal');
    if (!in_array($severity, ['low', 'normal', 'high', 'critical'], true)) {
        $severity = 'normal';
    }
    $description = trim((string)($_POST['description'] ?? ''));
    if ($description === '') throw new ValidationException('Bitte das Problem beschreiben.');
    $apartmentUsable = isset($_POST['apartment_usable']) ? 1 : 0;

    $photos = team_store_incident_photos($member);
    db()->beginTransaction();
    try {
        db()->prepare("INSERT INTO housekeeping_incidents(
                task_id,apartment_id,category,severity,description,apartment_usable,photos_json,reported_by
            ) VALUES(?,?,?,?,?,?,?,?)")
            ->execute([
                $taskId,
                (int)$task['apartment_id'],
                $category,
                $severity,
                $description,
                $apartmentUsable,
                json_encode($photos, JSON_UNESCAPED_SLASHES),
                (int)$user['id'],
            ]);
        $incidentId = (int)db()->lastInsertId();
        if (!$apartmentUsable || in_array($severity, ['high', 'critical'], true)) {
            db()->prepare("UPDATE housekeeping_tasks
                SET release_blocked=1,status=CASE WHEN status='released' THEN status ELSE 'blocked' END
                WHERE id=?")
                ->execute([$taskId]);
        }
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }

    HousekeepingWorkflow::notifyRoles(
        ['housekeeping_manager', 'admin', 'manager', 'reception'],
        'housekeeping_incident',
        'Problem gemeldet',
        (string)$task['apartment_code'].': '.$description,
        'housekeeping_incident',
        $incidentId,
        '../team-manager/'
    );
    AuditLogger::record('housekeeping_incident', $incidentId, 'create', null, [
        'task_id' => $taskId,
        'category' => $category,
        'severity' => $severity,
        'apartment_usable' => $apartmentUsable,
    ], 'Mitarbeiter meldete Problem');
    json_response(['ok' => true, 'message' => 'Problem wurde gemeldet.']);
}

/** @return string[] */
function team_store_incident_photos(array $member): array
{
    if (empty($_FILES['photos']['name'][0])) return [];
    if (!HousekeepingWorkflow::memberCan($member, 'upload_photos')) {
        throw new ForbiddenException('Für dieses Konto ist der Foto-Upload nicht freigegeben.');
    }
    $names = $_FILES['photos']['name'];
    if (count($names) > 5) throw new ValidationException('Pro Meldung sind höchstens fünf Fotos erlaubt.');

    $relativeDir = 'storage/uploads/housekeeping/'.date('Y/m');
    $directory = root_path($relativeDir);
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Fotoordner konnte nicht angelegt werden.');
    }

    $photos = [];
    foreach ($names as $index => $originalName) {
        $error = (int)($_FILES['photos']['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) throw new ValidationException('Ein Foto konnte nicht hochgeladen werden.');
        if ((int)($_FILES['photos']['size'][$index] ?? 0) > 5 * 1024 * 1024) {
            throw new ValidationException('Ein Foto ist größer als 5 MB.');
        }
        $temporary = (string)($_FILES['photos']['tmp_name'][$index] ?? '');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$extension) throw new ValidationException('Erlaubt sind JPG, PNG und WebP.');
        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        if (!move_uploaded_file($temporary, $directory.'/'.$filename)) {
            throw new RuntimeException('Foto konnte nicht gespeichert werden.');
        }
        $photos[] = $relativeDir.'/'.$filename;
    }
    return $photos;
}
