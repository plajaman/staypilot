<?php
declare(strict_types=1);

/**
 * Zentraler Angebotskern für StayPilot V2.1.
 *
 * Alle Preise werden serverseitig neu berechnet. Gespeicherte Positionen bilden
 * anschließend einen unveränderlichen Preisschnappschuss für das Angebot und
 * die spätere Rechnungs-/Gutschriftenlogik.
 */
final class OfferService
{
    public const LANGUAGES = [
        'de'=>'Deutsch','en'=>'English','es'=>'Español','pt'=>'Português',
        'fr'=>'Français','it'=>'Italiano','ca'=>'Català',
    ];

    public static function defaults(): array
    {
        return [
            'offer_validity_days'=>7,
            'offer_deposit_percent'=>30,
            'offer_tourist_tax_per_person_night'=>0,
            'offer_tourist_tax_max_nights'=>7,
            'offer_tourist_tax_adults_only'=>0,
            'offer_tourist_tax_min_age'=>16,
            'offer_vat_rate'=>10,
            'offer_prices_include_vat'=>1,
            'offer_number_prefix'=>'ANG',
            'offer_currency'=>'EUR',
            'offer_logo_url'=>'',
            'offer_bank_account_holder'=>'',
            'offer_bank_iban'=>'',
            'offer_bank_bic'=>'',
            'offer_bank_name'=>'',
            'offer_company_extra'=>'',
            'offer_company_website'=>'',
        ];
    }

    public static function settings(): array
    {
        $out = [];
        foreach (self::defaults() as $key=>$value) $out[$key] = setting($key,$value);
        $out['languages'] = self::LANGUAGES;
        return $out;
    }

    public static function saveSettings(array $data): array
    {
        $old = self::settings();
        $values = [
            'offer_validity_days'=>max(1,min(90,(int)($data['offer_validity_days']??7))),
            'offer_deposit_percent'=>max(0,min(100,(float)($data['offer_deposit_percent']??30))),
            'offer_tourist_tax_per_person_night'=>max(0,(float)($data['offer_tourist_tax_per_person_night']??0)),
            'offer_tourist_tax_max_nights'=>max(0,min(365,(int)($data['offer_tourist_tax_max_nights']??7))),
            'offer_tourist_tax_adults_only'=>0,
            'offer_tourist_tax_min_age'=>in_array((int)($data['offer_tourist_tax_min_age']??16),[16,17,18],true)?(int)$data['offer_tourist_tax_min_age']:16,
            'offer_vat_rate'=>max(0,min(100,(float)($data['offer_vat_rate']??10))),
            'offer_prices_include_vat'=>normalize_bool($data['offer_prices_include_vat']??1),
            'offer_number_prefix'=>strtoupper(trim((string)($data['offer_number_prefix']??'ANG'))),
            'offer_currency'=>strtoupper(trim((string)($data['offer_currency']??'EUR'))),
            'offer_logo_url'=>trim((string)($data['offer_logo_url']??'')),
            'offer_bank_account_holder'=>mb_substr(trim((string)($data['offer_bank_account_holder']??'')),0,190),
            'offer_bank_iban'=>mb_substr(strtoupper(preg_replace('/\s+/', '', trim((string)($data['offer_bank_iban']??'')))),0,50),
            'offer_bank_bic'=>mb_substr(strtoupper(preg_replace('/\s+/', '', trim((string)($data['offer_bank_bic']??'')))),0,30),
            'offer_bank_name'=>mb_substr(trim((string)($data['offer_bank_name']??'')),0,190),
            'offer_company_extra'=>mb_substr(trim((string)($data['offer_company_extra']??'')),0,1000),
            'offer_company_website'=>mb_substr(trim((string)($data['offer_company_website']??'')),0,500),
        ];
        if (!preg_match('/^[A-Z0-9-]{1,12}$/',$values['offer_number_prefix'])) throw new ValidationException('Das Nummernpräfix darf nur Großbuchstaben, Zahlen und Bindestriche enthalten.');
        if (!preg_match('/^[A-Z]{3}$/',$values['offer_currency'])) throw new ValidationException('Die Währung muss als dreistelliger ISO-Code angegeben werden.');
        if ($values['offer_logo_url']!=='' && !self::safeAssetUrl($values['offer_logo_url'])) throw new ValidationException('Die Logo-Adresse muss eine sichere HTTPS-/HTTP-Adresse oder ein relativer Pfad sein.');
        if ($values['offer_company_website']!=='' && !self::safePublicWebsiteUrl($values['offer_company_website'])) throw new ValidationException('Die Website muss als sichere HTTP-/HTTPS-Adresse angegeben werden.');
        if ($values['offer_bank_iban']!=='' && !preg_match('/^[A-Z0-9]{10,50}$/',$values['offer_bank_iban'])) throw new ValidationException('Die IBAN enthält ungültige Zeichen.');
        if ($values['offer_bank_bic']!=='' && !preg_match('/^[A-Z0-9]{8,11}$/',$values['offer_bank_bic'])) throw new ValidationException('BIC/SWIFT muss 8 oder 11 Zeichen enthalten.');
        foreach ($values as $key=>$value) save_setting($key,$value);
        AuditLogger::record('offer_settings',1,'update',$old,$values,'Angebotseinstellungen gespeichert');
        return self::settings();
    }

    public static function overview(array $filters=[]): array
    {
        if (function_exists('ensure_delete_center_v23663')) ensure_delete_center_v23663();
        $where=["(o.deleted_at IS NULL OR o.deleted_at='0000-00-00 00:00:00')", "(g.id IS NULL OR g.deleted_at IS NULL OR g.deleted_at='0000-00-00 00:00:00')", "NOT EXISTS (SELECT 1 FROM guests gd WHERE gd.deleted_at IS NOT NULL AND gd.email IS NOT NULL AND gd.email<>'' AND o.guest_email IS NOT NULL AND o.guest_email<>'' AND LOWER(TRIM(gd.email))=LOWER(TRIM(o.guest_email)))"];$params=[];
        $status=trim((string)($filters['status']??''));
        $search=trim((string)($filters['search']??''));
        if ($status!=='' && in_array($status,['draft','sent','viewed','accepted','declined','expired','converted','archived'],true)) { $where[]='o.status=?';$params[]=$status; }
        if ($search!=='') {
            $where[]='(o.offer_number LIKE ? OR o.guest_name LIKE ? OR o.guest_email LIKE ? OR at.name LIKE ? OR a.name LIKE ?)';
            $like='%'.$search.'%';array_push($params,$like,$like,$like,$like,$like);
        }
        $sql="SELECT o.*,at.name apartment_type_name,a.name apartment_name,a.code apartment_code,b.reference booking_reference,b.is_upgrade booking_is_upgrade,b.upgrade_note booking_upgrade_note,b.internal_notes booking_internal_notes,
              (SELECT e.event_type FROM offer_events e WHERE e.offer_id=o.id AND e.event_type LIKE 'no_availability_%' ORDER BY e.id DESC LIMIT 1) availability_event_type,
              (SELECT e.created_at FROM offer_events e WHERE e.offer_id=o.id AND e.event_type LIKE 'no_availability_%' ORDER BY e.id DESC LIMIT 1) availability_event_at,
              u.name created_by_name
              FROM offers o
              LEFT JOIN apartment_types at ON at.id=o.apartment_type_id
              LEFT JOIN apartments a ON a.id=o.apartment_id
              LEFT JOIN bookings b ON b.id=o.booking_id
              LEFT JOIN guests g ON g.id=o.guest_id
              LEFT JOIN users u ON u.id=o.created_by
              WHERE ".implode(' AND ',$where)." ORDER BY o.created_at DESC,o.id DESC LIMIT 1000";
        $stmt=db()->prepare($sql);$stmt->execute($params);$offers=$stmt->fetchAll();
        foreach($offers as &$offer){
            self::expireIfNeeded($offer);
            $offer['public_url']=self::publicUrlForRow($offer);
        }unset($offer);
        $counts=[];
        $countSql="SELECT o.status,COUNT(*) total FROM offers o LEFT JOIN guests g ON g.id=o.guest_id WHERE (o.deleted_at IS NULL OR o.deleted_at='0000-00-00 00:00:00') AND (g.id IS NULL OR g.deleted_at IS NULL OR g.deleted_at='0000-00-00 00:00:00') AND NOT EXISTS (SELECT 1 FROM guests gd WHERE gd.deleted_at IS NOT NULL AND gd.email IS NOT NULL AND gd.email<>'' AND o.guest_email IS NOT NULL AND o.guest_email<>'' AND LOWER(TRIM(gd.email))=LOWER(TRIM(o.guest_email))) GROUP BY o.status";
        foreach(db()->query($countSql)->fetchAll() as $row)$counts[$row['status']]=(int)$row['total'];
        return ['offers'=>$offers,'counts'=>$counts,'settings'=>self::settings()];
    }

    public static function formData(): array
    {
        $guests=db()->query("SELECT id,title,first_name,last_name,second_last_name,email,phone,address,postal_code,city,country,language FROM guests WHERE (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00') ORDER BY CASE WHEN TRIM(COALESCE(last_name,''))='' THEN 1 ELSE 0 END,last_name COLLATE utf8mb4_unicode_ci,first_name COLLATE utf8mb4_unicode_ci,email COLLATE utf8mb4_unicode_ci LIMIT 5000")->fetchAll();
        $types=db()->query("SELECT id,name,code,max_occupancy,standard_price,cleaning_fee,breakfast_price,half_board_price,parking_price,pet_price,extra_bed_price,baby_bed_price,description,amenities_json FROM apartment_types WHERE active=1 ORDER BY sort_order,name")->fetchAll();
        $apartments=db()->query("SELECT a.id,a.name,a.code,a.apartment_number,a.apartment_type_id,a.max_guests,a.base_price,a.cleaning_fee,a.breakfast_price,a.half_board_price,a.parking_price_per_night,a.pet_price_per_night,a.extra_bed_price_per_night,a.baby_bed_fee,a.late_checkout_fee,a.status,a.out_of_service,h.name house_name FROM apartments a LEFT JOIN houses h ON h.id=a.house_id WHERE a.status='active' AND a.out_of_service=0 ORDER BY h.sort_order,h.name,a.sort_order,a.name")->fetchAll();
        $services=self::services();
        $typeTranslations=self::typeTranslations();
        return ['guests'=>$guests,'apartment_types'=>$types,'apartments'=>$apartments,'services'=>$services,'content_blocks'=>self::contentBlocks(),'type_translations'=>$typeTranslations,'settings'=>self::settings(),'languages'=>self::LANGUAGES];
    }

    public static function get(int $id): array
    {
        $stmt=db()->prepare("SELECT o.*,at.name apartment_type_name,a.name apartment_name,a.code apartment_code,b.reference booking_reference,b.is_upgrade booking_is_upgrade,b.upgrade_note booking_upgrade_note,b.internal_notes booking_internal_notes,(SELECT e.event_type FROM offer_events e WHERE e.offer_id=o.id AND e.event_type LIKE 'no_availability_%' ORDER BY e.id DESC LIMIT 1) availability_event_type,(SELECT e.created_at FROM offer_events e WHERE e.offer_id=o.id AND e.event_type LIKE 'no_availability_%' ORDER BY e.id DESC LIMIT 1) availability_event_at FROM offers o LEFT JOIN apartment_types at ON at.id=o.apartment_type_id LEFT JOIN apartments a ON a.id=o.apartment_id LEFT JOIN bookings b ON b.id=o.booking_id WHERE o.id=? LIMIT 1");
        $stmt->execute([$id]);$offer=$stmt->fetch();
        if(!$offer)throw new RuntimeException('Angebot nicht gefunden.');
        self::expireIfNeeded($offer);
        $itemsStmt=db()->prepare('SELECT * FROM offer_items WHERE offer_id=? ORDER BY sort_order,id');$itemsStmt->execute([$id]);
        $offer['items']=$itemsStmt->fetchAll();
        $offer['calculation_input']=json_decode((string)($offer['calculation_input_json']??''),true)?:[];
        $offer['price_snapshot']=json_decode((string)($offer['price_snapshot_json']??''),true)?:[];
        $offer['document_options']=self::documentOptions(json_decode((string)($offer['document_options_json']??''),true)?:[]);
        $offer['document_snapshot']=json_decode((string)($offer['document_snapshot_json']??''),true)?:[];
        $offer['email_content']=json_decode((string)($offer['email_content_json']??''),true)?:[];
        $offer['public_page']=json_decode((string)($offer['public_page_json']??''),true)?:[];
        $events=db()->prepare('SELECT e.*,u.name user_name FROM offer_events e LEFT JOIN users u ON u.id=e.user_id WHERE e.offer_id=? ORDER BY e.created_at DESC,e.id DESC LIMIT 100');$events->execute([$id]);$offer['events']=$events->fetchAll();
        $root=(int)($offer['parent_offer_id']?:$offer['id']);$revisions=db()->prepare('SELECT id,offer_number,revision_number,status,total_amount,created_at FROM offers WHERE id=? OR parent_offer_id=? ORDER BY revision_number,id');$revisions->execute([$root,$root]);$offer['revisions']=$revisions->fetchAll();
        $offer['public_url']=self::publicUrlForRow($offer);
        return $offer;
    }

