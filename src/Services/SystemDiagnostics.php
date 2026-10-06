<?php
declare(strict_types=1);

final class SystemDiagnostics
{
    private const EXPECTED_APP_VERSION = '2.3.6.136';

    public static function run(): array
    {
        $checks = [];
        $push = static function (string $key, string $label, string $status, string $message, array $meta = [], string $group = 'Allgemein') use (&$checks): void {
            $checks[] = compact('key', 'label', 'status', 'message', 'meta', 'group');
        };

        $appVersion = (string)(config()['app_version'] ?? 'unknown');
        $versionFile = trim((string)@file_get_contents(root_path('VERSION.txt')));
        $adminVersionFile = trim((string)@file_get_contents(root_path('admin/VERSION.txt')));
        $schemaVersion = self::safeSetting('schema_version', 'unknown');

        $push('app_version_config', 'App-Version aus config/config.php', $appVersion === self::EXPECTED_APP_VERSION ? 'ok' : 'warning', $appVersion, ['expected' => self::EXPECTED_APP_VERSION], 'Update-Stand');
        $push('app_version_file', 'VERSION.txt', $versionFile === self::EXPECTED_APP_VERSION ? 'ok' : 'warning', $versionFile !== '' ? $versionFile : 'Datei leer oder fehlt', ['expected' => self::EXPECTED_APP_VERSION], 'Update-Stand');
        $push('admin_version_file', 'admin/VERSION.txt', $adminVersionFile === self::EXPECTED_APP_VERSION ? 'ok' : 'warning', $adminVersionFile !== '' ? $adminVersionFile : 'Datei leer oder fehlt', ['expected' => self::EXPECTED_APP_VERSION], 'Update-Stand');
        $push('schema_version', 'Datenbankschema', version_compare((string)$schemaVersion, '2.2.8', '>=') ? 'ok' : 'warning', (string)$schemaVersion . ' · Zielstand ohne neue Pflicht-DB-Struktur: 2.2.8', [], 'Update-Stand');

        self::runRuntimeChecks($push);
        self::runFreeIntegratedDiagnostics($push);
        self::runDiagnosticsProChecks($push);
        self::runDatabaseChecks($push);
        self::runFileChecks($push);
        self::runDocumentChecks($push);
        self::runCustomerPortalFlowChecks($push);
        self::runBusinessDataChecks($push);
        self::runCommunicationChecks($push);
        self::runSecurityAndCacheChecks($push);
        self::runProductReadinessChecks($push);

        $errors = [];
        try {
            if (self::tableExists('app_errors')) {
                $errors = db()->query('SELECT request_id,source,level,message,created_at FROM app_errors ORDER BY id DESC LIMIT 10')->fetchAll();
            }
        } catch (Throwable) {}

        $backups = [];
        try { $backups = BackupManager::list(); } catch (Throwable) {}

        $migrations = [];
        try {
            if (self::tableExists('schema_migrations')) {
                $migrations = db()->query('SELECT version,applied_at FROM schema_migrations ORDER BY applied_at DESC, version DESC LIMIT 15')->fetchAll();
            }
        } catch (Throwable) {}

        $recentCommunications = [];
        try {
            if (self::tableExists('communication_log')) {
                $bodyCol = self::columnExists('communication_log', 'message_body') ? ', LEFT(COALESCE(message_body, message_excerpt, \'\'), 1600) AS preview_body' : ', message_excerpt AS preview_body';
                $recentCommunications = db()->query('SELECT id,channel,entity_type,entity_id,recipient_address,subject,status,detail,created_at' . $bodyCol . ' FROM communication_log ORDER BY id DESC LIMIT 12')->fetchAll();
            }
        } catch (Throwable) {}

        $summary = [
            'ok' => count(array_filter($checks, static fn($c) => $c['status'] === 'ok')),
            'warning' => count(array_filter($checks, static fn($c) => $c['status'] === 'warning')),
            'error' => count(array_filter($checks, static fn($c) => $c['status'] === 'error')),
            'info' => count(array_filter($checks, static fn($c) => $c['status'] === 'info')),
        ];
        $groupSummary = [];
        foreach ($checks as $check) {
            $group = (string)($check['group'] ?? 'Allgemein');
            $groupSummary[$group] ??= ['ok' => 0, 'warning' => 0, 'error' => 0, 'info' => 0, 'total' => 0];
            $status = (string)$check['status'];
            if (isset($groupSummary[$group][$status])) $groupSummary[$group][$status]++;
            $groupSummary[$group]['total']++;
        }

        return [
            'version' => $appVersion,
            'expected_version' => self::EXPECTED_APP_VERSION,
            'version_file' => $versionFile,
            'admin_version_file' => $adminVersionFile,
            'schema_version' => (string)$schemaVersion,
            'generated_at' => date('Y-m-d H:i:s'),
            'checks' => $checks,
            'group_summary' => $groupSummary,
            'backups' => $backups,
            'migrations' => $migrations,
            'errors' => $errors,
            'recent_communications' => $recentCommunications,
            'summary' => $summary,
            'recommendations' => self::recommendations($checks),
        ];
    }

    private static function runRuntimeChecks(callable $push): void
    {
        $push('php', 'PHP-Version', version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'error', PHP_VERSION, ['required' => '>= 8.1'], 'Server');
        foreach (['pdo_mysql', 'curl', 'openssl', 'mbstring', 'json', 'gd', 'dom', 'fileinfo', 'zip'] as $extension) {
            $push('ext_' . $extension, 'PHP-Erweiterung ' . $extension, extension_loaded($extension) ? 'ok' : 'error', extension_loaded($extension) ? 'vorhanden' : 'fehlt', [], 'Server');
        }
        $push('ext_gd_webp', 'PHP-Bildoptimierung WebP', function_exists('imagewebp') ? 'ok' : 'warning', function_exists('imagewebp') ? 'GD/WebP verfügbar' : 'imagewebp fehlt – Bilder können nicht automatisch optimiert werden', [], 'Server');
        try {
            $version = (string)db()->query('SELECT VERSION()')->fetchColumn();
            $push('database', 'Datenbankverbindung', 'ok', $version, [], 'Server');
        } catch (Throwable $e) {
            $push('database', 'Datenbankverbindung', 'error', $e->getMessage(), [], 'Server');
        }
        $free = @disk_free_space(root_path()) ?: 0;
        $push('disk', 'Freier Speicher', $free > 100 * 1024 * 1024 ? 'ok' : 'warning', self::bytes($free), [], 'Server');
    }


    private static function runFreeIntegratedDiagnostics(callable $push): void
    {
        // Kostenlose, native Prüfungen direkt im Admin: keine externen Dienste, keine Datenänderung.
        $push('diagnostics_free_native', 'Kostenlose Diagnose-Integration', 'ok', 'Native PHP-/MariaDB-/Dateisystem-Prüfungen sind direkt im Admin integriert. Keine externen Dienste erforderlich.', [], 'Server');
        $push('diagnostics_external_tools', 'Externe Test-Tools', 'info', 'Playwright/Puppeteer, Sentry oder externe DNS-Dashboards werden bewusst nicht vorausgesetzt. Sie wären optional, aber nicht nötig für die aktuelle Diagnose.', [], 'Server');

        $maxCsv = (int)(config()['max_csv_size'] ?? 0);
        $uploadMax = self::iniBytes((string)ini_get('upload_max_filesize'));
        $postMax = self::iniBytes((string)ini_get('post_max_size'));
        $memory = self::iniBytes((string)ini_get('memory_limit'));
        $execution = (int)ini_get('max_execution_time');

        $uploadOk = $maxCsv <= 0 || ($uploadMax >= $maxCsv && $postMax >= $maxCsv);
        $push('php_upload_limits', 'PHP Upload-Limits', $uploadOk ? 'ok' : 'warning', 'upload_max_filesize ' . self::bytes($uploadMax) . ' · post_max_size ' . self::bytes($postMax) . ' · StayPilot CSV-Limit ' . self::bytes($maxCsv), [], 'Server');
        $push('php_memory_limit', 'PHP Speicherlimit', ($memory === -1 || $memory >= 128 * 1024 * 1024) ? 'ok' : 'warning', $memory === -1 ? 'unbegrenzt' : self::bytes($memory), [], 'Server');
        $push('php_execution_time', 'PHP Laufzeitlimit', ($execution === 0 || $execution >= 30) ? 'ok' : 'warning', $execution === 0 ? 'unbegrenzt' : $execution . ' Sekunden', [], 'Server');

        $tmp = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
        $push('php_tmp_dir', 'PHP temporärer Upload-Ordner', is_dir((string)$tmp) && is_writable((string)$tmp) ? 'ok' : 'warning', (string)$tmp, ['path' => (string)$tmp], 'Server');

        try {
            $size = db()->query("SELECT COALESCE(SUM(DATA_LENGTH+INDEX_LENGTH),0) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()")->fetchColumn();
            $push('database_size', 'Datenbankgröße', 'info', self::bytes((int)$size), [], 'Datenbank');
            $largest = db()->query("SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH+INDEX_LENGTH AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY bytes DESC LIMIT 5")->fetchAll();
            $parts = [];
            foreach ($largest as $row) {
                $parts[] = (string)$row['TABLE_NAME'] . ' · ' . self::bytes((int)$row['bytes']);
            }
            $push('database_largest_tables', 'Größte Tabellen', 'info', $parts ? implode(' · ', $parts) : 'nicht ermittelbar', ['tables' => $largest], 'Datenbank');
        } catch (Throwable $e) {
            $push('database_size', 'Datenbankgröße', 'info', 'konnte lokal nicht ermittelt werden: ' . $e->getMessage(), [], 'Datenbank');
        }
    }


    private static function runDiagnosticsProChecks(callable $push): void
    {
        self::runLoginReachabilityChecks($push);
        self::runPdfSelfTest($push);
        self::runBackupDiagnostics($push);
        self::runCalendarConsistencyChecks($push);
        self::runHousekeepingDiagnostics($push);
        self::runPerformanceDiagnostics($push);
    }

    private static function runLoginReachabilityChecks(callable $push): void
    {
        $pages = [
            'verwaltung-login.php' => 'Admin-Login',
            'mitarbeiter-login.php' => 'Mitarbeiter-Login',
            'leitung-login.php' => 'Team-Manager-Login',
            'kunde.php' => 'Kundenportal',
            'checkin.php' => 'Check-in',
        ];
        foreach ($pages as $file => $label) {
            $push('login_reach_' . str_replace(['.', '-'], '_', $file), $label, is_file(root_path($file)) ? 'ok' : 'error', is_file(root_path($file)) ? $file . ' vorhanden · echter Browser-Login bleibt Serverprüfung' : $file . ' fehlt', ['file' => $file], 'Diagnose Pro: Login');
        }
    }

    private static function runPdfSelfTest(callable $push): void
    {
        try {
            if (!class_exists('SimplePdf')) {
                $push('diagnostics_pdf_class', 'PDF-Test: Bibliothek', 'error', 'SimplePdf ist nicht geladen.', [], 'Diagnose Pro: PDF');
                return;
            }
            $folder = root_path('storage/tmp');
            if (!is_dir($folder) || !is_writable($folder)) {
                $push('diagnostics_pdf_tmp', 'PDF-Test: Speicherort', 'error', 'storage/tmp ist nicht beschreibbar.', ['path' => $folder], 'Diagnose Pro: PDF');
                return;
            }
            $pdf = SimplePdf::create('StayPilot Diagnose PDF-Test', ['Automatischer kostenloser Selbsttest', 'Zeitpunkt: ' . date('Y-m-d H:i:s'), 'Keine Buchungsdaten werden verändert.']);
            $relative = 'storage/tmp/diagnose-pdf-test.pdf';
            $absolute = root_path($relative);
            $written = @file_put_contents($absolute, $pdf, LOCK_EX);
            $ok = $written !== false && is_file($absolute) && filesize($absolute) > 100;
            $push('diagnostics_pdf_create', 'PDF-Testgenerator', $ok ? 'ok' : 'error', $ok ? 'Test-PDF erzeugt · ' . self::bytes((int)filesize($absolute)) : 'Test-PDF konnte nicht erzeugt werden.', ['path' => $relative], 'Diagnose Pro: PDF');
        } catch (Throwable $e) {
            $push('diagnostics_pdf_create', 'PDF-Testgenerator', 'error', $e->getMessage(), [], 'Diagnose Pro: PDF');
        }
    }

    private static function runBackupDiagnostics(callable $push): void
    {
        try {
            $backups = BackupManager::list();
            if (!$backups) {
                $push('diagnostics_backup_latest', 'Backup-Prüfung', 'warning', 'Noch keine Datensicherung gefunden. Bitte im Admin eine Sicherung erstellen.', [], 'Diagnose Pro: Backup');
                return;
            }
            $latest = $backups[0];
            $ageDays = null;
            if (!empty($latest['created_at'])) {
                $ageDays = max(0, (int)floor((time() - strtotime((string)$latest['created_at'])) / 86400));
            }
            $size = (int)($latest['size'] ?? 0);
            $status = $size > 1024 ? 'ok' : 'warning';
            $message = 'Letztes Backup: ' . (string)($latest['filename'] ?? 'unbekannt') . ' · ' . (string)($latest['created_at'] ?? 'ohne Datum') . ' · ' . self::bytes($size);
            if ($ageDays !== null) $message .= ' · Alter: ' . $ageDays . ' Tage';
            $push('diagnostics_backup_latest', 'Backup-Prüfung', $status, $message, ['latest' => $latest], 'Diagnose Pro: Backup');
        } catch (Throwable $e) {
            $push('diagnostics_backup_latest', 'Backup-Prüfung', 'warning', $e->getMessage(), [], 'Diagnose Pro: Backup');
        }
    }

