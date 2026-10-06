<?php
declare(strict_types=1);
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once dirname(__DIR__).'/src/bootstrap.php';
require_once __DIR__.'/_data.php';
$user=Auth::requireLogin();
if(!Auth::canForUser($user,'meals_manage')) json_response(['ok'=>false,'message'=>'Kein Zugriff auf Frühstück & HP.'],403);
try{
    $action=(string)($_GET['action'] ?? $_POST['action'] ?? 'meals');
    if($action==='meals') json_response(['ok'=>true,'data'=>bistro_fetch_meals($_GET)]);
    if($action==='set_status'){
        if($_SERVER['REQUEST_METHOD']!=='POST') json_response(['ok'=>false,'message'=>'Nur POST erlaubt.'],405);
        $raw=file_get_contents('php://input') ?: '';
        $data=json_decode($raw,true);
        if(!is_array($data)) $data=$_POST;
        $result=bistro_set_meal_status((int)($data['booking_id']??0),(string)($data['date']??''),(string)($data['meal_type']??''),(string)($data['status']??''),(string)($data['note']??''));
        if(class_exists('AuditLog')){
            try{ AuditLog::log('bistro_meal_status','meal_orders',$result['id'],['status'=>$result['status'],'booking_id'=>$result['booking_id'],'date'=>$result['date'],'meal_type'=>$result['meal_type']]); }catch(Throwable $ignore){}
        }
        json_response(['ok'=>true,'data'=>$result]);
    }
    json_response(['ok'=>false,'message'=>'Aktion nicht gefunden.'],404);
}catch(Throwable $e){$id=AppLogger::error($e,['portal'=>'bistro','action'=>$action??''],'bistro');json_response(['ok'=>false,'message'=>'Bistro-Daten konnten nicht verarbeitet werden.','request_id'=>$id],500);} 
