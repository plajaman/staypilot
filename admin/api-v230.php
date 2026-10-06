<?php
declare(strict_types=1);

/* StayPilot V2.3.0 – Abrechnung/Zahlungen professioneller ausbauen.
 * Baut auf V2.2.3-Abrechnung auf und erweitert vorhandene Tabellen/Funktionen,
 * ohne ein zweites Abrechnungsmodul oder neue Pflichttabellen anzulegen.
 */

function billing_overview_v230(): never
{
    if (function_exists('ensure_delete_center_v23663')) ensure_delete_center_v23663();
    BookingWorkflowService::syncBillingState();
    BookingWorkflowService::refreshOverdueStatuses();
    $q=trim((string)($_GET['q']??''));
    $paymentStatus=trim((string)($_GET['payment_status']??''));
    $from=trim((string)($_GET['from']??''));
    $to=trim((string)($_GET['to']??''));
    $bucket=trim((string)($_GET['bucket']??'all'));
    if(!in_array($bucket,['all','overdue','due_soon','deposit','remaining','paid','documents','no_docs','unsent','no_contact'],true))$bucket='all';

    $where=["(b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00')", "LOWER(COALESCE(b.status,'')) NOT IN ('cancelled','canceled','storniert','deleted','gelöscht','rejected','abgelehnt','declined','void')", "(g.deleted_at IS NULL OR g.deleted_at='0000-00-00 00:00:00')", "COALESCE(b.accounting_mode,'internal')='internal'"];$params=[];
    if($q!==''){
        $where[]="(b.reference LIKE ? OR g.first_name LIKE ? OR g.last_name LIKE ? OR g.email LIKE ? OR a.code LIKE ? OR a.name LIKE ? OR at.name LIKE ?)";
        $like='%'.$q.'%';$params=array_merge($params,[$like,$like,$like,$like,$like,$like,$like]);
    }
    if($paymentStatus!==''&&in_array($paymentStatus,['open','partial','paid','refunded'],true)){$where[]='b.payment_status=?';$params[]=$paymentStatus;}
    if(valid_date($from)){$where[]='b.departure>=?';$params[]=$from;}
    if(valid_date($to)){$where[]='b.arrival<=?';$params[]=$to;}
    if($bucket==='overdue')$where[]="EXISTS(SELECT 1 FROM booking_payment_schedule sx WHERE sx.booking_id=b.id AND sx.status='overdue')";
    if($bucket==='due_soon')$where[]="EXISTS(SELECT 1 FROM booking_payment_schedule sx WHERE sx.booking_id=b.id AND sx.status IN ('open','partial') AND sx.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY))";
    if($bucket==='deposit')$where[]="EXISTS(SELECT 1 FROM booking_payment_schedule sx WHERE sx.booking_id=b.id AND sx.installment_type='deposit' AND sx.status NOT IN ('received','waived'))";
    if($bucket==='remaining')$where[]="EXISTS(SELECT 1 FROM booking_payment_schedule sx WHERE sx.booking_id=b.id AND sx.installment_type='remaining' AND sx.status NOT IN ('received','waived'))";
    if($bucket==='paid')$where[]="b.payment_status='paid'";
    if($bucket==='documents')$where[]="EXISTS(SELECT 1 FROM booking_documents dx WHERE dx.booking_id=b.id)";
    if($bucket==='no_docs')$where[]="NOT EXISTS(SELECT 1 FROM booking_documents dx WHERE dx.booking_id=b.id AND (dx.deleted_at IS NULL OR dx.deleted_at='0000-00-00 00:00:00') AND dx.document_type IN ('invoice','receipt','payment_overview','credit_note','cancellation_statement')) AND b.total_price>0";
    if($bucket==='unsent')$where[]="EXISTS(SELECT 1 FROM booking_documents dx WHERE dx.booking_id=b.id AND (dx.deleted_at IS NULL OR dx.deleted_at='0000-00-00 00:00:00') AND dx.document_type IN ('invoice','receipt','payment_overview','credit_note','cancellation_statement')) AND NOT EXISTS(SELECT 1 FROM booking_documents ds WHERE ds.booking_id=b.id AND ds.sent_at IS NOT NULL AND (ds.deleted_at IS NULL OR ds.deleted_at='0000-00-00 00:00:00') AND ds.document_type IN ('invoice','receipt','payment_overview','credit_note','cancellation_statement'))";
    if($bucket==='no_contact')$where[]="(COALESCE(g.email,'')='' OR g.email NOT LIKE '%_@_%._%')";

    $sql="SELECT b.id,b.reference,b.arrival,b.departure,b.status,b.total_price,b.paid_amount,b.payment_status,b.deposit_amount,b.deposit_status,b.deposit_due_date,b.remaining_status,b.remaining_due_date,b.confirmation_email_sent_at,
        TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,g.email guest_email,g.phone guest_phone,
        at.name apartment_type_name,a.code apartment_code,a.name apartment_name,o.currency,
        COALESCE((SELECT SUM(GREATEST(s.amount-s.paid_amount,0)) FROM booking_payment_schedule s WHERE s.booking_id=b.id AND s.status NOT IN ('received','waived')),GREATEST(b.total_price-b.paid_amount,0)) open_amount,
        COALESCE((SELECT SUM(GREATEST(s.amount-s.paid_amount,0)) FROM booking_payment_schedule s WHERE s.booking_id=b.id AND s.installment_type='deposit' AND s.status NOT IN ('received','waived')),0) open_deposit_amount,
        COALESCE((SELECT SUM(GREATEST(s.amount-s.paid_amount,0)) FROM booking_payment_schedule s WHERE s.booking_id=b.id AND s.installment_type='remaining' AND s.status NOT IN ('received','waived')),0) open_remaining_amount,
        (SELECT MIN(s.due_date) FROM booking_payment_schedule s WHERE s.booking_id=b.id AND s.status NOT IN ('received','waived') AND s.due_date IS NOT NULL) next_due_date,
        (SELECT COUNT(*) FROM booking_payment_schedule s WHERE s.booking_id=b.id AND s.status='overdue') overdue_count,
        (SELECT COUNT(*) FROM booking_documents d WHERE d.booking_id=b.id AND (d.deleted_at IS NULL OR d.deleted_at='0000-00-00 00:00:00')) document_count,
        (SELECT COUNT(*) FROM booking_documents d WHERE d.booking_id=b.id AND d.sent_at IS NOT NULL) sent_document_count,
        (SELECT COUNT(*) FROM booking_documents d WHERE d.booking_id=b.id AND d.document_type IN ('invoice','receipt','payment_overview','credit_note','cancellation_statement')) billing_document_count,
        (SELECT COUNT(*) FROM booking_payments p WHERE p.booking_id=b.id AND p.status='received') payment_count,
        (SELECT MAX(p.payment_date) FROM booking_payments p WHERE p.booking_id=b.id AND p.status='received') last_payment_date
        FROM bookings b JOIN guests g ON g.id=b.guest_id AND (g.deleted_at IS NULL OR g.deleted_at='0000-00-00 00:00:00')
        LEFT JOIN apartments a ON a.id=b.apartment_id LEFT JOIN apartment_types at ON at.id=b.apartment_type_id LEFT JOIN offers o ON o.id=b.source_offer_id";
    if($where)$sql.=' WHERE '.implode(' AND ',$where);
    $sql.=' ORDER BY COALESCE(next_due_date,b.arrival) ASC,b.arrival DESC,b.id DESC LIMIT 300';
    $stmt=db()->prepare($sql);$stmt->execute($params);$bookings=$stmt->fetchAll();

    $summary = v230_billing_summary();
    $buckets = v230_billing_buckets();
    json_response(['ok'=>true,'bookings'=>$bookings,'summary'=>$summary,'buckets'=>$buckets,'active_bucket'=>$bucket]);
}

