<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Crypto.php';
require_once __DIR__ . '/HttpException.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Services/AppLogger.php';
require_once __DIR__ . '/Services/AuditLogger.php';
require_once __DIR__ . '/Services/BackupManager.php';
require_once __DIR__ . '/Services/SystemDiagnostics.php';
require_once __DIR__ . '/Services/PrivacyService.php';
require_once __DIR__ . '/Services/CommunicationLogger.php';
require_once __DIR__ . '/Services/SmtpMailer.php';
require_once __DIR__ . '/Services/ImapMailbox.php';
require_once __DIR__ . '/Services/HousekeepingWorkflow.php';
require_once __DIR__ . '/Services/OfferService.php';
require_once __DIR__ . '/Services/SimplePdf.php';
require_once __DIR__ . '/Services/DocumentTemplateService.php';
require_once __DIR__ . '/Services/BookingAccountingService.php';
require_once __DIR__ . '/Services/BookingWorkflowService.php';
require_once __DIR__ . '/Services/BookingPolicyService.php';
require_once __DIR__ . '/Services/TypeImageService.php';
require_once __DIR__ . '/Services/SiteMediaService.php';
require_once __DIR__ . '/Services/PublicSiteService.php';
require_once __DIR__ . '/Services/PublicSiteRenderer.php';
require_once __DIR__ . '/Services/CheckinService.php';
require_once __DIR__ . '/Services/CheckinDocumentService.php';
require_once __DIR__ . '/PricingService.php';

$appConfig = config();
date_default_timezone_set($appConfig['timezone'] ?? 'Europe/Berlin');
error_reporting(E_ALL);
ini_set('display_errors', '0');
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    AppLogger::error(new ErrorException($message, 0, $severity, $file, $line), [], 'php-warning');
    return true;
});
register_shutdown_function(static function (): void {
    $last = error_get_last();
    if ($last && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        AppLogger::error(new ErrorException($last['message'], 0, $last['type'], $last['file'], $last['line']), [], 'php-fatal');
    }
});
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($appConfig['session_name'] ?? 'staypilot_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

Auth::checkSsoCookie();

if (!is_file(root_path('config/local.php'))) {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script !== 'install.php') {
        $prefix = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/') || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/cron/') ? '../' : '';
        header('Location: ' . $prefix . 'install.php');
        exit;
    }
}

if (is_file(root_path('config/local.php'))) {
    require_once __DIR__ . '/Migrator.php';
    try {
        Migrator::run();
    } catch (Throwable $e) {
        $requestId = AppLogger::error($e, ['phase' => 'bootstrap_migration'], 'migration');
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "StayPilot-Migration fehlgeschlagen. Referenz: {$requestId}\n");
            exit(1);
        }
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (is_api_request() || str_contains($script, '/cron/')) {
            json_response([
                'ok' => false,
                'message' => 'Das sichere Datenbankupdate konnte nicht abgeschlossen werden. Die Anwendung wurde nicht weiter gestartet.',
                'code' => 'migration_failed',
                'request_id' => $requestId,
            ], 503);
        }
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        $safeMessage = e(mb_substr($e->getMessage(), 0, 900));
        $base = str_contains($script, '/admin/') ? '../' : '';
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>StayPilot – Update angehalten</title><style>body{font-family:system-ui,Arial;background:#eef3f9;color:#172033;margin:0;min-height:100vh;display:grid;place-items:center;padding:24px}.box{max-width:860px;background:#fff;border:1px solid #dce4ef;border-radius:18px;padding:30px;box-shadow:0 20px 60px rgba(20,40,70,.12)}h1{margin-top:0;font-size:32px}.ref,.err{background:#f3f6fa;padding:12px;border-radius:10px;font-family:ui-monospace,Menlo,Consolas,monospace}.err{white-space:pre-wrap;color:#7c2d12;background:#fff7ed;border:1px solid #fed7aa}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.btn{display:inline-flex;align-items:center;justify-content:center;border-radius:12px;padding:11px 14px;text-decoration:none;font-weight:800;border:1px solid #cbd5e1;color:#172033}.btn.primary{background:#2563eb;color:#fff;border-color:#2563eb}code{background:#eef2f7;border-radius:6px;padding:2px 5px}li{margin:6px 0}</style></head><body><main class="box"><h1>Das Update wurde sicher angehalten</h1><p>StayPilot hat die Migration gestoppt, bevor unkontrollierte Änderungen entstehen. Die genaue Ursache wird jetzt angezeigt.</p><p class="err">' . $safeMessage . '</p><p>Bitte zuerst prüfen: <code>storage</code>, <code>storage/backups</code>, <code>storage/logs</code> und die Datenbankverbindung.</p><ul><li>Ordner müssen für PHP beschreibbar sein, meist Rechte <b>775</b> oder auf manchen Servern <b>755</b>.</li><li>Nicht neu installieren und keine Datenbank löschen.</li><li>Die Reparaturseite kann ohne Migration geöffnet werden.</li></ul><p class="ref">Fehlerreferenz: ' . e($requestId) . '</p><div class="actions"><a class="btn primary" href="' . e($base) . 'update-rettung.php">Update-Rettung öffnen</a><a class="btn" href="' . e($base) . 'datenbank-hilfe.php">Datenbank-Hilfe öffnen</a></div></main></body></html>';
        exit;
    }
}
