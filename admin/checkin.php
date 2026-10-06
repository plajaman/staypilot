<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; frame-ancestors 'none'");
header('Cache-Control: no-store, private');

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$booking = null;
$error = '';
$notice = '';
try {
    $booking = CheckinService::bookingByToken($token);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $booking = array_merge($booking, CheckinService::saveSubmission((int)$booking['booking_id'], $_POST, $_FILES, null, true));
        $summary = $booking['checkin_summary'];
        $notice = (int)$summary['missing_fields'] === 0
            ? 'Vielen Dank. Ihr Online-Check-in wurde gespeichert und ist vollständig.'
            : 'Ihre Angaben wurden gespeichert. Es fehlen noch ' . (int)$summary['missing_fields'] . ' Pflichtangabe(n).';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
$lang = (string)($booking['public_language'] ?? 'de');
if (!in_array($lang, ['de','en','es','fr','it','pt','ca'], true)) $lang = 'de';
$labels = [
    'de' => ['title'=>'Online-Check-in','intro'=>'Bitte erfassen Sie die Daten aller mitreisenden Personen. Sie können zusätzlich ein Foto oder PDF eines handschriftlichen Formulars hochladen.','booking'=>'Buchung','stay'=>'Aufenthalt','guest'=>'Hauptgast','arrival_time'=>'Geplante Anreisezeit','vehicle'=>'Autokennzeichen','requests'=>'Besondere Hinweise','travellers'=>'Reisende Personen','add'=>'Weitere Person hinzufügen','save'=>'Check-in speichern','privacy'=>'Ich bestätige, dass die Angaben korrekt sind und zur Bearbeitung des Aufenthalts gespeichert werden dürfen.','rules'=>'Ich habe Hausordnung / Bedingungen erhalten bzw. zur Kenntnis genommen.','signature'=>'Name als Bestätigung / Unterschrift','upload'=>'Handschriftliches Formular hochladen','upload_note'=>'Notiz zum Upload','back'=>'Zurück zum Kundenbereich','complete'=>'Vollständig','incomplete'=>'Unvollständig','missing'=>'Fehlende Angaben','file_help'=>'Erlaubt: PDF, JPG, PNG, WEBP oder HEIC bis 12 MB.','person'=>'Person','primary'=>'Hauptgast','minor'=>'Minderjährig','relationship'=>'Beziehung zum Hauptgast','first_name'=>'Vorname','last_name'=>'Nachname','second_last_name'=>'Zweiter Nachname','gender'=>'Geschlecht','document_type'=>'Dokumentart','document_number'=>'Dokumentnummer','support_number'=>'Supportnummer','document_country'=>'Ausstellungsland','document_issue_date'=>'Ausstellungsdatum','nationality'=>'Nationalität','birth'=>'Geburtsdatum','birth_place'=>'Geburtsort','address'=>'Adresse','postal'=>'PLZ','city'=>'Ort','country'=>'Land','phone'=>'Telefon','email'=>'E-Mail','notes'=>'Notiz'],
    'en' => ['title'=>'Online check-in','intro'=>'Please enter the details of all travelling guests. You may also upload a photo or PDF of a handwritten form.','booking'=>'Booking','stay'=>'Stay','guest'=>'Main guest','arrival_time'=>'Planned arrival time','vehicle'=>'Vehicle plate','requests'=>'Special requests','travellers'=>'Travelling guests','add'=>'Add another person','save'=>'Save check-in','privacy'=>'I confirm that the information is correct and may be stored for processing the stay.','rules'=>'I have received or acknowledged the house rules / terms.','signature'=>'Name as confirmation / signature','upload'=>'Upload handwritten form','upload_note'=>'Upload note','back'=>'Back to customer area','complete'=>'Complete','incomplete'=>'Incomplete','missing'=>'Missing details','file_help'=>'Allowed: PDF, JPG, PNG, WEBP or HEIC up to 12 MB.','person'=>'Person','primary'=>'Main guest','minor'=>'Minor','relationship'=>'Relationship to main guest','first_name'=>'First name','last_name'=>'Last name','second_last_name'=>'Second last name','gender'=>'Gender','document_type'=>'Document type','document_number'=>'Document number','support_number'=>'Support number','document_country'=>'Issuing country','document_issue_date'=>'Issue date','nationality'=>'Nationality','birth'=>'Date of birth','birth_place'=>'Place of birth','address'=>'Address','postal'=>'Postal code','city'=>'City','country'=>'Country','phone'=>'Phone','email'=>'Email','notes'=>'Note'],
    'es' => ['title'=>'Check-in online','intro'=>'Introduzca los datos de todas las personas que viajan. También puede subir una foto o PDF de un formulario escrito a mano.','booking'=>'Reserva','stay'=>'Estancia','guest'=>'Huésped principal','arrival_time'=>'Hora prevista de llegada','vehicle'=>'Matrícula','requests'=>'Observaciones especiales','travellers'=>'Personas viajeras','add'=>'Añadir persona','save'=>'Guardar check-in','privacy'=>'Confirmo que los datos son correctos y pueden guardarse para gestionar la estancia.','rules'=>'He recibido o acepto las normas / condiciones.','signature'=>'Nombre como confirmación / firma','upload'=>'Subir formulario manuscrito','upload_note'=>'Nota del archivo','back'=>'Volver al área de cliente','complete'=>'Completo','incomplete'=>'Incompleto','missing'=>'Datos pendientes','file_help'=>'Permitido: PDF, JPG, PNG, WEBP o HEIC hasta 12 MB.','person'=>'Persona','primary'=>'Huésped principal','minor'=>'Menor','relationship'=>'Relación con el huésped principal','first_name'=>'Nombre','last_name'=>'Apellido','second_last_name'=>'Segundo apellido','gender'=>'Sexo','document_type'=>'Tipo de documento','document_number'=>'Número de documento','support_number'=>'Número soporte','document_country'=>'País emisor','document_issue_date'=>'Fecha de emisión','nationality'=>'Nacionalidad','birth'=>'Fecha de nacimiento','birth_place'=>'Lugar de nacimiento','address'=>'Dirección','postal'=>'Código postal','city'=>'Ciudad','country'=>'País','phone'=>'Teléfono','email'=>'E-mail','notes'=>'Nota'],
];
$L = $labels[$lang] ?? $labels['de'];
function cdate(?string $date, string $lang): string { if (!$date || !valid_date($date)) return '–'; return (new DateTimeImmutable($date))->format($lang === 'de' ? 'd.m.Y' : 'd/m/Y'); }
function time5(?string $time): string { $time = (string)$time; return preg_match('/^\d\d:\d\d/', $time) ? substr($time,0,5) : ''; }
function traveller_value(array $row, string $key): string { return e((string)($row[$key] ?? '')); }
function traveller_row_html(array $row, int $i, array $L): string {
    $primary = (int)($row['is_primary'] ?? 0) === 1;
    $minor = (int)($row['minor'] ?? 0) === 1;
    ob_start(); ?>
    <article class="traveller" data-traveller-row>
      <div class="traveller-head"><b><?=e($L['person'])?> <?=($i+1)?></b><label><input type="checkbox" name="travellers[<?=$i?>][is_primary]" value="1" <?=$primary?'checked':''?>> <?=e($L['primary'])?></label><label><input type="checkbox" name="travellers[<?=$i?>][minor]" value="1" <?=$minor?'checked':''?>> <?=e($L['minor'])?></label></div>
      <div class="form-grid">
        <label><?=e($L['first_name'])?> *<input name="travellers[<?=$i?>][first_name]" value="<?=traveller_value($row,'first_name')?>" required></label>
        <label><?=e($L['last_name'])?> *<input name="travellers[<?=$i?>][last_name]" value="<?=traveller_value($row,'last_name')?>" required></label>
        <label><?=e($L['second_last_name'])?><input name="travellers[<?=$i?>][second_last_name]" value="<?=traveller_value($row,'second_last_name')?>"></label>
        <label><?=e($L['gender'])?> *<select name="travellers[<?=$i?>][gender]" required><?php $g=(string)($row['gender']??''); foreach([''=>'–','female'=>'Frau','male'=>'Mann','diverse'=>'Divers'] as $v=>$txt): ?><option value="<?=e($v)?>" <?=$g===$v?'selected':''?>><?=e($txt)?></option><?php endforeach; ?></select></label>
        <label><?=e($L['document_type'])?> *<select name="travellers[<?=$i?>][document_type]" required><?php $dt=(string)($row['document_type']??''); foreach([''=>'–','DNI'=>'DNI','NIE'=>'NIE','passport'=>'Reisepass','identity_card'=>'Personalausweis','other'=>'Sonstiges'] as $v=>$txt): ?><option value="<?=e($v)?>" <?=$dt===$v?'selected':''?>><?=e($txt)?></option><?php endforeach; ?></select></label>
        <label><?=e($L['document_number'])?> *<input name="travellers[<?=$i?>][document_number]" value="<?=traveller_value($row,'document_number')?>" required></label>
        <label><?=e($L['support_number'])?><input name="travellers[<?=$i?>][document_support_number]" value="<?=traveller_value($row,'document_support_number')?>"></label>
        <label><?=e($L['document_issue_date'])?><input type="date" name="travellers[<?=$i?>][document_issue_date]" value="<?=traveller_value($row,'document_issue_date')?>"></label>
        <label><?=e($L['document_country'])?><input name="travellers[<?=$i?>][document_country]" value="<?=traveller_value($row,'document_country')?>"></label>
        <label><?=e($L['nationality'])?> *<input name="travellers[<?=$i?>][nationality]" value="<?=traveller_value($row,'nationality')?>" required></label>
        <label><?=e($L['birth'])?> *<input type="date" name="travellers[<?=$i?>][date_of_birth]" value="<?=traveller_value($row,'date_of_birth')?>" required></label>
        <label><?=e($L['birth_place'])?><input name="travellers[<?=$i?>][place_of_birth]" value="<?=traveller_value($row,'place_of_birth')?>"></label>
        <label class="span-2"><?=e($L['address'])?> *<input name="travellers[<?=$i?>][address]" value="<?=traveller_value($row,'address')?>" required></label>
        <label><?=e($L['postal'])?> *<input name="travellers[<?=$i?>][postal_code]" value="<?=traveller_value($row,'postal_code')?>" required></label>
        <label><?=e($L['city'])?> *<input name="travellers[<?=$i?>][city]" value="<?=traveller_value($row,'city')?>" required></label>
        <label><?=e($L['country'])?> *<input name="travellers[<?=$i?>][country]" value="<?=traveller_value($row,'country')?>" required></label>
        <label><?=e($L['relationship'])?><input name="travellers[<?=$i?>][relationship_to_primary]" value="<?=traveller_value($row,'relationship_to_primary')?>"></label>
        <label><?=e($L['phone'])?><input name="travellers[<?=$i?>][mobile_phone]" value="<?=traveller_value($row,'mobile_phone')?>"></label>
        <label><?=e($L['email'])?><input type="email" name="travellers[<?=$i?>][email]" value="<?=traveller_value($row,'email')?>"></label>
        <label class="span-2"><?=e($L['notes'])?><textarea name="travellers[<?=$i?>][notes]"><?=traveller_value($row,'notes')?></textarea></label>
      </div>
    </article>
    <?php return (string)ob_get_clean();
}
?><!doctype html><html lang="<?=e($lang)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title><?=e($L['title'])?></title><style>
:root{--blue:#2563eb;--ink:#172033;--muted:#64748b;--line:#dbe6f1;--bg:#edf4fb;--ok:#16a34a;--warn:#f59e0b;--bad:#dc2626}*{box-sizing:border-box}body{margin:0;background:linear-gradient(180deg,#f8fbff,#edf4fb);font-family:Inter,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:var(--ink)}.wrap{max-width:1160px;margin:24px auto 50px;padding:0 16px}.hero{background:linear-gradient(135deg,#0f172a,#2563eb,#0f766e);color:#fff;border-radius:28px;padding:30px;box-shadow:0 22px 70px rgba(15,23,42,.22)}.hero h1{font-size:clamp(30px,5vw,54px);margin:6px 0}.hero p{color:#dbeafe}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:16px}.box{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25);border-radius:18px;padding:14px}.box small{display:block;opacity:.75}.card{background:#fff;border:1px solid var(--line);border-radius:24px;padding:22px;margin-top:16px;box-shadow:0 14px 40px rgba(15,23,42,.08)}.card h2{margin:0 0 14px}.form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}label{display:grid;gap:6px;font-weight:800;font-size:13px}input,select,textarea{width:100%;border:1px solid #cbd5e1;border-radius:12px;padding:11px;font:inherit;background:#fff}textarea{min-height:80px}.span-2{grid-column:span 2}.span-3{grid-column:span 3}.traveller{border:1px solid var(--line);border-radius:20px;padding:17px;margin-bottom:14px;background:#f8fafc}.traveller-head{display:flex;gap:14px;justify-content:space-between;align-items:center;margin-bottom:12px}.traveller-head label{display:flex;align-items:center;gap:6px;font-weight:700}.notice{border-radius:16px;padding:15px;margin-top:16px}.notice.ok{background:#dcfce7;color:#166534}.notice.warn{background:#fef3c7;color:#92400e}.notice.err{background:#fee2e2;color:#991b1b}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.btn{appearance:none;border:1px solid #cbd5e1;background:#fff;color:var(--ink);border-radius:14px;padding:12px 16px;font-weight:900;text-decoration:none;cursor:pointer}.btn.primary{background:var(--blue);border-color:var(--blue);color:#fff}.btn.soft{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}.muted{color:var(--muted)}.missing-list{columns:2;margin:6px 0 0}.file-list{display:grid;gap:8px}.file-row{display:flex;justify-content:space-between;gap:10px;border:1px solid var(--line);border-radius:14px;padding:12px;background:#f8fafc}@media(max-width:880px){.grid,.form-grid{grid-template-columns:1fr 1fr}.span-2,.span-3{grid-column:span 2}}@media(max-width:560px){.wrap{padding:0 10px;margin:10px auto 34px}.hero{border-radius:20px;padding:22px}.grid,.form-grid{grid-template-columns:1fr}.span-2,.span-3{grid-column:auto}.traveller-head{display:block}.missing-list{columns:1}}
</style></head><body><main class="wrap">
<?php if (!$booking): ?><section class="card"><h1><?=e($L['title'])?></h1><div class="notice err"><?=e($error ?: 'Der Check-in konnte nicht geöffnet werden.')?></div></section><?php else:
$summary=$booking['checkin_summary'];$checkin=$booking['checkin'];$travellers=$booking['travellers'];
$customerHref='kunde.php?token='.rawurlencode($token);
?>
<section class="hero"><small><?=e($L['booking'])?></small><h1><?=e($L['title'])?></h1><p><?=e($L['intro'])?></p><div class="grid"><div class="box"><small><?=e($L['booking'])?></small><b><?=e((string)$booking['reference'])?></b></div><div class="box"><small><?=e($L['stay'])?></small><b><?=e(cdate($booking['arrival'],$lang).' – '.cdate($booking['departure'],$lang))?></b></div><div class="box"><small><?=e($L['guest'])?></small><b><?=e((string)$booking['guest_name'])?></b></div><div class="box"><small>Status</small><b><?=e(((int)$summary['missing_fields']===0)?$L['complete']:$L['incomplete'])?></b></div></div></section>
<?php if ($notice): ?><div class="notice <?=(int)$summary['missing_fields']===0?'ok':'warn'?>"><?=e($notice)?></div><?php endif; ?>
<?php if ((int)$summary['missing_fields']>0): ?><section class="card"><h2>⚠️ <?=e($L['missing'])?></h2><ul class="missing-list"><?php foreach($summary['missing_details'] as $m): ?><li><?=e($m)?></li><?php endforeach; ?></ul></section><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card" id="checkinForm"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="token" value="<?=e($token)?>"><h2>🕒 <?=e($L['arrival_time'])?></h2><div class="form-grid"><label><?=e($L['arrival_time'])?><input type="time" name="planned_arrival_time" value="<?=e(time5($checkin['planned_arrival_time'] ?: $booking['planned_arrival_time'] ?? ''))?>"></label><label><?=e($L['vehicle'])?><input name="vehicle_plate" value="<?=e((string)($checkin['vehicle_plate'] ?: $booking['vehicle_plate'] ?? ''))?>"></label><label class="span-2"><?=e($L['requests'])?><textarea name="special_requests"><?=e((string)($checkin['special_requests'] ?: $booking['special_requests'] ?? $booking['guest_request'] ?? ''))?></textarea></label></div>
<h2 style="margin-top:24px">🛂 <?=e($L['travellers'])?></h2><div id="travellerRows"><?php foreach($travellers as $i=>$row) echo traveller_row_html($row,(int)$i,$L); ?></div><button class="btn soft" type="button" id="addTraveller">＋ <?=e($L['add'])?></button>
<section class="card" style="box-shadow:none"><h2>📎 <?=e($L['upload'])?></h2><div class="form-grid"><label class="span-2"><?=e($L['upload'])?><input type="file" name="checkin_file" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,application/pdf,image/*"><span class="muted"><?=e($L['file_help'])?></span></label><label><?=e($L['upload_note'])?><input name="upload_note"></label></div><?php if(!empty($booking['checkin_uploads'])): ?><div class="file-list" style="margin-top:12px"><?php foreach($booking['checkin_uploads'] as $u): ?><div class="file-row"><span>📄 <?=e((string)$u['original_name'])?><br><small class="muted"><?=e((string)$u['created_at'])?></small></span><a class="btn" target="_blank" href="checkin-datei.php?token=<?=rawurlencode($token)?>&amp;id=<?=(int)$u['id']?>">Öffnen</a></div><?php endforeach; ?></div><?php endif; ?></section>
<section class="card" style="box-shadow:none"><h2>✅ Bestätigung</h2><div class="form-grid"><label class="span-3"><input type="checkbox" name="consent_privacy" value="1" <?=(int)($checkin['consent_privacy']??0)===1?'checked':''?> required> <?=e($L['privacy'])?></label><label class="span-3"><input type="checkbox" name="consent_house_rules" value="1" <?=(int)($checkin['consent_house_rules']??0)===1?'checked':''?> required> <?=e($L['rules'])?></label><label class="span-2"><?=e($L['signature'])?><input name="signature_name" value="<?=e((string)($checkin['signature_name'] ?? $booking['guest_name'] ?? ''))?>"></label></div></section>
<div class="actions"><button class="btn primary" type="submit">💾 <?=e($L['save'])?></button><a class="btn" href="<?=e($customerHref)?>">← <?=e($L['back'])?></a></div></form>
<template id="travellerTemplate"><?=str_replace(['__IDX__','&lt;?'], ['__IDX__','&lt;?'], traveller_row_html([], 999, $L))?></template>
<script>
(function(){
  const rows=document.getElementById('travellerRows');
  const btn=document.getElementById('addTraveller');
  btn?.addEventListener('click',()=>{
    const idx=rows.querySelectorAll('[data-traveller-row]').length;
    const html=document.getElementById('travellerTemplate').innerHTML.replaceAll('[999]', '['+idx+']').replace('Person 1000','Person '+(idx+1));
    const wrap=document.createElement('div');wrap.innerHTML=html;rows.appendChild(wrap.firstElementChild);
  });
})();
</script>
<?php endif; ?></main></body></html>
