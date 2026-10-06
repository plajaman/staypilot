<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$action=(string)($_GET['action']??'search');
try{
    if($action==='search') public_search();
    if($action==='inquiry') public_inquiry();
    if($action==='type') public_type();
    if($action==='type_calendar') public_type_calendar();
    if($action==='export_bookings_for_sync') export_bookings_for_sync();
    json_response(['ok'=>false,'message'=>'Unbekannte Aktion.'],404);
}catch(HttpException $e){json_response(['ok'=>false,'message'=>$e->getMessage(),'code'=>$e->errorCode,'details'=>$e->details],$e->status);}
catch(Throwable $e){AppLogger::error($e,['action'=>$action],'public-api');json_response(['ok'=>false,'message'=>$e instanceof ValidationException||$e instanceof ConflictException?$e->getMessage():'Die Anfrage konnte nicht verarbeitet werden.'],422);}

function public_search(): never
{
    $arrival=(string)($_GET['arrival']??'');$departure=(string)($_GET['departure']??'');
    $adults=max(1,(int)($_GET['adults']??$_GET['guests']??1));$children=max(0,(int)($_GET['children']??0));$babies=max(0,(int)($_GET['babies']??0));$guests=$adults+$children;
    $language=public_language((string)($_GET['language']??setting('public_booking_default_language','de')));
    $childAges=public_child_ages($_GET['child_ages']??[],$children,true,$language);
    if(!valid_date($arrival)||!valid_date($departure)||$arrival>=$departure)throw new ValidationException(public_message('invalid_dates',$language));
    $types=db()->query("SELECT * FROM apartment_types WHERE active=1 AND public_active=1 ORDER BY sort_order,name")->fetchAll();$rows=[];
    $freeStmt=db()->prepare("SELECT COUNT(*) FROM apartments a WHERE a.apartment_type_id=? AND a.status='active' AND a.out_of_service=0 AND NOT EXISTS(SELECT 1 FROM bookings b WHERE b.apartment_id=a.id AND b.status NOT IN ('cancelled','rejected') AND b.arrival<? AND b.departure>?) AND NOT EXISTS(SELECT 1 FROM availability_blocks bl WHERE bl.apartment_id=a.id AND bl.start_date<? AND bl.end_date>?)");
    $poolStmt=db()->prepare("SELECT COUNT(*) FROM bookings WHERE apartment_id IS NULL AND apartment_type_id=? AND status NOT IN ('cancelled','rejected') AND arrival<? AND departure>?");
    foreach($types as $type){
        $typeId=(int)$type['id'];$freeStmt->execute([$typeId,$departure,$arrival,$departure,$arrival]);$free=(int)$freeStmt->fetchColumn();$poolStmt->execute([$typeId,$departure,$arrival]);$pool=(int)$poolStmt->fetchColumn();$available=max(0,$free-$pool);if($available<=0)continue;
        $capacity=public_capacity_info($type,$guests);if($guests>(int)$type['max_occupancy']&&!(int)$type['allow_capacity_override'])continue;
        $translation=public_type_translation($typeId,$language);$amenities=public_type_amenities($typeId,$language);$images=public_type_images($typeId,$language,$translation);
        $price=null;$priceError='';$minimum=1;
        try{$quote=OfferService::calculate(['apartment_type_id'=>$typeId,'arrival'=>$arrival,'departure'=>$departure,'adults'=>$adults,'children'=>$children,'babies'=>$babies,'child_ages'=>$childAges,'capacity_override'=>$capacity['warning']?1:0,'capacity_override_reason'=>$capacity['warning']?'Öffentliche Verfügbarkeitssuche – Belegungshinweis':'']);$price=(float)$quote['total_amount'];$minimum=(int)$quote['minimum_stay'];}
        catch(MissingPriceException $e){$priceError=public_message('price_on_request',$language);}
        catch(ConflictException $e){continue;}
        $snapshot=BookingPolicyService::cancellationSnapshot($typeId);
        $rows[]=[
            'id'=>$typeId,'name'=>trim((string)($translation['name']??''))?:$type['name'],'code'=>$type['code'],
            'description_html'=>(string)($translation['public_description_html']??''),'seo_title'=>(string)($translation['seo_title']??''),'seo_description'=>(string)($translation['seo_description']??''),'request_hint'=>(string)($translation['request_hint']??''),
            'standard_occupancy'=>(int)$type['standard_occupancy'],'max_occupancy'=>(int)$type['max_occupancy'],'bedrooms'=>(int)$type['bedrooms'],'beds'=>(int)$type['beds'],'living_area'=>$type['living_area'],
            'available_count'=>$available,'nights'=>nights($arrival,$departure),'price'=>$price,'price_available'=>$price!==null,'price_message'=>$priceError,'minimum_stay'=>$minimum,
            'capacity'=>$capacity,'amenities'=>$amenities,'images'=>$images,'cancellation_text'=>BookingPolicyService::cancellationText($snapshot,$language),
        ];
    }
    json_response(['ok'=>true,'arrival'=>$arrival,'departure'=>$departure,'guests'=>$guests,'adults'=>$adults,'children'=>$children,'babies'=>$babies,'child_ages'=>$childAges,'language'=>$language,'nights'=>nights($arrival,$departure),'apartment_types'=>$rows,'currency'=>(string)setting('offer_currency',setting('currency','EUR')),'tourist_tax_min_age'=>(int)setting('offer_tourist_tax_min_age',16)]);
}

