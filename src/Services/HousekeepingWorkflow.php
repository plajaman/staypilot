<?php
declare(strict_types=1);

final class HousekeepingWorkflow
{
    public const STATUSES = [
        'open','assigned','accepted','in_progress','cleaning_done','inspection_required','inspection_passed',
        'rework_required','ready_reported','released','blocked','cancelled'
    ];

    public static function statusLabel(string $status, string $lang = 'de'): string
    {
        $labels = [
            'de' => [
                'open'=>'Neu','assigned'=>'Zugewiesen','accepted'=>'Angenommen','in_progress'=>'In Arbeit',
                'cleaning_done'=>'Reinigung abgeschlossen','inspection_required'=>'Kontrolle erforderlich','inspection_passed'=>'Kontrolle bestanden',
                'rework_required'=>'Nachreinigung erforderlich','ready_reported'=>'Bezugsbereit gemeldet',
                'released'=>'Endgültig freigegeben','blocked'=>'Freigabe blockiert','cancelled'=>'Storniert',
                'planned'=>'Zugewiesen','done'=>'Reinigung abgeschlossen',
            ],
            'es' => [
                'open'=>'Nuevo','assigned'=>'Asignado','accepted'=>'Aceptado','in_progress'=>'En curso',
                'cleaning_done'=>'Limpieza terminada','inspection_required'=>'Revisión necesaria','inspection_passed'=>'Revisión superada',
                'rework_required'=>'Repetir limpieza','ready_reported'=>'Listo para ocupar',
                'released'=>'Liberado definitivamente','blocked'=>'Liberación bloqueada','cancelled'=>'Cancelado',
                'planned'=>'Asignado','done'=>'Limpieza terminada',
            ],
        ];
        return $labels[$lang][$status] ?? $labels['de'][$status] ?? $status;
    }

    public static function taskTypeLabel(string $type, string $lang = 'de'): string
    {
        $labels = [
            'de'=>[
                'turnover'=>'Wechselreinigung','stayover'=>'Zwischenreinigung','deep_clean'=>'Grundreinigung',
                'inspection'=>'Kontrolle','reclean'=>'Nachreinigung','special'=>'Sonderauftrag','maintenance'=>'Wartung',
            ],
            'es'=>[
                'turnover'=>'Limpieza de salida','stayover'=>'Limpieza intermedia','deep_clean'=>'Limpieza profunda',
                'inspection'=>'Revisión','reclean'=>'Repetir limpieza','special'=>'Tarea especial','maintenance'=>'Mantenimiento',
            ],
        ];
        return $labels[$lang][$type] ?? $labels['de'][$type] ?? $type;
    }

    public static function memberForUser(int $userId): ?array
    {
        $sql = "SELECT hm.*,ht.name team_name,ht.code team_code
                FROM housekeeping_members hm
                LEFT JOIN housekeeping_teams ht ON ht.id=hm.team_id
                WHERE hm.user_id=? AND hm.active=1
                LIMIT 1";
        $stmt = db()->prepare($sql);
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row) return $row;

        // Sichere Übernahme älterer, noch nicht verknüpfter Mitarbeiterdatensätze:
        // Nur aktive Housekeeping-Konten, exakte E-Mail-Übereinstimmung und genau
        // ein freier Mitarbeiterdatensatz dürfen automatisch verbunden werden.
        $userStmt = db()->prepare("SELECT id,name,email,role,active FROM users WHERE id=? LIMIT 1");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        if (!$user || !(int)$user['active'] || !in_array((string)$user['role'], ['housekeeping','housekeeping_manager'], true)) return null;
        $email = mb_strtolower(trim((string)($user['email'] ?? '')));
        if ($email === '') return null;