function booking_billing_v230(): never
{
    if (function_exists('ensure_delete_center_v23663')) ensure_delete_center_v23663();
    $id=(int)($_GET['id']??0);if($id<=0)throw new ValidationException('Buchung fehlt.');
    BookingWorkflowService::syncBillingState($id);
    BookingWorkflowService::refreshOverdueStatuses();
    $booking=v223_booking_row($id);
    $s=db()->prepare('SELECT * FROM booking_payment_schedule WHERE booking_id=? ORDER BY sort_order,id');$s->execute([$id]);
    $p=db()->prepare("SELECT p.*,(SELECT GROUP_CONCAT(DISTINCT s.label ORDER BY s.sort_order,s.id SEPARATOR ', ') FROM booking_payment_allocations a LEFT JOIN booking_payment_schedule s ON s.id=a.schedule_id WHERE a.payment_id=p.id) allocation_labels FROM booking_payments p WHERE p.booking_id=? ORDER BY p.payment_date DESC,p.id DESC");$p->execute([$id]);
    $d=db()->prepare("SELECT id,document_type,document_number,language,title,pdf_path,status,generated_at,sent_at,created_at FROM booking_documents WHERE booking_id=? AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') ORDER BY id DESC");$d->execute([$id]);
    $logs=db()->prepare("SELECT channel,subject,status,recipient_address,detail,created_at FROM communication_log WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND entity_type='booking' AND entity_id=? ORDER BY id DESC LIMIT 12");$logs->execute([(string)$id]);
    $changes=db()->prepare("SELECT action,note,created_at FROM booking_change_log WHERE booking_id=? ORDER BY id DESC LIMIT 12");$changes->execute([$id]);
    $accessStmt=db()->prepare('SELECT active,valid_until,token_encrypted,last_viewed_at,updated_at FROM booking_customer_access WHERE booking_id=? LIMIT 1');$accessStmt->execute([$id]);$access=$accessStmt->fetch()?:null;$customerUrl='';
    if($access){$token=Crypto::decrypt($access['token_encrypted']??null);if($token!=='')$customerUrl=HousekeepingWorkflow::applicationUrl('kunde.php?token='.rawurlencode($token));}
    $paid=(float)($booking['paid_amount']??0);
    $open=max(0,(float)$booking['total_price']-$paid);
    $nextStmt=db()->prepare("SELECT * FROM booking_payment_schedule WHERE booking_id=? AND status NOT IN ('received','waived') ORDER BY CASE WHEN due_date IS NULL THEN 1 ELSE 0 END,due_date,sort_order,id LIMIT 1");$nextStmt->execute([$id]);
    $next=$nextStmt->fetch()?:null;
    $paymentIssue=function_exists('v235_payment_issue_for_booking') ? v235_payment_issue_for_booking($id) : null;
    json_response(['ok'=>true,'booking'=>$booking,'schedules'=>$s->fetchAll(),'payments'=>$p->fetchAll(),'documents'=>$d->fetchAll(),'communication'=>$logs->fetchAll(),'changes'=>$changes->fetchAll(),'customer_access'=>$access,'customer_url'=>$customerUrl,'payment_issue'=>$paymentIssue,'summary'=>['paid'=>$paid,'open'=>$open,'next_due'=>$next]]);
}