function public_type(): never
{
    $id=(int)($_GET['id']??0);$language=public_language((string)($_GET['language']??'de'));$type=BookingPolicyService::type($id);if(!(int)$type['active']||!(int)$type['public_active'])throw new NotFoundException('Wohnungstyp nicht gefunden.');$translation=public_type_translation($id,$language);json_response(['ok'=>true,'apartment_type'=>['id'=>$id,'name'=>$translation['name']?:$type['name'],'description_html'=>$translation['public_description_html'],'amenities'=>public_type_amenities($id,$language),'images'=>public_type_images($id,$language,$translation),'cancellation_text'=>BookingPolicyService::cancellationText(BookingPolicyService::cancellationSnapshot($id),$language)]]);
}


function public_type_calendar(): never
{
    $language=public_language((string)($_GET['language']??setting('public_booking_default_language','de')));
    $start=(string)($_GET['start']??date('Y-m-d'));
    $days=max(7,min(180,(int)($_GET['days']??60)));
    $sort=(string)($_GET['sort']??'type');
    $typeId=max(0,(int)($_GET['type_id']??0));
    if(!in_array($sort,['type','name','free_desc','scarce','price_asc','price_desc','min_stay'],true)) $sort='type';
    if(!valid_date($start)) throw new ValidationException(public_message('invalid_dates',$language));
    $dates=[];
    $end=date('Y-m-d',strtotime($start.' +'.($days-1).' days'));
    for($i=0;$i<$days;$i++) $dates[]=date('Y-m-d',strtotime($start.' +'.$i.' days'));
    if($typeId>0){
        $typesStmt=db()->prepare("SELECT id,code,name,standard_occupancy,max_occupancy,bedrooms,beds,living_area,standard_price,default_min_stay,sort_order FROM apartment_types WHERE id=? AND active=1 AND public_active=1 ORDER BY sort_order,name");
        $typesStmt->execute([$typeId]);
        $types=$typesStmt->fetchAll();
    }else{
        $types=db()->query("SELECT id,code,name,standard_occupancy,max_occupancy,bedrooms,beds,living_area,standard_price,default_min_stay,sort_order FROM apartment_types WHERE active=1 AND public_active=1 ORDER BY sort_order,name")->fetchAll();
    }
    $rows=[];
    $apStmt=db()->prepare("SELECT COUNT(*) FROM apartments WHERE apartment_type_id=? AND status='active' AND out_of_service=0");
    $bookStmt=db()->prepare("SELECT COUNT(DISTINCT apartment_id) FROM bookings WHERE apartment_type_id=? AND apartment_id IS NOT NULL AND status NOT IN ('cancelled','rejected') AND arrival<=? AND departure>?");
    $poolStmt=db()->prepare("SELECT COUNT(*) FROM bookings WHERE apartment_type_id=? AND apartment_id IS NULL AND status NOT IN ('cancelled','rejected') AND arrival<=? AND departure>?");
    $blockStmt=db()->prepare("SELECT COUNT(DISTINCT apartment_id) FROM availability_blocks WHERE apartment_id IN (SELECT id FROM apartments WHERE apartment_type_id=?) AND start_date<=? AND end_date>?");
    foreach($types as $type){
        $typeId=(int)$type['id'];
        $tr=public_type_translation($typeId,$language);
        $apStmt->execute([$typeId]);$total=(int)$apStmt->fetchColumn();
        if($total<=0) continue;
        $calendar=[];$minFree=$total;$closedDays=0;$bookedDays=0;
        foreach($dates as $date){
            $bookStmt->execute([$typeId,$date,$date]);$booked=(int)$bookStmt->fetchColumn();
            $poolStmt->execute([$typeId,$date,$date]);$pool=(int)$poolStmt->fetchColumn();
            $blockStmt->execute([$typeId,$date,$date]);$blocked=(int)$blockStmt->fetchColumn();
            $used=min($total,$booked+$pool+$blocked);$free=max(0,$total-$used);
            $status=$free>0?'free':'booked';
            if($blocked>0 && $free<=0){$status='closed';$closedDays++;}
            if($status==='booked')$bookedDays++;
            $minFree=min($minFree,$free);

            // V2.3.6.35: Tagespreis nicht nur links als Startpreis,
            // sondern pro Kalendertag und Wohnungstyp berechnen.
            // Keine 0-Euro-Anzeige: fehlt ein Preis, wird die Zelle klar markiert.
            $dayPrice=['available'=>false,'nightly'=>0.0,'label'=>'Preis fehlt','short_label'=>'Preis fehlt','source'=>'','minimum_stay'=>max(1,(int)($type['default_min_stay']??setting('default_min_stay',1)))];
            try{
                $dayDeparture=date('Y-m-d',strtotime($date.' +1 day'));
                $dayQuote=PricingService::accommodationForType($typeId,$date,$dayDeparture);
                $dayFirst=$dayQuote['nightly'][0]??null;
                $dayNightly=(float)($dayFirst['price']??0);
                $dayMin=max(1,(int)($dayQuote['minimum_stay']??($type['default_min_stay']??1)));
                $dayPrice['minimum_stay']=$dayMin;
                $dayPrice['source']=(string)($dayFirst['source']??'');
                if($dayNightly>0){
                    $dayPrice['available']=true;
                    $dayPrice['nightly']=round($dayNightly,2);
                    $dayPrice['label']=number_format($dayNightly,2,',','.').' '.(string)setting('offer_currency',setting('currency','EUR')).' / Nacht';
                    $dayPrice['short_label']=number_format($dayNightly,0,',','.').' '.(string)setting('offer_currency',setting('currency','EUR'));
                }
            }catch(Throwable $e){
                $dayPrice['available']=false;
            }

            $calendar[]=['date'=>$date,'free'=>$free,'total'=>$total,'booked'=>$booked+$pool,'closed'=>$blocked,'status'=>$status,'price'=>$dayPrice];
        }
        $marketing=[];
        if($minFree<=2)$marketing[]=['type'=>'scarcity','text'=>$minFree<=0?'Aktuell stark belegt':'Nur noch '.$minFree.' verfügbar'];
        if($bookedDays>($days*0.45))$marketing[]=['type'=>'popular','text'=>'Sehr beliebt'];
        if($closedDays>0)$marketing[]=['type'=>'closed','text'=>$closedDays.' Tage geschlossen'];
        $priceInfo=['available'=>false,'nightly'=>0.0,'currency'=>(string)setting('offer_currency',setting('currency','EUR')),'label'=>'Preis auf Anfrage','source'=>'','minimum_stay'=>max(1,(int)($type['default_min_stay']??setting('default_min_stay',1))),'minimum_source'=>'Wohnungstyp'];
        try{
            $previewDeparture=date('Y-m-d',strtotime($start.' +1 day'));
            $quote=PricingService::accommodationForType($typeId,$start,$previewDeparture);
            $first=$quote['nightly'][0]??null;
            $nightly=(float)($first['price']??0);
            $minStay=max(1,(int)($quote['minimum_stay']??($type['default_min_stay']??1)));
            $priceInfo['minimum_stay']=$minStay;
            $priceInfo['minimum_source']=(string)($quote['minimum_source']??'');
            $priceInfo['source']=(string)($first['source']??'');
            if($nightly>0){
                $priceInfo['available']=true;
                $priceInfo['nightly']=round($nightly,2);
                $priceInfo['label']='ab '.number_format($nightly,2,',','.').' '.$priceInfo['currency'].' / Nacht';
            }
        }catch(Throwable $e){
            $priceInfo['available']=false;
        }
        $rows[]=['id'=>$typeId,'code'=>$type['code'],'name'=>trim((string)($tr['name']??''))?:$type['name'],'sort_order'=>(int)($type['sort_order']??999),'standard_occupancy'=>(int)$type['standard_occupancy'],'max_occupancy'=>(int)$type['max_occupancy'],'total'=>$total,'min_free'=>$minFree,'price'=>$priceInfo,'minimum_stay'=>$priceInfo['minimum_stay'],'marketing'=>$marketing,'calendar'=>$calendar];
    }
    usort($rows, static function(array $a,array $b) use($sort): int {
        return match($sort){
            'name' => strnatcasecmp((string)$a['name'],(string)$b['name']),
            'free_desc' => (($b['min_free'] <=> $a['min_free']) ?: strnatcasecmp((string)$a['name'],(string)$b['name'])),
            'scarce' => (($a['min_free'] <=> $b['min_free']) ?: strnatcasecmp((string)$a['name'],(string)$b['name'])),
            'price_asc' => (((float)($a['price']['nightly']?:999999) <=> (float)($b['price']['nightly']?:999999)) ?: strnatcasecmp((string)$a['name'],(string)$b['name'])),
            'price_desc' => ((float)($b['price']['nightly']??0) <=> (float)($a['price']['nightly']??0)) ?: strnatcasecmp((string)$a['name'],(string)$b['name']),
            'min_stay' => (($a['minimum_stay'] <=> $b['minimum_stay']) ?: strnatcasecmp((string)$a['name'],(string)$b['name'])),
            default => (((int)($a['sort_order']??999) <=> (int)($b['sort_order']??999)) ?: strnatcasecmp((string)$a['name'],(string)$b['name'])),
        };
    });
    json_response(['ok'=>true,'language'=>$language,'start'=>$start,'end'=>$end,'days'=>$days,'sort'=>$sort,'type_id'=>$typeId,'dates'=>$dates,'types'=>$rows]);
}

