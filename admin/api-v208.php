<?php
declare(strict_types=1);

function role_matrix_v208(): never
{
    json_response(['ok'=>true,'roles'=>Auth::roleMatrix()]);
}

function housekeeping_teams_v208(): never
{
    $teams = db()->query("SELECT t.*,(SELECT COUNT(*) FROM housekeeping_members m WHERE m.team_id=t.id AND m.active=1) member_count FROM housekeeping_teams t ORDER BY t.active DESC,t.name")->fetchAll();
    $members = db()->query("SELECT m.*,t.name team_name,u.name user_name,u.email user_email,u.role user_role FROM housekeeping_members m LEFT JOIN housekeeping_teams t ON t.id=m.team_id LEFT JOIN users u ON u.id=m.user_id ORDER BY m.active DESC,COALESCE(t.name,''),m.name")->fetchAll();
    $users = db()->query("SELECT u.id,u.name,u.email,u.role,u.active,(SELECT hm.id FROM housekeeping_members hm WHERE hm.user_id=u.id LIMIT 1) housekeeping_member_id FROM users u WHERE u.role IN ('housekeeping','housekeeping_manager') ORDER BY u.active DESC,u.name")->fetchAll();
    json_response(['ok'=>true,'teams'=>$teams,'members'=>$members,'users'=>$users]);
}

function save_housekeeping_team_v208(): never
{
    $d=request_data();$id=(int)($d['id']??0);$old=$id?fetch_housekeeping_row_v208('housekeeping_teams',$id):null;
    if($id&&!$old)throw new NotFoundException('Putzteam nicht gefunden.');
    $name=Validator::text($d,'name','Teamname',160,true);
    $code=strtoupper(Validator::text($d,'code','Kurzcode',60,true));
    $email=Validator::email($d,'email','E-Mail');
    $values=[$name,$code,Validator::text($d,'phone','Telefon',80),$email,Validator::text($d,'whatsapp_number','WhatsApp-Nummer',80),Validator::text($d,'color','Farbe',20)?:'#0f9f6e',normalize_bool($d['active']??0),Validator::text($d,'notes','Notizen',10000)];
    try{
        if($id)db()->prepare('UPDATE housekeeping_teams SET name=?,code=?,phone=?,email=?,whatsapp_number=?,color=?,active=?,notes=? WHERE id=?')->execute([...$values,$id]);
        else{db()->prepare('INSERT INTO housekeeping_teams(name,code,phone,email,whatsapp_number,color,active,notes) VALUES(?,?,?,?,?,?,?,?)')->execute($values);$id=(int)db()->lastInsertId();}
    }catch(PDOException $e){if((string)$e->getCode()==='23000')throw new ConflictException('Der Team-Kurzcode ist bereits vergeben.');throw $e;}
    $new=fetch_housekeeping_row_v208('housekeeping_teams',$id);AuditLogger::record('housekeeping_team',$id,$old?'update':'create',$old,$new,'Putzteam gespeichert');
    json_response(['ok'=>true,'message'=>'Putzteam gespeichert.','id'=>$id]);
}

function delete_housekeeping_team_v208(): never
{
    $id=(int)(request_data()['id']??0);$old=fetch_housekeeping_row_v208('housekeeping_teams',$id);if(!$old)throw new NotFoundException('Putzteam nicht gefunden.');
    $stmt=db()->prepare('SELECT COUNT(*) FROM housekeeping_members WHERE team_id=?');$stmt->execute([$id]);
    $tasks=db()->prepare('SELECT COUNT(*) FROM housekeeping_tasks WHERE team_id=?');$tasks->execute([$id]);
    if((int)$stmt->fetchColumn()>0||(int)$tasks->fetchColumn()>0)throw new ConflictException('Das Team wird verwendet. Setzen Sie es stattdessen auf inaktiv.');
    db()->prepare('DELETE FROM housekeeping_teams WHERE id=?')->execute([$id]);AuditLogger::record('housekeeping_team',$id,'delete',$old,null,'Putzteam gelöscht');
    json_response(['ok'=>true,'message'=>'Putzteam gelöscht.']);
}

