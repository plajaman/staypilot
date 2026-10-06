<?php
declare(strict_types=1);
require_once __DIR__.'/_print_common.php';
$requestedFrom=(string)($_GET['from']??date('Y-m-d'));
$to=(string)($_GET['to']??$requestedFrom);
$type=(string)($_GET['type']??'all');
$showPast=(int)($_GET['show_past']??0)===1;
$includeUnassigned=(int)($_GET['include_unassigned']??0)===1;
if(!in_array($type,['all','breakfast','half_board'],true))$type='all';
if(!valid_date($requestedFrom)||!valid_date($to)||$requestedFrom>$to){http_response_code(400);exit('Ungültiger Zeitraum.');}
$from=$requestedFrom;
if(!$showPast && $from<date('Y-m-d'))$from=date('Y-m-d');
if($from>$to)$from=$to;
$end=(new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
$stmt=db()->prepare(booking_select_print()." WHERE b.status NOT IN ('cancelled','rejected') AND b.arrival<? AND b.departure>? ORDER BY a.name,guest_name");
$stmt->execute([$end,$from]);
$raw=$stmt->fetchAll();
$bookings=[];
foreach($raw as $b){
    if(!$includeUnassigned && empty($b['apartment_id']))continue;
    $has=false;
    for($d=new DateTimeImmutable($from),$last=new DateTimeImmutable($to);$d<=$last;$d=$d->modify('+1 day')){
        $date=$d->format('Y-m-d');
        if(has_bf($b,$date)||has_hp($b,$date)){$has=true;break;}
    }
    if($has)$bookings[]=$b;
}
$title=$type==='breakfast'?'Frühstücksliste':($type==='half_board'?'Halbpensionsliste':'Frühstücks- und Halbpensionslisten');
$sub=fmt_date_meal($from).' bis '.fmt_date_meal($to).(!$showPast&&$requestedFrom<$from?' · vergangene Tage ausgeblendet':'');
print_header($title,$sub);
?>
<div class="print-note">Gedruckt wird exakt der ausgewählte Zeitraum<?=!$includeUnassigned?' ohne nicht zugeordnete Buchungen':''?>.</div>
<?php
for($d=new DateTimeImmutable($from),$last=new DateTimeImmutable($to);$d<=$last;$d=$d->modify('+1 day')){$date=$d->format('Y-m-d');$bf=[];$hp=[];foreach($bookings as $b){if(has_bf($b,$date))$bf[]=$b;if(has_hp($b,$date))$hp[]=$b;}?>
<h2 class="date-title"><?=e(fmt_date_meal($date))?></h2>
<?php if($type!=='half_board'): meal_section('☕ Frühstück',$bf); endif;?>
<?php if($type!=='breakfast'): meal_section('🍽 Halbpension',$hp); endif;?>
<?php } print_footer();
function booking_select_print():string{return "SELECT b.*,CONCAT(g.first_name,' ',g.last_name) guest_name,g.phone,g.email,a.name apartment_name,a.code apartment_code FROM bookings b JOIN guests g ON g.id=b.guest_id LEFT JOIN apartments a ON a.id=b.apartment_id";}
function fmt_date_meal(string $d):string{return (new DateTimeImmutable($d))->format('d.m.Y');}
function bf_start(array $b):string{return (string)($b['breakfast_start_date']?:date('Y-m-d',strtotime($b['arrival'].' +1 day')));} 
function bf_end(array $b):string{return (string)($b['breakfast_end_date']?:$b['departure']);}
function hp_start(array $b):string{return (string)($b['half_board_start_date']?:$b['arrival']);}
function hp_end(array $b):string{return (string)($b['half_board_end_date']?:date('Y-m-d',strtotime($b['departure'].' -1 day')));} 
function has_bf(array $b,string $date):bool{if(!(int)$b['breakfast'])return false;return $date>=bf_start($b)&&$date<=bf_end($b);} 
function has_hp(array $b,string $date):bool{if(!(int)$b['half_board'])return false;return $date>=hp_start($b)&&$date<=hp_end($b);} 
function meal_section(string $title,array $rows):void{$ad=array_sum(array_map(fn($r)=>(int)$r['adults'],$rows));$ch=array_sum(array_map(fn($r)=>(int)$r['children'],$rows));?><section class="section"><h3><?=$title?> · <?=$ad?> Erwachsene · <?=$ch?> Kinder · <?=count($rows)?> Buchungen</h3><?php if(!$rows):?><div class="no-data">Keine Einträge.</div><?php else:?><table><thead><tr><th>Wohnung</th><th>Gast</th><th>Erw.</th><th>Kinder</th><th>Babys</th><th>Hinweise / Wünsche</th><th>Erledigt</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><b><?=e(($r['apartment_code']?:'⚠').' · '.($r['apartment_name']?:'Nicht zugeordnet'))?></b></td><td><?=e($r['guest_name'])?><br><span class="muted"><?=e((string)$r['phone'])?></span></td><td><?=(int)$r['adults']?></td><td><?=(int)$r['children']?></td><td><?=(int)$r['babies']?></td><td><?=nl2br(e(trim((string)$r['guest_request']."\n".(string)$r['notes'])))?></td><td>☐</td></tr><?php endforeach?></tbody></table><?php endif?></section><?php }
