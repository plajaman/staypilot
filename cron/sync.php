<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/Integrations/IntegrationFactory.php';

$expected=(string)(local_config()['cron_token']??'');$provided=(string)($_GET['token']??($_SERVER['argv'][1]??''));
if($expected===''||!hash_equals($expected,$provided)){http_response_code(403);exit('Forbidden');}
$lock=fopen(root_path('storage/sync.lock'),'c+');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){exit("Synchronisation läuft bereits.\n");}
header('Content-Type: text/plain; charset=utf-8');
$rows=db()->query('SELECT * FROM integrations WHERE active=1 ORDER BY provider')->fetchAll();
foreach($rows as $row){$provider=$row['provider'];echo '['.date('c')."] {$provider}: ";try{$connector=IntegrationFactory::make($row);$result=$connector->pullReservations();log_sync($provider,'in','cron_reservations','success',$result['message']??'Erfolgreich',200,'',$result['raw']??'');db()->prepare("UPDATE integrations SET last_sync_at=NOW(),last_status='success',last_message=? WHERE provider=?")->execute([$result['message']??'Erfolgreich',$provider]);echo ($result['message']??'Erfolgreich')."\n";}catch(Throwable $e){log_sync($provider,'in','cron_reservations','error',$e->getMessage());db()->prepare("UPDATE integrations SET last_status='error',last_message=? WHERE provider=?")->execute([$e->getMessage(),$provider]);echo 'FEHLER: '.$e->getMessage()."\n";}}
save_setting('cron_last_run_at',date('Y-m-d H:i:s'));
flock($lock,LOCK_UN);fclose($lock);