    private static function runCalendarConsistencyChecks(callable $push): void
    {
        try {
            if (!self::tableExists('bookings')) {
                $push('calendar_consistency', 'Kalender-Konsistenz', 'info', 'Tabelle bookings fehlt; Kalenderprüfung übersprungen.', [], 'Diagnose Pro: Kalender');
                return;
            }
            $activeSql = "status NOT IN ('cancelled','rejected')";
            if (self::columnExists('bookings', 'deleted_at')) $activeSql .= " AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00')";
            $invalidDates = (int)db()->query("SELECT COUNT(*) FROM bookings WHERE $activeSql AND (arrival IS NULL OR departure IS NULL OR departure<=arrival)")->fetchColumn();
            $unassigned = self::columnExists('bookings', 'apartment_id') ? (int)db()->query("SELECT COUNT(*) FROM bookings WHERE $activeSql AND apartment_id IS NULL")->fetchColumn() : 0;
            $overlap = 0;
            if (self::columnExists('bookings', 'apartment_id')) {
                $activeB1 = "b1.status NOT IN ('cancelled','rejected')";
                $activeB2 = "b2.status NOT IN ('cancelled','rejected')";
                if (self::columnExists('bookings', 'deleted_at')) {
                    $activeB1 .= " AND (b1.deleted_at IS NULL OR b1.deleted_at='0000-00-00 00:00:00')";
                    $activeB2 .= " AND (b2.deleted_at IS NULL OR b2.deleted_at='0000-00-00 00:00:00')";
                }
                $overlap = (int)db()->query("SELECT COUNT(*) FROM bookings b1 JOIN bookings b2 ON b1.id<b2.id AND b1.apartment_id=b2.apartment_id AND b1.apartment_id IS NOT NULL AND b1.arrival < b2.departure AND b2.arrival < b1.departure WHERE $activeB1 AND $activeB2")->fetchColumn();
            }
            $status = ($invalidDates === 0 && $unassigned === 0 && $overlap === 0) ? 'ok' : 'warning';
            $push('calendar_consistency', 'Kalender-Konsistenzprüfung', $status, $overlap . ' mögliche Überbuchungen · ' . $unassigned . ' ohne Wohnung · ' . $invalidDates . ' ungültige Datumsbereiche', ['overlaps' => $overlap, 'unassigned' => $unassigned, 'invalid_dates' => $invalidDates], 'Diagnose Pro: Kalender');
        } catch (Throwable $e) {
            $push('calendar_consistency', 'Kalender-Konsistenzprüfung', 'warning', $e->getMessage(), [], 'Diagnose Pro: Kalender');
        }
    }

    private static function runHousekeepingDiagnostics(callable $push): void
    {
        try {
            if (!self::tableExists('housekeeping_tasks')) {
                $push('housekeeping_pro_status', 'Housekeeping-Diagnose', 'info', 'Tabelle housekeeping_tasks nicht vorhanden.', [], 'Diagnose Pro: Housekeeping');
                return;
            }
            $open = (int)db()->query("SELECT COUNT(*) FROM housekeeping_tasks WHERE status IN ('open','assigned','in_progress')")->fetchColumn();
            $doneNoRelease = (int)db()->query("SELECT COUNT(*) FROM housekeeping_tasks WHERE status IN ('done','inspected','ready') AND released_at IS NULL")->fetchColumn();
            $oldOpen = (int)db()->query("SELECT COUNT(*) FROM housekeeping_tasks WHERE status IN ('open','assigned','in_progress','done','inspected','ready') AND task_date < DATE_SUB(CURDATE(), INTERVAL 2 DAY) AND released_at IS NULL")->fetchColumn();
            $status = ($oldOpen > 0 || $doneNoRelease > 0) ? 'warning' : 'ok';
            $push('housekeeping_pro_status', 'Housekeeping-Diagnose', $status, $open . ' offen/in Arbeit · ' . $doneNoRelease . ' warten auf finale Freigabe · ' . $oldOpen . ' älter als 2 Tage offen', ['open' => $open, 'pending_release' => $doneNoRelease, 'old_open' => $oldOpen], 'Diagnose Pro: Housekeeping');
        } catch (Throwable $e) {
            $push('housekeeping_pro_status', 'Housekeeping-Diagnose', 'warning', $e->getMessage(), [], 'Diagnose Pro: Housekeeping');
        }
    }

    private static function runPerformanceDiagnostics(callable $push): void
    {
        $start = microtime(true);
        try {
            db()->query('SELECT 1')->fetchColumn();
            $dbMs = (int)round((microtime(true) - $start) * 1000);
            $push('performance_db_response', 'Performance: DB-Antwortzeit', $dbMs < 200 ? 'ok' : ($dbMs < 800 ? 'warning' : 'error'), $dbMs . ' ms für SELECT 1', ['ms' => $dbMs], 'Diagnose Pro: Performance');
        } catch (Throwable $e) {
            $push('performance_db_response', 'Performance: DB-Antwortzeit', 'error', $e->getMessage(), [], 'Diagnose Pro: Performance');
        }
        $memory = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = self::iniBytes((string)ini_get('memory_limit'));
        $ratio = ($limit > 0) ? $peak / $limit : 0;
        $status = ($limit <= 0 || $ratio < 0.70) ? 'ok' : ($ratio < 0.90 ? 'warning' : 'error');
        $push('performance_php_memory', 'Performance: PHP-Speicher', $status, 'aktuell ' . self::bytes($memory) . ' · Spitze ' . self::bytes($peak) . ' · Limit ' . ($limit === -1 ? 'unbegrenzt' : self::bytes($limit)), ['usage' => $memory, 'peak' => $peak, 'limit' => $limit], 'Diagnose Pro: Performance');
        $push('performance_opcache', 'Performance: OPcache', function_exists('opcache_get_status') ? 'ok' : 'info', function_exists('opcache_get_status') ? 'OPcache-Funktion verfügbar' : 'OPcache-Status nicht abrufbar; nicht zwingend ein Fehler.', [], 'Diagnose Pro: Performance');
    }

    private static function runDatabaseChecks(callable $push): void
    {
        $expected = self::expectedSchema();
        foreach ($expected as $table => $columns) {
            try {
                $stmt = db()->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
                $stmt->execute([$table]);
                $present = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (!$present) {
                    $push('table_' . $table, 'Tabelle ' . $table, 'error', 'fehlt', ['missing_table' => $table], 'Datenbank');
                    continue;
                }
                $missing = array_values(array_diff($columns, $present));
                $push('table_' . $table, 'Tabelle ' . $table, $missing ? 'error' : 'ok', $missing ? 'Fehlende Spalten: ' . implode(', ', $missing) : 'vollständig', ['missing_columns' => $missing], 'Datenbank');
            } catch (Throwable $e) {
                $push('table_' . $table, 'Tabelle ' . $table, 'error', $e->getMessage(), [], 'Datenbank');
            }
        }
        try {
            if (self::tableExists('schema_migrations')) {
                $count = (int)db()->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
                $push('schema_migrations_count', 'Migrationseinträge', $count > 0 ? 'ok' : 'warning', $count . ' Einträge', [], 'Datenbank');
            }
        } catch (Throwable $e) {
            $push('schema_migrations_count', 'Migrationseinträge', 'warning', $e->getMessage(), [], 'Datenbank');
        }
    }

    private static function expectedSchema(): array
    {
        return [
            'settings' => ['setting_key','setting_value','updated_at'],
            'schema_migrations' => ['version','applied_at'],
            'users' => ['id','name','email','role','active'],
            'user_permission_overrides' => ['user_id','capability','allowed','updated_by','updated_at'],
            'houses' => ['id','name','code','active','default_checkin_time','default_checkout_time'],
            'apartment_types' => ['id','name','code','max_occupancy','standard_occupancy','allow_capacity_override','public_active','standard_price','default_min_stay','active'],
            'apartments' => ['id','house_id','apartment_type_id','apartment_number','code','status','min_stay_override'],
            'guests' => ['id','first_name','last_name','email','phone','language','country','category_id','preferences'],
            'booking_channels' => ['id','name','code','color','channel_category','default_accounting_mode','active'],
            'bookings' => ['id','reference','source_offer_id','guest_id','apartment_id','apartment_type_id','arrival','departure','status','accounting_mode','billing_excluded_reason','payment_status','total_price','confirmed_at','confirmed_by','confirmation_email_sent_at','deposit_required','deposit_due_date','deposit_status','remaining_due_date','remaining_status','is_upgrade','upgrade_note'],
            'offers' => ['id','offer_number','guest_name','guest_email','language','apartment_type_id','apartment_id','status','valid_until','total_amount','public_token_hash','public_token_encrypted','booking_id'],
            'offer_events' => ['id','offer_id','event_type','note','created_at'],
            'booking_payment_schedule' => ['id','booking_id','installment_type','label','amount','due_date','status','paid_amount','waived_reason'],
            'booking_payments' => ['id','booking_id','payment_number','payment_date','amount','payment_method','reference','status'],
            'booking_payment_allocations' => ['id','payment_id','schedule_id','amount'],
            'booking_documents' => ['id','booking_id','document_type','document_number','language','title','html_snapshot','pdf_path','checksum_sha256','status','sent_at'],
            'booking_customer_access' => ['id','booking_id','token_hash','token_encrypted','active','valid_until','last_viewed_at'],
            'document_templates' => ['id','channel','code','name','category','context_type','language','status','subject_template','title_template','body_html','page_settings_json','layout_json','show_in_portal'],
            'mail_template_attachments' => ['id','mail_key','document_template_id','active','sort_order','language_mode','generation_mode','store_copy'],
            'booking_checkins' => ['booking_id','status','planned_arrival_time','vehicle_plate','special_requests','consent_privacy','consent_house_rules','submitted_at','reviewed_at','reviewed_by'],
            'booking_checkin_uploads' => ['id','booking_id','file_path','original_name','mime_type','size_bytes','uploaded_by_guest','created_at'],
            'checkin_text_templates' => ['language','email_subject','email_intro','reminder_text'],
            'mail_settings' => ['id','active','host','port','encryption','password_encrypted','from_email'],
            'communication_log' => ['id','channel','entity_type','entity_id','recipient_address','subject','message_excerpt','message_body','status','detail','created_at'],
            'housekeeping_teams' => ['id','name','code','email','whatsapp_number','active'],
            'housekeeping_members' => ['id','team_id','user_id','name','email','whatsapp_number','active','can_inspect','can_mark_ready'],
            'housekeeping_tasks' => ['id','apartment_id','task_date','status','team_id','member_id','checklist_done_json','completion_notes','released_at','released_by'],
            'site_pages' => ['id','system_key','slug','title_fallback','page_type','status','show_header','show_footer','sort_order'],
            'site_page_blocks' => ['id','page_id','block_type','settings_json','active','sort_order'],
            'site_media' => ['id','file_path','thumb_path','original_name','mime_type','size_bytes'],
            'apartment_type_images' => ['id','apartment_type_id','file_path','thumb_path','mime_type','alt_text_json','is_cover','sort_order'],
            'audit_log' => ['id','entity_type','action','created_at'],
            'app_errors' => ['id','request_id','message','created_at'],
            'system_backups' => ['id','filename','created_at'],
        ];
    }

    private static function runFileChecks(callable $push): void
    {
        $requiredFiles = [
            'admin/api.php', 'admin/index.php', 'admin/api-v232.php', 'assets/admin-v232.js', 'admin/api-v236-tasks.php', 'assets/admin-v236-task-center.js', 'admin/api-v235.php', 'assets/admin-v235.js',
            'src/Services/BookingWorkflowService.php', 'src/Services/CommunicationLogger.php', 'src/Services/CheckinService.php', 'src/Services/CheckinDocumentService.php',
            'admin/billing-dokument.php', 'admin/checkin-dokument.php', 'kunde-dokument.php', 'kunde.php', 'checkin.php',
            'database/schema.sql', 'VERSION.txt', 'admin/VERSION.txt', 'PROJECT_STATUS.md', 'CHANGELOG.md',
            'update-rettung.php', 'admin/update-rettung.php', 'admin/api-v236-delete-center.php', 'assets/admin-v236-delete-center.js',
            'assets/admin-v236-studio-pro.js', 'assets/admin-v236-studio-page-actions.js', 'assets/admin-v236-website-templates.js',
        ];
        foreach ($requiredFiles as $file) {
            $push('file_' . str_replace(['/', '.'], '_', $file), 'Datei ' . $file, is_file(root_path($file)) ? 'ok' : 'error', is_file(root_path($file)) ? 'vorhanden' : 'fehlt', [], 'Dateien');
        }
        foreach (['storage', 'storage/logs', 'storage/backups', 'storage/tmp', 'storage/type-images', 'storage/site-images', 'storage/site-images/thumbs', 'storage/documents', 'storage/documents/booking-confirmations'] as $folder) {
            $path = root_path($folder);
            $push('path_' . str_replace('/', '_', $folder), 'Schreibrecht ' . $folder, is_dir($path) && is_writable($path) ? 'ok' : 'error', is_dir($path) ? (is_writable($path) ? 'beschreibbar' : 'nicht beschreibbar') : 'Ordner fehlt', ['path' => $path], 'Dateien');
        }
        $index = (string)@file_get_contents(root_path('admin/index.php'));
        $push('admin_assets_v232', 'Admin-Skripte V2.3.2 geladen', str_contains($index, 'admin-v232.js') ? 'ok' : 'warning', str_contains($index, 'admin-v232.js') ? 'admin-v232.js ist eingebunden.' : 'admin-v232.js fehlt in admin/index.php.', [], 'Dateien');
        $push('admin_assets_v233', 'Admin-Skripte V2.3.3 geladen', str_contains($index, 'admin-v233.js') ? 'ok' : 'warning', str_contains($index, 'admin-v233.js') ? 'admin-v233.js ist eingebunden.' : 'admin-v233.js fehlt in admin/index.php.', [], 'Dateien');
        $push('admin_assets_v235', 'Admin-Skripte V2.3.5 geladen', str_contains($index, 'admin-v235.js') ? 'ok' : 'warning', str_contains($index, 'admin-v235.js') ? 'admin-v235.js ist eingebunden.' : 'admin-v235.js fehlt in admin/index.php.', [], 'Dateien');
        $push('admin_assets_delete_center', 'Löschcenter-Skript geladen', str_contains($index, 'admin-v236-delete-center.js') ? 'ok' : 'warning', str_contains($index, 'admin-v236-delete-center.js') ? 'admin-v236-delete-center.js ist eingebunden.' : 'admin-v236-delete-center.js fehlt in admin/index.php.', [], 'Dateien');
        $push('admin_assets_studio_pro', 'Studio-Editor-Skripte geladen', (str_contains($index, 'admin-v236-studio-pro.js') && str_contains($index, 'admin-v236-studio-page-actions.js') && str_contains($index, 'admin-v236-website-templates.js')) ? 'ok' : 'warning', 'Studio Pro: ' . (str_contains($index, 'admin-v236-studio-pro.js') ? 'ja' : 'nein') . ' · Seitenaktionen: ' . (str_contains($index, 'admin-v236-studio-page-actions.js') ? 'ja' : 'nein') . ' · Website-Vorlagen: ' . (str_contains($index, 'admin-v236-website-templates.js') ? 'ja' : 'nein'), [], 'Dateien');
    }

