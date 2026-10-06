<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; frame-ancestors 'none'");
header('Cache-Control: no-store, private');

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$notice = '';
$booking = null;
$uploads = [];
try {
    if (!normalize_bool(setting('smart_arrival_paper_scan_enabled', 1)) || !normalize_bool(setting('smart_arrival_paper_scan_public_link', 1))) {
        throw new RuntimeException('Dieser optionale Handy-Scan ist deaktiviert. Der normale Check-in und die Rezeptionserfassung bleiben weiterhin möglich.');
    }
    $booking = CheckinService::bookingByToken($token);
    $bookingId = (int)$booking['booking_id'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (!empty($_FILES['paper_form_file'])) {
            $note = trim((string)($_POST['note'] ?? ''));
            if ($note === '') $note = 'Optionaler Handy-Scan eines handschriftlichen Papier-Meldescheins';
            CheckinService::storeUpload($bookingId, $_FILES['paper_form_file'], null, true, $note);
            try {
                db()->prepare("INSERT INTO smart_arrival_manual_entries(booking_id,source_type,status,missing_fields,captured_by,note) VALUES(?,?,?,?,?,?)")
                    ->execute([$bookingId,'paper_photo','uploaded',json_encode(['Foto/Scan liegt zur späteren Prüfung in den vorhandenen Check-in-Uploads.'], JSON_UNESCAPED_UNICODE),null,$note]);
            } catch (Throwable) {}
            $notice = 'Foto/Scan wurde gespeichert. Die Rezeption kann ihn im bestehenden Check-in-/Meldeschein-Ablauf prüfen. Es wurde kein neuer Meldeschein erzeugt.';
            $booking = CheckinService::bookingByToken($token);
        } else {
            throw new RuntimeException('Bitte ein Foto oder PDF auswählen.');
        }
    }
    $uploads = $booking['checkin_uploads'] ?? [];
} catch (Throwable $e) {
    $error = $e->getMessage();
}
function pe(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>StayPilot Papierformular-Scan</title><style>
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#eef4fb;color:#172033}.wrap{max-width:780px;margin:auto;padding:18px}.hero{background:linear-gradient(135deg,#183b70,#2563eb);color:#fff;border-radius:24px;padding:24px;box-shadow:0 18px 45px rgba(15,23,42,.18)}.card{background:#fff;border:1px solid #dbe6f3;border-radius:20px;padding:20px;margin-top:16px;box-shadow:0 12px 35px rgba(15,23,42,.08)}h1{margin:.1em 0;font-size:28px}.muted{color:#64748b}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:14px;padding:14px 18px;font-weight:800;text-decoration:none;background:#e8eef7;color:#172033;cursor:pointer}.btn.primary{background:#2563eb;color:#fff;width:100%;font-size:18px}.notice{padding:14px;border-radius:14px;background:#ecfdf5;border:1px solid #bbf7d0;color:#14532d}.error{padding:14px;border-radius:14px;background:#fff1f2;border:1px solid #fecdd3;color:#7f1d1d}.warning{padding:14px;border-radius:14px;background:#fffbeb;border:1px solid #fde68a;color:#78350f}.filebox{border:2px dashed #9db5d4;border-radius:18px;padding:22px;text-align:center;background:#f8fbff}input[type=file]{display:block;width:100%;margin-top:14px;font-size:16px}textarea{width:100%;min-height:80px;border:1px solid #cbd5e1;border-radius:12px;padding:12px;font:inherit}.uploads{display:grid;gap:8px}.upload{display:flex;justify-content:space-between;gap:10px;align-items:center;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:10px}.small{font-size:13px}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}@media(max-width:600px){.wrap{padding:12px}.hero{border-radius:18px}.card{border-radius:16px;padding:16px}.upload{align-items:flex-start;flex-direction:column}}
</style></head><body><main class="wrap">
<section class="hero"><h1>📷 Papier-Meldeschein fotografieren</h1><p>Optionaler Handy-Helfer für die Rezeption. Der bisherige Check-in bleibt vollständig erhalten.</p></section>
<?php if ($error): ?><section class="card"><div class="error"><?=pe($error)?></div></section><?php else: ?>
<section class="card"><h2>Buchung <?=pe((string)($booking['reference'] ?? ''))?></h2><p class="muted">Gast: <?=pe((string)($booking['guest_name'] ?? ''))?> · Aufenthalt: <?=pe((string)($booking['arrival'] ?? ''))?> bis <?=pe((string)($booking['departure'] ?? ''))?></p><div class="warning"><b>Wichtig:</b> Dieser Weg ist freiwillig. Er ersetzt nicht die normale Eingabe, den Online-Check-in oder die Prüfung durch die Rezeption. Das Foto wird nur als vorhandener Check-in-Upload gespeichert.</div></section>
<?php if ($notice): ?><section class="card"><div class="notice"><?=pe($notice)?></div></section><?php endif; ?>
<section class="card"><form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=pe(csrf_token())?>"><input type="hidden" name="token" value="<?=pe($token)?>"><div class="filebox"><b>Foto/PDF auswählen oder Kamera öffnen</b><input type="file" name="paper_form_file" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,application/pdf,image/*" capture="environment" required><p class="muted small">Erlaubt: PDF, JPG, PNG, WEBP oder HEIC bis 12 MB.</p></div><p><label>Notiz für die Rezeption</label><textarea name="note" placeholder="z. B. Papierformular Hauptgast, Gruppe Müller, schwer lesbar …"></textarea></p><button class="btn primary" type="submit">Foto/Scan speichern</button></form></section>
<?php if ($uploads): ?><section class="card"><h2>Bereits gespeicherte Dateien</h2><div class="uploads"><?php foreach($uploads as $u): ?><div class="upload"><span>📄 <?=pe((string)$u['original_name'])?><br><small class="muted"><?=pe((string)$u['created_at'])?> · <?=pe((string)($u['note'] ?? ''))?></small></span><a class="btn" target="_blank" href="checkin-datei.php?token=<?=rawurlencode($token)?>&amp;id=<?=(int)$u['id']?>">Öffnen</a></div><?php endforeach; ?></div></section><?php endif; ?>
<section class="card"><h2>Normaler Check-in bleibt möglich</h2><p class="muted">Die vollständigen Gästedaten können weiterhin wie bisher im Online-Check-in oder direkt an der Rezeption erfasst werden.</p><div class="actions"><a class="btn" href="checkin.php?token=<?=rawurlencode($token)?>">Zum vollständigen Online-Check-in</a><a class="btn" href="kunde.php?token=<?=rawurlencode($token)?>">Zum Kundenbereich</a></div></section>
<?php endif; ?>
</main></body></html>
