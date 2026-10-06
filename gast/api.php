<?php
declare(strict_types=1);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
require_once dirname(__DIR__).'/src/bootstrap.php';

try {
    $lang=(string)($_GET['lang']??'de');
    if(!in_array($lang,['de','es','en'],true))$lang='de';
    $token=trim((string)($_GET['token']??''));

    // Bereits versandte persönliche Links bleiben kompatibel. Neue Gästeansicht benötigt keinen Token.
    if($token!==''){
        $status=HousekeepingWorkflow::publicGuestStatus($token);
        $sql="SELECT title,body FROM guest_portal_contents WHERE active=1 AND language=? AND (scope_type='global' OR (scope_type='house' AND scope_id=?) OR (scope_type='booking' AND scope_id=?)) ORDER BY FIELD(scope_type,'booking','house','global'),sort_order,id";
        $stmt=db()->prepare($sql);$stmt->execute([$lang,$status['house_id'],$status['booking_id']]);
        json_response(['ok'=>true,'mode'=>'legacy','status'=>$status,'contents'=>$stmt->fetchAll(),'server_time'=>date('c')]);
    }

    $hours=max(1,min(48,(int)setting('guest_public_display_hours',12)));
    $cutoff=date('Y-m-d H:i:s',time()-$hours*3600);
    $stmt=db()->prepare("SELECT h.id task_id,h.released_at,a.id apartment_id,a.code apartment_code,a.apartment_number,a.name apartment_name,
        ho.id house_id,ho.name house_name,ho.default_checkin_time,ho.phone reception_phone
        FROM housekeeping_tasks h
        JOIN apartments a ON a.id=h.apartment_id
        LEFT JOIN houses ho ON ho.id=a.house_id
        WHERE h.status='released' AND h.released_at IS NOT NULL AND h.released_at>=?
        ORDER BY h.released_at DESC,h.id DESC LIMIT 100");
    $stmt->execute([$cutoff]);
    $seen=[];$apartments=[];$houseIds=[];
    foreach($stmt->fetchAll() as $row){
        $aid=(int)$row['apartment_id'];if(isset($seen[$aid]))continue;$seen[$aid]=true;
        $apartments[]=[
            'apartment_code'=>(string)($row['apartment_code']?:$row['apartment_number']?:$row['apartment_name']),
            'apartment_name'=>(string)($row['apartment_name']??''),
            'house_id'=>(int)($row['house_id']??0),
            'house_name'=>(string)($row['house_name']??''),
            'released_at'=>(string)$row['released_at'],
            'checkin_time'=>$row['default_checkin_time']??null,
            'reception_phone'=>$row['reception_phone']??null,
        ];
        if((int)($row['house_id']??0)>0)$houseIds[(int)$row['house_id']]=true;
    }

    $params=[$lang];
    $where="scope_type='global'";
    if($houseIds){$ph=implode(',',array_fill(0,count($houseIds),'?'));$where.=" OR (scope_type='house' AND scope_id IN ({$ph}))";$params=[...$params,...array_keys($houseIds)];}
    $contentStmt=db()->prepare("SELECT title,body,scope_type,scope_id FROM guest_portal_contents WHERE active=1 AND language=? AND ({$where}) ORDER BY FIELD(scope_type,'house','global'),sort_order,id");
    $contentStmt->execute($params);

    $suffix=$lang==='es'?'es':($lang==='en'?'en':'de');
    json_response([
        'ok'=>true,'mode'=>'public','apartments'=>$apartments,'contents'=>$contentStmt->fetchAll(),
        'title'=>(string)setting('guest_public_title_'.$suffix,$suffix==='es'?'Apartamentos listos':($suffix==='en'?'Apartments ready':'Bezugsbereite Wohnungen')),
        'empty_message'=>(string)setting('guest_public_empty_'.$suffix,$suffix==='es'?'Actualmente no hay ningún apartamento liberado para recoger la llave.':($suffix==='en'?'No apartment has currently been released for key collection.':'Zurzeit wurde noch keine Wohnung für die Schlüsselabholung freigegeben.')),
        'show_house'=>(bool)setting('guest_public_show_house',true),
        'display_hours'=>$hours,'server_time'=>date('c'),
    ]);
} catch(HttpException $e){json_response(['ok'=>false,'message'=>$e->getMessage()],$e->status());}
catch(Throwable $e){$rid=AppLogger::error($e,['portal'=>'guest'],'guest-portal');json_response(['ok'=>false,'message'=>'Die Gästeanzeige konnte nicht geladen werden.','request_id'=>$rid],500);}
