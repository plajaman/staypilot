<?php
declare(strict_types=1);

/* StayPilot V2.2.3 – Abrechnung, Zahlungsstatus, Kundenbenachrichtigung und WhatsApp-Textübergabe. */

function billing_overview_v223(): never
{
    if (function_exists('ensure_delete_center_v23663')) ensure_delete_center_v23663();
    BookingWorkflowService::refreshOverdueStatuses();
    $q=trim((string)($_GET['q']??''));
    $paymentStatus=trim((string)($_GET['payment_status']??''));
    $from=trim((string)($_GET['from']??''));
    $to=trim((string)($_GET['to']??''));
    $where=["(b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00')", "LOWER(COALESCE(b.status,'')) NOT IN ('cancelled','canceled','storniert','deleted','gelöscht','rejected','abgelehnt','declined','void')", "(g.deleted_at IS NULL OR g.deleted_at='0000-00-00 00:00:00')", "COALESCE(b.accounting_mode,'internal')='internal'"];$params=[];
    if($q!==''){
        $where[]="(b.reference LIKE ? OR g.first_name LIKE ? OR g.last_name LIKE ? OR g.email LIKE ? OR a.code LIKE ? OR a.name LIKE ? OR at.name LIKE ?)";
        $like='%'.$q.'%';$params=array_merge($params,[$like,$like,$like,$like,$like,$like,$like]);
    }
    if($paymentStatus!==''&&in_array($paymentStatus,['open','partial','paid','refunded'],true)){$where[]='b.payment_status=?';$params[]=$paymentStatus;}
    if(valid_date($from)){$where[]='b.departure>=?';$params[]=$from;}
    if(valid_date($to)){$where[]='b.arrival<=?';$params[]=$to;}
    $sql="SELECT b.id,b.reference,b.arrival,b.departure,b.status,b.total_price,b.paid_amount,b.payment_status,b.deposit_amount,b.deposit_status,b.deposit_due_date,b.remaining_status,b.remaining_due_date,b.confirmation_email_sent_at,
        TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,g.email guest_email,g.phone guest_phone,
        at.name apartment_type_name,a.code apartment_code,a.name apartment_name,o.currency,
        COALESCE((SELECT SUM(GREATEST(s.amount-s.paid_amount,0)) FROM booking_payment_schedule s WHERE s.booking_id=b.id AND s.status NOT IN ('received','waived')),GREATEST(b.total_price-b.paid_amount,0)) open_amount,
        (SELECT COUNT(*) FROM booking_payment_schedule s WHERE s.booking_id=b.id AND s.status='overdue') overdue_count,
        (SELECT COUNT(*) FROM booking_documents d WHERE d.booking_id=b.id AND (d.deleted_at IS NULL OR d.deleted_at='0000-00-00 00:00:00')) document_count,
        (SELECT COUNT(*) FROM booking_payments p WHERE p.booking_id=b.id AND p.status='received') payment_count
        FROM bookings b JOIN guests g ON g.id=b.guest_id AND (g.deleted_at IS NULL OR g.deleted_at='0000-00-00 00:00:00')
        LEFT JOIN apartments a ON a.id=b.apartment_id LEFT JOIN apartment_types at ON at.id=b.apartment_type_id LEFT JOIN offers o ON o.id=b.source_offer_id";
    if($where)$sql.=' WHERE '.implode(' AND ',$where);
    $sql.=' ORDER BY b.arrival DESC,b.id DESC LIMIT 250';
    $stmt=db()->prepare($sql);$stmt->execute($params);$bookings=$stmt->fetchAll();
    $stats=BookingWorkflowService::paymentAttention();
    $totalOpen=(float)(db()->query("SELECT COALESCE(SUM(GREATEST(s.amount-s.paid_amount,0)),0) FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id WHERE s.status NOT IN ('received','waived') AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND COALESCE(b.accounting_mode,'internal')='internal'")->fetchColumn()?:0);
    $receivedMonth=(float)(db()->query("SELECT COALESCE(SUM(amount),0) FROM booking_payments p JOIN bookings b ON b.id=p.booking_id JOIN guests g ON g.id=b.guest_id AND (g.deleted_at IS NULL OR g.deleted_at='0000-00-00 00:00:00') WHERE p.status='received' AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND LOWER(COALESCE(b.status,'')) NOT IN ('cancelled','canceled','storniert','deleted','gelöscht','rejected','abgelehnt','declined','void') AND COALESCE(b.accounting_mode,'internal')='internal' AND p.payment_date BETWEEN DATE_FORMAT(CURDATE(),'%Y-%m-01') AND LAST_DAY(CURDATE())")->fetchColumn()?:0);
    json_response(['ok'=>true,'bookings'=>$bookings,'stats'=>['open_amount'=>$totalOpen,'received_month'=>$receivedMonth,'overdue'=>(int)$stats['overdue'],'due_soon'=>(int)$stats['due_soon']]]);
}

