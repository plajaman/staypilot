<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow, noarchive', true);
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); echo 'Datei nicht gefunden.'; exit; }
try {
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token !== '') {
        $upload = CheckinService::tokenAllowsUpload($token, $id);
    } else {
        Auth::requireLogin();
        $upload = CheckinService::uploadById($id);
    }
    $root = realpath(root_path('storage/checkin'));
    $file = realpath(root_path((string)$upload['file_path']));
    if (!$root || !$file || !str_starts_with($file, $root) || !is_file($file)) throw new NotFoundException('Datei nicht gefunden.');
    $name = preg_replace('/[^a-zA-Z0-9._ -]+/', '_', (string)$upload['original_name']) ?: basename($file);
    header('Content-Type: ' . (string)$upload['mime_type']);
    header('Content-Length: ' . (string)filesize($file));
    header('Content-Disposition: inline; filename="' . str_replace('"','', $name) . '"');
    header('Cache-Control: private, no-store');
    readfile($file);
} catch (Throwable $e) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage();
}
