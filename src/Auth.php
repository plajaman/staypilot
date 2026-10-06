<?php
declare(strict_types=1);

final class Auth
{
    public const ROLES = ['admin','manager','reception','housekeeping_manager','housekeeping','readonly'];
    private static ?array $cachedUser = null;

    public static function user(): ?array
    {
        if (empty($_SESSION['user_id'])) return null;
        $idleLimit = !empty($_SESSION['staypilot_remember'])
            ? 30 * 24 * 60 * 60
            : (int)setting('session_idle_minutes', 120) * 60;
        if ($idleLimit > 0 && !empty($_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > $idleLimit) {
            self::logout();
            return null;
        }
        $_SESSION['last_activity'] = time();
        if (self::$cachedUser !== null) return self::$cachedUser;
        $stmt = db()->prepare('SELECT id,name,email,role,active,last_login_at FROM users WHERE id=? LIMIT 1');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user || !(int)$user['active']) {
            self::logout();
            return null;
        }
        return self::$cachedUser = $user;
    }

    public static function attempt(string $identity, string $password): bool
    {
        $identity = mb_strtolower(trim($identity));
        if ($identity === '' || $password === '') return false;
        $stmt = db()->prepare(
            'SELECT * FROM users
             WHERE (LOWER(email)=? OR LOWER(name)=?)
             ORDER BY CASE WHEN LOWER(email)=? THEN 0 ELSE 1 END, id
             LIMIT 1'
        );
        $stmt->execute([$identity, $identity, $identity]);
        $user = $stmt->fetch();
        if (!$user || !(int)$user['active']) {
            usleep(250000);
            return false;
        }
        if (!empty($user['locked_until']) && strtotime((string)$user['locked_until']) > time()) {
            usleep(250000);
            return false;
        }
        if (!password_verify($password, (string)$user['password_hash'])) {
            $failed = (int)($user['failed_login_count'] ?? 0) + 1;
            $lock = $failed >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
            db()->prepare('UPDATE users SET failed_login_count=?,locked_until=? WHERE id=?')->execute([$failed, $lock, $user['id']]);
            AppLogger::info('Fehlgeschlagener Login.', ['identity' => $identity, 'user_id' => $user['id']], 'auth');
            usleep(250000);
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['login_at'] = time();
        $_SESSION['last_activity'] = time();
        self::$cachedUser = null;
        db()->prepare('UPDATE users SET failed_login_count=0,locked_until=NULL,last_login_at=NOW() WHERE id=?')->execute([$user['id']]);
        AuditLogger::record('user', (int)$user['id'], 'login', null, ['last_login_at' => date('Y-m-d H:i:s')], 'Anmeldung erfolgreich');
        return true;
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) {
            if (is_api_request()) json_response(['ok' => false, 'message' => 'Nicht angemeldet.', 'code' => 'unauthorized'], 401);
            $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
            $prefix = preg_match('#/(admin|team|team-manager)/#', $scriptName) ? '../' : '';
            header('Location: ' . $prefix . 'login.php');
            exit;
        }
        return $user;
    }

    public static function requireRole(array|string $roles): array
    {
        $user = self::requireLogin();
        $allowed = is_array($roles) ? $roles : [$roles];
        if (!in_array($user['role'], $allowed, true)) throw new ForbiddenException();
        return $user;
    }

    /**
     * Liefert das eindeutige Startziel für die angemeldete Rolle.
     * Der Prefix wird bewusst vom Aufrufer übergeben, damit die Funktion
     * sowohl aus dem Stammverzeichnis als auch aus Unterordnern sicher arbeitet.
     */
    public static function landingPath(array $user, string $prefix = ''): string
    {
        return match ((string)($user['role'] ?? 'readonly')) {
            'housekeeping' => $prefix . 'team/',
            'housekeeping_manager' => $prefix . 'team-manager/',
            default => $prefix . 'admin/',
        };
    }

    public static function redirectToLanding(array $user, string $prefix = ''): never
    {
        header('Location: ' . self::landingPath($user, $prefix));
        exit;
    }

