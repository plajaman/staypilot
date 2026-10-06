<?php
declare(strict_types=1);

function pricing_v205_data(): never
{
    $seasons = db()->query('SELECT * FROM seasons ORDER BY priority DESC,name')->fetchAll();
    $periods = db()->query('SELECT * FROM season_periods ORDER BY start_date,id')->fetchAll();
    $prices = db()->query('SELECT * FROM season_type_prices')->fetchAll();
    foreach ($seasons as &$season) {
        $season['periods'] = array_values(array_filter($periods, fn(array $row): bool => (int)$row['season_id'] === (int)$season['id']));
        $season['type_prices'] = array_values(array_filter($prices, fn(array $row): bool => (int)$row['season_id'] === (int)$season['id']));
    }
    unset($season);

    json_response([
        'ok' => true,
        'seasons' => $seasons,
        'apartment_types' => db()->query("SELECT id,name,code,standard_price,default_min_stay,active,sort_order FROM apartment_types ORDER BY active DESC,sort_order,name")->fetchAll(),
        'houses' => db()->query("SELECT id,name,code,active,sort_order FROM houses ORDER BY active DESC,sort_order,name")->fetchAll(),
        'apartments' => db()->query("SELECT id,house_id,apartment_type_id,code,name,base_price,min_stay_override,status,sort_order FROM apartments ORDER BY sort_order,name")->fetchAll(),
        'special_prices' => db()->query("SELECT sp.*,h.name house_name,t.name apartment_type_name,a.name apartment_name,a.code apartment_code
            FROM special_prices sp
            LEFT JOIN houses h ON h.id=sp.house_id
            LEFT JOIN apartment_types t ON t.id=sp.apartment_type_id
            LEFT JOIN apartments a ON a.id=sp.apartment_id
            ORDER BY sp.active DESC,sp.priority DESC,sp.start_date,sp.name")->fetchAll(),
        'blocks' => db()->query('SELECT bl.*,a.name apartment_name FROM availability_blocks bl JOIN apartments a ON a.id=bl.apartment_id ORDER BY start_date DESC LIMIT 500')->fetchAll(),
        'length_discounts' => db()->query('SELECT * FROM length_discounts ORDER BY min_nights')->fetchAll(),
        'discount_codes' => db()->query('SELECT d.*,a.name apartment_name FROM discount_codes d LEFT JOIN apartments a ON a.id=d.apartment_id ORDER BY d.active DESC,d.code')->fetchAll(),
        'default_min_stay' => max(1, (int)setting('default_min_stay', 1)),
    ]);
}

function save_season_v205(): never
{
    $d = request_data();
    $id = (int)($d['id'] ?? 0);
    $name = Validator::text($d, 'name', 'Saisonname', 120, true);
    $color = trim((string)($d['color'] ?? '#2563eb'));
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#2563eb';
    $priority = (int)($d['priority'] ?? 0);
    $minimum = max(1, (int)($d['default_min_stay'] ?? 1));
    $notes = Validator::text($d, 'notes', 'Hinweise', 20000) ?: null;
    $active = normalize_bool($d['active'] ?? 0);
    $old = $id ? fetch_named_row('seasons', $id) : null;
    try {
        if ($id) {
            if (!$old) throw new NotFoundException('Saison nicht gefunden.');
            db()->prepare('UPDATE seasons SET name=?,color=?,priority=?,default_min_stay=?,notes=?,active=? WHERE id=?')
                ->execute([$name,$color,$priority,$minimum,$notes,$active,$id]);
        } else {
            db()->prepare('INSERT INTO seasons(name,color,priority,default_min_stay,notes,active) VALUES(?,?,?,?,?,?)')
                ->execute([$name,$color,$priority,$minimum,$notes,$active]);
            $id = (int)db()->lastInsertId();
        }
    } catch (PDOException $e) {
        if ((string)$e->getCode() === '23000') throw new ConflictException('Dieser Saisonname ist bereits vorhanden.');
        throw $e;
    }
    $new = fetch_named_row('seasons', $id);
    AuditLogger::record('season', $id, $old ? 'update' : 'create', $old, $new, 'Saison gespeichert');
    json_response(['ok'=>true,'message'=>'Saison gespeichert.','id'=>$id]);
}

function delete_season_v205(): never
{
    $id = (int)(request_data()['id'] ?? 0);
    $old = fetch_named_row('seasons', $id);
    if (!$old) throw new NotFoundException('Saison nicht gefunden.');
    db()->prepare('DELETE FROM seasons WHERE id=?')->execute([$id]);
    AuditLogger::record('season',$id,'delete',$old,null,'Saison gelöscht');
    json_response(['ok'=>true,'message'=>'Saison gelöscht.']);
}

function save_season_period_v205(): never
{
    $d = request_data();
    $id = (int)($d['id'] ?? 0);
    $seasonId = (int)($d['season_id'] ?? 0);
    $start = (string)($d['start_date'] ?? '');
    $end = (string)($d['end_date'] ?? '');
    if (!$seasonId || !valid_date($start) || !valid_date($end) || $start > $end) throw new ValidationException('Saison und ein gültiger Zeitraum sind erforderlich.');
    $stmt = db()->prepare('SELECT COUNT(*) FROM seasons WHERE id=?');$stmt->execute([$seasonId]);
    if (!(int)$stmt->fetchColumn()) throw new ValidationException('Die gewählte Saison existiert nicht.');
    $minimumRaw = trim((string)($d['min_stay'] ?? ''));
    $minimum = $minimumRaw === '' ? null : max(1,(int)$minimumRaw);
    $notes = Validator::text($d,'notes','Hinweis',255) ?: null;

    // Überschneidungen werden nicht stillschweigend akzeptiert. Unterschiedliche Prioritäten
    // sind erlaubt; bei gleicher Priorität muss der Benutzer den Zeitraum bewusst korrigieren.
    $sql = "SELECT sp.id,s.name FROM season_periods sp JOIN seasons s ON s.id=sp.season_id
        JOIN seasons target ON target.id=?
        WHERE sp.start_date<=? AND sp.end_date>=? AND s.priority=target.priority";
    $params = [$seasonId,$end,$start];
    if ($id) {$sql .= ' AND sp.id<>?';$params[]=$id;}
    $stmt = db()->prepare($sql);$stmt->execute($params);$conflict=$stmt->fetch();
    if ($conflict) throw new ConflictException('Der Zeitraum überschneidet sich mit „'.$conflict['name'].'“ auf derselben Prioritätsstufe. Priorität oder Zeitraum anpassen.');

    $old = $id ? fetch_named_row('season_periods',$id) : null;
    if ($id) {
        if (!$old) throw new NotFoundException('Saisonzeitraum nicht gefunden.');
        db()->prepare('UPDATE season_periods SET season_id=?,start_date=?,end_date=?,min_stay=?,notes=? WHERE id=?')
            ->execute([$seasonId,$start,$end,$minimum,$notes,$id]);
    } else {
        db()->prepare('INSERT INTO season_periods(season_id,start_date,end_date,min_stay,notes) VALUES(?,?,?,?,?)')
            ->execute([$seasonId,$start,$end,$minimum,$notes]);
        $id=(int)db()->lastInsertId();
    }
    AuditLogger::record('season_period',$id,$old?'update':'create',$old,fetch_named_row('season_periods',$id),'Saisonzeitraum gespeichert');
    json_response(['ok'=>true,'message'=>'Saisonzeitraum gespeichert.','id'=>$id]);
}

function delete_season_period_v205(): never
{
    $id=(int)(request_data()['id']??0);$old=fetch_named_row('season_periods',$id);
    if(!$old)throw new NotFoundException('Saisonzeitraum nicht gefunden.');
    db()->prepare('DELETE FROM season_periods WHERE id=?')->execute([$id]);
    AuditLogger::record('season_period',$id,'delete',$old,null,'Saisonzeitraum gelöscht');
    json_response(['ok'=>true,'message'=>'Saisonzeitraum gelöscht.']);
}

function save_season_matrix_v205(): never
{
    $d=request_data();$rows=$d['rows']??[];
    if(!is_array($rows))throw new ValidationException('Preismatrix fehlt.');
    $stmt=db()->prepare('INSERT INTO season_type_prices(season_id,apartment_type_id,nightly_price,min_stay) VALUES(?,?,?,?)
        ON DUPLICATE KEY UPDATE nightly_price=VALUES(nightly_price),min_stay=VALUES(min_stay)');
    db()->beginTransaction();$count=0;
    try{
        foreach($rows as $row){
            if(!is_array($row))continue;
            $seasonId=(int)($row['season_id']??0);$typeId=(int)($row['apartment_type_id']??0);
            if(!$seasonId||!$typeId)continue;
            $priceRaw=trim((string)($row['nightly_price']??''));$minRaw=trim((string)($row['min_stay']??''));
            $price=$priceRaw===''?null:max(0,(float)str_replace(',','.',$priceRaw));
            $min=$minRaw===''?null:max(1,(int)$minRaw);
            $stmt->execute([$seasonId,$typeId,$price,$min]);$count++;
        }
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    AuditLogger::record('season_price_matrix','all','update',null,['rows'=>$count],'Saisonpreise und Mindestaufenthalte gespeichert');
    json_response(['ok'=>true,'message'=>$count.' Preis-/Mindestaufenthaltswerte gespeichert.']);
}

function save_type_minimums_v205(): never
{
    $d=request_data();$rows=$d['types']??[];
    if(!is_array($rows))throw new ValidationException('Wohnungstypen fehlen.');
    $global=max(1,(int)($d['global_default']??1));
    $stmt=db()->prepare('UPDATE apartment_types SET default_min_stay=? WHERE id=?');
    $exists=db()->prepare('SELECT COUNT(*) FROM apartment_types WHERE id=?');
    $saved=[];
    db()->beginTransaction();
    try{
        foreach($rows as $row){
            if(!is_array($row))continue;
            $id=(int)($row['id']??0);
            if($id<=0)continue;
            $exists->execute([$id]);
            if(!(int)$exists->fetchColumn())throw new ValidationException('Ein Wohnungstyp der Mindestaufenthalte existiert nicht mehr. Bitte Seite neu laden.');
            $minimum=max(1,(int)($row['default_min_stay']??1));
            $stmt->execute([$minimum,$id]);
            $saved[]=['id'=>$id,'default_min_stay'=>$minimum];
        }
        save_setting('default_min_stay',$global);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    AuditLogger::record('minimum_stay','all','update',null,['global_default'=>$global,'types'=>$saved],'Standard-Mindestaufenthalte gespeichert');
    json_response(['ok'=>true,'message'=>'Standard-Mindestaufenthalte gespeichert.']);
}

function save_special_price_v205(): never
{
    $d=request_data();$id=(int)($d['id']??0);
    $name=Validator::text($d,'name','Bezeichnung',160,true);
    $scope=Validator::oneOf($d,'scope_type','Gültigkeit',['all','house','apartment_type','apartment'],'all');
    $houseId=(int)($d['house_id']??0)?:null;$typeId=(int)($d['apartment_type_id']??0)?:null;$apartmentId=(int)($d['apartment_id']??0)?:null;
    if($scope==='house'&&!$houseId)throw new ValidationException('Bitte ein Haus wählen.');
    if($scope==='apartment_type'&&!$typeId)throw new ValidationException('Bitte einen Wohnungstyp wählen.');
    if($scope==='apartment'&&!$apartmentId)throw new ValidationException('Bitte ein Apartment wählen.');
    if($scope==='house'){$check=db()->prepare('SELECT COUNT(*) FROM houses WHERE id=?');$check->execute([$houseId]);if(!(int)$check->fetchColumn())throw new ValidationException('Das gewählte Haus existiert nicht mehr. Bitte Seite neu laden.');}
    if($scope==='apartment_type'){$check=db()->prepare('SELECT COUNT(*) FROM apartment_types WHERE id=?');$check->execute([$typeId]);if(!(int)$check->fetchColumn())throw new ValidationException('Der gewählte Wohnungstyp existiert nicht mehr. Bitte Seite neu laden.');}
    if($scope==='apartment'){$check=db()->prepare('SELECT COUNT(*) FROM apartments WHERE id=?');$check->execute([$apartmentId]);if(!(int)$check->fetchColumn())throw new ValidationException('Das gewählte Apartment existiert nicht mehr. Bitte Seite neu laden.');}
    $start=(string)($d['start_date']??'');$end=(string)($d['end_date']??'');
    if(!valid_date($start)||!valid_date($end)||$start>$end)throw new ValidationException('Gültiger Zeitraum erforderlich.');
    $mode=Validator::oneOf($d,'price_mode','Preisart',['fixed_nightly','percent','fixed_adjustment'],'fixed_nightly');
    $value=(float)str_replace(',','.',(string)($d['price_value']??0));
    if($mode==='fixed_nightly'&&$value<0)throw new ValidationException('Der feste Nachtpreis darf nicht negativ sein.');
    if($mode==='percent'&&($value<-100||$value>1000))throw new ValidationException('Prozentwert ist außerhalb des zulässigen Bereichs.');
    $weekdays=$d['weekdays']??[];if(is_string($weekdays))$weekdays=array_filter(array_map('intval',explode(',',$weekdays)));if(!is_array($weekdays))$weekdays=[];$weekdays=array_values(array_unique(array_filter(array_map('intval',$weekdays),fn($v)=>$v>=1&&$v<=7)));
    $minRaw=trim((string)($d['min_stay']??''));$minimum=$minRaw===''?null:max(1,(int)$minRaw);
    $values=[$name,$scope,$scope==='house'?$houseId:null,$scope==='apartment_type'?$typeId:null,$scope==='apartment'?$apartmentId:null,$start,$end,json_encode($weekdays),$mode,$value,$minimum,(int)($d['priority']??100),Validator::text($d,'notes','Hinweise',20000)?:null,normalize_bool($d['active']??0)];
    $old=$id?fetch_named_row('special_prices',$id):null;
    if($id){if(!$old)throw new NotFoundException('Sonderpreis nicht gefunden.');db()->prepare('UPDATE special_prices SET name=?,scope_type=?,house_id=?,apartment_type_id=?,apartment_id=?,start_date=?,end_date=?,weekdays_json=?,price_mode=?,price_value=?,min_stay=?,priority=?,notes=?,active=? WHERE id=?')->execute([...$values,$id]);}
    else{db()->prepare('INSERT INTO special_prices(name,scope_type,house_id,apartment_type_id,apartment_id,start_date,end_date,weekdays_json,price_mode,price_value,min_stay,priority,notes,active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values);$id=(int)db()->lastInsertId();}
    AuditLogger::record('special_price',$id,$old?'update':'create',$old,fetch_named_row('special_prices',$id),'Sonderpreis gespeichert');
    json_response(['ok'=>true,'message'=>'Sonderpreis gespeichert.','id'=>$id]);
}

function delete_special_price_v205(): never
{
    $id=(int)(request_data()['id']??0);$old=fetch_named_row('special_prices',$id);
    if(!$old)throw new NotFoundException('Sonderpreis nicht gefunden.');
    db()->prepare('DELETE FROM special_prices WHERE id=?')->execute([$id]);
    AuditLogger::record('special_price',$id,'delete',$old,null,'Sonderpreis gelöscht');
    json_response(['ok'=>true,'message'=>'Sonderpreis gelöscht.']);
}

function booking_channels_data(): never
{
    $rows=db()->query("SELECT c.*,(SELECT COUNT(*) FROM bookings b WHERE b.booking_channel_id=c.id OR (b.booking_channel_id IS NULL AND b.source=c.name)) booking_count FROM booking_channels c ORDER BY c.active DESC,c.sort_order,c.name")->fetchAll();
    json_response(['ok'=>true,'channels'=>$rows]);
}

function save_booking_channel(): never
{
    $d=request_data();$id=(int)($d['id']??0);$name=Validator::text($d,'name','Kanalname',120,true);
    $code=strtoupper(trim((string)($d['code']??'')));$code=trim((string)preg_replace('/[^A-Z0-9]+/','-',$code?:$name),'-');if($code==='')throw new ValidationException('Kurzcode fehlt.');
    $color=trim((string)($d['color']??'#64748b'));if(!preg_match('/^#[0-9a-fA-F]{6}$/',$color))$color='#64748b';
    $category=BookingAccountingService::normalizeMode((string)($d['default_accounting_mode']??''))===BookingAccountingService::MODE_INTERNAL?'direct':(BookingAccountingService::normalizeMode((string)($d['default_accounting_mode']??''))===BookingAccountingService::MODE_EXTERNAL?'portal':'internal_use');
    $accountingMode=BookingAccountingService::normalizeMode((string)($d['default_accounting_mode']??''),$name);
    $values=[$name,$code,$color,$category,$accountingMode,Validator::text($d,'description','Beschreibung',255)?:null,normalize_bool($d['active']??0),(int)($d['sort_order']??0)];
    $old=$id?fetch_named_row('booking_channels',$id):null;
    try{
        if($id){if(!$old)throw new NotFoundException('Buchungskanal nicht gefunden.');db()->prepare('UPDATE booking_channels SET name=?,code=?,color=?,channel_category=?,default_accounting_mode=?,description=?,active=?,sort_order=? WHERE id=?')->execute([...$values,$id]);}
        else{db()->prepare('INSERT INTO booking_channels(name,code,color,channel_category,default_accounting_mode,description,active,sort_order) VALUES(?,?,?,?,?,?,?,?)')->execute($values);$id=(int)db()->lastInsertId();}
    }catch(PDOException $e){if((string)$e->getCode()==='23000')throw new ConflictException('Name oder Kurzcode ist bereits vergeben.');throw $e;}
    // Das Textfeld source bleibt bewusst synchron, damit alte Statistiken und Exporte funktionieren.
    if($old&&$old['name']!==$name){
        db()->prepare('UPDATE bookings SET source=?,booking_channel_id=? WHERE booking_channel_id=? OR (booking_channel_id IS NULL AND source=?)')
            ->execute([$name,$id,$id,(string)$old['name']]);
    }
    AuditLogger::record('booking_channel',$id,$old?'update':'create',$old,fetch_named_row('booking_channels',$id),'Buchungskanal gespeichert');
    json_response(['ok'=>true,'message'=>'Buchungskanal gespeichert.','id'=>$id]);
}

function delete_booking_channel(): never
{
    $id=(int)(request_data()['id']??0);$old=fetch_named_row('booking_channels',$id);if(!$old)throw new NotFoundException('Buchungskanal nicht gefunden.');
    $stmt=db()->prepare('SELECT COUNT(*) FROM bookings WHERE booking_channel_id=? OR (booking_channel_id IS NULL AND source=?)');
    $stmt->execute([$id,(string)$old['name']]);
    if((int)$stmt->fetchColumn()>0)throw new ConflictException('Der Kanal wird von Buchungen verwendet. Bitte deaktivieren statt löschen.');
    db()->prepare('DELETE FROM booking_channels WHERE id=?')->execute([$id]);AuditLogger::record('booking_channel',$id,'delete',$old,null,'Buchungskanal gelöscht');
    json_response(['ok'=>true,'message'=>'Buchungskanal gelöscht.']);
}

function price_check_v205(): never
{
    $d=$_SERVER['REQUEST_METHOD']==='GET'?$_GET:request_data();$apartmentId=(int)($d['apartment_id']??0);$arrival=(string)($d['arrival']??'');$departure=(string)($d['departure']??'');
    if(!$apartmentId||!valid_date($arrival)||!valid_date($departure)||$arrival>=$departure)throw new ValidationException('Apartment und gültiger Zeitraum erforderlich.');
    $details=calculate_price_details($apartmentId,$arrival,$departure,[
        'special_price_type'=>(string)($d['special_price_type']??'none'),
        'special_price_value'=>(float)($d['special_price_value']??0),
    ]);
    json_response(['ok'=>true,'details'=>$details,'minimum'=>PricingService::minimumStay($apartmentId,$arrival,$departure)]);
}

function fetch_named_row(string $table,int $id): ?array
{
    $allowed=['seasons','season_periods','special_prices','booking_channels'];
    if(!in_array($table,$allowed,true))throw new InvalidArgumentException('Ungültige Tabelle.');
    $stmt=db()->prepare("SELECT * FROM `{$table}` WHERE id=? LIMIT 1");$stmt->execute([$id]);$row=$stmt->fetch();return $row?:null;
}

function csv_preview_v205(): never
{
    if(empty($_FILES['file'])||$_FILES['file']['error']!==UPLOAD_ERR_OK)throw new ValidationException('CSV-Datei konnte nicht hochgeladen werden.');
    $file=$_FILES['file'];if((int)$file['size']>(int)config()['max_csv_size'])throw new ValidationException('CSV-Datei ist zu groß.');
    $entity=(string)($_POST['entity_type']??'bookings');if(!isset(csv_target_definitions()[$entity]))throw new ValidationException('Unbekannter Importtyp.');
    $raw=file_get_contents($file['tmp_name']);if($raw===false)throw new RuntimeException('Datei konnte nicht gelesen werden.');
    $requestedEncoding=(string)($_POST['encoding_name']??'AUTO');
    $detected=mb_detect_encoding($raw,['UTF-8','Windows-1252','ISO-8859-1'],true)?:'UTF-8';
    $encoding=$requestedEncoding==='AUTO'?$detected:$requestedEncoding;
    if(!in_array($encoding,['UTF-8','Windows-1252','ISO-8859-1'],true))throw new ValidationException('Zeichensatz wird nicht unterstützt.');
    if($encoding!=='UTF-8')$raw=mb_convert_encoding($raw,'UTF-8',$encoding);
    $token=bin2hex(random_bytes(16));$path=root_path('storage/uploads/import_'.$token.'.csv');
    if(file_put_contents($path,$raw,LOCK_EX)===false)throw new RuntimeException('Temporäre Importdatei konnte nicht gespeichert werden.');
    $settings=[
        'delimiter'=>(string)($_POST['delimiter']??'AUTO'),
        'quote_char'=>(string)($_POST['quote_char']??'"'),
        'header_row'=>max(1,(int)($_POST['header_row']??1)),
        'skip_rows'=>max(0,(int)($_POST['skip_rows']??0)),
    ];
    $parsed=csv_parse_preview_v205($path,$settings);
    json_response(['ok'=>true,'token'=>$token,'filename'=>basename((string)$file['name']),'entity_type'=>$entity,'encoding'=>$encoding,'detected_encoding'=>$detected,'targets'=>csv_target_definitions()[$entity],...$parsed]);
}

function csv_repreview_v205(): never
{
    $d=request_data();$token=preg_replace('/[^a-f0-9]/','',(string)($d['token']??''));$entity=(string)($d['entity_type']??'');
    $path=root_path('storage/uploads/import_'.$token.'.csv');if(strlen($token)!==32||!is_file($path)||!isset(csv_target_definitions()[$entity]))throw new ValidationException('Importdatei ist abgelaufen oder ungültig.');
    $parsed=csv_parse_preview_v205($path,[
        'delimiter'=>(string)($d['delimiter']??'AUTO'),'quote_char'=>(string)($d['quote_char']??'"'),
        'header_row'=>max(1,(int)($d['header_row']??1)),'skip_rows'=>max(0,(int)($d['skip_rows']??0)),
    ]);
    json_response(['ok'=>true,'token'=>$token,'entity_type'=>$entity,'targets'=>csv_target_definitions()[$entity],...$parsed]);
}

function csv_parse_preview_v205(string $path,array $settings): array
{
    $raw=file_get_contents($path);if($raw===false)throw new RuntimeException('Importdatei konnte nicht gelesen werden.');
    $lines=preg_split('/\r\n|\n|\r/',$raw);$probe='';foreach($lines as $line){if(trim($line)!==''){$probe=$line;break;}}
    $delimiter=(string)($settings['delimiter']??'AUTO');
    if($delimiter==='AUTO'||$delimiter===''){$best=';';$bestCount=0;foreach([';',',',"\t",'|'] as $candidate){$count=count(str_getcsv($probe,$candidate));if($count>$bestCount){$bestCount=$count;$best=$candidate;}}$delimiter=$best;}
    if($delimiter==='TAB')$delimiter="\t";
    if(!in_array($delimiter,[';',',',"\t",'|'],true)&&mb_strlen($delimiter)!==1)throw new ValidationException('Trennzeichen ist ungültig.');
    $quote=(string)($settings['quote_char']??'"');if($quote==='NONE')$quote="\0";if($quote===''||mb_strlen($quote)>1)$quote='"';
    $headerRow=max(1,(int)($settings['header_row']??1));$skipRows=max(0,(int)($settings['skip_rows']??0));$headerLine=$skipRows+$headerRow;
    $h=fopen($path,'rb');if(!$h)throw new RuntimeException('Importdatei konnte nicht geöffnet werden.');
    $lineNo=0;$headers=[];$rows=[];
    while(($row=fgetcsv($h,0,$delimiter,$quote))!==false){$lineNo++;if($lineNo<=$skipRows)continue;if($lineNo===$headerLine){$headers=array_map(fn($v)=>trim((string)$v),$row);continue;}if($lineNo<$headerLine)continue;if(count($rows)<8)$rows[]=$row;else break;}
    fclose($h);if(!$headers)throw new ValidationException('In der angegebenen Kopfzeile wurden keine Spaltennamen gefunden.');
    return ['delimiter'=>$delimiter==="\t"?'TAB':$delimiter,'quote_char'=>$quote==="\0"?'NONE':$quote,'header_row'=>$headerRow,'skip_rows'=>$skipRows,'headers'=>$headers,'rows'=>$rows];
}

function csv_import_v205(): never
{
    global $user;$d=request_data();$token=preg_replace('/[^a-f0-9]/','',(string)($d['token']??''));$entity=(string)($d['entity_type']??'');$path=root_path('storage/uploads/import_'.$token.'.csv');
    if(strlen($token)!==32||!is_file($path)||!isset(csv_target_definitions()[$entity]))throw new ValidationException('Importdatei ist abgelaufen oder ungültig.');
    $delimiter=(string)($d['delimiter']??';');if($delimiter==='TAB')$delimiter="\t";$quote=(string)($d['quote_char']??'"');if($quote==='NONE')$quote="\0";
    $headerRow=max(1,(int)($d['header_row']??1));$skipRows=max(0,(int)($d['skip_rows']??0));
    $mapping=normalize_json_array($d['mapping']??[]);$defaults=normalize_json_array($d['defaults']??[]);$valueMappings=normalize_json_array($d['value_mappings']??[]);
    $dateFormat=(string)($d['date_format']??'Y-m-d');$decimal=(string)($d['decimal_separator']??',');$thousands=(string)($d['thousands_separator']??'.');
    $updateMode=(string)($d['update_mode']??'update');if(!in_array($updateMode,['update','skip','fill_empty','abort_on_error'],true))throw new ValidationException('Importmodus ist ungültig.');
    $profileName=trim((string)($d['profile_name']??''));
    $h=fopen($path,'rb');if(!$h)throw new RuntimeException('Importdatei konnte nicht geöffnet werden.');
    $headerLine=$skipRows+$headerRow;$lineNo=0;$headers=[];$index=[];$total=$imported=$skipped=0;$errors=[];db()->beginTransaction();
    try{
        while(($row=fgetcsv($h,0,$delimiter,$quote))!==false){
            $lineNo++;if($lineNo<=$skipRows)continue;if($lineNo===$headerLine){$headers=array_map(fn($v)=>trim((string)$v),$row);foreach($headers as $i=>$header)$index[$header]=$i;continue;}if($lineNo<$headerLine)continue;
            if(!$headers)throw new ValidationException('Kopfzeile wurde nicht gefunden.');
            if(count(array_filter($row,fn($v)=>trim((string)$v)!==''))===0)continue;
            $total++;if($total>(int)config()['max_csv_rows'])throw new RuntimeException('Maximale Zeilenzahl überschritten.');
            $record=[];foreach($mapping as $target=>$source){if($source!==''&&isset($index[$source]))$record[$target]=trim((string)($row[$index[$source]]??''));}
            foreach($defaults as $key=>$value){if(!isset($record[$key])||$record[$key]==='')$record[$key]=$value;}
            $record=apply_csv_value_mappings_v205($record,$valueMappings);
            $record=normalize_csv_numbers_v205($record,$entity,$decimal,$thousands);
            $savepoint='csv_row_'.$lineNo;
            db()->exec('SAVEPOINT '.$savepoint);
            try{
                if($updateMode==='skip'&&csv_record_exists_v205($entity,$record)){
                    db()->exec('RELEASE SAVEPOINT '.$savepoint);
                    $skipped++;
                    continue;
                }
                if($updateMode==='fill_empty')$record=csv_merge_existing_v205($entity,$record);
                import_csv_record_v205($entity,$record,$dateFormat,'.');
                db()->exec('RELEASE SAVEPOINT '.$savepoint);
                $imported++;
            }catch(Throwable $e){
                db()->exec('ROLLBACK TO SAVEPOINT '.$savepoint);
                db()->exec('RELEASE SAVEPOINT '.$savepoint);
                $skipped++;
                if(count($errors)<50)$errors[]='Zeile '.$lineNo.': '.$e->getMessage();
                if($updateMode==='abort_on_error')throw new RuntimeException('Import bei Zeile '.$lineNo.' abgebrochen: '.$e->getMessage());
            }
        }
        if($profileName!==''){
            $stmt=db()->prepare('INSERT INTO csv_profiles(name,entity_type,delimiter_char,encoding_name,quote_char,header_row,skip_rows,date_format,decimal_separator,thousands_separator,update_mode,mapping_json,defaults_json,value_mappings_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE delimiter_char=VALUES(delimiter_char),encoding_name=VALUES(encoding_name),quote_char=VALUES(quote_char),header_row=VALUES(header_row),skip_rows=VALUES(skip_rows),date_format=VALUES(date_format),decimal_separator=VALUES(decimal_separator),thousands_separator=VALUES(thousands_separator),update_mode=VALUES(update_mode),mapping_json=VALUES(mapping_json),defaults_json=VALUES(defaults_json),value_mappings_json=VALUES(value_mappings_json)');
            $stmt->execute([$profileName,$entity,$delimiter,(string)($d['encoding_name']??'UTF-8'),$quote,$headerRow,$skipRows,$dateFormat,$decimal,$thousands,$updateMode,json_encode($mapping,JSON_UNESCAPED_UNICODE),json_encode($defaults,JSON_UNESCAPED_UNICODE),json_encode($valueMappings,JSON_UNESCAPED_UNICODE)]);
        }
        $stmt=db()->prepare('INSERT INTO csv_imports(entity_type,filename,total_rows,imported_rows,skipped_rows,errors_json,created_by) VALUES(?,?,?,?,?,?,?)');$stmt->execute([$entity,basename($path),$total,$imported,$skipped,json_encode($errors,JSON_UNESCAPED_UNICODE),(int)$user['id']]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();fclose($h);@unlink($path);throw $e;}
    fclose($h);@unlink($path);json_response(['ok'=>true,'message'=>$imported.' Datensätze importiert, '.$skipped.' übersprungen.','imported'=>$imported,'skipped'=>$skipped,'errors'=>$errors]);
}

function normalize_json_array(mixed $value): array{if(is_array($value))return $value;if(is_string($value)){$decoded=json_decode($value,true);return is_array($decoded)?$decoded:[];}return [];}
function apply_csv_value_mappings_v205(array $record,array $maps): array{foreach($maps as $target=>$mapping){if(!isset($record[$target])||!is_array($mapping))continue;$current=trim((string)$record[$target]);foreach($mapping as $from=>$to){if(mb_strtolower(trim((string)$from))===mb_strtolower($current)){$record[$target]=$to;break;}}}return $record;}
function normalize_csv_numbers_v205(array $record,string $entity,string $decimal,string $thousands): array
{
    $numeric=[
        'apartments'=>['max_guests','bedrooms','bathrooms','base_price','cleaning_fee','breakfast_price','half_board_price','parking_price_per_night','pet_price_per_night','extra_bed_price_per_night','baby_bed_fee','late_checkout_fee'],
        'bookings'=>['adults','children','babies','pets','total_price','paid_amount','deposit_amount','tourist_tax','discount_amount'],
        'guests'=>[],
    ];
    foreach($numeric[$entity]??[] as $key){if(!isset($record[$key])||$record[$key]==='')continue;$value=(string)$record[$key];if($thousands!=='')$value=str_replace($thousands,'',$value);if($decimal!=='.')$value=str_replace($decimal,'.',$value);$record[$key]=$value;}
    return $record;
}
function csv_record_exists_v205(string $entity,array $record): bool
{
    if($entity==='apartments'&&!empty($record['code'])){$stmt=db()->prepare('SELECT COUNT(*) FROM apartments WHERE code=?');$stmt->execute([strtoupper((string)$record['code'])]);return (int)$stmt->fetchColumn()>0;}
    if($entity==='guests'&&!empty($record['email'])){$stmt=db()->prepare('SELECT COUNT(*) FROM guests WHERE email=?');$stmt->execute([trim((string)$record['email'])]);return (int)$stmt->fetchColumn()>0;}
    if($entity==='bookings'&&!empty($record['reference'])){$stmt=db()->prepare('SELECT COUNT(*) FROM bookings WHERE reference=?');$stmt->execute([trim((string)$record['reference'])]);return (int)$stmt->fetchColumn()>0;}
    if($entity==='bookings'&&!empty($record['external_id'])){$stmt=db()->prepare("SELECT COUNT(*) FROM bookings WHERE external_provider='csv' AND external_id=?");$stmt->execute([trim((string)$record['external_id'])]);return (int)$stmt->fetchColumn()>0;}
    return false;
}
function csv_merge_existing_v205(string $entity,array $record): array
{
    $row=null;
    if($entity==='apartments'&&!empty($record['code'])){$stmt=db()->prepare('SELECT * FROM apartments WHERE code=?');$stmt->execute([strtoupper((string)$record['code'])]);$row=$stmt->fetch();}
    if($entity==='guests'&&!empty($record['email'])){$stmt=db()->prepare('SELECT * FROM guests WHERE email=?');$stmt->execute([trim((string)$record['email'])]);$row=$stmt->fetch();}
    if($entity==='bookings'&&!empty($record['reference'])){$stmt=db()->prepare('SELECT * FROM bookings WHERE reference=?');$stmt->execute([trim((string)$record['reference'])]);$row=$stmt->fetch();}
    if($entity==='bookings'&&!$row&&!empty($record['external_id'])){$stmt=db()->prepare("SELECT * FROM bookings WHERE external_provider='csv' AND external_id=?");$stmt->execute([trim((string)$record['external_id'])]);$row=$stmt->fetch();}
    if(!$row)return $record;
    $aliases=['guest_request'=>'guest_request','source'=>'source','status'=>'status','notes'=>'notes','total_price'=>'total_price','paid_amount'=>'paid_amount','base_price'=>'base_price','cleaning_fee'=>'cleaning_fee'];
    foreach($record as $key=>$value){$dbKey=$aliases[$key]??$key;if(array_key_exists($dbKey,$row)&&trim((string)$row[$dbKey])!=='')$record[$key]=$row[$dbKey];}
    return $record;
}


function import_csv_record_v205(string $entity,array $r,string $dateFormat,string $decimal): void
{
    if($entity==='apartments'){
        import_csv_record($entity,$r,$dateFormat,$decimal);
        return;
    }
    if($entity==='guests'){
        import_csv_record($entity,$r,$dateFormat,$decimal);
        if(array_key_exists('preferences',$r)){
            $id=0;$email=trim((string)($r['email']??''));
            if($email!==''){$stmt=db()->prepare('SELECT id FROM guests WHERE email=? ORDER BY id DESC LIMIT 1');$stmt->execute([$email]);$id=(int)($stmt->fetchColumn()?:0);}
            if(!$id){$first=trim((string)($r['first_name']??''));$last=trim((string)($r['last_name']??''));if(($first===''||$last==='')&&!empty($r['full_name'])){$parts=preg_split('/\s+/',trim((string)$r['full_name']),2);$first=$parts[0]??'';$last=$parts[1]??'-';}$stmt=db()->prepare('SELECT id FROM guests WHERE first_name=? AND last_name=? ORDER BY id DESC LIMIT 1');$stmt->execute([$first,$last]);$id=(int)($stmt->fetchColumn()?:0);}
            if($id)db()->prepare('UPDATE guests SET preferences=? WHERE id=?')->execute([trim((string)$r['preferences'])?:null,$id]);
        }
        return;
    }
    if($entity==='bookings'){
        import_booking_csv_v205($r,$dateFormat,$decimal);
        return;
    }
    throw new RuntimeException('Unbekannter Importtyp.');
}

function booking_channel_id_by_name_v205(string $name): ?int
{
    $name=trim($name);if($name==='')return null;
    $stmt=db()->prepare('SELECT id FROM booking_channels WHERE LOWER(name)=LOWER(?) OR LOWER(code)=LOWER(?) LIMIT 1');$stmt->execute([$name,$name]);$id=$stmt->fetchColumn();if($id)return (int)$id;
    $base=strtoupper(trim((string)preg_replace('/[^A-Za-z0-9]+/','-',$name),'-'))?:'KANAL';$base=substr($base,0,50);$code=$base;$n=1;
    $exists=db()->prepare('SELECT COUNT(*) FROM booking_channels WHERE code=?');while(true){$exists->execute([$code]);if(!(int)$exists->fetchColumn())break;$n++;$code=substr($base,0,50).'-'.$n;}
    db()->prepare('INSERT INTO booking_channels(name,code,color,channel_category,default_accounting_mode,description,active,sort_order) VALUES(?,?,?,?,?,?,1,999)')->execute([$name,$code,'#64748b','import',BookingAccountingService::modeFromSource($name),'Beim CSV-Import angelegt']);
    return (int)db()->lastInsertId();
}

function import_booking_csv_v205(array $r,string $dateFormat,string $decimal): void
{
    $name=trim((string)($r['guest_name']??''));if($name==='')throw new RuntimeException('Gastname fehlt.');
    $email=trim((string)($r['guest_email']??''));$guestId=0;
    if($email!==''){$stmt=db()->prepare('SELECT id FROM guests WHERE email=? LIMIT 1');$stmt->execute([$email]);$guestId=(int)($stmt->fetchColumn()?:0);}
    if(!$guestId){$parts=preg_split('/\s+/',trim($name),2);db()->prepare('INSERT INTO guests(first_name,last_name,email,phone,language,category_id) VALUES(?,?,?,?,?,?)')->execute([$parts[0]??'Gast',$parts[1]??'-',$email?:null,$r['guest_phone']??null,'Deutsch',category_id_by_name(trim((string)($r['guest_category']??'')))]);$guestId=(int)db()->lastInsertId();}
    $aptId=0;if(!empty($r['apartment_code'])){$stmt=db()->prepare('SELECT id FROM apartments WHERE code=? LIMIT 1');$stmt->execute([strtoupper(trim((string)$r['apartment_code']))]);$aptId=(int)($stmt->fetchColumn()?:0);}elseif(!empty($r['apartment_name'])){$stmt=db()->prepare('SELECT id FROM apartments WHERE name=? LIMIT 1');$stmt->execute([trim((string)$r['apartment_name'])]);$aptId=(int)($stmt->fetchColumn()?:0);}
    $arrival=parse_csv_date((string)($r['arrival']??''),$dateFormat);$departure=parse_csv_date((string)($r['departure']??''),$dateFormat);if($arrival>=$departure)throw new RuntimeException('Abreise liegt nicht nach Anreise.');
    $external=trim((string)($r['external_id']??''));$reference=trim((string)($r['reference']??''));$existingId=0;
    if($reference!==''){$stmt=db()->prepare('SELECT id FROM bookings WHERE reference=? LIMIT 1');$stmt->execute([$reference]);$existingId=(int)($stmt->fetchColumn()?:0);}
    if(!$existingId&&$external!==''){$stmt=db()->prepare("SELECT id FROM bookings WHERE external_provider='csv' AND external_id=? LIMIT 1");$stmt->execute([$external]);$existingId=(int)($stmt->fetchColumn()?:0);}
    if($reference==='')$reference=generate_reference();
    if($aptId&&booking_conflict($aptId,$arrival,$departure,$existingId?:null))throw new RuntimeException('Doppelbelegung für '.$name.'.');
    $source=trim((string)($r['source']??'CSV'))?:'CSV';$channelId=booking_channel_id_by_name_v205($source);$accountingMode=BookingAccountingService::normalizeMode((string)($r['accounting_mode']??''),$source);$billingExcludedReason=trim((string)($r['billing_excluded_reason']??''));if($accountingMode!==BookingAccountingService::MODE_INTERNAL&&$billingExcludedReason==='')$billingExcludedReason=$accountingMode===BookingAccountingService::MODE_EXTERNAL?'CSV/XML/Portal-Import – extern abgerechnet':'Nur Belegung – keine Abrechnung';
    $bfStart=!empty($r['breakfast_start_date'])?parse_csv_date((string)$r['breakfast_start_date'],$dateFormat):null;$bfEnd=!empty($r['breakfast_end_date'])?parse_csv_date((string)$r['breakfast_end_date'],$dateFormat):null;$hpStart=!empty($r['half_board_start_date'])?parse_csv_date((string)$r['half_board_start_date'],$dateFormat):null;$hpEnd=!empty($r['half_board_end_date'])?parse_csv_date((string)$r['half_board_end_date'],$dateFormat):null;
    $status=(string)($r['status']??'confirmed');if(!in_array($status,['inquiry','confirmed','checked_in','checked_out','cancelled','rejected'],true))$status='confirmed';
    $columns=['reference','guest_id','apartment_id','arrival','departure','planned_arrival_time','planned_departure_time','adults','children','babies','pets','status','source','booking_channel_id','accounting_mode','billing_excluded_reason','total_price','paid_amount','payment_status','deposit_amount','tourist_tax','discount_amount','breakfast','breakfast_start_date','breakfast_end_date','half_board','half_board_start_date','half_board_end_date','is_upgrade','upgrade_note','vehicle_plate','guest_request','special_requests','internal_notes','notes','external_provider','external_id'];
    $values=[$reference,$guestId,$aptId?:null,$arrival,$departure,nullable_time($r['planned_arrival_time']??null),nullable_time($r['planned_departure_time']??null),max(1,(int)($r['adults']??1)),max(0,(int)($r['children']??0)),max(0,(int)($r['babies']??0)),max(0,(int)($r['pets']??0)),$status,$source,$channelId,$accountingMode,$billingExcludedReason?:null,csv_number($r['total_price']??0,$decimal),csv_number($r['paid_amount']??0,$decimal),(string)($r['payment_status']??'open'),csv_number($r['deposit_amount']??0,$decimal),csv_number($r['tourist_tax']??0,$decimal),csv_number($r['discount_amount']??0,$decimal),normalize_bool($r['breakfast']??0),$bfStart,$bfEnd,normalize_bool($r['half_board']??0),$hpStart,$hpEnd,normalize_bool($r['is_upgrade']??0),$r['upgrade_note']??null,$r['vehicle_plate']??null,$r['guest_request']??null,$r['special_requests']??null,$r['internal_notes']??null,$r['notes']??null,$external!==''?'csv':null,$external?:null];
    if($existingId){$sets=implode(',',array_map(fn($c)=>"`{$c}`=?",$columns));db()->prepare("UPDATE bookings SET {$sets} WHERE id=?")->execute([...$values,$existingId]);$bookingId=$existingId;}
    else{$quoted=implode(',',array_map(fn($c)=>"`{$c}`",$columns));$marks=implode(',',array_fill(0,count($columns),'?'));db()->prepare("INSERT INTO bookings({$quoted}) VALUES({$marks})")->execute($values);$bookingId=(int)db()->lastInsertId();}
    if($accountingMode!==BookingAccountingService::MODE_INTERNAL)BookingAccountingService::neutralizeInternalBilling(db(),$bookingId,$accountingMode,$billingExcludedReason);
}