function booking_billing_v223(): never
{
    if (function_exists('ensure_delete_center_v23663')) ensure_delete_center_v23663();
    $id=(int)($_GET['id']??0);if($id<=0)throw new ValidationException('Buchung fehlt.');
    BookingWorkflowService::refreshOverdueStatuses();
    $booking=v223_booking_row($id);
    $s=db()->prepare('SELECT * FROM booking_payment_schedule WHERE booking_id=? ORDER BY sort_order,id');$s->execute([$id]);
    $p=db()->prepare("SELECT p.*,(SELECT GROUP_CONCAT(DISTINCT s.label ORDER BY s.sort_order,s.id SEPARATOR ', ') FROM booking_payment_allocations a LEFT JOIN booking_payment_schedule s ON s.id=a.schedule_id WHERE a.payment_id=p.id) allocation_labels FROM booking_payments p WHERE p.booking_id=? ORDER BY p.payment_date DESC,p.id DESC");$p->execute([$id]);
    $d=db()->prepare("SELECT id,document_type,document_number,language,title,pdf_path,status,generated_at,sent_at,created_at FROM booking_documents WHERE booking_id=? AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') ORDER BY id DESC");$d->execute([$id]);
    $accessStmt=db()->prepare('SELECT active,valid_until,token_encrypted,last_viewed_at FROM booking_customer_access WHERE booking_id=? LIMIT 1');$accessStmt->execute([$id]);$access=$accessStmt->fetch()?:null;$customerUrl='';
    if($access){$token=Crypto::decrypt($access['token_encrypted']??null);if($token!=='')$customerUrl=HousekeepingWorkflow::applicationUrl('kunde.php?token='.rawurlencode($token));}
    json_response(['ok'=>true,'booking'=>$booking,'schedules'=>$s->fetchAll(),'payments'=>$p->fetchAll(),'documents'=>$d->fetchAll(),'customer_access'=>$access,'customer_url'=>$customerUrl]);
}