    public static function calculate(array $data): array
    {
        $arrival=trim((string)($data['arrival']??''));$departure=trim((string)($data['departure']??''));
        if(!valid_date($arrival)||!valid_date($departure)||$arrival>=$departure)throw new ValidationException('Bitte einen gültigen An- und Abreisezeitraum wählen.');
        $apartmentId=(int)($data['apartment_id']??0);$typeId=(int)($data['apartment_type_id']??0);
        if(!$apartmentId&&!$typeId)throw new ValidationException('Bitte einen Wohnungstyp oder eine konkrete Wohnung wählen.');
        if($apartmentId){
            $stmt=db()->prepare('SELECT a.*,t.name apartment_type_name,t.id resolved_type_id FROM apartments a LEFT JOIN apartment_types t ON t.id=a.apartment_type_id WHERE a.id=? AND a.status=\'active\' AND a.out_of_service=0 LIMIT 1');$stmt->execute([$apartmentId]);$accommodation=$stmt->fetch();
            if(!$accommodation)throw new ValidationException('Die gewählte Wohnung ist nicht verfügbar oder nicht aktiv.');
            $typeId=(int)($accommodation['resolved_type_id']??$typeId);
            if(booking_conflict($apartmentId,$arrival,$departure))throw new ConflictException('Die gewählte Wohnung ist in diesem Zeitraum bereits durch eine Buchung belegt.');
            $rate=PricingService::accommodation($apartmentId,$arrival,$departure);
        }else{
            $stmt=db()->prepare('SELECT * FROM apartment_types WHERE id=? AND active=1 LIMIT 1');$stmt->execute([$typeId]);$accommodation=$stmt->fetch();
            if(!$accommodation)throw new ValidationException('Der gewählte Wohnungstyp ist nicht aktiv.');
            $rate=PricingService::accommodationForType($typeId,$arrival,$departure);
        }
        $nights=(int)$rate['nights'];
        if($nights<=0)throw new ValidationException('Der Aufenthalt muss mindestens eine Nacht umfassen.');
        $missingDates=[];
        foreach((array)($rate['nightly']??[]) as $night){
            if((float)($night['price']??0)<=0)$missingDates[]=(string)($night['date']??'');
        }
        if($missingDates){
            throw new MissingPriceException(
                'Für diesen Wohnungstyp und Zeitraum ist noch kein Preis hinterlegt. Möchten Sie jetzt einen Preis eingeben?',
                [
                    'apartment_type_id'=>$typeId,
                    'apartment_id'=>$apartmentId?:null,
                    'arrival'=>$arrival,
                    'departure'=>$departure,
                    'missing_dates'=>array_values(array_filter($missingDates)),
                    'scope'=>$apartmentId?'apartment':'apartment_type',
                ]
            );
        }
        $adults=max(1,(int)($data['adults']??1));$children=max(0,(int)($data['children']??0));$babies=max(0,(int)($data['babies']??0));$pets=max(0,(int)($data['pets']??0));
        $childAges=BookingPolicyService::childAges($data['child_ages']??[],$children,true);
        $capacityOverride=normalize_bool($data['capacity_override']??0);$capacityReason=trim((string)($data['capacity_override_reason']??''));
        $capacity=BookingPolicyService::capacityCheck($typeId,$adults,$children,$babies,(bool)$capacityOverride,$capacityReason);
        if($nights<(int)$rate['minimum_stay']&&!normalize_bool($data['min_stay_override']??0))throw new ConflictException('Der Mindestaufenthalt beträgt '.$rate['minimum_stay'].' Nächte ('.$rate['minimum_source'].').');

        $currency=(string)setting('offer_currency','EUR');$vatRate=(float)setting('offer_vat_rate',10);$includeVat=normalize_bool(setting('offer_prices_include_vat',1));
        $items=[];$sort=10;
        $accommodationAmount=(float)$rate['amount'];
        $items[]=self::item('accommodation','Unterkunft',$nights,'Nacht',$nights?round($accommodationAmount/$nights,2):0,$vatRate,$accommodationAmount,$sort++,'pricing',$apartmentId?:$typeId,['nightly'=>$rate['nightly']]);

        $cleaning=(float)($accommodation['cleaning_fee']??0);
        if($cleaning>0)$items[]=self::item('cleaning','Endreinigung',1,'einmalig',$cleaning,$vatRate,$cleaning,$sort++,'accommodation',$apartmentId?:$typeId);
        $builtins=[
            ['parking_spaces','parking','Parkplatz','Nacht',(float)($apartmentId?($accommodation['parking_price_per_night']??0):($accommodation['parking_price']??0)),$nights],
            ['pets','pet','Haustier','Nacht',(float)($apartmentId?($accommodation['pet_price_per_night']??0):($accommodation['pet_price']??0)),$nights],
            ['extra_beds','extra_bed','Zusatzbett','Nacht',(float)($apartmentId?($accommodation['extra_bed_price_per_night']??0):($accommodation['extra_bed_price']??0)),$nights],
            ['baby_beds','baby_bed','Babybett','einmalig',(float)($apartmentId?($accommodation['baby_bed_fee']??0):($accommodation['baby_bed_price']??0)),1],
        ];
        foreach($builtins as [$field,$type,$label,$unit,$unitPrice,$multiplier]){
            $qty=max(0,(int)($data[$field]??0));if(!$qty||$unitPrice<=0)continue;
            $effective=$qty*$multiplier;$total=round($unitPrice*$effective,2);
            $items[]=self::item($type,$label,$effective,$unit,$unitPrice,$vatRate,$total,$sort++,'accommodation',$apartmentId?:$typeId,['requested_quantity'=>$qty,'nights'=>$nights]);
        }
        if(normalize_bool($data['late_checkout']??0)&&$apartmentId&&(float)($accommodation['late_checkout_fee']??0)>0){$price=(float)$accommodation['late_checkout_fee'];$items[]=self::item('late_checkout','Spätabreise',1,'einmalig',$price,$vatRate,$price,$sort++,'apartment',$apartmentId);}
        foreach([['breakfast','Frühstück','breakfast_price'],['half_board','Halbpension','half_board_price']] as [$field,$label,$priceField]){
            if(!normalize_bool($data[$field]??0))continue;$unitPrice=(float)($accommodation[$priceField]??0);if($unitPrice<=0)continue;
            $days=max(1,min($nights,(int)($data[$field.'_days']??$nights)));$effective=($adults+$children)*$days;$total=round($unitPrice*$effective,2);
            $items[]=self::item($field,$label,$effective,'Person/Tag',$unitPrice,$vatRate,$total,$sort++,'accommodation',$apartmentId?:$typeId,['days'=>$days,'persons'=>$adults+$children]);
        }

        $serviceSelections=is_array($data['services']??null)?$data['services']:[];
        if($serviceSelections){
            $ids=[];foreach($serviceSelections as $sel){$id=(int)($sel['service_id']??0);if($id)$ids[]=$id;}
            if($ids){$placeholders=implode(',',array_fill(0,count($ids),'?'));$stmt=db()->prepare("SELECT * FROM offer_services WHERE active=1 AND id IN ($placeholders)");$stmt->execute($ids);$catalog=[];foreach($stmt->fetchAll() as $row)$catalog[(int)$row['id']]=$row;
                foreach($serviceSelections as $sel){$id=(int)($sel['service_id']??0);$service=$catalog[$id]??null;if(!$service)continue;$requested=max(0,(float)($sel['quantity']??1));if($requested<=0)continue;$unitMode=(string)$service['unit_mode'];$effective=match($unitMode){'per_night'=>$requested*$nights,'per_person'=>$requested*($adults+$children),'per_person_night'=>$requested*($adults+$children)*$nights,default=>$requested};$total=round((float)$service['default_price']*$effective,2);$items[]=self::item('service',(string)$service['name'],$effective,self::unitLabel($unitMode),(float)$service['default_price'],(float)$service['vat_rate'],$total,$sort++,'offer_service',$id,['requested_quantity'=>$requested,'unit_mode'=>$unitMode]);}
            }
        }

        $itemsTotal=round(array_sum(array_map(fn($i)=>(float)$i['line_total'],$items)),2);
        $lengthPercent=0.0;$stmt=db()->prepare('SELECT percent FROM length_discounts WHERE active=1 AND min_nights<=? ORDER BY min_nights DESC LIMIT 1');$stmt->execute([$nights]);$lengthPercent=(float)($stmt->fetchColumn()?:0);$lengthDiscount=round($accommodationAmount*$lengthPercent/100,2);
        $code=strtoupper(trim((string)($data['discount_code']??'')));$codeDiscount=0.0;$discountCodeId=null;
        if($code!==''){$stmt=db()->prepare("SELECT * FROM discount_codes WHERE UPPER(code)=? AND active=1 AND (start_date IS NULL OR start_date<=?) AND (end_date IS NULL OR end_date>=?) AND min_nights<=? AND (apartment_id IS NULL OR apartment_id=?) AND (max_uses IS NULL OR used_count<max_uses) LIMIT 1");$stmt->execute([$code,$arrival,$departure,$nights,$apartmentId?:0]);$rule=$stmt->fetch();if(!$rule)throw new ValidationException('Der Rabattcode ist für dieses Angebot nicht gültig.');$discountCodeId=(int)$rule['id'];$basis=max(0,$itemsTotal-$lengthDiscount);$codeDiscount=$rule['discount_type']==='fixed'?min($basis,(float)$rule['discount_value']):round($basis*(float)$rule['discount_value']/100,2);}
        $manualDiscount=max(0,(float)($data['manual_discount']??0));$manualDiscount=min(max(0,$itemsTotal-$lengthDiscount-$codeDiscount),$manualDiscount);if($manualDiscount>0&&trim((string)($data['manual_discount_reason']??''))==='')throw new ValidationException('Bitte den manuellen Rabatt kurz begründen.');
        if($lengthDiscount>0)$items[]=self::item('discount','Aufenthaltsrabatt '.$lengthPercent.' %',1,'Rabatt',-$lengthDiscount,$vatRate,-$lengthDiscount,$sort++,'length_discount',null,['percentage'=>$lengthPercent]);
        if($codeDiscount>0)$items[]=self::item('discount','Rabattcode '.$code,1,'Rabatt',-$codeDiscount,$vatRate,-$codeDiscount,$sort++,'discount_code',$discountCodeId,['code'=>$code]);
        if($manualDiscount>0)$items[]=self::item('discount','Manueller Rabatt',1,'Rabatt',-$manualDiscount,$vatRate,-$manualDiscount,$sort++,'manual',null,['reason'=>trim((string)($data['manual_discount_reason']??''))]);

        $taxNights=(int)setting('offer_tourist_tax_max_nights',7);if($taxNights<=0)$taxNights=$nights;else $taxNights=min($taxNights,$nights);
        $minTaxAge=(int)setting('offer_tourist_tax_min_age',16);if(!in_array($minTaxAge,[16,17,18],true))$minTaxAge=16;
        $taxableChildren=0;for($i=0;$i<$children;$i++){if(($childAges[$i]??0)>=$minTaxAge)$taxableChildren++;}
        $taxPersons=$adults+$taxableChildren;$touristUnit=max(0,(float)setting('offer_tourist_tax_per_person_night',0));$touristTax=round($touristUnit*$taxPersons*$taxNights,2);
        if($touristTax>0)$items[]=self::item('tourist_tax','Touristensteuer',$taxPersons*$taxNights,'Person/Nacht',$touristUnit,0,$touristTax,$sort++,'settings',null,['persons'=>$taxPersons,'nights'=>$taxNights]);

        $subtotalNet=0.0;$vatAmount=0.0;$grossItems=0.0;
        foreach($items as $item){
            $line=(float)$item['line_total'];$lineVat=max(0,(float)$item['vat_rate']);$grossItems+=$line;
            if($includeVat){$tax=$lineVat>0?($line-($line/(1+$lineVat/100))):0.0;$subtotalNet+=($line-$tax);$vatAmount+=$tax;}
            else{$subtotalNet+=$line;$vatAmount+=($lineVat>0?$line*$lineVat/100:0.0);}
        }
        $subtotalNet=round($subtotalNet,2);$vatAmount=round($vatAmount,2);$total=$includeVat?round($grossItems,2):round($subtotalNet+$vatAmount,2);
        $depositPercent=max(0,min(100,(float)($data['deposit_percent']??setting('offer_deposit_percent',30))));$deposit=round($total*$depositPercent/100,2);$remaining=round($total-$deposit,2);
        return [
            'currency'=>$currency,'scope'=>$apartmentId?'apartment':'apartment_type','apartment_id'=>$apartmentId?:null,'apartment_type_id'=>$typeId?:null,
            'arrival'=>$arrival,'departure'=>$departure,'nights'=>$nights,'adults'=>$adults,'children'=>$children,'babies'=>$babies,'pets'=>$pets,'child_ages'=>$childAges,'capacity'=>$capacity,
            'minimum_stay'=>(int)$rate['minimum_stay'],'minimum_source'=>(string)$rate['minimum_source'],'minimum_valid'=>$nights>=(int)$rate['minimum_stay'],
            'items'=>$items,'subtotal_net'=>$subtotalNet,'vat_rate'=>$vatRate,'vat_amount'=>$vatAmount,'prices_include_vat'=>$includeVat,
            'tourist_tax'=>$touristTax,'discount_amount'=>round($lengthDiscount+$codeDiscount+$manualDiscount,2),'discount_code'=>$code,'discount_code_id'=>$discountCodeId,
            'total_amount'=>$total,'deposit_percent'=>$depositPercent,'deposit_amount'=>$deposit,'remaining_amount'=>$remaining,
        ];
    }

