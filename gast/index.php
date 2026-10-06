<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/bootstrap.php';
$token=trim((string)($_GET['token']??''));
$version=(string)(config()['app_version'] ?? '2.2.0');
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta name="referrer" content="no-referrer">
  <meta name="theme-color" content="#0f766e">
  <title><?=e((string)setting('property_name','StayPilot'))?> – Bezugsbereite Wohnungen</title>
  <link rel="stylesheet" href="../assets/portal.css?v=<?=e($version)?>">
</head>
<body>
<div class="portal-shell guest-public-shell">
  <header class="portal-head">
    <div class="portal-brand"><div class="logo">🏡</div><div><b><?=e((string)setting('property_name','StayPilot'))?></b><small id="portalSubtitle">Gäste-Information</small></div></div>
    <div class="portal-actions language-switch"><button class="btn active" type="button" data-lang="de">DE</button><button class="btn" type="button" data-lang="es">ES</button><button class="btn" type="button" data-lang="en">EN</button><button class="btn hide-mobile" id="enableNotifications" type="button">🔔</button></div>
  </header>
  <main class="portal-main guest-public-main">
    <section class="portal-card guest-public-hero">
      <div class="guest-icon">✓</div>
      <h1 id="publicTitle">Bezugsbereite Wohnungen</h1>
      <p class="muted" id="publicIntro">Diese Anzeige aktualisiert sich automatisch.</p>
    </section>
    <section id="readyApartments" class="guest-ready-grid"><div class="portal-card empty">Status wird geladen …</div></section>
    <section id="guestContents" class="portal-grid guest-info-grid"></section>
    <p class="muted guest-updated" id="updatedAt"></p>
  </main>
</div>
<div class="toast-area" id="toastArea"></div>
<script>window.GUEST_PORTAL={api:'api.php',token:<?=json_encode($token)?>,pollSeconds:<?=max(10,(int)setting('guest_portal_poll_seconds',20))?>};</script>
<script src="guest.js?v=<?=e($version)?>"></script>
</body>
</html>
