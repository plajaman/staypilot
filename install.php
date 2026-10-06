<?php
declare(strict_types=1);
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/Database.php';

$config = require __DIR__ . '/config/config.php';
date_default_timezone_set($config['timezone'] ?? 'Europe/Berlin');
$localFile = __DIR__ . '/config/local.php';
$installed = is_file($localFile);
// STAYPILOT_INSTALLER_PROTECTION_V236105: Produktivschutz.
if ($installed) {
    http_response_code(403);
    ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Installer geschützt</title><style>body{font-family:system-ui,Arial;background:#f8fafc;color:#172033;display:grid;place-items:center;min-height:100vh;margin:0;padding:24px}.box{max-width:720px;background:#fff;border:1px solid #dbe3ef;border-radius:22px;padding:28px;box-shadow:0 18px 60px rgba(15,23,42,.12)}.btn{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;border-radius:12px;padding:12px 16px;font-weight:800}</style></head><body><main class="box"><h1>StayPilot ist bereits installiert</h1><p>Der Installer ist im Produktivbetrieb geschützt und kann nicht erneut ausgeführt werden. Das verhindert versehentliche Neuinstallationen oder Datenverlust.</p><p><a class="btn" href="login.php">Zum Login</a></p></main></body></html><?php
    exit;
}
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $port = (int)($_POST['db_port'] ?? 3306);
    $dbname = trim($_POST['db_name'] ?? '');
    $dbuser = trim($_POST['db_user'] ?? '');
    $dbpass = (string)($_POST['db_password'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? 'Administrator');
    $adminEmail = mb_strtolower(trim($_POST['admin_email'] ?? ''));
    $adminPassword = (string)($_POST['admin_password'] ?? '');
    $propertyName = trim($_POST['property_name'] ?? 'Meine Ferienwohnungen');

    try {
        if ($dbname === '' || $dbuser === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminPassword) < 8) {
            throw new RuntimeException('Bitte alle Pflichtfelder ausfüllen. Das Admin-Passwort muss mindestens 8 Zeichen haben.');
        }
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('Die PHP-Erweiterung pdo_mysql fehlt. Bitte beim Hoster PHP-MySQL/PDO aktivieren.');
        }
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbuser, $dbpass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $sql = file_get_contents(__DIR__ . '/database/schema.sql');
        if ($sql === false) {
            throw new RuntimeException('Datenbankschema fehlt.');
        }
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
        $stmt = $pdo->prepare('INSERT INTO users(name,email,password_hash,role,active) VALUES(?,?,?,?,1)');
        $stmt->execute([$adminName, $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), 'admin']);

        $settings = [
            'property_name' => $propertyName,
            'currency' => 'EUR',
            'checkin_time' => '16:00',
            'checkout_time' => '10:00',
            'contact_email' => $adminEmail,
            'contact_phone' => '',
            'legal_name' => $propertyName,
            'tax_id' => '',
            'police_registration_number' => '',
            'full_address' => '',
            'postal_code' => '',
            'city' => '',
            'province' => '',
            'country' => 'Spanien',
            'public_booking_enabled' => true,
            'accent_color' => '#2563eb',
            'schema_version' => '2.0.10',
            'auto_pre_update_backup' => true,
            'backup_retention_count' => 12,
            'session_idle_minutes' => 120,
            'smtp_host' => '',
            'smtp_port' => '587',
            'default_min_stay' => 1,
            'allow_gap_booking_override' => true,
            'guest_public_display_hours' => 12,
            'guest_public_title_de' => 'Bezugsbereite Wohnungen',
            'guest_public_title_es' => 'Apartamentos listos',
            'guest_public_title_en' => 'Apartments ready',
            'guest_public_empty_de' => 'Zurzeit wurde noch keine Wohnung für die Schlüsselabholung freigegeben.',
            'guest_public_empty_es' => 'Actualmente no hay ningún apartamento liberado para recoger la llave.',
            'guest_public_empty_en' => 'No apartment has currently been released for key collection.',
            'guest_public_show_house' => true,
        ];
        $stmt = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, is_string($value) ? $value : json_encode($value)]);
        }
        $typeDefaults = [
            ['1 BM','1-BM',2,2,0,1,2,10], ['2 BM','2-BM',4,2,2,2,4,20], ['3/4 BM','3-4-BM',4,2,2,2,4,30],
            ['4 GB','4-GB',4,4,0,2,4,40], ['4 PM','4-PM',4,2,2,2,4,50], ['5 PM','5-PM',5,3,2,2,5,60],
            ['4 CM','4-CM',4,2,2,2,4,70], ['3/4 SM','3-4-SM',4,2,2,2,4,80], ['3/4 Plus TM','3-4-PLUS-TM',4,2,2,2,4,90],
            ['3/4 TM','3-4-TM',4,2,2,2,4,100]
        ];
        $typeStmt = $pdo->prepare('INSERT INTO apartment_types(name,code,max_occupancy,default_adults,default_children,bedrooms,beds,sort_order) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)');
        foreach ($typeDefaults as $row) $typeStmt->execute($row);

        // Leere, klar erkennbare Saisonvorlagen: keine erfundenen Zeiträume oder Preise.
        $seasonStmt = $pdo->prepare('INSERT INTO seasons(name,color,priority,default_min_stay,notes,active) VALUES(?,?,?,?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name)');
        $seasonStmt->execute(['Vorsaison','#0ea5e9',10,1,'Zeiträume und Preise bitte festlegen']);
        $seasonStmt->execute(['Zwischensaison','#f59e0b',20,1,'Zeiträume und Preise bitte festlegen']);
        $seasonStmt->execute(['Hauptsaison','#ef4444',30,1,'Zeiträume und Preise bitte festlegen']);

        $channelDefaults = [
            ['Direkt','DIREKT','#2563eb','Direkte Buchung',10],
            ['Webseite','WEBSEITE','#0f9f6e','Eigene Webseite',20],
            ['Booking.com','BOOKING','#1d4ed8','Booking.com',30],
            ['Airbnb','AIRBNB','#ef4444','Airbnb',40],
            ['AGR','AGR','#7c3aed','AGR / Vermittlung',50],
            ['Passant','PASSANT','#f59e0b','Laufkundschaft',60],
            ['Telefon','TELEFON','#0891b2','Telefonische Buchung',70],
            ['E-Mail','EMAIL','#64748b','Buchung per E-Mail',80],
        ];
        $channelStmt = $pdo->prepare('INSERT INTO booking_channels(name,code,color,description,sort_order,active) VALUES(?,?,?,?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name)');
        foreach ($channelDefaults as $row) $channelStmt->execute($row);

        $pdo->prepare('INSERT INTO schema_migrations(version,applied_at) VALUES(?,NOW()) ON DUPLICATE KEY UPDATE applied_at=VALUES(applied_at)')->execute(['2.0.9']);

        $stmt = $pdo->prepare('INSERT INTO integrations(provider,active,mode,base_url,settings_json) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE provider=VALUES(provider)');
        $stmt->execute(['booking_com',0,'test','https://supply-xml.booking.com',json_encode(['notes'=>'Direkter Zugriff nur als freigeschalteter Booking.com Connectivity Partner.'])]);
        $stmt->execute(['hotel_spider',0,'test','',json_encode(['health_path'=>'','reservations_path'=>'reservations','availability_path'=>'availability','auth_header'=>'Authorization','auth_prefix'=>'Bearer '])]);



        $local = [
            'db' => ['dsn' => $dsn, 'user' => $dbuser, 'password' => $dbpass],
            'app_key' => 'base64:' . base64_encode(random_bytes(32)),
            'cron_token' => bin2hex(random_bytes(24)),
        ];
        $content = "<?php\nreturn " . var_export($local, true) . ";\n";
        if (file_put_contents($localFile, $content, LOCK_EX) === false) {
            throw new RuntimeException('config/local.php konnte nicht geschrieben werden. Ordner config bitte kurz beschreibbar machen.');
        }
        @chmod($localFile, 0640);
        $success = true;
        $installed = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>StayPilot installieren</title>
