<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';

function sp_sitemap_url(string $path): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    $base = $host !== '' ? $scheme . '://' . $host : '';
    return htmlspecialchars($base . '/' . ltrim($path, '/'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

header('Content-Type: application/xml; charset=utf-8');
$language = PublicSiteService::language((string)setting('public_booking_default_language','de'));
$today = date('Y-m-d');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
$static = [
    ['index.php?lang='.$language, 'daily', '1.0'],
    ['buchung.php?lang='.$language, 'daily', '0.95'],
    ['typen-belegung.php?lang='.$language, 'daily', '0.90'],
    ['wohnungstypen.php?lang='.$language, 'weekly', '0.90'],
];
foreach ($static as [$loc,$freq,$priority]) {
    echo "  <url><loc>".sp_sitemap_url($loc)."</loc><lastmod>{$today}</lastmod><changefreq>{$freq}</changefreq><priority>{$priority}</priority></url>\n";
}
try {
    $stmt = db()->query("SELECT slug,updated_at FROM site_pages WHERE status='published' ORDER BY sort_order,slug");
    foreach ($stmt->fetchAll() as $page) {
        $slug = (string)($page['slug'] ?? '');
        if ($slug === '') continue;
        $last = substr((string)($page['updated_at'] ?? $today), 0, 10) ?: $today;
        echo "  <url><loc>".sp_sitemap_url("seite.php?slug=".rawurlencode($slug)."&lang=".$language)."</loc><lastmod>".htmlspecialchars($last, ENT_XML1)."</lastmod><changefreq>weekly</changefreq><priority>0.80</priority></url>\n";
    }
} catch (Throwable $e) {
    AppLogger::error($e, [], 'sitemap');
}
try {
    $stmt = db()->query("SELECT id,updated_at FROM apartment_types WHERE active=1 AND public_active=1 ORDER BY sort_order,name");
    foreach ($stmt->fetchAll() as $type) {
        $last = substr((string)($type['updated_at'] ?? $today), 0, 10) ?: $today;
        echo "  <url><loc>".sp_sitemap_url("wohnungstyp.php?id=".(int)$type['id']."&lang=".$language)."</loc><lastmod>".htmlspecialchars($last, ENT_XML1)."</lastmod><changefreq>weekly</changefreq><priority>0.85</priority></url>\n";
    }
} catch (Throwable $e) {
    AppLogger::error($e, [], 'sitemap');
}
echo '</urlset>';