    private static function runDocumentChecks(callable $push): void
    {
        $docHtaccess = root_path('storage/documents/.htaccess');
        $ht = is_file($docHtaccess) ? (string)@file_get_contents($docHtaccess) : '';
        $private = str_contains($ht, 'Require all denied') || str_contains($ht, 'Deny from all');
        $push('documents_private', 'Dokumentordner geschützt', $private ? 'ok' : 'warning', $private ? 'Direkter Storage-Zugriff ist gesperrt; PDFs müssen über App-Links geöffnet werden.' : 'storage/documents ist nicht eindeutig geschützt.', [], 'Dokumente/PDF');
        $push('billing_document_endpoint', 'Admin-PDF-Endpunkt Abrechnung', is_file(root_path('admin/billing-dokument.php')) ? 'ok' : 'error', is_file(root_path('admin/billing-dokument.php')) ? 'vorhanden' : 'fehlt', [], 'Dokumente/PDF');
        $push('customer_document_endpoint', 'Kunden-PDF-Endpunkt', is_file(root_path('kunde-dokument.php')) ? 'ok' : 'error', is_file(root_path('kunde-dokument.php')) ? 'vorhanden' : 'fehlt', [], 'Dokumente/PDF');
        try {
            if (self::tableExists('booking_documents')) {
                $total = (int)db()->query('SELECT COUNT(*) FROM booking_documents')->fetchColumn();
                $withPdf = (int)db()->query("SELECT COUNT(*) FROM booking_documents WHERE COALESCE(pdf_path,'')<>''")->fetchColumn();
                $missingFiles = 0;
                $sample = db()->query("SELECT id,pdf_path FROM booking_documents WHERE COALESCE(pdf_path,'')<>'' ORDER BY id DESC LIMIT 50")->fetchAll();
                foreach ($sample as $row) {
                    $path = (string)$row['pdf_path'];
                    $abs = root_path($path);
                    if (!is_file($abs)) $missingFiles++;
                }
                $push('document_records', 'Dokumentdatensätze', 'ok', $total . ' Dokumente · ' . $withPdf . ' mit PDF-Pfad', [], 'Dokumente/PDF');
                $push('document_pdf_files_sample', 'PDF-Dateien Stichprobe', $missingFiles === 0 ? 'ok' : 'warning', $missingFiles === 0 ? 'letzte PDF-Pfade gefunden' : $missingFiles . ' von max. 50 PDF-Pfaden zeigen auf fehlende Dateien', [], 'Dokumente/PDF');
            }
        } catch (Throwable $e) {
            $push('document_records', 'Dokumentdatensätze', 'warning', $e->getMessage(), [], 'Dokumente/PDF');
        }
    }



    private static function runCustomerPortalFlowChecks(callable $push): void
    {
        // Diese Prüfgruppe ergänzt die vorhandene technische Diagnose um den echten neuen Ablauf:
        // Buchung -> Kundentoken -> Dokumentkarten -> Mailhistorie -> Builder-Module.
        try {
            if (self::tableExists('booking_customer_access') && self::tableExists('bookings')) {
                $active = (int)db()->query("SELECT COUNT(*) FROM booking_customer_access a JOIN bookings b ON b.id=a.booking_id WHERE a.active=1 AND (a.valid_until IS NULL OR a.valid_until>=CURDATE())")->fetchColumn();
                $expired = (int)db()->query("SELECT COUNT(*) FROM booking_customer_access WHERE active=1 AND valid_until IS NOT NULL AND valid_until<CURDATE()")->fetchColumn();
                $withoutBooking = (int)db()->query("SELECT COUNT(*) FROM booking_customer_access a LEFT JOIN bookings b ON b.id=a.booking_id WHERE b.id IS NULL")->fetchColumn();
                $push('portal_flow_active_tokens', 'Kundenbereich: gültige Buchungslinks', $active > 0 ? 'ok' : 'info', $active . ' gültige aktive Links · ' . $expired . ' abgelaufene aktive Links', ['expired_active_links' => $expired], 'Kundenbereich-Fluss');
                $push('portal_flow_orphan_tokens', 'Kundenbereich: Token-Zuordnung', $withoutBooking === 0 ? 'ok' : 'error', $withoutBooking === 0 ? 'alle Token gehören zu Buchungen' : $withoutBooking . ' Token ohne Buchung gefunden', [], 'Kundenbereich-Fluss');
                $sample = db()->query("SELECT a.id access_id,a.booking_id,b.reference,b.arrival,b.departure FROM booking_customer_access a JOIN bookings b ON b.id=a.booking_id WHERE a.active=1 ORDER BY a.id DESC LIMIT 1")->fetch();
                $push('portal_flow_sample_booking', 'Kundenbereich: Beispielbuchung vorhanden', $sample ? 'ok' : 'info', $sample ? ('Beispiel: Buchung #' . $sample['booking_id'] . ' · ' . ($sample['reference'] ?? 'ohne Referenz')) : 'kein aktiver Kundenlink für einen Live-Test gefunden', $sample ?: [], 'Kundenbereich-Fluss');
            } else {
                $push('portal_flow_active_tokens', 'Kundenbereich: gültige Buchungslinks', 'warning', 'booking_customer_access oder bookings fehlt', [], 'Kundenbereich-Fluss');
            }
        } catch (Throwable $e) {
            $push('portal_flow_active_tokens', 'Kundenbereich: gültige Buchungslinks', 'warning', $e->getMessage(), [], 'Kundenbereich-Fluss');
        }

        try {
            if (self::tableExists('booking_documents')) {
                $total = (int)db()->query('SELECT COUNT(*) FROM booking_documents')->fetchColumn();
                $linkedToActivePortal = self::tableExists('booking_customer_access')
                    ? (int)db()->query("SELECT COUNT(*) FROM booking_documents d JOIN booking_customer_access a ON a.booking_id=d.booking_id AND a.active=1")->fetchColumn()
                    : 0;
                $withoutType = self::columnExists('booking_documents', 'document_type')
                    ? (int)db()->query("SELECT COUNT(*) FROM booking_documents WHERE COALESCE(document_type,'')='' OR document_type='document'")->fetchColumn()
                    : 0;
                $missingPdf = self::columnExists('booking_documents', 'pdf_path')
                    ? (int)db()->query("SELECT COUNT(*) FROM booking_documents WHERE COALESCE(pdf_path,'')='' AND COALESCE(html_snapshot,'')='' ")->fetchColumn()
                    : 0;
                $badFiles = 0;
                if (self::columnExists('booking_documents', 'pdf_path')) {
                    foreach (db()->query("SELECT pdf_path FROM booking_documents WHERE COALESCE(pdf_path,'')<>'' ORDER BY id DESC LIMIT 50")->fetchAll() as $row) {
                        $path = (string)($row['pdf_path'] ?? '');
                        if ($path !== '' && !is_file(root_path($path))) $badFiles++;
                    }
                }
                $push('portal_documents_linked', 'Kundenbereich: Dokumentkarten zu Buchungen', $total > 0 ? 'ok' : 'info', $total . ' Dokumente · ' . $linkedToActivePortal . ' bei aktiven Kundenlinks', ['linked_active_portal' => $linkedToActivePortal], 'Kundenbereich-Dokumente');
                $push('portal_documents_types', 'Kundenbereich: fachliche Dokumenttypen', $withoutType === 0 ? 'ok' : 'warning', $withoutType === 0 ? 'Dokumenttypen wirken eindeutig' : $withoutType . ' Dokumente ohne klaren fachlichen Typ', [], 'Kundenbereich-Dokumente');
                $push('portal_documents_files', 'Kundenbereich: PDF-/HTML-Inhalt', ($missingPdf === 0 && $badFiles === 0) ? 'ok' : 'warning', $missingPdf . ' Dokumente ohne PDF/HTML · ' . $badFiles . ' fehlende PDF-Dateien in Stichprobe', ['missing_content' => $missingPdf, 'missing_files_sample' => $badFiles], 'Kundenbereich-Dokumente');
            } else {
                $push('portal_documents_linked', 'Kundenbereich: Dokumentkarten zu Buchungen', 'error', 'Tabelle booking_documents fehlt', [], 'Kundenbereich-Dokumente');
            }
        } catch (Throwable $e) {
            $push('portal_documents_linked', 'Kundenbereich: Dokumentkarten zu Buchungen', 'warning', $e->getMessage(), [], 'Kundenbereich-Dokumente');
        }

        try {
            if (self::tableExists('communication_log')) {
                $bookingMails = self::columnExists('communication_log', 'entity_type')
                    ? (int)db()->query("SELECT COUNT(*) FROM communication_log WHERE entity_type='booking' AND entity_id IS NOT NULL")->fetchColumn()
                    : 0;
                $missingSubject = self::columnExists('communication_log', 'subject')
                    ? (int)db()->query("SELECT COUNT(*) FROM communication_log WHERE entity_type='booking' AND (subject IS NULL OR subject='')")->fetchColumn()
                    : 0;
                $hasBody = self::columnExists('communication_log', 'message_body');
                $push('portal_mailhistory_linked', 'Kundenbereich: Mailhistorie pro Buchung', $bookingMails > 0 ? 'ok' : 'info', $bookingMails . ' buchungsbezogene E-Mails im Protokoll', [], 'Kundenbereich-Mailhistorie');
                $push('portal_mailhistory_fields', 'Kundenbereich: Mailhistorie Datenqualität', ($missingSubject === 0 && $hasBody) ? 'ok' : 'warning', ($hasBody ? 'message_body vorhanden' : 'message_body fehlt') . ' · ' . $missingSubject . ' Buchungsmails ohne Betreff', ['missing_subject' => $missingSubject], 'Kundenbereich-Mailhistorie');
            } else {
                $push('portal_mailhistory_linked', 'Kundenbereich: Mailhistorie pro Buchung', 'warning', 'communication_log fehlt', [], 'Kundenbereich-Mailhistorie');
            }
        } catch (Throwable $e) {
            $push('portal_mailhistory_linked', 'Kundenbereich: Mailhistorie pro Buchung', 'warning', $e->getMessage(), [], 'Kundenbereich-Mailhistorie');
        }

        try {
            if (self::tableExists('mail_template_attachments') && self::tableExists('document_templates')) {
                $total = (int)db()->query('SELECT COUNT(*) FROM mail_template_attachments')->fetchColumn();
                $active = (int)db()->query('SELECT COUNT(*) FROM mail_template_attachments WHERE active=1')->fetchColumn();
                $notPdf = (int)db()->query("SELECT COUNT(*) FROM mail_template_attachments a LEFT JOIN document_templates t ON t.id=a.document_template_id WHERE t.id IS NULL OR t.channel<>'pdf'")->fetchColumn();
                $archived = (int)db()->query("SELECT COUNT(*) FROM mail_template_attachments a JOIN document_templates t ON t.id=a.document_template_id WHERE t.status='archived'")->fetchColumn();
                $multi = (int)db()->query("SELECT COUNT(*) FROM (SELECT mail_key,COUNT(*) c FROM mail_template_attachments WHERE active=1 GROUP BY mail_key HAVING c>1) x")->fetchColumn();
                $push('mail_fixed_attachments', 'Feste Mailanhänge: PDF-Vorlagen', ($notPdf === 0 && $archived === 0) ? 'ok' : 'error', $total . ' Zuordnungen · ' . $active . ' aktiv · ' . $notPdf . ' nicht-PDF/defekt · ' . $archived . ' archiviert', ['multi_attachment_mail_keys' => $multi], 'E-Mail-Anhänge');
            } else {
                $push('mail_fixed_attachments', 'Feste Mailanhänge: PDF-Vorlagen', 'info', 'Tabelle für feste Anhänge oder Dokumentvorlagen fehlt noch', [], 'E-Mail-Anhänge');
            }
        } catch (Throwable $e) {
            $push('mail_fixed_attachments', 'Feste Mailanhänge: PDF-Vorlagen', 'warning', $e->getMessage(), [], 'E-Mail-Anhänge');
        }

        try {
            if (self::tableExists('document_templates')) {
                $pdfTemplates = (int)db()->query("SELECT COUNT(*) FROM document_templates WHERE channel='pdf' AND status<>'archived'")->fetchColumn();
                $rawCodeRisk = (int)db()->query("SELECT COUNT(*) FROM document_templates WHERE channel='pdf' AND status<>'archived' AND (body_html LIKE '%<style%' OR body_html LIKE '%</style>%' OR body_html LIKE '%{\\\"%' OR body_html LIKE '%css%:%')")->fetchColumn();
                $emailTemplates = (int)db()->query("SELECT COUNT(*) FROM document_templates WHERE channel='email' AND status<>'archived'")->fetchColumn();
                $emailNoSubject = (int)db()->query("SELECT COUNT(*) FROM document_templates WHERE channel='email' AND status<>'archived' AND COALESCE(subject_template,'')='' ")->fetchColumn();
                $push('pdf_template_sanity', 'PDF-Erzeugung: Vorlageninhalt', ($pdfTemplates > 0 && $rawCodeRisk === 0) ? 'ok' : ($pdfTemplates > 0 ? 'warning' : 'info'), $pdfTemplates . ' aktive PDF-Vorlagen · ' . $rawCodeRisk . ' mit möglichem rohem CSS/Code im Inhalt', ['raw_code_risk' => $rawCodeRisk], 'PDF-Erzeugung');
                $push('email_preview_templates', 'E-Mail-Vorschau: Vorlagenbasis', ($emailTemplates > 0 && $emailNoSubject === 0) ? 'ok' : ($emailTemplates > 0 ? 'warning' : 'info'), $emailTemplates . ' aktive E-Mail-Vorlagen · ' . $emailNoSubject . ' ohne Betreff', [], 'E-Mail-Vorschau');
            }
        } catch (Throwable $e) {
            $push('pdf_template_sanity', 'PDF-/E-Mail-Vorlagen prüfen', 'warning', $e->getMessage(), [], 'PDF-Erzeugung');
        }

        try {
            if (self::tableExists('document_templates')) {
                $customerTemplates = db()->query("SELECT id,name,layout_json FROM document_templates WHERE channel='customer' AND status<>'archived' ORDER BY sort_order,id LIMIT 200")->fetchAll();
                $invalidJson = 0; $blocks = 0; $missingMedia = 0; $emptyActions = 0;
                foreach ($customerTemplates as $tpl) {
                    $layout = json_decode((string)($tpl['layout_json'] ?? ''), true);
                    if (!is_array($layout)) { $invalidJson++; continue; }
                    $items = is_array($layout['blocks'] ?? null) ? $layout['blocks'] : [];
                    $blocks += count($items);
                    foreach ($items as $block) {
                        if (!is_array($block)) continue;
                        $settings = is_array($block['settings'] ?? null) ? $block['settings'] : $block;
                        foreach (['image','image_url','file_path','media_path','background_image'] as $key) {
                            $val = trim((string)($settings[$key] ?? ''));
                            if ($val !== '' && !preg_match('~^https?://~i', $val) && !is_file(root_path(ltrim($val,'/')))) $missingMedia++;
                        }
                        $type = (string)($block['type'] ?? $block['block_type'] ?? '');
                        if (in_array($type, ['actions','button','cta'], true)) {
                            $href = trim((string)($settings['href'] ?? $settings['url'] ?? $settings['link'] ?? ''));
                            if ($href === '') $emptyActions++;
                        }
                    }
                }
                $push('customer_builder_layouts', 'Kundenbereich-Builder: Layoutdaten', ($invalidJson === 0 && count($customerTemplates)>0) ? 'ok' : (count($customerTemplates)>0 ? 'warning' : 'info'), count($customerTemplates) . ' Kundenbereich-Vorlagen · ' . $blocks . ' Blöcke · ' . $invalidJson . ' kaputte Layout-JSON', ['blocks' => $blocks], 'Kundenbereich-Builder');
                $customerMediaStatus = $missingMedia > 0 ? 'warning' : ($emptyActions > 0 ? 'info' : 'ok');
                $customerMediaMessage = $missingMedia . ' fehlende Medienpfade · ' . $emptyActions . ' Aktionsblöcke ohne Link';
                if ($missingMedia === 0 && $emptyActions > 0) $customerMediaMessage .= ' · kein technischer Fehler, nur inhaltlich prüfen';
                $push('customer_builder_media', 'Kundenbereich-Builder: Medien/Buttons', $customerMediaStatus, $customerMediaMessage, ['missing_media' => $missingMedia, 'empty_action_blocks' => $emptyActions], 'Kundenbereich-Builder');
            }
        } catch (Throwable $e) {
            $push('customer_builder_layouts', 'Kundenbereich-Builder: Layoutdaten', 'warning', $e->getMessage(), [], 'Kundenbereich-Builder');
        }
    }