<style>
:root{--a:#2563eb;--bg:#eef3f9;--text:#172033;--line:#dce4ef}*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,Arial;background:linear-gradient(135deg,#eef4ff,#f7f9fc);color:var(--text);min-height:100vh;display:grid;place-items:center;padding:24px}.wrap{width:min(860px,100%);background:#fff;border:1px solid var(--line);border-radius:24px;box-shadow:0 30px 80px rgba(30,50,80,.14);overflow:hidden}.head{padding:28px 32px;background:#101827;color:#fff}.head h1{margin:0 0 8px}.head p{margin:0;color:#b9c7da}.body{padding:30px 32px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.span{grid-column:1/-1}.field{display:grid;gap:6px}.field label{font-size:12px;font-weight:750;color:#526078}.field input{border:1px solid var(--line);border-radius:11px;padding:11px 12px;font:inherit}.field input:focus{outline:3px solid rgba(37,99,235,.12);border-color:var(--a)}.btn{border:0;border-radius:12px;background:var(--a);color:#fff;font-weight:800;padding:12px 18px;cursor:pointer}.alert{padding:13px 15px;border-radius:12px;margin-bottom:18px}.error{background:#fff1f2;color:#b42335;border:1px solid #fecdd3}.ok{background:#ecfdf5;color:#08754f;border:1px solid #a7f3d0}.note{background:#f8fafc;border:1px solid var(--line);padding:13px;border-radius:12px;color:#5c687b;font-size:13px}.check{display:flex;gap:10px;align-items:flex-start}.check input{margin-top:3px}@media(max-width:680px){.grid{grid-template-columns:1fr}.span{grid-column:auto}.head,.body{padding:23px}}
</style></head><body><main class="wrap"><header class="head"><h1>🏡 StayPilot SaaS</h1><p>Professionelle PHP/MySQL-Verwaltung für Häuser, Wohnungstypen, Apartments, Gäste und Buchungen.</p></header><section class="body">
<?php if ($success): ?><div class="alert ok"><b>Installation abgeschlossen.</b><br>Die Anwendung und das Administratorkonto wurden angelegt.</div><a class="btn" href="login.php" style="display:inline-block;text-decoration:none">Zum Admin-Login</a>
<?php elseif ($installed): ?><div class="alert ok"><b>StayPilot ist bereits installiert.</b></div><a class="btn" href="login.php" style="display:inline-block;text-decoration:none">Zum Login</a>
<?php else: ?>
<?php if ($error): ?><div class="alert error"><b>Installation nicht möglich:</b><br><?=e($error)?></div><?php endif; ?>
<form method="post"><div class="grid">
<div class="span note"><b>Voraussetzungen:</b> PHP 8.1 oder neuer, PDO MySQL, MySQL/MariaDB und Schreibrecht für <code>config/</code>. Die Datenbank muss beim Hoster bereits angelegt sein.</div>
<div class="field"><label>Datenbank-Host *</label><input name="db_host" value="<?=e($_POST['db_host']??'localhost')?>" required></div>
<div class="field"><label>Port *</label><input type="number" name="db_port" value="<?=e($_POST['db_port']??'3306')?>" required></div>
<div class="field"><label>Datenbankname *</label><input name="db_name" value="<?=e($_POST['db_name']??'')?>" required></div>
<div class="field"><label>Datenbank-Benutzer *</label><input name="db_user" value="<?=e($_POST['db_user']??'')?>" required></div>
<div class="field span"><label>Datenbank-Passwort</label><input type="password" name="db_password"></div>
<div class="field span"><label>Name des Betriebs *</label><input name="property_name" value="<?=e($_POST['property_name']??'Meine Ferienwohnungen')?>" required></div>
<div class="field"><label>Administratorname *</label><input name="admin_name" value="<?=e($_POST['admin_name']??'Administrator')?>" required></div>
<div class="field"><label>Admin-E-Mail *</label><input type="email" name="admin_email" value="<?=e($_POST['admin_email']??'')?>" required></div>
<div class="field span"><label>Admin-Passwort (mindestens 8 Zeichen) *</label><input type="password" name="admin_password" minlength="8" required></div>
<div class="span"><button class="btn" type="submit">Anwendung installieren</button></div>
</div></form><?php endif; ?></section></main></body></html>