    /**
     * Beschreibt die klar getrennten Portal-Anmeldeseiten. Die Anmeldung selbst
     * bleibt zentral; die Portalseite erklärt jedoch eindeutig, für wen sie ist.
     */
    public static function portalDefinition(string $portal): array
    {
        return match ($portal) {
            'admin' => [
                'key' => 'admin',
                'title' => 'StayPilot Verwaltung',
                'subtitle' => 'Anmeldung für Administrator, Manager und Rezeption',
                'roles' => ['admin','manager','reception','readonly'],
                'path' => 'admin/',
                'icon' => '⚙️',
            ],
            'manager' => [
                'key' => 'manager',
                'title' => 'StayPilot Housekeeping-Leitung',
                'subtitle' => 'Anmeldung für Gouvernante und Housekeeping-Leitung',
                'roles' => ['housekeeping_manager'],
                'path' => 'team-manager/',
                'icon' => '🗂️',
            ],
            'team' => [
                'key' => 'team',
                'title' => 'StayPilot Team',
                'subtitle' => 'Anmeldung für Reinigungskräfte',
                'roles' => ['housekeeping'],
                'path' => 'team/',
                'icon' => '🧹',
            ],
            default => [
                'key' => '',
                'title' => 'StayPilot',
                'subtitle' => 'Zentrale Anmeldung',
                'roles' => self::ROLES,
                'path' => '',
                'icon' => '🏡',
            ],
        };
    }

    public static function normalizePortal(string $portal): string
    {
        return in_array($portal, ['admin','manager','team'], true) ? $portal : '';
    }

    public static function portalAllowsRole(string $portal, string $role): bool
    {
        $definition = self::portalDefinition(self::normalizePortal($portal));
        return in_array($role, $definition['roles'], true);
    }

    public static function portalPath(string $portal, string $prefix = ''): string
    {
        $definition = self::portalDefinition(self::normalizePortal($portal));
        return $prefix . (string)$definition['path'];
    }

    public static function roleMatrix(): array
    {
        return [
            'admin' => [
                'label'=>'Administrator','description'=>'Vollzugriff einschließlich Benutzer, Rollen, SMTP, Integrationen, Sicherungen und Systemeinstellungen.',
                'capabilities'=>['*'],
            ],
            'manager' => [
                'label'=>'Manager','description'=>'Leitungsrolle mit Verwaltung, Webseite, Angeboten, Buchungen, Preisen, Housekeeping, Kommunikation, Berichten und Sicherungen. Keine Benutzer-, SMTP- oder Schnittstellenverwaltung.',
                'capabilities'=>[
                    'dashboard_view','reports_view','calendar_view','bookings_view','bookings_manage','offers_view','offers_manage','billing_view','billing_manage',
                    'guests_view','guests_manage','masterdata_view','masterdata_manage','prices_view','prices_manage','housekeeping_view','housekeeping_manage',
                    'communications_view','communications_manage','website_view','website_manage','settings_view','settings_manage','import_export_manage','system_view','backups_manage',
                    'dashboard','reports','masterdata','bookings','guests','prices','operations','housekeeping_manage','communications','settings','backups','csv'
                ],
            ],
            'reception' => [
                'label'=>'Rezeption','description'=>'Rezeption mit Gästen, Buchungen, Kalender, Angeboten, Zahlungen, Meldedaten und Freigabeablauf. Keine Stammdaten-, Preis-, Import- oder Systemverwaltung.',
                'capabilities'=>[
                    'dashboard_view','reports_view','calendar_view','bookings_view','bookings_manage','offers_view','offers_manage','billing_view','billing_manage',
                    'guests_view','guests_manage','police_manage','meals_manage','housekeeping_view','housekeeping_assign','housekeeping_inspect','housekeeping_ready','housekeeping_release','communications_view',
                    'dashboard','reports','bookings','guests','meals','police','housekeeping_assign','housekeeping_inspect','housekeeping_ready','housekeeping_release'
                ],
            ],
            'housekeeping_manager' => [
                'label'=>'Gouvernante / Housekeeping-Leitung','description'=>'Erhält Reinigungsaufträge, verteilt sie an Teams oder Mitarbeiter, kontrolliert und meldet Wohnungen bezugsbereit.',
                'capabilities'=>[
                    'housekeeping_view','housekeeping_manage','housekeeping_assign','housekeeping_inspect','housekeeping_ready','reports_view','settings_view','account',
                    'housekeeping_manager_portal','reports'
                ],
            ],
            'housekeeping' => [
                'label'=>'Housekeeping','description'=>'Nur die zugeordneten Reinigungsaufträge. Gastdaten werden nach Datenschutzvorgabe verschleiert.',
                'capabilities'=>['housekeeping_view','settings_view','account','housekeeping_portal'],
            ],
            'readonly' => [
                'label'=>'Nur Lesen','description'=>'Berichte, Kalender, Angebote, Buchungen, Abrechnung und Grunddaten ohne Änderungsrechte.',
                'capabilities'=>['dashboard_view','reports_view','calendar_view','bookings_view','offers_view','billing_view','masterdata_view','prices_view','housekeeping_view','settings_view','dashboard','reports','readonly'],
            ],
        ];
    }

