<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';

$portal = Auth::normalizePortal((string)($_GET['portal'] ?? $_POST['portal'] ?? ''));
$portalInfo = Auth::portalDefinition($portal);
$loggedIn = Auth::user();
$sessionMismatch = false;

if ($loggedIn) {
    if ($portal === '') {
        Auth::redirectToLanding($loggedIn);
    }
    if (Auth::portalAllowsRole($portal, (string)$loggedIn['role'])) {
        header('Location: ' . Auth::portalPath($portal));
        exit;
    }
    $sessionMismatch = true;
}

$error='';
if (!$sessionMismatch && $_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($_SESSION['login_csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) {
        $error='Sicherheitsprüfung fehlgeschlagen. Bitte neu laden.';
    } elseif (Auth::attempt((string)($_POST['identity']??''),(string)($_POST['password']??''))) {
        if (!empty($_POST['remember_login'])) {
            $_SESSION['staypilot_remember'] = 1;
        } else {
            unset($_SESSION['staypilot_remember']);
        }
        $user = Auth::user() ?? [];
        Auth::issueSsoCookie($user['email'] ?? '');
        if ($portal !== '' && Auth::portalAllowsRole($portal, (string)($user['role'] ?? ''))) {
            header('Location: ' . Auth::portalPath($portal));
            exit;
        }
        Auth::redirectToLanding($user);
    } else {
        $error='E-Mail/Benutzername oder Passwort ist falsch.';
    }
}

$_SESSION['login_csrf'] ??= bin2hex(random_bytes(24));
$version = (string)(config()['app_version'] ?? '2.2.0');
$propertyName = (string)setting('property_name','StayPilot');
$logoUrl = trim((string)setting('logo_url',''));
if ($logoUrl !== '' && !preg_match('~^https?://~i', $logoUrl)) { $logoUrl = ltrim($logoUrl, '/'); }
$roleLabels = array_column(Auth::roleMatrix(), 'label');
$currentRoleLabel = $loggedIn ? ($roleLabels[(string)$loggedIn['role']] ?? (string)$loggedIn['role']) : '';
$logoutPortal = $portal !== '' ? '?portal=' . rawurlencode($portal) : '';
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e((string)$portalInfo['title'])?> – Anmeldung</title>
<link rel="stylesheet" href="assets/app.css?v=<?=e($version)?>">
</head>
<body class="login-body sp-v23666-login">
<main class="login-card portal-login-card sp-login-shell">
<section class="sp-login-hero">
<div class="sp-login-logo-wrap">
<?php if($logoUrl !== ''): ?><img class="sp-login-logo-img" src="<?=e($logoUrl)?>" alt="<?=e($propertyName)?> Logo"><?php else: ?><div class="sp-login-logo">🏡</div><?php endif; ?>
</div>
<p class="sp-login-kicker">StayPilot Verwaltungssystem</p>
<h1><?=e((string)$portalInfo['title'])?></h1>
<p><?=e((string)$portalInfo['subtitle'])?></p>
<div class="sp-login-meta"><span>Version <?=e($version)?></span><span><?=e($propertyName)?></span></div>
</section>
<section class="sp-login-form-panel">
<?php if($sessionMismatch): ?>
<div class="alert warning portal-session-warning">
<b>Im Browser ist bereits ein anderes Konto angemeldet.</b><br>
Aktuell: <?=e((string)$loggedIn['name'])?> · <?=e($currentRoleLabel)?>
</div>
<div class="form-stack">
<a class="btn primary block" href="<?=e(Auth::landingPath($loggedIn))?>">Zum aktuell angemeldeten Bereich</a>
<a class="btn block" href="logout.php<?=e($logoutPortal)?>">Abmelden und hier mit anderem Konto anmelden</a>
</div>
<?php else: ?>
<?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?>
<form method="post" class="form-stack sp-login-form" autocomplete="on">
<input type="hidden" name="_csrf" value="<?=e($_SESSION['login_csrf'])?>">
<input type="hidden" name="portal" value="<?=e($portal)?>">
<label>E-Mail oder Benutzername<input type="text" name="identity" value="<?=e($_POST['identity'] ?? '')?>" autocomplete="username" required autofocus placeholder="name@example.com"></label>
<label>Passwort
  <span class="sp-password-field"><input id="spLoginPassword" type="password" name="password" autocomplete="current-password" required placeholder="Passwort"><button class="btn small" type="button" id="spTogglePassword" aria-controls="spLoginPassword">Anzeigen</button></span>
</label>
<label class="sp-login-check"><input type="checkbox" name="remember_login" value="1" <?=!empty($_POST['remember_login'])?'checked':''?>> Angemeldet bleiben / längere Sitzung</label>
<button class="btn primary block" type="submit">Anmelden</button>
</form>
<div class="sp-login-help-row">
<button class="muted-link sp-text-button" type="button" id="spForgotPassword">Passwort vergessen?</button>
<a class="muted-link" href="index.php">Zur öffentlichen Buchungsseite</a>
</div>
<div class="alert warning sp-reset-hint" id="spResetHint" hidden>
<b>Passwort zurücksetzen</b><br>
Nutze das geschützte Notfall-Werkzeug nur als Admin. Der bekannte Reset-Link funktioniert mit dem vereinbarten Schlüssel. Nach Benutzung muss die Reset-Datei wieder vom Server gelöscht werden.
</div>
<?php endif; ?>
<nav class="portal-login-links sp-login-links" aria-label="Weitere Anmeldeseiten">
<a href="verwaltung-login.php">⚙️ Verwaltung</a>
<a href="leitung-login.php">🗂️ Gouvernante</a>
<a href="mitarbeiter-login.php">🧹 Mitarbeiter</a>
<a href="housekeeping/login.php">🧽 Housekeeping</a>
<a href="bistro/login.php">🍽️ Bistro / Frühstück & HP</a>
</nav>
<footer class="sp-login-footer">
<a href="https://jmg-design.de" target="_blank" rel="noopener">by JMG-design.de</a>
<span>StayPilot <?=e($version)?></span>
</footer>
</section>
</main>
<script>
(function(){
  var btn=document.getElementById('spTogglePassword'), input=document.getElementById('spLoginPassword');
  if(btn&&input){btn.addEventListener('click',function(){var show=input.type==='password';input.type=show?'text':'password';btn.textContent=show?'Verbergen':'Anzeigen';});}
  var fp=document.getElementById('spForgotPassword'), hint=document.getElementById('spResetHint');
  if(fp&&hint){fp.addEventListener('click',function(){hint.hidden=!hint.hidden;});}
})();
</script>
</body>
</html>