    public static function saveMissingPrice(array $data): array
    {
        $typeId=(int)($data['apartment_type_id']??0);
        $arrival=trim((string)($data['arrival']??''));
        $departure=trim((string)($data['departure']??''));
        $price=round((float)($data['nightly_price']??0),2);
        $minStay=max(1,min(365,(int)($data['min_stay']??1)));
        $mode=(string)($data['price_save_mode']??'season');
        if($typeId<=0)throw new ValidationException('Bitte einen Wohnungstyp auswählen.');
        if(!valid_date($arrival)||!valid_date($departure)||$arrival>=$departure)throw new ValidationException('Der Zeitraum für den Preis ist ungültig.');
        if($price<=0)throw new ValidationException('Bitte einen Preis größer als 0 Euro eingeben.');
        if(!in_array($mode,['season','standard'],true))throw new ValidationException('Die gewählte Speicherart ist ungültig.');
        $stmt=db()->prepare('SELECT id,name,standard_price FROM apartment_types WHERE id=? AND active=1 LIMIT 1');
        $stmt->execute([$typeId]);$type=$stmt->fetch();
        if(!$type)throw new ValidationException('Der gewählte Wohnungstyp ist nicht aktiv.');

        $pdo=db();$pdo->beginTransaction();
        try{
            if($mode==='standard'){
                $pdo->prepare('UPDATE apartment_types SET standard_price=?,default_min_stay=? WHERE id=?')->execute([$price,$minStay,$typeId]);
                AuditLogger::record('apartment_type',$typeId,'offer_price_create',$type,['standard_price'=>$price,'default_min_stay'=>$minStay],'Fehlenden Standardpreis direkt aus der Angebotserstellung ergänzt');
                $message='Der Standardpreis des Wohnungstyps wurde gespeichert.';
            }else{
                $seasonName=mb_substr(trim((string)($data['season_name']??'')),0,120);
                if($seasonName===''){
                    $seasonName='Angebotspreis '.(new DateTimeImmutable($arrival))->format('d.m.Y').'–'.(new DateTimeImmutable($departure))->modify('-1 day')->format('d.m.Y');
                }
                $find=$pdo->prepare('SELECT id FROM seasons WHERE name=? LIMIT 1');$find->execute([$seasonName]);$seasonId=(int)($find->fetchColumn()?:0);
                if(!$seasonId){
                    $pdo->prepare("INSERT INTO seasons(name,color,priority,default_min_stay,legacy_multiplier,notes,active) VALUES(?, '#0f9f6e', 5000, ?, NULL, ?, 1)")
                        ->execute([$seasonName,$minStay,'Direkt aus der Angebotserstellung angelegter Saisonpreis.']);
                    $seasonId=(int)$pdo->lastInsertId();
                }else{
                    $pdo->prepare("UPDATE seasons SET color='#0f9f6e',priority=5000,default_min_stay=?,active=1 WHERE id=?")->execute([$minStay,$seasonId]);
                }
                $periodEnd=(new DateTimeImmutable($departure))->modify('-1 day')->format('Y-m-d');
                $period=$pdo->prepare('SELECT id FROM season_periods WHERE season_id=? AND start_date=? AND end_date=? LIMIT 1');
                $period->execute([$seasonId,$arrival,$periodEnd]);
                if(!$period->fetchColumn()){
                    $pdo->prepare('INSERT INTO season_periods(season_id,start_date,end_date,min_stay,notes) VALUES(?,?,?,?,?)')
                        ->execute([$seasonId,$arrival,$periodEnd,$minStay,'Aus fehlender Angebotsberechnung ergänzt']);
                }
                $pdo->prepare('INSERT INTO season_type_prices(season_id,apartment_type_id,nightly_price,min_stay) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE nightly_price=VALUES(nightly_price),min_stay=VALUES(min_stay)')
                    ->execute([$seasonId,$typeId,$price,$minStay]);
                AuditLogger::record('season',$seasonId,'offer_price_create',null,['apartment_type_id'=>$typeId,'arrival'=>$arrival,'departure'=>$departure,'nightly_price'=>$price,'min_stay'=>$minStay],'Fehlenden Saisonpreis direkt aus der Angebotserstellung ergänzt');
                $message='Der Saisonpreis für den gewählten Zeitraum wurde gespeichert.';
            }
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        PricingService::resetCaches();
        return ['message'=>$message,'apartment_type_id'=>$typeId,'nightly_price'=>$price,'mode'=>$mode];
    }

    public static function communication(int $id): array
    {
        $offer=self::get($id);
        $email=self::emailContent($offer);
        $public=self::publicPage($offer,$email);
        $emailOffer=$offer;$emailOffer['email_content']=$email;$emailOffer['public_page']=$public;
        $publicOffer=$emailOffer;
        return [
            'offer'=>[
                'id'=>(int)$offer['id'],'offer_number'=>(string)$offer['offer_number'],'status'=>(string)$offer['status'],
                'guest_name'=>(string)$offer['guest_name'],'guest_email'=>(string)($offer['guest_email']??''),
                'language'=>(string)$offer['language'],'public_url'=>(string)($offer['public_url']??''),
                'arrival'=>(string)$offer['arrival'],'departure'=>(string)$offer['departure'],
                'adults'=>(int)$offer['adults'],'children'=>(int)$offer['children'],'babies'=>(int)($offer['babies']??0),'pets'=>(int)($offer['pets']??0),
                'total_amount'=>(float)$offer['total_amount'],'currency'=>(string)$offer['currency'],
                'accommodation'=>self::accommodationLabel($offer,(string)$offer['language']),
            ],
            'email_content'=>$email,
            'public_page'=>$public,
            'content_blocks'=>self::editorContentBlocks((string)$offer['language']),
            'email_preview'=>self::render($emailOffer,false,'email')['html'],
            'public_preview'=>self::render($publicOffer,false,'public')['html'],
        ];
    }

    public static function previewCommunication(array $data): array
    {
        $id=(int)($data['id']??0);if(!$id)throw new ValidationException('Das Angebot fehlt.');
        $offer=self::get($id);
        $snapshot=is_array($offer['document_snapshot']??null)?$offer['document_snapshot']:[];
        $email=self::normalizeEmailContent(is_array($data['email_content']??null)?$data['email_content']:[],$offer,$snapshot);
        $public=self::normalizePublicPage(is_array($data['public_page']??null)?$data['public_page']:[],$offer,$snapshot,$email);
        $offer['email_content']=$email;$offer['public_page']=$public;$offer['email_subject']=$email['subject'];
        return [
            'email_content'=>$email,'public_page'=>$public,
            'email_preview'=>self::render($offer,false,'email')['html'],
            'public_preview'=>self::render($offer,false,'public')['html'],
        ];
    }

    public static function saveCommunication(array $data): array
    {
        $id=(int)($data['id']??0);if(!$id)throw new ValidationException('Das Angebot fehlt.');
        $offer=self::get($id);
        if((string)$offer['status']!=='draft')throw new ConflictException('Nur ein Angebotsentwurf kann direkt bearbeitet werden. Bitte zuerst eine neue Revision erstellen.');
        $snapshot=is_array($offer['document_snapshot']??null)?$offer['document_snapshot']:[];
        $email=self::normalizeEmailContent(is_array($data['email_content']??null)?$data['email_content']:[],$offer,$snapshot);
        $public=self::normalizePublicPage(is_array($data['public_page']??null)?$data['public_page']:[],$offer,$snapshot,$email);
        $blockIds=array_values(array_unique(array_merge((array)$email['content_block_ids'],(array)$public['content_block_ids'])));
        $options=self::documentOptions($offer['document_options']??[]);$options['content_block_ids']=$blockIds;
        $documentSnapshot=self::buildDocumentSnapshot((string)$offer['language'],(int)$offer['apartment_type_id'],(int)$offer['apartment_id'],$options,(array)$offer['items']);
        $personal=self::richToPlain((string)($email['personal_html']??''));
        $pdo=db();$pdo->beginTransaction();
        try{
            $lock=$pdo->prepare("SELECT status FROM offers WHERE id=? FOR UPDATE");$lock->execute([$id]);
            if((string)$lock->fetchColumn()!=='draft')throw new ConflictException('Das Angebot wurde inzwischen versendet oder abgeschlossen.');
            $pdo->prepare('UPDATE offers SET email_subject=?,personal_message=?,email_content_json=?,public_page_json=?,document_options_json=?,document_snapshot_json=?,updated_by=?,updated_at=NOW() WHERE id=?')
                ->execute([
                    mb_substr((string)$email['subject'],0,255),mb_substr($personal,0,10000),
                    json_encode($email,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                    json_encode($public,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                    json_encode($options,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                    json_encode($documentSnapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                    Auth::user()['id']??null,$id,
                ]);
            self::event($id,'communication_updated',null,['email_sections'=>count($email['sections']),'public_sections'=>count($public['sections'])]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        AuditLogger::record('offer',$id,'communication_update',null,['email_content'=>$email,'public_page'=>$public],'E-Mail und öffentliche Angebotsseite bearbeitet');
        return self::communication($id);
    }

    public static function save(array $data): array
    {
        $id=(int)($data['id']??0);
        $old=$id?self::get($id):null;
        if($old && $old['status']!=='draft'){
            throw new ConflictException('Ein bereits versendetes oder abgeschlossenes Angebot kann nicht überschrieben werden. Bitte „Überarbeiten“ verwenden.');
        }

        $language=self::normalizeLanguage((string)($data['language']??'de'));
        $price=self::calculate($data);
        $validUntil=trim((string)($data['valid_until']??''));
        if(!valid_date($validUntil)){
            $validUntil=(new DateTimeImmutable())->modify('+'.(int)setting('offer_validity_days',7).' days')->format('Y-m-d');
        }
        if($validUntil<date('Y-m-d'))throw new ValidationException('Die Gültigkeit des Angebots darf nicht in der Vergangenheit liegen.');
        $depositDueDate=trim((string)($data['deposit_due_date']??''));
        if($depositDueDate!==''&&!valid_date($depositDueDate))throw new ValidationException('Das Fälligkeitsdatum der Anzahlung ist ungültig.');
        if($depositDueDate!==''&&$depositDueDate<date('Y-m-d'))throw new ValidationException('Das Fälligkeitsdatum der Anzahlung darf nicht in der Vergangenheit liegen.');
        if($depositDueDate!==''&&$depositDueDate>$price['arrival'])throw new ValidationException('Die Anzahlung darf nicht erst nach der Anreise fällig werden.');
        $depositDueDate=$depositDueDate?:null;
        $personal=mb_substr(trim((string)($data['personal_message']??'')),0,10000);
        $internal=mb_substr(trim((string)($data['internal_notes']??'')),0,10000);
        $subjectInput=mb_substr(trim((string)($data['email_subject']??'')),0,255);
        $documentOptions=self::documentOptions($data);
        $calculationInput=self::calculationInput($data,$price);
        $pdo=db();
        $pdo->beginTransaction();
        try{
            if($id){
                $lock=$pdo->prepare('SELECT status FROM offers WHERE id=? FOR UPDATE');
                $lock->execute([$id]);
                $lockedStatus=$lock->fetchColumn();
                if($lockedStatus===false)throw new RuntimeException('Angebot nicht gefunden.');
                if($lockedStatus!=='draft')throw new ConflictException('Das Angebot wurde inzwischen versendet oder abgeschlossen. Bitte eine Revision erstellen.');
            }

            // Ein neuer Gast und sein Angebot entstehen atomar. Bei einem Fehler
            // bleibt kein unvollständiger Gastdatensatz zurück.
            $guest=self::resolveGuest($data);
            $token=$old?Crypto::decrypt((string)($old['public_token_encrypted']??'')):bin2hex(random_bytes(32));
            if($token==='')$token=bin2hex(random_bytes(32));
            $number=$old?(string)$old['offer_number']:self::nextNumber('offer',(string)setting('offer_number_prefix','ANG'));
            $template=self::template($language);
            $subject=$subjectInput!==''?$subjectInput:str_replace('{number}',$number,trim((string)($template['email_subject']??self::tr($language,'email_subject',['number'=>$number]))));
            if($subject==='')$subject=self::tr($language,'email_subject',['number'=>$number]);
            $subject=str_replace(['{number}','{guest}'],[$number,(string)$guest['name']],$subject);
            $documentSnapshot=self::buildDocumentSnapshot($language,(int)($price['apartment_type_id']??0),(int)($price['apartment_id']??0),$documentOptions,(array)($price['items']??[]));
            $contentOffer=[
                'id'=>$id,'offer_number'=>$number,'guest_name'=>(string)$guest['name'],'guest_email'=>(string)$guest['email'],
                'language'=>$language,'apartment_type_id'=>$price['apartment_type_id'],'apartment_id'=>$price['apartment_id'],
                'arrival'=>$price['arrival'],'departure'=>$price['departure'],'adults'=>$price['adults'],'children'=>$price['children'],
                'babies'=>$price['babies'],'pets'=>$price['pets'],'status'=>'draft','valid_until'=>$validUntil,'currency'=>$price['currency'],
                'subtotal_net'=>$price['subtotal_net'],'vat_rate'=>$price['vat_rate'],'vat_amount'=>$price['vat_amount'],
                'tourist_tax'=>$price['tourist_tax'],'discount_amount'=>$price['discount_amount'],'total_amount'=>$price['total_amount'],
                'deposit_percent'=>$price['deposit_percent'],'deposit_amount'=>$price['deposit_amount'],'deposit_due_date'=>$depositDueDate,
                'remaining_amount'=>$price['remaining_amount'],'personal_message'=>$personal,'email_subject'=>$subject,
                'document_options'=>$documentOptions,'document_snapshot'=>$documentSnapshot,'items'=>$price['items'],
            ];
            if($old){
                $storedEmail=is_array($old['email_content']??null)?$old['email_content']:[];
                $storedPublic=is_array($old['public_page']??null)?$old['public_page']:[];
                if($subjectInput!=='')$storedEmail['subject']=$subject;
                if(array_key_exists('personal_message',$data))$storedEmail['personal_html']=self::richFromValue($personal);
                $emailContent=self::normalizeEmailContent($storedEmail,$contentOffer,$documentSnapshot);
                $publicPage=self::normalizePublicPage($storedPublic,$contentOffer,$documentSnapshot,$emailContent);
            }else{
                $emailContent=self::normalizeEmailContent([],$contentOffer,$documentSnapshot);
                $publicPage=self::normalizePublicPage([],$contentOffer,$documentSnapshot,$emailContent);
            }
            $subject=(string)$emailContent['subject'];
            $userId=Auth::user()['id']??null;

            $record=[
                'offer_number'=>$number,
                'guest_id'=>$guest['guest_id'],
                'guest_name'=>$guest['name'],
                'guest_email'=>$guest['email'],
                'guest_phone'=>$guest['phone'],
                'guest_address'=>$guest['address'],
                'language'=>$language,
                'apartment_type_id'=>$price['apartment_type_id'],
                'apartment_id'=>$price['apartment_id'],
                'arrival'=>$price['arrival'],
                'departure'=>$price['departure'],
                'adults'=>$price['adults'],
                'children'=>$price['children'],
                'babies'=>$price['babies'],
                'pets'=>$price['pets'],
                'status'=>'draft',
                'valid_until'=>$validUntil,
                'currency'=>$price['currency'],
                'subtotal_net'=>$price['subtotal_net'],
                'vat_rate'=>$price['vat_rate'],
                'vat_amount'=>$price['vat_amount'],
                'tourist_tax'=>$price['tourist_tax'],
                'discount_amount'=>$price['discount_amount'],
                'total_amount'=>$price['total_amount'],
                'deposit_percent'=>$price['deposit_percent'],
                'deposit_amount'=>$price['deposit_amount'],
                'deposit_due_date'=>$depositDueDate,
                'remaining_amount'=>$price['remaining_amount'],
                'personal_message'=>$personal,
                'internal_notes'=>$internal,
                'email_subject'=>$subject,
                'email_content_json'=>json_encode($emailContent,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'public_page_json'=>json_encode($publicPage,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'document_options_json'=>json_encode($documentOptions,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'document_snapshot_json'=>json_encode($documentSnapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'calculation_input_json'=>json_encode($calculationInput,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'price_snapshot_json'=>json_encode($price,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'public_token_hash'=>hash('sha256',$token),
                'public_token_encrypted'=>Crypto::encrypt($token),
                'updated_by'=>$userId,
            ];

            if($id){
                $sets=[];$values=[];
                foreach($record as $column=>$value){$sets[]='`'.$column.'`=?';$values[]=$value;}
                $values[]=$id;
                $pdo->prepare('UPDATE offers SET '.implode(',',$sets).',updated_at=NOW() WHERE id=?')->execute($values);
            }else{
                $record['created_by']=$userId;
                $columns=array_keys($record);
                $quoted=implode(',',array_map(static fn(string $column):string=>'`'.$column.'`',$columns));
                $marks=implode(',',array_fill(0,count($record),'?'));
                $pdo->prepare("INSERT INTO offers({$quoted}) VALUES({$marks})")->execute(array_values($record));
                $id=(int)$pdo->lastInsertId();
            }

            $pdo->prepare('DELETE FROM offer_items WHERE offer_id=?')->execute([$id]);
            $ins=$pdo->prepare('INSERT INTO offer_items(offer_id,item_type,description,quantity,unit,unit_price,vat_rate,line_total,sort_order,source_type,source_id,metadata_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach($price['items'] as $item){
                $ins->execute([$id,$item['item_type'],$item['description'],$item['quantity'],$item['unit'],$item['unit_price'],$item['vat_rate'],$item['line_total'],$item['sort_order'],$item['source_type'],$item['source_id'],$item['metadata_json']]);
            }
            self::event($id,$old?'updated':'created',$old?['status'=>$old['status'],'total'=>$old['total_amount']]:null,['status'=>'draft','total'=>$price['total_amount']]);
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
        $new=self::get($id);
        AuditLogger::record('offer',$id,$old?'update':'create',$old,$new,$old?'Angebot bearbeitet':'Angebot angelegt');
        return $new;
    }

    public static function revise(int $id): array
    {
        $old=self::get($id);
        if($old['status']==='draft')return $old;
        $root=(int)($old['parent_offer_id']?:$old['id']);
        $newToken=bin2hex(random_bytes(32));
        $pdo=db();
        $pdo->beginTransaction();
        try{
            // Sperrt die Revisionsfamilie, damit zwei parallele Klicks nicht
            // dieselbe Revisionsnummer erzeugen können.
            $lock=$pdo->prepare('SELECT id,offer_number FROM offers WHERE id=? FOR UPDATE');
            $lock->execute([$root]);
            $rootOffer=$lock->fetch();
            if(!$rootOffer)throw new RuntimeException('Das Ursprungsangebot wurde nicht gefunden.');

            $max=$pdo->prepare('SELECT COALESCE(MAX(revision_number),1)+1 FROM offers WHERE id=? OR parent_offer_id=?');
            $max->execute([$root,$root]);
            $revision=max(2,(int)$max->fetchColumn());
            $revisionNumber=(string)$rootOffer['offer_number'].'-R'.$revision;

            $stmt=$pdo->prepare("INSERT INTO offers(offer_number,parent_offer_id,revision_number,guest_id,guest_name,guest_email,guest_phone,guest_address,language,apartment_type_id,apartment_id,arrival,departure,adults,children,babies,pets,status,valid_until,currency,subtotal_net,vat_rate,vat_amount,tourist_tax,discount_amount,total_amount,deposit_percent,deposit_amount,deposit_due_date,remaining_amount,personal_message,internal_notes,email_subject,email_content_json,public_page_json,document_options_json,document_snapshot_json,calculation_input_json,price_snapshot_json,public_token_hash,public_token_encrypted,created_by,updated_by) SELECT ?,?, ?,guest_id,guest_name,guest_email,guest_phone,guest_address,language,apartment_type_id,apartment_id,arrival,departure,adults,children,babies,pets,'draft',DATE_ADD(CURDATE(),INTERVAL ? DAY),currency,subtotal_net,vat_rate,vat_amount,tourist_tax,discount_amount,total_amount,deposit_percent,deposit_amount,deposit_due_date,remaining_amount,personal_message,internal_notes,email_subject,email_content_json,public_page_json,document_options_json,document_snapshot_json,calculation_input_json,price_snapshot_json,?,?,?,? FROM offers WHERE id=?");
            $stmt->execute([$revisionNumber,$root,$revision,(int)setting('offer_validity_days',7),hash('sha256',$newToken),Crypto::encrypt($newToken),Auth::user()['id']??null,Auth::user()['id']??null,$id]);
            if($stmt->rowCount()!==1)throw new RuntimeException('Das Ausgangsangebot wurde nicht gefunden.');
            $newId=(int)$pdo->lastInsertId();

            $copy=$pdo->prepare('INSERT INTO offer_items(offer_id,item_type,description,quantity,unit,unit_price,vat_rate,line_total,sort_order,source_type,source_id,metadata_json) SELECT ?,item_type,description,quantity,unit,unit_price,vat_rate,line_total,sort_order,source_type,source_id,metadata_json FROM offer_items WHERE offer_id=?');
            $copy->execute([$newId,$id]);
            self::event($newId,'revision_created',['source_offer_id'=>$id],['revision'=>$revision]);
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
        AuditLogger::record('offer',$newId,'revise',['source_offer_id'=>$id],['revision'=>$revision],'Neue Angebotsrevision erstellt');
        return self::get($newId);
    }

    public static function send(int $id): array
    {
        $offer=self::get($id);
        if(!in_array((string)$offer['status'],['draft','sent','viewed'],true)){
            throw new ConflictException('Dieses Angebot kann nicht versendet werden. Für Änderungen bitte eine Revision erstellen.');
        }
        if(!filter_var((string)$offer['guest_email'],FILTER_VALIDATE_EMAIL)){
            throw new ValidationException('Für den Versand fehlt eine gültige E-Mail-Adresse des Gastes.');
        }
        $initial=(string)$offer['status']==='draft';
        $content=self::render($offer,true,'email');
        $email=self::emailContent($offer);
        $subject=self::replacePlaceholders(trim((string)($email['subject']??$offer['email_subject']??'')),$offer);
        if($subject==='')$subject=self::tr((string)$offer['language'],'email_subject',['number'=>$offer['offer_number']]);
        try{
            $result=SmtpMailer::send((string)$offer['guest_email'],$subject,$content['text'],$content['html']);
            CommunicationLogger::record('email','offer',$id,(string)$offer['guest_name'],(string)$offer['guest_email'],$subject,$content['text'],'sent',(string)($result['message_id']??''));
        }catch(Throwable $e){
            CommunicationLogger::record('email','offer',$id,(string)$offer['guest_name'],(string)$offer['guest_email'],$subject,$content['text'],'failed',$e->getMessage());
            throw $e;
        }
        if($initial){
            db()->prepare("UPDATE offers SET status='sent',sent_at=NOW(),updated_by=?,updated_at=NOW() WHERE id=? AND status='draft'")->execute([Auth::user()['id']??null,$id]);
            self::event($id,'sent',null,['recipient'=>$offer['guest_email']]);
            AuditLogger::record('offer',$id,'send',['status'=>'draft'],['status'=>'sent'],'Angebot versendet');
        }else{
            db()->prepare('UPDATE offers SET sent_at=NOW(),updated_by=?,updated_at=NOW() WHERE id=?')->execute([Auth::user()['id']??null,$id]);
            self::event($id,'resent',['status'=>$offer['status']],['recipient'=>$offer['guest_email']]);
            AuditLogger::record('offer',$id,'resend',['status'=>$offer['status']],['status'=>$offer['status']],'Angebot erneut versendet');
        }
        return self::get($id);
    }

    public static function archive(int $id): array
    {
        $old=self::get($id);if($old['status']==='converted')throw new ConflictException('Ein in eine Buchung übernommenes Angebot wird nicht archiviert.');db()->prepare("UPDATE offers SET status='archived',archived_at=NOW(),updated_by=?,updated_at=NOW() WHERE id=?")->execute([Auth::user()['id']??null,$id]);self::event($id,'archived',['status'=>$old['status']],['status'=>'archived']);AuditLogger::record('offer',$id,'archive',$old,['status'=>'archived'],'Angebot archiviert');return self::get($id);
    }

    public static function convertToBooking(int $id, int $apartmentId=0, array $workflowOptions=[]): array
    {
        $offer=self::get($id);
        if($offer['status']!=='accepted')throw new ConflictException('Nur ein angenommenes Angebot kann in eine Buchung übernommen werden.');
        if((int)$offer['booking_id'])throw new ConflictException('Dieses Angebot ist bereits mit einer Buchung verknüpft.');
        $apartmentId=$apartmentId?:((int)$offer['apartment_id']);
        if(!$apartmentId)throw new ValidationException('Bitte vor der Übernahme eine konkrete Wohnung auswählen.');
        $guestId=(int)$offer['guest_id'];
        if(!$guestId)throw new ConflictException('Das Angebot ist keinem StayPilot-Gast zugeordnet.');

        $input=is_array($offer['calculation_input']??null)?$offer['calculation_input']:[];
        $snapshot=is_array($offer['price_snapshot']??null)?$offer['price_snapshot']:[];
        $reference=generate_reference();
        $pdo=db();
        $workflowContext=null;
        $pdo->beginTransaction();
        try{
            // Sperrt den Datensatz gegen Doppelklicks und parallele Übernahmen.
            $lock=$pdo->prepare('SELECT status,booking_id FROM offers WHERE id=? FOR UPDATE');
            $lock->execute([$id]);
            $locked=$lock->fetch();
            if(!$locked)throw new RuntimeException('Angebot nicht gefunden.');
            if((string)$locked['status']!=='accepted'||(int)$locked['booking_id']){
                throw new ConflictException('Das Angebot wurde inzwischen bereits verarbeitet.');
            }

            $stmt=$pdo->prepare('SELECT id,apartment_type_id,status,out_of_service,max_guests FROM apartments WHERE id=? FOR UPDATE');
            $stmt->execute([$apartmentId]);
            $apt=$stmt->fetch();
            if(!$apt||(string)$apt['status']!=='active'||(int)$apt['out_of_service'])throw new ValidationException('Die gewählte Wohnung ist nicht aktiv.');
            $bookingTypeId=(int)$offer['apartment_type_id'];
            $selectedTypeId=(int)$apt['apartment_type_id'];
            $allowAlternative=normalize_bool($workflowOptions['allow_alternative_apartment']??0);
            $alternativeNote=trim((string)($workflowOptions['alternative_assignment_note']??''));
            if($bookingTypeId&&$selectedTypeId!==$bookingTypeId){
                if(!$allowAlternative)throw new ValidationException('Die Wohnung gehört nicht zum angebotenen Wohnungstyp. Bitte wählen Sie eine passende Wohnung oder nutzen Sie den Alternativ-/Upgrade-Ablauf.');
                if($alternativeNote==='')throw new ValidationException('Für eine alternative Wohnung ist eine interne Begründung erforderlich.');
                $bookingTypeId=$selectedTypeId;
            }
            if(booking_conflict($apartmentId,(string)$offer['arrival'],(string)$offer['departure']))throw new ConflictException('Die gewählte Wohnung ist im Angebotszeitraum nicht verfügbar.');
            $people=(int)$offer['adults']+(int)$offer['children']+(int)$offer['babies'];
            $capacityOverride=normalize_bool($input['capacity_override']??0);
            $capacityReason=trim((string)($input['capacity_override_reason']??''));
            if((int)($apt['max_guests']??0)>0&&$people>(int)$apt['max_guests']&&!$capacityOverride){
                throw new ConflictException('Die gewählte Wohnung ist für die Personenzahl nicht ausreichend. Bitte prüfen Sie die Kapazität oder genehmigen Sie die Ausnahme im Angebot.');
            }
            if((int)($apt['max_guests']??0)>0&&$people>(int)$apt['max_guests']&&$capacityOverride&&$capacityReason===''){
                throw new ValidationException('Für die Kapazitätsüberschreitung fehlt eine Begründung.');
            }

            $breakfast=normalize_bool($input['breakfast']??0);
            $halfBoard=normalize_bool($input['half_board']??0);
            $breakfastStart=$breakfast?(new DateTimeImmutable((string)$offer['arrival']))->modify('+1 day')->format('Y-m-d'):null;
            $breakfastEnd=$breakfast?(string)$offer['departure']:null;
            $halfStart=$halfBoard?(string)$offer['arrival']:null;
            $halfEnd=$halfBoard?(new DateTimeImmutable((string)$offer['departure']))->modify('-1 day')->format('Y-m-d'):null;
            $discountCode=trim((string)($snapshot['discount_code']??$input['discount_code']??''));
            $discountCodeId=(int)($snapshot['discount_code_id']??0)?:null;

            $childAges=$input['child_ages']??[];if(!is_array($childAges))$childAges=[];
            $capacityReason=$capacityReason?:null;
            $cancellationSnapshot=json_encode(BookingPolicyService::cancellationSnapshot($bookingTypeId),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $columns=[
                'reference','guest_id','apartment_id','apartment_type_id','arrival','departure','adults','children','babies','pets',
                'capacity_override','capacity_override_reason','child_ages_json','public_language','cancellation_snapshot_json',
                'status','source','total_price','paid_amount','payment_status','deposit_amount','tourist_tax','discount_amount',
                'discount_code','discount_code_id','parking_spaces','extra_beds','baby_beds','late_checkout',
                'price_breakdown_json','price_locked','min_stay_override','breakfast','breakfast_start_date','breakfast_end_date',
                'half_board','half_board_start_date','half_board_end_date','is_upgrade','upgrade_note','internal_notes','notes',
            ];
            $isAlternativeAssignment = $allowAlternative && $alternativeNote !== '';
            $values=[
                $reference,$guestId,$apartmentId,$bookingTypeId,$offer['arrival'],$offer['departure'],$offer['adults'],$offer['children'],$offer['babies'],$offer['pets'],
                $capacityOverride,$capacityReason,json_encode($childAges,JSON_UNESCAPED_UNICODE),(string)$offer['language'],$cancellationSnapshot,
                'confirmed','Angebot',$offer['total_amount'],0,'open',$offer['deposit_amount'],$offer['tourist_tax'],$offer['discount_amount'],
                $discountCode?:null,$discountCodeId,max(0,(int)($input['parking_spaces']??0)),max(0,(int)($input['extra_beds']??0)),max(0,(int)($input['baby_beds']??0)),normalize_bool($input['late_checkout']??0),
                $offer['price_snapshot_json'],1,normalize_bool($input['min_stay_override']??0),$breakfast,$breakfastStart,$breakfastEnd,
                $halfBoard,$halfStart,$halfEnd,$isAlternativeAssignment?1:0,$isAlternativeAssignment?mb_substr($alternativeNote,0,255):null,self::appendAlternativeNote(trim((string)($offer['internal_notes']??''))?:null,$alternativeNote),'Übernommen aus Angebot '.$offer['offer_number'].($alternativeNote!==''?' · Alternative Wohnung: '.$alternativeNote:''),
            ];
            $quoted=implode(',',array_map(static fn(string $column):string=>'`'.$column.'`',$columns));
            $marks=implode(',',array_fill(0,count($values),'?'));
            $pdo->prepare("INSERT INTO bookings({$quoted}) VALUES({$marks})")->execute($values);
            $bookingId=(int)$pdo->lastInsertId();

            if(!empty($workflowOptions['workflow'])){
                $workflowContext=BookingWorkflowService::initializeInTransaction($bookingId,$offer,$workflowOptions,Auth::user());
            }

            if($discountCodeId){
                $pdo->prepare('UPDATE discount_codes SET used_count=used_count+1 WHERE id=?')->execute([$discountCodeId]);
            }
            $update=$pdo->prepare("UPDATE offers SET status='converted',booking_id=?,converted_at=NOW(),updated_by=?,updated_at=NOW() WHERE id=? AND status='accepted' AND booking_id IS NULL");
            $update->execute([$bookingId,Auth::user()['id']??null,$id]);
            if($update->rowCount()!==1)throw new ConflictException('Das Angebot wurde parallel bereits verarbeitet.');
            self::event($id,'converted',['status'=>'accepted'],['status'=>'converted','booking_id'=>$bookingId]);
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
        try{HousekeepingWorkflow::upsertDepartureTask($bookingId);}catch(Throwable $e){AppLogger::error($e,['booking_id'=>$bookingId],'offer-conversion-housekeeping');}
        $workflowResult=null;
        if($workflowContext){
            try{$workflowResult=BookingWorkflowService::finalizeAfterCommit($workflowContext);}
            catch(Throwable $e){AppLogger::error($e,['booking_id'=>$bookingId,'offer_id'=>$id],'booking-confirmation-finalize');$workflowResult=['email_sent'=>false,'email_error'=>$e->getMessage(),'customer_url'=>$workflowContext['customer_url']??''];}
        }
        AuditLogger::record('offer',$id,'convert',$offer,['booking_id'=>$bookingId],'Angebot in Buchung übernommen');
        return ['offer'=>self::get($id),'booking_id'=>$bookingId,'booking_reference'=>$reference,'workflow'=>$workflowResult];
    }

    private static function appendAlternativeNote(?string $notes,string $alternativeNote): ?string
    {
        $base=trim((string)$notes);
        if($alternativeNote==='')return $base!==''?$base:null;
        $line='Alternative Wohnungszuweisung: '.$alternativeNote;
        return $base!==''?$base."

".$line:$line;
    }

    public static function publicByToken(string $token, bool $markViewed=true): array
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new RuntimeException('Der Angebotslink ist ungültig.');
        $stmt=db()->prepare("SELECT o.*,at.name apartment_type_name,a.name apartment_name,a.code apartment_code FROM offers o LEFT JOIN apartment_types at ON at.id=o.apartment_type_id LEFT JOIN apartments a ON a.id=o.apartment_id WHERE o.public_token_hash=? LIMIT 1");
        $stmt->execute([hash('sha256',$token)]);
        $offer=$stmt->fetch();
        if(!$offer)throw new RuntimeException('Das Angebot wurde nicht gefunden.');
        self::expireIfNeeded($offer);
        if($offer['status']==='archived')throw new RuntimeException('Dieses Angebot ist nicht mehr verfügbar.');
        $items=db()->prepare('SELECT * FROM offer_items WHERE offer_id=? ORDER BY sort_order,id');
        $items->execute([$offer['id']]);
        $offer['items']=$items->fetchAll();
        $offer['public_token']=$token;
        if($markViewed&&$offer['status']==='sent'){
            $update=db()->prepare("UPDATE offers SET status='viewed',viewed_at=COALESCE(viewed_at,NOW()) WHERE id=? AND status='sent'");
            $update->execute([$offer['id']]);
            if($update->rowCount()===1){
                $offer['status']='viewed';
                try{self::event((int)$offer['id'],'viewed',null,null);}catch(Throwable $e){AppLogger::error($e,['offer_id'=>$offer['id']],'offer-public-view-event');}
            }
        }
        return $offer;
    }

    public static function publicDecision(string $token,string $decision): array
    {
        $offer=self::publicByToken($token,false);
        if(!in_array($decision,['accept','decline'],true))throw new ValidationException('Ungültige Entscheidung.');
        if(!in_array($offer['status'],['sent','viewed'],true))throw new ConflictException('Dieses Angebot wurde bereits beantwortet oder ist nicht mehr aktiv.');
        if(valid_date((string)$offer['valid_until'])&&$offer['valid_until']<date('Y-m-d')){
            db()->prepare("UPDATE offers SET status='expired' WHERE id=? AND status IN ('sent','viewed')")->execute([$offer['id']]);
            throw new ConflictException('Dieses Angebot ist abgelaufen.');
        }
        if($decision==='accept'&&(int)$offer['apartment_id']&&booking_conflict((int)$offer['apartment_id'],(string)$offer['arrival'],(string)$offer['departure'])){
            throw new ConflictException('Die angebotene Wohnung ist inzwischen nicht mehr verfügbar. Bitte kontaktieren Sie uns.');
        }
        $status=$decision==='accept'?'accepted':'declined';
        $column=$decision==='accept'?'accepted_at':'declined_at';
        $pdo=db();
        $pdo->beginTransaction();
        try{
            $stmt=$pdo->prepare("UPDATE offers SET status=?,{$column}=NOW(),updated_at=NOW() WHERE id=? AND status IN ('sent','viewed') AND valid_until>=CURDATE()");
            $stmt->execute([$status,$offer['id']]);
            if($stmt->rowCount()!==1)throw new ConflictException('Dieses Angebot wurde gerade bereits beantwortet oder ist abgelaufen.');
            self::event((int)$offer['id'],$status,['status'=>$offer['status']],['status'=>$status]);
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
        try{
            HousekeepingWorkflow::notifyRoles(['admin','manager','reception'],'offer_'.$status,$decision==='accept'?'Angebot angenommen':'Angebot abgelehnt',$offer['guest_name'].' · '.$offer['offer_number'],'offer',$offer['id'],'#dashboard');
        }catch(Throwable $e){
            AppLogger::error($e,['offer_id'=>$offer['id'],'status'=>$status],'offer-public-notification');
        }
        return self::publicByToken($token,false);
    }

    public static function render(array $offer,bool $includePublicLink=true,string $channel='email'): array
    {
        $channel=$channel==='public'?'public':'email';
        if(empty($offer['items'])){
            $stmt=db()->prepare('SELECT * FROM offer_items WHERE offer_id=? ORDER BY sort_order,id');
            $stmt->execute([(int)($offer['id']??0)]);
            $offer['items']=$stmt->fetchAll();
        }
        $lang=self::normalizeLanguage((string)($offer['language']??'de'));
        $t=self::dictionary($lang);
        $options=self::documentOptions(is_array($offer['document_options']??null)?$offer['document_options']:(json_decode((string)($offer['document_options_json']??''),true)?:[]));
        $snapshot=is_array($offer['document_snapshot']??null)?$offer['document_snapshot']:(json_decode((string)($offer['document_snapshot_json']??''),true)?:[]);
        if(!$snapshot)$snapshot=self::buildDocumentSnapshot($lang,(int)($offer['apartment_type_id']??0),(int)($offer['apartment_id']??0),$options,(array)($offer['items']??[]));
        $template=is_array($snapshot['template']??null)?$snapshot['template']:self::template($lang);
        $settings=is_array($snapshot['settings']??null)?$snapshot['settings']:self::documentSettingsSnapshot();
        $property=(string)($snapshot['property_name']??setting('property_name','StayPilot'));
        $url=(string)($offer['public_url']??self::publicUrlForRow($offer));
        $email=self::normalizeEmailContent(is_array($offer['email_content']??null)?$offer['email_content']:(json_decode((string)($offer['email_content_json']??''),true)?:[]),$offer,$snapshot);
        $public=self::normalizePublicPage(is_array($offer['public_page']??null)?$offer['public_page']:(json_decode((string)($offer['public_page_json']??''),true)?:[]),$offer,$snapshot,$email);
        $content=$channel==='public'?$public:$email;
        $accommodation=(string)($snapshot['accommodation']['label']??self::accommodationLabel($offer,$lang));
        $accommodationContent=is_array($snapshot['accommodation']??null)?[
            'description'=>(string)($snapshot['accommodation']['description']??''),
            'amenities_html'=>(string)($snapshot['accommodation']['amenities_html']??''),
        ]:self::accommodationContent($offer,$lang);

        $logoHtml='';
        $logoUrl=trim((string)($settings['offer_logo_url']??''));
        if($logoUrl!==''&&self::safeAssetUrl($logoUrl)){
            if(!preg_match('~^https?://~i',$logoUrl))$logoUrl=HousekeepingWorkflow::applicationUrl(ltrim($logoUrl,'/'));
            $logoHtml='<img class="sp-offer-logo" src="'.e($logoUrl).'" alt="'.e($property).'">';
        }

        $rows='';$itemSnapshot=is_array($snapshot['items']??null)?$snapshot['items']:[];
        foreach((array)$offer['items'] as $index=>$item){
            $description=(string)($itemSnapshot[$index]['description']??self::translatedItemDescription($item,$lang));
            $unit=(string)($itemSnapshot[$index]['unit']??self::translatedUnit((string)$item['unit'],$lang));
            $rows.='<tr><td>'.e($description).'</td><td>'.e(self::formatQuantity((float)$item['quantity'])).' '.e($unit).'</td><td>'.self::money((float)$item['unit_price'],(string)$offer['currency'],$lang).'</td><td>'.self::money((float)$item['line_total'],(string)$offer['currency'],$lang).'</td></tr>';
        }

        $titleDefaults=[
            'hero'=>$t['offer']??'Angebot','greeting'=>'','intro'=>'','personal'=>'','facts'=>$t['offer']??'Angebot',
            'gallery'=>$t['accommodation_details']??'Unterkunft','accommodation'=>$t['accommodation_details']??'Unterkunft',
            'prices'=>$t['item']??'Leistungen','totals'=>$t['total']??'Gesamtpreis','validity'=>'',
            'payment'=>$t['payment_information']??'Zahlungsinformationen','bank'=>$t['bank_details']??'Bankverbindung',
            'checkin'=>$t['arrival_information']??'Anreiseinformationen','additional'=>trim((string)($template['additional_label']??''))?:($t['additional_information']??'Zusätzliche Informationen'),
            'content_blocks'=>'','custom_blocks'=>'','closing'=>'','company'=>$t['company_information']??'Anbieter & Kontakt',
            'terms'=>$t['terms']??'Bedingungen','signature'=>'','public_link'=>'','decision'=>'','footer'=>'',
        ];
        $sectionMeta=[];foreach((array)($content['sections']??[]) as $row){if(is_array($row)&&isset($row['key']))$sectionMeta[(string)$row['key']]=$row;}
        $heading=static function(string $key)use($sectionMeta,$titleDefaults):string{
            $custom=trim((string)($sectionMeta[$key]['title']??''));$value=$custom!==''?$custom:(string)($titleDefaults[$key]??'');
            return $value!==''?'<h2>'.e($value).'</h2>':'';
        };
        $section=static function(string $key,string $body,string $class='sp-offer-section')use($heading):string{
            if(trim($body)==='')return '';
            return '<section class="'.$class.'" data-sp-section="'.e($key).'">'.$heading($key).$body.'</section>';
        };

        $facts='<div class="facts">'
            .'<div><small>'.e($t['guest']).'</small><b>'.e((string)$offer['guest_name']).'</b></div>'
            .'<div><small>'.e($t['accommodation']).'</small><b>'.e($accommodation).'</b></div>'
            .'<div><small>'.e($t['stay']).'</small><b>'.e(self::date((string)$offer['arrival'],$lang)).' – '.e(self::date((string)$offer['departure'],$lang)).'</b></div>'
            .'<div><small>'.e($t['persons']).'</small><b>'.(int)$offer['adults'].' '.e($t['adults']).((int)$offer['children']?', '.(int)$offer['children'].' '.e($t['children']):'').((int)($offer['babies']??0)?', '.(int)$offer['babies'].' '.e($t['babies']):'').((int)($offer['pets']??0)?', '.(int)$offer['pets'].' '.e($t['pets']):'').'</b></div>'
            .'</div>';
        $prices='<div class="sp-table-wrap"><table><thead><tr><th>'.e($t['item']).'</th><th>'.e($t['quantity']).'</th><th>'.e($t['unit_price']).'</th><th>'.e($t['total']).'</th></tr></thead><tbody>'.$rows.'</tbody></table></div>';
        $totals='<div class="totals"><div><span>'.e($t['net']).'</span><b>'.self::money((float)$offer['subtotal_net'],(string)$offer['currency'],$lang).'</b></div><div><span>'.e($t['vat']).' '.e(self::formatQuantity((float)$offer['vat_rate'])).'%</span><b>'.self::money((float)$offer['vat_amount'],(string)$offer['currency'],$lang).'</b></div><div class="grand"><span>'.e($t['total']).'</span><b>'.self::money((float)$offer['total_amount'],(string)$offer['currency'],$lang).'</b></div><div><span>'.e($t['deposit']).' '.e(self::formatQuantity((float)$offer['deposit_percent'])).'%</span><b>'.self::money((float)$offer['deposit_amount'],(string)$offer['currency'],$lang).'</b></div><div><span>'.e($t['remaining']).'</span><b>'.self::money((float)$offer['remaining_amount'],(string)$offer['currency'],$lang).'</b></div></div>';
        $validityText=self::replacePlaceholders((string)($template['validity']??''),$offer,false);
        $validityText=str_replace('{date}',self::date((string)$offer['valid_until'],$lang),$validityText);

        $payment='';
        $paymentInfo=self::sanitizeRich((string)($template['payment_info']??''));
        $remainingText=trim((string)($template['remaining_payment']??''));$depositDue=trim((string)($offer['deposit_due_date']??''));
        if($paymentInfo!==''||$remainingText!==''||$depositDue!==''){
            $payment=$paymentInfo;
            if($depositDue!=='')$payment.='<p><b>'.e($t['deposit_due']).':</b> '.e(self::date($depositDue,$lang)).'</p>';
            if($remainingText!==''){
                $remainingText=str_replace(['{amount}','{date}'],[strip_tags(self::money((float)$offer['remaining_amount'],(string)$offer['currency'],$lang)),$depositDue!==''?self::date($depositDue,$lang):''],$remainingText);
                $payment.='<p>'.nl2br(e($remainingText)).'</p>';
            }
        }
        $bankRows=[];foreach([
            $t['account_holder']=>$settings['offer_bank_account_holder']??'',
            $t['iban']=>$settings['offer_bank_iban']??'',
            $t['bic']=>$settings['offer_bank_bic']??'',
            $t['bank']=>$settings['offer_bank_name']??'',
        ] as $label=>$value){if(trim((string)$value)!=='')$bankRows[]='<div><small>'.e($label).'</small><b>'.e((string)$value).'</b></div>';}
        $bank=$bankRows?'<div class="sp-offer-info-grid">'.implode('',$bankRows).'</div>':'';
        $checkin='';$checkinTime=trim((string)($settings['checkin_time']??''));$checkoutTime=trim((string)($settings['checkout_time']??''));
        if($checkinTime!==''||$checkoutTime!==''){
            $checkin='<div class="sp-offer-info-grid">';
            if($checkinTime!=='')$checkin.='<div><small>'.e($t['checkin']).'</small><b>'.e(substr($checkinTime,0,5)).'</b></div>';
            if($checkoutTime!=='')$checkin.='<div><small>'.e($t['checkout']).'</small><b>'.e(substr($checkoutTime,0,5)).'</b></div>';
            $checkin.='</div>';
        }
        $additional=self::sanitizeRich((string)($template['additional_info']??''));
        $companyLines=array_filter([
            (string)($settings['legal_name']??''),(string)($settings['full_address']??''),
            trim((string)($settings['postal_code']??'').' '.(string)($settings['city']??'')),(string)($settings['province']??''),(string)($settings['country']??''),
            trim((string)($settings['contact_email']??'')),(string)($settings['contact_phone']??''),
            trim((string)($settings['tax_id']??''))!==''?$t['tax_id'].': '.($settings['tax_id']??''):'',
            trim((string)($settings['offer_company_website']??''))!==''?$t['website'].': '.($settings['offer_company_website']??''):'',
            (string)($settings['offer_company_extra']??''),
        ],static fn($v)=>trim((string)$v)!=='');
        $company=$companyLines?'<p>'.nl2br(e(implode("\n",$companyLines))).'</p>':'';
        $terms=self::sanitizeRich((string)($template['terms']??''));

        $blockIds=array_flip(array_map('intval',(array)($content['content_block_ids']??[])));$contentBlocks='';
        $availableBlocks=is_array($snapshot['content_blocks']??null)?$snapshot['content_blocks']:self::contentBlocksForDocument(array_keys($blockIds),$lang);
        foreach($availableBlocks as $block){if(!isset($blockIds[(int)($block['id']??0)]))continue;$html=self::sanitizeRich((string)($block['content_html']??''));if($html==='')continue;$contentBlocks.='<article class="sp-content-card">'.(trim((string)($block['title']??''))!==''?'<h3>'.e((string)$block['title']).'</h3>':'').$html.'</article>';}
        $customBlocks='';foreach((array)($public['custom_blocks']??[]) as $block){if(!normalize_bool($block['visible']??1))continue;$customBlocks.='<article class="sp-content-card">'.(trim((string)($block['title']??''))!==''?'<h3>'.e((string)$block['title']).'</h3>':'').self::sanitizeRich((string)($block['content_html']??'')).'</article>';}

        $images=is_array($snapshot['images']??null)?$snapshot['images']:[];$gallery='';$cover='';
        foreach($images as $image){$src=trim((string)($image['file_path']??''));if($src===''||!self::safeAssetUrl($src))continue;if(!preg_match('~^https?://~i',$src))$src=HousekeepingWorkflow::applicationUrl(ltrim($src,'/'));$alt=(string)($image['alt']??$accommodation);$img='<img src="'.e($src).'" alt="'.e($alt).'" loading="lazy">';if(!$cover||normalize_bool($image['is_cover']??0))$cover=$img;$gallery.='<figure>'.$img.'</figure>';}
        if($gallery!=='')$gallery='<div class="sp-gallery">'.$gallery.'</div>';

        $greeting=self::replacePlaceholders((string)($content['greeting_html']??''),$offer,true);
        $intro=self::replacePlaceholders((string)($content['intro_html']??''),$offer,true);
        $personal=self::replacePlaceholders((string)($content['personal_html']??''),$offer,true);
        $closing=self::replacePlaceholders((string)($content['closing_html']??''),$offer,true);
        $signature=self::replacePlaceholders((string)(($channel==='public'?$content['footer_html']??'':$content['signature_html']??'')),$offer,true);
        $description='';if($accommodationContent['description']!==''||$accommodationContent['amenities_html']!==''){
            if($accommodationContent['description']!=='')$description.='<p>'.nl2br(e($accommodationContent['description'])).'</p>';
            if($accommodationContent['amenities_html']!=='')$description.='<div class="sp-offer-rich">'.$accommodationContent['amenities_html'].'</div>';
        }
        $publicLink=$includePublicLink&&$url!==''?'<div class="cta"><a href="'.e($url).'">'.e($t['open_offer']).'</a></div>':'';
        $decision='<div data-sp-offer-controls><div class="sp-preview-note">'.e($t['preview_actions']).'</div></div>';
        $heroTitle=self::replacePlaceholders((string)($public['hero_title']??''),$offer,false);if(trim($heroTitle)==='')$heroTitle=$t['offer'].' '.(string)$offer['offer_number'];
        $heroSubtitle=self::replacePlaceholders((string)($public['hero_subtitle']??''),$offer,false);if(trim($heroSubtitle)==='')$heroSubtitle=$accommodation;
        $hero='<div class="sp-hero '.e((string)($public['style']['hero']??'image')).'">'.($cover?'<div class="sp-hero-image">'.$cover.'</div>':'').'<div class="sp-hero-copy">'.$logoHtml.'<span class="sp-eyebrow">'.e($property).'</span><h1>'.e($heroTitle).'</h1><p>'.e($heroSubtitle).'</p><span class="sp-status">'.e(self::statusLabel((string)$offer['status'],$lang)).'</span></div></div>';
        $emailHeader='<div class="header"><div class="sp-offer-brand">'.$logoHtml.'<div><b>'.e($property).'</b><h1>'.e($t['offer']).' '.e((string)$offer['offer_number']).'</h1></div></div><span>'.e(self::statusLabel((string)$offer['status'],$lang)).'</span></div>';

        $sections=[
            'hero'=>$hero,'greeting'=>$section('greeting',$greeting),'intro'=>$section('intro',$intro),'personal'=>$personal!==''?$section('personal','<div class="sp-offer-note">'.$personal.'</div>'):'',
            'facts'=>$section('facts',$facts),'gallery'=>$gallery!==''?$section('gallery',$gallery):'','accommodation'=>$section('accommodation',$description),
            'prices'=>$section('prices',$prices),'totals'=>$section('totals',$totals),'validity'=>$validityText!==''?'<div class="valid" data-sp-section="validity">'.e($validityText).'</div>':'',
            'payment'=>$section('payment',$payment),'bank'=>$section('bank',$bank),'checkin'=>$section('checkin',$checkin),'additional'=>$section('additional',$additional),
            'content_blocks'=>$section('content_blocks',$contentBlocks),'custom_blocks'=>$section('custom_blocks',$customBlocks),'closing'=>$section('closing',$closing),
            'company'=>$section('company',$company),'terms'=>$section('terms',$terms,'sp-offer-terms'),'signature'=>$section('signature',$signature),
            'public_link'=>$publicLink,'decision'=>$decision,'footer'=>$signature!==''?'<footer data-sp-section="footer">'.$signature.'</footer>':'',
        ];
        $body='';foreach((array)($content['sections']??[]) as $row){if(!is_array($row)||!normalize_bool($row['visible']??1))continue;$key=(string)($row['key']??'');$body.=$sections[$key]??'';}
        if($channel==='email')$body=$emailHeader.$body;
        $style=$channel==='public'?self::publicCss((array)($public['style']??[])):self::emailCss();
        $bodyClass=$channel==='public'?'sp-public-body':'sp-email-body';
        $pageTitle=$channel==='public'?self::replacePlaceholders((string)($public['page_title']??''),$offer,false):self::replacePlaceholders((string)($email['subject']??''),$offer,false);
        if(trim($pageTitle)==='')$pageTitle=$property.' · '.$t['offer'].' '.(string)$offer['offer_number'];
        $meta=$channel==='public'?self::replacePlaceholders((string)($public['meta_description']??''),$offer,false):'';
        $metaTag=$meta!==''?'<meta name="description" content="'.e($meta).'">':'';
        $html='<!doctype html><html lang="'.e($lang).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($pageTitle).'</title>'.$metaTag.'<style>'.$style.'</style></head><body class="'.$bodyClass.'"><main class="wrap">'.$body.'</main></body></html>';
        $text=self::richToPlain($body);
        if($includePublicLink&&$url!=='')$text=trim($text)."\n\n".$url;
        return ['html'=>$html,'text'=>trim($text)];
    }


    public static function services(): array
    {
        $rows=db()->query('SELECT * FROM offer_services ORDER BY active DESC,sort_order,name')->fetchAll();$translations=db()->query('SELECT * FROM offer_service_translations')->fetchAll();$by=[];foreach($translations as $tr)$by[(int)$tr['service_id']][(string)$tr['language']]=$tr;foreach($rows as &$row)$row['translations']=$by[(int)$row['id']]??[];unset($row);return $rows;
    }

    public static function saveService(array $data): array
    {
        $id=(int)($data['id']??0);$old=null;if($id){$stmt=db()->prepare('SELECT * FROM offer_services WHERE id=?');$stmt->execute([$id]);$old=$stmt->fetch()?:null;}
        $name=trim((string)($data['name']??''));if($name==='')throw new ValidationException('Die Bezeichnung der Zusatzleistung fehlt.');$code=strtoupper(trim((string)($data['code']??'')));if($code==='')$code='SVC-'.strtoupper(substr(bin2hex(random_bytes(4)),0,6));if(!preg_match('/^[A-Z0-9_-]{2,40}$/',$code))throw new ValidationException('Der Leistungscode enthält ungültige Zeichen.');$unit=in_array($data['unit_mode']??'', ['once','per_night','per_person','per_person_night','quantity'],true)?$data['unit_mode']:'once';
        if($id){$stmt=db()->prepare('UPDATE offer_services SET code=?,name=?,description=?,unit_mode=?,default_price=?,vat_rate=?,active=?,sort_order=?,updated_at=NOW() WHERE id=?');$stmt->execute([$code,$name,trim((string)($data['description']??'')),$unit,max(0,(float)($data['default_price']??0)),max(0,min(100,(float)($data['vat_rate']??setting('offer_vat_rate',10)))),normalize_bool($data['active']??0),(int)($data['sort_order']??0),$id]);}
        else{$stmt=db()->prepare('INSERT INTO offer_services(code,name,description,unit_mode,default_price,vat_rate,active,sort_order) VALUES(?,?,?,?,?,?,?,?)');$stmt->execute([$code,$name,trim((string)($data['description']??'')),$unit,max(0,(float)($data['default_price']??0)),max(0,min(100,(float)($data['vat_rate']??setting('offer_vat_rate',10)))),normalize_bool($data['active']??1),(int)($data['sort_order']??0)]);$id=(int)db()->lastInsertId();}
        $translations=is_array($data['translations']??null)?$data['translations']:[];$up=db()->prepare('INSERT INTO offer_service_translations(service_id,language,name,description) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description)');foreach(self::LANGUAGES as $lang=>$label){$tr=$translations[$lang]??[];$up->execute([$id,$lang,trim((string)($tr['name']??'')),trim((string)($tr['description']??''))]);}
        AuditLogger::record('offer_service',$id,$old?'update':'create',$old,$data,$old?'Zusatzleistung bearbeitet':'Zusatzleistung angelegt');return ['id'=>$id,'services'=>self::services()];
    }

    public static function deleteService(int $id): void
    {
        $stmt=db()->prepare('SELECT COUNT(*) FROM offer_items WHERE source_type=\'offer_service\' AND source_id=?');$stmt->execute([$id]);if((int)$stmt->fetchColumn()>0){db()->prepare('UPDATE offer_services SET active=0 WHERE id=?')->execute([$id]);AuditLogger::record('offer_service',$id,'deactivate',null,['active'=>0],'Verwendete Zusatzleistung deaktiviert');return;}db()->prepare('DELETE FROM offer_services WHERE id=?')->execute([$id]);AuditLogger::record('offer_service',$id,'delete',null,null,'Unbenutzte Zusatzleistung gelöscht');
    }

    public static function contentBlocks(): array
    {
        $rows=db()->query('SELECT * FROM offer_content_blocks ORDER BY active DESC,sort_order,internal_name')->fetchAll();
        $translations=db()->query('SELECT * FROM offer_content_block_translations ORDER BY block_id,language')->fetchAll();
        $by=[];foreach($translations as $translation)$by[(int)$translation['block_id']][(string)$translation['language']]=$translation;
        foreach($rows as &$row)$row['translations']=$by[(int)$row['id']]??[];unset($row);
        return $rows;
    }

    public static function saveContentBlock(array $data): array
    {
        $id=(int)($data['id']??0);$old=null;
        if($id){$stmt=db()->prepare('SELECT * FROM offer_content_blocks WHERE id=?');$stmt->execute([$id]);$old=$stmt->fetch()?:null;}
        $name=mb_substr(trim((string)($data['internal_name']??'')),0,190);if($name==='')throw new ValidationException('Der interne Name des Textbausteins fehlt.');
        $code=strtoupper(trim((string)($data['code']??'')));if($code==='')$code='BLOCK-'.strtoupper(substr(bin2hex(random_bytes(4)),0,6));
        if(!preg_match('/^[A-Z0-9_-]{2,60}$/',$code))throw new ValidationException('Der Bausteincode enthält ungültige Zeichen.');
        if($id){$stmt=db()->prepare('UPDATE offer_content_blocks SET code=?,internal_name=?,active=?,show_by_default=?,sort_order=?,updated_at=NOW() WHERE id=?');$stmt->execute([$code,$name,normalize_bool($data['active']??0),normalize_bool($data['show_by_default']??0),(int)($data['sort_order']??0),$id]);}
        else{$stmt=db()->prepare('INSERT INTO offer_content_blocks(code,internal_name,active,show_by_default,sort_order) VALUES(?,?,?,?,?)');$stmt->execute([$code,$name,normalize_bool($data['active']??1),normalize_bool($data['show_by_default']??0),(int)($data['sort_order']??0)]);$id=(int)db()->lastInsertId();}
        $translations=is_array($data['translations']??null)?$data['translations']:[];
        $up=db()->prepare('INSERT INTO offer_content_block_translations(block_id,language,title,content_html) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),content_html=VALUES(content_html)');
        foreach(self::LANGUAGES as $lang=>$label){$tr=$translations[$lang]??[];$up->execute([$id,$lang,mb_substr(trim((string)($tr['title']??'')),0,190),self::sanitizeRich((string)($tr['content_html']??''))]);}
        AuditLogger::record('offer_content_block',$id,$old?'update':'create',$old,$data,$old?'Angebotsbaustein bearbeitet':'Angebotsbaustein angelegt');
        return ['id'=>$id,'content_blocks'=>self::contentBlocks()];
    }

    public static function deleteContentBlock(int $id): void
    {
        $used=false;$stmt=db()->query("SELECT document_options_json FROM offers WHERE document_options_json IS NOT NULL AND document_options_json<>''");
        foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $json){$options=json_decode((string)$json,true);if(is_array($options)&&in_array($id,array_map('intval',(array)($options['content_block_ids']??[])),true)){$used=true;break;}}
        if($used){db()->prepare('UPDATE offer_content_blocks SET active=0 WHERE id=?')->execute([$id]);AuditLogger::record('offer_content_block',$id,'deactivate',null,['active'=>0],'Verwendeter Angebotsbaustein deaktiviert');return;}
        db()->prepare('DELETE FROM offer_content_blocks WHERE id=?')->execute([$id]);AuditLogger::record('offer_content_block',$id,'delete',null,null,'Unbenutzter Angebotsbaustein gelöscht');
    }

    private static function contentBlocksForDocument(array $ids,string $lang): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));if(!$ids)return [];
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $sql="SELECT b.id,b.sort_order,COALESCE(NULLIF(tr.title,''),NULLIF(de.title,''),b.internal_name) title,COALESCE(NULLIF(tr.content_html,''),de.content_html,'') content_html FROM offer_content_blocks b LEFT JOIN offer_content_block_translations tr ON tr.block_id=b.id AND tr.language=? LEFT JOIN offer_content_block_translations de ON de.block_id=b.id AND de.language='de' WHERE b.active=1 AND b.id IN ($marks) ORDER BY b.sort_order,b.internal_name";
        $stmt=db()->prepare($sql);$stmt->execute(array_merge([$lang],$ids));return $stmt->fetchAll();
    }

    public static function typeTranslations(): array
    {
        $rows=db()->query('SELECT * FROM offer_apartment_type_translations ORDER BY apartment_type_id,language')->fetchAll();$out=[];foreach($rows as $row)$out[(int)$row['apartment_type_id']][(string)$row['language']]=$row;return $out;
    }

    public static function saveTranslations(array $data): array
    {
        $typeId=(int)($data['apartment_type_id']??0);if(!$typeId)throw new ValidationException('Wohnungstyp fehlt.');$stmt=db()->prepare('SELECT COUNT(*) FROM apartment_types WHERE id=?');$stmt->execute([$typeId]);if(!(int)$stmt->fetchColumn())throw new ValidationException('Wohnungstyp wurde nicht gefunden.');$translations=is_array($data['translations']??null)?$data['translations']:[];$up=db()->prepare('INSERT INTO offer_apartment_type_translations(apartment_type_id,language,name,description,amenities_html) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),amenities_html=VALUES(amenities_html)');foreach(self::LANGUAGES as $lang=>$label){$tr=$translations[$lang]??[];$up->execute([$typeId,$lang,trim((string)($tr['name']??'')),trim((string)($tr['description']??'')),self::sanitizeRich((string)($tr['amenities_html']??''))]);}AuditLogger::record('offer_type_translation',$typeId,'update',null,$translations,'Mehrsprachige Angebotstexte gespeichert');return self::typeTranslations();
    }

    public static function templates(): array
    {
        $rows=db()->query('SELECT * FROM offer_text_templates ORDER BY language')->fetchAll();$out=[];foreach($rows as $row)$out[$row['language']]=$row;return $out;
    }

    public static function saveTemplates(array $data): array
    {
        $translations=is_array($data['templates']??null)?$data['templates']:[];
        $stmt=db()->prepare('INSERT INTO offer_text_templates(language,email_subject,greeting,intro,validity,closing,signature,footer,terms,payment_info,additional_label,additional_info,remaining_payment) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE email_subject=VALUES(email_subject),greeting=VALUES(greeting),intro=VALUES(intro),validity=VALUES(validity),closing=VALUES(closing),signature=VALUES(signature),footer=VALUES(footer),terms=VALUES(terms),payment_info=VALUES(payment_info),additional_label=VALUES(additional_label),additional_info=VALUES(additional_info),remaining_payment=VALUES(remaining_payment)');
        foreach(self::LANGUAGES as $lang=>$label){
            $tr=$translations[$lang]??[];
            $stmt->execute([
                $lang,mb_substr(trim((string)($tr['email_subject']??'')),0,255),
                self::sanitizeRich((string)($tr['greeting']??'')),
                self::sanitizeRich((string)($tr['intro']??'')),trim((string)($tr['validity']??'')),
                self::sanitizeRich((string)($tr['closing']??'')),self::sanitizeRich((string)($tr['signature']??'')),trim((string)($tr['footer']??'')),
                self::sanitizeRich((string)($tr['terms']??'')),
                self::sanitizeRich((string)($tr['payment_info']??'')),
                mb_substr(trim((string)($tr['additional_label']??'')),0,160),
                self::sanitizeRich((string)($tr['additional_info']??'')),
                trim((string)($tr['remaining_payment']??'')),
            ]);
        }
        AuditLogger::record('offer_templates',1,'update',null,$translations,'Angebotstexte und Sprachen gespeichert');
        return self::templates();
    }

    private static function emailContent(array $offer): array
    {
        $stored=is_array($offer['email_content']??null)?$offer['email_content']:(json_decode((string)($offer['email_content_json']??''),true)?:[]);
        $snapshot=is_array($offer['document_snapshot']??null)?$offer['document_snapshot']:(json_decode((string)($offer['document_snapshot_json']??''),true)?:[]);
        return self::normalizeEmailContent($stored,$offer,$snapshot);
    }

    private static function publicPage(array $offer,array $email=[]): array
    {
        $stored=is_array($offer['public_page']??null)?$offer['public_page']:(json_decode((string)($offer['public_page_json']??''),true)?:[]);
        $snapshot=is_array($offer['document_snapshot']??null)?$offer['document_snapshot']:(json_decode((string)($offer['document_snapshot_json']??''),true)?:[]);
        if(!$email)$email=self::emailContent($offer);
        return self::normalizePublicPage($stored,$offer,$snapshot,$email);
    }

    private static function defaultGreeting(string $lang): string
    {
        return [
            'de'=>'Guten Tag {guest},','en'=>'Dear {guest},','es'=>'Estimado/a {guest},','pt'=>'Caro/a {guest},',
            'fr'=>'Bonjour {guest},','it'=>'Gentile {guest},','ca'=>'Benvolgut/da {guest},',
        ][$lang]??'Guten Tag {guest},';
    }

    private static function defaultEmailSections(array $options): array
    {
        return [
            ['key'=>'greeting','visible'=>1],['key'=>'intro','visible'=>1],['key'=>'personal','visible'=>1],
            ['key'=>'facts','visible'=>1],['key'=>'accommodation','visible'=>normalize_bool($options['include_equipment']??1)],
            ['key'=>'prices','visible'=>1],['key'=>'totals','visible'=>1],['key'=>'validity','visible'=>1],
            ['key'=>'payment','visible'=>normalize_bool($options['include_payment_info']??1)],['key'=>'bank','visible'=>normalize_bool($options['include_bank_info']??1)],
            ['key'=>'checkin','visible'=>normalize_bool($options['include_checkin_info']??1)],['key'=>'additional','visible'=>normalize_bool($options['include_additional_info']??1)],
            ['key'=>'content_blocks','visible'=>1],['key'=>'closing','visible'=>1],['key'=>'company','visible'=>normalize_bool($options['include_company_info']??1)],
            ['key'=>'terms','visible'=>normalize_bool($options['include_terms']??1)],['key'=>'signature','visible'=>1],['key'=>'public_link','visible'=>1],
        ];
    }

    private static function defaultPublicSections(array $options): array
    {
        return [
            ['key'=>'hero','visible'=>1],['key'=>'greeting','visible'=>1],['key'=>'intro','visible'=>1],['key'=>'personal','visible'=>1],
            ['key'=>'facts','visible'=>1],['key'=>'gallery','visible'=>1],['key'=>'accommodation','visible'=>normalize_bool($options['include_equipment']??1)],
            ['key'=>'prices','visible'=>1],['key'=>'totals','visible'=>1],['key'=>'validity','visible'=>1],
            ['key'=>'payment','visible'=>normalize_bool($options['include_payment_info']??1)],['key'=>'bank','visible'=>normalize_bool($options['include_bank_info']??1)],
            ['key'=>'checkin','visible'=>normalize_bool($options['include_checkin_info']??1)],['key'=>'additional','visible'=>normalize_bool($options['include_additional_info']??1)],
            ['key'=>'content_blocks','visible'=>1],['key'=>'custom_blocks','visible'=>1],['key'=>'closing','visible'=>1],['key'=>'company','visible'=>normalize_bool($options['include_company_info']??1)],
            ['key'=>'terms','visible'=>normalize_bool($options['include_terms']??1)],['key'=>'decision','visible'=>1],['key'=>'footer','visible'=>1],
        ];
    }

    private static function defaultEmailContent(array $offer,array $snapshot): array
    {
        $lang=self::normalizeLanguage((string)($offer['language']??'de'));
        $template=is_array($snapshot['template']??null)?$snapshot['template']:self::template($lang);
        $options=self::documentOptions(is_array($offer['document_options']??null)?$offer['document_options']:(json_decode((string)($offer['document_options_json']??''),true)?:[]));
        $subject=trim((string)($offer['email_subject']??$template['email_subject']??''));
        if($subject==='')$subject=self::tr($lang,'email_subject',['number'=>(string)($offer['offer_number']??'')]);
        $greeting=trim((string)($template['greeting']??''))?:self::defaultGreeting($lang);
        $signature=trim((string)($template['signature']??''))?:trim((string)($template['footer']??''));
        return [
            'version'=>1,'subject'=>$subject,
            'greeting_html'=>self::richFromValue($greeting),
            'intro_html'=>self::richFromValue((string)($template['intro']??'')),
            'personal_html'=>self::richFromValue((string)($offer['personal_message']??'')),
            'closing_html'=>self::richFromValue((string)($template['closing']??'')),
            'signature_html'=>self::richFromValue($signature),
            'sections'=>self::defaultEmailSections($options),
            'content_block_ids'=>(array)($options['content_block_ids']??[]),
        ];
    }

    private static function defaultPublicPage(array $offer,array $snapshot,array $email): array
    {
        $options=self::documentOptions(is_array($offer['document_options']??null)?$offer['document_options']:(json_decode((string)($offer['document_options_json']??''),true)?:[]));
        return [
            'version'=>1,
            'page_title'=>'{property} · '.self::tr(self::normalizeLanguage((string)($offer['language']??'de')),'offer').' {number}',
            'meta_description'=>'{property} · {arrival} – {departure} · {total}',
            'hero_title'=>self::tr(self::normalizeLanguage((string)($offer['language']??'de')),'offer').' {number}',
            'hero_subtitle'=>(string)($snapshot['accommodation']['label']??''),
            'greeting_html'=>$email['greeting_html']??'',
            'intro_html'=>$email['intro_html']??'',
            'personal_html'=>$email['personal_html']??'',
            'closing_html'=>$email['closing_html']??'',
            'footer_html'=>$email['signature_html']??'',
            'sections'=>self::defaultPublicSections($options),
            'content_block_ids'=>(array)($options['content_block_ids']??[]),
            'custom_blocks'=>[],
            'style'=>[
                'accent'=>self::safeColor((string)setting('accent_color','#2563eb'),'#2563eb'),
                'background'=>'#eef3f9','surface'=>'#ffffff','text'=>'#172033','muted'=>'#64748b',
                'font'=>'system','width'=>980,'radius'=>20,'hero'=>'image',
            ],
        ];
    }

    private static function normalizeEmailContent(array $content,array $offer,array $snapshot): array
    {
        $defaults=self::defaultEmailContent($offer,$snapshot);
        $out=$defaults;
        $out['subject']=mb_substr(trim(strip_tags((string)($content['subject']??$defaults['subject']))),0,255);
        if($out['subject']==='')$out['subject']=$defaults['subject'];
        foreach(['greeting_html','intro_html','personal_html','closing_html','signature_html'] as $field){
            $out[$field]=self::sanitizeEditorRich((string)($content[$field]??$defaults[$field]??''));
        }
        $out['sections']=self::normalizeSections($content['sections']??$defaults['sections'],$defaults['sections']);
        $out['content_block_ids']=self::normalizeIds($content['content_block_ids']??$defaults['content_block_ids']);
        return $out;
    }

    private static function normalizePublicPage(array $page,array $offer,array $snapshot,array $email): array
    {
        $defaults=self::defaultPublicPage($offer,$snapshot,$email);$out=$defaults;
        foreach(['page_title','meta_description','hero_title','hero_subtitle'] as $field){
            $limit=$field==='meta_description'?300:190;
            $out[$field]=mb_substr(trim(strip_tags((string)($page[$field]??$defaults[$field]??''))),0,$limit);
        }
        foreach(['greeting_html','intro_html','personal_html','closing_html','footer_html'] as $field){
            $out[$field]=self::sanitizeEditorRich((string)($page[$field]??$defaults[$field]??''));
        }
        $out['sections']=self::normalizeSections($page['sections']??$defaults['sections'],$defaults['sections']);
        // Die Gastentscheidung ist ein sicherheits- und prozessrelevanter Pflichtbereich.
        foreach($out['sections'] as &$section){if(($section['key']??'')==='decision')$section['visible']=1;}unset($section);
        $out['content_block_ids']=self::normalizeIds($page['content_block_ids']??$defaults['content_block_ids']);
        $out['style']=self::normalizePublicStyle(is_array($page['style']??null)?$page['style']:[],$defaults['style']);
        $out['custom_blocks']=self::normalizeCustomBlocks($page['custom_blocks']??[]);
        return $out;
    }

    private static function normalizeSections(mixed $input,array $defaults): array
    {
        $defaultMap=[];foreach($defaults as $row)$defaultMap[(string)$row['key']]=normalize_bool($row['visible']??1);
        $result=[];$seen=[];
        if(is_array($input))foreach($input as $row){
            if(is_string($row))$row=['key'=>$row,'visible'=>1];
            if(!is_array($row))continue;$key=(string)($row['key']??'');
            if(!array_key_exists($key,$defaultMap)||isset($seen[$key]))continue;
            $seen[$key]=true;$result[]=['key'=>$key,'visible'=>normalize_bool($row['visible']??$defaultMap[$key]),'title'=>mb_substr(trim((string)($row['title']??'')),0,160)];
        }
        foreach($defaults as $row){$key=(string)$row['key'];if(isset($seen[$key]))continue;$result[]=['key'=>$key,'visible'=>normalize_bool($row['visible']??1),'title'=>''];}
        return $result;
    }

    private static function normalizeIds(mixed $ids): array
    {
        if(!is_array($ids))$ids=preg_split('/[,;\s]+/',trim((string)$ids),-1,PREG_SPLIT_NO_EMPTY)?:[];
        return array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));
    }

    private static function normalizePublicStyle(array $style,array $defaults): array
    {
        $font=in_array((string)($style['font']??''),['system','modern','serif'],true)?(string)$style['font']:(string)$defaults['font'];
        $hero=in_array((string)($style['hero']??''),['image','compact','split'],true)?(string)$style['hero']:(string)$defaults['hero'];
        return [
            'accent'=>self::safeColor((string)($style['accent']??$defaults['accent']),$defaults['accent']),
            'background'=>self::safeColor((string)($style['background']??$defaults['background']),$defaults['background']),
            'surface'=>self::safeColor((string)($style['surface']??$defaults['surface']),$defaults['surface']),
            'text'=>self::safeColor((string)($style['text']??$defaults['text']),$defaults['text']),
            'muted'=>self::safeColor((string)($style['muted']??$defaults['muted']),$defaults['muted']),
            'font'=>$font,'width'=>max(680,min(1400,(int)($style['width']??$defaults['width']))),
            'radius'=>max(0,min(40,(int)($style['radius']??$defaults['radius']))),'hero'=>$hero,
        ];
    }

    private static function normalizeCustomBlocks(mixed $blocks): array
    {
        $out=[];if(!is_array($blocks))return $out;
        foreach(array_slice($blocks,0,12) as $index=>$block){if(!is_array($block))continue;$content=self::sanitizeEditorRich((string)($block['content_html']??''));$title=mb_substr(trim(strip_tags((string)($block['title']??''))),0,190);if($title===''&&trim(strip_tags($content))==='')continue;$out[]=['id'=>mb_substr(preg_replace('/[^a-zA-Z0-9_-]/','',(string)($block['id']??''))?:('custom-'.($index+1)),0,60),'title'=>$title,'content_html'=>$content,'visible'=>normalize_bool($block['visible']??1)];}
        return $out;
    }

    private static function richToPlain(string $html): string
    {
        $html=preg_replace('~<(br\s*/?|/p|/div|/h[1-6]|/li|/blockquote|/tr)>~i',"\n",$html)??$html;
        $html=preg_replace('~<(li|tr)[^>]*>~i','• ',$html)??$html;
        $text=html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $text=preg_replace('/[ \t]+/u',' ',$text)??$text;
        $text=preg_replace('/\h*\n\h*/u',"\n",$text)??$text;
        $text=preg_replace('/\n{3,}/u',"\n\n",$text)??$text;
        return mb_substr(trim($text),0,50000);
    }

    private static function safeColor(string $value,string $fallback): string
    {return preg_match('/^#[0-9a-fA-F]{6}$/',trim($value))?strtolower(trim($value)):$fallback;}

    private static function richFromValue(string $value): string
    {
        $value=trim($value);if($value==='')return '';
        if(str_contains($value,'<'))return self::sanitizeEditorRich($value);
        return '<p>'.nl2br(e($value)).'</p>';
    }

    private static function sanitizeEditorRich(string $html): string
    {
        $html=mb_substr(trim($html),0,50000);return self::sanitizeRich($html);
    }

    private static function editorContentBlocks(string $lang): array
    {
        $all=self::contentBlocks();$out=[];$lang=self::normalizeLanguage($lang);
        foreach($all as $block){if(!normalize_bool($block['active']??0))continue;$tr=$block['translations'][$lang]??$block['translations']['de']??[];$out[]=['id'=>(int)$block['id'],'code'=>(string)$block['code'],'internal_name'=>(string)$block['internal_name'],'title'=>(string)($tr['title']??$block['internal_name']),'content_html'=>(string)($tr['content_html']??'')];}
        return $out;
    }

    private static function replacePlaceholders(string $value,array $offer,bool $html=false): string
    {
        $lang=self::normalizeLanguage((string)($offer['language']??'de'));
        $replacements=[
            '{guest}'=>(string)($offer['guest_name']??''),'{number}'=>(string)($offer['offer_number']??''),
            '{property}'=>(string)setting('property_name','StayPilot'),
            '{arrival}'=>self::date((string)($offer['arrival']??''),$lang),'{departure}'=>self::date((string)($offer['departure']??''),$lang),
            '{valid_until}'=>self::date((string)($offer['valid_until']??''),$lang),
            '{total}'=>strip_tags(self::money((float)($offer['total_amount']??0),(string)($offer['currency']??'EUR'),$lang)),
            '{deposit}'=>strip_tags(self::money((float)($offer['deposit_amount']??0),(string)($offer['currency']??'EUR'),$lang)),
            '{remaining}'=>strip_tags(self::money((float)($offer['remaining_amount']??0),(string)($offer['currency']??'EUR'),$lang)),
        ];
        if($html){foreach($replacements as $key=>$replacement)$replacements[$key]=e($replacement);}
        return strtr($value,$replacements);
    }

    private static function publicCss(array $style): string
    {
        $normalized=self::normalizePublicStyle($style,[
            'accent'=>'#2563eb','background'=>'#eef3f9','surface'=>'#ffffff','text'=>'#172033','muted'=>'#64748b','font'=>'system','width'=>980,'radius'=>20,'hero'=>'image',
        ]);
        $font=match($normalized['font']){'serif'=>'Georgia,Times New Roman,serif','modern'=>'Inter,Segoe UI,Arial,sans-serif',default=>'system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Arial,sans-serif'};
        return ':root{--sp-accent:'.$normalized['accent'].';--sp-bg:'.$normalized['background'].';--sp-surface:'.$normalized['surface'].';--sp-text:'.$normalized['text'].';--sp-muted:'.$normalized['muted'].';--sp-radius:'.$normalized['radius'].'px}*{box-sizing:border-box}body{margin:0;background:var(--sp-bg);color:var(--sp-text);font-family:'.$font.';line-height:1.6}.wrap{width:min(calc(100% - 28px),'.$normalized['width'].'px);margin:28px auto;background:var(--sp-surface);border-radius:var(--sp-radius);overflow:hidden;box-shadow:0 24px 80px rgba(15,23,42,.16)}.sp-hero{position:relative;display:grid;min-height:300px;background:linear-gradient(135deg,var(--sp-accent),#0f172a);color:white}.sp-hero.split{grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr)}.sp-hero.compact{min-height:190px}.sp-hero-image{position:absolute;inset:0;overflow:hidden}.sp-hero.split .sp-hero-image{position:relative;grid-column:1}.sp-hero-image:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(15,23,42,.1),rgba(15,23,42,.82))}.sp-hero-image img{width:100%;height:100%;object-fit:cover}.sp-hero-copy{position:relative;z-index:2;align-self:end;padding:42px}.sp-hero.split .sp-hero-copy{grid-column:2;align-self:center}.sp-hero-copy h1{font-size:clamp(30px,5vw,54px);line-height:1.06;margin:8px 0}.sp-hero-copy p{font-size:20px;margin:0 0 14px}.sp-eyebrow{font-weight:800;letter-spacing:.13em;text-transform:uppercase;font-size:12px}.sp-status{display:inline-flex;background:rgba(255,255,255,.18);padding:7px 12px;border-radius:999px}.sp-offer-logo{max-height:70px;max-width:220px;object-fit:contain;background:rgba(255,255,255,.92);padding:8px;border-radius:10px;margin-bottom:12px}.sp-offer-section,.sp-offer-terms,.valid,.cta,[data-sp-offer-controls],footer{margin:0;padding:28px 42px}.sp-offer-section+ .sp-offer-section,.sp-offer-section+ .valid,.valid+ .sp-offer-section{border-top:1px solid #e5e7eb}.sp-offer-section h2,.sp-offer-terms h2{font-size:24px;margin:0 0 16px}.sp-offer-section h3{font-size:18px;margin:0 0 8px}.facts,.sp-offer-info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.facts div,.sp-offer-info-grid div,.sp-content-card{padding:16px;border:1px solid #e5e7eb;border-radius:calc(var(--sp-radius) * .65);background:#f8fafc}.facts small,.sp-offer-info-grid small{display:block;color:var(--sp-muted);margin-bottom:5px}.sp-gallery{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px}.sp-gallery figure{margin:0;aspect-ratio:4/3;overflow:hidden;border-radius:calc(var(--sp-radius) * .65)}.sp-gallery img{width:100%;height:100%;object-fit:cover}.sp-table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:620px}th,td{padding:13px 10px;border-bottom:1px solid #e5e7eb;text-align:left}th{background:#f8fafc;font-size:12px;text-transform:uppercase;letter-spacing:.06em}.totals{margin-left:auto;max-width:440px}.totals div{display:flex;justify-content:space-between;gap:20px;padding:8px 0}.totals .grand{border-top:2px solid var(--sp-text);font-size:21px;margin-top:5px;padding-top:14px}.valid,.sp-offer-note{background:#fffbeb;border-left:4px solid #f59e0b}.sp-offer-note{padding:17px;border-radius:10px}.sp-offer-rich a,.sp-offer-section a{color:var(--sp-accent)}.sp-offer-terms{background:#f8fafc;color:#475569}.sp-content-card+.sp-content-card{margin-top:12px}.cta{text-align:center}.cta a,.sp-public-actions button{display:inline-flex;align-items:center;justify-content:center;padding:14px 24px;border-radius:11px;background:var(--sp-accent);color:white;text-decoration:none;border:0;font-weight:800;cursor:pointer}.sp-preview-note{padding:16px;border:1px dashed var(--sp-accent);color:var(--sp-muted);text-align:center;border-radius:12px}.sp-public-actions{display:flex;flex-wrap:wrap;gap:12px;justify-content:center}.sp-public-actions .decline{background:#fff;color:#b91c1c;border:1px solid #fecaca}footer{background:#0f172a;color:#cbd5e1}.sp-offer-section blockquote{border-left:4px solid var(--sp-accent);margin:14px 0;padding:8px 16px;color:var(--sp-muted)}@media(max-width:760px){.wrap{width:100%;margin:0;border-radius:0}.sp-hero.split{display:block}.sp-hero.split .sp-hero-image{position:absolute}.sp-hero-copy,.sp-offer-section,.sp-offer-terms,.valid,.cta,[data-sp-offer-controls],footer{padding:22px}.facts,.sp-offer-info-grid{grid-template-columns:1fr}.sp-hero-copy{padding-top:90px}.sp-gallery{grid-template-columns:repeat(2,minmax(0,1fr))}}';
    }

    private static function calculationInput(array $data,array $price): array
    {
        $services=[];
        foreach((array)($data['services']??[]) as $selection){
            $serviceId=(int)($selection['service_id']??0);
            $quantity=max(0,(float)($selection['quantity']??0));
            if($serviceId>0&&$quantity>0)$services[]=['service_id'=>$serviceId,'quantity'=>$quantity];
        }
        return [
            'apartment_type_id'=>$price['apartment_type_id'],
            'apartment_id'=>$price['apartment_id'],
            'arrival'=>$price['arrival'],
            'departure'=>$price['departure'],
            'adults'=>$price['adults'],
            'children'=>$price['children'],
            'babies'=>$price['babies'],
            'pets'=>$price['pets'],
            'parking_spaces'=>max(0,(int)($data['parking_spaces']??0)),
            'extra_beds'=>max(0,(int)($data['extra_beds']??0)),
            'baby_beds'=>max(0,(int)($data['baby_beds']??0)),
            'late_checkout'=>normalize_bool($data['late_checkout']??0),
            'breakfast'=>normalize_bool($data['breakfast']??0),
            'breakfast_days'=>max(0,(int)($data['breakfast_days']??0)),
            'half_board'=>normalize_bool($data['half_board']??0),
            'half_board_days'=>max(0,(int)($data['half_board_days']??0)),
            'services'=>$services,
            'discount_code'=>strtoupper(trim((string)($data['discount_code']??''))),
            'manual_discount'=>max(0,(float)($data['manual_discount']??0)),
            'manual_discount_reason'=>trim((string)($data['manual_discount_reason']??'')),
            'deposit_percent'=>$price['deposit_percent'],
            'min_stay_override'=>normalize_bool($data['min_stay_override']??0),
        ];
    }

    private static function resolveGuest(array $data): array
    {
        $id=(int)($data['guest_id']??0);if($id){$stmt=db()->prepare('SELECT * FROM guests WHERE id=? LIMIT 1');$stmt->execute([$id]);$g=$stmt->fetch();if(!$g)throw new ValidationException('Der gewählte Gast wurde nicht gefunden.');return ['guest_id'=>$id,'name'=>trim(($g['title']?($g['title'].' '):'').$g['first_name'].' '.$g['last_name'].' '.($g['second_last_name']??'')),'email'=>(string)($g['email']??''),'phone'=>(string)($g['phone']??''),'address'=>trim(implode(', ',array_filter([$g['address']??'',trim(($g['postal_code']??'').' '.($g['city']??'')),$g['country']??''])))];}
        $first=trim((string)($data['guest_first_name']??''));$last=trim((string)($data['guest_last_name']??''));if($first===''||$last==='')throw new ValidationException('Bitte einen Gast auswählen oder Vor- und Nachname für einen neuen Gast eingeben.');$email=trim((string)($data['guest_email']??''));if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new ValidationException('Die E-Mail-Adresse des neuen Gastes ist ungültig.');$languageName=self::LANGUAGES[self::normalizeLanguage((string)($data['language']??'de'))]??'Deutsch';$stmt=db()->prepare('INSERT INTO guests(first_name,last_name,email,phone,address,postal_code,city,country,language,notes) VALUES(?,?,?,?,?,?,?,?,?,?)');$stmt->execute([$first,$last,$email,trim((string)($data['guest_phone']??'')),trim((string)($data['guest_address']??'')),trim((string)($data['guest_postal_code']??'')),trim((string)($data['guest_city']??'')),trim((string)($data['guest_country']??'')),$languageName,'Beim Anlegen eines Angebots erstellt']);$id=(int)db()->lastInsertId();return ['guest_id'=>$id,'name'=>$first.' '.$last,'email'=>$email,'phone'=>trim((string)($data['guest_phone']??'')),'address'=>trim(implode(', ',array_filter([$data['guest_address']??'',trim(($data['guest_postal_code']??'').' '.($data['guest_city']??'')),$data['guest_country']??''])))];
    }

    private static function nextNumber(string $type,string $prefix): string
    {
        $year=(int)date('Y');$pdo=db();$pdo->prepare('INSERT INTO document_sequences(document_type,document_year,current_value) VALUES(?,?,0) ON DUPLICATE KEY UPDATE current_value=current_value')->execute([$type,$year]);$pdo->prepare('UPDATE document_sequences SET current_value=LAST_INSERT_ID(current_value+1) WHERE document_type=? AND document_year=?')->execute([$type,$year]);$value=(int)$pdo->lastInsertId();return $prefix.'-'.$year.'-'.str_pad((string)$value,4,'0',STR_PAD_LEFT);
    }

    private static function event(int $offerId,string $type,?array $old,?array $new): void
    {
        $stmt=db()->prepare('INSERT INTO offer_events(offer_id,event_type,old_values_json,new_values_json,user_id,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)');$stmt->execute([$offerId,$type,$old?json_encode($old,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,$new?json_encode($new,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,Auth::user()['id']??null,$_SERVER['REMOTE_ADDR']??null,mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);
    }

    private static function expireIfNeeded(array &$offer): void
    {
        if(in_array((string)$offer['status'],['sent','viewed'],true)&&valid_date((string)$offer['valid_until'])&&$offer['valid_until']<date('Y-m-d')){db()->prepare("UPDATE offers SET status='expired' WHERE id=?")->execute([$offer['id']]);$offer['status']='expired';}
    }

    private static function publicUrlForRow(array $offer): string
    {
        $token=Crypto::decrypt((string)($offer['public_token_encrypted']??''));return $token!==''?HousekeepingWorkflow::applicationUrl('angebot.php?token='.rawurlencode($token)):'';
    }

    private static function item(string $type,string $description,float $quantity,string $unit,float $unitPrice,float $vat,float $total,int $sort,string $sourceType,?int $sourceId,array $metadata=[]): array
    {return ['item_type'=>$type,'description'=>$description,'quantity'=>round($quantity,3),'unit'=>$unit,'unit_price'=>round($unitPrice,2),'vat_rate'=>round($vat,2),'line_total'=>round($total,2),'sort_order'=>$sort,'source_type'=>$sourceType,'source_id'=>$sourceId,'metadata_json'=>$metadata?json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null];}

    private static function unitLabel(string $mode): string{return match($mode){'per_night'=>'Nacht','per_person'=>'Person','per_person_night'=>'Person/Nacht','quantity'=>'Stück',default=>'einmalig'};}
    private static function normalizeLanguage(string $language): string{$language=mb_strtolower(trim($language));return array_key_exists($language,self::LANGUAGES)?$language:'de';}
    private static function formatQuantity(float $value): string{return rtrim(rtrim(number_format($value,3,'.',''),'0'),'.');}
    private static function documentSettingsSnapshot(): array
    {
        $offer=self::settings();
        foreach(['legal_name','full_address','postal_code','city','province','country','contact_email','contact_phone','tax_id','checkin_time','checkout_time'] as $key)$offer[$key]=(string)setting($key,'');
        return $offer;
    }

    private static function buildDocumentSnapshot(string $lang,int $typeId,int $apartmentId,array $options,array $items=[]): array
    {
        $lang=self::normalizeLanguage($lang);$settings=self::documentSettingsSnapshot();
        $offer=['apartment_type_id'=>$typeId,'apartment_id'=>$apartmentId,'apartment_type_name'=>'','apartment_name'=>''];
        if($typeId){$stmt=db()->prepare('SELECT name FROM apartment_types WHERE id=? LIMIT 1');$stmt->execute([$typeId]);$offer['apartment_type_name']=(string)($stmt->fetchColumn()?:'');}
        if($apartmentId){$stmt=db()->prepare('SELECT name FROM apartments WHERE id=? LIMIT 1');$stmt->execute([$apartmentId]);$offer['apartment_name']=(string)($stmt->fetchColumn()?:'');}
        $content=self::accommodationContent($offer,$lang);
        $images=[];
        if($typeId){
            $imageStmt=db()->prepare('SELECT file_path,thumb_path,alt_text_json,is_cover,sort_order FROM apartment_type_images WHERE apartment_type_id=? ORDER BY is_cover DESC,sort_order,id LIMIT 12');
            $imageStmt->execute([$typeId]);
            foreach($imageStmt->fetchAll() as $image){
                $alts=json_decode((string)($image['alt_text_json']??''),true)?:[];
                $path=trim((string)($image['file_path']??''));if($path==='')continue;
                $images[]=['file_path'=>$path,'thumb_path'=>(string)($image['thumb_path']??''),'alt'=>(string)($alts[$lang]??$alts['de']??$offer['apartment_type_name']),'is_cover'=>normalize_bool($image['is_cover']??0),'sort_order'=>(int)($image['sort_order']??0)];
            }
        }
        $itemSnapshot=[];foreach($items as $item)$itemSnapshot[]=['description'=>self::translatedItemDescription($item,$lang),'unit'=>self::translatedUnit((string)($item['unit']??''),$lang)];
        return [
            'version'=>1,'created_at'=>date('c'),'language'=>$lang,
            'property_name'=>(string)setting('property_name','StayPilot'),
            'settings'=>$settings,
            'template'=>self::template($lang),
            'accommodation'=>['label'=>self::accommodationLabel($offer,$lang),'description'=>$content['description'],'amenities_html'=>$content['amenities_html']],
            'content_blocks'=>self::contentBlocksForDocument((array)($options['content_block_ids']??[]),$lang),
            'images'=>$images,
            'items'=>$itemSnapshot,
        ];
    }

    private static function documentOptions(array $data): array
    {
        $source=is_array($data['document_options']??null)?$data['document_options']:$data;
        $defaults=['include_equipment'=>1,'include_payment_info'=>1,'include_bank_info'=>1,'include_additional_info'=>1,'include_checkin_info'=>1,'include_company_info'=>1,'include_terms'=>1];
        foreach($defaults as $key=>$default)$defaults[$key]=array_key_exists($key,$source)?normalize_bool($source[$key]):$default;
        $ids=$source['content_block_ids']??[];
        if(!is_array($ids))$ids=preg_split('/[,;\s]+/',trim((string)$ids),-1,PREG_SPLIT_NO_EMPTY)?:[];
        $defaults['content_block_ids']=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));
        return $defaults;
    }

    private static function safePublicWebsiteUrl(string $url): bool
    {
        $url=trim($url);if($url==='')return true;
        if(preg_match('/[\x00-\x20]/',$url))return false;
        $parts=parse_url($url);
        return is_array($parts)&&isset($parts['scheme'],$parts['host'])&&!isset($parts['user'])&&!isset($parts['pass'])&&in_array(mb_strtolower((string)$parts['scheme']),['http','https'],true);
    }

    private static function safeAssetUrl(string $url): bool
    {
        $url=trim($url);if($url==='')return true;
        if(str_starts_with($url,'//')||preg_match('/[\\x00-\\x20]/',$url))return false;
        $parts=parse_url($url);if($parts===false)return false;
        if(isset($parts['scheme']))return in_array(mb_strtolower((string)$parts['scheme']),['http','https'],true);
        return !str_contains($url,':')&&preg_match('~^[A-Za-z0-9_./?=&%+\\-]+$~',$url)===1;
    }

    private static function sanitizeRich(string $html): string
    {
        $html=trim($html);if($html==='')return '';
        if(!class_exists('DOMDocument'))return trim(strip_tags($html,'<div><p><br><h2><h3><blockquote><hr><ul><ol><li><strong><b><em><i><u><a>'));
        $previous=libxml_use_internal_errors(true);
        $dom=new DOMDocument('1.0','UTF-8');
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="sp-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
        $allowed=['div','p','br','h2','h3','blockquote','hr','ul','ol','li','strong','b','em','i','u','a'];
        $nodes=[];foreach($dom->getElementsByTagName('*') as $node)$nodes[]=$node;
        foreach(array_reverse($nodes) as $node){
            $tag=mb_strtolower($node->nodeName);
            if($tag==='div'&&$node->getAttribute('id')==='sp-root')continue;
            if(!in_array($tag,$allowed,true)){
                if(in_array($tag,['script','style','iframe','object','embed','svg','math'],true)){$node->parentNode?->removeChild($node);continue;}
                while($node->firstChild)$node->parentNode?->insertBefore($node->firstChild,$node);
                $node->parentNode?->removeChild($node);continue;
            }
            $href=$tag==='a'?$node->getAttribute('href'):'';
            while($node->attributes?->length)$node->removeAttributeNode($node->attributes->item(0));
            if($tag==='a'&&$href!==''&&(preg_match('~^https?://~i',$href)||preg_match('~^mailto:[^\\s@]+@[^\\s@]+\\.[^\\s@]+$~i',$href)||preg_match('~^[/?#][^\\s]*$~',$href))){
                $node->setAttribute('href',$href);$node->setAttribute('rel','noopener noreferrer');
            }elseif($tag==='a'){
                while($node->firstChild)$node->parentNode?->insertBefore($node->firstChild,$node);
                $node->parentNode?->removeChild($node);
            }
        }
        $root=$dom->getElementById('sp-root');$out='';if($root)foreach($root->childNodes as $child)$out.=$dom->saveHTML($child);
        libxml_clear_errors();libxml_use_internal_errors($previous);return trim($out);
    }

    private static function template(string $lang): array
    {
        $stmt=db()->prepare('SELECT * FROM offer_text_templates WHERE language=? LIMIT 1');$stmt->execute([$lang]);$row=$stmt->fetch();if($row)return $row;$stmt->execute(['de']);return $stmt->fetch()?:['email_subject'=>'','greeting'=>self::defaultGreeting($lang),'intro'=>'','validity'=>'','closing'=>'','signature'=>'','footer'=>'','terms'=>'','payment_info'=>'','additional_label'=>'','additional_info'=>'','remaining_payment'=>''];
    }

    private static function accommodationLabel(array $offer,string $lang): string
    {
        $type=(string)($offer['apartment_type_name']??'');
        if((int)($offer['apartment_type_id']??0)){$stmt=db()->prepare('SELECT name FROM offer_apartment_type_translations WHERE apartment_type_id=? AND language=? LIMIT 1');$stmt->execute([$offer['apartment_type_id'],$lang]);$translated=trim((string)$stmt->fetchColumn());if($translated!=='')$type=$translated;}
        return trim($type.(!empty($offer['apartment_name'])?' · '.$offer['apartment_name']:''));
    }

    private static function accommodationContent(array $offer,string $lang): array
    {
        $typeId=(int)($offer['apartment_type_id']??0);if(!$typeId)return ['description'=>'','amenities_html'=>''];
        $stmt=db()->prepare('SELECT at.description,at.amenities_json,tr.description translated_description,tr.amenities_html FROM apartment_types at LEFT JOIN offer_apartment_type_translations tr ON tr.apartment_type_id=at.id AND tr.language=? WHERE at.id=? LIMIT 1');
        $stmt->execute([$lang,$typeId]);$row=$stmt->fetch()?:[];
        $description=trim((string)($row['translated_description']??''));if($description==='')$description=trim((string)($row['description']??''));
        $amenities=self::sanitizeRich((string)($row['amenities_html']??''));
        if($amenities===''){
            $list=json_decode((string)($row['amenities_json']??''),true);
            if(is_array($list)&&$list){$amenities='<ul>';foreach($list as $entry){if(trim((string)$entry)!=='')$amenities.='<li>'.e((string)$entry).'</li>';}$amenities.='</ul>';}
        }
        return ['description'=>$description,'amenities_html'=>$amenities];
    }
    private static function translatedItemDescription(array $item,string $lang): string{if((string)$item['source_type']==='offer_service'&&(int)$item['source_id']){$stmt=db()->prepare('SELECT name FROM offer_service_translations WHERE service_id=? AND language=? LIMIT 1');$stmt->execute([$item['source_id'],$lang]);$name=trim((string)$stmt->fetchColumn());if($name!=='')return $name;}return self::builtinItemTranslation((string)$item['item_type'],$lang,(string)$item['description']);}
    private static function builtinItemTranslation(string $type,string $lang,string $fallback): string{$map=self::dictionary($lang)['items']??[];return $map[$type]??$fallback;}
    private static function translatedUnit(string $unit,string $lang): string{$dict=self::dictionary($lang)['units']??[];return $dict[$unit]??$unit;}
    public static function statusLabel(string $status,string $lang='de'): string{$labels=self::dictionary($lang)['statuses']??[];return $labels[$status]??$status;}
    public static function date(string $date,string $lang): string
    {
        if(!valid_date((string)$date))return (string)$date;
        $lang=self::normalizeLanguage($lang);
        $locale=['de'=>'de-DE','en'=>'en-GB','es'=>'es-ES','pt'=>'pt-PT','fr'=>'fr-FR','it'=>'it-IT','ca'=>'ca-ES'][$lang]??'de-DE';
        if(class_exists('IntlDateFormatter')){
            $fmt=new IntlDateFormatter($locale,IntlDateFormatter::MEDIUM,IntlDateFormatter::NONE,date_default_timezone_get());
            $formatted=$fmt->format(new DateTimeImmutable($date));
            if($formatted!==false&&$formatted!=='')return (string)$formatted;
        }
        return (new DateTimeImmutable($date))->format($lang==='de'?'d.m.Y':'d/m/Y');
    }
    public static function money(float $amount,string $currency,string $lang): string
    {
        $lang=self::normalizeLanguage($lang);
        $currency=strtoupper(trim($currency))?:'EUR';
        $locale=['de'=>'de-DE','en'=>'en-GB','es'=>'es-ES','pt'=>'pt-PT','fr'=>'fr-FR','it'=>'it-IT','ca'=>'ca-ES'][$lang]??'de-DE';
        if(class_exists('NumberFormatter')){
            $fmt=new NumberFormatter($locale,NumberFormatter::CURRENCY);
            $formatted=$fmt->formatCurrency($amount,$currency);
            if($formatted!==false&&$formatted!=='')return e((string)$formatted);
        }
        if($lang==='en')return e($currency.' '.number_format($amount,2,'.',','));
        if($lang==='fr')return e(number_format($amount,2,',',' ').' '.$currency);
        return e(number_format($amount,2,',','.').' '.$currency);
    }
    private static function tr(string $lang,string $key,array $replace=[]): string{$value=(string)(self::dictionary($lang)[$key]??self::dictionary('de')[$key]??$key);foreach($replace as $k=>$v)$value=str_replace('{'.$k.'}',(string)$v,$value);return $value;}

    public static function dictionary(string $lang): array
    {
        $lang=self::normalizeLanguage($lang);$all=[
'de'=>['offer'=>'Angebot','guest'=>'Gast','accommodation'=>'Unterkunft','stay'=>'Aufenthalt','persons'=>'Personen','adults'=>'Erwachsene','children'=>'Kinder','item'=>'Position','quantity'=>'Menge','unit_price'=>'Einzelpreis','net'=>'Nettobetrag','vat'=>'MwSt.','total'=>'Gesamtpreis','deposit'=>'Anzahlung','remaining'=>'Restbetrag','open_offer'=>'Angebot ansehen und beantworten','accept'=>'Angebot annehmen','decline'=>'Angebot ablehnen','print'=>'Drucken / als PDF speichern','valid_until'=>'Gültig bis','email_subject'=>'Ihr Angebot {number}','accepted_message'=>'Vielen Dank. Ihre Angebotsannahme ist eingegangen und wird jetzt von Rezeption oder Verwaltung geprüft. Anschließend erhalten Sie die Buchungsbestätigung.','declined_message'=>'Das Angebot wurde abgelehnt.','expired_message'=>'Dieses Angebot ist abgelaufen.','already_answered'=>'Dieses Angebot wurde bereits beantwortet.','decision_prompt'=>'Dieses Angebot können Sie hier verbindlich annehmen oder ablehnen.','accept_confirm'=>'Möchten Sie dieses Angebot wirklich annehmen?','decline_confirm'=>'Möchten Sie dieses Angebot wirklich ablehnen?','draft_preview'=>'Dies ist eine Vorschau. Der Entwurf kann noch nicht beantwortet werden.','converted_message'=>'Dieses Angebot wurde bereits in eine Buchung übernommen.','items'=>['accommodation'=>'Unterkunft','cleaning'=>'Endreinigung','parking'=>'Parkplatz','pet'=>'Haustier','extra_bed'=>'Zusatzbett','baby_bed'=>'Babybett','late_checkout'=>'Spätabreise','breakfast'=>'Frühstück','half_board'=>'Halbpension','tourist_tax'=>'Touristensteuer'],'units'=>['Nacht'=>'Nacht','einmalig'=>'einmalig','Person'=>'Person','Person/Tag'=>'Person/Tag','Person/Nacht'=>'Person/Nacht','Stück'=>'Stück','Rabatt'=>'Rabatt'],'statuses'=>['draft'=>'Entwurf','sent'=>'Versendet','viewed'=>'Angesehen','accepted'=>'Angenommen','declined'=>'Abgelehnt','expired'=>'Abgelaufen','converted'=>'Als Buchung übernommen','archived'=>'Archiviert']],
'en'=>['offer'=>'Offer','guest'=>'Guest','accommodation'=>'Accommodation','stay'=>'Stay','persons'=>'Guests','adults'=>'adults','children'=>'children','item'=>'Item','quantity'=>'Quantity','unit_price'=>'Unit price','net'=>'Net amount','vat'=>'VAT','total'=>'Total price','deposit'=>'Deposit','remaining'=>'Remaining amount','open_offer'=>'View and respond to offer','accept'=>'Accept offer','decline'=>'Decline offer','print'=>'Print / save as PDF','valid_until'=>'Valid until','email_subject'=>'Your offer {number}','accepted_message'=>'Thank you. Your acceptance has been received and will now be reviewed. You will then receive the booking confirmation.','declined_message'=>'The offer has been declined.','expired_message'=>'This offer has expired.','already_answered'=>'This offer has already been answered.','decision_prompt'=>'You can accept or decline this offer here.','accept_confirm'=>'Do you really want to accept this offer?','decline_confirm'=>'Do you really want to decline this offer?','draft_preview'=>'This is a preview. The draft cannot be answered yet.','converted_message'=>'This offer has already been converted into a booking.','items'=>['accommodation'=>'Accommodation','cleaning'=>'Final cleaning','parking'=>'Parking','pet'=>'Pet','extra_bed'=>'Extra bed','baby_bed'=>'Baby cot','late_checkout'=>'Late check-out','breakfast'=>'Breakfast','half_board'=>'Half board','tourist_tax'=>'Tourist tax'],'units'=>['Nacht'=>'night','einmalig'=>'once','Person'=>'person','Person/Tag'=>'person/day','Person/Nacht'=>'person/night','Stück'=>'unit','Rabatt'=>'discount'],'statuses'=>['draft'=>'Draft','sent'=>'Sent','viewed'=>'Viewed','accepted'=>'Accepted','declined'=>'Declined','expired'=>'Expired','converted'=>'Converted to booking','archived'=>'Archived']],
'es'=>['offer'=>'Oferta','guest'=>'Huésped','accommodation'=>'Alojamiento','stay'=>'Estancia','persons'=>'Personas','adults'=>'adultos','children'=>'niños','item'=>'Concepto','quantity'=>'Cantidad','unit_price'=>'Precio unitario','net'=>'Importe neto','vat'=>'IVA','total'=>'Precio total','deposit'=>'Anticipo','remaining'=>'Importe restante','open_offer'=>'Ver y responder a la oferta','accept'=>'Aceptar oferta','decline'=>'Rechazar oferta','print'=>'Imprimir / guardar como PDF','valid_until'=>'Válida hasta','email_subject'=>'Su oferta {number}','accepted_message'=>'Gracias. Hemos recibido su aceptación y ahora será revisada. Después recibirá la confirmación de reserva.','declined_message'=>'La oferta ha sido rechazada.','expired_message'=>'Esta oferta ha caducado.','already_answered'=>'Esta oferta ya ha sido respondida.','decision_prompt'=>'Puede aceptar o rechazar esta oferta aquí.','accept_confirm'=>'¿Desea aceptar esta oferta?','decline_confirm'=>'¿Desea rechazar esta oferta?','draft_preview'=>'Esta es una vista previa. El borrador todavía no se puede responder.','converted_message'=>'Esta oferta ya se ha convertido en una reserva.','items'=>['accommodation'=>'Alojamiento','cleaning'=>'Limpieza final','parking'=>'Aparcamiento','pet'=>'Mascota','extra_bed'=>'Cama supletoria','baby_bed'=>'Cuna','late_checkout'=>'Salida tardía','breakfast'=>'Desayuno','half_board'=>'Media pensión','tourist_tax'=>'Tasa turística'],'units'=>['Nacht'=>'noche','einmalig'=>'una vez','Person'=>'persona','Person/Tag'=>'persona/día','Person/Nacht'=>'persona/noche','Stück'=>'unidad','Rabatt'=>'descuento'],'statuses'=>['draft'=>'Borrador','sent'=>'Enviada','viewed'=>'Vista','accepted'=>'Aceptada','declined'=>'Rechazada','expired'=>'Caducada','converted'=>'Convertida en reserva','archived'=>'Archivada']],
'pt'=>['offer'=>'Oferta','guest'=>'Hóspede','accommodation'=>'Alojamento','stay'=>'Estadia','persons'=>'Pessoas','adults'=>'adultos','children'=>'crianças','item'=>'Item','quantity'=>'Quantidade','unit_price'=>'Preço unitário','net'=>'Valor líquido','vat'=>'IVA','total'=>'Preço total','deposit'=>'Sinal','remaining'=>'Valor restante','open_offer'=>'Ver e responder à oferta','accept'=>'Aceitar oferta','decline'=>'Recusar oferta','print'=>'Imprimir / guardar como PDF','valid_until'=>'Válida até','email_subject'=>'A sua oferta {number}','accepted_message'=>'Obrigado. A sua aceitação foi recebida e será agora verificada. Depois receberá a confirmação da reserva.','declined_message'=>'A oferta foi recusada.','expired_message'=>'Esta oferta expirou.','already_answered'=>'Esta oferta já foi respondida.','decision_prompt'=>'Pode aceitar ou recusar esta oferta aqui.','accept_confirm'=>'Deseja aceitar esta oferta?','decline_confirm'=>'Deseja recusar esta oferta?','draft_preview'=>'Esta é uma pré-visualização. O rascunho ainda não pode ser respondido.','converted_message'=>'Esta oferta já foi convertida numa reserva.','items'=>['accommodation'=>'Alojamento','cleaning'=>'Limpeza final','parking'=>'Estacionamento','pet'=>'Animal de estimação','extra_bed'=>'Cama extra','baby_bed'=>'Berço','late_checkout'=>'Saída tardia','breakfast'=>'Pequeno-almoço','half_board'=>'Meia pensão','tourist_tax'=>'Taxa turística'],'units'=>['Nacht'=>'noite','einmalig'=>'uma vez','Person'=>'pessoa','Person/Tag'=>'pessoa/dia','Person/Nacht'=>'pessoa/noite','Stück'=>'unidade','Rabatt'=>'desconto'],'statuses'=>['draft'=>'Rascunho','sent'=>'Enviada','viewed'=>'Vista','accepted'=>'Aceite','declined'=>'Recusada','expired'=>'Expirada','converted'=>'Convertida em reserva','archived'=>'Arquivada']],
'fr'=>['offer'=>'Offre','guest'=>'Client','accommodation'=>'Hébergement','stay'=>'Séjour','persons'=>'Personnes','adults'=>'adultes','children'=>'enfants','item'=>'Poste','quantity'=>'Quantité','unit_price'=>'Prix unitaire','net'=>'Montant net','vat'=>'TVA','total'=>'Prix total','deposit'=>'Acompte','remaining'=>'Solde','open_offer'=>'Voir et répondre à l’offre','accept'=>'Accepter l’offre','decline'=>'Refuser l’offre','print'=>'Imprimer / enregistrer en PDF','valid_until'=>'Valable jusqu’au','email_subject'=>'Votre offre {number}','accepted_message'=>'Merci. Votre acceptation a été reçue et va maintenant être vérifiée. Vous recevrez ensuite la confirmation de réservation.','declined_message'=>'L’offre a été refusée.','expired_message'=>'Cette offre a expiré.','already_answered'=>'Cette offre a déjà reçu une réponse.','decision_prompt'=>'Vous pouvez accepter ou refuser cette offre ici.','accept_confirm'=>'Souhaitez-vous accepter cette offre ?','decline_confirm'=>'Souhaitez-vous refuser cette offre ?','draft_preview'=>'Ceci est un aperçu. Le brouillon ne peut pas encore recevoir de réponse.','converted_message'=>'Cette offre a déjà été convertie en réservation.','items'=>['accommodation'=>'Hébergement','cleaning'=>'Nettoyage final','parking'=>'Parking','pet'=>'Animal','extra_bed'=>'Lit supplémentaire','baby_bed'=>'Lit bébé','late_checkout'=>'Départ tardif','breakfast'=>'Petit-déjeuner','half_board'=>'Demi-pension','tourist_tax'=>'Taxe de séjour'],'units'=>['Nacht'=>'nuit','einmalig'=>'une fois','Person'=>'personne','Person/Tag'=>'personne/jour','Person/Nacht'=>'personne/nuit','Stück'=>'unité','Rabatt'=>'remise'],'statuses'=>['draft'=>'Brouillon','sent'=>'Envoyée','viewed'=>'Consultée','accepted'=>'Acceptée','declined'=>'Refusée','expired'=>'Expirée','converted'=>'Convertie en réservation','archived'=>'Archivée']],
'it'=>['offer'=>'Offerta','guest'=>'Ospite','accommodation'=>'Alloggio','stay'=>'Soggiorno','persons'=>'Persone','adults'=>'adulti','children'=>'bambini','item'=>'Voce','quantity'=>'Quantità','unit_price'=>'Prezzo unitario','net'=>'Importo netto','vat'=>'IVA','total'=>'Prezzo totale','deposit'=>'Acconto','remaining'=>'Importo restante','open_offer'=>'Visualizza e rispondi all’offerta','accept'=>'Accetta offerta','decline'=>'Rifiuta offerta','print'=>'Stampa / salva come PDF','valid_until'=>'Valida fino al','email_subject'=>'La Sua offerta {number}','accepted_message'=>'Grazie. La sua accettazione è stata ricevuta e sarà ora verificata. Riceverà poi la conferma della prenotazione.','declined_message'=>'L’offerta è stata rifiutata.','expired_message'=>'Questa offerta è scaduta.','already_answered'=>'Questa offerta ha già ricevuto una risposta.','decision_prompt'=>'Qui può accettare o rifiutare questa offerta.','accept_confirm'=>'Desidera accettare questa offerta?','decline_confirm'=>'Desidera rifiutare questa offerta?','draft_preview'=>'Questa è un’anteprima. La bozza non può ancora ricevere una risposta.','converted_message'=>'Questa offerta è già stata convertita in una prenotazione.','items'=>['accommodation'=>'Alloggio','cleaning'=>'Pulizia finale','parking'=>'Parcheggio','pet'=>'Animale domestico','extra_bed'=>'Letto aggiuntivo','baby_bed'=>'Culla','late_checkout'=>'Partenza tardiva','breakfast'=>'Colazione','half_board'=>'Mezza pensione','tourist_tax'=>'Tassa di soggiorno'],'units'=>['Nacht'=>'notte','einmalig'=>'una volta','Person'=>'persona','Person/Tag'=>'persona/giorno','Person/Nacht'=>'persona/notte','Stück'=>'unità','Rabatt'=>'sconto'],'statuses'=>['draft'=>'Bozza','sent'=>'Inviata','viewed'=>'Visualizzata','accepted'=>'Accettata','declined'=>'Rifiutata','expired'=>'Scaduta','converted'=>'Convertita in prenotazione','archived'=>'Archiviata']],
'ca'=>['offer'=>'Oferta','guest'=>'Hoste','accommodation'=>'Allotjament','stay'=>'Estada','persons'=>'Persones','adults'=>'adults','children'=>'nens','item'=>'Concepte','quantity'=>'Quantitat','unit_price'=>'Preu unitari','net'=>'Import net','vat'=>'IVA','total'=>'Preu total','deposit'=>'Paga i senyal','remaining'=>'Import restant','open_offer'=>'Veure i respondre l’oferta','accept'=>'Acceptar oferta','decline'=>'Rebutjar oferta','print'=>'Imprimir / desar com a PDF','valid_until'=>'Vàlida fins al','email_subject'=>'La seva oferta {number}','accepted_message'=>'Gràcies. Hem rebut la seva acceptació i ara serà revisada. Després rebrà la confirmació de la reserva.','declined_message'=>'L’oferta ha estat rebutjada.','expired_message'=>'Aquesta oferta ha caducat.','already_answered'=>'Aquesta oferta ja ha estat resposta.','decision_prompt'=>'Aquí pot acceptar o rebutjar aquesta oferta.','accept_confirm'=>'Vol acceptar aquesta oferta?','decline_confirm'=>'Vol rebutjar aquesta oferta?','draft_preview'=>'Aquesta és una vista prèvia. L’esborrany encara no es pot respondre.','converted_message'=>'Aquesta oferta ja s’ha convertit en una reserva.','items'=>['accommodation'=>'Allotjament','cleaning'=>'Neteja final','parking'=>'Aparcament','pet'=>'Mascota','extra_bed'=>'Llit supletori','baby_bed'=>'Bressol','late_checkout'=>'Sortida tardana','breakfast'=>'Esmorzar','half_board'=>'Mitja pensió','tourist_tax'=>'Taxa turística'],'units'=>['Nacht'=>'nit','einmalig'=>'una vegada','Person'=>'persona','Person/Tag'=>'persona/dia','Person/Nacht'=>'persona/nit','Stück'=>'unitat','Rabatt'=>'descompte'],'statuses'=>['draft'=>'Esborrany','sent'=>'Enviada','viewed'=>'Vista','accepted'=>'Acceptada','declined'=>'Rebutjada','expired'=>'Caducada','converted'=>'Convertida en reserva','archived'=>'Arxivada']],
        ];
        $extra=[
            'de'=>['accommodation_details'=>'Beschreibung & Ausstattung','payment_information'=>'Zahlungsinformationen','bank_details'=>'Bankverbindung','account_holder'=>'Kontoinhaber','iban'=>'IBAN','bic'=>'BIC / SWIFT','bank'=>'Bank','arrival_information'=>'An- und Abreise','checkin'=>'Check-in ab','checkout'=>'Check-out bis','deposit_due'=>'Anzahlung fällig am','additional_information'=>'Weitere Informationen','company_information'=>'Anbieter & Kontakt','tax_id'=>'Steuernummer','terms'=>'Bedingungen','action_error'=>'Die Aktion konnte nicht abgeschlossen werden. Bitte laden Sie die Seite neu oder kontaktieren Sie uns.','invalid_offer'=>'Das Angebot konnte nicht geladen werden.'],
            'en'=>['accommodation_details'=>'Description & amenities','payment_information'=>'Payment information','bank_details'=>'Bank details','account_holder'=>'Account holder','iban'=>'IBAN','bic'=>'BIC / SWIFT','bank'=>'Bank','arrival_information'=>'Arrival and departure','checkin'=>'Check-in from','checkout'=>'Check-out by','deposit_due'=>'Deposit due on','additional_information'=>'Additional information','company_information'=>'Provider & contact','tax_id'=>'Tax ID','terms'=>'Terms','action_error'=>'The action could not be completed. Please reload the page or contact us.','invalid_offer'=>'The offer could not be loaded.'],
            'es'=>['accommodation_details'=>'Descripción y equipamiento','payment_information'=>'Información de pago','bank_details'=>'Datos bancarios','account_holder'=>'Titular de la cuenta','iban'=>'IBAN','bic'=>'BIC / SWIFT','bank'=>'Banco','arrival_information'=>'Llegada y salida','checkin'=>'Check-in desde','checkout'=>'Check-out hasta','deposit_due'=>'Anticipo con vencimiento','additional_information'=>'Información adicional','company_information'=>'Proveedor y contacto','tax_id'=>'NIF / CIF','terms'=>'Condiciones','action_error'=>'No se pudo completar la acción. Recargue la página o póngase en contacto con nosotros.','invalid_offer'=>'No se pudo cargar la oferta.'],
            'pt'=>['accommodation_details'=>'Descrição e comodidades','payment_information'=>'Informações de pagamento','bank_details'=>'Dados bancários','account_holder'=>'Titular da conta','iban'=>'IBAN','bic'=>'BIC / SWIFT','bank'=>'Banco','arrival_information'=>'Chegada e partida','checkin'=>'Check-in a partir de','checkout'=>'Check-out até','deposit_due'=>'Sinal com vencimento em','additional_information'=>'Informações adicionais','company_information'=>'Fornecedor e contacto','tax_id'=>'NIF','terms'=>'Condições','action_error'=>'Não foi possível concluir a ação. Recarregue a página ou contacte-nos.','invalid_offer'=>'Não foi possível carregar a oferta.'],
            'fr'=>['accommodation_details'=>'Description et équipements','payment_information'=>'Informations de paiement','bank_details'=>'Coordonnées bancaires','account_holder'=>'Titulaire du compte','iban'=>'IBAN','bic'=>'BIC / SWIFT','bank'=>'Banque','arrival_information'=>'Arrivée et départ','checkin'=>'Check-in à partir de','checkout'=>'Check-out avant','deposit_due'=>'Acompte dû le','additional_information'=>'Informations complémentaires','company_information'=>'Prestataire et contact','tax_id'=>'Numéro fiscal','terms'=>'Conditions','action_error'=>'L’action n’a pas pu être effectuée. Rechargez la page ou contactez-nous.','invalid_offer'=>'L’offre n’a pas pu être chargée.'],
            'it'=>['accommodation_details'=>'Descrizione e dotazioni','payment_information'=>'Informazioni di pagamento','bank_details'=>'Coordinate bancarie','account_holder'=>'Intestatario del conto','iban'=>'IBAN','bic'=>'BIC / SWIFT','bank'=>'Banca','arrival_information'=>'Arrivo e partenza','checkin'=>'Check-in dalle','checkout'=>'Check-out entro','deposit_due'=>'Acconto dovuto il','additional_information'=>'Informazioni aggiuntive','company_information'=>'Fornitore e contatti','tax_id'=>'Codice fiscale / P. IVA','terms'=>'Condizioni','action_error'=>'Non è stato possibile completare l’azione. Ricarichi la pagina o ci contatti.','invalid_offer'=>'Non è stato possibile caricare l’offerta.'],
            'ca'=>['accommodation_details'=>'Descripció i equipament','payment_information'=>'Informació de pagament','bank_details'=>'Dades bancàries','account_holder'=>'Titular del compte','iban'=>'IBAN','bic'=>'BIC / SWIFT','bank'=>'Banc','arrival_information'=>'Arribada i sortida','checkin'=>'Check-in des de','checkout'=>'Check-out fins a','deposit_due'=>'Paga i senyal amb venciment','additional_information'=>'Informació addicional','company_information'=>'Proveïdor i contacte','tax_id'=>'NIF / CIF','terms'=>'Condicions','action_error'=>'No s’ha pogut completar l’acció. Torni a carregar la pàgina o contacti amb nosaltres.','invalid_offer'=>'No s’ha pogut carregar l’oferta.'],
        ];
        $result=array_replace_recursive($all[$lang]??$all['de'],$extra[$lang]??$extra['de']);
        $result['website']=['de'=>'Website','en'=>'Website','es'=>'Sitio web','pt'=>'Website','fr'=>'Site web','it'=>'Sito web','ca'=>'Lloc web'][$lang]??'Website';
        $result['babies']=['de'=>'Babys','en'=>'babies','es'=>'bebés','pt'=>'bebés','fr'=>'bébés','it'=>'bebè','ca'=>'nadons'][$lang]??'Babys';
        $result['pets']=['de'=>'Haustiere','en'=>'pets','es'=>'mascotas','pt'=>'animais','fr'=>'animaux','it'=>'animali','ca'=>'mascotes'][$lang]??'Haustiere';
        $result['preview_actions']=['de'=>'Die Schaltflächen zum Annehmen oder Ablehnen erscheinen hier auf der echten Gastseite.','en'=>'The accept and decline buttons appear here on the real guest page.','es'=>'Los botones para aceptar o rechazar aparecen aquí en la página real del huésped.','pt'=>'Os botões para aceitar ou recusar aparecem aqui na página real do hóspede.','fr'=>'Les boutons pour accepter ou refuser apparaissent ici sur la véritable page client.','it'=>'I pulsanti per accettare o rifiutare appaiono qui nella pagina reale dell’ospite.','ca'=>'Els botons per acceptar o rebutjar apareixen aquí a la pàgina real de l’hoste.'][$lang]??'Die Schaltflächen erscheinen auf der Gastseite.';
        return $result;
    }

    private static function emailCss(): string{return 'body{margin:0;background:#f1f5f9;color:#172033;font-family:Arial,sans-serif;line-height:1.55}.wrap{max-width:760px;margin:24px auto;background:#fff;border-radius:16px;padding:28px;box-shadow:0 10px 35px rgba(15,23,42,.1)}.header{display:flex;justify-content:space-between;gap:20px;border-bottom:1px solid #e2e8f0;padding-bottom:18px}.header h1{margin:5px 0 0}.header>span{background:#e0ecff;color:#1d4ed8;padding:8px 12px;border-radius:999px;height:max-content}.sp-offer-brand{display:flex;align-items:center;gap:14px}.sp-offer-logo{max-height:64px;max-width:180px;object-fit:contain}.facts,.sp-offer-info-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin:22px 0}.facts div,.sp-offer-info-grid div{background:#f8fafc;padding:13px;border-radius:10px}.facts small,.sp-offer-info-grid small{display:block;color:#64748b;margin-bottom:4px}.sp-offer-section{margin:24px 0}.sp-offer-section h2,.sp-offer-terms h2{font-size:18px;margin:0 0 10px}.sp-offer-rich ul{padding-left:22px}.sp-offer-rich a,.sp-offer-section a{color:#1d4ed8}table{width:100%;border-collapse:collapse}th,td{padding:11px;border-bottom:1px solid #e2e8f0;text-align:left}th{background:#f8fafc}.totals{margin:20px 0 0 auto;max-width:360px}.totals div{display:flex;justify-content:space-between;padding:7px 0}.totals .grand{border-top:2px solid #172033;font-size:18px;margin-top:5px;padding-top:12px}.valid,.sp-offer-note{padding:14px;border-radius:10px;background:#fffbeb;margin:20px 0}.cta{text-align:center;margin:26px 0}.cta a{display:inline-block;padding:14px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:10px;font-weight:bold}.sp-offer-terms{margin-top:22px;padding:14px;background:#f8fafc;border-radius:10px;color:#475569}footer{border-top:1px solid #e2e8f0;margin-top:24px;padding-top:16px;color:#64748b;font-size:13px}@media(max-width:620px){.wrap{margin:0;border-radius:0;padding:18px}.facts,.sp-offer-info-grid{grid-template-columns:1fr}.header{display:block}.header>span{display:inline-block;margin-top:10px}.sp-offer-brand{align-items:flex-start;flex-direction:column}th:nth-child(3),td:nth-child(3){display:none}}';}
}
