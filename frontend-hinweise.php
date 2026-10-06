<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';
$language = PublicSiteService::language((string)($_GET['lang'] ?? setting('public_booking_default_language','de')));
$slug = trim((string)($_GET['slug'] ?? ''));
$map = ['impressum'=>'Impressum','datenschutz'=>'Datenschutz','bedingungen'=>'Bedingungen'];
if (!isset($map[$slug])) { http_response_code(404); echo 'Seite nicht gefunden'; exit; }
$page = PublicSiteService::pageBySlug($slug,$language,false);
if ($page) { header('Location: seite.php?slug='.rawurlencode($slug).'&lang='.rawurlencode($language)); exit; }
$property = (string)setting('property_name','StayPilot');
echo PublicSiteRenderer::head($language,$map[$slug].' – '.$property,'');
echo PublicSiteRenderer::header($language,$slug);
echo '<main class="site-main"><section class="site-section"><div class="site-container site-prose"><h1>'.e($map[$slug]).'</h1><div class="alert warning">Diese Seite ist noch nicht im Frontend-Editor veröffentlicht. Bitte im Adminbereich als öffentliche Seite anlegen oder veröffentlichen.</div></div></section></main>';
echo PublicSiteRenderer::footer($language).'</body></html>';