    public static function capabilityCatalog(): array
    {
        return [
            'dashboard_view' => ['label'=>'Dashboard ansehen','category'=>'Allgemein','description'=>'Startseite, Hinweise und Tagesübersicht öffnen.'],
            'reports_view' => ['label'=>'Statistiken ansehen','category'=>'Allgemein','description'=>'Statistiken und Auswertungen lesen.'],
            'calendar_view' => ['label'=>'Belegungskalender ansehen','category'=>'Buchung','description'=>'Kalender, freie Zeiten und Belegung lesen.'],
            'bookings_view' => ['label'=>'Buchungen ansehen','category'=>'Buchung','description'=>'Buchungslisten und Details öffnen.'],
            'bookings_manage' => ['label'=>'Buchungen bearbeiten','category'=>'Buchung','description'=>'Buchungen anlegen, ändern, verschieben oder Status ändern.'],
            'offers_view' => ['label'=>'Angebote ansehen','category'=>'Buchung','description'=>'Angebote und angenommene Angebote lesen.'],
            'offers_manage' => ['label'=>'Angebote bearbeiten','category'=>'Buchung','description'=>'Angebote erstellen, senden, archivieren und in Buchungen umwandeln.'],
            'billing_view' => ['label'=>'Abrechnung ansehen','category'=>'Abrechnung','description'=>'Zahlungspläne, Rechnungen und Zahlungsstatus lesen.'],
            'billing_manage' => ['label'=>'Abrechnung bearbeiten','category'=>'Abrechnung','description'=>'Zahlungen erfassen, Zahlungsziele ändern, Rechnungs-/Zahlungsdokumente erzeugen und Kunden informieren.'],
            'guests_view' => ['label'=>'Gäste ansehen','category'=>'Gäste','description'=>'Gästedaten lesen.'],
            'guests_manage' => ['label'=>'Gäste bearbeiten','category'=>'Gäste','description'=>'Gäste, Mitreisende und Meldedaten pflegen.'],
            'police_manage' => ['label'=>'Meldeliste bearbeiten','category'=>'Gäste','description'=>'Polizeimeldungen/Meldelisten vorbereiten und Status ändern.'],
            'meals_manage' => ['label'=>'Frühstück/HP verwalten','category'=>'Gäste','description'=>'Verpflegungslisten einsehen und bearbeiten.'],
            'masterdata_view' => ['label'=>'Stammdaten ansehen','category'=>'Stammdaten','description'=>'Häuser, Wohnungstypen und Apartments lesen.'],
            'masterdata_manage' => ['label'=>'Stammdaten bearbeiten','category'=>'Stammdaten','description'=>'Häuser, Wohnungstypen, Apartments und Ausstattungen ändern.'],
            'prices_view' => ['label'=>'Preise ansehen','category'=>'Preise','description'=>'Preise, Saisonzeiten und Kanäle lesen.'],
            'prices_manage' => ['label'=>'Preise bearbeiten','category'=>'Preise','description'=>'Preise, Saisons, Sonderpreise und Rabatte ändern.'],
            'housekeeping_view' => ['label'=>'Housekeeping ansehen','category'=>'Housekeeping','description'=>'Putzplan, Aufgaben und Freigaben lesen.'],
            'housekeeping_manage' => ['label'=>'Housekeeping verwalten','category'=>'Housekeeping','description'=>'Aufträge, Teams, Mitarbeiter und Checklisten verwalten.'],
            'housekeeping_assign' => ['label'=>'Aufträge zuweisen','category'=>'Housekeeping','description'=>'Reinigungsaufträge Teams oder Mitarbeitern zuweisen.'],
            'housekeeping_inspect' => ['label'=>'Kontrolle durchführen','category'=>'Housekeeping','description'=>'Reinigung kontrollieren und Nacharbeit anfordern.'],
            'housekeeping_ready' => ['label'=>'Bezugsbereit melden','category'=>'Housekeeping','description'=>'Wohnung nach Kontrolle als bezugsbereit melden.'],
            'housekeeping_release' => ['label'=>'Wohnung final freigeben','category'=>'Housekeeping','description'=>'Wohnung endgültig für den Gast freigeben.'],
            'communications_view' => ['label'=>'Versandprotokoll ansehen','category'=>'Kommunikation','description'=>'E-Mail- und WhatsApp-Protokolle lesen.'],
            'communications_manage' => ['label'=>'Kommunikation senden','category'=>'Kommunikation','description'=>'E-Mails, Testmails, Statusmails und WhatsApp-Vorbereitungen auslösen.'],
            'website_view' => ['label'=>'Webseite ansehen','category'=>'Webseite','description'=>'Frontend-/Webbaukasten-Inhalte lesen.'],
            'website_manage' => ['label'=>'Webseite bearbeiten','category'=>'Webseite','description'=>'Seiten, Blöcke, Medien, Slider und Wohnungstypdarstellung bearbeiten.'],
            'settings_view' => ['label'=>'Einstellungen ansehen','category'=>'System','description'=>'Konto- und Systemeinstellungen lesen.'],
            'settings_manage' => ['label'=>'Einstellungen bearbeiten','category'=>'System','description'=>'Allgemeine Einstellungen speichern.'],
            'import_export_manage' => ['label'=>'Import/Export','category'=>'System','description'=>'CSV-Importe, Profile, Druck und Export vorbereiten.'],
            'system_view' => ['label'=>'Systemdiagnose ansehen','category'=>'System','description'=>'Diagnose, Audit und Statusberichte lesen.'],
            'backups_manage' => ['label'=>'Backups erstellen','category'=>'System','description'=>'Datensicherungen anstoßen und Backup-Listen sehen.'],
            'users_manage' => ['label'=>'Benutzer & Rollen verwalten','category'=>'System','description'=>'Benutzer, Rollen und individuelle Rechte verwalten.'],
        ];
    }