    private static function runBusinessDataChecks(callable $push): void
    {
        try {
            if (self::tableExists('bookings')) {
                $bookings = (int)db()->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
                $push('bookings_count', 'Buchungen', $bookings > 0 ? 'ok' : 'info', $bookings . ' Buchungen', [], 'Abläufe');
                if (self::columnExists('bookings', 'accounting_mode')) {
                    $external = (int)db()->query("SELECT COUNT(*) FROM bookings WHERE accounting_mode='external' AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00')")->fetchColumn();
                    $none = (int)db()->query("SELECT COUNT(*) FROM bookings WHERE accounting_mode='none' AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00')")->fetchColumn();
                    $wrongSchedules = self::tableExists('booking_payment_schedule') ? (int)db()->query("SELECT COUNT(*) FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id WHERE b.accounting_mode IN ('external','none') AND s.status IN ('open','partial','overdue')")->fetchColumn() : 0;
                    $push('booking_accounting_modes', 'Buchungsquelle/Abrechnungsart', $wrongSchedules === 0 ? 'ok' : 'warning', $external . ' extern abgerechnet · ' . $none . ' ohne Abrechnung · ' . $wrongSchedules . ' interne offene Zahlungsziele bei nicht intern abrechnungsrelevanten Buchungen', ['external'=>$external,'none'=>$none,'wrong_schedules'=>$wrongSchedules], 'Produktreife Phase 2: Kern-PMS');
                }
                if (self::columnExists('bookings', 'source_offer_id')) {
                    $acceptedNoApt = (int)db()->query("SELECT COUNT(*) FROM bookings WHERE status NOT IN ('cancelled','rejected') AND apartment_id IS NULL AND (deleted_at IS NULL OR deleted_at='0000-00-00 00:00:00')")->fetchColumn();
                    $push('bookings_unassigned', 'Nicht zugeordnete aktive Buchungen', $acceptedNoApt === 0 ? 'ok' : 'warning', $acceptedNoApt . ' Buchungen ohne konkrete Wohnung', ['repair_action' => 'data_repair_overview_v235'], 'Abläufe');
                }
            }
            if (self::tableExists('booking_payment_schedule') && self::tableExists('booking_payments')) {
                $openSchedules = (int)db()->query("SELECT COUNT(*) FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id WHERE COALESCE(b.accounting_mode,'internal')='internal' AND (s.status IN ('open','partial','overdue') OR COALESCE(s.paid_amount,0) < s.amount)")->fetchColumn();
                $push('payment_schedules_open', 'Interne offene Zahlungsziele', $openSchedules === 0 ? 'ok' : 'info', $openSchedules . ' intern abrechnungsrelevante offene oder teiloffene Zahlungsziele', ['scope'=>'Nur interne Abrechnung'], 'Abläufe');
                if (self::columnExists('bookings','total_price')) {
                    $mismatch = (int)db()->query("SELECT COUNT(*) FROM bookings b LEFT JOIN (SELECT booking_id,SUM(amount) scheduled FROM booking_payment_schedule GROUP BY booking_id) s ON s.booking_id=b.id WHERE b.status NOT IN ('cancelled','rejected') AND COALESCE(b.accounting_mode,'internal')='internal' AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND b.total_price > 0 AND s.scheduled IS NOT NULL AND ABS(b.total_price - s.scheduled) > 0.05")->fetchColumn();
                    $push('payment_schedule_mismatch', 'Zahlungsplan gegen Buchungssumme', $mismatch === 0 ? 'ok' : 'warning', $mismatch === 0 ? 'keine Abweichung gefunden' : $mismatch . ' Buchungen mit abweichendem Zahlungsplan', [], 'Abläufe');
                    $dueOrder = (int)db()->query("SELECT COUNT(*) FROM bookings b JOIN booking_payment_schedule d ON d.booking_id=b.id AND d.installment_type='deposit' JOIN booking_payment_schedule r ON r.booking_id=b.id AND r.installment_type='remaining' WHERE b.status NOT IN ('cancelled','rejected') AND COALESCE(b.accounting_mode,'internal')='internal' AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00') AND d.due_date IS NOT NULL AND r.due_date IS NOT NULL AND r.due_date < d.due_date")->fetchColumn();
                    $push('payment_schedule_due_order', 'Zahlungsplan Fälligkeiten', $dueOrder === 0 ? 'ok' : 'warning', $dueOrder === 0 ? 'Anzahlung und Restbetrag wirken logisch.' : $dueOrder . ' Buchungen mit Restbetrag vor Anzahlung · Datenreparatur kann das korrigieren.', ['repair_action' => 'recalculate_payment_schedule_v235'], 'Abläufe');
                }
            }
            if (self::tableExists('booking_customer_access')) {
                $active = (int)db()->query('SELECT COUNT(*) FROM booking_customer_access WHERE active=1')->fetchColumn();
                $push('customer_portal_links', 'Aktive Kundenportal-Links', $active > 0 ? 'ok' : 'info', $active . ' aktive Links', [], 'Abläufe');
            }
            if (self::tableExists('booking_checkins')) {
                $open = (int)db()->query("SELECT COUNT(*) FROM booking_checkins WHERE status IN ('open','submitted')")->fetchColumn();
                $push('checkin_open', 'Online-Check-ins offen/in Prüfung', $open === 0 ? 'ok' : 'info', $open . ' offene oder zu prüfende Check-ins', [], 'Abläufe');
            }
            if (self::tableExists('housekeeping_tasks')) {
                $release = (int)db()->query("SELECT COUNT(*) FROM housekeeping_tasks WHERE status IN ('done','inspected','ready') AND released_at IS NULL")->fetchColumn();
                $push('housekeeping_release_pending', 'Housekeeping-Freigaben', $release === 0 ? 'ok' : 'info', $release . ' Aufgaben warten ggf. auf finale Freigabe', [], 'Abläufe');
            }
        } catch (Throwable $e) {
            $push('business_data_checks', 'Ablaufdaten prüfen', 'warning', $e->getMessage(), [], 'Abläufe');
        }
    }

    private static function runCommunicationChecks(callable $push): void
    {
        try {
            $smtp = SmtpMailer::settings(false);
            $smtpConfigured = trim((string)($smtp['host'] ?? '')) !== '' && trim((string)($smtp['from_email'] ?? '')) !== '';
            $push('smtp', 'E-Mail/SMTP', $smtpConfigured ? ((int)($smtp['active'] ?? 0) ? 'ok' : 'warning') : 'info', $smtpConfigured ? ((int)($smtp['active'] ?? 0) ? 'Konfiguriert und aktiviert; echten Versand mit Testmail prüfen.' : 'Konfiguriert, aber nicht aktiviert.') : 'Nicht konfiguriert.', ['host' => $smtp['host'] ?? '', 'from_email' => $smtp['from_email'] ?? ''], 'Kommunikation');
            $fromEmail = (string)($smtp['from_email'] ?? '');
            $domain = str_contains($fromEmail, '@') ? substr(strrchr($fromEmail, '@') ?: '', 1) : '';
            $push('mail_dns_notice', 'Spam-Hinweis SPF/DKIM/DMARC', 'info', $domain !== '' ? 'Für ' . $domain . ' bitte SPF, DKIM und DMARC im Hosting/DNS prüfen. Das ist eine externe DNS-/Mailserver-Prüfung und kein StayPilot-Dateifehler.' : 'Keine Absenderdomain erkennbar.', ['external_check' => true], 'Kommunikation');
        } catch (Throwable $e) {
            $push('smtp', 'E-Mail/SMTP', 'error', $e->getMessage(), [], 'Kommunikation');
        }
        try {
            if (self::tableExists('communication_log')) {
                $hasBody = self::columnExists('communication_log', 'message_body');
                $push('communication_body_column', 'Vollständiger E-Mail-Text im Protokoll', $hasBody ? 'ok' : 'warning', $hasBody ? 'message_body vorhanden' : 'message_body fehlt – E-Mail-Verlauf kann nur Auszüge zeigen.', [], 'Kommunikation');
                $total = (int)db()->query('SELECT COUNT(*) FROM communication_log')->fetchColumn();
                $failed = (int)db()->query("SELECT COUNT(*) FROM communication_log WHERE status COLLATE utf8mb4_unicode_ci IN ('failed' COLLATE utf8mb4_unicode_ci,'error' COLLATE utf8mb4_unicode_ci,'bounced' COLLATE utf8mb4_unicode_ci) OR status COLLATE utf8mb4_unicode_ci LIKE '%fail%' COLLATE utf8mb4_unicode_ci OR status COLLATE utf8mb4_unicode_ci LIKE '%error%' COLLATE utf8mb4_unicode_ci")->fetchColumn();
                $push('communication_log_count', 'Versandprotokoll', $total > 0 ? 'ok' : 'info', $total . ' Einträge · ' . $failed . ' Fehler/Fehlschläge', [], 'Kommunikation');
                try {
                    $collations = db()->query("SELECT COLLATION_NAME,COUNT(*) cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('communication_log','guests','bookings','offers','document_templates') AND CHARACTER_SET_NAME='utf8mb4' GROUP BY COLLATION_NAME ORDER BY COLLATION_NAME")->fetchAll();
                    $parts = array_map(static fn($r) => (string)$r['COLLATION_NAME'] . ': ' . (int)$r['cnt'], $collations);
                    $mixed = count($collations) > 1;
                    $push('communication_collations', 'Kommunikation: Datenbank-Collations', $mixed ? 'warning' : 'ok', $mixed ? 'Gemischte Collations erkannt · Kommunikationsabfragen werden in StayPilot abgesichert: ' . implode(' · ', $parts) : 'Collation einheitlich: ' . (string)($parts[0] ?? 'nicht ermittelbar'), ['collations'=>$collations], 'Kommunikation');
                } catch (Throwable $ignored) {}
            }
        } catch (Throwable $e) {
            $push('communication_log_count', 'Versandprotokoll', 'warning', $e->getMessage(), [], 'Kommunikation');
        }
    }

    private static function runSecurityAndCacheChecks(callable $push): void
    {
        $rootHt = is_file(root_path('.htaccess')) ? (string)@file_get_contents(root_path('.htaccess')) : '';
        $push('root_htaccess', 'Haupt-.htaccess', $rootHt !== '' ? 'ok' : 'warning', $rootHt !== '' ? 'vorhanden' : 'fehlt oder leer', [], 'Sicherheit/Cache');
        $scriptVersion = (string)(config()['app_version'] ?? '');
        $push('asset_cache_version', 'Cache-Busting für Admin-Assets', $scriptVersion !== '' ? 'ok' : 'warning', $scriptVersion !== '' ? '?v=' . $scriptVersion . ' wird verwendet. Nach Update trotzdem Browser hart neu laden.' : 'Version leer', [], 'Sicherheit/Cache');
        $publicStorageRisk = false;
        foreach (['storage/tmp/.htaccess','storage/backups/.htaccess','storage/documents/.htaccess','src/.htaccess','config/.htaccess','database/.htaccess'] as $file) {
            if (!is_file(root_path($file))) $publicStorageRisk = true;
        }
        $push('sensitive_htaccess', 'Schutzdateien für sensible Ordner', !$publicStorageRisk ? 'ok' : 'warning', !$publicStorageRisk ? 'vorhanden' : 'mindestens eine Schutzdatei fehlt', [], 'Sicherheit/Cache');
    }


    private static function runProductReadinessChecks(callable $push): void
    {
        self::runUploadCompletenessChecks($push);
        self::runReleaseIntegrityChecks($push);
        self::runRollbackReadinessChecks($push);
        self::runInstallerExposureChecks($push);
        self::runPermissionModelChecks($push);
        self::runOperationalWorkflowChecks($push);
        self::runProductPhaseReadinessChecks($push);
    }

