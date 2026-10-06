<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

function sp_bistro_target(array $user): ?string {
    return Auth::canForUser($user, 'meals_manage') ? 'index.php' : null;
}

$loggedIn = Auth::user();
$error = '';
$target = $loggedIn ? sp_bistro_target($loggedIn) : null;

if (!$loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['bistro_login_csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) {
        $error = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.';
    } elseif (Auth::attempt((string)($_POST['identity'] ?? ''), (string)($_POST['password'] ?? ''))) {
        $user = Auth::user() ?? [];
        $target = sp_bistro_target($user);
        if ($target) {
            header('Location: ' . $target);
            exit;
        }
        Auth::logout();
        $error = 'Dieses Konto hat keinen Zugriff auf Frühstück & HP.';
    } else {
        $error = 'E-Mail/Benutzername oder Passwort ist falsch.';
    }
}

$_SESSION['bistro_login_csrf'] ??= bin2hex(random_bytes(24));
$version = (string)(config()['app_version'] ?? '2.3.6.36');
$roleLabels = array_column(Auth::roleMatrix(), 'label');
$currentRoleLabel = $loggedIn ? ($roleLabels[(string)($loggedIn['role'] ?? '')] ?? (string)($loggedIn['role'] ?? '')) : '';
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#b45309">
<title>StayPilot Bistro – Anmeldung</title>
<link rel="stylesheet" href="../assets/app.css?v=<?=e($version)?>">
<link rel="stylesheet" href="../assets/bistro-portal.css?v=<?=e($version)?>">
<style>
:root{--bg:#fff7ed;--card:#fff;--text:#1f2937;--muted:#64748b;--line:#fed7aa;--blue:#2563eb;--orange:#b45309;--green:#16a34a;--red:#dc2626;--soft:#fff7ed}
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Arial,Helvetica,sans-serif;background:linear-gradient(135deg,#fff7ed,#fff 55%,#fef3c7);color:var(--text);display:flex;align-items:center;justify-content:center;padding:24px}.portal-card{width:min(980px,100%);background:rgba(255,255,255,.96);border:1px solid var(--line);border-radius:28px;box-shadow:0 24px 70px rgba(120,53,15,.16);overflow:hidden}.portal-grid{display:grid;grid-template-columns:1.05fr .95fr;min-height:560px}.hero{background:linear-gradient(145deg,#b45309,#92400e);color:#fff;padding:42px;display:flex;flex-direction:column;justify-content:space-between}.brand{display:flex;gap:14px;align-items:center}.brand-icon{width:58px;height:58px;border-radius:20px;background:rgba(255,255,255,.18);display:grid;place-items:center;font-size:30px}.hero h1{font-size:38px;line-height:1.05;margin:24px 0 12px}.hero p{font-size:18px;line-height:1.5;opacity:.92}.pills{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}.pill{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:999px;padding:9px 12px;font-weight:700}.content{padding:42px}.content h2{font-size:28px;margin:0 0 8px}.muted{color:var(--muted)}.form-stack{display:grid;gap:16px;margin-top:24px}label{font-weight:800;color:#334155}input{display:block;width:100%;margin-top:8px;border:1px solid var(--line);border-radius:14px;padding:14px 15px;font-size:16px;background:#fff}.btn{border:1px solid var(--line);border-radius:14px;padding:14px 16px;font-weight:900;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;color:var(--text);background:#fff;cursor:pointer}.btn.primary{background:var(--orange);border-color:var(--orange);color:#fff}.btn.blue{background:var(--blue);border-color:var(--blue);color:#fff}.btn.block{width:100%}.alert{border-radius:16px;padding:14px 16px;margin:18px 0;font-weight:700}.alert.danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}.alert.warning{background:#fffbeb;color:#92400e;border:1px solid #fde68a}.cards{display:grid;gap:12px;margin:22px 0}.area-card{border:1px solid var(--line);border-radius:18px;padding:16px;background:var(--soft);display:flex;justify-content:space-between;gap:14px;align-items:center}.area-card b{display:block;font-size:17px}.area-card small{color:var(--muted)}.links{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}.links a{color:#b45309;font-weight:800;text-decoration:none}.switch{margin-top:18px;padding-top:18px;border-top:1px solid var(--line)}@media(max-width:820px){body{padding:0;align-items:stretch}.portal-card{border-radius:0}.portal-grid{grid-template-columns:1fr}.hero{padding:28px}.content{padding:28px}.hero h1{font-size:30px}}
</style>
</head>
<body>
<main class="portal-card">
  <div class="portal-grid">
    <section class="hero">
      <div>
        <div class="brand"><div class="brand-icon">🍽️</div><div><b>StayPilot</b><br><span>Bistro · Frühstück & HP</span></div></div>
        <h1>Login für Frühstücks- und HP-Listen</h1>
        <p>Hier arbeiten Küche, Bistro, Rezeption oder Admin mit den bestehenden Frühstück-&-HP-Daten.</p>
        <div class="pills"><span class="pill">Frühstück</span><span class="pill">Halbpension</span><span class="pill">Tageslisten</span><span class="pill">Druck</span></div>
      </div>
      <p class="muted" style="color:rgba(255,255,255,.78)">Kein Parallelmodul: dieselben Buchungsdaten wie Frühstück & HP im Admin.</p>
    </section>
    <section class="content">
      <?php if($loggedIn): ?>
        <h2>Bereits angemeldet</h2>
        <p class="muted">Aktuell: <b><?=e((string)($loggedIn['name'] ?? ''))?></b> · <?=e($currentRoleLabel)?></p>
        <?php if($target): ?>
          <div class="alert warning">Du bist bereits angemeldet. Öffne Bistro/Frühstück & HP oder melde dich ab, um mit einem anderen Konto einzusteigen.</div>
          <div class="cards">
            <div class="area-card"><div><b>Bistro Tageslisten</b><small>Frühstück, HP, Zeitraum und Druck</small></div><a class="btn primary" href="index.php">Öffnen</a></div>
            <div class="area-card"><div><b>Admin Frühstück & HP</b><small>Verwaltung im Adminbereich</small></div><a class="btn" href="../admin/#meals">Admin</a></div>
            <div class="area-card"><div><b>Druckliste</b><small>öffne die Druckansicht</small></div><a class="btn" href="print.php" target="_blank">Drucken</a></div>
          </div>
        <?php else: ?>
          <div class="alert danger">Dieses Konto hat keinen Zugriff auf Frühstück & HP.</div>
        <?php endif; ?>
        <div class="switch"><a class="btn block" href="logout.php">Abmelden und anderes Konto verwenden</a></div>
      <?php else: ?>
        <h2>Anmelden</h2>
        <p class="muted">Für Bistro, Küche, Rezeption und Admin.</p>
        <?php if($error): ?><div class="alert danger"><?=e($error)?></div><?php endif; ?>
        <form method="post" class="form-stack">
          <input type="hidden" name="_csrf" value="<?=e($_SESSION['bistro_login_csrf'])?>">
          <label>E-Mail oder Benutzername<input type="text" name="identity" autocomplete="username" required autofocus></label>
          <label>Passwort<input type="password" name="password" autocomplete="current-password" required></label>
          <button class="btn primary block" type="submit">Anmelden</button>
        </form>
      <?php endif; ?>
      <nav class="links" aria-label="Weitere Bereiche">
        <a href="../verwaltung-login.php">Verwaltung</a>
        <a href="../housekeeping/login.php">Housekeeping</a>
        <a href="../admin/#meals">Admin Frühstück & HP</a>
        <a href="../index.php">Öffentliche Seite</a>
      </nav>
    </section>
  </div>
</main>
</body>
</html>
