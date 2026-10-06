<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/bootstrap.php';
Auth::requireRole(['housekeeping_manager','admin','manager','reception']);
$incidentId=(int)($_GET['incident_id']??0);$index=max(0,(int)($_GET['index']??0));
$stmt=db()->prepare('SELECT photos_json FROM housekeeping_incidents WHERE id=? LIMIT 1');$stmt->execute([$incidentId]);$json=$stmt->fetchColumn();
$photos=json_decode((string)$json,true);if(!is_array($photos)||!isset($photos[$index])){http_response_code(404);exit('Foto nicht gefunden.');}
$relative=str_replace('\\','/',(string)$photos[$index]);
if(!str_starts_with($relative,'storage/uploads/housekeeping/')){http_response_code(403);exit('Ungültiger Fotopfad.');}
$uploadsRoot=realpath(root_path('storage/uploads/housekeeping'));$file=realpath(root_path($relative));
if(!$uploadsRoot||!$file||!str_starts_with($file,$uploadsRoot.DIRECTORY_SEPARATOR)||!is_file($file)){http_response_code(404);exit('Foto nicht gefunden.');}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(415);exit('Dateityp nicht erlaubt.');}
header('Content-Type: '.$mime);header('Content-Length: '.filesize($file));header('Content-Disposition: inline; filename="housekeeping-incident-'.$incidentId.'-'.$index.'.'.pathinfo($file,PATHINFO_EXTENSION).'"');header('Cache-Control: private, max-age=300');readfile($file);
