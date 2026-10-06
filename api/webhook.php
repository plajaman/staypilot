<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/Integrations/IntegrationFactory.php';

$provider=(string)($_GET['provider']??'');
if($provider!=='hotel_spider')json_response(['ok'=>false,'message'=>'Unbekannter Webhook-Provider.'],404);
try{
    $stmt=db()->prepare('SELECT * FROM integrations WHERE provider=? AND active=1');$stmt->execute([$provider]);$integration=$stmt->fetch();if(!$integration)throw new RuntimeException('Schnittstelle ist nicht aktiv.');
    $raw=file_get_contents('php://input')?:'';$settings=json_decode((string)$integration['settings_json'],true)?:[];$webhookSecret=(string)($settings['webhook_secret']??Crypto::decrypt($integration['secret_encrypted']??null));
    if($webhookSecret!==''){$signature=(string)($_SERVER['HTTP_X_WEBHOOK_SIGNATURE']??'');$expected=hash_hmac('sha256',$raw,$webhookSecret);if(!hash_equals($expected,preg_replace('/^sha256=/','',$signature)))json_response(['ok'=>false,'message'=>'Signatur ungültig.'],401);}
    $data=json_decode($raw,true);if(!is_array($data))throw new RuntimeException('Ungültiges JSON.');$rows=$data['reservations']??$data['data']??[$data];$connector=new HotelSpiderConnector($integration);$count=0;foreach($rows as $row)if(is_array($row)&&$connector->importNormalized($row))$count++;
    log_sync($provider,'in','webhook','success',$count.' Reservierung(en) verarbeitet.',200,'',$raw);json_response(['ok'=>true,'processed'=>$count]);
}catch(Throwable $e){log_sync($provider,'in','webhook','error',$e->getMessage(),null,'',file_get_contents('php://input')?:'');json_response(['ok'=>false,'message'=>$e->getMessage()],422);}
