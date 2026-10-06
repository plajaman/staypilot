<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';
$language = PublicSiteService::language((string)($_GET['lang'] ?? setting('public_booking_default_language','de')));
$property = (string)setting('property_name','Meine Ferienwohnungen');
$labels = PublicSiteService::labels($language);
$design = PublicSiteService::design();
$title = ($labels['public_booking_page_title'] ?? 'Buchung anfragen') . ' – ' . $property;
$description = $labels['public_booking_page_description'] ?? 'Prüfen Sie Verfügbarkeit und senden Sie eine unverbindliche Buchungsanfrage.';
$page = [
    'blocks' => [
        ['block_type'=>'hero','active'=>1,'content'=>[
            'eyebrow'=>$labels['public_booking_eyebrow'] ?? 'Direkte Anfrage',
            'title'=>$labels['public_booking_page_title'] ?? 'Buchung anfragen',
            'subtitle'=>$labels['public_booking_page_description'] ?? 'Prüfen Sie Verfügbarkeit, Preise und passende Wohnungstypen. Die konkrete Wohnung wird intern zugewiesen.',
            'button_label'=>$labels['search'] ?? 'Verfügbarkeit prüfen',
            'button_url'=>'#booking-search',
        ],'settings'=>['height'=>'small','alignment'=>'left']],
        ['block_type'=>'booking_search','active'=>1,'content'=>[
            'title'=>$labels['public_booking_search_title'] ?? 'Verfügbarkeit und Preis prüfen',
            'subtitle'=>$labels['public_booking_search_subtitle'] ?? 'Fehlen Saisonpreise, zeigt StayPilot eine klare Meldung statt falscher 0-Euro-Preise.',
        ],'settings'=>[]],
        ['block_type'=>'rich_text','active'=>1,'content'=>['title'=>'Typen-Belegungsplan','subtitle'=>'Kalender mit frei / gebucht / geschlossen pro Wohnungstyp','content_html'=>'<p>Sie möchten erst sehen, welche Wohnungstypen in welchem Zeitraum frei sind? Öffnen Sie den Typen-Belegungsplan und starten Sie dort direkt Ihre Anfrage.</p>','button_label'=>'Typen-Belegungsplan öffnen','button_url'=>'typen-belegung.php?lang={$language}'], 'settings'=>['button_style'=>'primary']],
        ['block_type'=>'type_grid','active'=>1,'content'=>[
            'title'=>$labels['all_types'] ?? 'Alle Wohnungstypen',
            'subtitle'=>$labels['public_type_grid_subtitle'] ?? 'Vergleichen Sie die verfügbaren Typen, Bilder, Ausstattung und Belegung.',
        ],'settings'=>['limit'=>0,'show_description'=>1,'show_amenities'=>1]],
    ],
];
echo PublicSiteRenderer::head($language,$title,$description);
echo PublicSiteRenderer::header($language,'buchung');
echo '<main class="site-main">'.PublicSiteRenderer::renderBlocks($page,$language).'</main>';
echo PublicSiteRenderer::footer($language);

$inquirySettings = [
    'show_summary'=>(bool)($design['inquiry_show_summary'] ?? 1),
    'show_top_notice'=>(bool)($design['inquiry_show_top_notice'] ?? 1),
    'phone_required'=>(bool)($design['inquiry_phone_required'] ?? 0),
    'country_required'=>(bool)($design['inquiry_country_required'] ?? 0),
    'show_breakfast'=>(bool)($design['inquiry_show_breakfast'] ?? 1),
    'show_half_board'=>(bool)($design['inquiry_show_half_board'] ?? 1),
    'show_contact_preference'=>(bool)($design['inquiry_show_contact_preference'] ?? 1),
    'show_arrival_time'=>(bool)($design['inquiry_show_arrival_time'] ?? 1),
    'show_location_request'=>(bool)($design['inquiry_show_location_request'] ?? 1),
    'show_special_occasion'=>(bool)($design['inquiry_show_special_occasion'] ?? 1),
    'privacy_required'=>(bool)($design['inquiry_privacy_required'] ?? 1),
    'marketing_consent'=>(bool)($design['inquiry_marketing_consent'] ?? 0),
];
?>
<div id="modalRoot"></div><div class="toast-wrap" id="toastRoot"></div>
<script>window.PUBLIC_APP={api:'api/public.php',enabled:<?=setting('public_booking_enabled',true)?'true':'false'?>,currency:<?=json_encode(setting('currency','EUR'))?>,propertyName:<?=json_encode($property,JSON_UNESCAPED_UNICODE)?>,defaultLanguage:<?=json_encode($language)?>,touristTaxMinAge:<?=json_encode((int)setting('offer_tourist_tax_min_age',16))?>,copy:<?=json_encode($labels,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,inquirySettings:<?=json_encode($inquirySettings,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>};</script>
<script src="assets/public.js?v=<?=e((string)(config()['app_version']??'2.3.6.27'))?>"></script>
</body></html>
