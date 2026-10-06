<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/admin/api-v208.php';

$user = Auth::requireRole(['housekeeping_manager', 'admin', 'manager', 'reception']);
$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'dashboard');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    verify_csrf();
}

try {
    $identity = HousekeepingWorkflow::memberForUser((int)$user['id']);

    switch ($action) {
        case 'notifications':
            json_response([
                'ok' => true,
                'notifications' => HousekeepingWorkflow::unreadNotifications(
                    (int)$user['id'],
                    max(0, (int)($_GET['after_id'] ?? 0))
                ),
            ]);

        case 'notifications_read':
            HousekeepingWorkflow::markNotificationsRead((int)$user['id'], request_data()['ids'] ?? []);
            json_response(['ok' => true]);

        case 'dashboard':
            manager_dashboard($identity, $user);

        case 'assign':
            manager_assign_task($user);

        case 'inspect':
            manager_inspect_task($user);

        case 'mark_ready':
            manager_mark_ready($user);

        case 'create_task':
            manager_create_task($user);

        case 'incident_review':
            manager_review_incident($user);

        case 'whatsapp_preview':
            whatsapp_task_preview_v208();

        case 'whatsapp_open':
            whatsapp_task_open_v208();

        case 'email_task':
            send_task_email_v208();

        default:
            throw new NotFoundException('Aktion nicht gefunden.');
    }
} catch (HttpException $e) {
    json_response(['ok' => false, 'message' => $e->getMessage(), 'code' => $e->errorCode()], $e->status());
} catch (RuntimeException $e) {
    json_response(['ok' => false, 'message' => $e->getMessage(), 'code' => 'validation_error'], 422);
} catch (Throwable $e) {
    $requestId = AppLogger::error($e, ['portal' => 'manager', 'action' => $action], 'portal');
    json_response(['ok' => false, 'message' => 'Aktion fehlgeschlagen.', 'request_id' => $requestId], 500);
}

