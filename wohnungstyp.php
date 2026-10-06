<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';
$language = PublicSiteService::language((string)($_GET['lang'] ?? setting('public_booking_default_language', 'de')));
$id = (int)($_GET['id'] ?? 0);
$type = PublicSiteService::typeDetail($id, $language);
$labels = PublicSiteService::labels($language);
$property = (string)setting('property_name', 'Meine Ferienwohnungen');
if (!$type) {
    http_response_code(404);
    echo PublicSiteRenderer::head($language, 'Wohnungstyp nicht gefunden – '.$property, '');
    echo PublicSiteRenderer::header($language);
    echo '<main class="site-main"><section class="site-section"><div class="site-container"><div class="alert warning">'.e($labels['type_not_found']).'</div></div></section></main>'.PublicSiteRenderer::footer($language).'</body></html>';
    exit;
}
$title = trim((string)$type['seo_title']) ?: ($type['public_name'].' – '.$property);
$description = trim((string)$type['seo_description']) ?: mb_substr(trim(strip_tags((string)$type['public_description_html'])), 0, 300);
$images = $type['images'] ?? [];
$design = PublicSiteService::design();
$galleryLayout = in_array((string)($design['type_detail_gallery_layout'] ?? 'magazine'), ['magazine','classic'], true) ? (string)$design['type_detail_gallery_layout'] : 'magazine';
$lead = mb_substr(trim(strip_tags((string)$type['public_description_html'])), 0, 240);
if (mb_strlen($lead) >= 240) $lead .= '…';
$currency = (string)setting('offer_currency', setting('currency', 'EUR'));
$priceValue = (float)($type['standard_price'] ?? 0);
$priceLabel = $priceValue > 0 ? 'ab '.number_format($priceValue, 2, ',', '.').' '.$currency.' / Nacht' : ((string)($labels['price_request'] ?? 'Preis auf Anfrage'));
$bookingUrl = 'buchung.php?lang='.rawurlencode($language).'#booking-search';
$calendarUrl = 'typen-belegung.php?lang='.rawurlencode($language).'&type_id='.(int)$type['id'];
$copy = $language === 'de' ? [
    'availability_title' => 'Verfügbarkeitsvorschau',
    'availability_help' => 'Schnelle Übersicht über freie Tage dieses Wohnungstyps. Die konkrete Apartmentnummer wird intern zugewiesen.',
    'arrival' => 'Start',
    'days' => 'Tage',
    'loading' => 'Verfügbarkeit wird geladen …',
    'empty' => 'Derzeit liegen keine Verfügbarkeitsdaten vor.',
    'free_short' => 'frei',
    'free_days' => 'Tage mit Verfügbarkeit',
    'min_stay' => 'Mindestaufenthalt',
    'calendar_label' => 'Belegungsplan öffnen',
    'request_label' => 'Diesen Typ anfragen',
    'highlights' => 'Kurzübersicht',
    'request_box_title' => 'Jetzt unverbindlich anfragen',
    'request_box_text' => 'Sie wählen hier den Wohnungstyp. Die endgültige Wohnungszuteilung erfolgt später intern.',
    'caption_placeholder' => 'Weitere Bildinformationen erscheinen hier, sobald für das Bild ein Titel oder eine Kurzbeschreibung hinterlegt wurde.',
    'share_title' => 'Teilen / merken',
    'copy_link' => 'Link kopieren',
    'print_page' => 'Seite drucken',
] : [
    'availability_title' => 'Availability preview',
    'availability_help' => 'Quick overview of available days for this accommodation type. A specific apartment number is assigned internally later.',
    'arrival' => 'Start',
    'days' => 'Days',
    'loading' => 'Loading availability …',
    'empty' => 'No availability data is currently available.',
    'free_short' => 'free',
    'free_days' => 'days with availability',
    'min_stay' => 'Minimum stay',
    'calendar_label' => 'Open availability plan',
    'request_label' => 'Request this type',
    'highlights' => 'Quick facts',
    'request_box_title' => 'Send a non-binding request',
    'request_box_text' => 'You select the accommodation type here. The final apartment allocation is handled internally later.',
    'caption_placeholder' => 'Additional image information will appear here when a title or caption has been stored for the selected image.',
    'share_title' => 'Share / save',
    'copy_link' => 'Copy link',
    'print_page' => 'Print page',
];
$mainImage = $images[0] ?? null;
echo PublicSiteRenderer::head($language, $title, $description);
echo PublicSiteRenderer::header($language, 'wohnungstypen');
?>
<main class="site-main">
<section class="site-type-detail-hero v227"><div class="site-container"><a class="site-back-link" href="seite.php?slug=wohnungstypen&amp;lang=<?=e($language)?>">← <?=e($labels['all_types'])?></a><div class="site-type-detail-title v227"><div><span class="site-eyebrow"><?=e((string)$type['code'])?></span><h1><?=e((string)$type['public_name'])?></h1><?php if($lead !== ''):?><p class="site-type-detail-lead"><?=e($lead)?></p><?php endif;?><div class="site-type-detail-facts"><span>👥 <?=e((string)$type['standard_occupancy'])?>–<?=e((string)$type['max_occupancy'])?></span><span>🛏 <?=e((string)$type['bedrooms'])?> <?=e($labels['bedrooms'])?></span><span>🛌 <?=e((string)$type['beds'])?> <?=e($labels['beds'])?></span><?php if(!empty($type['living_area'])):?><span>📐 <?=e((string)$type['living_area'])?> m²</span><?php endif;?><span>💶 <?=e($priceLabel)?></span></div></div><div class="site-hero-cta-stack"><a class="site-btn primary" href="<?=e($bookingUrl)?>"><?=e($copy['request_label'])?></a><a class="site-btn secondary" href="<?=e($calendarUrl)?>"><?=e($copy['calendar_label'])?></a></div></div></div></section>
<?php if($images):?>
<section class="site-section compact"><div class="site-container"><?php if($galleryLayout === 'classic'): ?><div class="site-type-detail-gallery"><?php foreach(array_slice($images,0,5) as $index=>$image):?><button type="button" class="<?=!$index?'featured':''?>" data-site-gallery-src="<?=e((string)$image['file_path'])?>" data-site-gallery-alt="<?=e((string)$image['alt_text'])?>"><img src="<?=e((string)($image['thumb_path'] ?: $image['file_path']))?>" alt="<?=e((string)$image['alt_text'])?>" loading="<?=$index===0?'eager':'lazy'?>"></button><?php endforeach;?></div><?php else: ?><div class="site-detail-gallery-v227"><button type="button" class="site-detail-main-image" data-site-gallery-src="<?=e((string)$mainImage['file_path'])?>" data-site-gallery-alt="<?=e((string)$mainImage['alt_text'])?>" data-site-detail-main><img src="<?=e((string)$mainImage['file_path'])?>" alt="<?=e((string)$mainImage['alt_text'])?>" loading="eager"></button><div class="site-detail-thumbs"><?php foreach($images as $index=>$image):?><button type="button" class="<?=!$index?'active':''?>" data-site-detail-thumb data-site-gallery-src="<?=e((string)$image['file_path'])?>" data-site-gallery-alt="<?=e((string)$image['alt_text'])?>" data-site-title="<?=e((string)($image['title'] ?? ''))?>" data-site-caption="<?=e((string)($image['caption'] ?? ''))?>"><img src="<?=e((string)($image['thumb_path'] ?: $image['file_path']))?>" alt="<?=e((string)$image['alt_text'])?>" loading="lazy"></button><?php endforeach;?></div></div><div class="site-detail-caption" data-site-detail-caption <?=(!trim((string)($mainImage['title'] ?? '')) && !trim((string)($mainImage['caption'] ?? '')))?'hidden':''?>><b data-site-caption-title><?=e((string)($mainImage['title'] ?? ''))?></b><p data-site-caption-text><?=e((string)($mainImage['caption'] ?? $copy['caption_placeholder']))?></p></div><?php endif; ?></div></section>
<?php endif;?>
<section class="site-section"><div class="site-container site-type-detail-layout v227"><article class="site-prose"><div class="site-detail-highlight-grid"><div><small><?=e($copy['highlights'])?></small><strong><?=e($priceLabel)?></strong><span>Standardpreis laut Stammdaten</span></div><div><small><?=e($labels['regular'])?></small><strong><?=e((string)$type['standard_occupancy'])?> / <?=e((string)$type['max_occupancy'])?></strong><span>Regel- und Maximalbelegung</span></div><div><small><?=e($labels['nights'])?></small><strong><?=e((string)$type['default_min_stay'])?></strong><span>Mindestaufenthalt</span></div></div><h2><?=e((string)$type['public_name'])?></h2><?=PublicSiteService::sanitizeRich((string)$type['public_description_html'])?><h2><?=e($labels['cancellation'])?></h2><p><?=e((string)$type['cancellation_text'])?></p><?php if(trim((string)$type['request_hint']) !== ''):?><div class="site-note"><?=e((string)$type['request_hint'])?></div><?php endif;?></article><aside class="site-type-detail-aside v227"><div class="site-detail-cta-box"><b><?=e($copy['request_box_title'])?></b><p><?=e($copy['request_box_text'])?></p></div><h2><?=e($labels['amenities'])?></h2><div class="site-detail-amenities"><?php foreach($type['amenities'] as $amenity):?><span><i><?=e((string)$amenity['icon'])?></i><?=e((string)$amenity['label'])?></span><?php endforeach;?></div><div class="site-detail-availability" data-site-type-availability data-api="api/public.php" data-language="<?=e($language)?>" data-type-id="<?=(int)$type['id']?>" data-booking-url="<?=e($bookingUrl)?>" data-calendar-url="<?=e($calendarUrl)?>" data-loading-label="<?=e($copy['loading'])?>" data-empty-label="<?=e($copy['empty'])?>" data-free-short="<?=e($copy['free_short'])?>" data-free-days-label="<?=e($copy['free_days'])?>" data-min-stay-label="<?=e($copy['min_stay'])?>" data-calendar-label="<?=e($copy['calendar_label'])?>" data-request-label="<?=e($copy['request_label'])?>"><div class="site-detail-availability-head"><b><?=e($copy['availability_title'])?></b><small><?=e($copy['availability_help'])?></small></div><div class="site-detail-availability-controls"><label><span><?=e($copy['arrival'])?></span><input type="date" name="availability_start"></label><label><span><?=e($copy['days'])?></span><select name="availability_days"><option value="14">14</option><option value="21" selected>21</option><option value="30">30</option></select></label></div><div data-site-type-availability-result></div></div><div class="site-share-box"><b><?=e($copy['share_title'])?></b><div><button class="site-btn secondary" type="button" data-copy-current-url><?=e($copy['copy_link'])?></button><button class="site-btn secondary" type="button" onclick="window.print()"><?=e($copy['print_page'])?></button></div></div><a class="site-btn primary full" href="<?=e($bookingUrl)?>"><?=e($copy['request_label'])?></a><p class="site-small-note"><?=e($labels['nonbinding'])?></p></aside></div></section>
</main>
<div class="site-lightbox" id="siteLightbox" hidden><button type="button" data-lightbox-close>×</button><img alt=""></div>
<?php
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => (string)$type['public_name'],
    'description' => $description,
    'brand' => ['@type'=>'Brand','name'=>$property],
    'category' => 'Holiday apartment',
    'image' => array_values(array_map(static fn(array $img): string => (string)$img['file_path'], $images)),
    'offers' => [
        '@type' => 'Offer',
        'priceCurrency' => $currency,
        'price' => $priceValue > 0 ? number_format($priceValue, 2, '.', '') : null,
        'availability' => 'https://schema.org/InStock',
        'url' => (isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] : ''),
    ],
];
?>
<script type="application/ld+json"><?=json_encode($jsonLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?></script>
<?=PublicSiteRenderer::footer($language)?>
</body></html>