function create_billing_document_v230(): never
{
    $d=request_data();$bookingId=(int)($d['booking_id']??0);if($bookingId<=0)throw new ValidationException('Buchung fehlt.');
    BookingWorkflowService::syncBillingState($bookingId);
    $booking=v223_booking_row($bookingId);$currency=(string)($booking['currency']??setting('currency','EUR'));
    $type=(string)($d['document_type']??'invoice');
    $allowed=['invoice','payment_overview','receipt','credit_note','cancellation_statement'];
    if(!in_array($type,$allowed,true))$type='invoice';
    $send=normalize_bool($d['send_email']??1);
    $title=mb_substr(trim((string)($d['title']??'')),0,190);
    if($title==='')$title=v230_document_type_label($type).' '.$booking['reference'];
    $note=mb_substr(trim((string)($d['note']??'')),0,3000);
    $docAmount=round((float)str_replace(',','.',(string)($d['document_amount']??0)),2);
    $paymentId=(int)($d['payment_id']??0);
    if(in_array($type,['credit_note','cancellation_statement'],true)&&$docAmount<=0){
        $docAmount=max(0,(float)$booking['paid_amount']);
    }
    $payment=null;
    if($paymentId>0){$ps=db()->prepare('SELECT * FROM booking_payments WHERE id=? AND booking_id=? LIMIT 1');$ps->execute([$paymentId,$bookingId]);$payment=$ps->fetch()?:null;}

    $prefix=match($type){'receipt'=>'QU','payment_overview'=>'ZA','credit_note'=>'GU','cancellation_statement'=>'ST',default=>'RE'};
    $number=v223_next_number($type,$prefix);
    $snapshot=v230_build_billing_document($bookingId,$booking,$type,$title,$number,$note,$docAmount,$payment);
    $pdfPath='';$docId=0;
    db()->beginTransaction();
    try{
        db()->prepare('INSERT INTO booking_documents(booking_id,document_type,document_number,language,title,html_snapshot,status,created_by) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$bookingId,$type,$number,'de',$title,$snapshot['html'],'generated',Auth::user()['id']??null]);
        $docId=(int)db()->lastInsertId();
        $safe=preg_replace('/[^A-Za-z0-9_-]/','-',$number)?:'billing-document';
        $dir=root_path('storage/documents/booking-confirmations');if(!is_dir($dir))@mkdir($dir,0775,true);
        $pdfPath='storage/documents/booking-confirmations/'.$safe.'.pdf';
        $pdf=SimplePdf::create($title,$snapshot['lines']);
        if(@file_put_contents(root_path($pdfPath),$pdf,LOCK_EX)===false)throw new RuntimeException('Das Abrechnungs-PDF konnte nicht gespeichert werden.');
        db()->prepare('UPDATE booking_documents SET pdf_path=?,checksum_sha256=? WHERE id=?')->execute([$pdfPath,hash_file('sha256',root_path($pdfPath))?:null,$docId]);
        db()->prepare('INSERT INTO booking_change_log(booking_id,action,new_values_json,note,created_by) VALUES(?,?,?,?,?)')
            ->execute([$bookingId,'billing_document_created',json_encode(['document_id'=>$docId,'type'=>$type,'number'=>$number,'amount'=>$docAmount],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),v230_document_type_label($type).' erzeugt',Auth::user()['id']??null]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    AuditLogger::record('booking',$bookingId,'billing_document_created',null,['document_id'=>$docId,'type'=>$type,'number'=>$number],'Abrechnungsdokument erzeugt');
    $mail=$send?v230_send_customer_message($bookingId,'billing_document',$snapshot['email_subject'],$snapshot['email_text'],$snapshot['email_html'],[['path'=>root_path($pdfPath),'name'=>$number.'.pdf','mime'=>'application/pdf']],$docId):['sent'=>false,'message'=>'Benachrichtigung deaktiviert.'];
    json_response(['ok'=>true,'message'=>v230_document_type_label($type).' wurde erzeugt.'.($mail['sent']?' Der Kunde wurde per E-Mail informiert.':' Kunden-E-Mail nicht bestätigt: '.$mail['message']),'document_id'=>$docId,'document_number'=>$number,'pdf_path'=>$pdfPath,'email'=>$mail]);
}

function send_billing_document_v230(): never
{
    $d=request_data();$bookingId=(int)($d['booking_id']??0);$docId=(int)($d['document_id']??0);if($bookingId<=0||$docId<=0)throw new ValidationException('Buchung oder Dokument fehlt.');
    $stmt=db()->prepare('SELECT * FROM booking_documents WHERE id=? AND booking_id=? LIMIT 1');$stmt->execute([$docId,$bookingId]);$doc=$stmt->fetch();if(!$doc)throw new NotFoundException('Dokument nicht gefunden.');
    if(empty($doc['pdf_path'])||!is_file(root_path((string)$doc['pdf_path'])))throw new ValidationException('Für dieses Dokument ist kein gespeichertes PDF vorhanden.');
    BookingWorkflowService::syncBillingState($bookingId);
    $booking=v223_booking_row($bookingId);$subject=trim((string)($d['subject']??''));if($subject==='')$subject='Dokument zu Ihrer Buchung '.$booking['reference'];
    $message=trim((string)($d['message']??''));if($message==='')$message="Guten Tag ".$booking['guest_name'].",\n\nanbei senden wir Ihnen das Dokument: ".$doc['title'].".\n\nIm Kundenbereich finden Sie ebenfalls den aktuellen Zahlungs- und Dokumentenstatus.";
    $url=v223_customer_url($bookingId);if($url!=='')$message.="\n\nKundenbereich: ".$url;
    $message.="\n\nMit freundlichen Grüßen\n".(string)setting('property_name','StayPilot');
    $html='<p>Guten Tag '.e((string)$booking['guest_name']).',</p><p>'.nl2br(e($message)).'</p>';
    $mail=v230_send_customer_message($bookingId,'billing_document_resend',$subject,$message,$html,[[ 'path'=>root_path((string)$doc['pdf_path']),'name'=>(string)($doc['document_number']?:'Dokument').'.pdf','mime'=>'application/pdf']],$docId);
    json_response(['ok'=>true,'message'=>$mail['sent']?'Dokument wurde per E-Mail gesendet.':'Dokument konnte nicht gesendet werden: '.$mail['message'],'email'=>$mail]);
}

function send_payment_reminder_v230(): never
{
    $d=request_data();$bookingId=(int)($d['booking_id']??0);if($bookingId<=0)throw new ValidationException('Buchung fehlt.');
    BookingWorkflowService::syncBillingState($bookingId);
    $booking=v223_booking_row($bookingId);$scheduleId=(int)($d['schedule_id']??0);$schedule=null;
    if($scheduleId>0){$s=db()->prepare('SELECT * FROM booking_payment_schedule WHERE id=? AND booking_id=? LIMIT 1');$s->execute([$scheduleId,$bookingId]);$schedule=$s->fetch()?:null;}
    if(!$schedule){$s=db()->prepare("SELECT * FROM booking_payment_schedule WHERE booking_id=? AND status NOT IN ('received','waived') ORDER BY CASE WHEN status='overdue' THEN 0 ELSE 1 END,CASE WHEN due_date IS NULL THEN 1 ELSE 0 END,due_date,sort_order,id LIMIT 1");$s->execute([$bookingId]);$schedule=$s->fetch()?:null;}
    if(!$schedule)throw new ValidationException('Für diese Buchung ist kein offenes Zahlungsziel vorhanden.');
    $currency=(string)($booking['currency']??setting('currency','EUR'));$open=max(0,(float)$schedule['amount']-(float)$schedule['paid_amount']);
    $subject=trim((string)($d['subject']??''));if($subject==='')$subject=((string)$schedule['status']==='overdue'?'Zahlungserinnerung ':'Zahlungsinformation ').$booking['reference'];
    $message=trim((string)($d['message']??''));
    if($message===''){
        $message="Guten Tag ".$booking['guest_name'].",\n\nwir möchten Sie freundlich an das Zahlungsziel \"".$schedule['label']."\" erinnern.\n";
        $message.="Offener Betrag: ".v223_money($open,$currency)."\n";
        if(!empty($schedule['due_date']))$message.="Fällig am: ".$schedule['due_date']."\n";
        $message.="\nDen aktuellen Stand finden Sie jederzeit in Ihrem sicheren Kundenbereich.";
    }
    $url=v223_customer_url($bookingId);if($url!=='')$message.="\n\nKundenbereich: ".$url;
    $message.="\n\nMit freundlichen Grüßen\n".(string)setting('property_name','StayPilot');
    $html='<p>Guten Tag '.e((string)$booking['guest_name']).',</p><p>'.nl2br(e($message)).'</p>';
    $attachments=[];$documentId=null;
    if(normalize_bool($d['attach_pdf']??0)){
        $docTitle='Zahlungsübersicht '.$booking['reference'];
        $docNumber=v223_next_number('payment_reminder_overview','ZE');
        $snapshot=v230_build_billing_document($bookingId,$booking,'payment_overview',$docTitle,$docNumber,'Automatisch zur Zahlungserinnerung erzeugt.',0,null);
        db()->beginTransaction();
        try{
            db()->prepare('INSERT INTO booking_documents(booking_id,document_type,document_number,language,title,html_snapshot,status,created_by) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$bookingId,'payment_overview',$docNumber,'de',$docTitle,$snapshot['html'],'generated',Auth::user()['id']??null]);
            $documentId=(int)db()->lastInsertId();
            $safe=preg_replace('/[^A-Za-z0-9_-]/','-',$docNumber)?:'payment-overview';
            $dir=root_path('storage/documents/booking-confirmations');if(!is_dir($dir))@mkdir($dir,0775,true);
            $pdfPath='storage/documents/booking-confirmations/'.$safe.'.pdf';
            $pdf=SimplePdf::create($docTitle,$snapshot['lines']);
            if(@file_put_contents(root_path($pdfPath),$pdf,LOCK_EX)===false)throw new RuntimeException('PDF zur Zahlungserinnerung konnte nicht geschrieben werden.');
            db()->prepare('UPDATE booking_documents SET pdf_path=? WHERE id=?')->execute([$pdfPath,$documentId]);
            db()->commit();
            $attachments[]=['path'=>root_path($pdfPath),'name'=>$docNumber.'.pdf','mime'=>'application/pdf'];
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    }
    $mail=v230_send_customer_message($bookingId,'payment_reminder',$subject,$message,$html,$attachments,$documentId);
    db()->prepare('INSERT INTO booking_change_log(booking_id,action,new_values_json,note,created_by) VALUES(?,?,?,?,?)')
        ->execute([$bookingId,'payment_reminder_sent',json_encode(['schedule_id'=>$schedule['id'],'open_amount'=>$open,'email_sent'=>$mail['sent'],'document_id'=>$documentId],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'Zahlungserinnerung vorbereitet/gesendet',Auth::user()['id']??null]);
    json_response(['ok'=>true,'message'=>$mail['sent']?'Zahlungserinnerung wurde per E-Mail gesendet.'.($attachments?' Zahlungsübersicht wurde angehängt.':''):'Zahlungserinnerung konnte nicht per E-Mail gesendet werden: '.$mail['message'],'email'=>$mail,'document_id'=>$documentId]);
}

function v230_billing_summary(): array
{
    BookingWorkflowService::syncBillingState();
    $paidMonth=(float)(db()->query("SELECT COALESCE(SUM(p.amount),0) FROM booking_payments p JOIN bookings b ON b.id=p.booking_id WHERE p.status='received' AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND COALESCE(b.status,'') NOT IN ('cancelled','rejected') AND COALESCE(b.accounting_mode,'internal')='internal' AND p.payment_date BETWEEN DATE_FORMAT(CURDATE(),'%Y-%m-01') AND LAST_DAY(CURDATE())")->fetchColumn()?:0);
    $paidYear=(float)(db()->query("SELECT COALESCE(SUM(p.amount),0) FROM booking_payments p JOIN bookings b ON b.id=p.booking_id WHERE p.status='received' AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND COALESCE(b.status,'') NOT IN ('cancelled','rejected') AND COALESCE(b.accounting_mode,'internal')='internal' AND p.payment_date BETWEEN MAKEDATE(YEAR(CURDATE()),1) AND CURDATE()")->fetchColumn()?:0);
    $open=(float)(db()->query("SELECT COALESCE(SUM(x.open_amount),0) FROM (SELECT b.id,CASE WHEN COUNT(s.id)>0 THEN COALESCE(SUM(CASE WHEN s.status NOT IN ('received','waived') THEN GREATEST(s.amount-s.paid_amount,0) ELSE 0 END),0) ELSE GREATEST(b.total_price-b.paid_amount,0) END open_amount FROM bookings b LEFT JOIN booking_payment_schedule s ON s.booking_id=b.id WHERE (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND COALESCE(b.status,'') NOT IN ('cancelled','rejected') AND COALESCE(b.accounting_mode,'internal')='internal' GROUP BY b.id,b.total_price,b.paid_amount) x")->fetchColumn()?:0);
    $overdueAmount=(float)(db()->query("SELECT COALESCE(SUM(GREATEST(s.amount-s.paid_amount,0)),0) FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id WHERE s.status='overdue' AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND COALESCE(b.status,'') NOT IN ('cancelled','rejected') AND COALESCE(b.accounting_mode,'internal')='internal'")->fetchColumn()?:0);
    $docs=(int)(db()->query("SELECT COUNT(*) FROM booking_documents WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00')")->fetchColumn()?:0);
    $billingDocs=(int)(db()->query("SELECT COUNT(*) FROM booking_documents WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND document_type IN ('invoice','receipt','payment_overview','credit_note','cancellation_statement')")->fetchColumn()?:0);
    return ['received_month'=>$paidMonth,'received_year'=>$paidYear,'open_amount'=>$open,'overdue_amount'=>$overdueAmount,'billing_documents'=>$docs,'billing_documents_only'=>$billingDocs];
}

function v230_billing_buckets(): array
{
    $one=static function(string $sql): int {return (int)(db()->query($sql)->fetchColumn()?:0);};
    return [
        'all'=>$one("SELECT COUNT(*) FROM bookings WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND COALESCE(status,'') NOT IN ('cancelled','rejected')"),
        'overdue'=>$one("SELECT COUNT(DISTINCT s.booking_id) FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id WHERE s.status='overdue' AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND COALESCE(b.status,'') NOT IN ('cancelled','rejected') AND COALESCE(b.accounting_mode,'internal')='internal'"),
        'due_soon'=>$one("SELECT COUNT(DISTINCT s.booking_id) FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id WHERE s.status IN ('open','partial') AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND COALESCE(b.status,'') NOT IN ('cancelled','rejected') AND COALESCE(b.accounting_mode,'internal')='internal' AND s.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)"),
        'deposit'=>$one("SELECT COUNT(DISTINCT s.booking_id) FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id WHERE s.installment_type='deposit' AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND COALESCE(b.status,'') NOT IN ('cancelled','rejected') AND COALESCE(b.accounting_mode,'internal')='internal' AND s.status NOT IN ('received','waived')"),
        'remaining'=>$one("SELECT COUNT(DISTINCT s.booking_id) FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id WHERE s.installment_type='remaining' AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND COALESCE(b.status,'') NOT IN ('cancelled','rejected') AND COALESCE(b.accounting_mode,'internal')='internal' AND s.status NOT IN ('received','waived')"),
        'paid'=>$one("SELECT COUNT(*) FROM bookings WHERE payment_status='paid' AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00')"),
        'documents'=>$one("SELECT COUNT(DISTINCT booking_id) FROM booking_documents WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00')"),
        'no_docs'=>$one("SELECT COUNT(*) FROM bookings b WHERE (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND b.total_price>0 AND NOT EXISTS(SELECT 1 FROM booking_documents d WHERE d.booking_id=b.id AND (d.deleted_at IS NULL OR d.deleted_at='0000-00-00 00:00:00') AND d.document_type IN ('invoice','receipt','payment_overview','credit_note','cancellation_statement'))"),
        'unsent'=>$one("SELECT COUNT(*) FROM bookings b WHERE (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND EXISTS(SELECT 1 FROM booking_documents d WHERE d.booking_id=b.id AND (d.deleted_at IS NULL OR d.deleted_at='0000-00-00 00:00:00') AND d.document_type IN ('invoice','receipt','payment_overview','credit_note','cancellation_statement')) AND NOT EXISTS(SELECT 1 FROM booking_documents ds WHERE ds.booking_id=b.id AND ds.sent_at IS NOT NULL AND (ds.deleted_at IS NULL OR ds.deleted_at='0000-00-00 00:00:00') AND ds.document_type IN ('invoice','receipt','payment_overview','credit_note','cancellation_statement'))"),
        'no_contact'=>$one("SELECT COUNT(*) FROM bookings b JOIN guests g ON g.id=b.guest_id WHERE (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND (COALESCE(g.email,'')='' OR g.email NOT LIKE '%_@_%._%')"),
    ];
}

function v230_build_billing_document(int $bookingId,array $booking,string $type,string $title,string $number,string $note,float $docAmount,?array $payment): array
{
    $currency=(string)($booking['currency']??setting('currency','EUR'));
    $s=db()->prepare('SELECT * FROM booking_payment_schedule WHERE booking_id=? ORDER BY sort_order,id');$s->execute([$bookingId]);$schedules=$s->fetchAll();
    $p=db()->prepare("SELECT * FROM booking_payments WHERE booking_id=? AND status='received' ORDER BY payment_date,id");$p->execute([$bookingId]);$payments=$p->fetchAll();
    $paid=(float)($booking['paid_amount']??0);$open=max(0,(float)$booking['total_price']-$paid);
    $money=static fn(float $v):string=>v223_money($v,$currency);
    $label=v230_document_type_label($type);
    $rows='';
    if(in_array($type,['credit_note','cancellation_statement'],true)){
        $rows.='<tr><td>'.e($label).'</td><td>1</td><td>'.e($money($docAmount)).'</td><td>'.e($money($docAmount)).'</td></tr>';
    } elseif($type==='receipt'&&$payment){
        $rows.='<tr><td>Quittung für Zahlung '.e((string)($payment['payment_number']??'')).'</td><td>1</td><td>'.e($money((float)$payment['amount'])).'</td><td>'.e($money((float)$payment['amount'])).'</td></tr>';
    } else {
        $base=max(0,(float)$booking['total_price']-(float)($booking['tourist_tax']??0));
        $rows.='<tr><td>Aufenthalt '.e((string)$booking['arrival']).' bis '.e((string)$booking['departure']).'</td><td>1</td><td>'.e($money($base)).'</td><td>'.e($money($base)).'</td></tr>';
        if((float)($booking['tourist_tax']??0)>0)$rows.='<tr><td>Kurtaxe / lokale Abgabe</td><td>1</td><td>'.e($money((float)$booking['tourist_tax'])).'</td><td>'.e($money((float)$booking['tourist_tax'])).'</td></tr>';
        if((float)($booking['discount_amount']??0)>0)$rows.='<tr><td>Rabatt</td><td>1</td><td>-'.e($money((float)$booking['discount_amount'])).'</td><td>-'.e($money((float)$booking['discount_amount'])).'</td></tr>';
    }
    $scheduleRows='';foreach($schedules as $row)$scheduleRows.='<tr><td>'.e((string)$row['label']).'</td><td>'.e((string)($row['due_date']?:'–')).'</td><td>'.e($money((float)$row['amount'])).'</td><td>'.e($money((float)$row['paid_amount'])).'</td><td>'.e(v230_status_label((string)$row['status'])).'</td></tr>';
    $paymentRows='';foreach($payments as $row)$paymentRows.='<tr><td>'.e((string)$row['payment_date']).'</td><td>'.e((string)$row['payment_number']).'</td><td>'.e($money((float)$row['amount'])).'</td><td>'.e(v230_method_label((string)$row['payment_method'])).'</td></tr>';
    $html='<!doctype html><html lang="de"><head><meta charset="utf-8"><title>'.e($title).'</title><style>body{font-family:Arial,sans-serif;color:#172033;background:#fff}main{max-width:900px;margin:auto;padding:24px}.head{display:flex;justify-content:space-between;gap:24px;border-bottom:3px solid #2563eb;padding-bottom:18px}.box{border:1px solid #dce5f0;border-radius:12px;padding:16px;margin:14px 0;background:#f8fafc}table{border-collapse:collapse;width:100%;margin:10px 0 20px}td,th{border-bottom:1px solid #e5edf5;padding:8px;text-align:left}.right{text-align:right}.total{font-size:20px;font-weight:bold}.muted{color:#64748b}.warn{color:#b91c1c}</style></head><body><main><div class="head"><div><small>'.e($label).'</small><h1>'.e($title).'</h1><b>'.e($number).'</b></div><div><b>'.e((string)setting('property_name','StayPilot')).'</b><br>'.e((string)setting('contact_email','')).'</div></div><div class="box"><b>Gast:</b> '.e((string)$booking['guest_name']).'<br><b>Buchung:</b> '.e((string)$booking['reference']).'<br><b>Aufenthalt:</b> '.e((string)$booking['arrival']).' – '.e((string)$booking['departure']).'<br><b>Wohnung:</b> '.e((string)($booking['apartment_type_name']??'')).' '.e((string)($booking['apartment_code']??'')).'</div><h2>Positionen</h2><table><thead><tr><th>Beschreibung</th><th>Menge</th><th>Einzel</th><th>Gesamt</th></tr></thead><tbody>'.$rows.'</tbody></table><div class="box total">Gesamt: '.e($money(in_array($type,['credit_note','cancellation_statement'],true)?$docAmount:(float)$booking['total_price'])).'<br>Erhalten: '.e($money($paid)).'<br>Offen: '.e($money($open)).'</div><h2>Zahlungsplan</h2><table><thead><tr><th>Rate</th><th>Fällig</th><th>Betrag</th><th>Erhalten</th><th>Status</th></tr></thead><tbody>'.($scheduleRows?:'<tr><td colspan="5">Kein Zahlungsplan vorhanden.</td></tr>').'</tbody></table><h2>Zahlungen</h2><table><thead><tr><th>Datum</th><th>Nummer</th><th>Betrag</th><th>Art</th></tr></thead><tbody>'.($paymentRows?:'<tr><td colspan="4">Noch keine Zahlung erfasst.</td></tr>').'</tbody></table>'.($note!==''?'<div class="box"><b>Hinweis:</b><br>'.nl2br(e($note)).'</div>':'').'<p class="muted">Dieses Dokument wurde aus dem aktuellen StayPilot-Abrechnungsstand erzeugt.</p></main></body></html>';
    $lines=[['text'=>$title,'size'=>15,'bold'=>true,'space'=>4],$label.' '.$number,'Buchung: '.$booking['reference'],'Gast: '.$booking['guest_name'],'Aufenthalt: '.$booking['arrival'].' bis '.$booking['departure'],['text'=>'','space'=>4],['text'=>'Gesamt: '.$money(in_array($type,['credit_note','cancellation_statement'],true)?$docAmount:(float)$booking['total_price']),'bold'=>true],'Erhalten: '.$money($paid),'Offen: '.$money($open),['text'=>'','space'=>4],['text'=>'Zahlungsplan','bold'=>true]];
    foreach($schedules as $row)$lines[]=$row['label'].': '.$money((float)$row['amount']).' · erhalten '.$money((float)$row['paid_amount']).' · '.v230_status_label((string)$row['status']).' · fällig '.($row['due_date']?:'–');
    if($payments){$lines[]=['text'=>'','space'=>4];$lines[]=['text'=>'Zahlungen','bold'=>true];foreach($payments as $row)$lines[]=$row['payment_date'].' · '.($row['payment_number']?:'Zahlung').' · '.$money((float)$row['amount']).' · '.v230_method_label((string)$row['payment_method']);}
    if($note!==''){$lines[]=['text'=>'','space'=>4];$lines[]=['text'=>'Hinweis','bold'=>true];$lines[]=$note;}
    $subject=$label.' zu Ihrer Buchung '.$booking['reference'];
    $text="Guten Tag ".$booking['guest_name'].",\n\nim Kundenbereich steht ein neues Dokument bereit: ".$title.".\n\nBuchung: ".$booking['reference']."\nGesamt: ".$money((float)$booking['total_price'])."\nErhalten: ".$money($paid)."\nOffen: ".$money($open);
    $url=v223_customer_url($bookingId);if($url!=='')$text.="\n\nKundenbereich: ".$url;
    $text.="\n\nMit freundlichen Grüßen\n".(string)setting('property_name','StayPilot');
    $emailHtml='<p>Guten Tag '.e((string)$booking['guest_name']).',</p><p>im Kundenbereich steht ein neues Dokument bereit: <b>'.e($title).'</b>.</p><p>Buchung: '.e((string)$booking['reference']).'<br>Gesamt: '.e($money((float)$booking['total_price'])).'<br>Erhalten: '.e($money($paid)).'<br>Offen: <b>'.e($money($open)).'</b></p>'.($url!==''?'<p><a href="'.e($url).'">Kundenbereich öffnen</a></p>':'').'<p>Mit freundlichen Grüßen<br>'.e((string)setting('property_name','StayPilot')).'</p>';
    return ['html'=>$html,'lines'=>$lines,'email_subject'=>$subject,'email_text'=>$text,'email_html'=>$emailHtml];
}

function v230_send_customer_message(int $bookingId,string $kind,string $subject,string $text,string $html,array $attachments=[],?int $documentId=null): array
{
    BookingWorkflowService::syncBillingState($bookingId);
    $booking=v223_booking_row($bookingId);$email=trim((string)($booking['guest_email']??''));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))return ['sent'=>false,'message'=>'Keine gültige Kunden-E-Mail-Adresse hinterlegt.'];
    try{
        $result=SmtpMailer::send($email,$subject,$text,$html,$attachments);
        CommunicationLogger::record('email','booking',$bookingId,(string)$booking['guest_name'],$email,$subject,$text,'sent',(string)($result['message_id']??''));
        if($documentId!==null)db()->prepare("UPDATE booking_documents SET sent_at=NOW(),status='sent' WHERE id=? AND booking_id=?")->execute([$documentId,$bookingId]);
        return ['sent'=>true,'message'=>'gesendet'];
    }catch(Throwable $e){
        CommunicationLogger::record('email','booking',$bookingId,(string)$booking['guest_name'],$email,$subject,$text,'failed',$e->getMessage());
        AppLogger::error($e,['booking_id'=>$bookingId,'kind'=>$kind],'billing-v230-email');
        return ['sent'=>false,'message'=>$e->getMessage()];
    }
}

function v230_document_type_label(string $type): string
{
    return ['invoice'=>'Rechnung','payment_overview'=>'Zahlungsübersicht','receipt'=>'Quittung','credit_note'=>'Gutschrift','cancellation_statement'=>'Storno-/Rückzahlungsbeleg'][$type]??'Dokument';
}
function v230_status_label(string $status): string
{
    return ['open'=>'Offen','partial'=>'Teilbezahlt','received'=>'Erhalten','overdue'=>'Überfällig','waived'=>'Erlassen','paid'=>'Bezahlt','refunded'=>'Erstattet','sent'=>'Gesendet','generated'=>'Erzeugt'][$status]??$status;
}
function v230_method_label(string $method): string
{
    return ['bank_transfer'=>'Überweisung','cash'=>'Bar','card'=>'Karte','paypal'=>'PayPal','provider'=>'Portal','other'=>'Sonstige'][$method]??$method;
}


/* StayPilot V2.3.6.51 – Belegzähler & Abrechnungsübersicht im bestehenden Rechnungen/Zahlungen-Bereich. */
function billing_number_counters_v23651(): never
{
    $year=(int)($_GET['year']??date('Y'));
    if($year<2000||$year>2100)$year=(int)date('Y');
    $types=[
        'invoice'=>['label'=>'Rechnungen','prefix'=>'RE','description'=>'Fortlaufende Rechnungsnummern'],
        'receipt'=>['label'=>'Quittungen','prefix'=>'QU','description'=>'Fortlaufende Quittungsnummern'],
        'payment_overview'=>['label'=>'Zahlungsübersichten','prefix'=>'ZA','description'=>'Zahlungsübersichten und Zahlungsstatus-PDFs'],
        'credit_note'=>['label'=>'Gutschriften','prefix'=>'GU','description'=>'Gutschriften'],
        'cancellation_statement'=>['label'=>'Storno/Rückzahlung','prefix'=>'ST','description'=>'Storno- und Rückzahlungsbelege'],
        'payment'=>['label'=>'Zahlungseingänge','prefix'=>'PAY','description'=>'interne Zahlungsnummern'],
        'payment_reminder_overview'=>['label'=>'Zahlungserinnerungs-PDF','prefix'=>'ZE','description'=>'automatisch erzeugte Zahlungsübersichten zu Erinnerungen'],
    ];
    $out=[];
    $seq=db()->prepare('SELECT current_value,updated_at FROM document_sequences WHERE document_type=? AND document_year=? LIMIT 1');
    $docs=db()->prepare("SELECT COUNT(*) FROM booking_documents WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND document_type=? AND YEAR(COALESCE(generated_at,created_at,CURDATE()))=?");
    $lastDoc=db()->prepare("SELECT document_number,created_at,generated_at,sent_at,status FROM booking_documents WHERE document_type=? AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') ORDER BY id DESC LIMIT 1");
    foreach($types as $type=>$meta){
        $seq->execute([$type,$year]);$row=$seq->fetch()?:null;$current=(int)($row['current_value']??0);
        $docs->execute([$type,$year]);$docCount=(int)($docs->fetchColumn()?:0);
        $lastDoc->execute([$type]);$last=$lastDoc->fetch()?:null;
        $out[]=[
            'type'=>$type,
            'label'=>$meta['label'],
            'prefix'=>$meta['prefix'],
            'description'=>$meta['description'],
            'year'=>$year,
            'current_value'=>$current,
            'next_number'=>$meta['prefix'].'-'.$year.'-'.str_pad((string)($current+1),4,'0',STR_PAD_LEFT),
            'document_count'=>$docCount,
            'last_number'=>$last['document_number']??'',
            'last_status'=>$last['status']??'',
            'last_created_at'=>$last['generated_at']??($last['created_at']??''),
            'last_sent_at'=>$last['sent_at']??'',
            'updated_at'=>$row['updated_at']??'',
        ];
    }
    $payCount=(int)(db()->query("SELECT COUNT(*) FROM booking_payments p JOIN bookings b ON b.id=p.booking_id JOIN guests g ON g.id=b.guest_id AND (g.deleted_at IS NULL OR g.deleted_at='0000-00-00 00:00:00') WHERE p.status='received' AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND LOWER(COALESCE(b.status,'')) NOT IN ('cancelled','canceled','storniert','deleted','gelöscht','rejected','abgelehnt','declined','void') AND COALESCE(b.accounting_mode,'internal')='internal'")->fetchColumn()?:0);
    $docTotal=(int)(db()->query("SELECT COUNT(*) FROM booking_documents WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND document_type IN ('invoice','receipt','payment_overview','credit_note','cancellation_statement')")->fetchColumn()?:0);
    json_response(['ok'=>true,'year'=>$year,'counters'=>$out,'summary'=>['received_payments'=>$payCount,'billing_documents'=>$docTotal]]);
}

function billing_number_counter_set_v23651(): never
{
    $d=request_data();
    $type=trim((string)($d['document_type']??''));
    $year=(int)($d['year']??date('Y'));
    $current=(int)($d['current_value']??-1);
    $allowed=['invoice','receipt','payment_overview','credit_note','cancellation_statement','payment','payment_reminder_overview'];
    if(!in_array($type,$allowed,true))throw new ValidationException('Unbekannter Nummernkreis.');
    if($year<2000||$year>2100)throw new ValidationException('Ungültiges Jahr.');
    if($current<0)throw new ValidationException('Der Zähler darf nicht negativ sein.');
    $maxUsed=v23651_max_used_number($type,$year);
    if($current<$maxUsed)throw new ValidationException('Der Zähler darf nicht kleiner als bereits verwendete Belegnummern sein. Bereits verwendet: '.$maxUsed.'.');
    db()->prepare('INSERT INTO document_sequences(document_type,document_year,current_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE current_value=VALUES(current_value), updated_at=CURRENT_TIMESTAMP')->execute([$type,$year,$current]);
    AuditLogger::record('document_sequences',0,'billing_counter_set',null,['document_type'=>$type,'year'=>$year,'current_value'=>$current],'Belegzähler angepasst');
    json_response(['ok'=>true,'message'=>'Belegzähler gespeichert. Die nächste Nummer wird ab dem neuen Stand erzeugt.']);
}

function v23651_max_used_number(string $type,int $year): int
{
    $prefix=match($type){'receipt'=>'QU','payment_overview'=>'ZA','credit_note'=>'GU','cancellation_statement'=>'ST','payment'=>'PAY','payment_reminder_overview'=>'ZE',default=>'RE'};
    if($type==='payment'){
        $stmt=db()->prepare('SELECT payment_number FROM booking_payments WHERE payment_number LIKE ?');
        $stmt->execute([$prefix.'-'.$year.'-%']);
    } else {
        $stmt=db()->prepare('SELECT document_number FROM booking_documents WHERE document_type=? AND document_number LIKE ?');
        $stmt->execute([$type,$prefix.'-'.$year.'-%']);
    }
    $max=0;
    foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $n){
        if(preg_match('/-(\d+)$/',(string)$n,$m))$max=max($max,(int)$m[1]);
    }
    return $max;
}

/* StayPilot V2.3.6.52 – Zahlungsassistent: Teilzahlungen, Quittung nach Zahlung, Kundeninfo im bestehenden Abrechnungsbereich. */
function save_booking_payment_v23652(): never
{
    $d=request_data();
    $bookingId=(int)($d['booking_id']??0);if($bookingId<=0)throw new ValidationException('Buchung fehlt.');
    BookingWorkflowService::syncBillingState($bookingId);
    $booking=v223_booking_row($bookingId);
    $amount=round((float)str_replace(',','.',(string)($d['amount']??0)),2);if($amount<=0)throw new ValidationException('Der Zahlungsbetrag muss größer als 0 sein.');
    $date=trim((string)($d['payment_date']??date('Y-m-d')));if(!valid_date($date))throw new ValidationException('Das Zahlungsdatum ist ungültig.');
    $method=trim((string)($d['payment_method']??'bank_transfer'));if($method==='')$method='bank_transfer';
    $reference=mb_substr(trim((string)($d['reference']??'')),0,190);
    $note=mb_substr(trim((string)($d['note']??'')),0,1000);
    $scheduleChoice=trim((string)($d['schedule_id']??''));
    $notify=normalize_bool($d['notify_customer']??1);
    $createReceipt=normalize_bool($d['create_receipt']??0);
    $sendReceipt=normalize_bool($d['send_receipt']??0);
    $paymentNumber=v223_next_number('payment','PAY');
    $paymentId=0;$scheduleId=0;
    db()->beginTransaction();
    try{
        $scheduleId=v223_resolve_payment_schedule_choice($bookingId,$amount,$scheduleChoice);
        db()->prepare('INSERT INTO booking_payments(booking_id,payment_number,payment_date,amount,payment_method,reference,note,status,created_by) VALUES(?,?,?,?,?,?,?,?,?)')
            ->execute([$bookingId,$paymentNumber,$date,$amount,$method,$reference?:null,$note?:null,'received',Auth::user()['id']??null]);
        $paymentId=(int)db()->lastInsertId();
        v223_allocate_payment($paymentId,$bookingId,$amount,$scheduleId>0?$scheduleId:null);
        v223_recalculate_booking_payment_status($bookingId);
        db()->prepare('INSERT INTO booking_change_log(booking_id,action,new_values_json,note,created_by) VALUES(?,?,?,?,?)')
            ->execute([$bookingId,'payment_received',json_encode(['payment_id'=>$paymentId,'amount'=>$amount,'payment_number'=>$paymentNumber,'schedule_id'=>$scheduleId?:null,'receipt_requested'=>$createReceipt],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'Zahlung erfasst: '.$paymentNumber,Auth::user()['id']??null]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    AuditLogger::record('booking',$bookingId,'payment_received',null,['payment_number'=>$paymentNumber,'amount'=>$amount,'receipt_requested'=>$createReceipt],'Zahlung erfasst');

    $receipt=['created'=>false,'sent'=>false,'document_id'=>null,'message'=>'Keine Quittung erzeugt.'];
    if($createReceipt){
        $receipt=v23652_create_payment_receipt($bookingId,$paymentId,$sendReceipt);
    }
    $mail=['sent'=>false,'message'=>'Benachrichtigung deaktiviert.'];
    if($notify && !$sendReceipt){
        $mail=v223_notify_customer($bookingId,'payment','Zahlung erhalten: '.v223_money($amount,(string)($booking['currency']??'EUR')),[]);
    } elseif($notify && $sendReceipt) {
        $mail=['sent'=>(bool)($receipt['sent']??false),'message'=>(string)($receipt['message']??'Quittung wurde gesendet.')];
    }
    $msg='Zahlung gespeichert.';
    if($createReceipt)$msg.=' Quittung wurde erzeugt.';
    if($sendReceipt)$msg.=($receipt['sent']?' Quittung wurde per E-Mail gesendet.':' Quittung konnte nicht gesendet werden: '.($receipt['message']??'unbekannter Fehler'));
    elseif($notify)$msg.=($mail['sent']?' Der Kunde wurde per E-Mail informiert.':' Kunden-E-Mail nicht bestätigt: '.$mail['message']);
    json_response(['ok'=>true,'message'=>$msg,'payment_number'=>$paymentNumber,'payment_id'=>$paymentId,'receipt'=>$receipt,'email'=>$mail]);
}

function v23652_create_payment_receipt(int $bookingId,int $paymentId,bool $send): array
{
    BookingWorkflowService::syncBillingState($bookingId);
    $booking=v223_booking_row($bookingId);
    $ps=db()->prepare('SELECT * FROM booking_payments WHERE id=? AND booking_id=? LIMIT 1');$ps->execute([$paymentId,$bookingId]);$payment=$ps->fetch()?:null;
    if(!$payment)return ['created'=>false,'sent'=>false,'document_id'=>null,'message'=>'Zahlung nicht gefunden.'];
    $title='Quittung '.$booking['reference'].' / '.($payment['payment_number']??'Zahlung');
    $number=v223_next_number('receipt','QU');
    $snapshot=v230_build_billing_document($bookingId,$booking,'receipt',$title,$number,'Automatisch zur erfassten Zahlung erzeugt.',(float)$payment['amount'],$payment);
    $docId=0;$pdfPath='';
    db()->beginTransaction();
    try{
        db()->prepare('INSERT INTO booking_documents(booking_id,document_type,document_number,language,title,html_snapshot,status,created_by) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$bookingId,'receipt',$number,'de',$title,$snapshot['html'],'generated',Auth::user()['id']??null]);
        $docId=(int)db()->lastInsertId();
        $safe=preg_replace('/[^A-Za-z0-9_-]/','-',$number)?:'receipt';
        $dir=root_path('storage/documents/booking-confirmations');if(!is_dir($dir))@mkdir($dir,0775,true);
        $pdfPath='storage/documents/booking-confirmations/'.$safe.'.pdf';
        $pdf=SimplePdf::create($title,$snapshot['lines']);
        if(@file_put_contents(root_path($pdfPath),$pdf,LOCK_EX)===false)throw new RuntimeException('Das Quittungs-PDF konnte nicht gespeichert werden.');
        db()->prepare('UPDATE booking_documents SET pdf_path=?,checksum_sha256=? WHERE id=?')->execute([$pdfPath,hash_file('sha256',root_path($pdfPath))?:null,$docId]);
        db()->prepare('INSERT INTO booking_change_log(booking_id,action,new_values_json,note,created_by) VALUES(?,?,?,?,?)')
            ->execute([$bookingId,'receipt_created',json_encode(['payment_id'=>$paymentId,'document_id'=>$docId,'document_number'=>$number],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'Quittung zur Zahlung erzeugt: '.$number,Auth::user()['id']??null]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    if(!$send)return ['created'=>true,'sent'=>false,'document_id'=>$docId,'document_number'=>$number,'message'=>'Quittung wurde erzeugt.'];
    $subject='Quittung zu Ihrer Buchung '.$booking['reference'];
    $text="Guten Tag ".$booking['guest_name'].",\n\nanbei erhalten Sie die Quittung zur Zahlung ".($payment['payment_number']??'').".\nBetrag: ".v223_money((float)$payment['amount'],(string)($booking['currency']??setting('currency','EUR')))."\n\nVielen Dank.";
    $url=v223_customer_url($bookingId);if($url!=='')$text.="\n\nKundenbereich: ".$url;
    $text.="\n\nMit freundlichen Grüßen\n".(string)setting('property_name','StayPilot');
    $html='<p>Guten Tag '.e((string)$booking['guest_name']).',</p><p>anbei erhalten Sie die Quittung zur Zahlung <b>'.e((string)($payment['payment_number']??'')).'</b>.</p><p>Betrag: <b>'.e(v223_money((float)$payment['amount'],(string)($booking['currency']??setting('currency','EUR')))).'</b></p>'.($url!==''?'<p><a href="'.e($url).'">Kundenbereich öffnen</a></p>':'').'<p>Mit freundlichen Grüßen<br>'.e((string)setting('property_name','StayPilot')).'</p>';
    $mail=v230_send_customer_message($bookingId,'payment_receipt',$subject,$text,$html,[[ 'path'=>root_path($pdfPath),'name'=>$number.'.pdf','mime'=>'application/pdf']],$docId);
    return ['created'=>true,'sent'=>(bool)$mail['sent'],'document_id'=>$docId,'document_number'=>$number,'message'=>(string)$mail['message']];
}

/* StayPilot V2.3.6.53 – Rechnungen, Gutschriften, Storno und Rückzahlung im bestehenden Abrechnungsbereich. */
function record_refund_v23653(): never
{
    $d=request_data();
    $bookingId=(int)($d['booking_id']??0); if($bookingId<=0)throw new ValidationException('Buchung fehlt.');
    BookingWorkflowService::syncBillingState($bookingId);
    $booking=v223_booking_row($bookingId);
    $amount=round((float)str_replace(',','.',(string)($d['amount']??0)),2); if($amount<=0)throw new ValidationException('Der Rückzahlungsbetrag muss größer als 0 sein.');
    $paid=(float)($booking['paid_amount']??0);
    if($amount>$paid+0.01)throw new ValidationException('Die Rückzahlung darf nicht größer als der bisher erhaltene Betrag sein.');
    $date=trim((string)($d['refund_date']??date('Y-m-d'))); if(!valid_date($date))throw new ValidationException('Das Rückzahlungsdatum ist ungültig.');
    $method=trim((string)($d['payment_method']??'bank_transfer')); if($method==='')$method='bank_transfer';
    $reference=mb_substr(trim((string)($d['reference']??'')),0,190);
    $reason=mb_substr(trim((string)($d['reason']??'')),0,1500);
    $createDoc=normalize_bool($d['create_document']??1);
    $sendDoc=normalize_bool($d['send_email']??0);
    $number=v223_next_number('refund','RF');
    $paymentId=0;
    db()->beginTransaction();
    try{
        db()->prepare('INSERT INTO booking_payments(booking_id,payment_number,payment_date,amount,payment_method,reference,note,status,created_by) VALUES(?,?,?,?,?,?,?,?,?)')
            ->execute([$bookingId,$number,$date,$amount,$method,$reference?:null,$reason?:null,'refunded',Auth::user()['id']??null]);
        $paymentId=(int)db()->lastInsertId();
        db()->prepare('INSERT INTO booking_change_log(booking_id,action,new_values_json,note,created_by) VALUES(?,?,?,?,?)')
            ->execute([$bookingId,'refund_recorded',json_encode(['payment_id'=>$paymentId,'refund_number'=>$number,'amount'=>$amount,'document_requested'=>$createDoc],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'Rückzahlung erfasst: '.$number,Auth::user()['id']??null]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    AuditLogger::record('booking',$bookingId,'refund_recorded',null,['refund_number'=>$number,'amount'=>$amount],'Rückzahlung erfasst');
    $doc=['created'=>false,'sent'=>false,'document_id'=>null,'message'=>'Kein Rückzahlungsbeleg erzeugt.'];
    if($createDoc){
        $title='Storno-/Rückzahlungsbeleg '.$booking['reference'].' / '.$number;
        $payment=['payment_number'=>$number,'amount'=>$amount,'payment_date'=>$date,'payment_method'=>$method,'reference'=>$reference,'note'=>$reason,'status'=>'refunded'];
        $doc=v23653_create_refund_statement($bookingId,$booking,$title,$amount,$reason,$payment,$sendDoc);
    }
    json_response(['ok'=>true,'message'=>'Rückzahlung wurde erfasst.'.($doc['created']?' Rückzahlungsbeleg wurde erzeugt.':'').($sendDoc?($doc['sent']?' Beleg wurde per E-Mail gesendet.':' Beleg-Mail nicht bestätigt: '.($doc['message']??'')):''),'refund_number'=>$number,'payment_id'=>$paymentId,'document'=>$doc]);
}

function v23653_create_refund_statement(int $bookingId,array $booking,string $title,float $amount,string $reason,array $payment,bool $send): array
{
    $number=v223_next_number('cancellation_statement','ST');
    $snapshot=v230_build_billing_document($bookingId,$booking,'cancellation_statement',$title,$number,$reason,$amount,$payment);
    $docId=0;$pdfPath='';
    db()->beginTransaction();
    try{
        db()->prepare('INSERT INTO booking_documents(booking_id,document_type,document_number,language,title,html_snapshot,status,created_by) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$bookingId,'cancellation_statement',$number,'de',$title,$snapshot['html'],'generated',Auth::user()['id']??null]);
        $docId=(int)db()->lastInsertId();
        $safe=preg_replace('/[^A-Za-z0-9_-]/','-',$number)?:'refund-statement';
        $dir=root_path('storage/documents/booking-confirmations'); if(!is_dir($dir))@mkdir($dir,0775,true);
        $pdfPath='storage/documents/booking-confirmations/'.$safe.'.pdf';
        $pdf=SimplePdf::create($title,$snapshot['lines']);
        if(@file_put_contents(root_path($pdfPath),$pdf,LOCK_EX)===false)throw new RuntimeException('Der Rückzahlungsbeleg konnte nicht gespeichert werden.');
        db()->prepare('UPDATE booking_documents SET pdf_path=?,checksum_sha256=? WHERE id=?')->execute([$pdfPath,hash_file('sha256',root_path($pdfPath))?:null,$docId]);
        db()->prepare('INSERT INTO booking_change_log(booking_id,action,new_values_json,note,created_by) VALUES(?,?,?,?,?)')
            ->execute([$bookingId,'refund_statement_created',json_encode(['document_id'=>$docId,'document_number'=>$number,'amount'=>$amount],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'Storno-/Rückzahlungsbeleg erzeugt: '.$number,Auth::user()['id']??null]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    if(!$send)return ['created'=>true,'sent'=>false,'document_id'=>$docId,'document_number'=>$number,'message'=>'Rückzahlungsbeleg erzeugt.'];
    $mail=v230_send_customer_message($bookingId,'refund_statement',$snapshot['email_subject'],$snapshot['email_text'],$snapshot['email_html'],[['path'=>root_path($pdfPath),'name'=>$number.'.pdf','mime'=>'application/pdf']],$docId);
    return ['created'=>true,'sent'=>(bool)$mail['sent'],'document_id'=>$docId,'document_number'=>$number,'message'=>(string)$mail['message']];
}
