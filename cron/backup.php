<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

$expected = (string)(local_config()['cron_token'] ?? '');
$provided = (string)($_GET['token'] ?? ($_SERVER['argv'][1] ?? ''));
if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    exit("Forbidden\n");
}
header('Content-Type: text/plain; charset=utf-8');
$lock = fopen(root_path('storage/backup.lock'), 'c+');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit("Datensicherung läuft bereits.\n");
try {
    $result = BackupManager::create('scheduled');
    save_setting('cron_last_run_at', date('Y-m-d H:i:s'));
    echo "OK: {$result['filename']} ({$result['size']} Bytes)\n";
} catch (Throwable $e) {
    $reference = AppLogger::error($e, [], 'backup-cron');
    http_response_code(500);
    echo "FEHLER: Datensicherung fehlgeschlagen. Referenz {$reference}\n";
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
