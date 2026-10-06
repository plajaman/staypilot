<?php
declare(strict_types=1);

/**
 * Sicherer Übergang vom angenommenen Angebot zur bestätigten Buchung.
 * Buchung, Zahlungsplan, Kundenzugang und Dokument-Schnappschuss werden
 * gemeinsam aufgebaut; SMTP erfolgt erst nach erfolgreichem Commit.
 */
final class BookingWorkflowService
{
    public const LANGUAGES=['de','en','es','fr','it','pt','ca'];

    public static function queue(): array
    {
        self::refreshOverdueStatuses();
        if (function_exists('ensure_delete_center_v23663')) ensure_delete_center_v23663();
        $sql="SELECT o.id,o.offer_number,o.guest_name,o.guest_email,o.language,o.apartment_type_id,o.arrival,o.departure,o.adults,o.children,o.babies,o.pets,o.total_amount,o.deposit_amount,o.deposit_due_date,o.accepted_at,
            (SELECT e.event_type FROM offer_events e WHERE e.offer_id=o.id AND e.event_type LIKE 'no_availability_%' ORDER BY e.id DESC LIMIT 1) availability_event_type,
            (SELECT e.created_at FROM offer_events e WHERE e.offer_id=o.id AND e.event_type LIKE 'no_availability_%' ORDER BY e.id DESC LIMIT 1) availability_event_at,
            at.name apartment_type_name,
            (SELECT COUNT(*) FROM apartments a WHERE a.apartment_type_id=o.apartment_type_id AND a.status='active' AND a.out_of_service=0) apartment_count
            FROM offers o
            LEFT JOIN apartment_types at ON at.id=o.apartment_type_id
            LEFT JOIN guests g ON g.id=o.guest_id
            WHERE o.status='accepted'
              AND o.booking_id IS NULL
              AND (o.deleted_at IS NULL OR o.deleted_at='0000-00-00 00:00:00')
              AND (g.id IS NULL OR g.deleted_at IS NULL OR g.deleted_at='0000-00-00 00:00:00')
              AND NOT EXISTS (
                SELECT 1 FROM guests gd
                WHERE gd.deleted_at IS NOT NULL
                  AND gd.email IS NOT NULL AND gd.email<>''
                  AND o.guest_email IS NOT NULL AND o.guest_email<>''
                  AND LOWER(TRIM(gd.email))=LOWER(TRIM(o.guest_email))
              )
              AND COALESCE((SELECT e.event_type FROM offer_events e WHERE e.offer_id=o.id AND e.event_type LIKE 'no_availability_%' ORDER BY e.id DESC LIMIT 1),'')<>'no_availability_cancel'
            ORDER BY o.accepted_at ASC,o.id ASC";
        return db()->query($sql)->fetchAll();
    }

