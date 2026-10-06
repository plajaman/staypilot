<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';
$language = PublicSiteService::language((string)($_GET['lang'] ?? setting('public_booking_default_language','de')));
$property = (string)setting('property_name','Meine Ferienwohnungen');
$labels = PublicSiteService::labels($language);
$title = 'Typen-Belegungsplan – ' . $property;
$description = 'Verfügbarkeit nach Wohnungstypen: frei, gebucht oder geschlossen. Anfrage direkt aus dem Kalender starten.';
echo PublicSiteRenderer::head($language,$title,$description);
echo PublicSiteRenderer::header($language,'typen-belegung');
?>
<main class="site-main">
<section class="site-hero small align-left"><div class="site-hero-overlay"></div><div class="site-container site-hero-content"><span class="site-eyebrow">Verfügbarkeit</span><h1>Typen-Belegungsplan</h1><p>Sehen Sie die Verfügbarkeit nach Wohnungstyp. Konkrete Apartmentnummern werden nicht veröffentlicht.</p><a class="site-btn primary" href="#type-occupancy">Plan anzeigen</a></div></section>
<section class="site-section" id="type-occupancy"><div class="site-container"><div class="site-section-head"><h2>Frei / gebucht / geschlossen</h2><p>Wählen Sie Startdatum und Zeitraum. Aus dem Typen-Kalender können Sie direkt eine unverbindliche Anfrage starten.</p></div><div class="type-calendar-controls"><div class="field"><label>Startdatum</label><input type="date" id="typeCalendarStart" value="<?=e(date('Y-m-d'))?>"></div><div class="field"><label>Zeitraum</label><select id="typeCalendarDays"><option value="30">30 Tage</option><option value="60" selected>60 Tage</option><option value="93">93 Tage</option><option value="120">120 Tage</option><option value="180">180 Tage</option></select></div><div class="field"><label>Sortierung</label><select id="typeCalendarSort"><option value="type" selected>Nach Typ</option><option value="name">Name A-Z</option><option value="free_desc">Meiste freie zuerst</option><option value="scarce">Wenig verfügbar zuerst</option><option value="price_asc">Preis aufsteigend</option><option value="price_desc">Preis absteigend</option><option value="min_stay">Mindestnächte</option></select></div><button class="site-btn primary" id="typeCalendarLoad">Belegungsplan anzeigen</button></div><div class="type-calendar-legend"><span><i class="free"></i> frei</span><span><i class="booked"></i> gebucht</span><span><i class="closed"></i> geschlossen</span></div><div id="typeCalendarMessage"></div><div id="typeCalendarResult" class="type-calendar-result"><div class="empty">Belegungsplan wird geladen …</div></div></div></section>
</main>
<div id="modalRoot"></div><div class="toast-wrap" id="toastRoot"></div>
<?=PublicSiteRenderer::footer($language)?>
<script>window.PUBLIC_APP={api:'api/public.php',enabled:<?=setting('public_booking_enabled',true)?'true':'false'?>,currency:<?=json_encode(setting('currency','EUR'))?>,propertyName:<?=json_encode($property,JSON_UNESCAPED_UNICODE)?>,defaultLanguage:<?=json_encode($language)?>,touristTaxMinAge:<?=json_encode((int)setting('offer_tourist_tax_min_age',16))?>,copy:<?=json_encode($labels,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>};</script>
<script src="assets/public.js?v=<?=e((string)(config()['app_version']??'2.3.6.35'))?>"></script>
<script src="assets/type-calendar.js?v=<?=e((string)(config()['app_version']??'2.3.6.35'))?>"></script>
</body></html>