function manager_dashboard(?array $identity, array $user): never
{
    $from = (string)($_GET['from'] ?? date('Y-m-d'));
    $to = (string)($_GET['to'] ?? date('Y-m-d', strtotime('+7 days')));
    if (!valid_date($from) || !valid_date($to) || $from > $to) {
        throw new ValidationException('Zeitraum ungültig.');
    }

    $stmt = db()->prepare("SELECT h.*,a.code apartment_code,a.name apartment_name,b.reference,
        hm.name member_name,hm.email member_email,hm.whatsapp_number member_whatsapp,hm.receives_email member_receives_email,hm.receives_whatsapp member_receives_whatsapp,
        ht.name team_name,ht.email team_email,ht.whatsapp_number team_whatsapp,
        (SELECT COUNT(*) FROM housekeeping_incidents i WHERE i.task_id=h.id AND i.status IN ('open','review')) incident_count,
        (SELECT COUNT(*) FROM housekeeping_incidents i WHERE i.task_id=h.id AND i.status IN ('open','review')
            AND (i.severity IN ('high','critical') OR i.apartment_usable=0)) blocking_incident_count
        FROM housekeeping_tasks h
        JOIN apartments a ON a.id=h.apartment_id
        LEFT JOIN bookings b ON b.id=h.booking_id
        LEFT JOIN housekeeping_members hm ON hm.id=h.member_id
        LEFT JOIN housekeeping_teams ht ON ht.id=COALESCE(h.team_id,hm.team_id)
        WHERE h.task_date BETWEEN ? AND ? AND h.status<>'cancelled'
        ORDER BY h.task_date,COALESCE(h.due_time,'23:59:59'),FIELD(h.priority,'urgent','high','normal','low'),a.name");
    $stmt->execute([$from, $to]);
    $tasks = $stmt->fetchAll();

    $teams = db()->query("SELECT id,name,code,color,active FROM housekeeping_teams WHERE active=1 ORDER BY name")->fetchAll();
    $members = db()->query("SELECT m.id,m.team_id,m.user_id,m.name,m.can_assign,m.can_inspect,m.can_mark_ready,m.active,t.name team_name
        FROM housekeeping_members m
        LEFT JOIN housekeeping_teams t ON t.id=m.team_id
        WHERE m.active=1
        ORDER BY COALESCE(t.name,''),m.name")->fetchAll();
    $apartments = db()->query("SELECT id,code,name FROM apartments WHERE status='active' ORDER BY sort_order,name")->fetchAll();

    $incidentStmt = db()->prepare("SELECT i.*,a.code apartment_code,a.name apartment_name,h.task_date,u.name reported_by_name
        FROM housekeeping_incidents i
        JOIN apartments a ON a.id=i.apartment_id
        JOIN housekeeping_tasks h ON h.id=i.task_id
        LEFT JOIN users u ON u.id=i.reported_by
        WHERE h.task_date BETWEEN ? AND ? AND i.status IN ('open','review')
        ORDER BY FIELD(i.severity,'critical','high','normal','low'),i.id DESC");
    $incidentStmt->execute([$from, $to]);

    json_response([
        'ok' => true,
        'identity' => $identity,
        'user' => $user,
        'from' => $from,
        'to' => $to,
        'tasks' => $tasks,
        'teams' => $teams,
        'members' => $members,
        'apartments' => $apartments,
        'incidents' => $incidentStmt->fetchAll(),
    ]);
}

function manager_assign_task(array $user): never
{
    $data = request_data();
    $taskId = (int)($data['id'] ?? 0);
    $old = HousekeepingWorkflow::taskDetail($taskId);
    $currentStatus = (string)$old['status'];
    if (!in_array($currentStatus, ['open', 'assigned', 'accepted', 'in_progress', 'rework_required'], true)) {
        throw new ConflictException('Dieser Auftrag kann in seinem aktuellen Status nicht neu zugewiesen werden.');
    }

    [$teamId, $memberId, $assignedName] = manager_resolve_assignee($data);
    if (!$teamId && !$memberId) {
        throw new ValidationException('Bitte Team oder Mitarbeiter auswählen.');
    }

    $dueTime = manager_time_or_null((string)($data['due_time'] ?? ''));
    $priority = manager_priority((string)($data['priority'] ?? 'normal'));
    $newStatus = $currentStatus === 'rework_required' ? 'rework_required' : 'assigned';

    db()->prepare("UPDATE housekeeping_tasks
        SET team_id=?,member_id=?,assigned_to=?,assigned_by=?,due_time=?,priority=?,status=?
        WHERE id=?")
        ->execute([$teamId, $memberId, $assignedName, (int)$user['id'], $dueTime, $priority, $newStatus, $taskId]);

    HousekeepingWorkflow::notifyTaskAssignee(
        $taskId,
        $currentStatus === 'rework_required' ? 'Nachreinigung zugewiesen' : 'Neuer Reinigungsauftrag',
        (string)$old['apartment_code'].' wurde Ihnen zugewiesen.'
    );
    AuditLogger::record('housekeeping_task', $taskId, 'assign', $old, HousekeepingWorkflow::taskDetail($taskId), 'Gouvernante wies Auftrag zu');
    json_response(['ok' => true, 'message' => 'Auftrag wurde zugewiesen.']);
}

function manager_inspect_task(array $user): never
{
    $data = request_data();
    $taskId = (int)($data['id'] ?? 0);
    $old = HousekeepingWorkflow::taskDetail($taskId);
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
            ['housekeeping_manager', 'admin', 'manager', 'reception'],
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

    AuditLogger::record('housekeeping_task', $taskId, 'inspection', $old, HousekeepingWorkflow::taskDetail($taskId), 'Wohnung kontrolliert: '.$result);
    json_response(['ok' => true, 'message' => $message]);
}

function manager_mark_ready(array $user): never
{
    $data = request_data();
    $taskId = (int)($data['id'] ?? 0);
    $old = HousekeepingWorkflow::taskDetail($taskId);
    if ((int)($old['release_blocked'] ?? 0) === 1) {
        throw new ConflictException('Die Freigabe ist wegen eines offenen Problems blockiert.');
    }
    if ((string)$old['status'] !== 'inspection_passed' || (string)($old['inspection_result'] ?? '') !== 'passed') {
        throw new ConflictException('Zuerst muss die Kontrolle bestanden werden.');
    }

    $blocking = db()->prepare("SELECT COUNT(*) FROM housekeeping_incidents
        WHERE task_id=? AND status IN ('open','review')
        AND (severity IN ('high','critical') OR apartment_usable=0)");
    $blocking->execute([$taskId]);
    if ((int)$blocking->fetchColumn() > 0) {
        throw new ConflictException('Die Wohnung ist wegen eines offenen Problems nicht bezugsbereit.');
    }

    $note = Validator::text($data, 'note', 'Hinweis', 5000);
    db()->prepare("UPDATE housekeeping_tasks
        SET status='ready_reported',ready_reported_at=NOW(),ready_reported_by=?,release_note=?
        WHERE id=?")
        ->execute([(int)$user['id'], $note ?: null, $taskId]);
    if (!empty($old['booking_id'])) {
        db()->prepare("UPDATE bookings SET cleaning_status='ready_reported' WHERE id=?")->execute([(int)$old['booking_id']]);
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
    AuditLogger::record('housekeeping_task', $taskId, 'ready_reported', $old, HousekeepingWorkflow::taskDetail($taskId), 'Bezugsbereit gemeldet');
    json_response(['ok' => true, 'message' => 'Wohnung wurde als bezugsbereit gemeldet.']);
}

function manager_create_task(array $user): never
{
    $data = request_data();
    $apartmentId = (int)($data['apartment_id'] ?? 0);
    $taskDate = (string)($data['task_date'] ?? '');
    if (!$apartmentId || !valid_date($taskDate)) {
        throw new ValidationException('Apartment und Datum sind erforderlich.');
    }
    $apartmentStmt = db()->prepare("SELECT id FROM apartments WHERE id=? AND status='active' LIMIT 1");
    $apartmentStmt->execute([$apartmentId]);
    if (!$apartmentStmt->fetchColumn()) {
        throw new ValidationException('Das gewählte Apartment ist nicht aktiv.');
    }

    $taskType = (string)($data['task_type'] ?? 'special');
    if (!in_array($taskType, ['turnover', 'stayover', 'deep_clean', 'inspection', 'reclean', 'special', 'maintenance'], true)) {
        throw new ValidationException('Ungültige Auftragsart.');
    }
    [$teamId, $memberId, $assignedName] = manager_resolve_assignee($data, true);
    $priority = manager_priority((string)($data['priority'] ?? 'normal'));
    $dueTime = manager_time_or_null((string)($data['due_time'] ?? ''));
    $estimatedMinutes = max(0, min(1440, (int)($data['estimated_minutes'] ?? 60)));
    $checklist = array_values(array_filter(array_map(
        static fn(string $line): string => trim($line),
        preg_split('/\r?\n/', (string)($data['checklist'] ?? '')) ?: []
    )));
    $status = ($teamId || $memberId) ? 'assigned' : 'open';

    db()->prepare("INSERT INTO housekeeping_tasks(
            apartment_id,task_date,task_type,status,origin_type,due_time,assigned_to,team_id,member_id,assigned_by,
            priority,estimated_minutes,linen_change,towel_change,checklist_json,notes
        ) VALUES(?,?,?,?, 'manual',?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $apartmentId,
            $taskDate,
            $taskType,
            $status,
            $dueTime,
            $assignedName,
            $teamId,
            $memberId,
            (int)$user['id'],
            $priority,
            $estimatedMinutes,
            normalize_bool($data['linen_change'] ?? 1),
            normalize_bool($data['towel_change'] ?? 1),
            json_encode($checklist, JSON_UNESCAPED_UNICODE),
            Validator::text($data, 'notes', 'Notiz', 10000),
        ]);
    $taskId = (int)db()->lastInsertId();
    if ($teamId || $memberId) {
        HousekeepingWorkflow::notifyTaskAssignee($taskId, 'Neuer manueller Auftrag', 'Ein neuer Auftrag wurde Ihnen zugewiesen.');
    }
    AuditLogger::record('housekeeping_task', $taskId, 'create', null, HousekeepingWorkflow::taskDetail($taskId), 'Manueller Auftrag durch Gouvernante');
    json_response(['ok' => true, 'message' => 'Manueller Auftrag wurde angelegt.', 'id' => $taskId]);
}

function manager_review_incident(array $user): never
{
    $data = request_data();
    $incidentId = (int)($data['id'] ?? 0);
    $status = (string)($data['status'] ?? 'review');
    if (!in_array($status, ['review', 'resolved', 'dismissed'], true)) {
        throw new ValidationException('Status ungültig.');
    }
    $stmt = db()->prepare('SELECT * FROM housekeeping_incidents WHERE id=? LIMIT 1');
    $stmt->execute([$incidentId]);
    $old = $stmt->fetch();
    if (!$old) throw new NotFoundException('Meldung nicht gefunden.');

    $note = Validator::text($data, 'resolution_note', 'Hinweis', 10000);
    $resolvedAtSql = in_array($status, ['resolved', 'dismissed'], true) ? 'NOW()' : 'NULL';
    db()->prepare("UPDATE housekeeping_incidents
        SET status=?,reviewed_by=?,reviewed_at=NOW(),resolution_note=?,resolved_at={$resolvedAtSql}
        WHERE id=?")
        ->execute([$status, (int)$user['id'], $note, $incidentId]);

    if (in_array($status, ['resolved', 'dismissed'], true)) {
        $blocking = db()->prepare("SELECT COUNT(*) FROM housekeeping_incidents
            WHERE task_id=? AND status IN ('open','review')
            AND (severity IN ('high','critical') OR apartment_usable=0)");
        $blocking->execute([(int)$old['task_id']]);
        if ((int)$blocking->fetchColumn() === 0) {
            db()->prepare("UPDATE housekeeping_tasks
                SET release_blocked=0,status=CASE WHEN status='blocked' THEN 'cleaning_done' ELSE status END
                WHERE id=?")
                ->execute([(int)$old['task_id']]);
        }
    }

    AuditLogger::record('housekeeping_incident', $incidentId, 'review', $old, ['status' => $status, 'note' => $note], 'Gouvernante bearbeitete Meldung');
    json_response(['ok' => true, 'message' => 'Meldung wurde aktualisiert.']);
}

/** @return array{0:?int,1:?int,2:?string} */
function manager_resolve_assignee(array $data, bool $allowEmpty = false): array
{
    $teamId = (int)($data['team_id'] ?? 0) ?: null;
    $memberId = (int)($data['member_id'] ?? 0) ?: null;
    $assignedName = null;

    if ($memberId) {
        $stmt = db()->prepare('SELECT id,team_id,name FROM housekeeping_members WHERE id=? AND active=1 LIMIT 1');
        $stmt->execute([$memberId]);
        $member = $stmt->fetch();
        if (!$member) throw new ValidationException('Mitarbeiter nicht gefunden.');
        $assignedName = (string)$member['name'];
        $memberTeamId = (int)($member['team_id'] ?? 0) ?: null;
        if ($teamId && $memberTeamId && $teamId !== $memberTeamId) {
            throw new ValidationException('Der Mitarbeiter gehört nicht zum gewählten Team.');
        }
        $teamId = $memberTeamId ?: $teamId;
    } elseif ($teamId) {
        $stmt = db()->prepare('SELECT name FROM housekeeping_teams WHERE id=? AND active=1 LIMIT 1');
        $stmt->execute([$teamId]);
        $assignedName = $stmt->fetchColumn() ?: null;
        if (!$assignedName) throw new ValidationException('Team nicht gefunden.');
    } elseif (!$allowEmpty) {
        throw new ValidationException('Bitte Team oder Mitarbeiter auswählen.');
    }

    return [$teamId, $memberId, $assignedName];
}

function manager_priority(string $priority): string
{
    return in_array($priority, ['low', 'normal', 'high', 'urgent'], true) ? $priority : 'normal';
}

function manager_time_or_null(string $value): ?string
{
    $value = trim($value);
    if ($value === '') return null;
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
        throw new ValidationException('Die Uhrzeit ist ungültig.');
    }
    return $value;
}
