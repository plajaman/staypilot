<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
Auth::requireLogin();
if (!Auth::can('police_manage')) { http_response_code(403); exit('Keine Berechtigung fuer Meldedaten-Exporte.'); }
$id = (int)($_GET['id'] ?? 0);
$type = (string)($_GET['type'] ?? 'csv');
if ($id <= 0 || !in_array($type, ['csv','json'], true)) { http_response_code(400); echo 'Ungueltiger Export.'; exit; }
$stmt = db()->prepare('SELECT * FROM smart_arrival_exports WHERE id=? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); echo 'Export nicht gefunden.'; exit; }
$rel = $type === 'json' ? (string)$row['json_path'] : (string)$row['file_path'];
$path = root_path($rel);
if ($rel === '' || !is_file($path)) { http_response_code(404); echo 'Exportdatei nicht gefunden.'; exit; }
$filename = basename($path);
header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('X-Content-Type-Options: nosniff');
header('Content-Type: '.($type === 'json' ? 'application/json; charset=utf-8' : 'text/csv; charset=utf-8'));
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($path));
readfile($path);
