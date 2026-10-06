<?php
declare(strict_types=1);

function save_housekeeping_member_v209(): never
{
    $d=request_data();$id=(int)($d['id']??0);$old=$id?fetch_housekeeping_row_v208('housekeeping_members',$id):null;
    if($id&&!$old)throw new NotFoundException('Mitarbeiter nicht gefunden.');
    $teamId=(int)($d['team_id']??0)?:null;$userId=(int)($d['user_id']??0)?:null;
    if($teamId&&!fetch_housekeeping_row_v208('housekeeping_teams',$teamId))throw new ValidationException('Das gewählte Team existiert nicht.');
    if($userId){
        $stmt=db()->prepare("SELECT id,role,active FROM users WHERE id=? LIMIT 1");$stmt->execute([$userId]);$u=$stmt->fetch();
        if(!$u||!in_array((string)$u['role'],['housekeeping','housekeeping_manager'],true)||!(int)$u['active'])throw new ValidationException('Es kann nur ein aktives Housekeeping- oder Gouvernantenkonto verknüpft werden.');
    }
    $name=Validator::text($d,'name','Name',160,true);$email=Validator::email($d,'email','E-Mail');
    $language=in_array((string)($d['preferred_language']??'de'),['de','es'],true)?(string)$d['preferred_language']:'de';
    $values=[
        $teamId,$userId,$name,Validator::text($d,'phone','Telefon',80),$email,Validator::text($d,'whatsapp_number','WhatsApp-Nummer',80),
        normalize_bool($d['receives_whatsapp']??0),normalize_bool($d['receives_email']??0),
        normalize_bool($d['can_assign']??0),normalize_bool($d['can_reassign']??0),normalize_bool($d['can_inspect']??0),normalize_bool($d['can_mark_ready']??0),
        normalize_bool($d['can_report_incident']??0),normalize_bool($d['can_upload_photos']??0),$language,
        normalize_bool($d['active']??0),Validator::text($d,'notes','Notizen',10000)
    ];
    db()->beginTransaction();
    try{
        if($userId){$clear=db()->prepare('UPDATE housekeeping_members SET user_id=NULL WHERE user_id=? AND id<>?');$clear->execute([$userId,$id]);}
        if($id)db()->prepare('UPDATE housekeeping_members SET team_id=?,user_id=?,name=?,phone=?,email=?,whatsapp_number=?,receives_whatsapp=?,receives_email=?,can_assign=?,can_reassign=?,can_inspect=?,can_mark_ready=?,can_report_incident=?,can_upload_photos=?,preferred_language=?,active=?,notes=? WHERE id=?')->execute([...$values,$id]);
        else{db()->prepare('INSERT INTO housekeeping_members(team_id,user_id,name,phone,email,whatsapp_number,receives_whatsapp,receives_email,can_assign,can_reassign,can_inspect,can_mark_ready,can_report_incident,can_upload_photos,preferred_language,active,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values);$id=(int)db()->lastInsertId();}
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($e instanceof PDOException&&(string)$e->getCode()==='23000')throw new ConflictException('Dieses Benutzerkonto ist bereits mit einem anderen Mitarbeiter verbunden.');throw $e;}
    $new=fetch_housekeeping_row_v208('housekeeping_members',$id);AuditLogger::record('housekeeping_member',$id,$old?'update':'create',$old,$new,'Housekeeping-Mitarbeiter und Rechte gespeichert');
    json_response(['ok'=>true,'message'=>'Mitarbeiter und Rechte gespeichert.','id'=>$id]);
}

function save_task_v209(): never
{
    $d=request_data();$id=(int)($d['id']??0);$old=$id?fetch_housekeeping_row_v208('housekeeping_tasks',$id):null;if($id&&!$old)throw new NotFoundException('Aufgabe nicht gefunden.');
    $checklist=$d['checklist']??[];if(is_string($checklist))$checklist=array_values(array_filter(array_map('trim',preg_split('/[\r\n;]+/',$checklist))));if(!is_array($checklist))$checklist=[];
    $teamId=(int)($d['team_id']??0)?:null;$memberId=(int)($d['member_id']??0)?:null;$assigned=trim((string)($d['assigned_to']??''))?:null;
    if($memberId){$stmt=db()->prepare('SELECT id,team_id,name FROM housekeeping_members WHERE id=? AND active=1 LIMIT 1');$stmt->execute([$memberId]);$member=$stmt->fetch();if(!$member)throw new ValidationException('Der gewählte Mitarbeiter ist nicht aktiv.');$assigned=(string)$member['name'];$memberTeamId=(int)($member['team_id']??0)?:null;if($teamId&&$memberTeamId&&$teamId!==$memberTeamId)throw new ValidationException('Der Mitarbeiter gehört nicht zum gewählten Team.');$teamId=$memberTeamId?:$teamId;}
    elseif($teamId){$stmt=db()->prepare('SELECT id,name FROM housekeeping_teams WHERE id=? AND active=1 LIMIT 1');$stmt->execute([$teamId]);$team=$stmt->fetch();if(!$team)throw new ValidationException('Das gewählte Team ist nicht aktiv.');$assigned=(string)$team['name'];}
    $taskType=(string)($d['task_type']??'turnover');
    if(!in_array($taskType,['turnover','stayover','deep_clean','inspection','reclean','special','maintenance'],true))throw new ValidationException('Ungültige Auftragsart.');
    $priority=(string)($d['priority']??'normal');if(!in_array($priority,['low','normal','high','urgent'],true))$priority='normal';
    $apartmentId=(int)($d['apartment_id']??0);$apartmentCheck=db()->prepare('SELECT id FROM apartments WHERE id=? LIMIT 1');$apartmentCheck->execute([$apartmentId]);if(!$apartmentCheck->fetchColumn())throw new ValidationException('Apartment nicht gefunden.');
    $bookingId=(int)($d['booking_id']??0)?:null;if($bookingId){$bookingCheck=db()->prepare('SELECT id FROM bookings WHERE id=? LIMIT 1');$bookingCheck->execute([$bookingId]);if(!$bookingCheck->fetchColumn())throw new ValidationException('Buchung nicht gefunden.');}
    $status=(string)($d['status']??($old['status']??'open'));
    if(!in_array($status,HousekeepingWorkflow::STATUSES,true))throw new ValidationException('Ungültiger Aufgabenstatus.');
    $editorStatuses=['open','assigned','cancelled'];
    if($old){
        $oldStatus=(string)($old['status']??'open');
        if($status!==$oldStatus&&!in_array($status,$editorStatuses,true)){
            throw new ConflictException('Dieser Workflow-Status kann nicht im allgemeinen Aufgabenformular gesetzt werden.');
        }
    }elseif(!in_array($status,$editorStatuses,true)){
        $status='open';
    }
    if(($memberId||$teamId)&&$status==='open')$status='assigned';if(!$memberId&&!$teamId&&in_array($status,['open','assigned'],true))$status='open';
    $origin=(string)($d['origin_type']??($old['origin_type']??'manual'));
    if(!in_array($origin,['manual','booking_departure','booking_extra','inspection','reclean','special'],true))$origin='manual';
    $due=trim((string)($d['due_time']??''));if($due!==''&&!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$due))throw new ValidationException('Die Uhrzeit ist ungültig.');
    $values=[$apartmentId,$bookingId,(string)($d['task_date']??''),$taskType,$status,$origin,$due?:null,
        $assigned,$teamId,$memberId,(int)(Auth::user()['id']??0),$priority,max(0,min(1440,(int)($d['estimated_minutes']??60))),($d['actual_minutes']??'')===''?null:max(0,min(1440,(int)$d['actual_minutes'])),
        normalize_bool($d['linen_change']??0),normalize_bool($d['towel_change']??0),json_encode(array_values($checklist),JSON_UNESCAPED_UNICODE),trim((string)($d['supplies']??'')),trim((string)($d['supervisor']??''))?:null,trim((string)($d['notes']??'')),trim((string)($d['completion_notes']??''))];
    if(!$values[0]||!valid_date($values[2]))throw new ValidationException('Wohnung und Datum sind erforderlich.');
    if($id){$stmt=db()->prepare('UPDATE housekeeping_tasks SET apartment_id=?,booking_id=?,task_date=?,task_type=?,status=?,origin_type=?,due_time=?,assigned_to=?,team_id=?,member_id=?,assigned_by=?,priority=?,estimated_minutes=?,actual_minutes=?,linen_change=?,towel_change=?,checklist_json=?,supplies=?,supervisor=?,notes=?,completion_notes=? WHERE id=?');$stmt->execute([...$values,$id]);}
    else{$stmt=db()->prepare('INSERT INTO housekeeping_tasks(apartment_id,booking_id,task_date,task_type,status,origin_type,due_time,assigned_to,team_id,member_id,assigned_by,priority,estimated_minutes,actual_minutes,linen_change,towel_change,checklist_json,supplies,supervisor,notes,completion_notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute($values);$id=(int)db()->lastInsertId();}
    $new=fetch_housekeeping_row_v208('housekeeping_tasks',$id);AuditLogger::record('housekeeping_task',$id,$old?'update':'create',$old,$new,$old?'Reinigungsauftrag bearbeitet':'Reinigungsauftrag manuell angelegt');
    if(!$old||((int)($old['member_id']??0)!==$memberId)||((int)($old['team_id']??0)!==$teamId))HousekeepingWorkflow::notifyTaskAssignee($id,'Neuer Reinigungsauftrag','Ihnen wurde ein Auftrag für '.($new['task_date']??'').' zugewiesen.');
    json_response(['ok'=>true,'message'=>'Auftrag gespeichert.','id'=>$id]);
}

function task_status_v209(): never
{
    $d=request_data();$id=(int)($d['id']??0);$user=Auth::user();$old=HousekeepingWorkflow::taskForUser($id,$user,true);
    $status=(string)($d['status']??'open');
    if(!in_array($status,['open','assigned','cancelled'],true))throw new ValidationException('Dieser Status wird ausschließlich im Housekeeping-Workflow geändert.');
    $current=(string)($old['status']??'open');
    if(!in_array($current,['open','assigned','cancelled'],true)&&$status!==$current)throw new ConflictException('Ein fortgeschrittener Workflow-Status kann hier nicht zurückgesetzt werden.');
    db()->prepare('UPDATE housekeeping_tasks SET status=? WHERE id=?')->execute([$status,$id]);
    $new=HousekeepingWorkflow::taskDetail($id);AuditLogger::record('housekeeping_task',$id,'status',$old,$new,'Aufgabenstatus aktualisiert');json_response(['ok'=>true,'message'=>'Status aktualisiert.']);
}

function save_task_progress_v209(): never
{
    $d=request_data();$id=(int)($d['id']??0);$user=Auth::user();$old=HousekeepingWorkflow::taskForUser($id,$user);
    $status=(string)($d['status']??$old['status']);
    $allowed=['accepted','in_progress','cleaning_done'];
    if(!in_array($status,$allowed,true))throw new ValidationException('Dieser Status kann in der Mitarbeiteransicht nicht gesetzt werden.');
    $done=$d['checklist_done']??[];if(!is_array($done))$done=[];$done=array_values(array_unique(array_map('strval',$done)));
    $check=json_decode((string)($old['checklist_json']??''),true)?:[];
    $notes=Validator::text($d,'completion_notes','Abschlussnotiz',10000);
    if($status==='cleaning_done'&&count($done)<count($check)&&trim($notes)==='')throw new ValidationException('Nicht vollständig abgehakte Checklisten benötigen eine Abschlussnotiz.');
    $actual=($d['actual_minutes']??'')===''?null:max(0,(int)$d['actual_minutes']);
    $sets=['status=?','actual_minutes=?','checklist_done_json=?','completion_notes=?'];$params=[$status,$actual,json_encode($done,JSON_UNESCAPED_UNICODE),$notes];
    if($status==='accepted')$sets[]='accepted_at=COALESCE(accepted_at,NOW())';
    if($status==='in_progress')$sets[]='started_at=COALESCE(started_at,NOW())';
    if($status==='cleaning_done'){$sets[]='cleaning_completed_at=NOW()';$sets[]='cleaning_completed_by=?';$params[]=(int)$user['id'];$sets[]='completed_at=NOW()';}
    $params[]=$id;db()->prepare('UPDATE housekeeping_tasks SET '.implode(',',$sets).' WHERE id=?')->execute($params);
    if($status==='cleaning_done'){
        HousekeepingWorkflow::notifyRoles(['housekeeping_manager','admin','manager','reception'],'cleaning_done','Reinigung abgeschlossen',($old['apartment_code']??'Wohnung').' wartet auf Kontrolle.','housekeeping_task',$id,'../team-manager/');
        if(!empty($old['booking_id']))db()->prepare("UPDATE bookings SET cleaning_status='cleaning_done' WHERE id=?")->execute([(int)$old['booking_id']]);
    }
    $new=HousekeepingWorkflow::taskDetail($id);AuditLogger::record('housekeeping_task',$id,'progress',$old,$new,'Mitarbeiter aktualisierte den Auftrag');
    json_response(['ok'=>true,'message'=>'Auftragsfortschritt gespeichert.','task'=>$new]);
}

function notifications_v209(): never
{
    $user=Auth::requireLogin();$after=max(0,(int)($_GET['after_id']??0));
    json_response(['ok'=>true,'notifications'=>HousekeepingWorkflow::unreadNotifications((int)$user['id'],$after)]);
}
function notifications_read_v209(): never
{
    $user=Auth::requireLogin();$ids=request_data()['ids']??[];if(!is_array($ids))$ids=[];HousekeepingWorkflow::markNotificationsRead((int)$user['id'],$ids);json_response(['ok'=>true]);
}

function housekeeping_release_queue_v209(): never
{
    $sql="SELECT h.id,h.booking_id,h.task_date,h.due_time,h.status,h.ready_reported_at,h.release_blocked,h.release_note,a.code apartment_code,a.name apartment_name,b.reference,b.arrival,b.departure,
        hm.name member_name,ht.name team_name,
        (SELECT COUNT(*) FROM housekeeping_incidents hi WHERE hi.task_id=h.id AND hi.status IN ('open','review')) incident_count,
        (SELECT COUNT(*) FROM housekeeping_incidents hi WHERE hi.task_id=h.id AND hi.status IN ('open','review') AND (hi.severity IN ('high','critical') OR hi.apartment_usable=0)) blocking_incident_count
        FROM housekeeping_tasks h JOIN apartments a ON a.id=h.apartment_id LEFT JOIN bookings b ON b.id=h.booking_id LEFT JOIN housekeeping_members hm ON hm.id=h.member_id LEFT JOIN housekeeping_teams ht ON ht.id=h.team_id
        WHERE h.status IN ('cleaning_done','inspection_required','inspection_passed','rework_required','ready_reported','blocked','released') AND h.task_date BETWEEN ? AND ? AND (h.booking_id IS NULL OR (b.id IS NOT NULL AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND COALESCE(b.status,'') NOT IN ('cancelled','rejected')) OR h.status='cancelled') ORDER BY h.task_date,h.ready_reported_at,a.name";
    $from=(string)($_GET['from']??date('Y-m-d',strtotime('-2 days')));$to=(string)($_GET['to']??date('Y-m-d',strtotime('+7 days')));if(!valid_date($from)||!valid_date($to)||$from>$to)throw new ValidationException('Zeitraum ungültig.');
    $stmt=db()->prepare($sql);$stmt->execute([$from,$to]);json_response(['ok'=>true,'from'=>$from,'to'=>$to,'tasks'=>$stmt->fetchAll()]);
}

function housekeeping_final_release_v209(): never
{
    $d=request_data();$result=HousekeepingWorkflow::releaseTask((int)($d['id']??0),Auth::user(),Validator::text($d,'note','Freigabehinweis',5000));
    json_response(['ok'=>true,'message'=>'Wohnung wurde endgültig für den Gast freigegeben.','guest_url'=>$result['url'],'task'=>$result['task']]);
}

function guest_portal_link_v209(): never
{
    $d=$_SERVER['REQUEST_METHOD']==='GET'?$_GET:request_data();$bookingId=(int)($d['booking_id']??0);if(!$bookingId)throw new ValidationException('Buchung fehlt.');
    $stmt=db()->prepare('SELECT id FROM bookings WHERE id=? LIMIT 1');$stmt->execute([$bookingId]);if(!$stmt->fetchColumn())throw new NotFoundException('Buchung nicht gefunden.');
    $access=HousekeepingWorkflow::ensureGuestAccess($bookingId);json_response(['ok'=>true,'url'=>HousekeepingWorkflow::guestPortalUrl($access['token'])]);
}

function housekeeping_incidents_v209(): never
{
    $status=trim((string)($_GET['status']??''));$sql="SELECT i.*,a.code apartment_code,a.name apartment_name,h.task_date,u.name reported_by_name,ru.name reviewed_by_name FROM housekeeping_incidents i JOIN apartments a ON a.id=i.apartment_id JOIN housekeeping_tasks h ON h.id=i.task_id LEFT JOIN users u ON u.id=i.reported_by LEFT JOIN users ru ON ru.id=i.reviewed_by";$params=[];
    if($status!==''){$sql.=' WHERE i.status=?';$params[]=$status;}$sql.=' ORDER BY FIELD(i.severity,\'critical\',\'high\',\'normal\',\'low\'),i.id DESC LIMIT 500';$stmt=db()->prepare($sql);$stmt->execute($params);json_response(['ok'=>true,'incidents'=>$stmt->fetchAll()]);
}

function housekeeping_incident_review_v209(): never
{
    $d=request_data();$id=(int)($d['id']??0);$status=(string)($d['status']??'review');if(!in_array($status,['review','resolved','dismissed'],true))throw new ValidationException('Ungültiger Status.');
    $stmt=db()->prepare('SELECT * FROM housekeeping_incidents WHERE id=? LIMIT 1');$stmt->execute([$id]);$old=$stmt->fetch();if(!$old)throw new NotFoundException('Meldung nicht gefunden.');
    $note=Validator::text($d,'resolution_note','Bearbeitungshinweis',10000);$resolved=in_array($status,['resolved','dismissed'],true)?'NOW()':'NULL';
    db()->prepare("UPDATE housekeeping_incidents SET status=?,reviewed_by=?,reviewed_at=NOW(),resolution_note=?,resolved_at={$resolved} WHERE id=?")->execute([$status,Auth::user()['id'],$note,$id]);
    if(in_array($status,['resolved','dismissed'],true)){
        $block=db()->prepare("SELECT COUNT(*) FROM housekeeping_incidents WHERE task_id=? AND status IN ('open','review') AND (severity IN ('high','critical') OR apartment_usable=0)");$block->execute([$old['task_id']]);
        if(!(int)$block->fetchColumn())db()->prepare("UPDATE housekeeping_tasks SET release_blocked=0,status=CASE WHEN status='blocked' THEN 'cleaning_done' ELSE status END WHERE id=?")->execute([$old['task_id']]);
    }
    AuditLogger::record('housekeeping_incident',$id,'review',$old,['status'=>$status,'resolution_note'=>$note],'Housekeeping-Meldung bearbeitet');json_response(['ok'=>true,'message'=>'Meldung aktualisiert.']);
}

function guest_portal_contents_v209(): never
{
    $rows=db()->query('SELECT * FROM guest_portal_contents ORDER BY scope_type,scope_id,language,sort_order,id')->fetchAll();json_response(['ok'=>true,'contents'=>$rows]);
}
function save_guest_portal_content_v209(): never
{
    $d=request_data();$id=(int)($d['id']??0);$scope=(string)($d['scope_type']??'global');if(!in_array($scope,['global','house','booking'],true))throw new ValidationException('Ungültiger Geltungsbereich.');
    $scopeId=(int)($d['scope_id']??0)?:null;
    if($scope!=='global'&&!$scopeId)throw new ValidationException('Bitte den Geltungsbereich vollständig auswählen.');
    if($scope==='house'){$check=db()->prepare('SELECT id FROM houses WHERE id=? LIMIT 1');$check->execute([$scopeId]);if(!$check->fetchColumn())throw new ValidationException('Das gewählte Haus existiert nicht.');}
    if($scope==='booking'){$check=db()->prepare('SELECT id FROM bookings WHERE id=? LIMIT 1');$check->execute([$scopeId]);if(!$check->fetchColumn())throw new ValidationException('Die gewählte Buchung existiert nicht.');}
    $lang=(string)($d['language']??'de');if(!in_array($lang,['de','es','en'],true))$lang='de';
    $title=Validator::text($d,'title','Titel',190,true);$body=Validator::text($d,'body','Inhalt',20000,true);$active=normalize_bool($d['active']??0);$sort=(int)($d['sort_order']??0);
    $old=null;if($id){$find=db()->prepare('SELECT * FROM guest_portal_contents WHERE id=? LIMIT 1');$find->execute([$id]);$old=$find->fetch();if(!$old)throw new NotFoundException('Gästeinformation nicht gefunden.');}
    if($id)db()->prepare('UPDATE guest_portal_contents SET scope_type=?,scope_id=?,language=?,title=?,body=?,sort_order=?,active=? WHERE id=?')->execute([$scope,$scopeId,$lang,$title,$body,$sort,$active,$id]);
    else{db()->prepare('INSERT INTO guest_portal_contents(scope_type,scope_id,language,title,body,sort_order,active) VALUES(?,?,?,?,?,?,?)')->execute([$scope,$scopeId,$lang,$title,$body,$sort,$active]);$id=(int)db()->lastInsertId();}
    $find=db()->prepare('SELECT * FROM guest_portal_contents WHERE id=? LIMIT 1');$find->execute([$id]);$new=$find->fetch();
    AuditLogger::record('guest_portal_content',$id,$old?'update':'create',$old,$new,'Gästeportal-Inhalt gespeichert');json_response(['ok'=>true,'message'=>'Gästeinformation gespeichert.','id'=>$id]);
}
function delete_guest_portal_content_v209(): never
{
    $id=(int)(request_data()['id']??0);$find=db()->prepare('SELECT * FROM guest_portal_contents WHERE id=? LIMIT 1');$find->execute([$id]);$old=$find->fetch();if(!$old)throw new NotFoundException('Gästeinformation nicht gefunden.');db()->prepare('DELETE FROM guest_portal_contents WHERE id=?')->execute([$id]);AuditLogger::record('guest_portal_content',$id,'delete',$old,null,'Gästeportal-Inhalt gelöscht');json_response(['ok'=>true,'message'=>'Gästeinformation gelöscht.']);
}
