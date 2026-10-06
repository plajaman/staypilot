<?php
declare(strict_types=1);
require_once __DIR__.'/_print_common.php';
$from=(string)($_GET['from']??date('Y-m-d'));$to=(string)($_GET['to']??$from);
if(!valid_date($from)||!valid_date($to)||$from>$to){http_response_code(400);exit('Ungültiger Zeitraum.');}
$user=Auth::user();$role=(string)($user['role']??'readonly');
$sql="SELECT h.*,a.code apartment_code,a.name apartment_name,a.house_id,a.apartment_type_id,hs.name house_name,at.name apartment_type_name,b.reference,b.guest_request,TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,hm.name member_name,ht.name team_name
FROM housekeeping_tasks h JOIN apartments a ON a.id=h.apartment_id
LEFT JOIN houses hs ON hs.id=a.house_id LEFT JOIN apartment_types at ON at.id=a.apartment_type_id
LEFT JOIN bookings b ON b.id=h.booking_id LEFT JOIN guests g ON g.id=b.guest_id
LEFT JOIN housekeeping_members hm ON hm.id=h.member_id LEFT JOIN housekeeping_teams ht ON ht.id=COALESCE(h.team_id,hm.team_id)
WHERE h.task_date BETWEEN ? AND ?";$params=[$from,$to];
if(in_array($role,['housekeeping','housekeeping_manager'],true)){
    $identityStmt=db()->prepare('SELECT id,team_id FROM housekeeping_members WHERE user_id=? AND active=1 LIMIT 1');$identityStmt->execute([(int)($user['id']??0)]);$identity=$identityStmt->fetch();
    if(!$identity){$sql.=' AND 1=0';}else{$sql.=' AND (h.member_id=? OR (h.member_id IS NULL AND h.team_id=?))';$params[]=(int)$identity['id'];$params[]=(int)($identity['team_id']??0);}
}else{
    foreach(['apartment_id','house_id','apartment_type_id','status','task_type','assigned_to','priority','team_id','member_id'] as $key){$v=trim((string)($_GET[$key]??''));if($v==='')continue;if($key==='house_id'||$key==='apartment_type_id'){$sql.=' AND a.'.$key.'=?';$params[]=(int)$v;}elseif(in_array($key,['apartment_id','team_id','member_id'],true)){$sql.=' AND h.'.$key.'=?';$params[]=(int)$v;}else{$sql.=' AND h.'.$key.'=?';$params[]=$v;}}
}
$sql.=" ORDER BY h.task_date,FIELD(h.priority,'urgent','high','normal','low'),a.sort_order,a.name";$stmt=db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
foreach($rows as &$row){if(in_array($role,['housekeeping','housekeeping_manager'],true)){$row['guest_name']=PrivacyService::housekeepingName($row['guest_name']??'');$row['reference']=PrivacyService::housekeepingReference($row['reference']??'');$row['guest_request']=PrivacyService::housekeepingRequest($row['guest_request']??'');}}unset($row);
$group=[];foreach($rows as $r)$group[$r['task_date']][]=$r;
print_header('Putz- und Arbeitsliste',fmt_date($from).' bis '.fmt_date($to));
?><div class="summary"><span><b><?=count($rows)?></b> Aufgaben</span><span><b><?=count(array_filter($rows,fn($r)=>in_array($r['status'],['cleaning_done','inspection_passed','ready_reported','released'],true)))?></b> erledigt</span><span><b><?=array_sum(array_map(fn($r)=>(int)$r['estimated_minutes'],$rows))?></b> Minuten geplant</span></div>
<?php if(!$rows):?><div class="no-data">Für die gewählten Vorgaben wurden keine Aufgaben gefunden.</div><?php endif?>
<?php foreach($group as $date=>$items):?><h2 class="date-title"><?=e(fmt_date($date))?> · <?=e(weekday_de($date))?></h2><table><thead><tr><th>Wohnung</th><th>Aufgabe / Buchung</th><th>Zuständig</th><th>Priorität</th><th>Zeit</th><th>Wäsche</th><th>Checkliste / Material</th><th>Status</th><th>Unterschrift</th></tr></thead><tbody>
<?php foreach($items as $r):$check=json_decode((string)($r['checklist_json']??''),true)?:[];?><tr><td><b><?=e($r['apartment_code'].' · '.$r['apartment_name'])?></b></td><td><?=e(task_label((string)$r['task_type']))?><?php if($r['guest_name']):?><br><span class="muted"><?=e($r['reference'].' · '.$r['guest_name'])?></span><?php endif?><br><?=nl2br(e((string)$r['notes']))?><?php if(!empty($r['guest_request'])):?><br><b>Gastwunsch:</b> <?=nl2br(e((string)$r['guest_request']))?><?php endif?></td><td><?=e((string)($r['member_name']?:$r['team_name']?:$r['assigned_to']))?><?php if($r['supervisor']):?><br><span class="muted">Kontrolle: <?=e($r['supervisor'])?></span><?php endif?></td><td><?=e((string)$r['priority'])?></td><td><?=e((string)$r['estimated_minutes'])?> Min.<?php if($r['actual_minutes']!==null):?><br><span class="muted">Ist: <?=e((string)$r['actual_minutes'])?> Min.</span><?php endif?></td><td><span class="check"><?=((int)$r['linen_change']?'☐':'–')?></span> Bett<br><span class="check"><?=((int)$r['towel_change']?'☐':'–')?></span> Handtücher</td><td><?php foreach($check as $item):?>☐ <?=e((string)$item)?><br><?php endforeach?><?php if($r['supplies']):?><b>Material:</b> <?=nl2br(e((string)$r['supplies']))?><?php endif?></td><td><?=e(status_text_print((string)$r['status']))?></td><td><div class="signature"></div></td></tr><?php endforeach?></tbody></table><?php endforeach?>
<?php print_footer();
function fmt_date(string $d):string{return (new DateTimeImmutable($d))->format('d.m.Y');}
function task_label(string $s):string{return HousekeepingWorkflow::taskTypeLabel($s,'de');}
function status_text_print(string $s):string{return HousekeepingWorkflow::statusLabel($s,'de');}
function weekday_de(string $d):string{return ['Sunday'=>'Sonntag','Monday'=>'Montag','Tuesday'=>'Dienstag','Wednesday'=>'Mittwoch','Thursday'=>'Donnerstag','Friday'=>'Freitag','Saturday'=>'Samstag'][(new DateTimeImmutable($d))->format('l')]??'';}
