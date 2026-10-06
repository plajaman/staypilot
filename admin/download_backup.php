<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = Auth::requireRole(['admin','manager']);
$file = basename((string)($_GET['file'] ?? ''));
$path = BackupManager::path($file);
if ($path === null || !is_file($path)) {
    http_response_code(404);
    exit('Datensicherung nicht gefunden.');
}
AuditLogger::record('system_backup', $file, 'download', null, null, 'Datensicherung heruntergeladen');
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . addcslashes($file, '"\\') . '"');
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
readfile($path);
