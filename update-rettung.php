<?php
declare(strict_types=1);

$root = basename(__DIR__) === 'admin' ? dirname(__DIR__) : __DIR__;
$version = '2.3.6.113';
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function rel(string $path): string { global $root; return ltrim(str_replace($root, '', $path), '/'); }
function check_dir(string $relative, bool $fix=false): array {
    global $root;
    $path = $root . '/' . trim($relative, '/');
    $messages = [];
    if (!is_dir($path)) {
        if ($fix) @mkdir($path, 0775, true);
        $messages[] = is_dir($path) ? 'Ordner wurde angelegt.' : 'Ordner fehlt.';
    }
    if (is_dir($path) && $fix) @chmod($path, 0775);
    clearstatcache(true, $path);
    $writable = is_dir($path) && is_writable($path);
    $probeOk = false;
    if ($writable) {
        $probe = $path . '/.staypilot-write-test';
        $probeOk = @file_put_contents($probe, 'ok') !== false;
        @unlink($probe);
        if (!$probeOk) $messages[] = 'Schreibtest fehlgeschlagen.';
    } else {
        $messages[] = 'Nicht beschreibbar.';
    }
    return [
        'relative' => $relative,
        'path' => $path,
        'exists' => is_dir($path),
        'writable' => $writable && $probeOk,
        'perms' => is_dir($path) ? substr(sprintf('%o', fileperms($path)), -4) : '—',
        'messages' => $messages,
    ];
}
function db_check(): array {
    global $root;
    $local = $root . '/config/local.php';
    if (!is_file($local)) return ['ok'=>false,'message'=>'config/local.php fehlt. Die App ist noch nicht korrekt konfiguriert.'];
    try {
        $cfg = require $local;
        $db = $cfg['db'] ?? [];
        $pdo = new PDO((string)($db['dsn'] ?? ''), (string)($db['user'] ?? ''), (string)($db['password'] ?? ''), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $schema = 'unbekannt';
        try {
            $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='schema_version' LIMIT 1");
            $schema = (string)($stmt ? $stmt->fetchColumn() : 'unbekannt');
        } catch (Throwable) {}
        return ['ok'=>true,'message'=>'Datenbankverbindung funktioniert. Schema-Version: '.$schema];
    } catch (Throwable $e) {
        return ['ok'=>false,'message'=>'Datenbankverbindung fehlgeschlagen: '.$e->getMessage()];
    }
}
$fix = ($_GET['fix'] ?? '') === '1';
$dirs = ['storage','storage/backups','storage/logs','storage/type-images','storage/type-images/thumbs','storage/documents','storage/delete_center_trash'];
$checks = array_map(fn($d)=>check_dir($d, $fix), $dirs);
$db = db_check();
$allOk = $db['ok'];
foreach ($checks as $c) { if (!$c['writable']) $allOk = false; }
$recentLog = '';
$logFile = $root . '/storage/logs/app-' . date('Y-m-d') . '.log';
if (is_file($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $recentLog = implode("\n", array_slice($lines, -8));
}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>StayPilot Update-Rettung</title><style>
body{font-family:system-ui,Arial,sans-serif;background:#eef3f9;color:#172033;margin:0;padding:28px}.wrap{max-width:1100px;margin:auto}.hero,.card{background:#fff;border:1px solid #dce4ef;border-radius:20px;box-shadow:0 20px 60px rgba(20,40,70,.10);padding:24px;margin-bottom:18px}.hero{background:linear-gradient(135deg,#0f172a,#2563eb);color:#fff}.hero h1{font-size:36px;margin:0 0 8px}.hero p{font-size:18px;margin:0;opacity:.92}.status{display:inline-flex;border-radius:999px;padding:7px 11px;font-weight:900}.ok{background:#dcfce7;color:#166534}.bad{background:#fee2e2;color:#991b1b}.warn{background:#fff7ed;color:#9a3412}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px}.item{border:1px solid #e2e8f0;border-radius:16px;padding:14px;background:#fbfdff}.item b,.item small{display:block}.item small{color:#64748b;margin-top:5px;word-break:break-all}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:13px;padding:12px 15px;background:#2563eb;color:#fff;text-decoration:none;font-weight:900;margin:4px}.btn.secondary{background:#fff;color:#172033;border:1px solid #cbd5e1}.code{white-space:pre-wrap;background:#0f172a;color:#dbeafe;padding:14px;border-radius:14px;overflow:auto;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px}table{width:100%;border-collapse:collapse}td,th{padding:10px;border-bottom:1px solid #e2e8f0;text-align:left}code{background:#eef2f7;border-radius:6px;padding:2px 5px}.actions{display:flex;gap:8px;flex-wrap:wrap}</style></head><body><div class="wrap">
<section class="hero"><h1>StayPilot Update-Rettung <?=h($version)?></h1><p>Diese Seite läuft ohne normale Migration und prüft genau die Punkte, die das Update blockieren können.</p></section>
<section class="card"><h2>Gesamtstatus</h2><p><span class="status <?=$allOk?'ok':'bad'?>"><?=$allOk?'Bereit für Update':'Noch nicht bereit'?></span></p><?php if(!$allOk): ?><p>Bitte zuerst die roten Punkte beheben. Danach diese Seite erneut öffnen und anschließend die normale App starten.</p><?php endif; ?><div class="actions"><a class="btn" href="?fix=1">Ordner automatisch anlegen / chmod versuchen</a><a class="btn secondary" href="update-rettung.php">Neu prüfen</a><a class="btn secondary" href="index.php">App erneut starten</a></div></section>
<section class="card"><h2>Ordner & Schreibrechte</h2><div class="grid"><?php foreach($checks as $c): ?><div class="item"><span class="status <?=$c['writable']?'ok':'bad'?>"><?=$c['writable']?'OK':'Problem'?></span><b><?=h($c['relative'])?></b><small>Rechte: <?=h($c['perms'])?></small><small><?=h(implode(' ', $c['messages']) ?: 'Schreibtest erfolgreich.')?></small></div><?php endforeach; ?></div></section>
<section class="card"><h2>Datenbank</h2><p><span class="status <?=$db['ok']?'ok':'bad'?>"><?=$db['ok']?'OK':'Problem'?></span></p><p><?=h($db['message'])?></p></section>
<section class="card"><h2>Was jetzt?</h2><ol><li>Wenn <code>storage/backups</code> rot ist: im Hosting-Dateimanager/FTP Rechte setzen, meist <b>775</b> oder <b>755</b>.</li><li>Wenn <code>storage/logs</code> rot ist: ebenfalls Schreibrechte setzen.</li><li>Danach diese Rettungsseite erneut prüfen.</li><li>Erst wenn alles grün ist: <b>App erneut starten</b>.</li></ol><p><b>Nicht neu installieren. Nicht Datenbank löschen.</b></p></section>
<?php if($recentLog !== ''): ?><section class="card"><h2>Letzte Log-Einträge heute</h2><div class="code"><?=h($recentLog)?></div></section><?php endif; ?>
</div></body></html>
