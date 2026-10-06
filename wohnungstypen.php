<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';
$language = PublicSiteService::language((string)($_GET['lang'] ?? setting('public_booking_default_language','de')));
$property = (string)setting('property_name','Meine Ferienwohnungen');
$labels = PublicSiteService::labels($language);
$title = ($labels['all_types'] ?? 'Alle Wohnungstypen') . ' – ' . $property;
$page = ['blocks'=>[
    ['block_type'=>'hero','active'=>1,'content'=>[
        'eyebrow'=>$labels['all_types'] ?? 'Wohnungstypen',
        'title'=>$labels['all_types'] ?? 'Alle Wohnungstypen',
        'subtitle'=>$labels['public_type_grid_subtitle'] ?? 'Alle Unterkunftstypen mit Bildern, Ausstattung, Belegung und Detailseiten.',
        'button_label'=>$labels['search'] ?? 'Verfügbarkeit prüfen',
        'button_url'=>'buchung.php?lang='.rawurlencode($language).'#booking-search',
    ],'settings'=>['height'=>'small','alignment'=>'left']],
    ['block_type'=>'type_grid','active'=>1,'content'=>['title'=>$labels['all_types'] ?? 'Alle Wohnungstypen','subtitle'=>$labels['public_type_grid_subtitle'] ?? 'Details ansehen oder direkt eine unverbindliche Anfrage starten.'],'settings'=>['limit'=>0,'show_description'=>1,'show_amenities'=>1]],
]];
echo PublicSiteRenderer::head($language,$title,$property);
echo PublicSiteRenderer::header($language,'wohnungstypen');
echo '<main class="site-main">'.PublicSiteRenderer::renderBlocks($page,$language).'</main>';
echo PublicSiteRenderer::footer($language);
?>
</body></html>
