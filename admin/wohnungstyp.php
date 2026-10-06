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
echo PublicSiteRenderer::head($language, $title, $description);
echo PublicSiteRenderer::header($language, 'wohnungstypen');
?>
<main class="site-main">
<section class="site-type-detail-hero v227"><div class="site-container"><a class="site-back-link" href="seite.php?slug=wohnungstypen&amp;lang=<?=e($language)?>">← <?=e($labels['all_types'])?></a><div class="site-type-detail-title v227"><div><span class="site-eyebrow"><?=e((string)$type['code'])?></span><h1><?=e((string)$type['public_name'])?></h1><?php if($lead !== ''):?><p class="site-type-detail-lead"><?=e($lead)?></p><?php endif;?><div class="site-type-detail-facts"><span>👥 <?=e((string)$type['standard_occupancy'])?>–<?=e((string)$type['max_occupancy'])?></span><span>🛏 <?=e((string)$type['bedrooms'])?> <?=e($labels['bedrooms'])?></span><span>🛌 <?=e((string)$type['beds'])?> <?=e($labels['beds'])?></span><?php if(!empty($type['living_area'])):?><span>📐 <?=e((string)$type['living_area'])?> m²</span><?php endif;?></div></div><a class="site-btn primary" href="index.php?lang=<?=e($language)?>#booking-search"><?=e($labels['search'])?></a></div></div></section>
<?php if($images):?>
<section class="site-section compact"><div class="site-container"><?php if($galleryLayout === 'classic'): ?><div class="site-type-detail-gallery"><?php foreach(array_slice($images,0,5) as $index=>$image):?><button type="button" class="<?=!$index?'featured':''?>" data-site-gallery-src="<?=e((string)$image['file_path'])?>" data-site-gallery-alt="<?=e((string)$image['alt_text'])?>"><img src="<?=e((string)($image['thumb_path'] ?: $image['file_path']))?>" alt="<?=e((string)$image['alt_text'])?>" loading="<?=$index===0?'eager':'lazy'?>"></button><?php endforeach;?></div><?php else: ?><div class="site-detail-gallery-v227"><button type="button" class="site-detail-main-image" data-site-gallery-src="<?=e((string)$images[0]['file_path'])?>" data-site-gallery-alt="<?=e((string)$images[0]['alt_text'])?>" data-site-detail-main><img src="<?=e((string)$images[0]['file_path'])?>" alt="<?=e((string)$images[0]['alt_text'])?>" loading="eager"></button><div class="site-detail-thumbs"><?php foreach($images as $index=>$image):?><button type="button" class="<?=!$index?'active':''?>" data-site-detail-thumb data-site-gallery-src="<?=e((string)$image['file_path'])?>" data-site-gallery-alt="<?=e((string)$image['alt_text'])?>"><img src="<?=e((string)($image['thumb_path'] ?: $image['file_path']))?>" alt="<?=e((string)$image['alt_text'])?>" loading="lazy"></button><?php endforeach;?></div></div><?php endif; ?></div></section>
<?php endif;?>
<section class="site-section"><div class="site-container site-type-detail-layout v227"><article class="site-prose"><h2><?=e((string)$type['public_name'])?></h2><?=PublicSiteService::sanitizeRich((string)$type['public_description_html'])?><h2><?=e($labels['cancellation'])?></h2><p><?=e((string)$type['cancellation_text'])?></p><?php if(trim((string)$type['request_hint']) !== ''):?><div class="site-note"><?=e((string)$type['request_hint'])?></div><?php endif;?></article><aside class="site-type-detail-aside v227"><div class="site-detail-cta-box"><b><?=e($labels['request'])?></b><p><?=e($labels['nonbinding'])?></p></div><h2><?=e($labels['amenities'])?></h2><div class="site-detail-amenities"><?php foreach($type['amenities'] as $amenity):?><span><i><?=e((string)$amenity['icon'])?></i><?=e((string)$amenity['label'])?></span><?php endforeach;?></div><a class="site-btn primary full" href="index.php?lang=<?=e($language)?>#booking-search"><?=e($labels['request'])?></a><p class="site-small-note"><?=e($labels['nonbinding'])?></p></aside></div></section>
</main>
<div class="site-lightbox" id="siteLightbox" hidden><button type="button" data-lightbox-close>×</button><img alt=""></div>
<?=PublicSiteRenderer::footer($language)?>
</body></html>
