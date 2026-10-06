<?php
declare(strict_types=1);

/**
 * V2.0.10: vereinfachte Mitarbeiteranlage, Dashboard-Workflow und öffentliche Gästeanzeige.
 */

function save_staff_account_v210(): never
{
    $d = request_data();
    $memberId = (int)($d['id'] ?? 0);
    $oldMember = $memberId ? fetch_housekeeping_row_v208('housekeeping_members', $memberId) : null;
    if ($memberId && !$oldMember) throw new NotFoundException('Mitarbeiter nicht gefunden.');

    $role = (string)($d['role'] ?? 'housekeeping');
    if (!in_array($role, ['housekeeping', 'housekeeping_manager'], true)) {
        throw new ValidationException('Für Mitarbeiter sind nur Reinigungskraft oder Gouvernante zulässig.');
    }

    $name = Validator::text($d, 'name', 'Name', 160, true);
    $loginEmail = mb_strtolower(Validator::email($d, 'login_email', 'Login-E-Mail', true));
    $password = (string)($d['password'] ?? '');
    $active = normalize_bool($d['active'] ?? 0);
    $teamId = (int)($d['team_id'] ?? 0) ?: null;
    if ($teamId && !fetch_housekeeping_row_v208('housekeeping_teams', $teamId)) {
        throw new ValidationException('Das gewählte Team existiert nicht.');
    }

    $existingUserId = (int)($oldMember['user_id'] ?? 0);
    $oldUser = null;
    if ($existingUserId) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$existingUserId]);
        $oldUser = $stmt->fetch() ?: null;
    }
    if (!$oldUser && $password === '') {
        throw new ValidationException('Für einen neuen Zugang ist ein Passwort mit mindestens 8 Zeichen erforderlich.');
    }
    if ($password !== '' && mb_strlen($password) < 8) {
        throw new ValidationException('Das Passwort muss mindestens 8 Zeichen lang sein.');
    }

    $duplicate = db()->prepare('SELECT id FROM users WHERE LOWER(email)=LOWER(?) AND id<>? LIMIT 1');
    $duplicate->execute([$loginEmail, $existingUserId]);
    if ($duplicate->fetchColumn()) throw new ConflictException('Diese Login-E-Mail wird bereits verwendet.');

    $language = in_array((string)($d['preferred_language'] ?? 'de'), ['de', 'es'], true)
        ? (string)$d['preferred_language'] : 'de';

    $permissions = [
        'can_assign' => normalize_bool($d['can_assign'] ?? ($role === 'housekeeping_manager' ? 1 : 0)),
        'can_reassign' => normalize_bool($d['can_reassign'] ?? ($role === 'housekeeping_manager' ? 1 : 0)),
        'can_inspect' => normalize_bool($d['can_inspect'] ?? ($role === 'housekeeping_manager' ? 1 : 0)),
        'can_mark_ready' => normalize_bool($d['can_mark_ready'] ?? ($role === 'housekeeping_manager' ? 1 : 0)),
        'can_report_incident' => normalize_bool($d['can_report_incident'] ?? 1),
        'can_upload_photos' => normalize_bool($d['can_upload_photos'] ?? 1),
    ];

    db()->beginTransaction();
    try {
        if ($oldUser) {
            $params = [$name, $loginEmail, $role, $active];
            $sql = 'UPDATE users SET name=?,email=?,role=?,active=?';
            if ($password !== '') {
                $sql .= ',password_hash=?,failed_login_count=0,locked_until=NULL';
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= ' WHERE id=?';
            $params[] = $existingUserId;
            db()->prepare($sql)->execute($params);
            $userId = $existingUserId;
        } else {
            db()->prepare('INSERT INTO users(name,email,password_hash,role,active) VALUES(?,?,?,?,?)')
                ->execute([$name, $loginEmail, password_hash($password, PASSWORD_DEFAULT), $role, $active]);
            $userId = (int)db()->lastInsertId();
        }

        // Ein Benutzerkonto darf genau einem Mitarbeiter zugeordnet sein.
        db()->prepare('UPDATE housekeeping_members SET user_id=NULL WHERE user_id=? AND id<>?')
            ->execute([$userId, $memberId]);

        $memberValues = [
            $teamId, $userId, $name,
            Validator::text($d, 'phone', 'Telefon', 80),
            $loginEmail,
            Validator::text($d, 'whatsapp_number', 'WhatsApp-Nummer', 80),
            normalize_bool($d['receives_whatsapp'] ?? 0),
            normalize_bool($d['receives_email'] ?? 0),
            $permissions['can_assign'], $permissions['can_reassign'], $permissions['can_inspect'],
            $permissions['can_mark_ready'], $permissions['can_report_incident'], $permissions['can_upload_photos'],
            $language, $active, Validator::text($d, 'notes', 'Notizen', 10000),
        ];

        if ($memberId) {
            db()->prepare('UPDATE housekeeping_members SET team_id=?,user_id=?,name=?,phone=?,email=?,whatsapp_number=?,receives_whatsapp=?,receives_email=?,can_assign=?,can_reassign=?,can_inspect=?,can_mark_ready=?,can_report_incident=?,can_upload_photos=?,preferred_language=?,active=?,notes=? WHERE id=?')
                ->execute([...$memberValues, $memberId]);
        } else {
            db()->prepare('INSERT INTO housekeeping_members(team_id,user_id,name,phone,email,whatsapp_number,receives_whatsapp,receives_email,can_assign,can_reassign,can_inspect,can_mark_ready,can_report_incident,can_upload_photos,preferred_language,active,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute($memberValues);
            $memberId = (int)db()->lastInsertId();
        }
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        if ($e instanceof PDOException && (string)$e->getCode() === '23000') {
            throw new ConflictException('E-Mail oder Benutzerzuordnung ist bereits vorhanden.');
        }
        throw $e;
    }

    $newMember = fetch_housekeeping_row_v208('housekeeping_members', $memberId);
    $newUserStmt = db()->prepare('SELECT id,name,email,role,active FROM users WHERE id=? LIMIT 1');
    $newUserStmt->execute([(int)$newMember['user_id']]);
    $newUser = $newUserStmt->fetch();
    AuditLogger::record('housekeeping_member', $memberId, $oldMember ? 'update' : 'create', $oldMember, $newMember, 'Mitarbeiter und Zugang gemeinsam gespeichert');
    AuditLogger::record('user', (int)$newMember['user_id'], $oldUser ? 'update' : 'create', $oldUser, $newUser, 'Mitarbeiterzugang gemeinsam gespeichert');

    json_response([
        'ok' => true,
        'message' => $oldMember ? 'Mitarbeiter und Zugang wurden aktualisiert.' : 'Mitarbeiter und Zugang wurden gemeinsam angelegt.',
        'id' => $memberId,
        'user_id' => (int)$newMember['user_id'],
    ]);
}


function deactivate_staff_v210(): never
{
    $id = (int)(request_data()['id'] ?? 0);
    $member = fetch_housekeeping_row_v208('housekeeping_members', $id);
    if (!$member) throw new NotFoundException('Mitarbeiter nicht gefunden.');
    $userId = (int)($member['user_id'] ?? 0);
    if ($userId && $userId === (int)(Auth::user()['id'] ?? 0)) {
        throw new ConflictException('Das aktuell angemeldete eigene Konto kann hier nicht deaktiviert werden.');
    }
    $activeTasks = db()->prepare("SELECT COUNT(*) FROM housekeeping_tasks WHERE member_id=? AND status NOT IN ('released','cancelled')");
    $activeTasks->execute([$id]);
    if ((int)$activeTasks->fetchColumn() > 0) {
        throw new ConflictException('Der Mitarbeiter besitzt noch aktive Aufträge. Bitte diese zuerst umverteilen.');
    }
    $oldUser = null;
    if ($userId) {
        $stmt = db()->prepare('SELECT id,name,email,role,active FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $oldUser = $stmt->fetch() ?: null;
    }
    db()->beginTransaction();
    try {
        db()->prepare('UPDATE housekeeping_members SET active=0 WHERE id=?')->execute([$id]);
        if ($userId) db()->prepare('UPDATE users SET active=0 WHERE id=?')->execute([$userId]);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
    $newMember = fetch_housekeeping_row_v208('housekeeping_members', $id);
    AuditLogger::record('housekeeping_member', $id, 'deactivate', $member, $newMember, 'Mitarbeiter und Zugang deaktiviert');
    if ($userId) AuditLogger::record('user', $userId, 'deactivate', $oldUser, ['active'=>0], 'Mitarbeiterzugang deaktiviert');
    json_response(['ok'=>true,'message'=>'Mitarbeiter und Zugang wurden deaktiviert.']);
}

function housekeeping_dashboard_v210(): never
{
    $today = date('Y-m-d', strtotime('-2 days'));
    $to = date('Y-m-d', strtotime('+7 days'));
    $stmt = db()->prepare("SELECT h.id,h.status,h.task_date,h.due_time,h.ready_reported_at,h.released_at,a.code apartment_code,a.name apartment_name,
        hm.name member_name,ht.name team_name,
        (SELECT COUNT(*) FROM housekeeping_incidents i WHERE i.task_id=h.id AND i.status IN ('open','review')) incident_count
        FROM housekeeping_tasks h
        JOIN apartments a ON a.id=h.apartment_id
        LEFT JOIN bookings b ON b.id=h.booking_id
        LEFT JOIN housekeeping_members hm ON hm.id=h.member_id
        LEFT JOIN housekeeping_teams ht ON ht.id=COALESCE(h.team_id,hm.team_id)
        WHERE h.task_date BETWEEN ? AND ?
          AND (h.booking_id IS NULL OR (b.id IS NOT NULL AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND COALESCE(b.status,'') NOT IN ('cancelled','rejected')) OR h.status='cancelled')
          AND h.status IN ('cleaning_done','inspection_required','inspection_passed','ready_reported','released','blocked')
        ORDER BY FIELD(h.status,'ready_reported','blocked','cleaning_done','inspection_required','inspection_passed','released'),
                 COALESCE(h.ready_reported_at,h.released_at,h.updated_at) DESC
        LIMIT 40");
    $stmt->execute([$today, $to]);
    $rows = $stmt->fetchAll();

    $ready = [];
    $control = [];
    $released = [];
    $blocked = [];
    foreach ($rows as $row) {
        switch ((string)$row['status']) {
            case 'ready_reported': $ready[] = $row; break;
            case 'released': $released[] = $row; break;
            case 'blocked': $blocked[] = $row; break;
            default: $control[] = $row;
        }
    }

    json_response([
        'ok' => true,
        'ready' => $ready,
        'control' => $control,
        'released' => $released,
        'blocked' => $blocked,
        'public_guest_url' => HousekeepingWorkflow::publicGuestPortalUrl(),
        'team_url' => HousekeepingWorkflow::applicationUrl('team/'),
        'manager_url' => HousekeepingWorkflow::applicationUrl('team-manager/'),
        'admin_url' => HousekeepingWorkflow::applicationUrl('admin/'),
    ]);
}

function public_guest_settings_v210(): never
{
    json_response(['ok' => true, 'settings' => [
        'guest_public_display_hours' => (string)setting('guest_public_display_hours', 12),
        'guest_public_title_de' => (string)setting('guest_public_title_de', 'Bezugsbereite Wohnungen'),
        'guest_public_title_es' => (string)setting('guest_public_title_es', 'Apartamentos listos'),
        'guest_public_title_en' => (string)setting('guest_public_title_en', 'Apartments ready'),
        'guest_public_empty_de' => (string)setting('guest_public_empty_de', 'Zurzeit wurde noch keine Wohnung für die Schlüsselabholung freigegeben.'),
        'guest_public_empty_es' => (string)setting('guest_public_empty_es', 'Actualmente no hay ningún apartamento liberado para recoger la llave.'),
        'guest_public_empty_en' => (string)setting('guest_public_empty_en', 'No apartment has currently been released for key collection.'),
        'guest_public_show_house' => (string)setting('guest_public_show_house', 1),
    ]]);
}

function save_public_guest_settings_v210(): never
{
    $d = request_data();
    $hours = max(1, min(48, (int)($d['guest_public_display_hours'] ?? 12)));
    $values = [
        'guest_public_display_hours' => (string)$hours,
        'guest_public_title_de' => Validator::text($d, 'guest_public_title_de', 'Titel Deutsch', 190, true),
        'guest_public_title_es' => Validator::text($d, 'guest_public_title_es', 'Titel Spanisch', 190, true),
        'guest_public_title_en' => Validator::text($d, 'guest_public_title_en', 'Titel Englisch', 190, true),
        'guest_public_empty_de' => Validator::text($d, 'guest_public_empty_de', 'Leertext Deutsch', 1000, true),
        'guest_public_empty_es' => Validator::text($d, 'guest_public_empty_es', 'Leertext Spanisch', 1000, true),
        'guest_public_empty_en' => Validator::text($d, 'guest_public_empty_en', 'Leertext Englisch', 1000, true),
        'guest_public_show_house' => (string)normalize_bool($d['guest_public_show_house'] ?? 0),
    ];
    $stmt = db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    foreach ($values as $key => $value) $stmt->execute([$key, $value]);
    AuditLogger::record('settings', null, 'update', null, $values, 'Öffentliche Gästeanzeige aktualisiert');
    json_response(['ok' => true, 'message' => 'Öffentliche Gästeanzeige wurde gespeichert.']);
}

function housekeeping_final_release_v210(): never
{
    $d = request_data();
    $result = HousekeepingWorkflow::releaseTask(
        (int)($d['id'] ?? 0),
        Auth::user(),
        Validator::text($d, 'note', 'Freigabehinweis', 5000)
    );
    json_response([
        'ok' => true,
        'message' => 'Wohnung wurde endgültig freigegeben und erscheint automatisch auf der Gäste-Webseite.',
        'guest_url' => HousekeepingWorkflow::publicGuestPortalUrl(),
        'task' => $result['task'],
    ]);
}

function guest_portal_link_v210(): never
{
    json_response(['ok' => true, 'url' => HousekeepingWorkflow::publicGuestPortalUrl()]);
}
