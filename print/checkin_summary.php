<?php
declare(strict_types=1);
require_once __DIR__.'/_print_common.php';
$id = (int)($_GET['booking_id'] ?? 0);
$booking = CheckinDocumentService::bookingRow($id);
$bundle = CheckinService::bundle($id);
$s = $bundle['checkin_summary'];
$c = $bundle['checkin'];
print_header('Check-in-Zusammenfassung', 'Buchung ' . (string)$booking['reference'] . ' · ' . (string)$booking['arrival'] . ' bis ' . (string)$booking['departure']);
?>
<div class="summary"><span>Status: <b><?=e((string)$s['status'])?></b></span><span>Fehlend: <b><?=e((string)(int)$s['missing_fields'])?></b></span><span>Reisende: <b><?=e((string)(int)$s['traveller_count'])?></b></span><span>Kinder: <b><?=e((string)(int)$s['minor_count'])?></b></span></div>
<div class="two"><div class="fieldbox"><b>Gast</b><?=e((string)$booking['guest_name'])?><br><?=e((string)$booking['guest_email'])?> · <?=e((string)$booking['guest_phone'])?></div><div class="fieldbox"><b>Wohnung</b><?=e((string)($booking['apartment_code'] ?: '–'))?> · <?=e((string)($booking['apartment_name'] ?: $booking['apartment_type_name']))?></div><div class="fieldbox"><b>Anreisezeit / Kennzeichen</b><?=e(substr((string)($c['planned_arrival_time'] ?? $booking['planned_arrival_time'] ?? ''),0,5) ?: '–')?> · <?=e((string)($c['vehicle_plate'] ?? $booking['vehicle_plate'] ?? '–'))?></div><div class="fieldbox"><b>Geprüft</b><?=e((string)($c['reviewed_at'] ?? '–'))?></div></div>
<?php if((int)$s['missing_fields']>0): ?><div class="legal-note"><b>Fehlende Angaben</b><ul><?php foreach($s['missing_details'] as $m): ?><li><?=e((string)$m)?></li><?php endforeach; ?></ul></div><?php else: ?><div class="legal-note">Keine fehlenden Pflichtangaben erkannt.</div><?php endif; ?>
<h2>Reisende Personen</h2><table><thead><tr><th>#</th><th>Name</th><th>Geburtsdatum</th><th>Nationalität</th><th>Dokument</th><th>Adresse</th><th>Kind</th></tr></thead><tbody><?php foreach(($bundle['travellers'] ?? []) as $i=>$t): ?><tr><td><?=($i+1)?></td><td><?=((int)($t['is_primary']??0)?'⭐ ':'')?><?=e(trim((string)$t['first_name'].' '.(string)$t['last_name'].' '.(string)($t['second_last_name']??'')))?></td><td><?=e((string)($t['date_of_birth']??''))?></td><td><?=e((string)($t['nationality']??''))?></td><td><?=e((string)($t['document_type']??''))?> <?=e((string)($t['document_number']??''))?></td><td><?=e((string)($t['address']??''))?>, <?=e((string)($t['postal_code']??''))?> <?=e((string)($t['city']??''))?></td><td><?=((int)($t['minor']??0)?'Ja':'Nein')?></td></tr><?php endforeach; if(empty($bundle['travellers'])): ?><tr><td colspan="7">Noch keine Reisenden erfasst.</td></tr><?php endif; ?></tbody></table>
<div class="legal-note"><b>Hinweise Gast</b><br><?=nl2br(e((string)($c['special_requests'] ?? $booking['special_requests'] ?? $booking['guest_request'] ?? '')))?></div>
<?php print_footer();
