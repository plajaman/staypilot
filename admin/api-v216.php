<?php
declare(strict_types=1);

function apartment_type_management_v216(): never
{
    $language=normalize_language_v216((string)($_GET['language']??'de'));
    $types=db()->query("SELECT t.*,
        (SELECT COUNT(*) FROM apartments a WHERE a.apartment_type_id=t.id) apartment_count,
        (SELECT COUNT(*) FROM bookings b WHERE b.apartment_type_id=t.id AND b.apartment_id IS NULL AND b.status NOT IN ('cancelled','rejected')) unassigned_booking_count
        FROM apartment_types t ORDER BY t.active DESC,t.sort_order,t.name")->fetchAll();
    foreach($types as &$type)$type=enrich_apartment_type_v216($type,$language);
    json_response(['ok'=>true,'apartment_types'=>$types,'amenities'=>amenity_catalog_v216_data($language),'languages'=>OfferService::LANGUAGES]);
}

function apartment_type_editor_v216(): never
{
    $id=(int)($_GET['id']??0);
    if($id<=0){
        $row=['id'=>0,'name'=>'','code'=>'','max_occupancy'=>2,'standard_occupancy'=>2,'default_adults'=>2,'default_children'=>0,'bedrooms'=>1,'beds'=>1,'living_area'=>'','standard_price'=>0,'default_min_stay'=>1,'cleaning_fee'=>0,'standard_cleaning_minutes'=>60,'breakfast_price'=>0,'half_board_price'=>0,'parking_price'=>0,'pet_price'=>0,'extra_bed_price'=>0,'baby_bed_price'=>0,'allow_capacity_override'=>1,'public_active'=>1,'active'=>1,'sort_order'=>0,'cancel_free_until_days'=>30,'cancel_tier1_from_days'=>14,'cancel_tier1_percent'=>30,'cancel_tier2_from_days'=>0,'cancel_tier2_percent'=>80,'cancel_no_show_percent'=>100];
    }else{
        $row=fetch_row('apartment_types',$id);if(!$row)throw new NotFoundException('Wohnungstyp nicht gefunden.');
    }
    $row=enrich_apartment_type_v216($row,'de',true);
    json_response(['ok'=>true,'apartment_type'=>$row,'amenities'=>amenity_catalog_v216_data('de'),'languages'=>OfferService::LANGUAGES]);
}

function save_apartment_type_v216(): never
{
    $d=request_data();$id=(int)($d['id']??0);$old=$id?fetch_row('apartment_types',$id):null;if($id&&!$old)throw new NotFoundException('Wohnungstyp nicht gefunden.');
    $name=Validator::text($d,'name','Bezeichnung',160,true);$code=strtoupper(Validator::text($d,'code','Kurzcode',60,true));
    if(!preg_match('/^[A-Z0-9._-]{1,60}$/',$code))throw new ValidationException('Der Kurzcode darf nur Buchstaben, Zahlen, Punkt, Unterstrich und Bindestrich enthalten.');
    $max=max(1,min(99,(int)($d['max_occupancy']??2)));$standard=max(1,min($max,(int)($d['standard_occupancy']??$max)));
    $cancel=BookingPolicyService::validateCancellation($d);
    $translations=$d['translations']??[];if(!is_array($translations))$translations=[];
    $amenityIds=$d['amenity_ids']??[];if(!is_array($amenityIds))$amenityIds=preg_split('/[,;\s]+/',trim((string)$amenityIds),-1,PREG_SPLIT_NO_EMPTY)?:[];
    $amenityIds=array_values(array_unique(array_filter(array_map('intval',$amenityIds),static fn(int $v):bool=>$v>0)));
    $description=trim((string)($translations['de']['public_description_html']??$d['description']??''));
    $values=[
        $name,$code,$max,$standard,normalize_bool($d['allow_capacity_override']??1),normalize_bool($d['public_active']??1),
        max(0,min($max,(int)($d['default_adults']??2))),max(0,min($max,(int)($d['default_children']??0))),
        max(0,(int)($d['bedrooms']??1)),max(0,(int)($d['beds']??1)),($d['living_area']??'')===''?null:max(0,(float)str_replace(',','.',(string)$d['living_area'])),
        max(0,(float)str_replace(',','.',(string)($d['standard_price']??0))),max(1,(int)($d['default_min_stay']??1)),max(0,(float)str_replace(',','.',(string)($d['cleaning_fee']??0))),max(0,(int)($d['standard_cleaning_minutes']??60)),
        $old['amenities_json']??json_encode([],JSON_UNESCAPED_UNICODE),strip_tags($description),$old['photos_json']??json_encode([],JSON_UNESCAPED_UNICODE),
        max(0,(float)str_replace(',','.',(string)($d['breakfast_price']??0))),max(0,(float)str_replace(',','.',(string)($d['half_board_price']??0))),max(0,(float)str_replace(',','.',(string)($d['parking_price']??0))),max(0,(float)str_replace(',','.',(string)($d['pet_price']??0))),max(0,(float)str_replace(',','.',(string)($d['extra_bed_price']??0))),max(0,(float)str_replace(',','.',(string)($d['baby_bed_price']??0))),
        $old['discounts_json']??json_encode([],JSON_UNESCAPED_UNICODE),
        $cancel['cancel_free_until_days'],$cancel['cancel_tier1_from_days'],$cancel['cancel_tier1_percent'],$cancel['cancel_tier2_from_days'],$cancel['cancel_tier2_percent'],$cancel['cancel_no_show_percent'],
        normalize_bool($d['active']??1),(int)($d['sort_order']??0),
    ];
    $pdo=db();$pdo->beginTransaction();
    try{
        if($id){
            $sql='UPDATE apartment_types SET name=?,code=?,max_occupancy=?,standard_occupancy=?,allow_capacity_override=?,public_active=?,default_adults=?,default_children=?,bedrooms=?,beds=?,living_area=?,standard_price=?,default_min_stay=?,cleaning_fee=?,standard_cleaning_minutes=?,amenities_json=?,description=?,photos_json=?,breakfast_price=?,half_board_price=?,parking_price=?,pet_price=?,extra_bed_price=?,baby_bed_price=?,discounts_json=?,cancel_free_until_days=?,cancel_tier1_from_days=?,cancel_tier1_percent=?,cancel_tier2_from_days=?,cancel_tier2_percent=?,cancel_no_show_percent=?,active=?,sort_order=? WHERE id=?';
            $pdo->prepare($sql)->execute([...$values,$id]);
        }else{
            $sql='INSERT INTO apartment_types(name,code,max_occupancy,standard_occupancy,allow_capacity_override,public_active,default_adults,default_children,bedrooms,beds,living_area,standard_price,default_min_stay,cleaning_fee,standard_cleaning_minutes,amenities_json,description,photos_json,breakfast_price,half_board_price,parking_price,pet_price,extra_bed_price,baby_bed_price,discounts_json,cancel_free_until_days,cancel_tier1_from_days,cancel_tier1_percent,cancel_tier2_from_days,cancel_tier2_percent,cancel_no_show_percent,active,sort_order) VALUES('.implode(',',array_fill(0,count($values),'?')).')';
            $pdo->prepare($sql)->execute($values);$id=(int)$pdo->lastInsertId();
        }
        $translationStmt=$pdo->prepare('INSERT INTO offer_apartment_type_translations(apartment_type_id,language,name,description,amenities_html,public_description_html,seo_title,seo_description,request_hint,image_alt) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),amenities_html=VALUES(amenities_html),public_description_html=VALUES(public_description_html),seo_title=VALUES(seo_title),seo_description=VALUES(seo_description),request_hint=VALUES(request_hint),image_alt=VALUES(image_alt)');
        foreach(OfferService::LANGUAGES as $lang=>$label){
            $tr=is_array($translations[$lang]??null)?$translations[$lang]:[];
            $translatedName=mb_substr(trim((string)($tr['name']??($lang==='de'?$name:''))),0,160);
            if($translatedName===''&&$lang==='de')$translatedName=$name;
            $html=sanitize_rich_v216((string)($tr['public_description_html']??''));
            $plain=mb_substr(trim(strip_tags($html?:((string)($tr['description']??'')))),0,20000);
            $translationStmt->execute([$id,$lang,$translatedName,$plain,'',$html,mb_substr(trim((string)($tr['seo_title']??'')),0,190),mb_substr(trim((string)($tr['seo_description']??'')),0,320),mb_substr(trim((string)($tr['request_hint']??'')),0,500),mb_substr(trim((string)($tr['image_alt']??'')),0,255)]);
        }
        $pdo->prepare('DELETE FROM apartment_type_amenities WHERE apartment_type_id=?')->execute([$id]);
        if($amenityIds){
            $check=$pdo->prepare('SELECT id FROM amenity_catalog WHERE id=? AND active=1');$insert=$pdo->prepare('INSERT INTO apartment_type_amenities(apartment_type_id,amenity_id,sort_order) VALUES(?,?,?)');$sort=10;
            foreach($amenityIds as $amenityId){$check->execute([$amenityId]);if($check->fetchColumn()){$insert->execute([$id,$amenityId,$sort]);$sort+=10;}}
        }
        $pdo->commit();
    }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if((string)$e->getCode()==='23000')throw new ConflictException('Dieser Wohnungstyp-Kurzcode ist bereits vergeben.');throw $e;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    $new=fetch_row('apartment_types',$id);AuditLogger::record('apartment_type',$id,$old?'update':'create',$old,$new,'Wohnungstyp und öffentliche Inhalte gespeichert');
    json_response(['ok'=>true,'message'=>'Wohnungstyp vollständig gespeichert.','id'=>$id]);
}

function save_amenity_v216(): never
{
    $d=request_data();$id=(int)($d['id']??0);$code=mb_strtolower(trim((string)($d['code']??'')));$code=preg_replace('/[^a-z0-9_-]+/','_',$code);$icon=mb_substr(trim((string)($d['icon']??'✓')),0,40);$translations=$d['translations']??[];if(!is_array($translations))$translations=[];
    $de=trim((string)($translations['de']??$d['label']??''));if($de==='')throw new ValidationException('Bitte eine deutsche Bezeichnung eingeben.');if($code==='')$code='custom_'.substr(hash('sha256',$de.microtime(true)),0,10);
    $pdo=db();$pdo->beginTransaction();try{
        if($id)$pdo->prepare('UPDATE amenity_catalog SET code=?,icon=?,active=?,sort_order=? WHERE id=?')->execute([$code,$icon,normalize_bool($d['active']??1),(int)($d['sort_order']??0),$id]);
        else{$pdo->prepare('INSERT INTO amenity_catalog(code,icon,active,sort_order) VALUES(?,?,?,?)')->execute([$code,$icon,normalize_bool($d['active']??1),(int)($d['sort_order']??999)]);$id=(int)$pdo->lastInsertId();}
        $stmt=$pdo->prepare('INSERT INTO amenity_translations(amenity_id,language,label) VALUES(?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label)');
        foreach(OfferService::LANGUAGES as $lang=>$label){$value=mb_substr(trim((string)($translations[$lang]??($lang==='de'?$de:''))),0,160);if($value!=='')$stmt->execute([$id,$lang,$value]);}
        $pdo->commit();
    }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if((string)$e->getCode()==='23000')throw new ConflictException('Dieser Ausstattungscode ist bereits vergeben.');throw $e;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    json_response(['ok'=>true,'message'=>'Ausstattung gespeichert.','id'=>$id]);
}

function upload_type_image_v216(): never
{
    $typeId=(int)($_POST['apartment_type_id']??0);$alt=[];foreach(OfferService::LANGUAGES as $lang=>$label)$alt[$lang]=trim((string)($_POST['alt_'.$lang]??''));
    $image=TypeImageService::upload($typeId,$_FILES['image']??[],$alt);json_response(['ok'=>true,'message'=>'Bild optimiert und gespeichert.','image'=>$image]);
}
function delete_type_image_v216(): never{$id=(int)(request_data()['id']??0);TypeImageService::delete($id);json_response(['ok'=>true,'message'=>'Bild gelöscht.']);}
function set_type_cover_v216(): never{$id=(int)(request_data()['id']??0);TypeImageService::setCover($id);json_response(['ok'=>true,'message'=>'Titelbild festgelegt.']);}
function save_type_image_meta_v216(): never{$d=request_data();$image=TypeImageService::updateMeta((int)($d['id']??0),$d);json_response(['ok'=>true,'message'=>'Bildtexte gespeichert.','image'=>$image]);}

function bulk_create_apartments_v216(): never
{
    $d=request_data();$typeId=(int)($d['apartment_type_id']??0);$houseId=(int)($d['house_id']??0)?:null;$raw=trim((string)($d['numbers']??''));if($typeId<=0||!$houseId||$raw==='')throw new ValidationException('Wohnungstyp, Haus und mindestens eine Apartmentnummer sind erforderlich.');
    $type=BookingPolicyService::type($typeId);$numbers=parse_apartment_numbers_v216($raw);if(!$numbers)throw new ValidationException('Es wurden keine gültigen Apartmentnummern erkannt.');if(count($numbers)>200)throw new ValidationException('Pro Vorgang können höchstens 200 Apartments angelegt werden.');
    $stmt=db()->prepare('SELECT id,code FROM houses WHERE id=? AND active=1');$stmt->execute([$houseId]);$house=$stmt->fetch();if(!$house)throw new ValidationException('Das gewählte Haus ist nicht aktiv.');
    $pdo=db();$pdo->beginTransaction();$created=[];$skipped=[];
    try{
        $dup=$pdo->prepare('SELECT id FROM apartments WHERE house_id <=> ? AND apartment_number=? LIMIT 1');
        $insert=$pdo->prepare('INSERT INTO apartments(house_id,apartment_type_id,apartment_number,code,name,type,status,max_guests,bedrooms,bathrooms,base_price,cleaning_fee,breakfast_price,half_board_price,parking_price_per_night,pet_price_per_night,extra_bed_price_per_night,baby_bed_fee,internet_access,price_adjustment_type,price_adjustment_value,color,description,amenities_json,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach($numbers as $index=>$number){
            $dup->execute([$houseId,$number]);if($dup->fetchColumn()){$skipped[]=$number;continue;}
            $baseCode=trim(((string)($house['code']??'')).'-'.(string)$type['code'].'-'.$number,'-');$code=preg_replace('/[^A-Za-z0-9._-]+/','-',$baseCode);$suffix=1;$candidate=$code;
            while(true){$stmt=$pdo->prepare('SELECT COUNT(*) FROM apartments WHERE code=?');$stmt->execute([$candidate]);if(!(int)$stmt->fetchColumn())break;$candidate=$code.'-'.$suffix++;}
            $name=trim((string)$type['name'].' '.$number);$insert->execute([$houseId,$typeId,$number,$candidate,$name,'Ferienwohnung','active',(int)$type['max_occupancy'],(int)$type['bedrooms'],1,(float)$type['standard_price'],(float)$type['cleaning_fee'],(float)$type['breakfast_price'],(float)$type['half_board_price'],(float)$type['parking_price'],(float)$type['pet_price'],(float)$type['extra_bed_price'],(float)$type['baby_bed_price'],1,'fixed',0,'#2563eb',(string)($type['description']??''),(string)($type['amenities_json']??'[]'),($index+1)*10]);$created[]=['id'=>(int)$pdo->lastInsertId(),'number'=>$number,'code'=>$candidate];
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    AuditLogger::record('apartment_batch',$typeId,'create',null,['created'=>$created,'skipped'=>$skipped],'Apartments stapelweise aus Wohnungstyp angelegt');
    json_response(['ok'=>true,'message'=>count($created).' Apartment(s) angelegt.'.($skipped?' '.count($skipped).' bereits vorhandene Nummer(n) übersprungen.':''),'created'=>$created,'skipped'=>$skipped]);
}

function capacity_check_v216(): never
{
    $d=$_SERVER['REQUEST_METHOD']==='GET'?$_GET:request_data();$result=BookingPolicyService::capacityCheck((int)($d['apartment_type_id']??0),max(0,(int)($d['adults']??0)),max(0,(int)($d['children']??0)),max(0,(int)($d['babies']??0)),normalize_bool($d['capacity_override']??0),(string)($d['capacity_override_reason']??''));json_response(['ok'=>true,'capacity'=>$result]);
}

function cancellation_quote_v216(): never
{
    $d=$_SERVER['REQUEST_METHOD']==='GET'?$_GET:request_data();$typeId=(int)($d['apartment_type_id']??0);$snapshot=$typeId?BookingPolicyService::cancellationSnapshot($typeId):(json_decode((string)($d['snapshot_json']??''),true)?:[]);$quote=BookingPolicyService::cancellationQuote($snapshot,(string)($d['arrival']??''),(string)($d['cancel_date']??'')?:null,(float)($d['total']??0));json_response(['ok'=>true,'snapshot'=>$snapshot,'quote'=>$quote]);
}

function enrich_apartment_type_v216(array $type,string $language='de',bool $allLanguages=false): array
{
    $id=(int)($type['id']??0);$type['amenity_ids']=[];$type['amenities']=[];$type['images']=[];$type['translations']=[];
    if($id){
        $stmt=db()->prepare("SELECT c.id,c.code,c.icon,c.active,c.sort_order,COALESCE(tr.label,de.label,c.code) label FROM apartment_type_amenities ta JOIN amenity_catalog c ON c.id=ta.amenity_id LEFT JOIN amenity_translations tr ON tr.amenity_id=c.id AND tr.language=? LEFT JOIN amenity_translations de ON de.amenity_id=c.id AND de.language='de' WHERE ta.apartment_type_id=? ORDER BY ta.sort_order,c.sort_order,c.code");$stmt->execute([$language,$id]);$type['amenities']=$stmt->fetchAll();$type['amenity_ids']=array_map(static fn(array $r):int=>(int)$r['id'],$type['amenities']);
        $stmt=db()->prepare('SELECT * FROM apartment_type_images WHERE apartment_type_id=? ORDER BY is_cover DESC,sort_order,id');$stmt->execute([$id]);$type['images']=$stmt->fetchAll();foreach($type['images'] as &$image){$image['alt_texts']=json_decode((string)($image['alt_text_json']??''),true)?:[];$image['title_texts']=json_decode((string)($image['title_text_json']??''),true)?:[];$image['caption_texts']=json_decode((string)($image['caption_text_json']??''),true)?:[];}
        if($allLanguages){$stmt=db()->prepare('SELECT * FROM offer_apartment_type_translations WHERE apartment_type_id=?');$stmt->execute([$id]);foreach($stmt->fetchAll() as $tr)$type['translations'][$tr['language']]=$tr;}
        else{$stmt=db()->prepare("SELECT * FROM offer_apartment_type_translations WHERE apartment_type_id=? AND language IN (?, 'de') ORDER BY language=? DESC LIMIT 1");$stmt->execute([$id,$language,$language]);$tr=$stmt->fetch();if($tr)$type['translation']=$tr;}
    }
    $snapshot=['free_until_days'=>(int)($type['cancel_free_until_days']??30),'tier1_from_days'=>(int)($type['cancel_tier1_from_days']??14),'tier1_percent'=>(float)($type['cancel_tier1_percent']??30),'tier2_from_days'=>(int)($type['cancel_tier2_from_days']??0),'tier2_percent'=>(float)($type['cancel_tier2_percent']??80),'no_show_percent'=>(float)($type['cancel_no_show_percent']??100)];
    $type['cancellation_text']=BookingPolicyService::cancellationText($snapshot,$language);return $type;
}

function amenity_catalog_v216_data(string $language='de'): array
{
    $stmt=db()->prepare("SELECT c.*,COALESCE(tr.label,de.label,c.code) label FROM amenity_catalog c LEFT JOIN amenity_translations tr ON tr.amenity_id=c.id AND tr.language=? LEFT JOIN amenity_translations de ON de.amenity_id=c.id AND de.language='de' WHERE c.active=1 ORDER BY c.sort_order,c.code");$stmt->execute([$language]);$rows=$stmt->fetchAll();
    foreach($rows as &$row){$tr=db()->prepare('SELECT language,label FROM amenity_translations WHERE amenity_id=?');$tr->execute([(int)$row['id']]);$row['translations']=[];foreach($tr->fetchAll() as $t)$row['translations'][$t['language']]=$t['label'];}return $rows;
}

function parse_apartment_numbers_v216(string $raw): array
{
    $tokens=preg_split('/[,;\n\r]+/',$raw,-1,PREG_SPLIT_NO_EMPTY)?:[];$out=[];
    foreach($tokens as $token){$token=trim($token);if($token==='')continue;if(preg_match('/^(\d{1,5})\s*-\s*(\d{1,5})$/',$token,$m)){[$a,$b]=[(int)$m[1],(int)$m[2]];if(abs($b-$a)>200)throw new ValidationException('Ein Nummernbereich darf höchstens 200 Nummern umfassen.');$step=$a<=$b?1:-1;for($i=$a;;$i+=$step){$out[]=(string)$i;if($i===$b)break;}}else{$clean=mb_substr(preg_replace('/[^\pL\pN._-]+/u','-',trim($token)),0,60);if($clean!=='')$out[]=$clean;}}
    return array_values(array_unique($out));
}

function normalize_language_v216(string $language): string{return array_key_exists($language,OfferService::LANGUAGES)?$language:'de';}
function sanitize_rich_v216(string $html): string
{
    $html=trim($html);if($html==='')return '';
    if(!class_exists('DOMDocument')){
        // Ohne DOM-Erweiterung niemals HTML-Attribute ungeprüft übernehmen.
        // Der Inhalt bleibt lesbar, wird aber bewusst als sicherer Text gespeichert.
        $plain=trim(strip_tags($html));
        return $plain===''?'':'<p>'.nl2br(htmlspecialchars($plain,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')).'</p>';
    }
    $previous=libxml_use_internal_errors(true);$dom=new DOMDocument('1.0','UTF-8');$dom->loadHTML('<?xml encoding="utf-8" ?><div id="sp-v216-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
    $allowed=['div','p','br','ul','ol','li','strong','b','em','i','h2','h3','a'];$nodes=[];foreach($dom->getElementsByTagName('*') as $node)$nodes[]=$node;
    foreach(array_reverse($nodes) as $node){$tag=mb_strtolower($node->nodeName);if($tag==='div'&&$node->getAttribute('id')==='sp-v216-root')continue;if(!in_array($tag,$allowed,true)){if(in_array($tag,['script','style','iframe','object','embed','svg','math'],true)){$node->parentNode?->removeChild($node);continue;}while($node->firstChild)$node->parentNode?->insertBefore($node->firstChild,$node);$node->parentNode?->removeChild($node);continue;}$href=$tag==='a'?$node->getAttribute('href'):'';while($node->attributes?->length)$node->removeAttributeNode($node->attributes->item(0));if($tag==='a'&&$href!==''&&(preg_match('~^https?://~i',$href)||preg_match('~^mailto:~i',$href)||preg_match('~^/(?!/)~',$href)||preg_match('~^[?#]~',$href))){$node->setAttribute('href',$href);$node->setAttribute('rel','noopener noreferrer');}elseif($tag==='a'){while($node->firstChild)$node->parentNode?->insertBefore($node->firstChild,$node);$node->parentNode?->removeChild($node);}}
    $root=$dom->getElementById('sp-v216-root');$out='';if($root)foreach($root->childNodes as $child)$out.=$dom->saveHTML($child);libxml_clear_errors();libxml_use_internal_errors($previous);return trim($out);
}
