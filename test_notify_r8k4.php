<?php
declare(strict_types=1);
require __DIR__ . '/src/helpers.php';
require __DIR__ . '/src/Database.php';
require __DIR__ . '/src/Auth.php';
require __DIR__ . '/src/Crypto.php';
require __DIR__ . '/src/HttpException.php';
require __DIR__ . '/src/Validator.php';
require __DIR__ . '/src/Services/AppLogger.php';
require __DIR__ . '/src/Services/SmtpMailer.php';
header('Content-Type: application/json; charset=utf-8');

$recipients=array_filter(array_map('trim',explode(',',(string)setting('inquiry_notification_emails',''))));
$results=['configured_recipients'=>$recipients];

foreach($recipients as $to){
    $entry=['to'=>$to,'valid_format'=>(bool)filter_var($to,FILTER_VALIDATE_EMAIL)];
    if(!$entry['valid_format']){$entry['result']='uebersprungen (ungueltiges Format)';$results['attempts'][]=$entry;continue;}
    try{
        SmtpMailer::send($to,'StayPilot Testbenachrichtigung','Dies ist ein Testversand der Anfrage-Benachrichtigung. Wenn diese Mail ankommt, funktioniert die Funktion vollstaendig.');
        $entry['result']='OK: gesendet';
    }catch(Throwable $e){
        $entry['result']='FEHLER: '.$e->getMessage();
    }
    $results['attempts'][]=$entry;
}

echo json_encode($results, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
