<?php
declare(strict_types=1);

/**
 * StayPilot Datenbank-Hilfe
 *
 * Einmaliges, token-geschütztes Hilfsprogramm zum Testen der MySQL-Verbindung,
 * Erstellen/Ergänzen des Schemas und Schreiben von config/local.php.
 * Bestehende Datensätze werden nicht gelöscht oder zurückgesetzt.
 */

const STAYPILOT_HELPER_VERSION = '2.0.9';
const DEFAULT_DB_NAME = '';
const DEFAULT_DB_USER = '';

$root = __DIR__;
$keyFile = $root . '/config/database-helper-key.php';
$lockFile = $root . '/storage/database-helper.lock';
$localFile = $root . '/config/local.php';
$schemaFile = $root . '/database/schema.sql';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('staypilot_db_helper');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function helper_token(): string
{
    if (empty($_SESSION['db_helper_csrf'])) {
        $_SESSION['db_helper_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['db_helper_csrf'];
}

function verify_helper_token(): void
{
    $submitted = (string)($_POST['_csrf'] ?? '');
    if (!hash_equals((string)($_SESSION['db_helper_csrf'] ?? ''), $submitted)) {
        throw new RuntimeException('Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.');
    }
}

function read_setup_key(string $keyFile): string
{
    if (!is_file($keyFile)) {
        throw new RuntimeException('Der lokale Sicherheitsschlüssel fehlt. Kopiere config/database-helper-key.example.php als config/database-helper-key.php und trage dort vorübergehend einen eigenen Code ein.');
    }
    $key = require $keyFile;
    if (!is_string($key) || strlen($key) < 8) {
        throw new RuntimeException('Der interne Sicherheitsschlüssel ist ungültig.');
    }
    return $key;
}

function verify_setup_key(string $expected): void
{
    $submitted = strtoupper(trim((string)($_POST['setup_key'] ?? '')));
    if (!hash_equals(strtoupper($expected), $submitted)) {
        usleep(700000);
        throw new RuntimeException('Der Sicherheitscode ist nicht korrekt.');
    }
}

function pdo_connection(string $host, int $port, string $dbName, string $dbUser, string $dbPassword): PDO
{
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('Die PHP-Erweiterung pdo_mysql fehlt. Bitte im Hosting aktivieren.');
    }
    if ($host === '' || $dbName === '' || $dbUser === '') {
        throw new RuntimeException('Datenbank-Host, Datenbankname und Datenbankbenutzer sind Pflichtfelder.');
    }
    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('Der Datenbank-Port ist ungültig.');
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
    return new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function count_tables(PDO $pdo): int
{
    $stmt = $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
    return (int)$stmt->fetchColumn();
}

function split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $single = false;
    $double = false;
    $backtick = false;
    $escaped = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $buffer .= $char;

        if ($escaped) {
            $escaped = false;
            continue;
        }
        if (($single || $double) && $char === '\\') {
            $escaped = true;
            continue;
        }
        if (!$double && !$backtick && $char === "'") {
            $single = !$single;
            continue;
        }
        if (!$single && !$backtick && $char === '"') {
            $double = !$double;
            continue;
        }
        if (!$single && !$double && $char === '`') {
            $backtick = !$backtick;
            continue;
        }
        if (!$single && !$double && !$backtick && $char === ';') {
            $statement = trim(substr($buffer, 0, -1));
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
        }
    }
    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }
    return $statements;
}

function execute_schema(PDO $pdo, string $schemaFile): int
{
    $sql = @file_get_contents($schemaFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Die Datei database/schema.sql fehlt oder ist leer.');
    }
    $count = 0;
    foreach (split_sql($sql) as $statement) {
        $pdo->exec($statement);
        $count++;
    }
    return $count;
}

function ensure_directory(string $path): void
{
    if (!is_dir($path) && !@mkdir($path, 0770, true) && !is_dir($path)) {
        throw new RuntimeException('Ordner konnte nicht angelegt werden: ' . basename($path));
    }
    if (!is_writable($path)) {
        throw new RuntimeException('Ordner ist nicht beschreibbar: ' . basename($path));
    }
}

