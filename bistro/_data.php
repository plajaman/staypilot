<?php
declare(strict_types=1);

function bistro_valid_date(string $date): bool { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $date); }
function bistro_bool(mixed $v): bool { return in_array((string)$v, ['1','true','on','yes'], true); }
function bistro_breakfast_start(array $b): string { return (string)($b['breakfast_start_date'] ?: date('Y-m-d', strtotime((string)$b['arrival'].' +1 day'))); }
function bistro_breakfast_end(array $b): string { return (string)($b['breakfast_end_date'] ?: $b['departure']); }
function bistro_half_start(array $b): string { return (string)($b['half_board_start_date'] ?: $b['arrival']); }
function bistro_half_end(array $b): string { return (string)($b['half_board_end_date'] ?: date('Y-m-d', strtotime((string)$b['departure'].' -1 day'))); }
function bistro_has_breakfast(array $b,string $date): bool { return (int)($b['breakfast']??0)===1 && $date>=bistro_breakfast_start($b) && $date<=bistro_breakfast_end($b); }
function bistro_has_half(array $b,string $date): bool { return (int)($b['half_board']??0)===1 && $date>=bistro_half_start($b) && $date<=bistro_half_end($b); }
function bistro_status_label(string $status): string {
    return ['planned'=>'offen','prepared'=>'vorbereitet','served'=>'ausgegeben','done'=>'erledigt'][$status] ?? $status;
}
function bistro_status_step(string $status): int {
    return ['planned'=>0,'prepared'=>1,'served'=>2,'done'=>3][$status] ?? 0;
}
function bistro_booking_sql(): string {
    return "SELECT b.*,TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,
        g.phone guest_phone,g.email guest_email,g.category_id guest_category_id,gc.name guest_category_name,gc.color guest_category_color,
        a.name apartment_name,a.code apartment_code,h.name house_name,
        COALESCE(at.name,aat.name) apartment_type_name
        FROM bookings b JOIN guests g ON g.id=b.guest_id
        LEFT JOIN guest_categories gc ON gc.id=g.category_id
        LEFT JOIN apartments a ON a.id=b.apartment_id
        LEFT JOIN houses h ON h.id=a.house_id
        LEFT JOIN apartment_types at ON at.id=b.apartment_type_id
        LEFT JOIN apartment_types aat ON aat.id=a.apartment_type_id";
}
function bistro_fetch_order_map(string $from,string $to): array {
    try {
        $stmt=db()->prepare("SELECT * FROM meal_orders WHERE service_date BETWEEN ? AND ? ORDER BY id ASC");
        $stmt->execute([$from,$to]);
        $map=[];
        foreach($stmt->fetchAll() as $row){
            $key=(int)($row['booking_id']??0).'|'.(string)$row['service_date'].'|'.(string)$row['meal_type'];
            $map[$key]=$row;
        }
        return $map;
    } catch(Throwable $e) {
        return [];
    }
}
function bistro_attach_status(array $booking,string $date,string $mealType,array $orders): array {
    $key=(int)$booking['id'].'|'.$date.'|'.$mealType;
    $order=$orders[$key] ?? null;
    $status=(string)($order['status'] ?? 'planned');
    $booking['_meal']=[
        'type'=>$mealType,
        'date'=>$date,
        'status'=>$status,
        'status_label'=>bistro_status_label($status),
        'status_step'=>bistro_status_step($status),
        'order_id'=>$order ? (int)$order['id'] : 0,
        'service_key'=>(int)$booking['id'].'-'.$date.'-'.$mealType,
    ];
    return $booking;
}
function bistro_fetch_meals(array $filters): array {
    $requestedFrom=(string)($filters['from'] ?? date('Y-m-d'));
    $to=(string)($filters['to'] ?? date('Y-m-d', strtotime($requestedFrom.' +7 days')));
    $showPast=bistro_bool($filters['show_past'] ?? '0');
    $includeUnassigned=bistro_bool($filters['include_unassigned'] ?? '0');
    $service=(string)($filters['service'] ?? 'all');
    if(!in_array($service,['all','breakfast','half_board'],true)) $service='all';
    if(!bistro_valid_date($requestedFrom)) $requestedFrom=date('Y-m-d');
    if(!bistro_valid_date($to)||$to<$requestedFrom) $to=$requestedFrom;
    $from=$requestedFrom;
    if(!$showPast && $from<date('Y-m-d')) $from=date('Y-m-d');
    if($from>$to) $from=$to;
    $maxTo=(new DateTimeImmutable($from))->modify('+62 days')->format('Y-m-d');
    if($to>$maxTo) $to=$maxTo;
    $queryEnd=(new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
    $stmt=db()->prepare(bistro_booking_sql()." WHERE b.status NOT IN ('cancelled','rejected') AND b.arrival<? AND b.departure>? ORDER BY h.name,a.name,guest_name");
    $stmt->execute([$queryEnd,$from]);
    $raw=$stmt->fetchAll();
    $orders=bistro_fetch_order_map($from,$to);
    $days=[]; $warnings=[]; $hiddenUnassigned=0; $hiddenNoMeals=0;
    $overall=['breakfast_adults'=>0,'breakfast_children'=>0,'half_adults'=>0,'half_children'=>0,'breakfast_total'=>0,'half_total'=>0,'open'=>0,'prepared'=>0,'served'=>0,'done'=>0];
    for($day=new DateTimeImmutable($from),$end=new DateTimeImmutable($to);$day<=$end;$day=$day->modify('+1 day')){
        $date=$day->format('Y-m-d');
        $breakfast=[]; $half=[]; $summary=['breakfast_adults'=>0,'breakfast_children'=>0,'half_adults'=>0,'half_children'=>0,'breakfast_total'=>0,'half_total'=>0,'open'=>0,'prepared'=>0,'served'=>0,'done'=>0];
        foreach($raw as $b){
            if(!$includeUnassigned && empty($b['apartment_id'])) continue;
            if(($service==='all'||$service==='breakfast') && bistro_has_breakfast($b,$date)) {
                $row=bistro_attach_status($b,$date,'breakfast',$orders);
                $breakfast[]=$row; $summary['breakfast_adults']+=(int)$b['adults']; $summary['breakfast_children']+=(int)$b['children']; $summary['breakfast_total']+=(int)$b['adults']+(int)$b['children'];
                $status=(string)$row['_meal']['status']; $summary[$status==='done'?'done':($status==='served'?'served':($status==='prepared'?'prepared':'open'))]++;
            }
            if(($service==='all'||$service==='half_board') && bistro_has_half($b,$date)) {
                $row=bistro_attach_status($b,$date,'half_board',$orders);
                $half[]=$row; $summary['half_adults']+=(int)$b['adults']; $summary['half_children']+=(int)$b['children']; $summary['half_total']+=(int)$b['adults']+(int)$b['children'];
                $status=(string)$row['_meal']['status']; $summary[$status==='done'?'done':($status==='served'?'served':($status==='prepared'?'prepared':'open'))]++;
            }
        }
        foreach($summary as $k=>$v){ if(isset($overall[$k])) $overall[$k]+=$v; }
        $days[]=['date'=>$date,'weekday'=>['So','Mo','Di','Mi','Do','Fr','Sa'][(int)$day->format('w')],'is_today'=>$date===date('Y-m-d'),'breakfast'=>$breakfast,'half_board'=>$half,'summary'=>$summary];
    }
    foreach($raw as $b){
        $has=false; for($day=new DateTimeImmutable($from),$end=new DateTimeImmutable($to);$day<=$end;$day=$day->modify('+1 day')){ $d=$day->format('Y-m-d'); if(bistro_has_breakfast($b,$d)||bistro_has_half($b,$d)){ $has=true; break; }}
        if(!$has) $hiddenNoMeals++;
        elseif(empty($b['apartment_id'])) $hiddenUnassigned++;
    }
    if(!$showPast && $requestedFrom<$from) $warnings[]='Vergangene Tage wurden ausgeblendet.';
    if(!$includeUnassigned && $hiddenUnassigned>0) $warnings[]=$hiddenUnassigned.' nicht zugeordnete Buchung(en) mit Leistung wurden ausgeblendet.';
    return ['from'=>$from,'to'=>$to,'requested_from'=>$requestedFrom,'show_past'=>$showPast,'include_unassigned'=>$includeUnassigned,'service'=>$service,'days'=>$days,'summary'=>$overall,'warnings'=>$warnings];
}
function bistro_set_meal_status(int $bookingId,string $date,string $mealType,string $status,?string $note=''): array {
    if($bookingId<=0 || !bistro_valid_date($date) || !in_array($mealType,['breakfast','half_board'],true) || !in_array($status,['planned','prepared','served','done'],true)) {
        throw new RuntimeException('Ungültige Bistro-Angaben.');
    }
    $pdo=db();
    $check=$pdo->prepare('SELECT id FROM meal_orders WHERE booking_id=? AND service_date=? AND meal_type=? ORDER BY id DESC LIMIT 1');
    $check->execute([$bookingId,$date,$mealType]);
    $id=(int)($check->fetchColumn() ?: 0);
    $booking=$pdo->prepare('SELECT adults,children FROM bookings WHERE id=?');
    $booking->execute([$bookingId]);
    $b=$booking->fetch();
    if(!$b) throw new RuntimeException('Buchung nicht gefunden.');
    if($id>0){
        $stmt=$pdo->prepare('UPDATE meal_orders SET status=?, notes=COALESCE(NULLIF(?,\'\'), notes), adults=?, children=?, updated_at=NOW() WHERE id=?');
        $stmt->execute([$status,(string)$note,(int)$b['adults'],(int)$b['children'],$id]);
    } else {
        $stmt=$pdo->prepare('INSERT INTO meal_orders(booking_id,service_date,meal_type,adults,children,status,notes) VALUES(?,?,?,?,?,?,?)');
        $stmt->execute([$bookingId,$date,$mealType,(int)$b['adults'],(int)$b['children'],$status,(string)$note ?: null]);
        $id=(int)$pdo->lastInsertId();
    }
    return ['id'=>$id,'booking_id'=>$bookingId,'date'=>$date,'meal_type'=>$mealType,'status'=>$status,'status_label'=>bistro_status_label($status),'status_step'=>bistro_status_step($status)];
}