function save_booking_payment_v223(): never
{
    $d=request_data();$bookingId=(int)($d['booking_id']??0);if($bookingId<=0)throw new ValidationException('Buchung fehlt.');
    BookingWorkflowService::syncBillingState($bookingId);
    $booking=v223_booking_row($bookingId);$amount=round((float)str_replace(',','.',(string)($d['amount']??0)),2);if($amount<=0)throw new ValidationException('Der Zahlungsbetrag muss größer als 0 sein.');
    $date=trim((string)($d['payment_date']??date('Y-m-d')));if(!valid_date($date))throw new ValidationException('Das Zahlungsdatum ist ungültig.');
    $method=trim((string)($d['payment_method']??'bank_transfer'));if($method==='')$method='bank_transfer';
    $reference=mb_substr(trim((string)($d['reference']??'')),0,190);$note=mb_substr(trim((string)($d['note']??'')),0,1000);$scheduleChoice=trim((string)($d['schedule_id']??''));
    $notify=normalize_bool($d['notify_customer']??1);
    $paymentNumber=v223_next_number('payment','PAY');
    db()->beginTransaction();
    try{
        $scheduleId=v223_resolve_payment_schedule_choice($bookingId,$amount,$scheduleChoice);
        db()->prepare('INSERT INTO booking_payments(booking_id,payment_number,payment_date,amount,payment_method,reference,note,status,created_by) VALUES(?,?,?,?,?,?,?,?,?)')
            ->execute([$bookingId,$paymentNumber,$date,$amount,$method,$reference?:null,$note?:null,'received',Auth::user()['id']??null]);
        $paymentId=(int)db()->lastInsertId();
        v223_allocate_payment($paymentId,$bookingId,$amount,$scheduleId>0?$scheduleId:null);
        v223_recalculate_booking_payment_status($bookingId);
        db()->prepare('INSERT INTO booking_change_log(booking_id,action,new_values_json,note,created_by) VALUES(?,?,?,?,?)')
            ->execute([$bookingId,'payment_received',json_encode(['payment_id'=>$paymentId,'amount'=>$amount,'payment_number'=>$paymentNumber,'schedule_id'=>$scheduleId?:null],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'Zahlung erfasst: '.$paymentNumber,Auth::user()['id']??null]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    AuditLogger::record('booking',$bookingId,'payment_received',null,['payment_number'=>$paymentNumber,'amount'=>$amount],'Zahlung erfasst');
    $mail=$notify?v223_notify_customer($bookingId,'payment','Zahlung erhalten: '.v223_money($amount,(string)($booking['currency']??'EUR')),[]):['sent'=>false,'message'=>'Benachrichtigung deaktiviert.'];
    json_response(['ok'=>true,'message'=>'Zahlung gespeichert.'.($mail['sent']?' Der Kunde wurde per E-Mail informiert.':' Kunden-E-Mail nicht bestätigt: '.$mail['message']),'payment_number'=>$paymentNumber,'email'=>$mail]);
}

function update_payment_schedule_v223(): never
{
    $d=request_data();$id=(int)($d['id']??($d['schedule_id']??0));if($id<=0)throw new ValidationException('Zahlungsziel fehlt.');
    $stmt=db()->prepare('SELECT * FROM booking_payment_schedule WHERE id=? LIMIT 1');$stmt->execute([$id]);$old=$stmt->fetch();if(!$old)throw new NotFoundException('Zahlungsziel nicht gefunden.');
    $amount=round((float)str_replace(',','.',(string)($d['amount']??$old['amount'])),2);if($amount<0)throw new ValidationException('Der Betrag darf nicht negativ sein.');
    $paid=round((float)str_replace(',','.',(string)($d['paid_amount']??$old['paid_amount'])),2);if($paid<0)$paid=0;if($paid>$amount)$paid=$amount;
    $due=trim((string)($d['due_date']??''));$due=valid_date($due)?$due:null;
    $status=trim((string)($d['status']??$old['status']));if(!in_array($status,['open','partial','received','overdue','waived'],true))$status='open';
    $waivedReason=mb_substr(trim((string)($d['waived_reason']??'')),0,500);
    if($status==='received')$paid=$amount;
    if($status==='partial'&&$paid<=0)$status='open';
    if($status==='open')$paid=0;
    if($status==='waived'){$paid=0;if($waivedReason==='')$waivedReason='Intern erlassen / nicht erforderlich.';}
    db()->beginTransaction();
    try{
        db()->prepare('UPDATE booking_payment_schedule SET amount=?,due_date=?,status=?,paid_amount=?,waived_reason=? WHERE id=?')
            ->execute([$amount,$due,$status,$paid,$waivedReason?:null,$id]);
        v223_recalculate_booking_payment_status((int)$old['booking_id']);
        db()->prepare('INSERT INTO booking_change_log(booking_id,action,old_values_json,new_values_json,note,created_by) VALUES(?,?,?,?,?,?)')
            ->execute([(int)$old['booking_id'],'payment_schedule_updated',json_encode($old,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode(['id'=>$id,'amount'=>$amount,'due_date'=>$due,'status'=>$status,'paid_amount'=>$paid],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'Zahlungsziel geändert',Auth::user()['id']??null]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    AuditLogger::record('booking_payment_schedule',$id,'update',$old,['amount'=>$amount,'due_date'=>$due,'status'=>$status,'paid_amount'=>$paid],'Zahlungsziel geändert');
    $mail=normalize_bool($d['notify_customer']??1)?v223_notify_customer((int)$old['booking_id'],'schedule','Zahlungsstatus wurde aktualisiert.',[]):['sent'=>false,'message'=>'Benachrichtigung deaktiviert.'];
    json_response(['ok'=>true,'message'=>'Zahlungsziel gespeichert.'.($mail['sent']?' Der Kunde wurde per E-Mail informiert.':' Kunden-E-Mail nicht bestätigt: '.$mail['message']),'email'=>$mail]);
}

function create_invoice_v223(): never
{
    $d=request_data();$bookingId=(int)($d['booking_id']??0);if($bookingId<=0)throw new ValidationException('Buchung fehlt.');
    $send=normalize_bool($d['send_email']??1);$booking=v223_booking_row($bookingId);$currency=(string)($booking['currency']??setting('currency','EUR'));
    $type=(string)($d['document_type']??'invoice');if(!in_array($type,['invoice','payment_overview','receipt'],true))$type='invoice';
    $prefix=match($type){'receipt'=>'QU','payment_overview'=>'ZA',default=>'RE'};
    $number=v223_next_number($type,$prefix);$title=mb_substr(trim((string)($d['title']??'')),0,190);if($title==='')$title=($type==='receipt'?'Quittung':($type==='payment_overview'?'Zahlungsübersicht':'Rechnung')).' '.$booking['reference'];
    $note=mb_substr(trim((string)($d['note']??'')),0,2000);
    $s=db()->prepare('SELECT * FROM booking_payment_schedule WHERE booking_id=? ORDER BY sort_order,id');$s->execute([$bookingId]);$schedules=$s->fetchAll();
    $p=db()->prepare("SELECT * FROM booking_payments WHERE booking_id=? AND status='received' ORDER BY payment_date,id");$p->execute([$bookingId]);$payments=$p->fetchAll();
    $rows='';foreach($schedules as $row)$rows.='<tr><td>'.e((string)$row['label']).'</td><td>'.e((string)$row['due_date']).'</td><td>'.e(v223_money((float)$row['amount'],$currency)).'</td><td>'.e(v223_money((float)$row['paid_amount'],$currency)).'</td><td>'.e((string)$row['status']).'</td></tr>';
    $payRows='';foreach($payments as $row)$payRows.='<tr><td>'.e((string)$row['payment_date']).'</td><td>'.e((string)$row['payment_number']).'</td><td>'.e(v223_money((float)$row['amount'],$currency)).'</td><td>'.e((string)$row['payment_method']).'</td></tr>';
    $html='<!doctype html><html lang="de"><head><meta charset="utf-8"><title>'.e($title).'</title><style>body{font-family:Arial,sans-serif;color:#172033}main{max-width:900px;margin:auto}.box{border:1px solid #dce5f0;border-radius:12px;padding:16px;margin:14px 0}table{border-collapse:collapse;width:100%}td,th{border-bottom:1px solid #e5edf5;padding:8px;text-align:left}.total{font-size:20px;font-weight:bold}</style></head><body><main><h1>'.e($title).'</h1><p><b>Gast:</b> '.e((string)$booking['guest_name']).'<br><b>Aufenthalt:</b> '.e((string)$booking['arrival']).' – '.e((string)$booking['departure']).'<br><b>Wohnungstyp:</b> '.e((string)($booking['apartment_type_name']??'')).'</p><div class="box total">Gesamtpreis: '.e(v223_money((float)$booking['total_price'],$currency)).' · erhalten: '.e(v223_money((float)$booking['paid_amount'],$currency)).' · offen: '.e(v223_money(max(0,(float)$booking['total_price']-(float)$booking['paid_amount']),$currency)).'</div><h2>Zahlungsziele</h2><table><thead><tr><th>Position</th><th>Fällig</th><th>Betrag</th><th>Erhalten</th><th>Status</th></tr></thead><tbody>'.$rows.'</tbody></table><h2>Zahlungen</h2><table><thead><tr><th>Datum</th><th>Nummer</th><th>Betrag</th><th>Art</th></tr></thead><tbody>'.($payRows?:'<tr><td colspan="4">Noch keine Zahlungen erfasst.</td></tr>').'</tbody></table>'.($note!==''?'<div class="box"><b>Hinweis:</b><br>'.nl2br(e($note)).'</div>':'').'<p>Dieses Dokument wurde automatisch aus dem aktuellen StayPilot-Zahlungsstand erzeugt.</p></main></body></html>';
    $pdfPath='';
    db()->beginTransaction();
    try{
        db()->prepare('INSERT INTO booking_documents(booking_id,document_type,document_number,language,title,html_snapshot,status,created_by) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$bookingId,$type,$number,'de',$title,$html,'generated',Auth::user()['id']??null]);
        $docId=(int)db()->lastInsertId();
        $lines=[['text'=>$title,'size'=>14,'bold'=>true,'space'=>4],'Buchung: '.$booking['reference'],'Gast: '.$booking['guest_name'],'Aufenthalt: '.$booking['arrival'].' – '.$booking['departure'],['text'=>'','space'=>4],['text'=>'Gesamtpreis: '.v223_money((float)$booking['total_price'],$currency),'bold'=>true],'Erhalten: '.v223_money((float)$booking['paid_amount'],$currency),'Offen: '.v223_money(max(0,(float)$booking['total_price']-(float)$booking['paid_amount']),$currency),['text'=>'','space'=>4],['text'=>'Zahlungsziele','bold'=>true]];
        foreach($schedules as $row)$lines[]=$row['label'].': '.v223_money((float)$row['amount'],$currency).' · Status '.$row['status'].' · fällig '.($row['due_date']?:'–');
        if($note!==''){$lines[]=['text'=>'','space'=>4];$lines[]=['text'=>'Hinweis','bold'=>true];$lines[]=$note;}
        $pdf=SimplePdf::create($title,$lines);$safe=preg_replace('/[^A-Za-z0-9_-]/','-',$number)?:'invoice';$pdfPath='storage/documents/booking-confirmations/'.$safe.'.pdf';
        if(@file_put_contents(root_path($pdfPath),$pdf,LOCK_EX)===false)throw new RuntimeException('Das Rechnungs-PDF konnte nicht gespeichert werden.');
        db()->prepare('UPDATE booking_documents SET pdf_path=?,checksum_sha256=? WHERE id=?')->execute([$pdfPath,hash_file('sha256',root_path($pdfPath))?:null,$docId]);
        db()->prepare('INSERT INTO booking_change_log(booking_id,action,new_values_json,note,created_by) VALUES(?,?,?,?,?)')
            ->execute([$bookingId,'invoice_created',json_encode(['document_id'=>$docId,'number'=>$number],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'Rechnungs-/Zahlungsdokument erzeugt',Auth::user()['id']??null]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    $mail=$send?v223_notify_customer($bookingId,'invoice','Ein neues Rechnungs-/Zahlungsdokument steht im Kundenbereich bereit.',['attachment'=>$pdfPath,'attachment_name'=>$number.'.pdf']):['sent'=>false,'message'=>'Benachrichtigung deaktiviert.'];
    json_response(['ok'=>true,'message'=>'Rechnungs-/Zahlungsdokument erzeugt.'.($mail['sent']?' Der Kunde wurde per E-Mail informiert.':' Kunden-E-Mail nicht bestätigt: '.$mail['message']),'document_number'=>$number,'email'=>$mail]);
}

function resend_customer_status_v223(): never
{
    $d=request_data();$bookingId=(int)($d['booking_id']??0);if($bookingId<=0)throw new ValidationException('Buchung fehlt.');
    $mail=v223_notify_customer($bookingId,'status','Der aktuelle Status Ihrer Buchung wurde aktualisiert.',[]);
    json_response(['ok'=>true,'message'=>$mail['sent']?'Kundenstatus wurde per E-Mail gesendet.':'Kunden-E-Mail nicht bestätigt: '.$mail['message'],'email'=>$mail]);
}

function whatsapp_booking_open_v223(): never
{
    $d=request_data();$bookingId=(int)($d['booking_id']??0);if($bookingId<=0)throw new ValidationException('Buchung fehlt.');
    $booking=v223_booking_row($bookingId);$phone=trim((string)($booking['guest_phone']??''));$normalized=v223_phone($phone);if($normalized==='')throw new ValidationException('Für diesen Gast ist keine gültige WhatsApp-/Telefonnummer mit Landesvorwahl hinterlegt.');
    $url=v223_customer_url($bookingId);$message=trim((string)($d['message']??''));
    if($message==='')$message="Guten Tag ".$booking['guest_name'].",\n\nder aktuelle Status Ihrer Buchung ".$booking['reference']." wurde aktualisiert.\n\nKundenbereich: ".$url."\n\nMit freundlichen Grüßen\n".(string)setting('property_name','StayPilot');
    $wa='https://api.whatsapp.com/send?phone='.$normalized.'&text='.rawurlencode($message);
    CommunicationLogger::record('whatsapp','booking',$bookingId,(string)$booking['guest_name'],$phone,'Buchungsstatus',$message,'opened','WhatsApp-Link mit vorbereitetem Text geöffnet; Versand erfolgt manuell in WhatsApp.');
    json_response(['ok'=>true,'url'=>$wa,'message'=>'WhatsApp wurde mit vorbereitetem Text geöffnet. Der tatsächliche Versand erfolgt in WhatsApp.']);
}

function v223_booking_row(int $bookingId): array
{
    $stmt=db()->prepare("SELECT b.*,TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,g.email guest_email,g.phone guest_phone,g.country guest_country,
        at.name apartment_type_name,a.name apartment_name,a.code apartment_code,o.currency
        FROM bookings b JOIN guests g ON g.id=b.guest_id AND (g.deleted_at IS NULL OR g.deleted_at='0000-00-00 00:00:00') LEFT JOIN apartment_types at ON at.id=b.apartment_type_id LEFT JOIN apartments a ON a.id=b.apartment_id LEFT JOIN offers o ON o.id=b.source_offer_id WHERE b.id=? AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') LIMIT 1");
    $stmt->execute([$bookingId]);$row=$stmt->fetch();if(!$row)throw new NotFoundException('Buchung nicht gefunden.');return $row;
}

function v223_next_number(string $type,string $prefix): string
{
    $year=(int)date('Y');$pdo=db();$pdo->prepare('INSERT INTO document_sequences(document_type,document_year,current_value) VALUES(?,?,0) ON DUPLICATE KEY UPDATE current_value=current_value')->execute([$type,$year]);$pdo->prepare('UPDATE document_sequences SET current_value=LAST_INSERT_ID(current_value+1) WHERE document_type=? AND document_year=?')->execute([$type,$year]);$value=(int)$pdo->lastInsertId();return $prefix.'-'.$year.'-'.str_pad((string)$value,4,'0',STR_PAD_LEFT);
}

function v223_allocate_payment(int $paymentId,int $bookingId,float $amount,?int $preferredScheduleId=null): void
{
    $remaining=$amount;$params=[$bookingId];$order='sort_order,id';if($preferredScheduleId!==null){$order='CASE WHEN id=? THEN 0 ELSE 1 END,sort_order,id';$params[]=$preferredScheduleId;}
    $stmt=db()->prepare("SELECT * FROM booking_payment_schedule WHERE booking_id=? AND status NOT IN ('received','waived') ORDER BY ".$order." FOR UPDATE");$stmt->execute($params);
    foreach($stmt->fetchAll() as $schedule){if($remaining<=0)break;$open=max(0,(float)$schedule['amount']-(float)$schedule['paid_amount']);if($open<=0)continue;$apply=min($open,$remaining);$newPaid=(float)$schedule['paid_amount']+$apply;$newStatus=$newPaid+0.005>=(float)$schedule['amount']?'received':'partial';db()->prepare('UPDATE booking_payment_schedule SET paid_amount=?,status=? WHERE id=?')->execute([$newPaid,$newStatus,$schedule['id']]);db()->prepare('INSERT INTO booking_payment_allocations(payment_id,schedule_id,amount) VALUES(?,?,?)')->execute([$paymentId,$schedule['id'],$apply]);$remaining-=$apply;}
}

function v223_resolve_payment_schedule_choice(int $bookingId,float $amount,string $choice): ?int
{
    $choice=trim($choice);
    if($choice==='')return null;
    if(ctype_digit($choice)){
        $id=(int)$choice;
        if($id<=0)return null;
        $stmt=db()->prepare('SELECT id FROM booking_payment_schedule WHERE id=? AND booking_id=? LIMIT 1');
        $stmt->execute([$id,$bookingId]);
        if(!$stmt->fetchColumn())throw new ValidationException('Das gewählte Zahlungsziel gehört nicht zu dieser Buchung.');
        return $id;
    }
    if(!in_array($choice,['deposit_new','remaining_new'],true))return null;
    $type=$choice==='deposit_new'?'deposit':'remaining';
    $existing=db()->prepare("SELECT * FROM booking_payment_schedule WHERE booking_id=? AND installment_type=? LIMIT 1");
    $existing->execute([$bookingId,$type]);
    $existingRow=$existing->fetch();
    if($existingRow){
        $existingId=(int)$existingRow['id'];
        $open=max(0.0,round((float)$existingRow['amount']-(float)$existingRow['paid_amount'],2));
        if($open<=0.005 || in_array((string)$existingRow['status'],['received','waived'],true)){
            $targetAmount=max(0.01,round($amount,2));
            $newAmount=round((float)$existingRow['amount']+$targetAmount,2);
            $paid=max(0.0,(float)$existingRow['paid_amount']);
            $newStatus=$paid+0.005>=$newAmount?'received':($paid>0.005?'partial':'open');
            db()->prepare('UPDATE booking_payment_schedule SET amount=?,status=?,waived_reason=NULL WHERE id=?')->execute([$newAmount,$newStatus,$existingId]);
            if($type==='deposit')v223_reduce_remaining_for_deposit($bookingId,$targetAmount);
        }
        return $existingId;
    }

    $booking=v223_booking_row($bookingId);
    $total=max(0.0,round((float)($booking['total_price']??0),2));
    $targetAmount=max(0.01,min(round($amount,2),$total>0?$total:$amount));
    $today=date('Y-m-d');
    $due=$type==='deposit'
        ? (valid_date((string)($booking['deposit_due_date']??''))?(string)$booking['deposit_due_date']:$today)
        : (valid_date((string)($booking['remaining_due_date']??''))?(string)$booking['remaining_due_date']:(valid_date((string)($booking['arrival']??''))?(new DateTimeImmutable((string)$booking['arrival']))->modify('-14 days')->format('Y-m-d'):$today));
    if($due<$today)$status='overdue';else $status='open';
    $label=$type==='deposit'?'Anzahlung':'Restbetrag';
    $sort=$type==='deposit'?10:20;
    db()->prepare('INSERT INTO booking_payment_schedule(booking_id,installment_type,label,amount,due_date,status,paid_amount,sort_order) VALUES(?,?,?,?,?,?,0,?)')
        ->execute([$bookingId,$type,$label,$targetAmount,$due,$status,$sort]);
    $newId=(int)db()->lastInsertId();
    if($type==='deposit'){
        db()->prepare('UPDATE bookings SET deposit_amount=GREATEST(COALESCE(deposit_amount,0),?) WHERE id=?')->execute([$targetAmount,$bookingId]);
        v223_reduce_remaining_for_deposit($bookingId,$targetAmount);
    }
    return $newId;
}

function v223_reduce_remaining_for_deposit(int $bookingId,float $amount): void
{
    if($amount<=0.005)return;
    $remaining=db()->prepare("SELECT id,amount,paid_amount,due_date FROM booking_payment_schedule WHERE booking_id=? AND installment_type='remaining' LIMIT 1");
    $remaining->execute([$bookingId]);
    $row=$remaining->fetch();
    if(!$row)return;
    $newRemaining=max(0.0,round((float)$row['amount']-$amount,2));
    $newPaid=min((float)$row['paid_amount'],$newRemaining);
    if($newRemaining<=0.005)$newStatus='received';
    elseif($newPaid>0.005)$newStatus='partial';
    else $newStatus=(!empty($row['due_date']) && (string)$row['due_date']<date('Y-m-d'))?'overdue':'open';
    db()->prepare('UPDATE booking_payment_schedule SET amount=?,paid_amount=?,status=? WHERE id=?')->execute([$newRemaining,$newPaid,$newStatus,(int)$row['id']]);
}

function v223_recalculate_booking_payment_status(int $bookingId): void
{
    BookingWorkflowService::syncBillingState($bookingId);
}

function v223_customer_url(int $bookingId): string
{
    $stmt=db()->prepare('SELECT token_encrypted FROM booking_customer_access WHERE booking_id=? AND active=1 LIMIT 1');$stmt->execute([$bookingId]);$token=Crypto::decrypt($stmt->fetchColumn()?:null);return $token!==''?HousekeepingWorkflow::applicationUrl('kunde.php?token='.rawurlencode($token)):'';
}

function v223_notify_customer(int $bookingId,string $kind,string $message,array $options=[]): array
{
    BookingWorkflowService::syncBillingState($bookingId);
    $booking=v223_booking_row($bookingId);$email=trim((string)($booking['guest_email']??''));if(!filter_var($email,FILTER_VALIDATE_EMAIL))return ['sent'=>false,'message'=>'Keine gültige Kunden-E-Mail-Adresse hinterlegt.'];
    $currency=(string)($booking['currency']??setting('currency','EUR'));$url=v223_customer_url($bookingId);
    $s=db()->prepare('SELECT * FROM booking_payment_schedule WHERE booking_id=? ORDER BY sort_order,id');$s->execute([$bookingId]);$schedule=$s->fetchAll();
    $lines=[];foreach($schedule as $row)$lines[]='- '.$row['label'].': '.v223_money((float)$row['amount'],$currency).', Status '.$row['status'].($row['due_date']?', fällig '.$row['due_date']:'');
    $workflowStatus=function_exists('v235_status_line')?v235_status_line($booking):status_text((string)($booking['status']??''));
    $subject='Aktualisierung zu Ihrer Buchung '.$booking['reference'];
    if($kind==='payment')$subject='Zahlungsbestätigung '.$booking['reference'];
    if($kind==='invoice')$subject='Neues Dokument zu Ihrer Buchung '.$booking['reference'];
    $text="Guten Tag ".$booking['guest_name'].",\n\n".$message."\n\nBuchung: ".$booking['reference']."\nStatus: ".$workflowStatus."\nAufenthalt: ".$booking['arrival']." bis ".$booking['departure']."\nGesamtpreis: ".v223_money((float)$booking['total_price'],$currency)."\nErhalten: ".v223_money((float)$booking['paid_amount'],$currency)."\nOffen: ".v223_money(max(0,(float)$booking['total_price']-(float)$booking['paid_amount']),$currency)."\n\nZahlungsübersicht:\n".implode("\n",$lines).($url!==''?"\n\nIhr Kundenbereich:\n".$url:'')."\n\nMit freundlichen Grüßen\n".(string)setting('property_name','StayPilot');
    $html='<p>Guten Tag '.e((string)$booking['guest_name']).',</p><p>'.nl2br(e($message)).'</p><table style="border-collapse:collapse;width:100%;max-width:680px"><tr><td>Buchung</td><td><b>'.e((string)$booking['reference']).'</b></td></tr><tr><td>Status</td><td><b>'.e($workflowStatus).'</b></td></tr><tr><td>Aufenthalt</td><td>'.e((string)$booking['arrival']).' – '.e((string)$booking['departure']).'</td></tr><tr><td>Gesamtpreis</td><td>'.e(v223_money((float)$booking['total_price'],$currency)).'</td></tr><tr><td>Erhalten</td><td>'.e(v223_money((float)$booking['paid_amount'],$currency)).'</td></tr><tr><td>Offen</td><td><b>'.e(v223_money(max(0,(float)$booking['total_price']-(float)$booking['paid_amount']),$currency)).'</b></td></tr></table><p><b>Zahlungsübersicht</b><br>'.nl2br(e(implode("\n",$lines))).'</p>'.($url!==''?'<p><a href="'.e($url).'">Kundenbereich öffnen</a></p>':'').'<p>Mit freundlichen Grüßen<br>'.e((string)setting('property_name','StayPilot')).'</p>';
    $attachments=[];if(!empty($options['attachment'])&&is_file(root_path((string)$options['attachment'])))$attachments[]=['path'=>root_path((string)$options['attachment']),'name'=>(string)($options['attachment_name']??basename((string)$options['attachment'])),'mime'=>'application/pdf'];
    try{$result=SmtpMailer::send($email,$subject,$text,$html,$attachments);CommunicationLogger::record('email','booking',$bookingId,(string)$booking['guest_name'],$email,$subject,$text,'sent',(string)($result['message_id']??''));return ['sent'=>true,'message'=>'gesendet'];}
    catch(Throwable $e){CommunicationLogger::record('email','booking',$bookingId,(string)$booking['guest_name'],$email,$subject,$text,'failed',$e->getMessage());AppLogger::error($e,['booking_id'=>$bookingId,'kind'=>$kind],'billing-customer-email');return ['sent'=>false,'message'=>$e->getMessage()];}
}

function v223_money(float $value,string $currency='EUR'): string
{
    $symbol=$currency==='EUR'?'€':$currency;return number_format($value,2,',','.').' '.$symbol;
}

function v223_phone(string $phone): string
{
    $n=preg_replace('/[^0-9+]/','',$phone)??'';if(str_starts_with($n,'00'))$n=substr($n,2);$n=ltrim($n,'+');return strlen($n)>=7?$n:'';
}