    public static function baseCapabilitiesForRole(string $role): array
    {
        $caps = self::roleMatrix()[$role]['capabilities'] ?? [];
        $catalog = self::capabilityCatalog();
        if (in_array('*', $caps, true)) return array_keys($catalog);
        $result = [];
        foreach ($caps as $cap) {
            if (isset($catalog[$cap])) $result[$cap] = true;
        }
        return array_keys($result);
    }

    public static function permissionOverrides(int $userId): array
    {
        if ($userId <= 0) return [];
        try {
            $stmt = db()->prepare('SELECT capability,allowed FROM user_permission_overrides WHERE user_id=?');
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[(string)$row['capability']] = (int)$row['allowed'] ? 'allow' : 'deny';
        }
        return $out;
    }

    public static function effectiveCapabilitiesFor(array $user): array
    {
        $role = (string)($user['role'] ?? 'readonly');
        $effective = array_fill_keys(self::baseCapabilitiesForRole($role), true);
        $catalog = self::capabilityCatalog();
        foreach (self::permissionOverrides((int)($user['id'] ?? 0)) as $capability => $mode) {
            if (!isset($catalog[$capability])) continue;
            if ($mode === 'allow') $effective[$capability] = true;
            if ($mode === 'deny') unset($effective[$capability]);
        }
        return array_keys($effective);
    }

    public static function canForUser(array $user, string $capability): bool
    {
        return in_array($capability, self::effectiveCapabilitiesFor($user), true);
    }

    public static function can(string $capability): bool
    {
        $user = self::user();
        return $user ? self::canForUser($user, $capability) : false;
    }

    public static function logout(): void
    {
        self::clearSsoCookie();
        self::$cachedUser = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }

    public static function issueSsoCookie(string $email): void
    {
        $secret = self::ssoSecret();
        if (!$secret) return;
        $payload = base64_encode(json_encode(['email' => $email, 'exp' => time() + 7 * 24 * 60 * 60], JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $payload, $secret);
        $token = $payload . '.' . $signature;
        setcookie('qs_sso', $token, [
            'expires' => time() + 7 * 24 * 60 * 60,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearSsoCookie(): void
    {
        setcookie('qs_sso', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function checkSsoCookie(): void
    {
        if (!empty($_SESSION['user_id'])) return;
        $secret = self::ssoSecret();
        if (!$secret || empty($_COOKIE['qs_sso'])) return;
        $raw = (string)$_COOKIE['qs_sso'];
        $parts = explode('.', $raw, 2);
        if (count($parts) !== 2) return;
        try {
            $payload = json_decode(base64_decode($parts[0], true), true, 512, JSON_THROW_ON_ERROR);
            $signature = hash_hmac('sha256', $parts[0], $secret);
            if (!hash_equals($signature, $parts[1])) return;
            if (($payload['exp'] ?? 0) < time()) return;
            $email = $payload['email'] ?? '';
            if (!$email) return;
            $stmt = db()->prepare('SELECT id,name,email,role,active FROM users WHERE LOWER(email)=? LIMIT 1');
            $stmt->execute([mb_strtolower($email)]);
            $user = $stmt->fetch();
            if (!$user || !(int)$user['active']) return;
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['login_at'] = time();
            $_SESSION['last_activity'] = time();
            self::$cachedUser = null;
        } catch (Throwable) {
        }
    }

    private static function ssoSecret(): string
    {
        return (string)(setting('sso_secret') ?? '');
    }
}