function backup_database(PDO $pdo, string $backupDir): ?array
{
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (!$tables) {
        return null;
    }
    ensure_directory($backupDir);
    $filename = 'staypilot-' . date('Ymd-His') . '-vor-datenbank-hilfe.sql';
    $path = $backupDir . '/' . $filename;
    $tmp = $path . '.tmp';
    $fh = @fopen($tmp, 'wb');
    if (!$fh) {
        throw new RuntimeException('Die Sicherungsdatei konnte nicht angelegt werden.');
    }

    try {
        fwrite($fh, "-- StayPilot Sicherung vor Datenbank-Hilfe\n");
        fwrite($fh, '-- Erstellt: ' . date(DATE_ATOM) . "\n");
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach ($tables as $table) {
            $safeTable = str_replace('`', '``', (string)$table);
            $create = $pdo->query("SHOW CREATE TABLE `{$safeTable}`")->fetch(PDO::FETCH_NUM);
            if (!$create || empty($create[1])) {
                continue;
            }
            fwrite($fh, "DROP TABLE IF EXISTS `{$safeTable}`;\n" . $create[1] . ";\n\n");
            $rows = $pdo->query("SELECT * FROM `{$safeTable}`");
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                $columns = array_map(static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`', array_keys($row));
                $values = array_map(static function (mixed $value) use ($pdo): string {
                    return $value === null ? 'NULL' : $pdo->quote((string)$value);
                }, array_values($row));
                fwrite($fh, 'INSERT INTO `' . $safeTable . '` (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n");
            }
            fwrite($fh, "\n");
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
        if (!@rename($tmp, $path)) {
            throw new RuntimeException('Die Sicherungsdatei konnte nicht abgeschlossen werden.');
        }
        @chmod($path, 0640);
        return [
            'filename' => $filename,
            'size' => (int)(filesize($path) ?: 0),
            'checksum' => (string)(hash_file('sha256', $path) ?: ''),
        ];
    } catch (Throwable $e) {
        if (is_resource($fh)) {
            fclose($fh);
        }
        @unlink($tmp);
        throw $e;
    }
}

function write_local_config(string $localFile, array $dbConfig, bool $allowOverwrite): array
{
    $configDir = dirname($localFile);
    if (!is_dir($configDir) || !is_writable($configDir)) {
        throw new RuntimeException('Der Ordner config ist nicht beschreibbar.');
    }
    $previous = is_file($localFile) ? @file_get_contents($localFile) : null;
    if ($previous !== null && !$allowOverwrite) {
        return ['written' => false, 'previous' => $previous, 'created' => false];
    }
    $previousConfig = [];
    if ($previous !== null) {
        try {
            $loaded = require $localFile;
            if (is_array($loaded)) {
                $previousConfig = $loaded;
            }
        } catch (Throwable) {
            $previousConfig = [];
        }
    }
    $local = [
        'db' => $dbConfig,
        // Bestehende Schlüssel unbedingt erhalten, damit verschlüsselte Bestandsdaten lesbar bleiben.
        'app_key' => (string)($previousConfig['app_key'] ?? ('base64:' . base64_encode(random_bytes(32)))),
        'cron_token' => (string)($previousConfig['cron_token'] ?? bin2hex(random_bytes(24))),
    ];
    $content = "<?php\ndeclare(strict_types=1);\nreturn " . var_export($local, true) . ";\n";
    $tmp = $localFile . '.tmp';
    if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
        throw new RuntimeException('config/local.php konnte nicht vorbereitet werden.');
    }
    @chmod($tmp, 0640);
    if (!@rename($tmp, $localFile)) {
        @unlink($tmp);
        throw new RuntimeException('config/local.php konnte nicht gespeichert werden.');
    }
    @chmod($localFile, 0640);
    return ['written' => true, 'previous' => $previous, 'created' => $previous === null];
}

function restore_local_config(string $localFile, array $state): void
{
    if (!($state['written'] ?? false)) {
        return;
    }
    if (($state['created'] ?? false) === true) {
        @unlink($localFile);
        return;
    }
    if (is_string($state['previous'] ?? null)) {
        @file_put_contents($localFile, $state['previous'], LOCK_EX);
        @chmod($localFile, 0640);
    }
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function insert_defaults(PDO $pdo, string $propertyName, string $adminEmail): void
{
    $settings = [
        'property_name' => $propertyName,
        'currency' => 'EUR',
        'checkin_time' => '16:00',
        'checkout_time' => '10:00',
        'contact_email' => $adminEmail,
        'contact_phone' => '',
        'legal_name' => $propertyName,
        'country' => 'Spanien',
        'public_booking_enabled' => true,
        'accent_color' => '#2563eb',
        'auto_pre_update_backup' => true,
        'backup_retention_count' => 12,
        'session_idle_minutes' => 120,
        'smtp_host' => '',
        'smtp_port' => '587',
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)');
    foreach ($settings as $key => $value) {
        $stmt->execute([$key, is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    $types = [
        ['1 BM','1-BM',2,2,0,1,2,10], ['2 BM','2-BM',4,2,2,2,4,20], ['3/4 BM','3-4-BM',4,2,2,2,4,30],
        ['4 GB','4-GB',4,4,0,2,4,40], ['4 PM','4-PM',4,2,2,2,4,50], ['5 PM','5-PM',5,3,2,2,5,60],
        ['4 CM','4-CM',4,2,2,2,4,70], ['3/4 SM','3-4-SM',4,2,2,2,4,80], ['3/4 Plus TM','3-4-PLUS-TM',4,2,2,2,4,90],
        ['3/4 TM','3-4-TM',4,2,2,2,4,100],
    ];
    $typeStmt = $pdo->prepare('INSERT IGNORE INTO apartment_types(name,code,max_occupancy,default_adults,default_children,bedrooms,beds,sort_order) VALUES(?,?,?,?,?,?,?,?)');
    foreach ($types as $type) {
        $typeStmt->execute($type);
    }

    if (table_exists($pdo, 'integrations')) {
        $integration = $pdo->prepare('INSERT IGNORE INTO integrations(provider,active,mode,base_url,settings_json) VALUES(?,?,?,?,?)');
        $integration->execute(['booking_com',0,'test','https://supply-xml.booking.com',json_encode(['notes'=>'Nur nach offiziell freigeschalteter Anbindung aktivieren.'], JSON_UNESCAPED_UNICODE)]);
        $integration->execute(['hotel_spider',0,'test','',json_encode(['health_path'=>'','reservations_path'=>'reservations','availability_path'=>'availability'], JSON_UNESCAPED_UNICODE)]);
    }
}

function ensure_admin(PDO $pdo, string $name, string $email, string $password): bool
{
    if (!table_exists($pdo, 'users')) {
        throw new RuntimeException('Die Benutzertabelle konnte nicht angelegt werden.');
    }
    $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) {
        return false;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Für die erste Anmeldung wird eine gültige Admin-E-Mail benötigt.');
    }
    if (strlen($password) < 8) {
        throw new RuntimeException('Das Admin-Passwort muss mindestens 8 Zeichen lang sein.');
    }
    $stmt = $pdo->prepare('INSERT INTO users(name,email,password_hash,role,active) VALUES(?,?,?,?,1)');
    $stmt->execute([$name !== '' ? $name : 'Administrator', strtolower($email), password_hash($password, PASSWORD_DEFAULT), 'admin']);
    return true;
}

function run_migrations(PDO $pdo, array $dbConfig): void
{
    require_once __DIR__ . '/src/helpers.php';
    require_once __DIR__ . '/src/Database.php';
    require_once __DIR__ . '/src/Auth.php';
    require_once __DIR__ . '/src/Crypto.php';
    require_once __DIR__ . '/src/HttpException.php';
    require_once __DIR__ . '/src/Validator.php';
    require_once __DIR__ . '/src/Services/AppLogger.php';
    require_once __DIR__ . '/src/Services/AuditLogger.php';
    require_once __DIR__ . '/src/Services/BackupManager.php';
    require_once __DIR__ . '/src/Services/SystemDiagnostics.php';
    require_once __DIR__ . '/src/Migrator.php';
    Database::connect($dbConfig);
    Migrator::repairKnownSchema();
}

function write_helper_log(string $root, array $data): void
{
    $dir = $root . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    if (!is_writable($dir)) {
        return;
    }
    unset($data['password'], $data['setup_key']);
    $line = '[' . date(DATE_ATOM) . '] ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    @file_put_contents($dir . '/database-helper.log', $line, FILE_APPEND | LOCK_EX);
}

$errors = [];
$success = null;
$testResult = null;
$lockWarning = '';
$expectedKey = '';
$locked = is_file($lockFile);
$localExists = is_file($localFile);
$existingConfig = [];

try {
    $expectedKey = read_setup_key($keyFile);
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

if ($localExists) {
    try {
        $loaded = require $localFile;
        if (is_array($loaded)) {
            $existingConfig = $loaded;
        }
    } catch (Throwable) {
        $errors[] = 'Die bestehende config/local.php konnte nicht gelesen werden.';
    }
}

$defaults = [
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => DEFAULT_DB_NAME,
    'db_user' => DEFAULT_DB_USER,
    'property_name' => 'StayPilot Ferienapartments',
    'admin_name' => 'Administrator',
    'admin_email' => '',
];

if (!empty($existingConfig['db']['dsn']) && preg_match('/host=([^;]+).*port=(\d+).*dbname=([^;]+)/', (string)$existingConfig['db']['dsn'], $m)) {
    $defaults['db_host'] = $m[1];
    $defaults['db_port'] = $m[2];
    $defaults['db_name'] = $m[3];
}
if (!empty($existingConfig['db']['user'])) {
    $defaults['db_user'] = (string)$existingConfig['db']['user'];
}

$values = array_merge($defaults, array_intersect_key($_POST, $defaults));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked && !$errors) {
    $localState = ['written' => false];
    try {
        verify_helper_token();
        verify_setup_key($expectedKey);

        $action = (string)($_POST['action'] ?? '');
        $host = trim((string)($_POST['db_host'] ?? 'localhost'));
        $port = (int)($_POST['db_port'] ?? 3306);
        $dbName = trim((string)($_POST['db_name'] ?? ''));
        $dbUser = trim((string)($_POST['db_user'] ?? ''));
        $dbPassword = (string)($_POST['db_password'] ?? '');
        if ($dbPassword === '' && isset($existingConfig['db']['password']) && $localExists) {
            $dbPassword = (string)$existingConfig['db']['password'];
        }
        $pdo = pdo_connection($host, $port, $dbName, $dbUser, $dbPassword);
        $serverVersion = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
        $tableCountBefore = count_tables($pdo);

        if ($action === 'test') {
            $testResult = [
                'server' => $serverVersion,
                'database' => (string)$pdo->query('SELECT DATABASE()')->fetchColumn(),
                'tables' => $tableCountBefore,
            ];
        } elseif ($action === 'setup') {
            if (empty($_POST['confirm_backup'])) {
                throw new RuntimeException('Bitte bestätigen, dass du eine zusätzliche externe Sicherung besitzt.');
            }
            if (!is_file($schemaFile)) {
                throw new RuntimeException('database/schema.sql fehlt. Bitte die ZIP vollständig hochladen.');
            }
            ensure_directory($root . '/storage');
            ensure_directory($root . '/storage/backups');
            ensure_directory($root . '/storage/logs');
            $overwrite = !empty($_POST['overwrite_local']);
            if ((!$localExists || $overwrite) && (!is_dir(dirname($localFile)) || !is_writable(dirname($localFile)))) {
                throw new RuntimeException('Der Ordner config ist nicht beschreibbar.');
            }

            $backup = backup_database($pdo, $root . '/storage/backups');
            $statementCount = execute_schema($pdo, $schemaFile);

            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
            $dbConfig = ['dsn' => $dsn, 'user' => $dbUser, 'password' => $dbPassword];
            $localState = write_local_config($localFile, $dbConfig, $overwrite);

            if ($localExists && !($localState['written'] ?? false)) {
                $configuredDsn = (string)($existingConfig['db']['dsn'] ?? '');
                $configuredUser = (string)($existingConfig['db']['user'] ?? '');
                if ($configuredDsn !== $dsn || $configuredUser !== $dbUser) {
                    throw new RuntimeException('Die vorhandene config/local.php zeigt auf andere Zugangsdaten. Aktiviere „bestehende Konfiguration ersetzen“ oder verwende die dort eingetragenen Daten.');
                }
            }

            run_migrations($pdo, $dbConfig);
            insert_defaults(
                $pdo,
                trim((string)($_POST['property_name'] ?? 'StayPilot Ferienapartments')) ?: 'StayPilot Ferienapartments',
                strtolower(trim((string)($_POST['admin_email'] ?? '')))
            );
            $adminCreated = ensure_admin(
                $pdo,
                trim((string)($_POST['admin_name'] ?? 'Administrator')),
                strtolower(trim((string)($_POST['admin_email'] ?? ''))),
                (string)($_POST['admin_password'] ?? '')
            );
            $pdo->prepare('INSERT INTO schema_migrations(version,applied_at) VALUES(?,NOW()) ON DUPLICATE KEY UPDATE applied_at=VALUES(applied_at)')
                ->execute(['2.0.9']);

            $tableCountAfter = count_tables($pdo);
            $lockPayload = [
                'completed_at' => date(DATE_ATOM),
                'helper_version' => STAYPILOT_HELPER_VERSION,
                'database' => $dbName,
                'tables_before' => $tableCountBefore,
                'tables_after' => $tableCountAfter,
                'backup' => $backup['filename'] ?? null,
            ];
            if (@file_put_contents($lockFile, json_encode($lockPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
                $lockWarning = 'Die Einrichtung war erfolgreich, aber die automatische Sperrdatei konnte nicht geschrieben werden. Bitte datenbank-hilfe.php sofort manuell löschen.';
            } else {
                @chmod($lockFile, 0640);
            }
            write_helper_log($root, [
                'action' => 'setup_success',
                'database' => $dbName,
                'user' => $dbUser,
                'tables_before' => $tableCountBefore,
                'tables_after' => $tableCountAfter,
                'schema_statements' => $statementCount,
                'backup' => $backup['filename'] ?? null,
                'admin_created' => $adminCreated,
            ]);
            $success = [
                'server' => $serverVersion,
                'database' => $dbName,
                'tables_before' => $tableCountBefore,
                'tables_after' => $tableCountAfter,
                'schema_statements' => $statementCount,
                'backup' => $backup,
                'admin_created' => $adminCreated,
                'config_written' => (bool)($localState['written'] ?? false),
                'lock_warning' => $lockWarning,
            ];
            $locked = is_file($lockFile);
        } else {
            throw new RuntimeException('Unbekannte Aktion.');
        }
    } catch (PDOException $e) {
        restore_local_config($localFile, $localState);
        $errors[] = 'Datenbankfehler: ' . $e->getMessage();
        write_helper_log($root, ['action' => 'database_error', 'message' => $e->getMessage()]);
    } catch (Throwable $e) {
        restore_local_config($localFile, $localState);
        $errors[] = $e->getMessage();
        write_helper_log($root, ['action' => 'helper_error', 'message' => $e->getMessage()]);
    }
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>StayPilot – Datenbank-Hilfe</title>
<style>
:root{--blue:#2563eb;--dark:#101827;--text:#172033;--muted:#607089;--line:#dce4ef;--bg:#eef3f9;--ok:#067647;--danger:#b42335;--warn:#9a6700}*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:linear-gradient(135deg,#edf4ff,#f8fafc);color:var(--text);min-height:100vh;padding:24px}.shell{max-width:980px;margin:0 auto;background:#fff;border:1px solid var(--line);border-radius:24px;box-shadow:0 28px 80px rgba(30,50,80,.14);overflow:hidden}.head{background:var(--dark);color:#fff;padding:28px 32px}.head h1{margin:0 0 8px;font-size:clamp(25px,4vw,38px)}.head p{margin:0;color:#bac8db}.content{padding:30px 32px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.span{grid-column:1/-1}.field{display:grid;gap:7px}.field label{font-size:12px;font-weight:800;color:#506078}.field input{width:100%;border:1px solid var(--line);border-radius:11px;padding:11px 12px;font:inherit}.field input:focus{outline:3px solid rgba(37,99,235,.13);border-color:var(--blue)}.card{border:1px solid var(--line);background:#f8fafc;border-radius:15px;padding:16px}.card h2{font-size:17px;margin:0 0 12px}.alert{padding:14px 16px;border-radius:13px;margin-bottom:16px;line-height:1.5}.danger{background:#fff1f2;border:1px solid #fecdd3;color:var(--danger)}.success{background:#ecfdf3;border:1px solid #abefc6;color:var(--ok)}.warning{background:#fffaeb;border:1px solid #fedf89;color:var(--warn)}.info{background:#eff6ff;border:1px solid #bfdbfe;color:#1d4f91}.buttons{display:flex;gap:11px;flex-wrap:wrap;margin-top:18px}.btn{border:0;border-radius:12px;padding:12px 17px;font-weight:850;font-size:14px;cursor:pointer}.primary{background:var(--blue);color:#fff}.secondary{background:#e9eef5;color:#25334a}.check{display:flex;align-items:flex-start;gap:10px;line-height:1.45}.check input{margin-top:4px}.small{font-size:13px;color:var(--muted)}code{background:#edf1f6;padding:2px 6px;border-radius:6px}.result{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;margin-top:12px}.result div{background:#fff;border:1px solid var(--line);border-radius:10px;padding:10px}.result b{display:block;font-size:12px;color:var(--muted);margin-bottom:3px}.locked{display:grid;place-items:center;text-align:center;padding:22px}.locked .icon{font-size:50px}.footer{border-top:1px solid var(--line);padding:17px 32px;color:var(--muted);font-size:12px;background:#f8fafc}@media(max-width:720px){body{padding:10px}.content,.head{padding:22px}.grid,.result{grid-template-columns:1fr}.span{grid-column:auto}}
</style>
</head>
<body>
<main class="shell">
<header class="head"><h1>🛠️ StayPilot Datenbank-Hilfe</h1><p>Verbindung testen, Tabellen sicher erstellen oder ergänzen und die lokale Konfiguration schreiben.</p></header>
<section class="content">
<?php foreach ($errors as $error): ?><div class="alert danger"><strong>Fehler:</strong><br><?=h($error)?></div><?php endforeach; ?>

<?php if ($success): ?>
<div class="alert success"><strong>Die Datenbank wurde erfolgreich eingerichtet.</strong><br>Vorhandene Datensätze wurden nicht gelöscht oder zurückgesetzt.</div>
<div class="result">
<div><b>Datenbank</b><?=h($success['database'])?></div>
<div><b>MySQL/MariaDB</b><?=h($success['server'])?></div>
<div><b>Tabellen vorher</b><?=h($success['tables_before'])?></div>
<div><b>Tabellen nachher</b><?=h($success['tables_after'])?></div>
<div><b>SQL-Anweisungen geprüft</b><?=h($success['schema_statements'])?></div>
<div><b>Admin neu angelegt</b><?=$success['admin_created']?'Ja':'Nein, Benutzer war bereits vorhanden'?></div>
<div><b>Konfiguration geschrieben</b><?=$success['config_written']?'Ja':'Bestehende Konfiguration beibehalten'?></div>
<div><b>Sicherung</b><?=h($success['backup']['filename'] ?? 'Leere Datenbank – keine Sicherung nötig')?></div>
</div>
<?php if (!empty($success['lock_warning'])): ?><div class="alert danger" style="margin-top:18px"><strong>Achtung:</strong> <?=h($success['lock_warning'])?></div><?php endif; ?>
<div class="alert warning" style="margin-top:18px"><strong>Jetzt wichtig:</strong> Lösche die Datei <code>datenbank-hilfe.php</code> vom Server. <?=empty($success['lock_warning'])?'Das Programm ist zusätzlich automatisch gesperrt.':'Verlasse dich in diesem Fall nicht auf eine automatische Sperre.'?></div>
<div class="buttons"><a class="btn primary" href="login.php" style="text-decoration:none">Zum StayPilot-Login</a></div>

<?php elseif ($locked): ?>
<div class="locked"><div><div class="icon">🔒</div><h2>Datenbank-Hilfe ist gesperrt</h2><p>Die Einrichtung wurde bereits abgeschlossen. Lösche <code>datenbank-hilfe.php</code> vom Server.</p><a class="btn primary" href="login.php" style="display:inline-block;text-decoration:none">Zum Login</a></div></div>

<?php else: ?>
<div class="alert info"><strong>Keine Zugangsdaten in der ZIP:</strong> Datenbankname, Benutzer und Passwort müssen auf dem Server eingetragen oder aus einer bereits vorhandenen <code>config/local.php</code> übernommen werden.</div>
<?php if ($localExists): ?><div class="alert warning"><strong>Bestehende Installation erkannt:</strong> <code>config/local.php</code> ist vorhanden. Ein leeres Passwortfeld verwendet das bereits gespeicherte Passwort. Die Konfiguration wird nur nach ausdrücklicher Bestätigung ersetzt.</div><?php endif; ?>
<?php if ($testResult): ?><div class="alert success"><strong>Verbindung erfolgreich.</strong><div class="result"><div><b>Server</b><?=h($testResult['server'])?></div><div><b>Datenbank</b><?=h($testResult['database'])?></div><div><b>Vorhandene Tabellen</b><?=h($testResult['tables'])?></div></div></div><?php endif; ?>
<form method="post" autocomplete="off">
<input type="hidden" name="_csrf" value="<?=h(helper_token())?>">
<div class="grid">
<div class="field span"><label>Sicherheitscode aus der Anleitung *</label><input name="setup_key" required autocomplete="one-time-code" placeholder="XXXX-XXXX-XXXX"></div>
<div class="card span"><h2>1. Datenbankverbindung</h2><div class="grid">
<div class="field"><label>Datenbank-Host *</label><input name="db_host" value="<?=h($values['db_host'])?>" required></div>
<div class="field"><label>Port *</label><input type="number" min="1" max="65535" name="db_port" value="<?=h($values['db_port'])?>" required></div>
<div class="field"><label>Datenbankname *</label><input name="db_name" value="<?=h($values['db_name'])?>" required></div>
<div class="field"><label>Datenbankbenutzer *</label><input name="db_user" value="<?=h($values['db_user'])?>" required></div>
<div class="field span"><label>Datenbankpasswort <?=$localExists?'(leer = vorhandenes Passwort verwenden)':'*'?></label><input type="password" name="db_password" <?=$localExists?'':'required'?> autocomplete="new-password"></div>
<?php if ($localExists): ?><label class="check span"><input type="checkbox" name="overwrite_local" value="1"> <span>Bestehende <code>config/local.php</code> mit diesen Daten ersetzen. Nur aktivieren, wenn Datenbank oder Zugang wirklich geändert werden sollen.</span></label><?php endif; ?>
</div></div>
<div class="card span"><h2>2. Erster Administrator</h2><p class="small">Diese Daten werden nur verwendet, wenn noch kein Benutzer in der Datenbank vorhanden ist.</p><div class="grid">
<div class="field span"><label>Name des Betriebs</label><input name="property_name" value="<?=h($values['property_name'])?>"></div>
<div class="field"><label>Administratorname</label><input name="admin_name" value="<?=h($values['admin_name'])?>"></div>
<div class="field"><label>Admin-E-Mail</label><input type="email" name="admin_email" value="<?=h($values['admin_email'])?>"></div>
<div class="field span"><label>Admin-Passwort, mindestens 8 Zeichen</label><input type="password" minlength="8" name="admin_password" autocomplete="new-password"></div>
</div></div>
<label class="check span"><input type="checkbox" name="confirm_backup" value="1"> <span>Ich habe zusätzlich eine eigene Sicherung der bisherigen Dateien und Datenbank. Das Hilfsprogramm erstellt bei vorhandenen Tabellen vor Änderungen noch eine weitere SQL-Sicherung.</span></label>
</div>
<div class="buttons"><button class="btn secondary" type="submit" name="action" value="test">Nur Verbindung testen</button><button class="btn primary" type="submit" name="action" value="setup">Datenbank sicher einrichten</button></div>
</form>
<?php endif; ?>
</section>
<footer class="footer">StayPilot Datenbank-Hilfe <?=h(STAYPILOT_HELPER_VERSION)?> · Keine Lösch- oder Reset-Befehle · Nach erfolgreicher Nutzung vom Server entfernen.</footer>
</main>
</body>
</html>