    public static function confirmationData(int $offerId): array
    {
        $offer=OfferService::get($offerId);
        if((string)$offer['status']!=='accepted'||(int)$offer['booking_id'])throw new ConflictException('Dieses Angebot wartet nicht mehr auf eine Buchungsbestätigung.');
        $stmt=db()->prepare("SELECT a.id,a.name,a.code,a.apartment_number,a.apartment_type_id,a.max_guests,h.name house_name
            FROM apartments a LEFT JOIN houses h ON h.id=a.house_id
            WHERE a.apartment_type_id=? AND a.status='active' AND a.out_of_service=0
            ORDER BY h.sort_order,h.name,a.sort_order,a.name");
        $stmt->execute([(int)$offer['apartment_type_id']]);
        $apartments=[];
        $people=(int)$offer['adults']+(int)$offer['children']+(int)$offer['babies'];
        $input=is_array($offer['calculation_input']??null)?$offer['calculation_input']:[];
        $capacityOverride=normalize_bool($input['capacity_override']??0);
        foreach($stmt->fetchAll() as $apartment){
            $conflict=booking_conflict((int)$apartment['id'],(string)$offer['arrival'],(string)$offer['departure']);
            $maxGuests=max(0,(int)($apartment['max_guests']??0));
            $capacityOk=$maxGuests===0||$people<=$maxGuests;
            $apartment['conflict']=$conflict;
            $apartment['capacity_ok']=$capacityOk;
            $apartment['capacity_warning']=!$capacityOk;
            $apartment['available']=!$conflict&&($capacityOk||$capacityOverride);
            $apartments[]=$apartment;
        }
        $language=self::language((string)$offer['language']);
        $template=self::template($language);
        $remainingDue=self::defaultRemainingDueDate((string)$offer['arrival']);
        $depositDue=valid_date((string)($offer['deposit_due_date']??''))?(string)$offer['deposit_due_date']:(new DateTimeImmutable('today'))->modify('+'.max(0,(int)setting('booking_default_deposit_due_days',7)).' days')->format('Y-m-d');
        if (valid_date($depositDue) && valid_date($remainingDue) && $remainingDue < $depositDue) {
            $remainingDue = $depositDue;
        }
        $defaultCheckin=self::formatTime((string)setting('checkin_time','16:00'));
        $defaultCheckout=self::formatTime((string)setting('checkout_time','10:00'));
        return ['offer'=>$offer,'apartments'=>$apartments,'alternatives'=>self::alternativeApartments($offer),'date_suggestions'=>self::dateSuggestions($offer),'template'=>$template,'defaults'=>[
            'deposit_required'=>(float)$offer['deposit_amount']>0?1:0,
            'deposit_due_date'=>$depositDue,
            'remaining_due_date'=>$remainingDue,
            'send_email'=>(int)setting('booking_confirmation_email_enabled',1),
            'attach_pdf'=>(int)setting('booking_confirmation_pdf_enabled',1),
            'checkin_time'=>$defaultCheckin,
            'checkout_time'=>$defaultCheckout,
            'arrival_info'=>'Bitte melden Sie sich vor der Anreise, falls sich Ihre voraussichtliche Ankunftszeit ändert.',
            'key_info'=>'Die Schlüsselübergabe und weitere Zugangsinformationen erhalten Sie rechtzeitig vor der Anreise.',
            'checkout_info'=>'Bitte geben Sie die Unterkunft am Abreisetag bis zur angegebenen Check-out-Zeit frei.',
            'create_arrival_pdf'=>1,
            'create_payment_pdf'=>1,
        ]];
    }

    public static function alternativeApartments(array $offer,int $limit=14): array
    {
        $people=(int)$offer['adults']+(int)$offer['children']+(int)$offer['babies'];
        $input=is_array($offer['calculation_input']??null)?$offer['calculation_input']:[];
        $capacityOverride=normalize_bool($input['capacity_override']??0);
        $offeredTypeId=(int)($offer['apartment_type_id']??0);
        $offeredType=['standard_price'=>0,'max_occupancy'=>0];
        if($offeredTypeId>0){
            $typeStmt=db()->prepare('SELECT standard_price,max_occupancy FROM apartment_types WHERE id=? LIMIT 1');
            $typeStmt->execute([$offeredTypeId]);
            $offeredType=$typeStmt->fetch()?:$offeredType;
        }
        $nights=max(1,(new DateTimeImmutable((string)$offer['arrival']))->diff(new DateTimeImmutable((string)$offer['departure']))->days);
        $sql="SELECT a.id,a.name,a.code,a.apartment_number,a.apartment_type_id,a.max_guests,h.name house_name,at.name apartment_type_name,at.max_occupancy type_max_occupancy,at.bedrooms,at.beds,at.living_area,at.standard_price,at.sort_order type_sort
            FROM apartments a LEFT JOIN houses h ON h.id=a.house_id LEFT JOIN apartment_types at ON at.id=a.apartment_type_id
            WHERE a.status='active' AND a.out_of_service=0";
        $params=[];
        if($offeredTypeId>0){$sql.=' AND a.apartment_type_id<>?';$params[]=$offeredTypeId;}
        $sql.=' ORDER BY at.sort_order,at.name,h.sort_order,h.name,a.sort_order,a.name LIMIT 250';
        $stmt=db()->prepare($sql);$stmt->execute($params);
        $rows=[];
        foreach($stmt->fetchAll() as $apartment){
            if(booking_conflict((int)$apartment['id'],(string)$offer['arrival'],(string)$offer['departure']))continue;
            $maxGuests=max(0,(int)($apartment['max_guests']??0));
            if($maxGuests>0&&$people>$maxGuests&&!$capacityOverride)continue;
            $typeMax=max($maxGuests,(int)($apartment['type_max_occupancy']??0));
            $offeredMax=(int)($offeredType['max_occupancy']??0);
            $mode=($offeredMax>0&&$typeMax>=$offeredMax)?'upgrade':'alternative';
            $standardDiff=round(((float)($apartment['standard_price']??0)-(float)($offeredType['standard_price']??0))*$nights,2);
            $apartment['available']=true;
            $apartment['capacity_warning']=$maxGuests>0&&$people>$maxGuests;
            $apartment['mode']=$mode;
            $apartment['nights']=$nights;
            $apartment['estimated_standard_diff']=$standardDiff;
            $rows[]=$apartment;
        }
        usort($rows,static function(array $a,array $b): int{
            $rank=['upgrade'=>0,'alternative'=>1];
            return ($rank[$a['mode']]??9)<=>($rank[$b['mode']]??9) ?: abs((float)$a['estimated_standard_diff'])<=>abs((float)$b['estimated_standard_diff']) ?: strnatcasecmp((string)($a['apartment_type_name']??''),(string)($b['apartment_type_name']??''));
        });
        return array_slice($rows,0,max(0,$limit));
    }

    public static function dateSuggestions(array $offer,int $limit=8): array
    {
        $typeId=(int)($offer['apartment_type_id']??0);
        if($typeId<=0)return [];
        $arrival=new DateTimeImmutable((string)$offer['arrival']);
        $departure=new DateTimeImmutable((string)$offer['departure']);
        $nights=max(1,$arrival->diff($departure)->days);
        $people=(int)$offer['adults']+(int)$offer['children']+(int)$offer['babies'];
        $input=is_array($offer['calculation_input']??null)?$offer['calculation_input']:[];
        $capacityOverride=normalize_bool($input['capacity_override']??0);
        $stmt=db()->prepare("SELECT a.id,a.name,a.code,a.apartment_number,a.max_guests,h.name house_name FROM apartments a LEFT JOIN houses h ON h.id=a.house_id WHERE a.apartment_type_id=? AND a.status='active' AND a.out_of_service=0 ORDER BY h.sort_order,h.name,a.sort_order,a.name");
        $stmt->execute([$typeId]);
        $apartments=$stmt->fetchAll();
        $today=new DateTimeImmutable('today');
        $suggestions=[];
        foreach(array_merge(range(-7,-1),range(1,21)) as $offset){
            $from=$arrival->modify(($offset>=0?'+':'').$offset.' days');
            if($from<$today)continue;
            $to=$from->modify('+'.$nights.' days');
            $free=[];
            foreach($apartments as $apartment){
                $maxGuests=max(0,(int)($apartment['max_guests']??0));
                if($maxGuests>0&&$people>$maxGuests&&!$capacityOverride)continue;
                if(!booking_conflict((int)$apartment['id'],$from->format('Y-m-d'),$to->format('Y-m-d'))){
                    $free[]=$apartment;
                    if(count($free)>=3)break;
                }
            }
            if($free){
                $suggestions[]=['arrival'=>$from->format('Y-m-d'),'departure'=>$to->format('Y-m-d'),'offset_days'=>$offset,'free_count'=>count($free),'apartments'=>$free];
                if(count($suggestions)>=$limit)break;
            }
        }
        return $suggestions;
    }

    public static function noAvailabilityAction(int $offerId,string $mode,array $data): array
    {
        $mode=in_array($mode,['hold','notify','cancel'],true)?$mode:'hold';
        $offer=OfferService::get($offerId);
        if((string)$offer['status']!=='accepted'||(int)$offer['booking_id'])throw new ConflictException('Dieses Angebot wartet nicht mehr auf interne Klärung.');
        $note=mb_substr(trim((string)($data['note']??'')),0,1500);
        if($note==='')$note='Keine passende Wohnung frei – interne Klärung erforderlich.';
        $emailSent=false;$emailError='';
        if($mode==='notify'||($mode==='cancel'&&normalize_bool($data['send_email']??1))){
            $subject=mb_substr(trim(strip_tags((string)($data['subject']??''))),0,255);
            $message=mb_substr(trim((string)($data['message']??'')),0,8000);
            if($subject===''||$message==='')throw new ValidationException('Betreff und Nachricht an den Gast sind erforderlich.');
            if(!filter_var((string)$offer['guest_email'],FILTER_VALIDATE_EMAIL))throw new ValidationException('Für den Gast ist keine gültige E-Mail-Adresse hinterlegt.');
            $html='<!doctype html><html lang="de"><head><meta charset="utf-8"></head><body style="font-family:Arial,sans-serif;line-height:1.55;color:#172033">'.nl2br(e($message)).'</body></html>';
            try{
                $result=SmtpMailer::send((string)$offer['guest_email'],$subject,$message,$html,[]);
                $emailSent=true;
                CommunicationLogger::record('email','offer',$offerId,(string)$offer['guest_name'],(string)$offer['guest_email'],$subject,$message,'sent',(string)($result['message_id']??''));
            }catch(Throwable $e){
                foreach($generatedFixedAttachmentPaths as $tmpAttachmentPath){ if(is_file($tmpAttachmentPath)) @unlink($tmpAttachmentPath); }
                $emailError=$e->getMessage();
                CommunicationLogger::record('email','offer',$offerId,(string)$offer['guest_name'],(string)$offer['guest_email'],$subject,$message,'failed',$emailError);
                AppLogger::error($e,['offer_id'=>$offerId],'booking-no-availability-email');
                throw new RuntimeException('Die Nachricht konnte nicht versendet werden: '.$emailError);
            }
        }
        $old=['status'=>$offer['status'],'internal_notes'=>$offer['internal_notes']??''];
        $newNote=self::appendInternalNote((string)($offer['internal_notes']??''),$note,$mode);
        $params=[$newNote,Auth::user()['id']??null,$offerId];
        $sql="UPDATE offers SET internal_notes=?,updated_by=?,updated_at=NOW() WHERE id=? AND status='accepted' AND booking_id IS NULL";
        if($mode==='cancel'){
            $sql="UPDATE offers SET internal_notes=?,updated_by=?,updated_at=NOW(),status='archived',archived_at=NOW() WHERE id=? AND status='accepted' AND booking_id IS NULL";
        }
        $stmt=db()->prepare($sql);$stmt->execute($params);
        if($stmt->rowCount()!==1)throw new ConflictException('Das Angebot wurde parallel bereits verarbeitet.');
        self::recordOfferEvent($offerId,'no_availability_'.$mode,$old,['status'=>$mode==='cancel'?'archived':'accepted','note'=>$note,'email_sent'=>$emailSent]);
        try{
            HousekeepingWorkflow::notifyRoles(['admin','manager','reception'],'offer_no_availability_'.$mode,$mode==='cancel'?'Anfrage wegen fehlender Verfügbarkeit archiviert':'Buchungsbestätigung braucht Klärung',$offer['guest_name'].' · '.$offer['offer_number'],'offer',$offerId,'#dashboard');
        }catch(Throwable $e){AppLogger::error($e,['offer_id'=>$offerId,'mode'=>$mode],'booking-no-availability-notification');}
        return ['mode'=>$mode,'email_sent'=>$emailSent,'email_error'=>$emailError,'offer'=>OfferService::get($offerId)];
    }


    public static function normalizeOptions(array $data,array $offer): array
    {
        $language=self::language((string)($data['language']??$offer['language']??'de'));
        $template=self::template($language);
        $depositRequired=normalize_bool($data['deposit_required']??1);
        $waiveReason=mb_substr(trim((string)($data['deposit_waived_reason']??'')),0,500);
        if(!$depositRequired&&(float)$offer['deposit_amount']>0&&$waiveReason==='')throw new ValidationException('Bitte begründen Sie, warum keine Anzahlung verlangt wird.');
        $depositDue=trim((string)($data['deposit_due_date']??''));
        if($depositRequired&&$depositDue!==''&&!valid_date($depositDue))throw new ValidationException('Das Fälligkeitsdatum der Anzahlung ist ungültig.');
        $remainingDue=trim((string)($data['remaining_due_date']??''));
        if($remainingDue!==''&&!valid_date($remainingDue))throw new ValidationException('Das Fälligkeitsdatum des Restbetrags ist ungültig.');
        if($depositRequired&&$depositDue!==''&&$remainingDue!==''&&valid_date($depositDue)&&valid_date($remainingDue)&&$remainingDue<$depositDue){
            throw new ValidationException('Der Restbetrag darf nicht vor der Anzahlung fällig sein. Bitte Zahlungsdaten prüfen.');
        }
        $allowAlternative=normalize_bool($data['allow_alternative_apartment']??0);
        $alternativeNote=mb_substr(trim((string)($data['alternative_assignment_note']??'')),0,800);
        if($allowAlternative&&$alternativeNote==='')throw new ValidationException('Bitte begründen Sie die alternative Wohnungszuweisung.');
        $fields=[];
        foreach(['email_subject','greeting','intro','additional_info','closing','signature','pdf_title'] as $field){
            $raw=(string)($data[$field]??$template[$field]??'');
            $fields[$field]=$field==='email_subject'||$field==='pdf_title'?mb_substr(trim(strip_tags($raw)),0,$field==='email_subject'?255:190):self::safeRich($raw);
        }
        $checkinTime=self::normalizeTime($data['checkin_time']??setting('checkin_time','16:00'),'Check-in-Zeit');
        $checkoutTime=self::normalizeTime($data['checkout_time']??setting('checkout_time','10:00'),'Check-out-Zeit');
        $arrivalInfo=self::safeRich((string)($data['arrival_info']??''));
        $keyInfo=self::safeRich((string)($data['key_info']??''));
        $checkoutInfo=self::safeRich((string)($data['checkout_info']??''));
        $pdfExtraInfo=self::safeRich((string)($data['pdf_extra_info']??''));
        return array_merge($fields,[
            'workflow'=>true,'language'=>$language,'deposit_required'=>$depositRequired,
            'deposit_due_date'=>$depositRequired?($depositDue?:null):null,
            'deposit_waived_reason'=>$depositRequired?null:$waiveReason,
            'remaining_due_date'=>$remainingDue?:self::defaultRemainingDueDate((string)$offer['arrival']),
            'send_email'=>normalize_bool($data['send_email']??1),
            'attach_pdf'=>normalize_bool($data['attach_pdf']??1),
            'checkin_time'=>$checkinTime,
            'checkout_time'=>$checkoutTime,
            'arrival_info'=>$arrivalInfo,
            'key_info'=>$keyInfo,
            'checkout_info'=>$checkoutInfo,
            'pdf_extra_info'=>$pdfExtraInfo,
            'create_arrival_pdf'=>normalize_bool($data['create_arrival_pdf']??1),
            'create_payment_pdf'=>normalize_bool($data['create_payment_pdf']??1),
            'allow_alternative_apartment'=>$allowAlternative,
            'alternative_assignment_note'=>$allowAlternative?$alternativeNote:null,
        ]);
    }

    /** Muss innerhalb der von OfferService geöffneten Transaktion laufen. */
    public static function initializeInTransaction(int $bookingId,array $offer,array $options,array $user): array
    {
        $options=self::normalizeOptions($options,$offer);
        $deposit=(float)$offer['deposit_amount'];
        if(!$options['deposit_required'])$deposit=0.0;
        $remaining=max(0,round((float)$offer['total_amount']-$deposit,2));
        $now=date('Y-m-d H:i:s');
        db()->prepare("UPDATE bookings SET source_offer_id=?,confirmed_at=?,confirmed_by=?,planned_arrival_time=?,planned_departure_time=?,deposit_amount=?,deposit_required=?,deposit_due_date=?,deposit_status=?,deposit_waived_reason=?,remaining_due_date=?,remaining_status=?,payment_status='open' WHERE id=?")
            ->execute([(int)$offer['id'],$now,(int)$user['id'],$options['checkin_time'],$options['checkout_time'],$deposit,$options['deposit_required'],$options['deposit_due_date'],$options['deposit_required']?'open':'waived',$options['deposit_waived_reason'],$options['remaining_due_date'],$remaining>0?'open':'received',$bookingId]);

        $depositStatus=$options['deposit_required']?((!empty($options['deposit_due_date'])&&$options['deposit_due_date']<date('Y-m-d'))?'overdue':'open'):'waived';
        $remainingStatus=$remaining<=0?'received':((!empty($options['remaining_due_date'])&&$options['remaining_due_date']<date('Y-m-d'))?'overdue':'open');
        db()->prepare('UPDATE bookings SET deposit_status=?,remaining_status=? WHERE id=?')->execute([$depositStatus,$remainingStatus,$bookingId]);
        $schedule=db()->prepare('INSERT INTO booking_payment_schedule(booking_id,installment_type,label,amount,due_date,status,paid_amount,waived_reason,sort_order) VALUES(?,?,?,?,?,?,?,?,?)');
        $schedule->execute([$bookingId,'deposit',self::label($options['language'],'deposit'),$deposit,$options['deposit_due_date'],$depositStatus,0,$options['deposit_waived_reason'],10]);
        $schedule->execute([$bookingId,'remaining',self::label($options['language'],'remaining'),$remaining,$options['remaining_due_date'],$remainingStatus,0,null,20]);

        $token=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
        $validUntil=(new DateTimeImmutable((string)$offer['departure']))->modify('+'.max(1,(int)setting('booking_customer_access_days_after_departure',30)).' days')->format('Y-m-d 23:59:59');
        db()->prepare('INSERT INTO booking_customer_access(booking_id,token_hash,token_encrypted,active,valid_until) VALUES(?,?,?,?,?)')
            ->execute([$bookingId,hash('sha256',$token),Crypto::encrypt($token),1,$validUntil]);

        $booking=self::bookingRecord($bookingId);
        $documentNumber=self::nextNumber('booking_confirmation','BST');
        $customerUrl=self::customerUrl($token);
        $html=self::renderConfirmationHtml($booking,$offer,$options,$customerUrl,$deposit,$remaining);
        db()->prepare('INSERT INTO booking_documents(booking_id,document_type,document_number,language,title,html_snapshot,status,created_by) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$bookingId,'booking_confirmation',$documentNumber,$options['language'],self::replacePlain($options['pdf_title'],$booking,$offer),$html,'generated',(int)$user['id']]);
        $documentId=(int)db()->lastInsertId();
        $documentIds=[$documentId];
        if(!empty($options['create_arrival_pdf'])){
            $arrivalNumber=self::nextNumber('arrival_information','ANR');
            $arrivalTitle=self::label($options['language'],'arrival_document_title');
            $arrivalHtml=self::renderArrivalHtml($booking,$offer,$options,$customerUrl);
            db()->prepare('INSERT INTO booking_documents(booking_id,document_type,document_number,language,title,html_snapshot,status,created_by) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$bookingId,'arrival_information',$arrivalNumber,$options['language'],$arrivalTitle,$arrivalHtml,'generated',(int)$user['id']]);
            $documentIds[]=(int)db()->lastInsertId();
        }
        if(!empty($options['create_payment_pdf'])){
            $paymentNumber=self::nextNumber('payment_overview','ZAHL');
            $paymentTitle=self::label($options['language'],'payment_document_title');
            $paymentHtml=self::renderPaymentHtml($booking,$offer,$options,$customerUrl,$deposit,$remaining);
            db()->prepare('INSERT INTO booking_documents(booking_id,document_type,document_number,language,title,html_snapshot,status,created_by) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$bookingId,'payment_overview',$paymentNumber,$options['language'],$paymentTitle,$paymentHtml,'generated',(int)$user['id']]);
            $documentIds[]=(int)db()->lastInsertId();
        }
        db()->prepare('INSERT INTO booking_change_log(booking_id,action,new_values_json,note,created_by) VALUES(?,?,?,?,?)')
            ->execute([$bookingId,'offer_confirmed',json_encode(['offer_id'=>(int)$offer['id'],'document_ids'=>$documentIds,'deposit'=>$deposit,'remaining'=>$remaining],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'Angebot geprüft und als Buchung bestätigt',(int)$user['id']]);
        return ['booking_id'=>$bookingId,'document_id'=>$documentId,'document_ids'=>$documentIds,'document_number'=>$documentNumber,'token'=>$token,'customer_url'=>$customerUrl,'options'=>$options,'deposit'=>$deposit,'remaining'=>$remaining];
    }

    public static function finalizeAfterCommit(array $context): array
    {
        $booking=self::bookingRecord((int)$context['booking_id']);
        $documentIds=array_values(array_unique(array_map('intval',(array)($context['document_ids']??[$context['document_id']??0]))));
        $documents=[];
        foreach($documentIds as $docId){
            if($docId<=0)continue;
            $docStmt=db()->prepare('SELECT * FROM booking_documents WHERE id=? AND booking_id=? LIMIT 1');
            $docStmt->execute([$docId,(int)$context['booking_id']]);
            $document=$docStmt->fetch();
            if($document)$documents[]=$document;
        }
        if(!$documents)throw new RuntimeException('Die Buchungsdokumente wurden nicht gefunden.');
        $pdfPaths=[];
        foreach($documents as $document){
            $pdfPath=self::writeDocumentPdf($booking,$document,$context);
            if($pdfPath)$pdfPaths[(int)$document['id']]=$pdfPath;
        }
        $emailSent=false;$emailError='';
        if(!empty($context['options']['send_email'])&&!filter_var((string)$booking['guest_email'],FILTER_VALIDATE_EMAIL))$emailError='Für den Gast ist keine gültige E-Mail-Adresse hinterlegt.';
        if(!empty($context['options']['send_email'])&&filter_var((string)$booking['guest_email'],FILTER_VALIDATE_EMAIL)){
            $subject=self::replacePlain((string)$context['options']['email_subject'],$booking,['offer_number'=>$booking['offer_number']??'']);
            $email=self::renderEmail($booking,$context,$subject);
            $attachments=[];
            if(!empty($context['options']['attach_pdf'])){
                foreach($pdfPaths as $path){
                    if($path&&is_file(root_path($path)))$attachments[]=['path'=>root_path($path),'name'=>basename($path),'mime'=>'application/pdf'];
                }
            }
            $mailKey='booking_confirmation';
            $generatedFixedAttachmentPaths=[];
            foreach(DocumentTemplateService::attachments($mailKey) as $fixedAttachment){
                if(empty($fixedAttachment['active']))continue;
                $templateId=DocumentTemplateService::resolveAttachmentTemplateId($fixedAttachment,$booking);
                if($templateId<=0)continue;
                $attachContext=[
                    'portal_url'=>(string)($context['customer_url']??''),
                    'checkin_url'=>(string)($context['checkin_url']??''),
                    'payment_status'=>(string)($booking['payment_status']??''),
                    'language'=>(string)($booking['public_language']??'de'),
                ];
                $rendered=DocumentTemplateService::renderAttachmentPdf($templateId,$booking,$attachContext,(string)($fixedAttachment['filename_template']??''));
                if($rendered&&is_file((string)$rendered['path'])){
                    $attachments[]=['path'=>(string)$rendered['path'],'name'=>(string)$rendered['name'],'mime'=>'application/pdf'];
                    if(empty($fixedAttachment['store_copy']))$generatedFixedAttachmentPaths[]=(string)$rendered['path'];
                }
            }
            try{
                $result=SmtpMailer::send((string)$booking['guest_email'],$subject,$email['text'],$email['html'],$attachments);
                foreach($generatedFixedAttachmentPaths as $tmpAttachmentPath){ if(is_file($tmpAttachmentPath)) @unlink($tmpAttachmentPath); }
                $emailSent=true;
                CommunicationLogger::record('email','booking',(int)$booking['id'],(string)$booking['guest_name'],(string)$booking['guest_email'],$subject,$email['text'],'sent',(string)($result['message_id']??''));
                db()->prepare('UPDATE bookings SET confirmation_email_sent_at=NOW() WHERE id=?')->execute([$booking['id']]);
                $marks=implode(',',array_fill(0,count($documents),'?'));
                $params=array_map(static fn(array $doc):int=>(int)$doc['id'],$documents);
                db()->prepare("UPDATE booking_documents SET sent_at=NOW(),status='sent' WHERE id IN ($marks)")->execute($params);
            }catch(Throwable $e){
                foreach($generatedFixedAttachmentPaths as $tmpAttachmentPath){ if(is_file($tmpAttachmentPath)) @unlink($tmpAttachmentPath); }
                $emailError=$e->getMessage();
                CommunicationLogger::record('email','booking',(int)$booking['id'],(string)$booking['guest_name'],(string)$booking['guest_email'],$subject,$email['text'],'failed',$emailError);
                AppLogger::error($e,['booking_id'=>$booking['id']],'booking-confirmation-email');
            }
        }
        return ['booking'=>self::bookingRecord((int)$booking['id']),'document_id'=>(int)$documents[0]['id'],'document_ids'=>array_map(static fn(array $doc):int=>(int)$doc['id'],$documents),'pdf_path'=>reset($pdfPaths)?:null,'pdf_paths'=>array_values($pdfPaths),'customer_url'=>$context['customer_url'],'email_sent'=>$emailSent,'email_error'=>$emailError];
    }

    public static function publicByToken(string $token): array
    {
        if(strlen($token)<30)throw new NotFoundException('Kundenzugang nicht gefunden.');
        self::refreshOverdueStatuses();
        $stmt=db()->prepare("SELECT ca.id access_id,ca.booking_id,ca.active access_active,ca.valid_until access_valid_until,ca.last_viewed_at,
            b.reference,b.guest_id,b.apartment_id,b.apartment_type_id,b.arrival,b.departure,b.planned_arrival_time,b.planned_departure_time,b.adults,b.children,b.babies,b.pets,b.status,b.public_language,b.total_price,b.paid_amount,b.payment_status,b.deposit_amount,b.deposit_required,b.deposit_due_date,b.deposit_status,b.deposit_waived_reason,b.remaining_due_date,b.remaining_status,b.tourist_tax,b.discount_amount,b.confirmed_at,b.confirmation_email_sent_at,b.is_upgrade,b.upgrade_note,
            TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,g.email guest_email,COALESCE(at.name,aat.name) apartment_type_name,a.name apartment_name,a.code apartment_code,o.offer_number,o.currency
            FROM booking_customer_access ca JOIN bookings b ON b.id=ca.booking_id JOIN guests g ON g.id=b.guest_id
            LEFT JOIN apartments a ON a.id=b.apartment_id LEFT JOIN apartment_types at ON at.id=b.apartment_type_id LEFT JOIN apartment_types aat ON aat.id=a.apartment_type_id LEFT JOIN offers o ON o.id=b.source_offer_id
            WHERE ca.token_hash=? AND ca.active=1 AND (ca.valid_until IS NULL OR ca.valid_until>=NOW()) LIMIT 1");
        $stmt->execute([hash('sha256',$token)]);$booking=$stmt->fetch();if(!$booking)throw new NotFoundException('Kundenzugang nicht gefunden oder abgelaufen.');
        self::syncBillingState((int)$booking['booking_id']);
        $stmt->execute([hash('sha256',$token)]);$booking=$stmt->fetch();if(!$booking)throw new NotFoundException('Kundenzugang nicht gefunden oder abgelaufen.');
        db()->prepare('UPDATE booking_customer_access SET last_viewed_at=NOW() WHERE id=?')->execute([$booking['access_id']]);
        $s=db()->prepare('SELECT * FROM booking_payment_schedule WHERE booking_id=? ORDER BY sort_order,id');$s->execute([$booking['booking_id']]);
        $d=db()->prepare("SELECT id,document_type,document_number,language,title,status,generated_at,sent_at,created_at,pdf_path FROM booking_documents WHERE booking_id=? AND status<>'archived' ORDER BY FIELD(document_type,'booking_confirmation','arrival_information','payment_overview','invoice','receipt','credit_note','cancellation'), id DESC");$d->execute([$booking['booking_id']]);
        $p=db()->prepare("SELECT payment_number,payment_date,amount,payment_method,reference,status,created_at FROM booking_payments WHERE booking_id=? AND status='received' ORDER BY payment_date DESC,id DESC");$p->execute([$booking['booking_id']]);
        $m=db()->prepare("SELECT channel,subject,status,detail,created_at FROM communication_log WHERE entity_type='booking' AND entity_id=? AND channel='email' ORDER BY id DESC LIMIT 12");$m->execute([$booking['booking_id']]);
        $booking['payment_schedule']=$s->fetchAll();$booking['documents']=$d->fetchAll();$booking['payments']=$p->fetchAll();$booking['emails']=$m->fetchAll();
        $paidStmt=db()->prepare("SELECT COALESCE(SUM(amount),0) FROM booking_payments WHERE booking_id=? AND status='received'");$paidStmt->execute([$booking['booking_id']]);
        $booking['paid_total']=(float)$paidStmt->fetchColumn();$booking['open_total']=max(0,(float)$booking['total_price']-(float)$booking['paid_total']);
        $needsClarification=empty($booking['apartment_id'])&&!in_array((string)$booking['status'],['cancelled','rejected'],true);
        $booking['needs_clarification']=$needsClarification?1:0;
        $booking['workflow_status_label']=$needsClarification?'Klärung erforderlich · konkrete Wohnung wird intern geprüft':(((int)($booking['is_upgrade']??0)===1)?'Alternative / Upgrade zugeordnet':(string)$booking['status']);
        $booking['workflow_status_class']=$needsClarification?'warning':(((int)($booking['is_upgrade']??0)===1)?'success':'ok');
        $updatedStmt=db()->prepare("SELECT MAX(ts) FROM (SELECT MAX(updated_at) ts FROM booking_payment_schedule WHERE booking_id=? UNION ALL SELECT MAX(updated_at) ts FROM booking_payments WHERE booking_id=? UNION ALL SELECT MAX(created_at) ts FROM booking_documents WHERE booking_id=?) x");$updatedStmt->execute([$booking['booking_id'],$booking['booking_id'],$booking['booking_id']]);
        $booking['customer_status_updated_at']=$updatedStmt->fetchColumn()?:null;$booking['token']=$token;
        return $booking;
    }

    public static function documentForToken(string $token,int $documentId): array
    {
        $booking=self::publicByToken($token);
        $stmt=db()->prepare('SELECT * FROM booking_documents WHERE id=? AND booking_id=? LIMIT 1');$stmt->execute([$documentId,$booking['booking_id']]);$doc=$stmt->fetch();if(!$doc)throw new NotFoundException('Dokument nicht gefunden.');
        return ['booking'=>$booking,'document'=>$doc];
    }

    public static function paymentAttention(): array
    {
        self::refreshOverdueStatuses();
        $activeBooking="(b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND COALESCE(b.status,'') NOT IN ('cancelled','rejected') AND COALESCE(b.accounting_mode,'internal')='internal'";
        $overdue=(int)db()->query("SELECT COUNT(*) FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id WHERE s.status='overdue' AND ".$activeBooking)->fetchColumn();
        $dueSoon=(int)db()->query("SELECT COUNT(*) FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id WHERE s.status='open' AND s.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND ".$activeBooking)->fetchColumn();
        return ['overdue'=>$overdue,'due_soon'=>$dueSoon];
    }

    private static bool $overdueRefreshedThisRequest = false;

    public static function refreshOverdueStatuses(): void
    {
        if(self::$overdueRefreshedThisRequest)return;
        self::$overdueRefreshedThisRequest = true;
        if(!self::tableAvailable('booking_payment_schedule'))return;
        self::syncBillingState();
        db()->exec("UPDATE booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id SET s.status='overdue' WHERE COALESCE(b.accounting_mode,'internal')='internal' AND s.status IN ('open','partial') AND s.amount>s.paid_amount AND s.due_date IS NOT NULL AND s.due_date<CURDATE()");
        db()->exec("UPDATE bookings b LEFT JOIN booking_payment_schedule d ON d.booking_id=b.id AND d.installment_type='deposit' LEFT JOIN booking_payment_schedule r ON r.booking_id=b.id AND r.installment_type='remaining' SET b.deposit_status=COALESCE(d.status,b.deposit_status),b.remaining_status=COALESCE(r.status,b.remaining_status)");
    }

    public static function syncBillingState(int $bookingId = 0): void
    {
        if(!self::tableAvailable('bookings') || !self::tableAvailable('booking_payment_schedule') || !self::tableAvailable('booking_payments'))return;
        $params=[];
        // Im Massenlauf (bookingId=0) bereits vollständig abgerechnete oder nicht intern
        // abzurechnende Buchungen überspringen - sonst skaliert dieser Sync pro Dashboard-Aufruf
        // linear mit der Gesamtzahl aller Buchungen (N+1-Problem bei vielen Buchungen).
        $where=$bookingId>0?' WHERE id=?':" WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') AND COALESCE(accounting_mode,'internal')='internal' AND NOT (payment_status='paid' AND COALESCE(deposit_status,'') IN ('received','waived') AND COALESCE(remaining_status,'') IN ('received','waived'))";
        if($bookingId>0)$params[]=$bookingId;
        $stmt=db()->prepare('SELECT id,total_price,paid_amount,payment_status,deposit_amount,deposit_due_date,remaining_due_date,public_language,arrival,accounting_mode,billing_excluded_reason FROM bookings'.$where);
        $stmt->execute($params);
        foreach($stmt->fetchAll() as $booking){
            $id=(int)$booking['id'];
            if (BookingAccountingService::normalizeMode($booking['accounting_mode'] ?? null, null) !== BookingAccountingService::MODE_INTERNAL) {
                BookingAccountingService::neutralizeInternalBilling(db(), $id, (string)($booking['accounting_mode'] ?? ''), (string)($booking['billing_excluded_reason'] ?? 'Nicht intern abrechnungsrelevant'));
                continue;
            }
            self::ensurePaymentSchedule($booking);
            $payStmt=db()->prepare("SELECT COUNT(*) cnt,COALESCE(SUM(amount),0) total FROM booking_payments WHERE booking_id=? AND status='received'");
            $payStmt->execute([$id]);
            $paymentInfo=$payStmt->fetch()?:['cnt'=>0,'total'=>0];
            $schedulePaidStmt=db()->prepare("SELECT COALESCE(SUM(paid_amount),0) FROM booking_payment_schedule WHERE booking_id=? AND status<>'waived'");
            $schedulePaidStmt->execute([$id]);
            $schedulePaid=(float)$schedulePaidStmt->fetchColumn();
            $currentPaid=(float)($booking['paid_amount']??0);
            $paid=((int)$paymentInfo['cnt']>0)?(float)$paymentInfo['total']:max($currentPaid,$schedulePaid);
            $paid=max(0.0,round($paid,2));
            $allocatedBySchedule=[];$hasAllocations=false;$allocatedTotal=0.0;
            if(self::tableAvailable('booking_payment_allocations')){
                $allocStmt=db()->prepare("SELECT a.schedule_id,COALESCE(SUM(a.amount),0) amount FROM booking_payment_allocations a JOIN booking_payments p ON p.id=a.payment_id WHERE p.booking_id=? AND p.status='received' AND a.schedule_id IS NOT NULL GROUP BY a.schedule_id");
                $allocStmt->execute([$id]);
                foreach($allocStmt->fetchAll() as $alloc){$allocatedBySchedule[(int)$alloc['schedule_id']]=round((float)$alloc['amount'],2);$allocatedTotal+=$allocatedBySchedule[(int)$alloc['schedule_id']];$hasAllocations=true;}
            }
            $remaining=$hasAllocations?max(0.0,round($paid-$allocatedTotal,2)):$paid;
            $s=db()->prepare('SELECT * FROM booking_payment_schedule WHERE booking_id=? ORDER BY sort_order,id');
            $s->execute([$id]);
            foreach($s->fetchAll() as $row){
                $amount=max(0.0,round((float)$row['amount'],2));
                $status=(string)($row['status']??'open');
                if($status==='waived'){
                    if((float)$row['paid_amount']!==0.0)db()->prepare("UPDATE booking_payment_schedule SET paid_amount=0 WHERE id=?")->execute([(int)$row['id']]);
                    continue;
                }
                if($hasAllocations){
                    $explicit=min($amount,max(0.0,(float)($allocatedBySchedule[(int)$row['id']]??0)));
                    $auto=min(max(0.0,$amount-$explicit),$remaining);
                    $remaining=max(0.0,$remaining-$auto);
                    $apply=$explicit+$auto;
                }else{
                    $apply=min($amount,$remaining);
                    $remaining=max(0.0,$remaining-$apply);
                }
                if($amount<=0.0){
                    $newPaid=0.0;$newStatus='received';
                } elseif($apply+0.005>=$amount){
                    $newPaid=$amount;$newStatus='received';
                } elseif($apply>0.005){
                    $newPaid=round($apply,2);
                    $newStatus=(!empty($row['due_date']) && (string)$row['due_date']<date('Y-m-d'))?'overdue':'partial';
                } else {
                    $newPaid=0.0;
                    $newStatus=(!empty($row['due_date']) && (string)$row['due_date']<date('Y-m-d'))?'overdue':'open';
                }
                if(abs((float)$row['paid_amount']-$newPaid)>0.004 || (string)$row['status']!==$newStatus){
                    db()->prepare('UPDATE booking_payment_schedule SET paid_amount=?,status=? WHERE id=?')->execute([$newPaid,$newStatus,(int)$row['id']]);
                }
            }
            $total=max(0.0,(float)($booking['total_price']??0));
            $paymentStatus=$paid<=0.005?'open':($paid+0.005>=$total?'paid':'partial');
            $dep=db()->prepare("SELECT status FROM booking_payment_schedule WHERE booking_id=? AND installment_type='deposit' LIMIT 1");$dep->execute([$id]);$depositStatus=$dep->fetchColumn()?:null;
            $rem=db()->prepare("SELECT status FROM booking_payment_schedule WHERE booking_id=? AND installment_type='remaining' LIMIT 1");$rem->execute([$id]);$remainingStatus=$rem->fetchColumn()?:null;
            db()->prepare('UPDATE bookings SET paid_amount=?,payment_status=?,deposit_status=COALESCE(?,deposit_status),remaining_status=COALESCE(?,remaining_status) WHERE id=?')->execute([$paid,$paymentStatus,$depositStatus,$remainingStatus,$id]);
        }
    }

    private static function ensurePaymentSchedule(array $booking): void
    {
        $id=(int)($booking['id']??0);
        if($id<=0)return;
        $countStmt=db()->prepare('SELECT COUNT(*) FROM booking_payment_schedule WHERE booking_id=?');
        $countStmt->execute([$id]);
        if((int)$countStmt->fetchColumn()>0)return;

        $total=max(0.0,round((float)($booking['total_price']??0),2));
        if($total<=0.005)return;
        $deposit=max(0.0,round((float)($booking['deposit_amount']??0),2));
        if($deposit>$total)$deposit=$total;
        $remaining=max(0.0,round($total-$deposit,2));
        $language=(string)($booking['public_language']??'de');
        $today=(new DateTimeImmutable('today'))->format('Y-m-d');
        $depositDue=valid_date((string)($booking['deposit_due_date']??''))?(string)$booking['deposit_due_date']:$today;
        $remainingDue=valid_date((string)($booking['remaining_due_date']??''))?(string)$booking['remaining_due_date']:self::defaultRemainingDueDate((string)($booking['arrival']??$today));
        $insert=db()->prepare('INSERT INTO booking_payment_schedule(booking_id,installment_type,label,amount,due_date,status,paid_amount,waived_reason,sort_order) VALUES(?,?,?,?,?,?,?,?,?)');
        if($deposit>0.005){
            $insert->execute([$id,'deposit',self::label($language,'deposit'),$deposit,$depositDue,$depositDue<$today?'overdue':'open',0,null,10]);
        }
        if($remaining>0.005){
            $insert->execute([$id,'remaining',self::label($language,'remaining'),$remaining,$remainingDue,$remainingDue<$today?'overdue':'open',0,null,20]);
        }
    }

    private static function bookingRecord(int $bookingId): array
    {
        $stmt=db()->prepare("SELECT b.*,TRIM(CONCAT(g.first_name,' ',g.last_name,' ',COALESCE(g.second_last_name,''))) guest_name,g.email guest_email,g.phone guest_phone,
            at.name apartment_type_name,a.name apartment_name,a.code apartment_code,o.offer_number,o.currency
            FROM bookings b JOIN guests g ON g.id=b.guest_id LEFT JOIN apartment_types at ON at.id=b.apartment_type_id LEFT JOIN apartments a ON a.id=b.apartment_id LEFT JOIN offers o ON o.id=b.source_offer_id WHERE b.id=? LIMIT 1");
        $stmt->execute([$bookingId]);$row=$stmt->fetch();if(!$row)throw new NotFoundException('Buchung nicht gefunden.');return $row;
    }

    private static function renderConfirmationHtml(array $booking,array $offer,array $options,string $customerUrl,float $deposit,float $remaining): string
    {
        $language=self::language((string)$options['language']);
        $subject=self::replacePlain((string)$options['pdf_title'],$booking,$offer);
        $currency=(string)($offer['currency']??$booking['currency']??setting('currency','EUR'));
        $money=static fn(float $v):string=>self::formatMoney($v,$currency,$language);
        $people=(int)$booking['adults']+(int)$booking['children']+(int)$booking['babies'];
        $checkin=self::displayTime((string)($options['checkin_time']??$booking['planned_arrival_time']??''));
        $checkout=self::displayTime((string)($options['checkout_time']??$booking['planned_departure_time']??''));
        $services=self::serviceSummaryHtml($booking,$language);
        $infoBlocks='';
        foreach(['arrival_info'=>'arrival_info','key_info'=>'key_info','checkout_info'=>'checkout_info','pdf_extra_info'=>'additional_notes'] as $optionKey=>$labelKey){
            $content=trim((string)($options[$optionKey]??''));
            if($content!=='')$infoBlocks.='<section class="info"><h2>'.e(self::label($language,$labelKey)).'</h2>'.self::replaceRich($content,$booking,$offer).'</section>';
        }
        return '<!doctype html><html lang="'.e($language).'"><head><meta charset="utf-8"><title>'.e($subject).'</title><style>'.self::documentCss().'</style></head><body><main class="wrap"><header><small>'.e(self::label($language,'booking_confirmation')).'</small><h1>'.e($subject).'</h1></header>'.self::replaceRich($options['greeting'],$booking,$offer).self::replaceRich($options['intro'],$booking,$offer).'<section class="box"><div class="row"><span>'.e(self::label($language,'booking')).'</span><b>'.e($booking['reference']).'</b></div><div class="row"><span>'.e(self::label($language,'stay')).'</span><b>'.e(self::dateRange($booking['arrival'],$booking['departure'],$language)).'</b></div><div class="row"><span>'.e(self::label($language,'type')).'</span><b>'.e($booking['apartment_type_name']??'').'</b></div><div class="row"><span>'.e(self::label($language,'persons')).'</span><b>'.$people.' '.e(self::personsDetail($booking,$language)).'</b></div><div class="row"><span>'.e(self::label($language,'checkin')).'</span><b>'.e($checkin?:'–').'</b></div><div class="row"><span>'.e(self::label($language,'checkout')).'</span><b>'.e($checkout?:'–').'</b></div><div class="row total"><span>'.e(self::label($language,'total')).'</span><b>'.$money((float)$booking['total_price']).'</b></div><div class="row"><span>'.e(self::label($language,'deposit')).'</span><b>'.$money($deposit).($booking['deposit_due_date']?' · '.e(self::label($language,'due')).': '.e(self::date($booking['deposit_due_date'],$language)):'').'</b></div><div class="row"><span>'.e(self::label($language,'remaining')).'</span><b>'.$money($remaining).($booking['remaining_due_date']?' · '.e(self::label($language,'due')).': '.e(self::date($booking['remaining_due_date'],$language)):'').'</b></div></section>'.($services!==''?'<section class="info"><h2>'.e(self::label($language,'services')).'</h2>'.$services.'</section>':'').self::replaceRich($options['additional_info'],$booking,$offer).$infoBlocks.'<p class="cta"><a href="'.e($customerUrl).'">'.e(self::label($language,'customer_area')).'</a></p>'.self::replaceRich($options['closing'],$booking,$offer).self::replaceRich($options['signature'],$booking,$offer).'</main></body></html>';
    }

    private static function renderEmail(array $booking,array $context,string $subject): array
    {
        $options=$context['options'];$html=self::renderConfirmationHtml($booking,['currency'=>$booking['currency']??setting('currency','EUR'),'offer_number'=>$booking['offer_number']??''],$options,$context['customer_url'],(float)$context['deposit'],(float)$context['remaining']);
        $text=trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags(str_replace(['</p>','</div>','<br>','<br/>'],"\n",$html)),ENT_QUOTES|ENT_HTML5,'UTF-8'))??'');
        return ['subject'=>$subject,'html'=>$html,'text'=>$text];
    }

    private static function writeDocumentPdf(array $booking,array $document,array $context): ?string
    {
        if(!(int)setting('booking_confirmation_pdf_enabled',1))return null;
        $language=self::language((string)$document['language']);
        $type=(string)$document['document_type'];
        $lines=match($type){
            'arrival_information'=>self::arrivalPdfLines($booking,$context,$language),
            'payment_overview'=>self::paymentPdfLines($booking,$context,$language),
            default=>self::confirmationPdfLines($booking,$context,$language),
        };
        $pdf=SimplePdf::create((string)$document['title'],$lines);
        $safe=preg_replace('/[^A-Za-z0-9_-]/','-',(string)$document['document_number'])?:'booking-document';
        $relative='storage/documents/booking-confirmations/'.$safe.'.pdf';$absolute=root_path($relative);
        if(@file_put_contents($absolute,$pdf,LOCK_EX)===false)throw new RuntimeException('Das PDF-Dokument konnte nicht gespeichert werden.');
        $checksum=hash_file('sha256',$absolute)?:null;db()->prepare('UPDATE booking_documents SET pdf_path=?,checksum_sha256=? WHERE id=?')->execute([$relative,$checksum,$document['id']]);return $relative;
    }

    private static function confirmationPdfLines(array $booking,array $context,string $language): array
    {
        $currency=(string)($booking['currency']??setting('currency','EUR'));$money=static fn(float $v):string=>self::formatMoney($v,$currency,$language);$options=$context['options'];
        return [
            ['text'=>self::label($language,'booking').': '.$booking['reference'],'size'=>12,'bold'=>true,'space'=>4],
            self::label($language,'guest').': '.$booking['guest_name'],
            self::label($language,'stay').': '.self::dateRange($booking['arrival'],$booking['departure'],$language),
            self::label($language,'type').': '.($booking['apartment_type_name']??''),
            self::label($language,'persons').': '.((int)$booking['adults']+(int)$booking['children']+(int)$booking['babies']).' '.self::personsDetail($booking,$language),
            self::label($language,'checkin').': '.(self::displayTime((string)($options['checkin_time']??$booking['planned_arrival_time']??''))?:'–'),
            self::label($language,'checkout').': '.(self::displayTime((string)($options['checkout_time']??$booking['planned_departure_time']??''))?:'–'),
            ['text'=>'','space'=>5],
            ['text'=>self::label($language,'total').': '.$money((float)$booking['total_price']),'size'=>13,'bold'=>true],
            self::label($language,'deposit').': '.$money((float)$context['deposit']).($booking['deposit_due_date']?' · '.self::label($language,'due').': '.self::date($booking['deposit_due_date'],$language):''),
            self::label($language,'remaining').': '.$money((float)$context['remaining']).($booking['remaining_due_date']?' · '.self::label($language,'due').': '.self::date($booking['remaining_due_date'],$language):''),
            ['text'=>'','space'=>5],
            ['text'=>self::label($language,'services'),'bold'=>true],
            strip_tags(self::serviceSummaryHtml($booking,$language)) ?: '–',
            ['text'=>'','space'=>5],
            strip_tags((string)$options['additional_info']),
            strip_tags((string)($options['pdf_extra_info']??'')),
        ];
    }

    private static function arrivalPdfLines(array $booking,array $context,string $language): array
    {
        $options=$context['options'];
        return [
            ['text'=>self::label($language,'arrival_document_title'),'size'=>13,'bold'=>true,'space'=>4],
            self::label($language,'booking').': '.$booking['reference'],
            self::label($language,'guest').': '.$booking['guest_name'],
            self::label($language,'stay').': '.self::dateRange($booking['arrival'],$booking['departure'],$language),
            self::label($language,'checkin').': '.(self::displayTime((string)($options['checkin_time']??''))?:'–'),
            self::label($language,'checkout').': '.(self::displayTime((string)($options['checkout_time']??''))?:'–'),
            ['text'=>'','space'=>5],
            ['text'=>self::label($language,'arrival_info'),'bold'=>true],
            strip_tags((string)($options['arrival_info']??'')),
            ['text'=>self::label($language,'key_info'),'bold'=>true,'space'=>2],
            strip_tags((string)($options['key_info']??'')),
            ['text'=>self::label($language,'checkout_info'),'bold'=>true,'space'=>2],
            strip_tags((string)($options['checkout_info']??'')),
            ['text'=>self::label($language,'customer_area').': '.($context['customer_url']??''),'space'=>4],
        ];
    }

    private static function paymentPdfLines(array $booking,array $context,string $language): array
    {
        $currency=(string)($booking['currency']??setting('currency','EUR'));$money=static fn(float $v):string=>self::formatMoney($v,$currency,$language);
        return [
            ['text'=>self::label($language,'payment_document_title'),'size'=>13,'bold'=>true,'space'=>4],
            self::label($language,'booking').': '.$booking['reference'],
            self::label($language,'guest').': '.$booking['guest_name'],
            self::label($language,'total').': '.$money((float)$booking['total_price']),
            ['text'=>'','space'=>5],
            self::label($language,'deposit').': '.$money((float)$context['deposit']).($booking['deposit_due_date']?' · '.self::label($language,'due').': '.self::date($booking['deposit_due_date'],$language):''),
            self::label($language,'remaining').': '.$money((float)$context['remaining']).($booking['remaining_due_date']?' · '.self::label($language,'due').': '.self::date($booking['remaining_due_date'],$language):''),
            ['text'=>'','space'=>5],
            strip_tags((string)($context['options']['additional_info']??'')),
            self::label($language,'customer_area').': '.($context['customer_url']??''),
        ];
    }

    private static function renderArrivalHtml(array $booking,array $offer,array $options,string $customerUrl): string
    {
        $language=self::language((string)$options['language']);
        $title=self::label($language,'arrival_document_title');
        return '<!doctype html><html lang="'.e($language).'"><head><meta charset="utf-8"><title>'.e($title).'</title><style>'.self::documentCss().'</style></head><body><main class="wrap"><header><small>'.e(self::label($language,'booking')).' '.e($booking['reference']).'</small><h1>'.e($title).'</h1></header><section class="box"><div class="row"><span>'.e(self::label($language,'stay')).'</span><b>'.e(self::dateRange($booking['arrival'],$booking['departure'],$language)).'</b></div><div class="row"><span>'.e(self::label($language,'checkin')).'</span><b>'.e(self::displayTime((string)($options['checkin_time']??''))?:'–').'</b></div><div class="row"><span>'.e(self::label($language,'checkout')).'</span><b>'.e(self::displayTime((string)($options['checkout_time']??''))?:'–').'</b></div></section><section class="info"><h2>'.e(self::label($language,'arrival_info')).'</h2>'.self::replaceRich((string)($options['arrival_info']??''),$booking,$offer).'</section><section class="info"><h2>'.e(self::label($language,'key_info')).'</h2>'.self::replaceRich((string)($options['key_info']??''),$booking,$offer).'</section><section class="info"><h2>'.e(self::label($language,'checkout_info')).'</h2>'.self::replaceRich((string)($options['checkout_info']??''),$booking,$offer).'</section><p class="cta"><a href="'.e($customerUrl).'">'.e(self::label($language,'customer_area')).'</a></p></main></body></html>';
    }

    private static function renderPaymentHtml(array $booking,array $offer,array $options,string $customerUrl,float $deposit,float $remaining): string
    {
        $language=self::language((string)$options['language']);$currency=(string)($offer['currency']??$booking['currency']??setting('currency','EUR'));$money=static fn(float $v):string=>self::formatMoney($v,$currency,$language);
        $title=self::label($language,'payment_document_title');
        return '<!doctype html><html lang="'.e($language).'"><head><meta charset="utf-8"><title>'.e($title).'</title><style>'.self::documentCss().'</style></head><body><main class="wrap"><header><small>'.e(self::label($language,'booking')).' '.e($booking['reference']).'</small><h1>'.e($title).'</h1></header><section class="box"><div class="row total"><span>'.e(self::label($language,'total')).'</span><b>'.$money((float)$booking['total_price']).'</b></div><div class="row"><span>'.e(self::label($language,'deposit')).'</span><b>'.$money($deposit).($booking['deposit_due_date']?' · '.e(self::label($language,'due')).': '.e(self::date($booking['deposit_due_date'],$language)):'').'</b></div><div class="row"><span>'.e(self::label($language,'remaining')).'</span><b>'.$money($remaining).($booking['remaining_due_date']?' · '.e(self::label($language,'due')).': '.e(self::date($booking['remaining_due_date'],$language)):'').'</b></div></section>'.self::replaceRich((string)($options['additional_info']??''),$booking,$offer).'<p class="cta"><a href="'.e($customerUrl).'">'.e(self::label($language,'customer_area')).'</a></p></main></body></html>';
    }

    private static function documentCss(): string
    {
        return 'body{margin:0;background:#f1f5f9;color:#172033;font-family:Arial,sans-serif;line-height:1.55}.wrap{max-width:820px;margin:24px auto;background:#fff;border-radius:18px;padding:30px;box-shadow:0 18px 50px rgba(15,23,42,.12)}header{border-bottom:1px solid #dbe3ee;margin-bottom:20px;padding-bottom:16px}header small{color:#64748b;text-transform:uppercase;letter-spacing:.08em;font-size:12px}h1{margin:6px 0 0;color:#1d4ed8}h2{font-size:18px;margin:0 0 10px}.box,.info{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px;margin:18px 0}.row{display:flex;justify-content:space-between;gap:20px;border-bottom:1px solid #dbe3ee;padding:9px 0}.row:last-child{border-bottom:0}.row span{color:#64748b}.total{font-size:20px;font-weight:bold}.service-list{margin:0;padding-left:18px}.cta{text-align:center;margin:26px 0}.cta a{display:inline-block;padding:12px 20px;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:bold}p{margin:10px 0}ul,ol{padding-left:22px}@media(max-width:720px){.wrap{margin:0;border-radius:0;padding:20px}.row{display:block}.row b{display:block;margin-top:3px}}';
    }

    private static function serviceSummaryHtml(array $booking,string $language): string
    {
        $items=[];
        if((int)($booking['breakfast']??0))$items[]=self::label($language,'breakfast');
        if((int)($booking['half_board']??0))$items[]=self::label($language,'half_board');
        if((int)($booking['parking_spaces']??0)>0)$items[]=self::label($language,'parking').': '.(int)$booking['parking_spaces'];
        if((int)($booking['extra_beds']??0)>0)$items[]=self::label($language,'extra_beds').': '.(int)$booking['extra_beds'];
        if((int)($booking['baby_beds']??0)>0)$items[]=self::label($language,'baby_beds').': '.(int)$booking['baby_beds'];
        if((int)($booking['pets']??0)>0)$items[]=self::label($language,'pets').': '.(int)$booking['pets'];
        if((int)($booking['late_checkout']??0))$items[]=self::label($language,'late_checkout');
        if(!$items)return '';
        return '<ul class="service-list"><li>'.implode('</li><li>',array_map('e',$items)).'</li></ul>';
    }

    private static function personsDetail(array $booking,string $language): string
    {
        $parts=[];
        if((int)($booking['adults']??0)>0)$parts[]=(int)$booking['adults'].' '.self::label($language,'adults');
        if((int)($booking['children']??0)>0)$parts[]=(int)$booking['children'].' '.self::label($language,'children');
        if((int)($booking['babies']??0)>0)$parts[]=(int)$booking['babies'].' '.self::label($language,'babies');
        return $parts?'('.implode(', ',$parts).')':'';
    }

    private static function template(string $language): array
    {
        $stmt=db()->prepare("SELECT * FROM booking_confirmation_templates WHERE language IN (?, 'de') ORDER BY language=? DESC LIMIT 1");$stmt->execute([$language,$language]);return $stmt->fetch()?:[];
    }

    private static function nextNumber(string $type,string $prefix): string
    {
        $year=(int)date('Y');$pdo=db();$pdo->prepare('INSERT INTO document_sequences(document_type,document_year,current_value) VALUES(?,?,0) ON DUPLICATE KEY UPDATE current_value=current_value')->execute([$type,$year]);$pdo->prepare('UPDATE document_sequences SET current_value=LAST_INSERT_ID(current_value+1) WHERE document_type=? AND document_year=?')->execute([$type,$year]);$value=(int)$pdo->lastInsertId();return $prefix.'-'.$year.'-'.str_pad((string)$value,4,'0',STR_PAD_LEFT);
    }

    private static function customerUrl(string $token): string{return HousekeepingWorkflow::applicationUrl('kunde.php?token='.rawurlencode($token));}
    private static function defaultRemainingDueDate(string $arrival): string
    {
        $days=max(0,(int)setting('booking_default_remaining_due_days',14));
        $candidate=(new DateTimeImmutable($arrival))->modify('-'.$days.' days')->format('Y-m-d');
        $today=(new DateTimeImmutable('today'))->format('Y-m-d');
        return $candidate<$today?$today:$candidate;
    }
    private static function appendInternalNote(string $existing,string $note,string $mode): string
    {
        $label=['hold'=>'Rückfrage/Warteliste','notify'=>'Gast informiert','cancel'=>'Anfrage archiviert'][$mode]??'Verfügbarkeitsklärung';
        $line='['.date('Y-m-d H:i').'] '.$label.': '.$note;
        return trim($existing)!==''?trim($existing)."

".$line:$line;
    }
    private static function recordOfferEvent(int $offerId,string $type,?array $old,?array $new): void
    {
        $stmt=db()->prepare('INSERT INTO offer_events(offer_id,event_type,old_values_json,new_values_json,user_id,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)');
        $stmt->execute([$offerId,$type,$old?json_encode($old,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,$new?json_encode($new,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,Auth::user()['id']??null,$_SERVER['REMOTE_ADDR']??null,mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);
    }
    private static function language(string $language): string{return in_array($language,self::LANGUAGES,true)?$language:'de';}
    private static function safeRich(string $html): string
    {
        $clean=strip_tags($html,'<p><br><b><strong><i><em><ul><ol><li>');
        // In den erlaubten Tags sind keinerlei Attribute nötig. Damit werden
        // Event-Handler, Styles und sonstige eingeschleuste Attribute entfernt.
        $clean=preg_replace_callback('/<(\/?)(p|br|b|strong|i|em|ul|ol|li)\b[^>]*>/iu',static function(array $m):string{
            $tag=strtolower($m[2]);
            if($tag==='br')return '<br>';
            return '<'.$m[1].$tag.'>';
        },$clean)??'';
        return trim($clean);
    }

    private static function replacementValues(array $booking,array $offer=[]): array
    {
        return [
            '{guest}'=>(string)($booking['guest_name']??$offer['guest_name']??''),
            '{reference}'=>(string)($booking['reference']??''),
            '{offer}'=>(string)($offer['offer_number']??$booking['offer_number']??''),
            '{arrival}'=>(string)($booking['arrival']??''),
            '{departure}'=>(string)($booking['departure']??''),
            '{checkin}'=>self::displayTime((string)($booking['planned_arrival_time']??'')),
            '{checkout}'=>self::displayTime((string)($booking['planned_departure_time']??'')),
            '{type}'=>(string)($booking['apartment_type_name']??$offer['apartment_type_name']??''),
            '{apartment}'=>(string)($booking['apartment_name']??$offer['apartment_name']??''),
        ];
    }

    private static function replacePlain(string $text,array $booking,array $offer=[]): string
    {
        $values=array_map(static fn(string $value):string=>trim(preg_replace('/[\r\n\t]+/u',' ',$value)??$value),self::replacementValues($booking,$offer));
        return trim(strtr(strip_tags($text),$values));
    }

    private static function replaceRich(string $html,array $booking,array $offer=[]): string
    {
        $values=array_map(static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),self::replacementValues($booking,$offer));
        return strtr(self::safeRich($html),$values);
    }
    private static function normalizeTime(mixed $value,string $label): ?string
    {
        $value=trim((string)$value);
        if($value==='')return null;
        if(!preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',$value)){
            throw new ValidationException($label.' ist ungültig. Bitte HH:MM verwenden.');
        }
        return strlen($value)===5?$value.':00':$value;
    }

    private static function formatTime(string $value): string
    {
        $value=trim($value);
        if($value==='')return '';
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',$value)?substr($value,0,5):'';
    }

    private static function displayTime(string $value): string
    {
        $time=self::formatTime($value);
        return $time!==''?$time.' Uhr':'';
    }

    private static function formatMoney(float $value,string $currency,string $language): string
    {
        $language=self::language($language);$symbol=$currency==='EUR'?'€':$currency;
        if($language==='en')return number_format($value,2,'.',',').' '.$symbol;
        if($language==='fr')return number_format($value,2,',',' ').' '.$symbol;
        return number_format($value,2,',','.').' '.$symbol;
    }

    private static function dateRange(string $from,string $to,string $language): string{return self::date($from,$language).' – '.self::date($to,$language);}
    private static function date(string $date,string $language): string{if(!valid_date($date))return $date;$formats=['de'=>'d.m.Y','en'=>'m/d/Y','es'=>'d/m/Y','fr'=>'d/m/Y','it'=>'d/m/Y','pt'=>'d/m/Y','ca'=>'d/m/Y'];return (new DateTimeImmutable($date))->format($formats[$language]??'d.m.Y');}
    private static function label(string $language,string $key): string
    {
        $base=[
            'deposit'=>'Anzahlung','remaining'=>'Restbetrag','booking'=>'Buchung','guest'=>'Gast','stay'=>'Aufenthalt','type'=>'Wohnungstyp','persons'=>'Personen','total'=>'Gesamtpreis','due'=>'fällig','customer_area'=>'Sicheren Gastbereich öffnen',
            'booking_confirmation'=>'Buchungsbestätigung','checkin'=>'Check-in ab','checkout'=>'Check-out bis','services'=>'Gebuchte Zusatzleistungen','arrival_info'=>'Anreiseinformationen','key_info'=>'Schlüssel / Zugang','checkout_info'=>'Abreisehinweise','additional_notes'=>'Weitere Hinweise',
            'arrival_document_title'=>'Anreise- und Check-in-Informationen','payment_document_title'=>'Zahlungsübersicht','breakfast'=>'Frühstück','half_board'=>'Halbpension','parking'=>'Parkplatz','extra_beds'=>'Zustellbetten','baby_beds'=>'Babybetten','pets'=>'Haustiere','late_checkout'=>'Late Check-out','adults'=>'Erwachsene','children'=>'Kinder','babies'=>'Babys'
        ];
        $d=[
            'de'=>$base,
            'en'=>array_merge($base,['deposit'=>'Deposit','remaining'=>'Remaining amount','booking'=>'Booking','guest'=>'Guest','stay'=>'Stay','type'=>'Accommodation type','persons'=>'Guests','total'=>'Total price','due'=>'due','customer_area'=>'Open secure guest area','booking_confirmation'=>'Booking confirmation','checkin'=>'Check-in from','checkout'=>'Check-out until','services'=>'Booked additional services','arrival_info'=>'Arrival information','key_info'=>'Key / access','checkout_info'=>'Departure information','additional_notes'=>'Further notes','arrival_document_title'=>'Arrival and check-in information','payment_document_title'=>'Payment overview','breakfast'=>'Breakfast','half_board'=>'Half board','parking'=>'Parking','extra_beds'=>'Extra beds','baby_beds'=>'Baby beds','pets'=>'Pets','late_checkout'=>'Late check-out','adults'=>'adults','children'=>'children','babies'=>'babies']),
            'es'=>array_merge($base,['deposit'=>'Anticipo','remaining'=>'Importe restante','booking'=>'Reserva','guest'=>'Huésped','stay'=>'Estancia','type'=>'Tipo de alojamiento','persons'=>'Personas','total'=>'Precio total','due'=>'vencimiento','customer_area'=>'Abrir área segura','booking_confirmation'=>'Confirmación de reserva','checkin'=>'Check-in desde','checkout'=>'Check-out hasta','services'=>'Servicios adicionales reservados','arrival_info'=>'Información de llegada','key_info'=>'Llave / acceso','checkout_info'=>'Información de salida','additional_notes'=>'Información adicional','arrival_document_title'=>'Información de llegada y check-in','payment_document_title'=>'Resumen de pagos','breakfast'=>'Desayuno','half_board'=>'Media pensión','parking'=>'Aparcamiento','extra_beds'=>'Camas supletorias','baby_beds'=>'Cunas','pets'=>'Mascotas','late_checkout'=>'Salida tardía','adults'=>'adultos','children'=>'niños','babies'=>'bebés']),
            'fr'=>array_merge($base,['deposit'=>'Acompte','remaining'=>'Solde','booking'=>'Réservation','guest'=>'Client','stay'=>'Séjour','type'=>'Type de logement','persons'=>'Personnes','total'=>'Prix total','due'=>'échéance','customer_area'=>'Ouvrir l’espace sécurisé','booking_confirmation'=>'Confirmation de réservation','checkin'=>'Arrivée à partir de','checkout'=>'Départ jusqu’à','services'=>'Services supplémentaires réservés','arrival_info'=>'Informations d’arrivée','key_info'=>'Clé / accès','checkout_info'=>'Informations de départ','additional_notes'=>'Informations complémentaires','arrival_document_title'=>'Informations d’arrivée et check-in','payment_document_title'=>'Aperçu des paiements','breakfast'=>'Petit-déjeuner','half_board'=>'Demi-pension','parking'=>'Parking','extra_beds'=>'Lits supplémentaires','baby_beds'=>'Lits bébé','pets'=>'Animaux','late_checkout'=>'Départ tardif','adults'=>'adultes','children'=>'enfants','babies'=>'bébés']),
            'it'=>array_merge($base,['deposit'=>'Acconto','remaining'=>'Saldo','booking'=>'Prenotazione','guest'=>'Ospite','stay'=>'Soggiorno','type'=>'Tipo di alloggio','persons'=>'Persone','total'=>'Prezzo totale','due'=>'scadenza','customer_area'=>'Apri area sicura','booking_confirmation'=>'Conferma di prenotazione','checkin'=>'Check-in dalle','checkout'=>'Check-out entro','services'=>'Servizi aggiuntivi prenotati','arrival_info'=>'Informazioni di arrivo','key_info'=>'Chiave / accesso','checkout_info'=>'Informazioni di partenza','additional_notes'=>'Ulteriori informazioni','arrival_document_title'=>'Informazioni di arrivo e check-in','payment_document_title'=>'Riepilogo pagamenti','breakfast'=>'Colazione','half_board'=>'Mezza pensione','parking'=>'Parcheggio','extra_beds'=>'Letti aggiuntivi','baby_beds'=>'Culle','pets'=>'Animali','late_checkout'=>'Late check-out','adults'=>'adulti','children'=>'bambini','babies'=>'bebè']),
            'pt'=>array_merge($base,['deposit'=>'Sinal','remaining'=>'Montante restante','booking'=>'Reserva','guest'=>'Hóspede','stay'=>'Estadia','type'=>'Tipo de alojamento','persons'=>'Pessoas','total'=>'Preço total','due'=>'vencimento','customer_area'=>'Abrir área segura','booking_confirmation'=>'Confirmação de reserva','checkin'=>'Check-in a partir de','checkout'=>'Check-out até','services'=>'Serviços adicionais reservados','arrival_info'=>'Informações de chegada','key_info'=>'Chave / acesso','checkout_info'=>'Informações de saída','additional_notes'=>'Outras informações','arrival_document_title'=>'Informações de chegada e check-in','payment_document_title'=>'Resumo de pagamentos','breakfast'=>'Pequeno-almoço','half_board'=>'Meia pensão','parking'=>'Estacionamento','extra_beds'=>'Camas extra','baby_beds'=>'Berços','pets'=>'Animais','late_checkout'=>'Saída tardia','adults'=>'adultos','children'=>'crianças','babies'=>'bebés']),
            'ca'=>array_merge($base,['deposit'=>'Paga i senyal','remaining'=>'Import restant','booking'=>'Reserva','guest'=>'Hoste','stay'=>'Estada','type'=>'Tipus d’allotjament','persons'=>'Persones','total'=>'Preu total','due'=>'venciment','customer_area'=>'Obrir àrea segura','booking_confirmation'=>'Confirmació de reserva','checkin'=>'Check-in des de','checkout'=>'Check-out fins a','services'=>'Serveis addicionals reservats','arrival_info'=>'Informació d’arribada','key_info'=>'Clau / accés','checkout_info'=>'Informació de sortida','additional_notes'=>'Més informació','arrival_document_title'=>'Informació d’arribada i check-in','payment_document_title'=>'Resum de pagaments','breakfast'=>'Esmorzar','half_board'=>'Mitja pensió','parking'=>'Aparcament','extra_beds'=>'Llits supletoris','baby_beds'=>'Bressols','pets'=>'Mascotes','late_checkout'=>'Sortida tardana','adults'=>'adults','children'=>'nens','babies'=>'nadons']),
        ];
        return $d[$language][$key]??$base[$key]??$key;
    }
    private static function tableAvailable(string $table): bool{try{db()->query('SELECT 1 FROM `'.$table.'` LIMIT 1');return true;}catch(Throwable){return false;}}
}
