<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';
$language=PublicSiteService::language((string)($_GET['lang']??setting('public_booking_default_language','de')));
$preview=normalize_bool($_GET['preview']??0);$includeDraft=false;
if($preview){$user=Auth::user();$includeDraft=$user&&in_array((string)$user['role'],['admin','manager'],true);if(!$includeDraft)$preview=false;}
$id=(int)($_GET['id']??0);$slug=trim((string)($_GET['slug']??''));$page=$id?PublicSiteService::pageById($id,$language,$includeDraft):PublicSiteService::pageBySlug($slug,$language,$includeDraft);
if(!$page){http_response_code(404);$property=(string)setting('property_name','StayPilot');echo PublicSiteRenderer::head($language,'Seite nicht gefunden – '.$property,'');echo PublicSiteRenderer::header($language);$labels=PublicSiteService::labels($language);echo '<main class="site-main"><section class="site-section"><div class="site-container"><div class="alert warning">'.e($labels['not_found']).'</div></div></section></main>';echo PublicSiteRenderer::footer($language).'</body></html>';exit;}
$translation=$page['translation']??[];$property=(string)setting('property_name','Meine Ferienwohnungen');$title=trim((string)($translation['seo_title']??''))?:($page['title'].' – '.$property);$description=trim((string)($translation['seo_description']??''));$hasBooking=PublicSiteRenderer::hasBookingSearch($page);
echo PublicSiteRenderer::head($language,$title,$description);echo PublicSiteRenderer::header($language,(string)$page['slug']);
if($preview)echo '<div class="site-preview-banner">Vorschau eines Entwurfs · Änderungen sind nur für angemeldete Administratoren sichtbar.</div>';
echo '<main class="site-main">'.PublicSiteRenderer::renderBlocks($page,$language).'</main>';echo PublicSiteRenderer::footer($language);
if($hasBooking):?><div id="modalRoot"></div><div class="toast-wrap" id="toastRoot"></div><script>window.PUBLIC_APP={api:'api/public.php',enabled:<?=setting('public_booking_enabled',true)?'true':'false'?>,currency:<?=json_encode(setting('currency','EUR'))?>,propertyName:<?=json_encode($property,JSON_UNESCAPED_UNICODE)?>,defaultLanguage:<?=json_encode($language)?>,touristTaxMinAge:<?=json_encode((int)setting('offer_tourist_tax_min_age',16))?>,copy:<?=json_encode(PublicSiteService::labels($language),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>};</script><script src="assets/public.js?v=<?=e((string)(config()['app_version']??'2.2.0'))?>"></script><?php endif;?>
</body></html>
