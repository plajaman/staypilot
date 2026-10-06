<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
Auth::requireLogin();
$id = (int)($_GET['id'] ?? 0);
try {
    $stmt = db()->prepare("SELECT * FROM booking_documents WHERE id=? AND document_type IN ('checkin_summary','registration_form') LIMIT 1");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    if (!$doc) throw new NotFoundException('Dokument nicht gefunden.');
    $path = trim((string)($doc['pdf_path'] ?? ''));
    if ($path !== '' && is_file(root_path($path))) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/','-',((string)($doc['document_number'] ?: 'checkin-dokument')) . '.pdf') . '"');
        header('Content-Length: ' . filesize(root_path($path)));
        readfile(root_path($path));
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo (string)$doc['html_snapshot'];
} catch (Throwable $e) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>Check-in-Dokument</title><body style="font-family:system-ui;padding:30px"><h1>Dokument nicht verfügbar</h1><p>' . e($e->getMessage()) . '</p></body></html>';
}