    private static function runUploadCompletenessChecks(callable $push): void
    {
        $requiredFiles = [
            'VERSION.txt' => 'Versionsdatei im Stammverzeichnis',
            'admin/VERSION.txt' => 'Versionsdatei im Adminbereich',
            'config/config.php' => 'Hauptkonfiguration',
            'src/Services/SystemDiagnostics.php' => 'Systemdiagnose',
            'admin/api.php' => 'Admin-API-Hauptverteiler',
            'admin/api-v236-delete-center.php' => 'Löschcenter-API',
            'assets/admin-v236-delete-center.js' => 'Löschcenter-JavaScript',
            'assets/admin-v236-studio-pro.js' => 'Studio-Editor-JavaScript',
            'assets/admin-v236-studio-page-actions.js' => 'Studio-Seitenaktionen-JavaScript',
            'assets/admin-v236-website-templates.js' => 'Website-Vorlagen-JavaScript',
            'update-rettung.php' => 'Update-Rettung im Stammverzeichnis',
            'admin/update-rettung.php' => 'Update-Rettung im Adminbereich',
            'RELEASE_MANIFEST.json' => 'Release-Manifest mit Datei-Prüfsummen',
            'docs/UPDATE_ROLLBACK_READINESS_V236111.md' => 'Update-/Rollback-Dokumentation',
            'docs/BACKUP_RESTORE_RUNBOOK_V236112.md' => 'Backup-/Restore-Betriebshandbuch',
            'docs/ROLE_PORTAL_HARDENING_V236116.md' => 'Storno-/Lösch-/Archivlogik',
        ];
        $missing = [];
        $stale = [];
        foreach ($requiredFiles as $file => $label) {
            $path = root_path($file);
            if (!is_file($path)) {
                $missing[] = $file;
                continue;
            }
            if (str_ends_with($file, 'VERSION.txt')) {
                $content = trim((string)@file_get_contents($path));
                if ($content !== self::EXPECTED_APP_VERSION) {
                    $stale[] = $file . ' = ' . ($content !== '' ? $content : 'leer');
                }
            }
        }
        if ($missing) {
            $push('product_upload_files', 'Upload-Vollständigkeit: Pflichtdateien', 'error', 'Fehlende Dateien: ' . implode(', ', $missing), ['missing' => $missing], 'Produktreife');
        } elseif ($stale) {
            $push('product_upload_files', 'Upload-Vollständigkeit: Versionsdateien', 'warning', 'Versionsabweichung: ' . implode(', ', $stale), ['stale' => $stale], 'Produktreife');
        } else {
            $push('product_upload_files', 'Upload-Vollständigkeit: Pflichtdateien', 'ok', 'Alle wichtigen Dateien der aktuellen Version sind vorhanden.', ['files' => array_keys($requiredFiles)], 'Produktreife');
        }
    }

    private static function runReleaseIntegrityChecks(callable $push): void
    {
        $manifestFile = root_path('RELEASE_MANIFEST.json');
        if (!is_file($manifestFile)) {
            $push('product_release_manifest', 'Release-Integrität: Manifest', 'warning', 'RELEASE_MANIFEST.json fehlt. Vollständiger Upload kann nur über Einzeldateien geprüft werden.', [], 'Produktreife Phase 5: Marktreife');
            return;
        }
        $manifest = json_decode((string)@file_get_contents($manifestFile), true);
        if (!is_array($manifest)) {
            $push('product_release_manifest', 'Release-Integrität: Manifest', 'warning', 'RELEASE_MANIFEST.json ist nicht lesbar oder ungültig.', [], 'Produktreife Phase 5: Marktreife');
            return;
        }
        $version = (string)($manifest['version'] ?? '');
        $files = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
        $critical = [
            'VERSION.txt',
            'admin/VERSION.txt',
            'config/config.php',
            'src/Services/SystemDiagnostics.php',
            'src/Services/BackupManager.php',
            'src/Migrator.php',
            'admin/api.php',
            'update-rettung.php',
            'admin/update-rettung.php',
            '.htaccess',
            'admin/.htaccess',
        ];
        $missing = [];
        $changed = [];
        foreach ($critical as $file) {
            $path = root_path($file);
            if (!is_file($path)) {
                $missing[] = $file;
                continue;
            }
            $expectedHash = (string)($files[$file]['sha256'] ?? '');
            if ($expectedHash !== '') {
                $actualHash = hash_file('sha256', $path) ?: '';
                if (!hash_equals($expectedHash, $actualHash)) {
                    $changed[] = $file;
                }
            }
        }
        if ($version !== self::EXPECTED_APP_VERSION) {
            $push('product_release_manifest_version', 'Release-Integrität: Manifest-Version', 'warning', 'Manifest-Version ' . ($version !== '' ? $version : 'fehlt') . ' passt nicht zur erwarteten App-Version ' . self::EXPECTED_APP_VERSION . '.', ['manifest_version' => $version, 'expected' => self::EXPECTED_APP_VERSION], 'Produktreife Phase 5: Marktreife');
        } else {
            $push('product_release_manifest_version', 'Release-Integrität: Manifest-Version', 'ok', 'Manifest-Version passt zur App-Version ' . self::EXPECTED_APP_VERSION . '.', ['manifest_version' => $version], 'Produktreife Phase 5: Marktreife');
        }
        if ($missing) {
            $push('product_release_integrity', 'Release-Integrität: Kritische Dateien', 'error', 'Kritische Dateien fehlen: ' . implode(', ', $missing), ['missing' => $missing, 'changed' => $changed], 'Produktreife Phase 5: Marktreife');
        } elseif ($changed) {
            $push('product_release_integrity', 'Release-Integrität: Kritische Dateien', 'warning', 'Kritische Dateien weichen vom Release-Manifest ab: ' . implode(', ', $changed) . '. Das kann nach Hotfixes normal sein, muss aber bewusst sein.', ['changed' => $changed], 'Produktreife Phase 5: Marktreife');
        } else {
            $push('product_release_integrity', 'Release-Integrität: Kritische Dateien', 'ok', 'Kritische Dateien sind vorhanden und stimmen mit dem Release-Manifest überein.', ['checked' => $critical], 'Produktreife Phase 5: Marktreife');
        }
    }

    private static function runRollbackReadinessChecks(callable $push): void
    {
        $backupDir = root_path('storage/backups');
        $tmpDir = root_path('storage/tmp');
        $backupWritable = is_dir($backupDir) && is_writable($backupDir);
        $tmpWritable = is_dir($tmpDir) && is_writable($tmpDir);
        $push('product_rollback_storage', 'Rollback-Bereitschaft: Speicherorte', ($backupWritable && $tmpWritable) ? 'ok' : 'warning', 'storage/backups ' . ($backupWritable ? 'beschreibbar' : 'nicht beschreibbar') . ' · storage/tmp ' . ($tmpWritable ? 'beschreibbar' : 'nicht beschreibbar'), ['backup_dir' => $backupDir, 'tmp_dir' => $tmpDir], 'Produktreife Phase 5: Marktreife');

        $latest = null;
        try {
            $backups = BackupManager::list();
            $latest = $backups[0] ?? null;
        } catch (Throwable) {
            $latest = null;
        }
        if (!$latest) {
            $push('product_rollback_latest_backup', 'Rollback-Bereitschaft: Letzte Datensicherung', 'warning', 'Keine Datensicherung gefunden. Vor Produktiv-Updates muss eine Sicherung erstellt werden.', [], 'Produktreife Phase 5: Marktreife');
        } else {
            $size = (int)($latest['size'] ?? $latest['size_bytes'] ?? 0);
            $ageText = (string)($latest['created_at'] ?? 'ohne Datum');
            $status = $size > 1024 ? 'ok' : 'warning';
            $push('product_rollback_latest_backup', 'Rollback-Bereitschaft: Letzte Datensicherung', $status, 'Letztes Backup: ' . (string)($latest['filename'] ?? 'unbekannt') . ' · ' . $ageText . ' · ' . self::bytes($size), ['latest' => $latest], 'Produktreife Phase 5: Marktreife');
        }

        $rescueFiles = ['update-rettung.php', 'admin/update-rettung.php', 'admin/download_backup.php'];
        $missing = [];
        foreach ($rescueFiles as $file) {
            if (!is_file(root_path($file))) $missing[] = $file;
        }
        $push('product_rollback_tools', 'Rollback-Bereitschaft: Rettungswerkzeuge', $missing ? 'warning' : 'ok', $missing ? 'Fehlende Rettungswerkzeuge: ' . implode(', ', $missing) : 'Update-Rettung und Backup-Download sind vorhanden.', ['missing' => $missing], 'Produktreife Phase 5: Marktreife');

        try {
            $verification = BackupManager::verifyLatest();
            if (empty($verification['filename'])) {
                $push('product_backup_verification', 'Backup-Verifikation: Lesbarkeit', 'warning', 'Kein Backup für eine technische Lesbarkeitsprüfung gefunden.', [], 'Produktreife Phase 5: Marktreife');
            } else {
                $ok = !empty($verification['ok']);
                $issues = is_array($verification['issues'] ?? null) ? $verification['issues'] : [];
                $message = $ok
                    ? 'Letztes Backup ist lesbar und enthält erwartete SQL-Struktur: ' . (string)$verification['filename']
                    : 'Letztes Backup ist prüfbar, hat aber Hinweise: ' . implode('; ', $issues);
                $push('product_backup_verification', 'Backup-Verifikation: Lesbarkeit', $ok ? 'ok' : 'warning', $message, $verification, 'Produktreife Phase 5: Marktreife');
            }
        } catch (Throwable $e) {
            $push('product_backup_verification', 'Backup-Verifikation: Lesbarkeit', 'warning', 'Backup-Verifikation konnte nicht ausgeführt werden: ' . $e->getMessage(), [], 'Produktreife Phase 5: Marktreife');
        }
    }

    private static function runInstallerExposureChecks(callable $push): void
    {
        $localExists = is_file(root_path('config/local.php'));
        $installers = [];
        foreach (['install.php', 'admin/install.php'] as $file) {
            if (is_file(root_path($file))) $installers[] = $file;
        }
        if (!$installers) {
            $push('product_installer_exposure', 'Installer-Dateien', 'ok', 'Keine Installer-Dateien im Webroot gefunden.', [], 'Produktreife');
            return;
        }
        $protected = [];
        $unprotected = [];
        foreach ($installers as $file) {
            $source = (string)@file_get_contents(root_path($file));
            if (str_contains($source, 'STAYPILOT_INSTALLER_PROTECTION') && str_contains($source, 'http_response_code(403)')) {
                $protected[] = $file;
            } else {
                $unprotected[] = $file;
            }
        }
        if ($localExists && !$unprotected) {
            $push('product_installer_exposure', 'Installer-Dateien', 'ok', 'Installer-Dateien sind vorhanden, aber nach Installation aktiv geschützt: ' . implode(', ', $protected), ['files' => $installers, 'protected' => $protected, 'installed' => $localExists], 'Produktreife');
            return;
        }
        $status = $localExists ? 'warning' : 'error';
        $message = $localExists
            ? 'Installer-Dateien sind vorhanden; nicht vollständig geschützte Dateien prüfen: ' . implode(', ', ($unprotected ?: $installers))
            : 'Installer-Dateien sind vorhanden und config/local.php fehlt. Das ist im Produktivbetrieb kritisch: ' . implode(', ', $installers);
        $push('product_installer_exposure', 'Installer-Dateien', $status, $message, ['files' => $installers, 'protected' => $protected, 'unprotected' => $unprotected, 'installed' => $localExists], 'Produktreife');
    }

    private static function runPermissionModelChecks(callable $push): void
    {
        $apiFile = root_path('admin/api.php');
        if (!is_file($apiFile)) {
            $push('product_permission_model', 'Rollen-/Rechteprüfung', 'error', 'admin/api.php fehlt; Rechteprüfung kann nicht gelesen werden.', [], 'Produktreife');
            return;
        }
        $source = (string)@file_get_contents($apiFile);
        $hasAuthorize = str_contains($source, 'function authorize_action(');
        $hasCsrf = str_contains($source, 'verify_csrf()');
        $hasReadonlyGuard = str_contains($source, "role === 'readonly'") || str_contains($source, 'readonly');
        $hasMethodGuard = str_contains($source, 'require_safe_api_method_v236110') && str_contains($source, 'unsafe_method_blocked');
        $hasSecurityAudit = str_contains($source, 'unsafe_get_blocked') && str_contains($source, 'AuditLogger::record');
        $issues = [];
        if (!$hasAuthorize) $issues[] = 'authorize_action fehlt';
        if (!$hasCsrf) $issues[] = 'CSRF-Prüfung für Schreibzugriffe nicht erkennbar';
        if (!$hasReadonlyGuard) $issues[] = 'Readonly-Rolle nicht erkennbar';
        if (!$hasMethodGuard) $issues[] = 'GET-Sperre für schreibende API-Aktionen nicht erkennbar';
        if (!$hasSecurityAudit) $issues[] = 'Sicherheitsprotokoll für blockierte Schreibzugriffe nicht erkennbar';
        $push('product_permission_model', 'Rollen-/Rechteprüfung', $issues ? 'warning' : 'ok', $issues ? implode(' · ', $issues) : 'Zentrale API-Autorisierung, CSRF-Prüfung, Rollenmodell, POST-Pflicht für Schreibaktionen und Sicherheitsprotokoll sind erkennbar.', ['issues' => $issues], 'Produktreife');
        $push('product_api_write_protection', 'API-Schreibschutz', $hasMethodGuard ? 'ok' : 'warning', $hasMethodGuard ? 'Schreibende Admin-API-Aktionen werden nicht mehr per GET akzeptiert; sie benötigen POST mit CSRF-Token.' : 'POST-Pflicht für schreibende Admin-API-Aktionen fehlt oder ist nicht erkennbar.', ['method_guard' => $hasMethodGuard, 'security_audit' => $hasSecurityAudit], 'Produktreife Phase 5: Marktreife');

        $publicApis = ['api/public.php', 'api/webhook.php', 'gast/api.php', 'team/api.php', 'team-manager/api.php'];
        $present = array_values(array_filter($publicApis, static fn($file) => is_file(root_path($file))));
        $push('product_api_surface', 'API-Oberfläche', 'info', 'Öffentliche/portalbezogene API-Dateien vorhanden: ' . implode(', ', $present), ['files' => $present], 'Produktreife');

        $roleIssues = [];
        try {
            $roles = Auth::roleMatrix();
            $catalog = Auth::capabilityCatalog();
            foreach (['admin','manager','reception','housekeeping_manager','housekeeping','readonly'] as $roleKey) {
                if (!isset($roles[$roleKey])) $roleIssues[] = 'Rolle fehlt: ' . $roleKey;
            }
            foreach (['users_manage','billing_manage','housekeeping_release','website_manage','system_view','backups_manage'] as $capability) {
                if (!isset($catalog[$capability])) $roleIssues[] = 'Recht fehlt im Katalog: ' . $capability;
            }
            if (!in_array('users_manage', Auth::baseCapabilitiesForRole('admin'), true)) $roleIssues[] = 'Admin hat kein Benutzerrecht';
            if (in_array('users_manage', Auth::baseCapabilitiesForRole('manager'), true)) $roleIssues[] = 'Manager darf Benutzer verwalten';
            if (in_array('billing_manage', Auth::baseCapabilitiesForRole('housekeeping'), true)) $roleIssues[] = 'Housekeeping hat Abrechnungsrecht';
            if (in_array('guests_manage', Auth::baseCapabilitiesForRole('readonly'), true)) $roleIssues[] = 'Readonly hat Änderungsrechte';
            if (!Auth::portalAllowsRole('team', 'housekeeping')) $roleIssues[] = 'Teamportal erlaubt Housekeeping nicht';
            if (Auth::portalAllowsRole('team', 'reception')) $roleIssues[] = 'Teamportal erlaubt Rezeption';
            if (!Auth::portalAllowsRole('manager', 'housekeeping_manager')) $roleIssues[] = 'Leitungsportal erlaubt Housekeeping-Leitung nicht';
            if (!Auth::portalAllowsRole('admin', 'reception')) $roleIssues[] = 'Adminportal erlaubt Rezeption nicht';
        } catch (Throwable $e) {
            $roleIssues[] = 'Rollenmatrix nicht prüfbar: ' . $e->getMessage();
        }
        $push('product_role_portal_boundaries', 'Portal-/Rollen-Grenzen', $roleIssues ? 'warning' : 'ok', $roleIssues ? implode(' · ', $roleIssues) : 'Rollenmatrix, Rechtekatalog und Portalgrenzen für Verwaltung, Rezeption, Housekeeping-Leitung, Team und Nur-Lesen wirken plausibel.', ['issues' => $roleIssues], 'Produktreife Phase 5: Marktreife');

        $actionMapIssues = [];
        $apiV224 = root_path('admin/api-v224.php');
        $mapSource = is_file($apiV224) ? (string)@file_get_contents($apiV224) : '';
        foreach (['pms_consistency_action_v236109','pms_flow_action_v236113','pms_billing_action_v236114','pms_housekeeping_action_v236115'] as $requiredAction) {
            if (!str_contains($mapSource, $requiredAction)) $actionMapIssues[] = 'fehlendes Rechte-Mapping: ' . $requiredAction;
        }
        foreach (['team/api.php' => 'wrong_portal', 'team-manager/api.php' => 'requireRole'] as $file => $needle) {
            $portalSource = is_file(root_path($file)) ? (string)@file_get_contents(root_path($file)) : '';
            if (!str_contains($portalSource, $needle)) $actionMapIssues[] = 'Portalprüfung unklar: ' . $file;
        }
        $push('product_sensitive_action_mapping', 'Sensible Aktionen: Rechte-Mapping', $actionMapIssues ? 'warning' : 'ok', $actionMapIssues ? implode(' · ', $actionMapIssues) : 'Produktreife-Assistenten, Portal-APIs und sensible Aktionen sind serverseitig an Rollen/Rechte gebunden.', ['issues' => $actionMapIssues], 'Produktreife Phase 5: Marktreife');
    }

