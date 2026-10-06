<?php
declare(strict_types=1);
require_once __DIR__.'/_print_common.php';
if(!Auth::can('police_manage')){http_response_code(403);exit('Keine Berechtigung fuer Meldedaten-Exporte.');}

$from=(string)($_GET['from']??date('Y-m-d'));
$to=(string)($_GET['to']??$from);
$status=trim((string)($_GET['status']??''));
if(!valid_date($from)||!valid_date($to)||$from>$to){http_response_code(400);exit('Ungültiger Zeitraum.');}

$sql="SELECT b.id,b.reference,b.arrival,b.departure,b.police_status,b.police_sent_at,
             a.code apartment_code,a.name apartment_name,
             CONCAT(g.first_name,' ',g.last_name) guest_name
      FROM bookings b
      JOIN guests g ON g.id=b.guest_id
      LEFT JOIN apartments a ON a.id=b.apartment_id
      WHERE b.status NOT IN ('cancelled','rejected') AND b.arrival BETWEEN ? AND ?";
$params=[$from,$to];
if(in_array($status,['open','ready','sent','error'],true)){$sql.=' AND b.police_status=?';$params[]=$status;}
$sql.=' ORDER BY b.arrival,a.sort_order,a.name,b.reference';
$stmt=db()->prepare($sql);$stmt->execute($params);$bookings=$stmt->fetchAll();

$rows=[];
foreach($bookings as $b){
    $q=db()->prepare('SELECT * FROM booking_travellers WHERE booking_id=? ORDER BY is_primary DESC,id');
    $q->execute([(int)$b['id']]);
    $travellers=$q->fetchAll();
    if(!$travellers)$travellers=[['first_name'=>'','last_name'=>'','second_last_name'=>'','gender'=>'','date_of_birth'=>'','document_type'=>'','document_number'=>'','document_support_number'=>'','document_issue_date'=>'','document_country'=>'','nationality'=>'','place_of_birth'=>'','province'=>'','address'=>'','postal_code'=>'','city'=>'','country'=>'','relationship_to_primary'=>'','minor'=>0,'signature_status'=>'open']];
    foreach($travellers as $t)$rows[]=['booking'=>$b,'traveller'=>$t];
}

print_header('Polizeiliche Meldeliste',fmt_police_date($from).' bis '.fmt_police_date($to).($status!==''?' · Status: '.police_status_text($status):''));
?>
<div class="summary"><span><b><?=count($bookings)?></b> Buchungen</span><span><b><?=count($rows)?></b> reisende Personen</span><span><b><?=count(array_filter($bookings,fn($b)=>$b['police_status']==='sent'))?></b> als gemeldet markiert</span></div>
<?php if(!$rows):?><div class="no-data">Für den gewählten Zeitraum wurden keine Meldedaten gefunden.</div><?php else:?>
<table>
<thead><tr><th>Anreise / Unterkunft</th><th>Buchung</th><th>Name</th><th>Geburt / Geschlecht</th><th>Dokument</th><th>Nationalität / Geburtsort</th><th>Anschrift</th><th>Beziehung</th><th>Unterschrift</th><th>Status</th></tr></thead>
<tbody>
<?php foreach($rows as $row):$b=$row['booking'];$t=$row['traveller'];?>
<tr>
<td><b><?=e(fmt_police_date((string)$b['arrival']))?></b><br><?=e(($b['apartment_code']?:'–').' · '.($b['apartment_name']?:'Nicht zugeordnet'))?></td>
<td><b><?=e((string)$b['reference'])?></b><br><span class="muted"><?=e((string)$b['guest_name'])?></span></td>
<td><b><?=e(trim((string)($t['first_name']??'').' '.(string)($t['last_name']??'').' '.(string)($t['second_last_name']??'')))?></b></td>
<td><?=e(!empty($t['date_of_birth'])?fmt_police_date((string)$t['date_of_birth']):'')?> <br><?=e((string)($t['gender']??''))?></td>
<td><?=e((string)($t['document_type']??''))?> <?=e((string)($t['document_number']??''))?><br><span class="muted">Support: <?=e((string)($t['document_support_number']??''))?><br>Ausgabe: <?=e(!empty($t['document_issue_date'])?fmt_police_date((string)$t['document_issue_date']):'')?> <?=e((string)($t['document_country']??''))?></span></td>
<td><?=e((string)($t['nationality']??''))?><br><span class="muted"><?=e((string)($t['place_of_birth']??''))?> <?=e((string)($t['province']??''))?></span></td>
<td><?=e((string)($t['address']??''))?><br><?=e(trim((string)($t['postal_code']??'').' '.(string)($t['city']??'')))?> · <?=e((string)($t['country']??''))?></td>
<td><?=e((string)($t['relationship_to_primary']??''))?><?php if((int)($t['minor']??0)):?><br><b>Minderjährig</b><?php endif?></td>
<td><?=e((string)($t['signature_status']??'open'))?></td>
<td><?=e(police_status_text((string)$b['police_status']))?><?php if($b['police_sent_at']):?><br><span class="muted"><?=e((new DateTimeImmutable((string)$b['police_sent_at']))->format('d.m.Y H:i'))?></span><?php endif?></td>
</tr>
<?php endforeach?>
</tbody></table>
<?php endif?>
<div class="legal-note">Interne Arbeits- und Prüfliste. Vor einer behördlichen Übermittlung müssen Vollständigkeit, Berechtigung, Registrierung des Betriebs und das aktuell geforderte Übermittlungsformat geprüft werden.</div>
<?php print_footer();
function fmt_police_date(string $d):string{return $d!==''?(new DateTimeImmutable($d))->format('d.m.Y'):'';}
function police_status_text(string $s):string{return ['open'=>'Offen','ready'=>'Bereit','sent'=>'Gemeldet','error'=>'Fehler'][$s]??$s;}