        $candidateStmt = db()->prepare("SELECT hm.id
            FROM housekeeping_members hm
            WHERE hm.active=1 AND hm.user_id IS NULL AND LOWER(TRIM(hm.email))=?
            ORDER BY hm.id");
        $candidateStmt->execute([$email]);
        $candidateIds = array_map('intval', $candidateStmt->fetchAll(PDO::FETCH_COLUMN));
        if (count($candidateIds) !== 1) return null;

        $memberId = $candidateIds[0];
        try {
            $link = db()->prepare("UPDATE housekeeping_members SET user_id=? WHERE id=? AND user_id IS NULL AND active=1");
            $link->execute([$userId, $memberId]);
            if ($link->rowCount() !== 1) return null;
            AuditLogger::record('housekeeping_member', $memberId, 'auto_link', null, ['user_id'=>$userId], 'Eindeutige bestehende Mitarbeiterzuordnung automatisch repariert');
        } catch (Throwable $e) {
            AppLogger::error($e, ['user_id'=>$userId,'member_id'=>$memberId], 'housekeeping-link');
            return null;
        }

        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function memberCan(array $member, string $permission): bool
    {
        $map = [
            'assign'=>'can_assign','inspect'=>'can_inspect','mark_ready'=>'can_mark_ready',
            'report_incident'=>'can_report_incident','upload_photos'=>'can_upload_photos','reassign'=>'can_reassign',
        ];
        $column = $map[$permission] ?? '';
        return $column !== '' && (int)($member[$column] ?? 0) === 1;
    }

    public static function taskForUser(int $taskId, array $user, bool $managerAccess = false): array
    {
        $task = self::taskDetail($taskId);
        $role = (string)($user['role'] ?? '');
        if (in_array($role,['admin','manager','reception'],true)) return $task;
        $member = self::memberForUser((int)$user['id']);
        if (!$member) throw new ForbiddenException('Dieses Benutzerkonto ist keinem aktiven Mitarbeiter zugeordnet.');
        if ($role === 'housekeeping_manager' || $managerAccess) return $task;

        $memberId = (int)$member['id'];
        $memberTeamId = (int)($member['team_id'] ?? 0);
        $taskMemberId = (int)($task['member_id'] ?? 0);
        $taskTeamId = (int)($task['team_id'] ?? 0);
        $owns = $taskMemberId === $memberId;
        $unclaimedTeamTask = $taskMemberId === 0 && $taskTeamId > 0 && $taskTeamId === $memberTeamId;
        $sameTeam = $memberTeamId > 0 && $taskTeamId === $memberTeamId;
        $controlStage = in_array((string)($task['status'] ?? ''),[
            'cleaning_done','inspection_required','inspection_passed','rework_required','ready_reported','blocked'
        ],true);
        $mayControlTeamTask = $sameTeam && $controlStage
            && (self::memberCan($member,'inspect') || self::memberCan($member,'mark_ready'));
        $mayCoordinateTeamTask = $sameTeam
            && (self::memberCan($member,'assign') || self::memberCan($member,'reassign'));

        if (!$owns && !$unclaimedTeamTask && !$mayControlTeamTask && !$mayCoordinateTeamTask) {
            throw new ForbiddenException('Diese Aufgabe ist Ihrem Konto oder Team nicht zugeordnet.');
        }
        return $task;
    }

    public static function taskDetail(int $taskId): array
    {
        $stmt = db()->prepare("SELECT h.*,a.code apartment_code,a.name apartment_name,a.apartment_number,a.key_number,a.parking_number,a.cleaning_instructions AS cleaning_notes,
            b.reference,b.guest_request,b.adults,b.children,b.babies,b.pets,b.departure,b.arrival,b.status booking_status,
            TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,
            hm.name member_name,hm.email member_email,hm.phone member_phone,hm.whatsapp_number member_whatsapp,
            hm.receives_email member_receives_email,hm.receives_whatsapp member_receives_whatsapp,
            ht.name team_name,ht.email team_email,ht.phone team_phone,ht.whatsapp_number team_whatsapp,
            u1.name assigned_by_name,u2.name cleaning_completed_by_name,u3.name inspected_by_name,u4.name ready_reported_by_name,u5.name released_by_name
            FROM housekeeping_tasks h
            JOIN apartments a ON a.id=h.apartment_id
            LEFT JOIN bookings b ON b.id=h.booking_id
            LEFT JOIN guests g ON g.id=b.guest_id
            LEFT JOIN housekeeping_members hm ON hm.id=h.member_id
            LEFT JOIN housekeeping_teams ht ON ht.id=COALESCE(h.team_id,hm.team_id)
            LEFT JOIN users u1 ON u1.id=h.assigned_by
            LEFT JOIN users u2 ON u2.id=h.cleaning_completed_by
            LEFT JOIN users u3 ON u3.id=h.inspected_by
            LEFT JOIN users u4 ON u4.id=h.ready_reported_by
            LEFT JOIN users u5 ON u5.id=h.released_by
            WHERE h.id=? LIMIT 1");
        $stmt->execute([$taskId]);
        $row = $stmt->fetch();
        if (!$row) throw new NotFoundException('Aufgabe nicht gefunden.');
        $row['checklist'] = json_decode((string)($row['checklist_json'] ?? ''), true) ?: [];
        $row['checklist_done'] = json_decode((string)($row['checklist_done_json'] ?? ''), true) ?: [];
        return $row;
    }

    public static function createNotification(?int $userId, string $type, string $title, string $message, ?string $entityType = null, int|string|null $entityId = null, ?string $url = null): void
    {
        if (!$userId) return;
        $stmt = db()->prepare('INSERT INTO notifications(user_id,type,title,message,entity_type,entity_id,target_url,created_at) VALUES(?,?,?,?,?,?,?,NOW())');
        $stmt->execute([$userId,$type,$title,$message,$entityType,$entityId !== null ? (string)$entityId : null,$url]);
    }

    public static function notifyRoles(array $roles, string $type, string $title, string $message, ?string $entityType = null, int|string|null $entityId = null, ?string $url = null): void
    {
        if (!$roles) return;
        $placeholders = implode(',', array_fill(0,count($roles),'?'));
        $stmt = db()->prepare("SELECT id FROM users WHERE active=1 AND role IN ({$placeholders})");
        $stmt->execute(array_values($roles));
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId) self::createNotification((int)$userId,$type,$title,$message,$entityType,$entityId,$url);
    }

    public static function notifyTaskAssignee(int $taskId, string $title, string $message): void
    {
        $task = self::taskDetail($taskId);
        $userIds = [];
        if (!empty($task['member_id'])) {
            $stmt = db()->prepare('SELECT user_id FROM housekeeping_members WHERE id=? AND active=1');
            $stmt->execute([(int)$task['member_id']]);
            $uid = (int)($stmt->fetchColumn() ?: 0);
            if ($uid) $userIds[] = $uid;
        } elseif (!empty($task['team_id'])) {
            $stmt = db()->prepare('SELECT user_id FROM housekeeping_members WHERE team_id=? AND active=1 AND user_id IS NOT NULL');
            $stmt->execute([(int)$task['team_id']]);
            $userIds = array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
        }
        foreach (array_unique($userIds) as $uid) self::createNotification($uid,'housekeeping_task',$title,$message,'housekeeping_task',$taskId,'../team/');
    }

    public static function unreadNotifications(int $userId, int $afterId = 0): array
    {
        $stmt = db()->prepare('SELECT id,type,title,message,entity_type,entity_id,target_url,created_at FROM notifications WHERE user_id=? AND id>? AND read_at IS NULL ORDER BY id ASC LIMIT 50');
        $stmt->execute([$userId,$afterId]);
        return $stmt->fetchAll();
    }

    public static function markNotificationsRead(int $userId, array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval',$ids)));
        if (!$ids) return;
        $ph = implode(',',array_fill(0,count($ids),'?'));
        db()->prepare("UPDATE notifications SET read_at=NOW() WHERE user_id=? AND id IN ({$ph})")->execute([$userId,...$ids]);
    }

    public static function ensureGuestAccess(int $bookingId): array
    {
        $bookingStmt = db()->prepare('SELECT arrival,departure FROM bookings WHERE id=? LIMIT 1');
        $bookingStmt->execute([$bookingId]);
        $booking = $bookingStmt->fetch();
        if (!$booking) throw new NotFoundException('Buchung nicht gefunden.');
        $validFrom = (new DateTimeImmutable((string)$booking['arrival']))->modify('-14 days')->format('Y-m-d 00:00:00');
        $validUntil = (new DateTimeImmutable((string)$booking['departure']))->modify('+2 days')->format('Y-m-d 23:59:59');
        $stmt = db()->prepare('SELECT * FROM guest_portal_access WHERE booking_id=? LIMIT 1');
        $stmt->execute([$bookingId]);
        $row = $stmt->fetch();
        if ($row) {
            $token = Crypto::decrypt((string)($row['token_encrypted'] ?? ''));
            if ($token !== '') {
                db()->prepare('UPDATE guest_portal_access SET active=1,valid_from=?,valid_until=? WHERE id=?')->execute([$validFrom,$validUntil,$row['id']]);
                $stmt->execute([$bookingId]);
                return ['row'=>$stmt->fetch(),'token'=>$token];
            }
        }
        $token = rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
        $hash = hash('sha256',$token);
        if ($row) {
            db()->prepare('UPDATE guest_portal_access SET token_hash=?,token_encrypted=?,active=1,valid_from=?,valid_until=? WHERE id=?')
                ->execute([$hash,Crypto::encrypt($token),$validFrom,$validUntil,$row['id']]);
        } else {
            db()->prepare('INSERT INTO guest_portal_access(booking_id,token_hash,token_encrypted,active,valid_from,valid_until) VALUES(?,?,?,?,?,?)')
                ->execute([$bookingId,$hash,Crypto::encrypt($token),1,$validFrom,$validUntil]);
        }
        $stmt->execute([$bookingId]);
        return ['row'=>$stmt->fetch(),'token'=>$token];
    }

    public static function applicationUrl(string $relative = ''): string
    {
        $base = rtrim((string)setting('base_url',''),'/');
        if ($base === '') {
            $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $script = str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME'] ?? ''));
            // StayPilot kann im Webroot oder – wie in den vollständigen ZIPs –
            // unter /stay-pilot/ liegen. Von internen Unterordnern wird immer
            // bis zum tatsächlichen App-Stamm zurückgegangen.
            $path = preg_replace('#/(admin|team|team-manager|gast|api|print|cron|tools)(?:/.*)?$#','',$script) ?: '';
            if ($path === $script) $path = rtrim(str_replace('\\','/',dirname($script)),'/');
            $base = ($host ? ($https?'https':'http').'://'.$host : '').rtrim($path,'/');
        }
        return rtrim($base,'/').'/'.ltrim($relative,'/');
    }

    public static function publicGuestPortalUrl(): string
    {
        return self::applicationUrl('gast/');
    }

    /** Legacy-Kompatibilität für bereits versandte persönliche Links. */
    public static function guestPortalUrl(string $token): string
    {
        return self::publicGuestPortalUrl().'?token='.rawurlencode($token);
    }

    public static function releaseTask(int $taskId, array $user, string $note = ''): array
    {
        if (!in_array((string)($user['role'] ?? ''),['admin','reception'],true)) throw new ForbiddenException('Die endgültige Freigabe ist Admin oder Rezeption vorbehalten.');
        $task = self::taskDetail($taskId);
        if ((string)$task['status'] !== 'ready_reported') throw new ConflictException('Die Wohnung wurde noch nicht als bezugsbereit gemeldet.');
        $stmt = db()->prepare("SELECT COUNT(*) FROM housekeeping_incidents WHERE task_id=? AND status IN ('open','review') AND (severity IN ('high','critical') OR apartment_usable=0)");
        $stmt->execute([$taskId]);
        if ((int)$stmt->fetchColumn() > 0) throw new ConflictException('Die Freigabe ist wegen eines offenen schweren Problems blockiert.');
        db()->beginTransaction();
        try {
            db()->prepare("UPDATE housekeeping_tasks SET status='released',released_at=NOW(),released_by=?,release_note=?,release_blocked=0 WHERE id=?")
                ->execute([(int)$user['id'],$note ?: null,$taskId]);
            if (!empty($task['booking_id'])) {
                db()->prepare("UPDATE bookings SET cleaning_status='released' WHERE id=?")->execute([(int)$task['booking_id']]);
                // V2.0.10 benötigt für neue Freigaben keinen Gasttoken mehr. Bereits vorhandene
                // persönliche V2.0.9-Links werden weiterhin auf den Freigabestatus aktualisiert.
                $legacy = db()->prepare('SELECT * FROM guest_portal_access WHERE booking_id=? LIMIT 1');
                $legacy->execute([(int)$task['booking_id']]);
                $access = $legacy->fetch() ?: null;
                if ($access) {
                    db()->prepare('UPDATE guest_portal_access SET released_at=NOW(),released_by=?,active=1 WHERE booking_id=?')
                        ->execute([(int)$user['id'],(int)$task['booking_id']]);
                }
            } else {
                $access = null;
            }
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        self::notifyRoles(['admin','reception'],'apartment_released','Wohnung freigegeben',($task['apartment_code'] ?? '').' wurde endgültig freigegeben.','housekeeping_task',$taskId,'../admin/');
        AuditLogger::record('housekeeping_task',$taskId,'final_release',$task,self::taskDetail($taskId),'Wohnung endgültig freigegeben');
        return ['task'=>self::taskDetail($taskId),'guest_access'=>$access,'url'=>self::publicGuestPortalUrl()];
    }

    public static function publicGuestStatus(string $token): array
    {
        if ($token === '' || strlen($token) < 30) throw new NotFoundException('Gastzugang nicht gefunden.');
        $hash = hash('sha256',$token);
        $stmt = db()->prepare("SELECT ga.*,b.arrival,b.departure,b.reference,b.cleaning_status,a.code apartment_code,a.apartment_number,a.name apartment_name,
            h.id house_id,h.name house_name,h.default_checkin_time,h.phone reception_phone,h.email reception_email
            FROM guest_portal_access ga JOIN bookings b ON b.id=ga.booking_id
            LEFT JOIN apartments a ON a.id=b.apartment_id LEFT JOIN houses h ON h.id=a.house_id
            WHERE ga.token_hash=? AND ga.active=1 AND (ga.valid_from IS NULL OR ga.valid_from<=NOW()) AND (ga.valid_until IS NULL OR ga.valid_until>=NOW()) LIMIT 1");
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) throw new NotFoundException('Gastzugang nicht gefunden oder nicht mehr gültig.');
        db()->prepare('UPDATE guest_portal_access SET last_viewed_at=NOW() WHERE id=?')->execute([$row['id']]);
        $released = !empty($row['released_at']) && (string)$row['cleaning_status'] === 'released';
        return [
            'released'=>$released,
            'booking_id'=>(int)$row['booking_id'],'house_id'=>(int)($row['house_id']??0),
            'apartment_code'=>$released?(string)($row['apartment_code'] ?: $row['apartment_number']):'',
            'house_name'=>$released?(string)($row['house_name'] ?? ''):'',
            'arrival'=>$row['arrival'],'departure'=>$row['departure'],
            'checkin_time'=>$row['default_checkin_time'] ?? null,
            'reception_phone'=>$row['reception_phone'] ?? null,
            'reception_email'=>$row['reception_email'] ?? null,
            'message_de'=>(string)($row['message_de'] ?? ''),
            'message_es'=>(string)($row['message_es'] ?? ''),
            'message_en'=>(string)($row['message_en'] ?? ''),
            'updated_at'=>$row['updated_at'] ?? null,
        ];
    }

    public static function upsertDepartureTask(int $bookingId): void
    {
        $stmt = db()->prepare("SELECT b.id,b.apartment_id,b.departure,b.status,b.reference,at.standard_cleaning_minutes,h.cleaning_team
            FROM bookings b
            LEFT JOIN apartments a ON a.id=b.apartment_id
            LEFT JOIN apartment_types at ON at.id=a.apartment_type_id
            LEFT JOIN houses h ON h.id=a.house_id
            WHERE b.id=? LIMIT 1");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        if (!$booking || !$booking['apartment_id'] || in_array((string)$booking['status'],['cancelled','rejected'],true)) {
            // Automatische Aufträge nicht löschen: noch nicht begonnene Aufträge nachvollziehbar stornieren.
            db()->prepare("UPDATE housekeeping_tasks
                SET status='cancelled',notes=CONCAT(COALESCE(notes,''),CASE WHEN COALESCE(notes,'')='' THEN '' ELSE '\n' END,'Automatisch storniert: Buchung wurde storniert, abgelehnt oder ist nicht mehr zugeordnet.')
                WHERE booking_id=? AND task_type='turnover' AND origin_type='booking_departure' AND status IN ('open','assigned')")
                ->execute([$bookingId]);
            return;
        }

        $checklist = json_encode([
            'Bad reinigen','Küche prüfen','Bettwäsche wechseln','Handtücher wechseln',
            'Böden reinigen','Müll entsorgen','Inventar prüfen','Endkontrolle',
        ], JSON_UNESCAPED_UNICODE);
        $suggestion = trim((string)($booking['cleaning_team'] ?? ''));
        $autoNote = 'Automatisch aus Buchung '.(string)$booking['reference'].' erzeugt.';
        if ($suggestion !== '') $autoNote .= ' Vorgeschlagenes Putzteam: '.$suggestion.'.';

        // Alle automatisch erzeugten Aufträge der Buchung prüfen. Frühere Versionen konnten
        // bei parallelen Aufrufen mehrere offene Einträge erzeugen. Der fachlich am weitesten
        // fortgeschrittene Auftrag bleibt erhalten; ausschließlich noch unberührte Dubletten
        // werden nachvollziehbar storniert – niemals bereits bearbeitete Aufträge gelöscht.
        $stmt = db()->prepare("SELECT id,status FROM housekeeping_tasks
            WHERE booking_id=? AND task_type='turnover' AND origin_type='booking_departure'
            ORDER BY FIELD(status,'released','ready_reported','inspection_passed','cleaning_done','inspection_required','rework_required','in_progress','accepted','assigned','open','blocked','cancelled'),id");
        $stmt->execute([$bookingId]);
        $generatedTasks = $stmt->fetchAll();
        $existing = $generatedTasks[0] ?? null;
        if ($existing && count($generatedTasks) > 1) {
            $cancelDuplicate = db()->prepare("UPDATE housekeeping_tasks
                SET status='cancelled',notes=CONCAT(COALESCE(notes,''),CASE WHEN COALESCE(notes,'')='' THEN '' ELSE '\n' END,?)
                WHERE id=? AND status IN ('open','assigned','cancelled')");
            foreach (array_slice($generatedTasks, 1) as $duplicate) {
                if (!in_array((string)$duplicate['status'], ['open','assigned','cancelled'], true)) continue;
                $cancelDuplicate->execute(['Als doppelt erkannter automatischer Abreiseauftrag storniert; Originalauftrag #'.(int)$existing['id'].' bleibt erhalten.', (int)$duplicate['id']]);
            }
        }
        if ($existing) {
            // Nach Annahme/Beginn wird ein Auftrag durch spätere Buchungsänderungen nicht still zurückgesetzt.
            if (in_array((string)$existing['status'],['open','assigned'],true)) {
                db()->prepare("UPDATE housekeeping_tasks SET apartment_id=?,task_date=?,estimated_minutes=?,checklist_json=COALESCE(NULLIF(checklist_json,''),?) WHERE id=?")
                    ->execute([
                        (int)$booking['apartment_id'],
                        (string)$booking['departure'],
                        max(15,(int)($booking['standard_cleaning_minutes'] ?? 60)),
                        $checklist,
                        (int)$existing['id'],
                    ]);
            } elseif ((string)$existing['status'] === 'cancelled') {
                // Wird die Buchung wieder aktiviert, wird derselbe Auftrag ohne Datenverlust neu geöffnet.
                db()->prepare("UPDATE housekeeping_tasks SET apartment_id=?,task_date=?,status='open',team_id=NULL,member_id=NULL,assigned_to=NULL,assigned_by=NULL,estimated_minutes=?,checklist_json=COALESCE(NULLIF(checklist_json,''),?),release_blocked=0 WHERE id=?")
                    ->execute([
                        (int)$booking['apartment_id'],
                        (string)$booking['departure'],
                        max(15,(int)($booking['standard_cleaning_minutes'] ?? 60)),
                        $checklist,
                        (int)$existing['id'],
                    ]);
                self::notifyRoles(
                    ['housekeeping_manager','admin','manager','reception'],
                    'cleaning_task_reopened',
                    'Reinigungsauftrag erneut geöffnet',
                    'Der Reinigungsauftrag für Buchung '.(string)$booking['reference'].' wurde erneut geöffnet.',
                    'housekeeping_task',
                    (int)$existing['id'],
                    '../team-manager/'
                );
            }
            return;
        }

        db()->prepare("INSERT INTO housekeeping_tasks(
                apartment_id,booking_id,task_date,task_type,status,origin_type,priority,
                estimated_minutes,linen_change,towel_change,checklist_json,notes
            ) VALUES(?,?,?,'turnover','open','booking_departure','normal',?,1,1,?,?)")
            ->execute([
                (int)$booking['apartment_id'],
                $bookingId,
                (string)$booking['departure'],
                max(15,(int)($booking['standard_cleaning_minutes'] ?? 60)),
                $checklist,
                $autoNote,
            ]);
        $taskId = (int)db()->lastInsertId();
        self::notifyRoles(
            ['housekeeping_manager','admin','manager','reception'],
            'new_cleaning_task',
            'Neuer Reinigungsauftrag',
            'Für Buchung '.(string)$booking['reference'].' wurde ein Reinigungsauftrag erzeugt.',
            'housekeeping_task',
            $taskId,
            '../team-manager/'
        );
    }

}