    private static function runOperationalWorkflowChecks(callable $push): void
    {
        $services = [
            'src/Services/BookingWorkflowService.php' => 'Buchungsworkflow',
            'src/Services/OfferService.php' => 'Angebotslogik',
            'src/Services/HousekeepingWorkflow.php' => 'Housekeeping-Workflow',
            'src/Services/CheckinService.php' => 'Check-in-Workflow',
            'src/Services/DocumentTemplateService.php' => 'Dokumentenvorlagen',
            'src/Services/BackupManager.php' => 'Backup-Grundlage',
        ];
        $missing = [];
        foreach ($services as $file => $label) {
            if (!is_file(root_path($file))) $missing[] = $label . ' (' . $file . ')';
        }
        $push('product_core_services', 'Kern-PMS-Dienste', $missing ? 'warning' : 'ok', $missing ? 'Fehlende Dienste: ' . implode(', ', $missing) : 'Zentrale PMS-Dienste sind vorhanden.', ['missing' => $missing], 'Produktreife');

        $integrations = [
            'src/Integrations/BookingComConnector.php' => 'Booking.com Connector',
            'src/Integrations/HotelSpiderConnector.php' => 'Hotel-Spider Connector',
            'api/webhook.php' => 'Webhook-Eingang',
        ];
        $present = [];
        foreach ($integrations as $file => $label) {
            if (is_file(root_path($file))) $present[] = $label;
        }
        $push('product_integrations_foundation', 'Schnittstellen-Grundlage', count($present) >= 2 ? 'info' : 'warning', $present ? 'Vorhanden: ' . implode(', ', $present) : 'Keine Schnittstellen-Grundlage gefunden.', ['present' => $present], 'Produktreife');
    }


    private static function runProductPhaseReadinessChecks(callable $push): void
    {
        // Produktreife-Pfad Phase 2-5: nur prüfen und sichtbar machen, keine Datenänderung.
        self::runPhase2CorePmsChecks($push);
        self::runPhase3WebsiteStudioChecks($push);
        self::runPhase4IntegrationChecks($push);
        self::runPhase5MarketReadinessChecks($push);
    }

    private static function runPhase2CorePmsChecks(callable $push): void
    {
        $tables = [
            'houses' => 'Häuser',
            'apartment_types' => 'Wohnungstypen',
            'apartments' => 'Apartments',
            'guests' => 'Gäste',
            'bookings' => 'Buchungen',
            'offers' => 'Angebote',
            'booking_payment_schedule' => 'Zahlungsplan',
            'booking_payments' => 'Zahlungen',
            'housekeeping_tasks' => 'Housekeeping-Aufgaben',
            'booking_checkins' => 'Online-Check-in',
            'booking_documents' => 'Buchungsdokumente',
            'communication_log' => 'Kommunikationsprotokoll',
        ];
        $missing = [];
        foreach ($tables as $table => $label) {
            if (!self::tableExists($table)) $missing[] = $label . ' (' . $table . ')';
        }
        $push('phase2_core_schema', 'Phase 2 Kern-PMS: Datenmodell', $missing ? 'warning' : 'ok', $missing ? 'Fehlende Kerntabellen: ' . implode(', ', $missing) : 'Kern-PMS-Tabellen für Häuser, Apartments, Gäste, Buchungen, Angebote, Zahlungen, Housekeeping, Check-in und Kommunikation sind vorhanden.', ['missing' => $missing], 'Produktreife Phase 2: Kern-PMS');

        $services = [
            'src/Services/BookingWorkflowService.php' => 'Buchungsworkflow',
            'src/Services/OfferService.php' => 'Angebotsservice',
            'src/Services/HousekeepingWorkflow.php' => 'Housekeeping-Workflow',
            'src/Services/CheckinService.php' => 'Check-in-Service',
            'src/Services/DocumentTemplateService.php' => 'Dokumentenvorlagen',
            'admin/billing-dokument.php' => 'Abrechnungsdokumente',
            'kunde.php' => 'Kundenportal',
            'gast/api.php' => 'Gastportal-API',
            'team/api.php' => 'Team-API',
            'team-manager/api.php' => 'Team-Manager-API',
        ];
        $missingServices = [];
        foreach ($services as $file => $label) {
            if (!is_file(root_path($file))) $missingServices[] = $label . ' (' . $file . ')';
        }
        $push('phase2_core_services', 'Phase 2 Kern-PMS: Dienste/Portale', $missingServices ? 'warning' : 'ok', $missingServices ? 'Fehlende Dienste: ' . implode(', ', $missingServices) : 'Zentrale Kern-PMS-Dienste und Portale sind vorhanden.', ['missing' => $missingServices], 'Produktreife Phase 2: Kern-PMS');

        $openPayments = 0;
        try {
            if (self::tableExists('booking_payment_schedule')) {
                $where = self::columnExists('bookings', 'deleted_at') ? " AND (b.deleted_at IS NULL)" : '';
                $stmt = db()->query("SELECT COUNT(*) FROM booking_payment_schedule s LEFT JOIN bookings b ON b.id=s.booking_id WHERE COALESCE(s.status,'open') NOT IN ('paid','cancelled','void','aufgehoben')" . $where);
                $openPayments = (int)$stmt->fetchColumn();
            }
        } catch (Throwable) {}
        $push('phase2_open_payment_workload', 'Phase 2 Kern-PMS: Zahlungsarbeit gesamt', 'info', $openPayments . ' offene/teiloffene Zahlungsziele über alle Buchungsarten. Für interne Forderungen siehe Abläufe → Interne offene Zahlungsziele.', ['open_payment_schedule_all_modes' => $openPayments], 'Produktreife Phase 2: Kern-PMS');

        self::runPhase2CoreDataIntegrityChecks($push);
        self::runPhase2OperationalFlowChecks($push);
        self::runPhase2BillingStatusChecks($push);
        self::runPhase2HousekeepingReleaseChecks($push);
        self::runPhase2LifecycleStatusChecks($push);
    }

    private static function runPhase2CoreDataIntegrityChecks(callable $push): void
    {
        // Produktreife: reine Lesediagnose. Keine Datenänderung, keine automatische Bereinigung.
        $results = [];
        $warnCount = 0;

        $activeBookingFilter = "1=1";
        if (self::columnExists('bookings', 'deleted_at')) {
            $activeBookingFilter .= " AND b.deleted_at IS NULL";
        }
        if (self::columnExists('bookings', 'status')) {
            $activeBookingFilter .= " AND LOWER(COALESCE(b.status,'')) NOT IN ('cancelled','canceled','storniert','deleted','gelöscht','abgelehnt','declined','void')";
        }

        $deletedGuestFilter = "0=1";
        if (self::columnExists('guests', 'deleted_at')) {
            $deletedGuestFilter = "g.deleted_at IS NOT NULL";
        }
        if (self::columnExists('guests', 'is_deleted')) {
            $deletedGuestFilter = "(" . $deletedGuestFilter . " OR COALESCE(g.is_deleted,0)=1)";
        }

        if (self::tableExists('bookings') && self::tableExists('guests') && self::columnExists('bookings', 'guest_id')) {
            $sql = "SELECT COUNT(*) FROM bookings b LEFT JOIN guests g ON g.id=b.guest_id WHERE " . $activeBookingFilter . " AND b.guest_id IS NOT NULL AND (g.id IS NULL OR " . $deletedGuestFilter . ")";
            $count = self::safeCount($sql);
            $results['active_bookings_without_valid_guest'] = $count;
            if ($count > 0) $warnCount++;
        }

        if (self::tableExists('booking_payment_schedule') && self::tableExists('bookings') && self::columnExists('booking_payment_schedule', 'booking_id')) {
            $statusFilter = self::columnExists('booking_payment_schedule', 'status') ? " AND LOWER(COALESCE(s.status,'open')) NOT IN ('paid','cancelled','canceled','void','aufgehoben','storniert')" : '';
            $deletedFilter = self::columnExists('bookings', 'deleted_at') ? " OR b.deleted_at IS NOT NULL" : '';
            $statusBooking = self::columnExists('bookings', 'status') ? " OR LOWER(COALESCE(b.status,'')) IN ('cancelled','canceled','storniert','deleted','gelöscht','abgelehnt','declined','void')" : '';
            $count = self::safeCount("SELECT COUNT(*) FROM booking_payment_schedule s LEFT JOIN bookings b ON b.id=s.booking_id WHERE (b.id IS NULL" . $deletedFilter . $statusBooking . ")" . $statusFilter);
            $results['open_payment_schedule_without_active_booking'] = $count;
            if ($count > 0) $warnCount++;
        }

        if (self::tableExists('booking_payments') && self::tableExists('bookings') && self::columnExists('booking_payments', 'booking_id')) {
            $deletedFilter = self::columnExists('bookings', 'deleted_at') ? " OR b.deleted_at IS NOT NULL" : '';
            $statusBooking = self::columnExists('bookings', 'status') ? " OR LOWER(COALESCE(b.status,'')) IN ('cancelled','canceled','storniert','deleted','gelöscht','abgelehnt','declined','void')" : '';
            $count = self::safeCount("SELECT COUNT(*) FROM booking_payments p LEFT JOIN bookings b ON b.id=p.booking_id WHERE p.booking_id IS NOT NULL AND (b.id IS NULL" . $deletedFilter . $statusBooking . ")");
            $results['payments_without_active_booking'] = $count;
            if ($count > 0) $warnCount++;
        }

        if (self::tableExists('housekeeping_tasks') && self::tableExists('bookings') && self::columnExists('housekeeping_tasks', 'booking_id')) {
            $taskStatus = self::columnExists('housekeeping_tasks', 'status') ? " AND LOWER(COALESCE(t.status,'open')) NOT IN ('done','completed','cancelled','canceled','storniert','freigegeben','bezugsbereit')" : '';
            $deletedFilter = self::columnExists('bookings', 'deleted_at') ? " OR b.deleted_at IS NOT NULL" : '';
            $statusBooking = self::columnExists('bookings', 'status') ? " OR LOWER(COALESCE(b.status,'')) IN ('cancelled','canceled','storniert','deleted','gelöscht','abgelehnt','declined','void')" : '';
            $count = self::safeCount("SELECT COUNT(*) FROM housekeeping_tasks t LEFT JOIN bookings b ON b.id=t.booking_id WHERE t.booking_id IS NOT NULL AND (b.id IS NULL" . $deletedFilter . $statusBooking . ")" . $taskStatus);
            $results['open_housekeeping_without_active_booking'] = $count;
            if ($count > 0) $warnCount++;
        }

        if (self::tableExists('booking_checkins') && self::tableExists('bookings') && self::columnExists('booking_checkins', 'booking_id')) {
            $deletedFilter = self::columnExists('bookings', 'deleted_at') ? " OR b.deleted_at IS NOT NULL" : '';
            $statusBooking = self::columnExists('bookings', 'status') ? " OR LOWER(COALESCE(b.status,'')) IN ('cancelled','canceled','storniert','deleted','gelöscht','abgelehnt','declined','void')" : '';
            $count = self::safeCount("SELECT COUNT(*) FROM booking_checkins c LEFT JOIN bookings b ON b.id=c.booking_id WHERE c.booking_id IS NOT NULL AND (b.id IS NULL" . $deletedFilter . $statusBooking . ")");
            $results['checkins_without_active_booking'] = $count;
            if ($count > 0) $warnCount++;
        }

        if (self::tableExists('booking_documents') && self::tableExists('bookings') && self::columnExists('booking_documents', 'booking_id')) {
            $docFilter = self::columnExists('booking_documents', 'deleted_at') ? " AND d.deleted_at IS NULL" : '';
            $deletedFilter = self::columnExists('bookings', 'deleted_at') ? " OR b.deleted_at IS NOT NULL" : '';
            $statusBooking = self::columnExists('bookings', 'status') ? " OR LOWER(COALESCE(b.status,'')) IN ('cancelled','canceled','storniert','deleted','gelöscht','abgelehnt','declined','void')" : '';
            $count = self::safeCount("SELECT COUNT(*) FROM booking_documents d LEFT JOIN bookings b ON b.id=d.booking_id WHERE d.booking_id IS NOT NULL AND (b.id IS NULL" . $deletedFilter . $statusBooking . ")" . $docFilter);
            $results['documents_without_active_booking'] = $count;
            if ($count > 0) $warnCount++;
        }

        if (self::tableExists('offers') && self::tableExists('guests') && self::columnExists('offers', 'guest_id')) {
            $offerActive = "1=1";
            if (self::columnExists('offers', 'deleted_at')) $offerActive .= " AND o.deleted_at IS NULL";
            if (self::columnExists('offers', 'status')) $offerActive .= " AND LOWER(COALESCE(o.status,'')) NOT IN ('archived','archive','cancelled','canceled','storniert','deleted','gelöscht','declined','abgelehnt','void')";
            $count = self::safeCount("SELECT COUNT(*) FROM offers o LEFT JOIN guests g ON g.id=o.guest_id WHERE " . $offerActive . " AND o.guest_id IS NOT NULL AND (g.id IS NULL OR " . $deletedGuestFilter . ")");
            $results['active_offers_without_valid_guest'] = $count;
            if ($count > 0) $warnCount++;
        }

        if (!$results) {
            $push('phase2_data_integrity', 'Phase 2 Kern-PMS: Datenkonsistenz', 'info', 'Datenkonsistenz konnte nur teilweise geprüft werden, weil einzelne Tabellen oder Spalten fehlen. Kein technischer Fehler.', [], 'Produktreife Phase 2: Kern-PMS');
            return;
        }

        $parts = [];
        foreach ($results as $key => $value) {
            $parts[] = str_replace('_', ' ', $key) . ': ' . $value;
        }
        $push('phase2_data_integrity', 'Phase 2 Kern-PMS: Datenkonsistenz', $warnCount > 0 ? 'warning' : 'ok', $warnCount > 0 ? 'Konsistenzhinweise gefunden: ' . implode(' · ', $parts) : 'Keine aktiven Alt-/Folgedaten ohne gültigen Hauptbezug gefunden.', $results, 'Produktreife Phase 2: Kern-PMS');
    }



