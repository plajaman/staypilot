<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';
$language=PublicSiteService::language((string)($_GET['lang']??setting('public_booking_default_language','de')));
$design=PublicSiteService::design();
$page=PublicSiteService::home($language,false);
if(!$page){http_response_code(503);echo 'Die öffentliche Startseite ist noch nicht veröffentlicht.';exit;}
$translation=$page['translation']??[];$propertyName=(string)setting('property_name','Meine Ferienwohnungen');
$title=trim((string)($translation['seo_title']??''))?:($page['title'].' – '.$propertyName);
$description=trim((string)($translation['seo_description']??''))?:($propertyName.' – Verfügbarkeit prüfen und unverbindlich anfragen.');
$hasBooking=PublicSiteRenderer::hasBookingSearch($page);
echo PublicSiteRenderer::head($language,$title,$description);
echo PublicSiteRenderer::header($language,(string)$page['slug']);
echo '<main class="site-main">'.PublicSiteRenderer::renderBlocks($page,$language).'</main>';
echo PublicSiteRenderer::footer($language);
if($hasBooking): 
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
<script>window.PUBLIC_APP={api:'api/public.php',enabled:<?=setting('public_booking_enabled',true)?'true':'false'?>,currency:<?=json_encode(setting('currency','EUR'))?>,propertyName:<?=json_encode($propertyName,JSON_UNESCAPED_UNICODE)?>,defaultLanguage:<?=json_encode($language)?>,touristTaxMinAge:<?=json_encode((int)setting('offer_tourist_tax_min_age',16))?>,copy:<?=json_encode(PublicSiteService::labels($language),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,inquirySettings:<?=json_encode($inquirySettings,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>};</script>
<script src="assets/public.js?v=<?=e((string)(config()['app_version']??'2.2.0'))?>"></script>
<?php endif; ?>
</body></html>
