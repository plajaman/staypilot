<?php
declare(strict_types=1);

function ensure_internal_tasks_v23656(): void
{
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS internal_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        category VARCHAR(60) NOT NULL DEFAULT 'reception',
        priority VARCHAR(20) NOT NULL DEFAULT 'normal',
        status VARCHAR(30) NOT NULL DEFAULT 'open',
        due_date DATE NULL,
        due_time TIME NULL,
        booking_id INT NULL,
        guest_id INT NULL,
        apartment_id INT NULL,
        assigned_user_id INT NULL,
        created_by INT NULL,
        completed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_internal_tasks_due (due_date, due_time),
        INDEX idx_internal_tasks_status (status),
        INDEX idx_internal_tasks_booking (booking_id),
        INDEX idx_internal_tasks_guest (guest_id),
        INDEX idx_internal_tasks_apartment (apartment_id)
    ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}



function ensure_task_event_states_v23661(): void
{
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_event_states (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_key VARCHAR(190) NOT NULL,
        source VARCHAR(80) NOT NULL,
        source_id VARCHAR(80) NOT NULL,
        due_date DATE NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'open',
        title VARCHAR(255) NULL,
        category VARCHAR(60) NULL,
        updated_by INT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_task_event_key (event_key),
        INDEX idx_task_event_states_status (status),
        INDEX idx_task_event_states_due (due_date)
    ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function task_event_key_v23661(string $source, string $id, ?string $dueDate): string
{
    return substr($source . ':' . $id . ':' . (string)($dueDate ?: ''), 0, 190);
}

function apply_task_event_states_v23661(array $items): array
{
    ensure_task_event_states_v23661();
    $keys = [];
    foreach ($items as $item) {
        $source = (string)($item['source'] ?? '');
        if ($source === '' || $source === 'manual') continue;
        $keys[] = task_event_key_v23661($source, (string)($item['id'] ?? ''), $item['due_date'] ?? null);
    }
    $keys = array_values(array_unique(array_filter($keys)));
    if (!$keys) return $items;
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = db()->prepare("SELECT event_key,status FROM task_event_states WHERE event_key IN ($placeholders)");
    $stmt->execute($keys);
    $states = [];
    foreach ($stmt->fetchAll() as $row) {
        $states[(string)$row['event_key']] = (string)$row['status'];
    }
    foreach ($items as &$item) {
        $source = (string)($item['source'] ?? '');
        if ($source === '' || $source === 'manual') continue;
        $key = task_event_key_v23661($source, (string)($item['id'] ?? ''), $item['due_date'] ?? null);
        $item['event_key'] = $key;
        if (isset($states[$key])) $item['status'] = $states[$key];
    }
    unset($item);
    return $items;
}


function task_center_active_booking_sql_v236128(string $alias='b', string $guestAlias='g'): string
{
    return "($alias.deleted_at IS NULL OR $alias.deleted_at='0000-00-00 00:00:00') AND LOWER(COALESCE($alias.status,'')) NOT IN ('cancelled','canceled','storniert','deleted','gelöscht','rejected','abgelehnt','declined','void') AND ($guestAlias.id IS NULL OR $guestAlias.deleted_at IS NULL OR $guestAlias.deleted_at='0000-00-00 00:00:00')";
}

function task_center_today_v23656(): never
{
    if (function_exists('ensure_delete_center_v23663')) ensure_delete_center_v23663();
    ensure_internal_tasks_v23656();
    $today = (string)($_GET['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime($today . ' +1 day'));
    $weekEnd = date('Y-m-d', strtotime($today . ' +7 day'));
    $pdo = db();

    $manual = task_center_manual_tasks_v23656($today, $weekEnd);
    $auto = [];
    $auto = array_merge($auto, task_center_booking_events_v23656($today, $weekEnd));
    $auto = array_merge($auto, task_center_payments_v23656($today));
    $auto = array_merge($auto, task_center_checkins_v23656($today, $weekEnd));
    $auto = array_merge($auto, task_center_housekeeping_v23656($today, $weekEnd));
    $auto = array_merge($auto, task_center_meals_v23656($today));
    $auto = array_merge($auto, task_center_communication_errors_v23656($today));
    $auto = array_merge($auto, task_center_vermietung_conflicts_v1());

    $items = apply_task_event_states_v23661(array_merge($manual, $auto));
    usort($items, function(array $a, array $b): int {
        $statusA = ($a['status'] ?? '') === 'done' ? 1 : 0;
        $statusB = ($b['status'] ?? '') === 'done' ? 1 : 0;
        if ($statusA !== $statusB) return $statusA <=> $statusB;
        $date = strcmp((string)($a['due_date'] ?? '9999-12-31'), (string)($b['due_date'] ?? '9999-12-31'));
        if ($date !== 0) return $date;
        $prio = ['urgent'=>0,'high'=>1,'normal'=>2,'low'=>3];
        return ($prio[$a['priority'] ?? 'normal'] ?? 2) <=> ($prio[$b['priority'] ?? 'normal'] ?? 2);
    });

    $stats = [
        'today'=>0,'tomorrow'=>0,'overdue'=>0,'open'=>0,'done'=>0,
        'arrivals'=>0,'departures'=>0,'payments'=>0,'checkin'=>0,'housekeeping'=>0,'meals'=>0,'manual'=>0
    ];
    foreach ($items as $item) {
        $d = (string)($item['due_date'] ?? '');
        if (($item['status'] ?? '') === 'done') $stats['done']++; else $stats['open']++;
        if ($d === $today) $stats['today']++;
        if ($d === $tomorrow) $stats['tomorrow']++;
        if ($d !== '' && $d < $today && ($item['status'] ?? '') !== 'done') $stats['overdue']++;
        $cat = (string)($item['category'] ?? 'manual');
        if (isset($stats[$cat])) $stats[$cat]++;
        if (($item['source'] ?? '') === 'manual') $stats['manual']++;
    }

    $calendar = task_center_calendar_v23656(date('Y-m-d', strtotime($today . ' -14 day')), date('Y-m-d', strtotime($today . ' +45 day')));
    $meta = [
        'bookings'=>task_center_booking_options_v23656(),
        'guests'=>task_center_guest_options_v23656(),
        'apartments'=>task_center_apartment_options_v23656(),
        'users'=>task_center_user_options_v23656()
    ];
    json_response(['ok'=>true,'date'=>$today,'week_end'=>$weekEnd,'stats'=>$stats,'items'=>$items,'calendar'=>$calendar,'meta'=>$meta]);
}

function task_center_manual_tasks_v23656(string $today, string $weekEnd): array
{
    $active = task_center_active_booking_sql_v236128('b','g');
    $stmt = db()->prepare("SELECT t.*, b.reference booking_reference, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest_name, g.email guest_email, a.code apartment_code, a.name apartment_name, u.name assigned_user_name
        FROM internal_tasks t
        LEFT JOIN bookings b ON b.id=t.booking_id
        LEFT JOIN guests g ON g.id=COALESCE(t.guest_id,b.guest_id)
        LEFT JOIN apartments a ON a.id=COALESCE(t.apartment_id,b.apartment_id)
        LEFT JOIN users u ON u.id=t.assigned_user_id
        WHERE t.status <> 'archived'
          AND (t.deleted_at IS NULL OR t.deleted_at='0000-00-00 00:00:00')
          AND (t.booking_id IS NULL OR (b.id IS NOT NULL AND $active))
          AND (t.guest_id IS NULL OR (g.id IS NOT NULL AND (g.deleted_at IS NULL OR g.deleted_at='0000-00-00 00:00:00')))
          AND (t.due_date IS NULL OR t.due_date <= ? OR t.status IN ('open','in_progress'))
        ORDER BY COALESCE(t.due_date,'9999-12-31'), COALESCE(t.due_time,'23:59:00'), FIELD(t.priority,'urgent','high','normal','low'), t.id DESC
        LIMIT 120");
    $stmt->execute([$weekEnd]);
    $rows = $stmt->fetchAll();
    return array_map(function(array $r): array {
        return task_center_item_v23656('manual', (string)$r['id'], $r['title'], $r['description'] ?? '', $r['due_date'] ?? null, $r['due_time'] ?? null, $r['category'] ?: 'manual', $r['priority'] ?: 'normal', $r['status'] ?: 'open', $r);
    }, $rows);
}

function task_center_booking_events_v23656(string $today, string $weekEnd): array
{
    $items = [];
    if (!v235_table_exists('bookings')) return $items;
    $active = task_center_active_booking_sql_v236128('b','g');
    $select = "SELECT b.id,b.reference,b.arrival,b.departure,b.status,b.payment_status,b.total_price,b.paid_amount,
        CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest_name,g.email guest_email,
        a.code apartment_code,a.name apartment_name,t.name apartment_type_name
        FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id LEFT JOIN apartments a ON a.id=b.apartment_id LEFT JOIN apartment_types t ON t.id=b.apartment_type_id
        WHERE $active AND %s BETWEEN ? AND ? ORDER BY %s LIMIT 100";
    foreach ([['arrival','Anreise','arrivals','high'],['departure','Abreise','departures','normal']] as $cfg) {
        [$field,$label,$cat,$prio] = $cfg;
        $stmt = db()->prepare(sprintf($select, 'b.'.$field, 'b.'.$field));
        $stmt->execute([$today, $weekEnd]);
        foreach ($stmt->fetchAll() as $r) {
            $title = $label . ': ' . trim((string)($r['guest_name'] ?: 'Gast'));
            $items[] = task_center_item_v23656('booking_'.$field, (string)$r['id'], $title, ($r['reference'] ?? '') . ' · ' . ($r['apartment_code'] ?: $r['apartment_type_name'] ?: 'nicht zugeordnet'), $r[$field], null, $cat, $prio, 'open', $r);
        }
    }
    return $items;
}

function task_center_payments_v23656(string $today): array
{
    $items = [];
    $active = task_center_active_booking_sql_v236128('b','g');
    if (v235_table_exists('booking_payment_schedule')) {
        $stmt = db()->prepare("SELECT s.*, b.id booking_id,b.reference,b.status booking_status, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest_name,g.email guest_email
            FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id LEFT JOIN guests g ON g.id=b.guest_id
            WHERE $active AND COALESCE(b.accounting_mode,'internal')='internal' AND s.status NOT IN ('paid','received','waived','cancelled','canceled','void') AND s.due_date <= DATE_ADD(?, INTERVAL 7 DAY)
            ORDER BY s.due_date, s.id LIMIT 80");
        $stmt->execute([$today]);
        foreach ($stmt->fetchAll() as $r) {
            $open = max(0, (float)($r['amount'] ?? 0) - (float)($r['paid_amount'] ?? 0));
            if ($open <= 0) continue;
            $prio = ((string)$r['due_date'] < $today) ? 'urgent' : (((string)$r['due_date'] === $today) ? 'high' : 'normal');
            $items[] = task_center_item_v23656('payment', (string)$r['id'], 'Zahlung offen: ' . trim((string)($r['guest_name'] ?: 'Gast')), ($r['label'] ?? 'Zahlungsziel') . ' · offen ' . number_format($open,2,',','.') . ' €', $r['due_date'], null, 'payments', $prio, 'open', $r + ['booking_id'=>$r['booking_id']]);
        }
    } elseif (v235_table_exists('bookings')) {
        $stmt = db()->query("SELECT b.id,b.reference,b.arrival,b.departure,b.total_price,b.paid_amount,b.payment_status, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest_name,g.email guest_email FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id WHERE $active AND COALESCE(b.accounting_mode,'internal')='internal' AND b.payment_status IN ('open','partial') ORDER BY b.arrival LIMIT 80");
        foreach ($stmt->fetchAll() as $r) {
            $items[] = task_center_item_v23656('payment', (string)$r['id'], 'Zahlung prüfen: ' . trim((string)($r['guest_name'] ?: 'Gast')), 'Buchung ' . ($r['reference'] ?? ''), $r['arrival'] ?? $today, null, 'payments', 'normal', 'open', $r);
        }
    }
    return $items;
}

function task_center_checkins_v23656(string $today, string $weekEnd): array
{
    $items=[];
    if (!v235_table_exists('booking_checkins') || !v235_table_exists('bookings')) return $items;
    $active = task_center_active_booking_sql_v236128('b','g');
    $stmt = db()->prepare("SELECT b.id,b.reference,b.arrival,b.departure,bc.status checkin_status, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest_name,g.email guest_email
        FROM bookings b LEFT JOIN booking_checkins bc ON bc.booking_id=b.id LEFT JOIN guests g ON g.id=b.guest_id
        WHERE $active AND b.arrival BETWEEN ? AND ? AND (bc.status IS NULL OR LOWER(COALESCE(bc.status,'')) NOT IN ('complete','approved','closed','cancelled','canceled','storniert'))
        ORDER BY b.arrival LIMIT 80");
    $stmt->execute([$today,$weekEnd]);
    foreach($stmt->fetchAll() as $r){
        $prio=((string)$r['arrival'] <= date('Y-m-d',strtotime($today.' +1 day')))?'high':'normal';
        $items[]=task_center_item_v23656('checkin',(string)$r['id'],'Online-Check-in fehlt: '.trim((string)($r['guest_name']?:'Gast')),'Anreise '.$r['arrival'].' · '.($r['reference']??''),$r['arrival'],null,'checkin',$prio,'open',$r+['booking_id'=>$r['id']]);
    }
    return $items;
}

function task_center_housekeeping_v23656(string $today, string $weekEnd): array
{
    $items=[];
    if (!v235_table_exists('housekeeping_tasks')) return $items;
    $active = task_center_active_booking_sql_v236128('b','g');
    $stmt=db()->prepare("SELECT h.*,a.code apartment_code,a.name apartment_name,b.reference booking_reference, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest_name
        FROM housekeeping_tasks h LEFT JOIN apartments a ON a.id=h.apartment_id LEFT JOIN bookings b ON b.id=h.booking_id LEFT JOIN guests g ON g.id=b.guest_id
        WHERE h.status NOT IN ('released','cancelled','done') AND (h.booking_id IS NULL OR (b.id IS NOT NULL AND $active)) AND h.task_date BETWEEN ? AND ? ORDER BY h.task_date, h.priority DESC LIMIT 100");
    $stmt->execute([$today,$weekEnd]);
    foreach($stmt->fetchAll() as $r){
        $title='Housekeeping: '.($r['apartment_code']?:$r['apartment_name']?:'Wohnung');
        $items[]=task_center_item_v23656('housekeeping',(string)$r['id'],$title,($r['task_type']??'Auftrag').' · '.$r['status'].' · '.($r['guest_name']??''),$r['task_date'],null,'housekeeping',($r['priority']??'normal')==='high'?'high':'normal','open',$r+['booking_id'=>$r['booking_id']??null]);
    }
    return $items;
}

function task_center_meals_v23656(string $today): array
{
    $items=[];
    if (!v235_table_exists('bookings')) return $items;
    $active = task_center_active_booking_sql_v236128('b','g');
    $stmt=db()->prepare("SELECT b.id,b.reference,b.breakfast,b.half_board,b.breakfast_start_date,b.breakfast_end_date,b.half_board_start_date,b.half_board_end_date,b.adults,b.children, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest_name,a.code apartment_code
        FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id LEFT JOIN apartments a ON a.id=b.apartment_id
        WHERE $active AND ((b.breakfast=1 AND COALESCE(b.breakfast_start_date,b.arrival)<=? AND COALESCE(b.breakfast_end_date,b.departure)>?) OR (b.half_board=1 AND COALESCE(b.half_board_start_date,b.arrival)<=? AND COALESCE(b.half_board_end_date,b.departure)>?))
        ORDER BY b.reference LIMIT 80");
    $stmt->execute([$today,$today,$today,$today]);
    foreach($stmt->fetchAll() as $r){
        $parts=[]; if((int)$r['breakfast'])$parts[]='Frühstück'; if((int)$r['half_board'])$parts[]='HP';
        $items[]=task_center_item_v23656('meals',(string)$r['id'],'Bistro heute: '.trim((string)($r['guest_name']?:'Gast')),implode(' + ',$parts).' · '.((int)$r['adults']+(int)$r['children']).' Personen · '.($r['apartment_code']??''),$today,null,'meals','normal','open',$r+['booking_id'=>$r['id']]);
    }
    return $items;
}

function task_center_communication_errors_v23656(string $today): array
{
    $items=[];
    if (!v235_table_exists('communication_log')) return $items;
    $stmt=db()->prepare("SELECT * FROM communication_log WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND status IN ('failed','error') AND created_at >= DATE_SUB(?, INTERVAL 14 DAY) ORDER BY created_at DESC LIMIT 30");
    $stmt->execute([$today.' 00:00:00']);
    foreach($stmt->fetchAll() as $r){
        $items[]=task_center_item_v23656('communication',(string)$r['id'],'E-Mail/Kommunikation prüfen',($r['recipient_address']??'').' · '.($r['subject']??$r['detail']??''),substr((string)$r['created_at'],0,10),null,'communication','high','open',$r+['booking_id'=>$r['entity_type']==='booking'?($r['entity_id']??null):null]);
    }
    return $items;
}

function task_center_calendar_v23656(string $from, string $to): array
{
    $events=[];
    foreach(task_center_booking_events_v23656($from,$to) as $it){ $events[]=$it; }
    foreach(task_center_manual_tasks_v23656($from,$to) as $it){ if(($it['due_date']??'')>=$from && ($it['due_date']??'')<=$to) $events[]=$it; }
    foreach(task_center_payments_v23656($from) as $it){ if(($it['due_date']??'')>=$from && ($it['due_date']??'')<=$to) $events[]=$it; }
    return array_slice(apply_task_event_states_v23661($events),0,200);
}

function task_center_item_v23656(string $source, string $id, string $title, string $description, ?string $dueDate, ?string $dueTime, string $category, string $priority, string $status, array $raw=[]): array
{
    return [
        'id'=>$id,'source'=>$source,'title'=>$title,'description'=>$description,'due_date'=>$dueDate,'due_time'=>$dueTime,
        'category'=>$category,'priority'=>$priority,'status'=>$status,
        'booking_id'=>$raw['booking_id'] ?? $raw['id'] ?? null,
        'booking_reference'=>$raw['booking_reference'] ?? $raw['reference'] ?? null,
        'guest_name'=>trim((string)($raw['guest_name'] ?? '')) ?: null,
        'guest_email'=>$raw['guest_email'] ?? null,
        'apartment_code'=>$raw['apartment_code'] ?? null,
        'apartment_name'=>$raw['apartment_name'] ?? null,
        'assigned_user_name'=>$raw['assigned_user_name'] ?? null,
        'event_key'=>task_event_key_v23661($source, $id, $dueDate)
    ];
}

function task_center_booking_options_v23656(): array
{
    if (!v235_table_exists('bookings')) return [];
    $active = task_center_active_booking_sql_v236128('b','g');
    return db()->query("SELECT b.id,b.reference,CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest_name,b.arrival,b.departure FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id WHERE $active ORDER BY b.arrival DESC LIMIT 150")->fetchAll();
}
function task_center_guest_options_v23656(): array
{
    if (!v235_table_exists('guests')) return [];
    return db()->query("SELECT id,CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) name,email FROM guests WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') ORDER BY last_name,first_name LIMIT 150")->fetchAll();
}
function task_center_apartment_options_v23656(): array
{
    if (!v235_table_exists('apartments')) return [];
    return db()->query("SELECT id,code,name FROM apartments ORDER BY code,name LIMIT 200")->fetchAll();
}
function task_center_user_options_v23656(): array
{
    if (!v235_table_exists('users')) return [];
    return db()->query("SELECT id,name,email,role FROM users WHERE active=1 ORDER BY name LIMIT 100")->fetchAll();
}

function save_internal_task_v23656(): never
{
    ensure_internal_tasks_v23656();
    $d=request_data();
    $id=(int)($d['id']??0);
    $title=trim((string)($d['title']??''));
    if($title==='') throw new ValidationException('Bitte eine Aufgabe eintragen.');
    $due=trim((string)($d['due_date']??''));
    if($due!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$due)) throw new ValidationException('Fälligkeitsdatum ist ungültig.');
    $time=trim((string)($d['due_time']??''));
    if($time!=='' && preg_match('/^\d{2}:\d{2}$/',$time)) $time.=':00';
    $category=in_array(($d['category']??''),['reception','payments','checkin','housekeeping','meals','communication','other'],true)?$d['category']:'reception';
    $priority=in_array(($d['priority']??''),['low','normal','high','urgent'],true)?$d['priority']:'normal';
    $status=in_array(($d['status']??''),['open','in_progress','done','postponed','waiting'],true)?$d['status']:'open';
    $current=Auth::user();
    $payload=[
        $title,trim((string)($d['description']??''))?:null,$category,$priority,$status,$due?:null,$time?:null,
        (int)($d['booking_id']??0)?:null,(int)($d['guest_id']??0)?:null,(int)($d['apartment_id']??0)?:null,(int)($d['assigned_user_id']??0)?:null
    ];
    if($id>0){
        $old=db()->prepare('SELECT * FROM internal_tasks WHERE id=?');$old->execute([$id]);if(!$old->fetch()) throw new NotFoundException('Aufgabe nicht gefunden.');
        db()->prepare('UPDATE internal_tasks SET title=?,description=?,category=?,priority=?,status=?,due_date=?,due_time=?,booking_id=?,guest_id=?,apartment_id=?,assigned_user_id=?,completed_at=IF(?="done" AND completed_at IS NULL,NOW(),IF(?<>"done",NULL,completed_at)) WHERE id=?')
            ->execute(array_merge($payload,[$status,$status,$id]));
    }else{
        db()->prepare('INSERT INTO internal_tasks(title,description,category,priority,status,due_date,due_time,booking_id,guest_id,apartment_id,assigned_user_id,created_by,completed_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,IF(?="done",NOW(),NULL))')
            ->execute(array_merge($payload,[$current['id']??null,$status]));
        $id=(int)db()->lastInsertId();
    }
    json_response(['ok'=>true,'id'=>$id,'message'=>'Aufgabe gespeichert.']);
}

function task_status_v23656(): never
{
    ensure_internal_tasks_v23656();
    $d=request_data();$id=(int)($d['id']??0);$status=(string)($d['status']??'done');
    if($id<=0) throw new ValidationException('Aufgabe fehlt.');
    if(!in_array($status,['open','in_progress','done','postponed','waiting','archived'],true)) throw new ValidationException('Status ungültig.');
    db()->prepare('UPDATE internal_tasks SET status=?, completed_at=IF(?="done",NOW(),NULL) WHERE id=?')->execute([$status,$status,$id]);
    json_response(['ok'=>true,'message'=>'Aufgabe aktualisiert.']);
}


function task_event_status_v23661(): never
{
    ensure_task_event_states_v23661();
    $d = request_data();
    $source = trim((string)($d['source'] ?? ''));
    $id = trim((string)($d['id'] ?? ''));
    $due = trim((string)($d['due_date'] ?? ''));
    $status = trim((string)($d['status'] ?? 'done'));
    if ($source === '' || $id === '') throw new ValidationException('Kalendereintrag fehlt.');
    if ($source === 'manual') {
        $_POST['id'] = $id;
    }
    if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) throw new ValidationException('Datum des Kalendereintrags ist ungültig.');
    if (!in_array($status, ['open','done','waiting'], true)) throw new ValidationException('Status ungültig.');
    $key = task_event_key_v23661($source, $id, $due ?: null);
    $user = Auth::user();
    db()->prepare('INSERT INTO task_event_states(event_key,source,source_id,due_date,status,title,category,updated_by) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),title=VALUES(title),category=VALUES(category),updated_by=VALUES(updated_by),updated_at=NOW()')
        ->execute([$key,$source,$id,$due ?: null,$status,trim((string)($d['title'] ?? '')) ?: null,trim((string)($d['category'] ?? '')) ?: null,$user['id'] ?? null]);
    json_response(['ok'=>true,'message'=>$status === 'done' ? 'Kalendereintrag abgehakt.' : ($status === 'waiting' ? 'Kalendereintrag auf Warteliste gesetzt.' : 'Kalendereintrag wieder geöffnet.')]);
}

function hk_column_exists_v236105(string $column): bool
{
    static $cache = [];
    if (isset($cache[$column])) return $cache[$column];
    try {
        $stmt = db()->prepare("SHOW COLUMNS FROM housekeeping_tasks LIKE ?");
        $stmt->execute([$column]);
        return $cache[$column] = (bool)$stmt->fetch();
    } catch (Throwable $e) { return $cache[$column] = false; }
}

function housekeeping_stale_tasks_v236105(): never
{
    if (!v235_table_exists('housekeeping_tasks')) {
        json_response(['ok'=>true,'items'=>[],'stats'=>['old_open'=>0,'open'=>0],'message'=>'Housekeeping-Tabelle ist nicht vorhanden.']);
    }
    $sql = "SELECT h.*, a.code apartment_code, a.name apartment_name, b.reference booking_reference,
            CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest_name
        FROM housekeeping_tasks h
        LEFT JOIN apartments a ON a.id=h.apartment_id
        LEFT JOIN bookings b ON b.id=h.booking_id
        LEFT JOIN guests g ON g.id=b.guest_id
        WHERE COALESCE(h.status,'') IN ('open','planned','assigned','accepted','in_progress','cleaning_done','inspection_required','ready_reported','done','inspected','ready')
          AND (h.released_at IS NULL OR h.released_at='0000-00-00 00:00:00')
          AND h.task_date < DATE_SUB(CURDATE(), INTERVAL 2 DAY)
        ORDER BY h.task_date ASC, h.id ASC
        LIMIT 100";
    $rows = db()->query($sql)->fetchAll();
    $open = (int)db()->query("SELECT COUNT(*) FROM housekeeping_tasks WHERE COALESCE(status,'') IN ('open','planned','assigned','accepted','in_progress')")->fetchColumn();
    json_response(['ok'=>true,'items'=>$rows,'stats'=>['old_open'=>count($rows),'open'=>$open],'message'=>count($rows).' ältere offene Housekeeping-Aufgaben gefunden.']);
}

function housekeeping_stale_task_action_v236105(): never
{
    $d = request_data();
    $id = (int)($d['id'] ?? 0);
    $mode = (string)($d['mode'] ?? '');
    if ($id <= 0) throw new ValidationException('Housekeeping-Aufgabe fehlt.');
    if (!in_array($mode, ['cancel','done','keep_open'], true)) throw new ValidationException('Aktion ist ungültig.');
    if (!v235_table_exists('housekeeping_tasks')) throw new RuntimeException('Housekeeping-Tabelle ist nicht vorhanden.');
    $stmt = db()->prepare('SELECT * FROM housekeeping_tasks WHERE id=?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) throw new NotFoundException('Housekeeping-Aufgabe nicht gefunden.');
    $note = 'Produktreife-Prüfung V2.3.6.107: ältere Aufgabe manuell geprüft.';
    if ($mode === 'cancel') {
        $sets = ["status='cancelled'"];
        if (hk_column_exists_v236105('completion_notes')) $sets[] = "completion_notes=CONCAT(COALESCE(completion_notes,''), CASE WHEN COALESCE(completion_notes,'')='' THEN '' ELSE '\n' END, " . db()->quote($note . ' Ergebnis: storniert.') . ")";
        if (hk_column_exists_v236105('updated_at')) $sets[] = "updated_at=NOW()";
        db()->exec("UPDATE housekeeping_tasks SET " . implode(',', $sets) . " WHERE id=" . (int)$id);
        $message = 'Housekeeping-Aufgabe storniert.';
    } elseif ($mode === 'done') {
        $sets = ["status='done'"];
        if (hk_column_exists_v236105('completed_at')) $sets[] = "completed_at=COALESCE(completed_at,NOW())";
        if (hk_column_exists_v236105('completion_notes')) $sets[] = "completion_notes=CONCAT(COALESCE(completion_notes,''), CASE WHEN COALESCE(completion_notes,'')='' THEN '' ELSE '\n' END, " . db()->quote($note . ' Ergebnis: erledigt markiert.') . ")";
        if (hk_column_exists_v236105('updated_at')) $sets[] = "updated_at=NOW()";
        db()->exec("UPDATE housekeeping_tasks SET " . implode(',', $sets) . " WHERE id=" . (int)$id);
        $message = 'Housekeeping-Aufgabe als erledigt markiert.';
    } else {
        $sets = [];
        if (hk_column_exists_v236105('notes')) $sets[] = "notes=CONCAT(COALESCE(notes,''), CASE WHEN COALESCE(notes,'')='' THEN '' ELSE '\n' END, " . db()->quote($note . ' Ergebnis: bewusst offen gelassen.') . ")";
        if (hk_column_exists_v236105('updated_at')) $sets[] = "updated_at=NOW()";
        if ($sets) db()->exec("UPDATE housekeeping_tasks SET " . implode(',', $sets) . " WHERE id=" . (int)$id);
        $message = 'Housekeeping-Aufgabe bleibt bewusst offen.';
    }
    json_response(['ok'=>true,'message'=>$message]);
}
