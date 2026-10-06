<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';

$language = PublicSiteService::language((string)($_GET['lang'] ?? setting('public_booking_default_language', 'de')));
$property = (string)setting('property_name', 'Meine Ferienwohnungen');

echo PublicSiteRenderer::head($language, 'Cookie-Center - '.$property, 'Cookie-Einstellungen verwalten.');
echo PublicSiteRenderer::header($language, 'cookie-center');
?>
<main class="site-main">
<section class="site-section">
  <div class="site-container cookie-page">
    <span class="site-eyebrow">Datenschutz</span>
    <h1>Cookie-Center</h1>
    <p>Hier koennen Sie festlegen, welche Cookies und aehnlichen Technologien auf dieser Webseite verwendet werden duerfen. Notwendige Cookies sind fuer Betrieb, Sicherheit und Ihre gewaehlten Einstellungen erforderlich.</p>

    <div class="cookie-page-actions">
      <button class="site-btn primary" type="button" data-cookie-open>Cookie-Einstellungen oeffnen</button>
      <button class="site-btn secondary" type="button" data-cookie-accept-all>Alle akzeptieren</button>
      <button class="site-btn secondary" type="button" data-cookie-necessary>Nur notwendige Cookies</button>
    </div>

    <div class="cookie-info-grid">
      <article><h2>Notwendig</h2><p>Erforderlich fuer Sprache, Sicherheit, Formularfunktion und Ihre Cookie-Auswahl. Diese Kategorie kann nicht deaktiviert werden.</p></article>
      <article><h2>Komfort</h2><p>Speichert nuetzliche Einstellungen, damit die Webseite bei wiederholten Besuchen angenehmer nutzbar ist.</p></article>
      <article><h2>Statistik</h2><p>Erlaubt spaeter anonymisierte Auswertungen, um Inhalte und Buchungsablauf zu verbessern.</p></article>
      <article><h2>Marketing</h2><p>Reserviert fuer optionale externe Inhalte oder Kampagnenmessung. Ohne Zustimmung bleiben solche Skripte deaktiviert.</p></article>
    </div>
  </div>
</section>
</main>
<?=PublicSiteRenderer::footer($language)?>
</body></html>