    private static function runPhase2OperationalFlowChecks(callable $push): void
    {
        // Produktreife: fachliche Statuskette Angebot -> Buchung -> Zahlung -> Housekeeping -> Kundenportal.
        $results = [];
        $warn = 0;
        $activeBooking = "1=1";
        if (self::columnExists('bookings', 'deleted_at')) $activeBooking .= " AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00')";
        if (self::columnExists('bookings', 'status')) $activeBooking .= " AND LOWER(COALESCE(b.status,'')) NOT IN ('cancelled','canceled','storniert','deleted','gelöscht','rejected','abgelehnt','declined','void')";
        $bookedStatus = self::columnExists('bookings', 'status') ? "LOWER(COALESCE(b.status,'')) IN ('confirmed','accepted','booked','reserved','clarification_required')" : "1=1";

        if (self::tableExists('offers')) {
            $offerActive = "1=1";
            if (self::columnExists('offers', 'deleted_at')) $offerActive .= " AND (o.deleted_at IS NULL OR o.deleted_at='0000-00-00 00:00:00')";
            if (self::columnExists('offers', 'status')) {
                $results['accepted_offers_without_booking'] = self::safeCount("SELECT COUNT(*) FROM offers o WHERE " . $offerActive . " AND LOWER(COALESCE(o.status,'')) IN ('accepted','angenommen','confirmed') AND (o.booking_id IS NULL OR o.booking_id=0)");
                if ($results['accepted_offers_without_booking'] > 0) $warn++;
            }
            if (self::tableExists('bookings') && self::columnExists('offers', 'booking_id')) {
                $inactive = "0=1";
                if (self::columnExists('bookings', 'deleted_at')) $inactive .= " OR b.deleted_at IS NOT NULL";
                if (self::columnExists('bookings', 'status')) $inactive .= " OR LOWER(COALESCE(b.status,'')) IN ('cancelled','canceled','storniert','deleted','gelöscht','rejected','abgelehnt','declined','void')";
                $results['active_offers_linked_to_inactive_booking'] = self::safeCount("SELECT COUNT(*) FROM offers o JOIN bookings b ON b.id=o.booking_id WHERE " . $offerActive . " AND (" . $inactive . ")");
                if ($results['active_offers_linked_to_inactive_booking'] > 0) $warn++;
            }
        }

        if (self::tableExists('bookings')) {
            if (self::columnExists('bookings', 'apartment_id')) {
                $results['booked_bookings_without_apartment'] = self::safeCount("SELECT COUNT(*) FROM bookings b WHERE " . $activeBooking . " AND " . $bookedStatus . " AND (b.apartment_id IS NULL OR b.apartment_id=0)");
                if ($results['booked_bookings_without_apartment'] > 0) $warn++;
            }
            if (self::tableExists('booking_payment_schedule')) {
                $results['priced_bookings_without_payment_schedule'] = self::safeCount("SELECT COUNT(*) FROM (SELECT b.id FROM bookings b LEFT JOIN booking_payment_schedule s ON s.booking_id=b.id WHERE " . $activeBooking . " AND COALESCE(b.total_price,0)>0 GROUP BY b.id HAVING COUNT(s.id)=0) x");
                if ($results['priced_bookings_without_payment_schedule'] > 0) $warn++;
                $results['payment_schedule_total_mismatch'] = self::safeCount("SELECT COUNT(*) FROM (SELECT b.id,b.total_price,COALESCE(SUM(s.amount),0) scheduled FROM bookings b LEFT JOIN booking_payment_schedule s ON s.booking_id=b.id WHERE " . $activeBooking . " AND COALESCE(b.total_price,0)>0 GROUP BY b.id,b.total_price HAVING ABS(COALESCE(b.total_price,0)-scheduled)>0.05) x");
                if ($results['payment_schedule_total_mismatch'] > 0) $warn++;
            }
            if (self::tableExists('booking_customer_access')) {
                $results['booked_bookings_without_customer_access'] = self::safeCount("SELECT COUNT(*) FROM bookings b LEFT JOIN booking_customer_access a ON a.booking_id=b.id AND a.active=1 WHERE " . $activeBooking . " AND " . $bookedStatus . " AND a.id IS NULL");
                if ($results['booked_bookings_without_customer_access'] > 0) $warn++;
            }
            if (self::tableExists('booking_checkins')) {
                $results['booked_bookings_without_checkin_record'] = self::safeCount("SELECT COUNT(*) FROM bookings b LEFT JOIN booking_checkins c ON c.booking_id=b.id WHERE " . $activeBooking . " AND " . $bookedStatus . " AND c.booking_id IS NULL");
                if ($results['booked_bookings_without_checkin_record'] > 0) $warn++;
            }
            if (self::tableExists('housekeeping_tasks')) {
                $results['booked_bookings_without_housekeeping_task'] = self::safeCount("SELECT COUNT(*) FROM bookings b LEFT JOIN housekeeping_tasks t ON t.booking_id=b.id WHERE " . $activeBooking . " AND " . $bookedStatus . " AND b.apartment_id IS NOT NULL AND t.id IS NULL");
                if ($results['booked_bookings_without_housekeeping_task'] > 0) $warn++;
            }
        }

        $parts = [];
        foreach ($results as $key => $value) $parts[] = str_replace('_', ' ', $key) . ': ' . (int)$value;
        $push('phase2_operational_flow', 'Phase 2 Kern-PMS: Statuskette Angebot → Buchung → Betrieb', $warn > 0 ? 'warning' : 'ok', $warn > 0 ? 'Ablaufhinweise gefunden: ' . implode(' · ', $parts) : 'Die geprüfte Statuskette Angebot, Buchung, Zahlungsplan, Housekeeping, Check-in und Kundenportal wirkt konsistent.', $results, 'Produktreife Phase 2: Kern-PMS');
    }


    private static function runPhase2BillingStatusChecks(callable $push): void
    {
        // Produktreife: Abrechnung/Zahlungsstatus darf keine widersprüchlichen Werte liefern.
        $results = [];
        $warn = 0;
        if (!self::tableExists('bookings')) {
            $push('phase2_billing_status', 'Phase 2 Kern-PMS: Abrechnungsstatus', 'info', 'Abrechnungsstatus konnte nicht geprüft werden, weil die Buchungstabelle fehlt.', [], 'Produktreife Phase 2: Kern-PMS');
            return;
        }
        $activeBooking = "1=1";
        if (self::columnExists('bookings', 'deleted_at')) $activeBooking .= " AND (b.deleted_at IS NULL OR b.deleted_at='0000-00-00 00:00:00')";
        if (self::columnExists('bookings', 'status')) $activeBooking .= " AND LOWER(COALESCE(b.status,'')) NOT IN ('cancelled','canceled','storniert','deleted','gelöscht','rejected','abgelehnt','declined','void')";
        if (self::tableExists('booking_payment_schedule')) {
            $results['payment_schedule_total_mismatch'] = self::safeCount("SELECT COUNT(*) FROM (SELECT b.id,b.total_price,COALESCE(SUM(s.amount),0) scheduled FROM bookings b LEFT JOIN booking_payment_schedule s ON s.booking_id=b.id WHERE " . $activeBooking . " AND COALESCE(b.total_price,0)>0 GROUP BY b.id,b.total_price HAVING ABS(COALESCE(b.total_price,0)-scheduled)>0.05) x");
            if ($results['payment_schedule_total_mismatch'] > 0) $warn++;
            $results['invalid_payment_schedule_values'] = self::safeCount("SELECT COUNT(*) FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id WHERE " . $activeBooking . " AND (COALESCE(s.amount,0)<0 OR COALESCE(s.paid_amount,0)<0 OR COALESCE(s.paid_amount,0)>COALESCE(s.amount,0)+0.05)");
            if ($results['invalid_payment_schedule_values'] > 0) $warn++;
            $results['overdue_not_marked'] = self::safeCount("SELECT COUNT(*) FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id WHERE " . $activeBooking . " AND LOWER(COALESCE(s.status,'')) IN ('open','partial') AND COALESCE(s.amount,0)>COALESCE(s.paid_amount,0)+0.05 AND s.due_date IS NOT NULL AND s.due_date<CURDATE()");
            if ($results['overdue_not_marked'] > 0) $warn++;
        }
        if (self::tableExists('booking_payments')) {
            $results['booking_paid_amount_mismatch'] = self::safeCount("SELECT COUNT(*) FROM (SELECT b.id,COALESCE(b.paid_amount,0) stored,COALESCE(SUM(CASE WHEN p.status='received' THEN p.amount ELSE 0 END),0) paid_sum FROM bookings b LEFT JOIN booking_payments p ON p.booking_id=b.id WHERE " . $activeBooking . " GROUP BY b.id HAVING ABS(stored-paid_sum)>0.05) x");
            if ($results['booking_paid_amount_mismatch'] > 0) $warn++;
        }
        $parts = [];
        foreach ($results as $key => $value) $parts[] = str_replace('_', ' ', $key) . ': ' . (int)$value;
        $push('phase2_billing_status', 'Phase 2 Kern-PMS: Abrechnungsstatus', $warn > 0 ? 'warning' : 'ok', $warn > 0 ? 'Abrechnungshinweise gefunden: ' . implode(' · ', $parts) : 'Zahlungsplan, bezahlte Summen und Überfälligkeitsstatus wirken konsistent.', $results, 'Produktreife Phase 2: Kern-PMS');
    }

    private static function runPhase3WebsiteStudioChecks(callable $push): void
    {
        $files = [
            'assets/admin-v236-studio-pro.js' => 'Studio-Editor',
            'assets/admin-v236-studio-page-actions.js' => 'Studio-Seitenaktionen',
            'assets/admin-v236-website-templates.js' => 'Website-Vorlagen',
            'assets/site.js' => 'öffentliche Website-JS',
            'assets/site.css' => 'öffentliche Website-CSS',
            'wohnungstyp.php' => 'Wohnungstyp-Detailseite',
            'sitemap.php' => 'Sitemap',
            'api/public.php' => 'öffentliche Anfrage-/Buchungs-API',
        ];
        $missing = [];
        foreach ($files as $file => $label) {
            if (!is_file(root_path($file))) $missing[] = $label . ' (' . $file . ')';
        }
        $push('phase3_website_files', 'Phase 3 Website/Studio: Dateien', $missing ? 'warning' : 'ok', $missing ? 'Fehlende Website-/Studio-Dateien: ' . implode(', ', $missing) : 'Studio, Website, Vorlagen, Sitemap und öffentliche API sind vorhanden.', ['missing' => $missing], 'Produktreife Phase 3: Website/Studio');

        $tables = ['site_pages' => 'Seiten', 'site_page_blocks' => 'Blöcke', 'site_page_translations' => 'Übersetzungen', 'site_media' => 'Medien'];
        $missingTables = [];
        foreach ($tables as $table => $label) {
            if (!self::tableExists($table)) $missingTables[] = $label . ' (' . $table . ')';
        }
        $message = $missingTables ? 'Fehlende Website-Tabellen: ' . implode(', ', $missingTables) : 'Website-Seiten, Blöcke, Übersetzungen und Medienverwaltung sind als Datenmodell vorhanden.';
        $push('phase3_website_schema', 'Phase 3 Website/Studio: Datenmodell', $missingTables ? 'warning' : 'ok', $message, ['missing' => $missingTables], 'Produktreife Phase 3: Website/Studio');

        $pages = null;
        $blocks = null;
        try {
            if (self::tableExists('site_pages')) $pages = (int)db()->query('SELECT COUNT(*) FROM site_pages')->fetchColumn();
            if (self::tableExists('site_page_blocks')) $blocks = (int)db()->query('SELECT COUNT(*) FROM site_page_blocks')->fetchColumn();
        } catch (Throwable) {}
        $push('phase3_website_content_status', 'Phase 3 Website/Studio: Inhalt', 'info', 'Seiten: ' . ($pages === null ? 'nicht ermittelbar' : (string)$pages) . ' · Blöcke: ' . ($blocks === null ? 'nicht ermittelbar' : (string)$blocks) . '. Inhaltliche Qualität und Vorschau bleiben manuell zu prüfen.', ['pages' => $pages, 'blocks' => $blocks], 'Produktreife Phase 3: Website/Studio');
    }

