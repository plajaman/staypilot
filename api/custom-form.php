<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

function custom_form_response(string $title, string $message, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).'</title><link rel="stylesheet" href="../assets/site.css?v='.e((string)(config()['app_version'] ?? '2.3.6')).'"></head><body class="site-body"><main class="site-section"><div class="site-container site-prose"><h1>'.e($title).'</h1><p>'.e($message).'</p><p><a class="site-btn primary" href="../index.php">Zur Website</a></p></div></main></body></html>';
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') custom_form_response('Formular', 'Dieses Formular kann nur abgesendet werden.', 405);
    $data = request_data();
    if (!empty($data['website'])) custom_form_response('Vielen Dank', 'Ihre Nachricht wurde gesendet.');
    $design = PublicSiteService::design();
    $forms = is_array($design['custom_forms'] ?? null) ? $design['custom_forms'] : [];
    $index = max(1, min(3, (int)($data['form_index'] ?? 1))) - 1;
    $form = is_array($forms[$index] ?? null) ? $forms[$index] : [];
    if (!normalize_bool($form['active'] ?? 0)) custom_form_response('Formular nicht aktiv', 'Dieses Formular ist derzeit nicht verfügbar.', 404);
    $labels = is_array($data['labels'] ?? null) ? $data['labels'] : [];
    $lines = [];
    foreach ($labels as $name => $label) {
        $key = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$name) ?? '';
        if ($key === '') continue;
        $label = mb_substr(trim(strip_tags((string)$label)), 0, 120);
        $value = $data[$key] ?? '';
        if (is_array($value)) $value = implode(', ', array_map('strval', $value));
        $lines[] = $label . ': ' . mb_substr(trim(strip_tags((string)$value)), 0, 3000);
    }
    if (!$lines) custom_form_response('Formular leer', 'Bitte füllen Sie das Formular aus.', 422);
    $recipient = trim((string)($form['recipient'] ?? ''));
    if ($recipient === '') $recipient = trim((string)($design['email'] ?? setting('contact_email', '')));
    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) custom_form_response('Empfänger fehlt', 'Für dieses Formular ist noch keine gültige Empfängeradresse eingerichtet.', 422);
    $subject = 'Website-Formular: ' . (trim((string)($form['name'] ?? 'Formular')) ?: 'Formular');
    $text = implode("\n", $lines);
    $html = '<div style="font-family:Arial,sans-serif"><h2>'.e($subject).'</h2><table cellpadding="8" cellspacing="0" style="border-collapse:collapse">';
    foreach ($lines as $line) {
        [$label, $value] = array_pad(explode(': ', $line, 2), 2, '');
        $html .= '<tr><th align="left" style="border:1px solid #ddd;background:#f5f5f5">'.e($label).'</th><td style="border:1px solid #ddd">'.nl2br(e($value)).'</td></tr>';
    }
    $html .= '</table></div>';
    SmtpMailer::send($recipient, $subject, $text, $html);
    custom_form_response('Vielen Dank', trim((string)($form['success'] ?? 'Ihre Nachricht wurde gesendet.')) ?: 'Ihre Nachricht wurde gesendet.');
} catch (Throwable $e) {
    AppLogger::error($e, ['source'=>'custom-form'], 'custom-form');
    custom_form_response('Formular konnte nicht gesendet werden', 'Bitte versuchen Sie es später erneut oder kontaktieren Sie uns direkt.', 500);
}