function public_inquiry(): never
{
    if(!(bool)setting('public_booking_enabled',true))throw new ConflictException(public_message('disabled',public_language((string)($_POST['language']??'de'))));
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new ValidationException('Ungültige Anfrage.');
    $d=request_data();if(!empty($d['website']))json_response(['ok'=>true,'message'=>'Anfrage gesendet.']);
    $language=public_language((string)($d['language']??'de'));
    $last=(int)($_SESSION['last_public_inquiry']??0);if(time()-$last<25)throw new ConflictException(public_message('wait',$language));$typeId=(int)($d['apartment_type_id']??0);$arrival=(string)($d['arrival']??'');$departure=(string)($d['departure']??'');$name=trim((string)($d['name']??''));$email=trim((string)($d['email']??''));
    if(!$typeId||!valid_date($arrival)||!valid_date($departure)||$arrival>=$departure||$name===''||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new ValidationException(public_message('required',$language));
    $type=BookingPolicyService::type($typeId);if(!(int)$type['active']||!(int)$type['public_active'])throw new ConflictException(public_message('unavailable',$language));
    $design=PublicSiteService::design();
    if(!empty($design['inquiry_privacy_required']) && !normalize_bool($d['privacy_confirm']??0)) throw new ValidationException(public_message('required',$language));
    $adults=max(1,(int)($d['adults']??1));$children=max(0,(int)($d['children']??0));$babies=max(0,(int)($d['babies']??0));$override=normalize_bool($d['capacity_override']??0);$request=mb_substr(trim((string)($d['guest_request']??'')),0,5000);$reason=mb_substr(trim((string)($d['capacity_override_reason']??'')),0,500);
    $extraNotes=[];
    foreach(['contact_preference'=>'Bevorzugter Kontakt','preferred_arrival_time'=>'Voraussichtliche Anreisezeit','location_request'=>'Lagewunsch','special_occasion'=>'Besonderer Anlass/Hinweis'] as $key=>$label){$value=mb_substr(trim((string)($d[$key]??'')),0,300);if($value!=='')$extraNotes[]=$label.': '.$value;}
    if(normalize_bool($d['marketing_consent']??0))$extraNotes[]='Marketing-Zustimmung: ja';
    if($extraNotes){$request=trim($request."

--- Zusatzangaben aus Anfrageformular ---
".implode("
",$extraNotes));$request=mb_substr($request,0,5000);}
    $capacity=BookingPolicyService::capacityCheck($typeId,$adults,$children,$babies,(bool)$override,$reason?:$request);
    $childAges=public_child_ages($d['child_ages']??[],$children,true,$language);
    $free=public_available_count($typeId,$arrival,$departure);if($free<=0)throw new ConflictException(public_message('unavailable',$language));
    $price=0.0;$priceBreakdown=null;$priceMissing=false;
    try{$quote=OfferService::calculate(['apartment_type_id'=>$typeId,'arrival'=>$arrival,'departure'=>$departure,'adults'=>$adults,'children'=>$children,'babies'=>$babies,'child_ages'=>$childAges,'capacity_override'=>$override,'capacity_override_reason'=>$reason?:$request,'breakfast'=>normalize_bool($d['breakfast']??0),'half_board'=>normalize_bool($d['half_board']??0)]);$price=(float)$quote['total_amount'];$priceBreakdown=json_encode($quote,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    catch(MissingPriceException $e){$priceMissing=true;}
    $pdo=db();$pdo->beginTransaction();
    try{
        // Serialisiert parallele Anfragen desselben Wohnungstyps. So kann nicht
        // derselbe letzte freie Platz im Typ-Pool gleichzeitig doppelt vergeben werden.
        $lock=$pdo->prepare("SELECT id FROM apartments WHERE apartment_type_id=? AND status='active' AND out_of_service=0 FOR UPDATE");
        $lock->execute([$typeId]);$lock->fetchAll();
        $lock=$pdo->prepare("SELECT id FROM bookings WHERE apartment_type_id=? AND status NOT IN ('cancelled','rejected') AND arrival<? AND departure>? FOR UPDATE");
        $lock->execute([$typeId,$departure,$arrival]);$lock->fetchAll();
        if(public_available_count($typeId,$arrival,$departure)<=0)throw new ConflictException(public_message('unavailable',$language));
        $stmt=$pdo->prepare('SELECT id FROM guests WHERE email=? LIMIT 1');$stmt->execute([$email]);$guestId=$stmt->fetchColumn();$parts=preg_split('/\s+/',trim($name),2);
        if($guestId){$pdo->prepare('UPDATE guests SET first_name=?,last_name=?,phone=?,country=?,language=? WHERE id=?')->execute([$parts[0]??'Gast',$parts[1]??'-',trim((string)($d['phone']??''))?:null,trim((string)($d['country']??''))?:null,OfferService::LANGUAGES[$language],(int)$guestId]);}
        else{$pdo->prepare('INSERT INTO guests(first_name,last_name,email,phone,country,language) VALUES(?,?,?,?,?,?)')->execute([$parts[0]??'Gast',$parts[1]??'-',$email,trim((string)($d['phone']??''))?:null,trim((string)($d['country']??''))?:null,OfferService::LANGUAGES[$language]]);$guestId=(int)$pdo->lastInsertId();}
        $snapshot=json_encode(BookingPolicyService::cancellationSnapshot($typeId),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$notes=$priceMissing?'Preis für diesen Zeitraum fehlt noch und muss intern vor Bestätigung geprüft werden.':'';
        $reference=generate_reference();
        $columns=['reference','guest_id','apartment_id','apartment_type_id','arrival','departure','adults','children','babies','capacity_override','capacity_override_reason','child_ages_json','status','source','public_language','total_price','paid_amount','breakfast','half_board','guest_request','notes','price_breakdown_json','cancellation_snapshot_json'];
        $values=[$reference,(int)$guestId,null,$typeId,$arrival,$departure,$adults,$children,$babies,$override,$reason?:null,json_encode($childAges,JSON_UNESCAPED_UNICODE),'inquiry','Website',$language,$price,0,normalize_bool($d['breakfast']??0),normalize_bool($d['half_board']??0),$request,$notes,$priceBreakdown,$snapshot];
        $quoted=implode(',',array_map(static fn(string $column):string=>'`'.$column.'`',$columns));$marks=implode(',',array_fill(0,count($values),'?'));
        $pdo->prepare("INSERT INTO bookings({$quoted}) VALUES({$marks})")->execute($values);$bookingId=(int)$pdo->lastInsertId();$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    $labels=PublicSiteService::labels($language);$successText=trim((string)($labels['inquiry_success_text']??''))?:public_message('success',$language);
    AuditLogger::record('booking',$bookingId,'public_inquiry',null,['apartment_type_id'=>$typeId,'arrival'=>$arrival,'departure'=>$departure,'capacity'=>$capacity],'Öffentliche Anfrage nach Wohnungstyp');$_SESSION['last_public_inquiry']=time();
    notify_new_public_inquiry($reference,$type,$name,$email,$arrival,$departure,$adults,$children,$request);
    json_response(['ok'=>true,'message'=>$successText,'reference'=>$reference,'price_pending'=>$priceMissing]);
}


function notify_new_public_inquiry(string $reference,array $type,string $guestName,string $guestEmail,string $arrival,string $departure,int $adults,int $children,string $guestRequest): void
{
    $recipients=array_filter(array_map('trim',explode(',',(string)setting('inquiry_notification_emails',''))));
    if(!$recipients)return;
    $property=(string)setting('property_name','StayPilot');
    $subject='Neue Buchungsanfrage '.$reference.' – '.$property;
    $text="Eine neue unverbindliche Buchungsanfrage ist eingegangen.\n\n"
        ."Referenz: {$reference}\n"
        ."Wohnungstyp: {$type['name']} ({$type['code']})\n"
        ."Zeitraum: {$arrival} bis {$departure}\n"
        ."Personen: {$adults} Erwachsene, {$children} Kinder\n"
        ."Name: {$guestName}\n"
        ."E-Mail: {$guestEmail}\n"
        .($guestRequest!==''?"\nNachricht:\n{$guestRequest}\n":'')
        ."\nBitte im StayPilot-Adminbereich unter Buchungen pruefen.";
    foreach($recipients as $to){
        if(!filter_var($to,FILTER_VALIDATE_EMAIL))continue;
        try{SmtpMailer::send($to,$subject,$text);}
        catch(Throwable $e){AppLogger::error($e,['reference'=>$reference,'to'=>$to],'public-inquiry-notification');}
    }
}

function public_child_ages(mixed $value,int $children,bool $required=false,string $language='de'): array
{
    try{return BookingPolicyService::childAges($value,$children,$required);}
    catch(ValidationException){throw new ValidationException(public_message('child_ages_required',$language));}
}

function public_available_count(int $typeId,string $arrival,string $departure): int
{
    $stmt=db()->prepare("SELECT COUNT(*) FROM apartments a WHERE a.apartment_type_id=? AND a.status='active' AND a.out_of_service=0 AND NOT EXISTS(SELECT 1 FROM bookings b WHERE b.apartment_id=a.id AND b.status NOT IN ('cancelled','rejected') AND b.arrival<? AND b.departure>?) AND NOT EXISTS(SELECT 1 FROM availability_blocks bl WHERE bl.apartment_id=a.id AND bl.start_date<? AND bl.end_date>?)");$stmt->execute([$typeId,$departure,$arrival,$departure,$arrival]);$free=(int)$stmt->fetchColumn();$stmt=db()->prepare("SELECT COUNT(*) FROM bookings WHERE apartment_id IS NULL AND apartment_type_id=? AND status NOT IN ('cancelled','rejected') AND arrival<? AND departure>?");$stmt->execute([$typeId,$departure,$arrival]);return max(0,$free-(int)$stmt->fetchColumn());
}
function public_type_translation(int $typeId,string $language): array{$stmt=db()->prepare("SELECT * FROM offer_apartment_type_translations WHERE apartment_type_id=? AND language IN (?, 'de') ORDER BY language=? DESC LIMIT 1");$stmt->execute([$typeId,$language,$language]);return $stmt->fetch()?:['name'=>'','public_description_html'=>'','seo_title'=>'','seo_description'=>'','request_hint'=>'','image_alt'=>''];}
function public_type_amenities(int $typeId,string $language): array{$stmt=db()->prepare("SELECT c.icon,COALESCE(tr.label,de.label,c.code) label FROM apartment_type_amenities ta JOIN amenity_catalog c ON c.id=ta.amenity_id AND c.active=1 LEFT JOIN amenity_translations tr ON tr.amenity_id=c.id AND tr.language=? LEFT JOIN amenity_translations de ON de.amenity_id=c.id AND de.language='de' WHERE ta.apartment_type_id=? ORDER BY ta.sort_order,c.sort_order");$stmt->execute([$language,$typeId]);return $stmt->fetchAll();}
function public_type_images(int $typeId,string $language,array $translation=[]): array{$stmt=db()->prepare('SELECT file_path,thumb_path,alt_text_json,title_text_json,caption_text_json,is_cover,sort_order FROM apartment_type_images WHERE apartment_type_id=? ORDER BY is_cover DESC,sort_order,id');$stmt->execute([$typeId]);$rows=$stmt->fetchAll();foreach($rows as &$r){$alts=json_decode((string)($r['alt_text_json']??''),true)?:[];$titles=json_decode((string)($r['title_text_json']??''),true)?:[];$captions=json_decode((string)($r['caption_text_json']??''),true)?:[];$r['alt_text']=trim((string)($alts[$language]??$alts['de']??$translation['image_alt']??''));$r['title']=trim((string)($titles[$language]??$titles['de']??''));$r['caption']=trim((string)($captions[$language]??$captions['de']??''));unset($r['alt_text_json'],$r['title_text_json'],$r['caption_text_json']);}return $rows;}
function public_capacity_info(array $type,int $guests): array{$standard=max(1,(int)($type['standard_occupancy']??2));$maximum=max($standard,(int)($type['max_occupancy']??$standard));$warning=$guests>$standard;return ['persons'=>$guests,'standard'=>$standard,'maximum'=>$maximum,'warning'=>$warning,'exceeds_maximum'=>$guests>$maximum,'allowed'=>$guests<=$maximum||(int)$type['allow_capacity_override']===1];}
function public_language(string $language): string{return array_key_exists($language,OfferService::LANGUAGES)?$language:'de';}
function public_message(string $key,string $lang): string{$all=[
'de'=>['child_ages_required'=>'Bitte geben Sie das Alter aller Kinder an.','invalid_dates'=>'Bitte einen gültigen Reisezeitraum wählen.','price_on_request'=>'Preis auf Anfrage','disabled'=>'Buchungsanfragen sind derzeit deaktiviert.','wait'=>'Bitte warten Sie kurz, bevor Sie eine weitere Anfrage senden.','required'=>'Bitte alle Pflichtfelder korrekt ausfüllen.','unavailable'=>'Dieser Wohnungstyp ist für den gewählten Zeitraum inzwischen nicht mehr verfügbar.','success'=>'Vielen Dank. Ihre unverbindliche Anfrage wurde gespeichert.'],
'en'=>['child_ages_required'=>'Please enter the age of every child.','invalid_dates'=>'Please select valid travel dates.','price_on_request'=>'Price on request','disabled'=>'Booking requests are currently disabled.','wait'=>'Please wait briefly before sending another request.','required'=>'Please complete all required fields correctly.','unavailable'=>'This accommodation type is no longer available for the selected dates.','success'=>'Thank you. Your non-binding request has been saved.'],
'es'=>['child_ages_required'=>'Indique la edad de todos los niños.','invalid_dates'=>'Seleccione un periodo de viaje válido.','price_on_request'=>'Precio bajo petición','disabled'=>'Las solicitudes de reserva están desactivadas.','wait'=>'Espere un momento antes de enviar otra solicitud.','required'=>'Complete correctamente todos los campos obligatorios.','unavailable'=>'Este tipo de alojamiento ya no está disponible para las fechas seleccionadas.','success'=>'Gracias. Su solicitud sin compromiso ha sido guardada.'],
'pt'=>['child_ages_required'=>'Indique a idade de todas as crianças.','invalid_dates'=>'Selecione datas de viagem válidas.','price_on_request'=>'Preço sob consulta','disabled'=>'Os pedidos de reserva estão desativados.','wait'=>'Aguarde um momento antes de enviar outro pedido.','required'=>'Preencha corretamente todos os campos obrigatórios.','unavailable'=>'Este tipo de alojamento já não está disponível para as datas selecionadas.','success'=>'Obrigado. O seu pedido sem compromisso foi guardado.'],
'fr'=>['child_ages_required'=>'Veuillez indiquer l’âge de chaque enfant.','invalid_dates'=>'Veuillez choisir des dates de séjour valides.','price_on_request'=>'Prix sur demande','disabled'=>'Les demandes de réservation sont désactivées.','wait'=>'Veuillez patienter avant d’envoyer une autre demande.','required'=>'Veuillez remplir correctement tous les champs obligatoires.','unavailable'=>'Ce type d’hébergement n’est plus disponible aux dates choisies.','success'=>'Merci. Votre demande sans engagement a été enregistrée.'],
'it'=>['child_ages_required'=>'Indicare l’età di tutti i bambini.','invalid_dates'=>'Selezionare date di viaggio valide.','price_on_request'=>'Prezzo su richiesta','disabled'=>'Le richieste di prenotazione sono disattivate.','wait'=>'Attendere un momento prima di inviare un’altra richiesta.','required'=>'Compilare correttamente tutti i campi obbligatori.','unavailable'=>'Questo tipo di alloggio non è più disponibile per le date selezionate.','success'=>'Grazie. La richiesta non vincolante è stata salvata.'],
'ca'=>['child_ages_required'=>'Indiqueu l’edat de tots els infants.','invalid_dates'=>'Seleccioneu unes dates de viatge vàlides.','price_on_request'=>'Preu a consultar','disabled'=>'Les sol·licituds de reserva estan desactivades.','wait'=>'Espereu un moment abans d’enviar una altra sol·licitud.','required'=>'Empleneu correctament tots els camps obligatoris.','unavailable'=>'Aquest tipus d’allotjament ja no està disponible per a les dates seleccionades.','success'=>'Gràcies. La vostra sol·licitud sense compromís s’ha desat.']];return $all[$lang][$key]??$all['de'][$key]??$key;}

function ensure_sync_key_attempts_table_v1(): void
{
    static $done=false; if($done) return; $done=true;
    db()->exec("CREATE TABLE IF NOT EXISTS sync_key_attempts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, key_hash VARCHAR(64) NOT NULL, attempted_at DATETIME NOT NULL, INDEX idx_sync_key_attempts (key_hash,attempted_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function sync_key_limit_key_v1(): string { return hash('sha256','export-bookings|'.($_SERVER['REMOTE_ADDR']??'unknown')); }
function sync_key_throttle_allowed_v1(): bool
{
    $st=db()->prepare('SELECT COUNT(*) FROM sync_key_attempts WHERE key_hash=? AND attempted_at>?');
    $st->execute([sync_key_limit_key_v1(), date('Y-m-d H:i:s', time()-900)]);
    return (int)$st->fetchColumn() < 10;
}
function sync_key_register_failure_v1(): void
{
    $k=sync_key_limit_key_v1();
    db()->prepare('INSERT INTO sync_key_attempts(key_hash,attempted_at) VALUES(?,?)')->execute([$k, date('Y-m-d H:i:s')]);
    db()->prepare('DELETE FROM sync_key_attempts WHERE key_hash=? AND attempted_at<?')->execute([$k, date('Y-m-d H:i:s', time()-900)]);
}
function sync_key_clear_failures_v1(): void
{
    db()->prepare('DELETE FROM sync_key_attempts WHERE key_hash=?')->execute([sync_key_limit_key_v1()]);
}

function export_bookings_for_sync(): never
{
    ensure_sync_key_attempts_table_v1();
    $key=(string)($_GET['key']??'');
    $hash=trim((string)setting('staypilot_export_key_hash',''));
    if($hash===''||!sync_key_throttle_allowed_v1()||!hash_equals($hash,hash('sha256',$key))){
        sync_key_register_failure_v1();
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'message'=>'Ungueltiger Schluessel.']);
        exit;
    }
    sync_key_clear_failures_v1();
    $rows=db()->query("SELECT a.code, b.reference, b.arrival, b.departure, b.status, CONCAT(COALESCE(g.first_name,''),' ',COALESCE(g.last_name,'')) guest_name FROM apartments a JOIN bookings b ON b.apartment_id=a.id LEFT JOIN guests g ON g.id=b.guest_id WHERE b.status NOT IN ('cancelled','rejected') AND b.departure>=CURDATE() ORDER BY a.code, b.arrival")->fetchAll();
    $out=[];
    foreach($rows as $r){
        $code=(string)($r['code']??''); if($code==='')continue;
        $out[$code][]=['reference'=>$r['reference'],'arrival'=>$r['arrival'],'departure'=>$r['departure'],'status'=>$r['status'],'guest_name'=>trim((string)$r['guest_name'])];
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok'=>true,'bookings_by_code'=>$out], JSON_UNESCAPED_UNICODE);
    exit;
}