function save_housekeeping_member_v208(): never
{
    $d=request_data();$id=(int)($d['id']??0);$old=$id?fetch_housekeeping_row_v208('housekeeping_members',$id):null;
    if($id&&!$old)throw new NotFoundException('Mitarbeiter nicht gefunden.');
    $teamId=(int)($d['team_id']??0)?:null;$userId=(int)($d['user_id']??0)?:null;
    if($teamId&&!fetch_housekeeping_row_v208('housekeeping_teams',$teamId))throw new ValidationException('Das gewählte Team existiert nicht.');
    if($userId){$stmt=db()->prepare("SELECT id,role,active FROM users WHERE id=? LIMIT 1");$stmt->execute([$userId]);$u=$stmt->fetch();if(!$u||$u['role']!=='housekeeping'||!(int)$u['active'])throw new ValidationException('Es kann nur ein aktives Housekeeping-Benutzerkonto verknüpft werden.');}
    $name=Validator::text($d,'name','Name',160,true);$email=Validator::email($d,'email','E-Mail');
    $values=[$teamId,$userId,$name,Validator::text($d,'phone','Telefon',80),$email,Validator::text($d,'whatsapp_number','WhatsApp-Nummer',80),normalize_bool($d['receives_whatsapp']??0),normalize_bool($d['receives_email']??0),normalize_bool($d['active']??0),Validator::text($d,'notes','Notizen',10000)];
    db()->beginTransaction();
    try{
        if($userId){$clear=db()->prepare('UPDATE housekeeping_members SET user_id=NULL WHERE user_id=? AND id<>?');$clear->execute([$userId,$id]);}
        if($id)db()->prepare('UPDATE housekeeping_members SET team_id=?,user_id=?,name=?,phone=?,email=?,whatsapp_number=?,receives_whatsapp=?,receives_email=?,active=?,notes=? WHERE id=?')->execute([...$values,$id]);
        else{db()->prepare('INSERT INTO housekeeping_members(team_id,user_id,name,phone,email,whatsapp_number,receives_whatsapp,receives_email,active,notes) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute($values);$id=(int)db()->lastInsertId();}
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($e instanceof PDOException&&(string)$e->getCode()==='23000')throw new ConflictException('Dieses Benutzerkonto ist bereits mit einem anderen Putzteam-Mitarbeiter verbunden.');throw $e;}
    $new=fetch_housekeeping_row_v208('housekeeping_members',$id);AuditLogger::record('housekeeping_member',$id,$old?'update':'create',$old,$new,'Putzteam-Mitarbeiter gespeichert');
    json_response(['ok'=>true,'message'=>'Mitarbeiter gespeichert.','id'=>$id]);
}

function delete_housekeeping_member_v208(): never
{
    $id=(int)(request_data()['id']??0);$old=fetch_housekeeping_row_v208('housekeeping_members',$id);if(!$old)throw new NotFoundException('Mitarbeiter nicht gefunden.');
    $stmt=db()->prepare('SELECT COUNT(*) FROM housekeeping_tasks WHERE member_id=?');$stmt->execute([$id]);
    if((int)$stmt->fetchColumn()>0)throw new ConflictException('Der Mitarbeiter ist Aufgaben zugeordnet. Setzen Sie ihn stattdessen auf inaktiv.');
    db()->prepare('DELETE FROM housekeeping_members WHERE id=?')->execute([$id]);AuditLogger::record('housekeeping_member',$id,'delete',$old,null,'Putzteam-Mitarbeiter gelöscht');
    json_response(['ok'=>true,'message'=>'Mitarbeiter gelöscht.']);
}

function smtp_settings_v208(): never
{
    json_response(['ok'=>true,'settings'=>SmtpMailer::settings(false)]);
}

function save_smtp_settings_v208(): never
{
    $result=SmtpMailer::save(request_data());
    json_response(['ok'=>true,'message'=>'SMTP-Einstellungen sicher gespeichert.','settings'=>$result]);
}

function test_smtp_v208(): never
{
    $d=request_data();
    $result=SmtpMailer::test();
    CommunicationLogger::record('email','smtp_test',1,(string)(Auth::user()['name']??''),(string)($d['test_email']??''),'SMTP-Verbindungstest','SMTP-Verbindung und Anmeldung getestet.','success','Kein Nachrichtenversand');
    json_response(['ok'=>true,'message'=>$result['message']]);
}

function send_test_email_v208(): never
{
    $d=request_data();$email=Validator::email($d,'email','Empfänger',true);
    $subject='StayPilot SMTP-Test';$text="Diese Testnachricht bestätigt, dass der SMTP-Versand von StayPilot funktioniert.\n\nZeitpunkt: ".date('d.m.Y H:i:s');
    try{$result=SmtpMailer::send($email,$subject,$text,'<div style="font-family:Arial,sans-serif"><h2>StayPilot SMTP-Test</h2><p>Diese Testnachricht bestätigt, dass der SMTP-Versand funktioniert.</p><p><b>Zeitpunkt:</b> '.e(date('d.m.Y H:i:s')).'</p></div>');
        CommunicationLogger::record('email','smtp_test',1,(string)(Auth::user()['name']??''),$email,$subject,$text,'sent',$result['message_id']??'');
        AuditLogger::record('smtp_settings',1,'test_mail',null,['recipient'=>$email],'SMTP-Testmail versendet');json_response(['ok'=>true,'message'=>'Testmail wurde versendet.']);
    }catch(Throwable $e){CommunicationLogger::record('email','smtp_test',1,(string)(Auth::user()['name']??''),$email,$subject,$text,'failed',$e->getMessage());throw $e;}
}

function communications_v208(): never
{
    $channel=trim((string)($_GET['channel']??''));$limit=max(20,min(500,(int)($_GET['limit']??200)));
    $sql='SELECT l.*,u.name created_by_name FROM communication_log l LEFT JOIN users u ON u.id=l.created_by';$params=[];
    if($channel!==''){$sql.=' WHERE l.channel=?';$params[]=$channel;}
    $sql.=' ORDER BY l.id DESC LIMIT '.$limit;$stmt=db()->prepare($sql);$stmt->execute($params);
    json_response(['ok'=>true,'entries'=>$stmt->fetchAll()]);
}

function whatsapp_task_preview_v208(): never
{
    $task=housekeeping_task_detail_v208((int)(request_data()['id']??0));
    [$name,$number]=housekeeping_recipient_v208($task,'whatsapp');
    if($number==='')throw new ValidationException('Für den zugeordneten Mitarbeiter oder das Team ist keine WhatsApp-Nummer hinterlegt.');
    $message=housekeeping_message_v208($task);
    json_response(['ok'=>true,'recipient_name'=>$name,'recipient_number'=>$number,'message'=>$message]);
}

function whatsapp_task_open_v208(): never
{
    $task=housekeeping_task_detail_v208((int)(request_data()['id']??0));
    [$name,$number]=housekeeping_recipient_v208($task,'whatsapp');
    if($number==='')throw new ValidationException('Keine WhatsApp-Nummer hinterlegt.');
    $message=housekeeping_message_v208($task);$normalized=preg_replace('/\D+/','',$number)?:'';
    if($normalized==='')throw new ValidationException('Die WhatsApp-Nummer ist ungültig.');
    $url='https://api.whatsapp.com/send?phone='.$normalized.'&text='.rawurlencode($message);
    db()->prepare("UPDATE housekeeping_tasks SET whatsapp_status='opened',whatsapp_opened_at=NOW(),whatsapp_opened_by=? WHERE id=?")->execute([Auth::user()['id']??null,$task['id']]);
    CommunicationLogger::record('whatsapp','housekeeping_task',$task['id'],$name,$number,'Putzauftrag',$message,'opened','WhatsApp wurde mit vorbereiteter Nachricht geöffnet; Versand ist technisch nicht bestätigt.');
    AuditLogger::record('housekeeping_task',$task['id'],'whatsapp_open',null,['recipient'=>$name,'address'=>$number],'WhatsApp-Auftrag vorbereitet und geöffnet; kein automatischer Versandnachweis');
    json_response(['ok'=>true,'url'=>$url,'message'=>'WhatsApp-Nachricht wurde vorbereitet. Der tatsächliche Versand erfolgt erst in WhatsApp.']);
}

function send_task_email_v208(): never
{
    $task=housekeeping_task_detail_v208((int)(request_data()['id']??0));
    [$name,$email]=housekeeping_recipient_v208($task,'email');
    if($email==='')throw new ValidationException('Für den zugeordneten Mitarbeiter oder das Team ist keine E-Mail-Adresse hinterlegt.');
    $subject='Putzauftrag '.($task['apartment_code']??'').' am '.date('d.m.Y',strtotime((string)$task['task_date']));
    $message=housekeeping_message_v208($task);
    $html='<div style="font-family:Arial,sans-serif;line-height:1.5"><h2>'.e($subject).'</h2><pre style="white-space:pre-wrap;font-family:Arial,sans-serif">'.e($message).'</pre><p style="color:#64748b">Automatisch aus StayPilot erstellt.</p></div>';
    try{
        $result=SmtpMailer::send($email,$subject,$message,$html);
        db()->prepare("UPDATE housekeeping_tasks SET email_status='sent',email_sent_at=NOW() WHERE id=?")->execute([$task['id']]);
        CommunicationLogger::record('email','housekeeping_task',$task['id'],$name,$email,$subject,$message,'sent',$result['message_id']??'');
        AuditLogger::record('housekeeping_task',$task['id'],'email_send',null,['recipient'=>$name,'address'=>$email],'Putzauftrag per SMTP versendet');
        json_response(['ok'=>true,'message'=>'Putzauftrag wurde per E-Mail versendet.']);
    }catch(Throwable $e){
        db()->prepare("UPDATE housekeeping_tasks SET email_status='failed' WHERE id=?")->execute([$task['id']]);
        CommunicationLogger::record('email','housekeeping_task',$task['id'],$name,$email,$subject,$message,'failed',$e->getMessage());
        throw $e;
    }
}

function save_task_progress_v208(): never
{
    $d=request_data();$id=(int)($d['id']??0);$old=fetch_housekeeping_row_v208('housekeeping_tasks',$id);if(!$old)throw new NotFoundException('Aufgabe nicht gefunden.');
    ensure_housekeeping_task_access_v208($old);
    $status=(string)($d['status']??$old['status']);if(!in_array($status,['open','planned','in_progress','done'],true))throw new ValidationException('Ungültiger Aufgabenstatus.');
    $done=$d['checklist_done']??[];if(!is_array($done))$done=[];$done=array_values(array_unique(array_map('strval',$done)));
    $actual=($d['actual_minutes']??'')===''?null:max(0,(int)$d['actual_minutes']);
    $notes=Validator::text($d,'completion_notes','Abschlussnotiz',10000);
    $completedAt=$status==='done'?'NOW()':'NULL';
    $stmt=db()->prepare("UPDATE housekeeping_tasks SET status=?,actual_minutes=?,checklist_done_json=?,completion_notes=?,completed_at={$completedAt} WHERE id=?");$stmt->execute([$status,$actual,json_encode($done,JSON_UNESCAPED_UNICODE),$notes,$id]);
    $new=fetch_housekeeping_row_v208('housekeeping_tasks',$id);AuditLogger::record('housekeeping_task',$id,'progress',$old,$new,'Putzteam aktualisierte Aufgabe');
    json_response(['ok'=>true,'message'=>'Aufgabenfortschritt gespeichert.']);
}

function fetch_housekeeping_row_v208(string $table,int $id): ?array
{
    if(!in_array($table,['housekeeping_teams','housekeeping_members','housekeeping_tasks'],true))throw new InvalidArgumentException('Ungültige Tabelle.');
    $stmt=db()->prepare("SELECT * FROM `{$table}` WHERE id=? LIMIT 1");$stmt->execute([$id]);$row=$stmt->fetch();return $row?:null;
}

function housekeeping_task_detail_v208(int $id): array
{
    $stmt=db()->prepare("SELECT h.*,a.code apartment_code,a.name apartment_name,a.key_number,a.parking_number,a.cleaning_instructions AS cleaning_notes,
        b.reference,b.guest_request,b.adults,b.children,b.babies,b.pets,b.departure,b.arrival,
        TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,
        hm.name member_name,hm.email member_email,hm.phone member_phone,hm.whatsapp_number member_whatsapp,hm.receives_email member_receives_email,hm.receives_whatsapp member_receives_whatsapp,
        ht.name team_name,ht.email team_email,ht.phone team_phone,ht.whatsapp_number team_whatsapp
        FROM housekeeping_tasks h JOIN apartments a ON a.id=h.apartment_id
        LEFT JOIN bookings b ON b.id=h.booking_id LEFT JOIN guests g ON g.id=b.guest_id
        LEFT JOIN housekeeping_members hm ON hm.id=h.member_id LEFT JOIN housekeeping_teams ht ON ht.id=COALESCE(h.team_id,hm.team_id)
        WHERE h.id=? LIMIT 1");$stmt->execute([$id]);$row=$stmt->fetch();if(!$row)throw new NotFoundException('Aufgabe nicht gefunden.');ensure_housekeeping_task_access_v208($row);return $row;
}

function ensure_housekeeping_task_access_v208(array $task): void
{
    $user=Auth::user();if(($user['role']??'')!=='housekeeping')return;
    $stmt=db()->prepare('SELECT id,team_id FROM housekeeping_members WHERE user_id=? AND active=1 LIMIT 1');$stmt->execute([(int)$user['id']]);$member=$stmt->fetch();
    if(!$member||((int)$task['member_id']!==(int)$member['id']&&(!(int)$task['team_id']||(int)$task['team_id']!==(int)$member['team_id'])))throw new ForbiddenException('Diese Aufgabe ist Ihrem Konto nicht zugeordnet.');
}

function housekeeping_recipient_v208(array $task,string $channel): array
{
    if($channel==='email'){
        if((int)($task['member_receives_email']??0)&&filter_var($task['member_email']??'',FILTER_VALIDATE_EMAIL))return [(string)$task['member_name'],(string)$task['member_email']];
        if(filter_var($task['team_email']??'',FILTER_VALIDATE_EMAIL))return [(string)$task['team_name'],(string)$task['team_email']];
        return [(string)($task['member_name']?:$task['team_name']),''];
    }
    if((int)($task['member_receives_whatsapp']??0)&&trim((string)($task['member_whatsapp']??''))!=='')return [(string)$task['member_name'],(string)$task['member_whatsapp']];
    if(trim((string)($task['team_whatsapp']??''))!=='')return [(string)$task['team_name'],(string)$task['team_whatsapp']];
    return [(string)($task['member_name']?:$task['team_name']),''];
}

function housekeeping_message_v208(array $task): string
{
    $name=PrivacyService::housekeepingName($task['guest_name']??'');
    $reference=PrivacyService::housekeepingReference($task['reference']??'');
    $request=PrivacyService::housekeepingRequest($task['guest_request']??'');
    $check=json_decode((string)($task['checklist_json']??''),true)?:[];
    $lines=[
        'StayPilot – Putzauftrag',
        'Datum: '.date('d.m.Y',strtotime((string)$task['task_date'])),
        'Apartment: '.trim((string)$task['apartment_code'].' · '.(string)$task['apartment_name']),
        'Aufgabe: '.housekeeping_task_label_v208((string)$task['task_type']),
        'Priorität: '.ucfirst((string)$task['priority']),
        'Geplante Zeit: '.(int)$task['estimated_minutes'].' Minuten',
    ];
    if($name!=='')$lines[]='Gast: '.$name;if($reference!=='')$lines[]='Buchung: '.$reference;
    if((int)($task['adults']??0)||(int)($task['children']??0)||(int)($task['babies']??0))$lines[]='Belegung: '.(int)$task['adults'].' Erw. · '.(int)$task['children'].' Kinder · '.(int)$task['babies'].' Babys';
    $lines[]='Wäsche: '.((int)$task['linen_change']?'Bettwäsche ':'').((int)$task['towel_change']?'Handtücher':'');
    if(!empty($task['key_number']))$lines[]='Schlüssel: '.$task['key_number'];if(!empty($task['parking_number']))$lines[]='Parkplatz: '.$task['parking_number'];
    if($request!=='')$lines[]='Gastwunsch: '.$request;
    if($check){$lines[]='';$lines[]='Checkliste:';foreach($check as $item)$lines[]='☐ '.trim((string)$item);}
    if(trim((string)($task['supplies']??''))!==''){$lines[]='';$lines[]='Material: '.trim((string)$task['supplies']);}
    $notes=trim((string)($task['notes']??''));$clean=trim((string)($task['cleaning_notes']??''));if($notes!==''||$clean!==''){$lines[]='';$lines[]='Hinweise: '.trim($notes."\n".$clean);}
    $lines[]='';$lines[]='Hinweis: Diese Nachricht wurde vorbereitet. Bei WhatsApp bestätigt StayPilot den tatsächlichen Versand nicht automatisch.';
    return implode("\n",$lines);
}

function housekeeping_task_label_v208(string $s): string
{
    return ['turnover'=>'Wechselreinigung','stayover'=>'Zwischenreinigung','deep_clean'=>'Grundreinigung','maintenance'=>'Wartung','inspection'=>'Kontrolle','reclean'=>'Nachreinigung','special'=>'Sonderauftrag'][$s]??$s;
}
