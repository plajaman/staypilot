<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
Auth::requireLogin();
$id = (int)($_GET['id'] ?? 0);
try {
    if ($id <= 0) throw new ValidationException('Dokument fehlt.');
    $stmt = db()->prepare('SELECT * FROM booking_documents WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    if (!$doc) throw new NotFoundException('Dokument nicht gefunden.');
    $path = trim((string)($doc['pdf_path'] ?? ''));
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', ((string)($doc['document_number'] ?: 'staypilot-dokument')) . '.pdf');
    if ($path !== '') {
        $absolute = root_path($path);
        $storageRoot = realpath(root_path('storage/documents')) ?: root_path('storage/documents');
        $real = realpath($absolute);
        if ($real && str_starts_with($real, $storageRoot) && is_file($real)) {
            header('X-Robots-Tag: noindex, nofollow, noarchive', true);
            header('X-Content-Type-Options: nosniff');
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($real));
            readfile($real);
            exit;
        }
    }
    $html = (string)($doc['html_snapshot'] ?? '');
    if ($html === '') throw new NotFoundException('Für dieses Dokument ist weder ein gespeichertes PDF noch eine Vorschau vorhanden.');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
} catch (Throwable $e) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>Dokument</title><body style="font-family:system-ui;padding:30px"><h1>Dokument nicht verfügbar</h1><p>' . e($e->getMessage()) . '</p></body></html>';
}