    private static function runPhase4IntegrationChecks(callable $push): void
    {
        $files = [
            'src/Integrations/BookingComConnector.php' => 'Booking.com Connector',
            'src/Integrations/HotelSpiderConnector.php' => 'Hotel-Spider Connector',
            'api/webhook.php' => 'Webhook-Eingang',
            'docs/API_SETUP.md' => 'API-Dokumentation',
            'print/police_csv.php' => 'Meldelisten-CSV',
        ];
        $present = [];
        $missing = [];
        foreach ($files as $file => $label) {
            if (is_file(root_path($file))) $present[] = $label; else $missing[] = $label . ' (' . $file . ')';
        }
        $push('phase4_integrations_foundation', 'Phase 4 Schnittstellen: vorhandene Grundlage', $present ? 'info' : 'warning', $present ? 'Vorhanden: ' . implode(', ', $present) : 'Keine Schnittstellen-Grundlage gefunden.', ['present' => $present, 'missing' => $missing], 'Produktreife Phase 4: Schnittstellen');

        $ariReady = false;
        $bookingFile = root_path('src/Integrations/BookingComConnector.php');
        if (is_file($bookingFile)) {
            $source = (string)@file_get_contents($bookingFile);
            $ariReady = str_contains($source, 'pushAvailability') || str_contains($source, 'ARI') && str_contains($source, 'push');
        }
        $push('phase4_channel_manager_gap', 'Phase 4 Schnittstellen: Channel-Manager-Reife', $ariReady ? 'ok' : 'warning', $ariReady ? 'ARI-/Verfügbarkeits-Push ist im Code erkennbar.' : 'OTA-Grundlagen sind vorhanden, aber vollständiger Preis-/Verfügbarkeits-/Storno-Sync ist noch nicht als marktreife Channel-Manager-Logik abgesichert.', ['ari_push_detected' => $ariReady], 'Produktreife Phase 4: Schnittstellen');

        $paymentProvider = false;
        foreach (['Stripe', 'PayPal', 'Mollie', 'Redsys'] as $needle) {
            foreach (['src', 'admin', 'api'] as $dir) {
                try {
                    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(root_path($dir), FilesystemIterator::SKIP_DOTS));
                    foreach ($iterator as $file) {
                        if (!$file->isFile()) continue;
                        $path = $file->getPathname();
                        if (!preg_match('/\.(php|js)$/', $path)) continue;
                        if (str_contains((string)@file_get_contents($path), $needle)) { $paymentProvider = true; break 3; }
                    }
                } catch (Throwable) {}
            }
        }
        $push('phase4_payment_provider_gap', 'Phase 4 Schnittstellen: Zahlungsanbieter', $paymentProvider ? 'info' : 'warning', $paymentProvider ? 'Hinweise auf Zahlungsanbieter-Code gefunden; Live-Konfiguration separat prüfen.' : 'Kein produktionsreifer Zahlungsanbieter wie Stripe/PayPal/Redsys erkannt. Zahlungsstatus kann intern verwaltet werden, aber echte Online-Zahlung fehlt noch.', ['payment_provider_detected' => $paymentProvider], 'Produktreife Phase 4: Schnittstellen');
    }

    private static function runPhase5MarketReadinessChecks(callable $push): void
    {
        $files = [
            'README.md' => 'README',
            'CHANGELOG.md' => 'Changelog',
            'PROJECT_STATUS.md' => 'Projektstatus',
            'docs/UPDATE.md' => 'Update-Hinweise',
            'src/Migrator.php' => 'Migrationssystem',
            'src/Services/BackupManager.php' => 'Backup-Manager',
            'src/Services/SystemDiagnostics.php' => 'Systemdiagnose',
            'tools/calendar_regression_check.js' => 'Kalender-Regressionstest',
        ];
        $missing = [];
        foreach ($files as $file => $label) {
            if (!is_file(root_path($file))) $missing[] = $label . ' (' . $file . ')';
        }
        $push('phase5_release_files', 'Phase 5 Marktreife: Release-/Wartungsdateien', $missing ? 'warning' : 'ok', $missing ? 'Fehlende Wartungsdateien: ' . implode(', ', $missing) : 'Dokumentation, Migration, Backup, Diagnose und erste Prüfwerkzeuge sind vorhanden.', ['missing' => $missing], 'Produktreife Phase 5: Marktreife');

        $securityFiles = ['.htaccess' => 'Webroot-Schutz', 'admin/.htaccess' => 'Admin-Schutz'];
        $missingSecurity = [];
        foreach ($securityFiles as $file => $label) {
            if (!is_file(root_path($file))) $missingSecurity[] = $label . ' (' . $file . ')';
        }
        $push('phase5_security_baseline', 'Phase 5 Marktreife: Sicherheitsbasis', $missingSecurity ? 'warning' : 'ok', $missingSecurity ? 'Fehlende Schutzdateien: ' . implode(', ', $missingSecurity) : 'Grundlegende Schutzdateien sind vorhanden; Rechteprüfung und DSGVO-Prozess bleiben fachlich zu prüfen.', ['missing' => $missingSecurity], 'Produktreife Phase 5: Marktreife');

        $hasRollback = false;
        try {
            foreach (['src/Migrator.php', 'update-rettung.php', 'admin/update-rettung.php'] as $file) {
                $source = is_file(root_path($file)) ? (string)@file_get_contents(root_path($file)) : '';
                if (preg_match('/rollback|restore|wiederherstell/i', $source)) { $hasRollback = true; break; }
            }
        } catch (Throwable) {}
        $push('phase5_rollback_gap', 'Phase 5 Marktreife: Rollback/Wiederherstellung', $hasRollback ? 'info' : 'warning', $hasRollback ? 'Hinweise auf Wiederherstellungs-/Rettungslogik vorhanden; Live-Restore muss trotzdem getestet werden.' : 'Backup-Grundlagen sind vorhanden, aber ein voll geprüfter Rollback-/Restore-Prozess ist noch nicht marktreif abgesichert.', ['rollback_detected' => $hasRollback], 'Produktreife Phase 5: Marktreife');
    }


    private static function runPhase2HousekeepingReleaseChecks(callable $push): void
    {
        $results = [];
        $warn = 0;
        if (!self::tableExists('housekeeping_tasks')) {
            $push('phase2_housekeeping_release', 'Phase 2 Kern-PMS: Housekeeping-Freigabeprozess', 'info', 'Housekeeping-Freigabeprozess konnte nicht geprüft werden, weil die Tabelle housekeeping_tasks fehlt.', [], 'Produktreife Phase 2: Kern-PMS');
            return;
        }
        $active = "1=1";
        if (self::columnExists('housekeeping_tasks','status')) $active .= " AND LOWER(COALESCE(t.status,'')) NOT IN ('released','done','completed','cancelled','canceled','storniert')";
        $results['stale_open_tasks'] = self::safeCount("SELECT COUNT(*) FROM housekeeping_tasks t WHERE $active AND LOWER(COALESCE(t.status,'')) IN ('open','assigned','in_progress') AND COALESCE(t.task_date,t.due_date) < DATE_SUB(CURDATE(), INTERVAL 2 DAY)");
        if ($results['stale_open_tasks'] > 0) $warn++;
        if (self::columnExists('housekeeping_tasks','cleaning_completed_at') && self::columnExists('housekeeping_tasks','inspected_at')) {
            $results['cleaning_done_without_inspection'] = self::safeCount("SELECT COUNT(*) FROM housekeeping_tasks t WHERE $active AND t.cleaning_completed_at IS NOT NULL AND t.inspected_at IS NULL");
            if ($results['cleaning_done_without_inspection'] > 0) $warn++;
        }
        if (self::columnExists('housekeeping_tasks','inspected_at') && self::columnExists('housekeeping_tasks','ready_reported_at')) {
            $results['inspected_not_ready'] = self::safeCount("SELECT COUNT(*) FROM housekeeping_tasks t WHERE $active AND t.inspected_at IS NOT NULL AND t.ready_reported_at IS NULL");
            if ($results['inspected_not_ready'] > 0) $warn++;
        }
        if (self::columnExists('housekeeping_tasks','ready_reported_at') && self::columnExists('housekeeping_tasks','released_at')) {
            $results['ready_not_released'] = self::safeCount("SELECT COUNT(*) FROM housekeeping_tasks t WHERE $active AND t.ready_reported_at IS NOT NULL AND t.released_at IS NULL");
            if ($results['ready_not_released'] > 0) $warn++;
        }
        $parts = [];
        foreach ($results as $key => $value) $parts[] = str_replace('_',' ',$key) . ': ' . (int)$value;
        $push('phase2_housekeeping_release', 'Phase 2 Kern-PMS: Housekeeping-Freigabeprozess', $warn > 0 ? 'warning' : 'ok', $warn > 0 ? 'Housekeeping-Freigabehinweise gefunden: ' . implode(' · ', $parts) : 'Housekeeping-Freigabekette wirkt in den geprüften Punkten konsistent.', $results, 'Produktreife Phase 2: Kern-PMS');
    }

    private static function runPhase2LifecycleStatusChecks(callable $push): void
    {
        $results = [];
        $warn = 0;
        $terminalBooking = "0=1";
        if (self::tableExists('bookings')) {
            $parts = [];
            if (self::columnExists('bookings','deleted_at')) $parts[] = "b.deleted_at IS NOT NULL";
            if (self::columnExists('bookings','status')) $parts[] = "LOWER(COALESCE(b.status,'')) IN ('cancelled','canceled','storniert','deleted','gelöscht','rejected','abgelehnt','declined','void')";
            if ($parts) $terminalBooking = '(' . implode(' OR ', $parts) . ')';
        }
        if (self::tableExists('booking_payment_schedule') && self::tableExists('bookings')) {
            $results['open_schedules_for_terminal_bookings'] = self::safeCount("SELECT COUNT(*) FROM booking_payment_schedule s JOIN bookings b ON b.id=s.booking_id WHERE $terminalBooking AND LOWER(COALESCE(s.status,'')) NOT IN ('paid','received','waived','cancelled','canceled','void','aufgehoben','storniert')");
            if ($results['open_schedules_for_terminal_bookings'] > 0) $warn++;
        }
        if (self::tableExists('booking_customer_access') && self::tableExists('bookings')) {
            $results['active_customer_access_for_terminal_bookings'] = self::safeCount("SELECT COUNT(*) FROM booking_customer_access a JOIN bookings b ON b.id=a.booking_id WHERE $terminalBooking AND COALESCE(a.active,1)=1");
            if ($results['active_customer_access_for_terminal_bookings'] > 0) $warn++;
        }
        if (self::tableExists('booking_checkins') && self::tableExists('bookings')) {
            $results['open_checkins_for_terminal_bookings'] = self::safeCount("SELECT COUNT(*) FROM booking_checkins c JOIN bookings b ON b.id=c.booking_id WHERE $terminalBooking AND LOWER(COALESCE(c.status,'')) NOT IN ('cancelled','canceled','closed','completed','storniert','deleted')");
            if ($results['open_checkins_for_terminal_bookings'] > 0) $warn++;
        }
        if (self::tableExists('housekeeping_tasks') && self::tableExists('bookings')) {
            $results['active_housekeeping_for_terminal_bookings'] = self::safeCount("SELECT COUNT(*) FROM housekeeping_tasks t JOIN bookings b ON b.id=t.booking_id WHERE $terminalBooking AND LOWER(COALESCE(t.status,'')) NOT IN ('done','completed','released','cancelled','canceled','storniert')");
            if ($results['active_housekeeping_for_terminal_bookings'] > 0) $warn++;
        }
        if (self::tableExists('booking_documents') && self::tableExists('bookings')) {
            $docActive = self::columnExists('booking_documents','deleted_at') ? " AND (d.deleted_at IS NULL OR d.deleted_at='0000-00-00 00:00:00')" : '';
            $results['active_documents_for_terminal_bookings'] = self::safeCount("SELECT COUNT(*) FROM booking_documents d JOIN bookings b ON b.id=d.booking_id WHERE $terminalBooking $docActive AND LOWER(COALESCE(d.status,'')) NOT IN ('cancelled','canceled','deleted','storniert','void')");
            if ($results['active_documents_for_terminal_bookings'] > 0) $warn++;
        }
        $parts = [];
        foreach ($results as $key => $value) $parts[] = str_replace('_',' ',$key) . ': ' . (int)$value;
        $push('phase2_lifecycle_status', 'Phase 2 Kern-PMS: Storno-/Lösch-/Archivstatus', $warn > 0 ? 'warning' : 'ok', $warn > 0 ? 'Status-/Archivhinweise gefunden: ' . implode(' · ', $parts) : 'Beendete Buchungen erzeugen in den geprüften Bereichen keine aktiven Folgevorgänge.', $results, 'Produktreife Phase 2: Kern-PMS');
    }

    private static function recommendations(array $checks): array
    {
        $recs = [];
        $hasErrors = count(array_filter($checks, static fn($c) => $c['status'] === 'error'));
        $hasWarnings = count(array_filter($checks, static fn($c) => $c['status'] === 'warning'));
        if ($hasErrors > 0) $recs[] = 'Zuerst rote Fehler beheben: fehlende Dateien, fehlende Tabellen/Spalten oder Schreibrechte verhindern zuverlässige Abläufe.';
        if ($hasWarnings > 0) $recs[] = 'Gelbe Hinweise prüfen: Sie stoppen die App nicht zwingend, erklären aber oft alte Anzeige, fehlende PDFs oder E-Mail-/Spam-Probleme.';
        if (array_filter($checks, static fn($c) => in_array($c['key'] ?? '', ['bookings_unassigned','payment_schedule_due_order'], true) && ($c['status'] ?? '') === 'warning')) $recs[] = 'Datenreparatur öffnen: Nicht zugeordnete Buchungen klären, Alternative/Upgrade zuweisen oder Zahlungsplan logisch neu berechnen.';
        $recs[] = 'Nach jedem ZIP-Update im Browser Strg+F5 drücken oder Cache leeren, damit neue JavaScript-Dateien sicher geladen werden.';
        $recs[] = 'PDFs niemals direkt aus storage/documents öffnen; immer die geschützten Admin- oder Kundenlinks verwenden.';
        $recs[] = 'Vor weiteren Funktionsupdates auf dem Server einmal Datensicherung erstellen und diese Diagnose als Kontrollliste nutzen.';
        return $recs;
    }


    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') return 0;
        if ($value === '-1') return -1;
        $unit = strtolower(substr($value, -1));
        $number = (float)$value;
        return (int)match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private static function safeCount(string $sql): int
    {
        try { return (int)db()->query($sql)->fetchColumn(); } catch (Throwable) { return 0; }
    }

    private static function safeSetting(string $key, mixed $default = null): mixed
    {
        try { return setting($key, $default); } catch (Throwable) { return $default; }
    }

    private static function tableExists(string $table): bool
    {
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable) { return false; }
    }

    private static function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable) { return false; }
    }

    private static function bytes(float|int $bytes): string
    {
        $units = ['B','KB','MB','GB','TB']; $i = 0; $value = (float)$bytes;
        while ($value >= 1024 && $i < count($units) - 1) { $value /= 1024; $i++; }
        return number_format($value, $i > 1 ? 1 : 0, ',', '.') . ' ' . $units[$i];
    }
}
