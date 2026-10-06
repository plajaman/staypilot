<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = Auth::requireLogin();
if (in_array((string)($user['role'] ?? ''), ['housekeeping','housekeeping_manager'], true)) {
    Auth::redirectToLanding($user, '../');
}
$propertyName = (string)setting('property_name','Meine Ferienwohnungen');
$accent = (string)setting('accent_color','#2563eb');
$version = (string)(config()['app_version'] ?? '2.2.0');
$docsVersion = (string)(@filemtime(__DIR__ . '/../assets/admin-v236-docs.js') ?: $version);
$role = (string)($user['role'] ?? 'readonly');
$canBook = Auth::canForUser($user, 'bookings_manage') || in_array($role, ['admin','manager','reception'], true);
$canOperate = Auth::canForUser($user, 'housekeeping_view') || in_array($role, ['admin','manager','reception','housekeeping'], true);
$canManageMaster = Auth::canForUser($user, 'masterdata_manage') || in_array($role, ['admin','manager'], true);
$canReception = Auth::canForUser($user, 'guests_view') || Auth::canForUser($user, 'bookings_view') || in_array($role, ['admin','manager','reception'], true);
$canSystem = Auth::canForUser($user, 'system_view') || in_array($role, ['admin','manager'], true);
$canWebsite = Auth::canForUser($user, 'website_view') || Auth::canForUser($user, 'website_manage') || in_array($role, ['admin','manager'], true);
$canOffers = Auth::canForUser($user, 'offers_view') || in_array($role, ['admin','manager','reception','readonly'], true);
$canBilling = Auth::canForUser($user, 'billing_view') || in_array($role, ['admin','manager','reception','readonly'], true);
$canHousekeeping = Auth::canForUser($user, 'housekeeping_view') || in_array($role, ['admin','manager','reception'], true);
$canHousekeepingManage = Auth::canForUser($user, 'housekeeping_manage') || in_array($role, ['admin','manager'], true);
$canHousekeepingRelease = Auth::canForUser($user, 'housekeeping_release') || in_array($role, ['admin','manager','reception'], true);
$canMeals = Auth::canForUser($user, 'meals_manage') || $role !== 'housekeeping';
$canCommunications = Auth::canForUser($user, 'communications_view') || in_array($role, ['admin','manager'], true);
$canUsers = Auth::canForUser($user, 'users_manage') || $role === 'admin';
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="<?=e($accent)?>"><title>StayPilot Admin</title><link rel="stylesheet" href="../assets/app.css?v=<?=e($version)?>"></head><body style="--accent:<?=e($accent)?>">
<div class="app-shell"><aside class="sidebar" id="sidebar"><div class="brand"><div class="brand-logo">🏡</div><div><b>StayPilot</b><small id="brandProperty"><?=e($propertyName)?></small></div></div>
<div class="nav-title">Verwaltung</div><nav class="nav" id="nav">
<?php if ($role !== 'housekeeping'): ?><button type="button" data-page="dashboard" class="active"><span class="ico">📊</span>Dashboard</button>
<button type="button" data-page="statistics"><span class="ico">📈</span>Statistik</button><?php else: ?><button type="button" data-page="housekeeping" class="active"><span class="ico">🧹</span>Meine Aufgaben</button><?php endif; ?>
<?php if ($role !== 'housekeeping'): ?><button type="button" data-page="calendar"><span class="ico">📅</span>Belegung <span class="badge" id="navWaiting">0</span></button><?php else: ?><span id="navWaiting" hidden>0</span><?php endif; ?>
<?php if ($role !== 'housekeeping'): ?><button type="button" data-page="bookings"><span class="ico">🧾</span>Buchungen</button><?php endif; ?>
<?php if ($canOffers): ?><button type="button" data-page="offers"><span class="ico">📄</span>Angebote <span class="badge" id="navAcceptedOffers" hidden>0</span></button><?php endif; ?>
<?php if ($canBilling): ?><button type="button" data-page="billing"><span class="ico">💳</span>Rechnungen & Zahlungen</button><?php endif; ?>
<?php if ($canWebsite): ?><div class="nav-title">Website</div><button type="button" data-page="website"><span class="ico">🌐</span>Webseite & Buchungsseite</button><?php endif; ?>
<?php if ($canManageMaster): ?><div class="nav-title">Stammdaten</div>
<button type="button" data-page="houses"><span class="ico">🏢</span>Häuser</button>
<button type="button" data-page="apartment_types"><span class="ico">🧩</span>Wohnungstypen</button>
<button type="button" data-page="apartments"><span class="ico">🏠</span>Apartments</button><?php endif; ?>
<?php if ($canReception): ?><button type="button" data-page="guests"><span class="ico">👥</span>Gäste</button><?php endif; ?>
<div class="nav-title">Betrieb</div>
<?php if ($role !== 'housekeeping'): ?><button type="button" data-page="tasks"><span class="ico">✅</span>Aufgaben & Kalender</button><?php endif; ?>
<?php if ($canHousekeeping): ?><button type="button" data-page="housekeeping"><span class="ico">🧹</span>Putzplan & Aufgaben</button><?php endif; ?><?php if ($canHousekeepingManage): ?><button type="button" data-page="housekeeping_teams"><span class="ico">👷</span>Mitarbeiter & Teams</button><?php endif; ?>
<?php if ($canHousekeepingRelease): ?><button type="button" data-page="housekeeping_release"><span class="ico">✅</span>Kontrolle & Freigabe <span class="badge" id="releaseBadge">0</span></button><?php endif; ?>
<?php if ($canWebsite): ?><button type="button" data-page="guest_portal_contents"><span class="ico">📱</span>Gäste-Informationen</button><?php endif; ?>
<?php if ($canWebsite): ?><button type="button" data-page="documents"><span class="ico">📚</span>Dokumente & Vorlagen</button><?php endif; ?>
<?php if (in_array($role,['admin','manager','reception'],true)): ?><button type="button" data-page="portal_links"><span class="ico">🔗</span>Portale & Anmeldungen</button><?php endif; ?>
<?php if ($canMeals): ?><button type="button" data-page="meals"><span class="ico">🍽️</span>Frühstück & HP</button><?php endif; ?>
<?php if ($canReception): ?><button type="button" data-page="police"><span class="ico">🛂</span>Meldeliste</button><button type="button" data-page="checkin"><span class="ico">📝</span>Online-Check-in</button><?php endif; ?>
<?php if ($canManageMaster): ?><button type="button" data-page="prices"><span class="ico">💶</span>Preise & Saisons</button>
<button type="button" data-page="price_overview"><span class="ico">📋</span>Preisübersicht</button><?php endif; ?>
<div class="nav-title">System</div>
<?php if ($canSystem): ?><button type="button" data-page="csv"><span class="ico">📥</span>CSV-Import</button><?php endif; ?><?php if ($canCommunications): ?><button type="button" data-page="communications"><span class="ico">✉️</span>Kommunikationscenter</button><?php endif; ?>
<?php if ($role === 'admin'): ?><button type="button" data-page="integrations"><span class="ico">🔌</span>Schnittstellen</button><?php endif; ?>
<?php if ($role === 'admin'): ?><button type="button" data-page="audit_log"><span class="ico">📜</span>Aktivitätsprotokoll</button><?php endif; ?>
<?php if ($role === 'admin' || $role === 'manager'): ?><button type="button" data-page="delete_center"><span class="ico">🗑️</span>Löschcenter</button><?php endif; ?>
<?php if ($canSystem): ?><button type="button" data-page="system"><span class="ico">🛠️</span>Systemdiagnose</button><?php endif; ?>
<?php if ($canUsers): ?><button type="button" data-page="users"><span class="ico">🔐</span>Benutzer & Rollen</button><button type="button" data-page="access_rights"><span class="ico">🛡️</span>Rollen & Rechte</button><?php endif; ?>
<button type="button" data-page="settings"><span class="ico">⚙️</span>Konto & Einstellungen</button>
<button type="button" data-page="help"><span class="ico">❓</span>Hilfe & Abläufe</button>
</nav><div class="sidebar-bottom"><div class="user-box"><b><?=e($user['name'])?></b><br><?=e($user['email'])?><br><a href="../logout.php?portal=admin">Abmelden</a><div class="sp-sidebar-foot"><a href="https://jmg-design.de" target="_blank" rel="noopener">by JMG-design.de</a><span>Version <?=e($version)?></span></div></div></div></aside>
<main class="main"><header class="topbar"><button class="icon-btn mobile-menu" id="mobileMenu" type="button" title="Menü ein- oder ausblenden" aria-label="Menü ein- oder ausblenden" aria-controls="sidebar">☰</button><div><h1 id="pageTitle">Dashboard</h1><p id="pageSubtitle">Auslastung, Anreisen und Aufgaben im Überblick</p></div><div class="top-actions"><button class="btn soft" type="button" id="spAdminSearch" title="Einstellungen und Seiten durchsuchen (Strg+K)">🔍 Suche</button><a class="btn hide-mobile" href="../index.php" target="_blank">🌐 Buchungsseite</a><?php if ($canBook): ?><button type="button" class="btn primary" id="quickBooking">＋ Buchung</button><?php endif; ?></div><div class="admin-app-switcher"><a href="https://quartier-schweizer.de/">Vermietung</a><a href="https://quartier-schweizer.de/nebenkosten/">Nebenkosten</a></div></header><section class="content" id="content"><div class="card">Anwendung wird geladen …</div></section></main></div>
<div id="modalRoot"></div><div class="toast-wrap" id="toastRoot"></div>
<script>window.STAYPILOT={api:'api.php',csrf:'<?=e(csrf_token())?>',propertyName:<?=json_encode($propertyName,JSON_UNESCAPED_UNICODE)?>,today:'<?=date('Y-m-d')?>',user:<?=json_encode($user,JSON_UNESCAPED_UNICODE)?>,version:<?=json_encode($version)?>};</script><script charset="utf-8" src="../assets/admin-core.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v205.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v207.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v208.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v209.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v210.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v213.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v214.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v216.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v217.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v218.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v219.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v220.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v223.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v224.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v225.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v226.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v228.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v229.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v230.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v232.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v233.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v235.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v236-docs.js?v=<?=e($docsVersion)?>"></script><script charset="utf-8" src="../assets/admin-v236-booking-offer-flow.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v236-calendar-apartments.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v236-calendar-navigation.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v236-housekeeping-studio.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v236-checkin-studio.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v236-communication-studio.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v236-task-center.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v236-click-consistency.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v236-nav-login.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v236-delete-center.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v236-studio-pro.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v236-studio-page-actions.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v236-website-templates.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v236-product-readiness-actions.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-v237-smart-arrival.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-search.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-audit-log.js?v=<?=e($version)?>"></script><script charset="utf-8" src="../assets/admin-help-tips.js?v=<?=e($version)?>"></script></body></html>
