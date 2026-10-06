<?php
declare(strict_types=1);

/**
 * V2.3.6.125 – IMAP-Grundlage für ein echtes Kommunikationscenter.
 * Speichert Zugangsdaten verschlüsselt und bietet sichere Verbindungs-/Abrufprüfungen.
 */
final class ImapMailbox
{
    private const SETTING_KEY = 'mail_imap_settings_v236125';

    public static function settings(bool $withSecret = false): array
    {
        $stored = setting(self::SETTING_KEY, []);
        if (!is_array($stored)) $stored = [];
        $defaults = [
            'active' => 0,
            'host' => '',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => '',
            'email_address' => '',
            'folder' => 'INBOX',
            'timeout_seconds' => 15,
            'password_encrypted' => '',
            'updated_at' => null,
        ];
        $out = array_merge($defaults, $stored);
        $out['has_password'] = !empty($out['password_encrypted']);
        if ($withSecret) $out['password'] = Crypto::decrypt((string)($out['password_encrypted'] ?? ''));
        unset($out['password_encrypted']);
        return $out;
    }

    public static function save(array $data): array
    {
        $old = self::settings(false);
        $existing = setting(self::SETTING_KEY, []);
        if (!is_array($existing)) $existing = [];
        $password = (string)($data['imap_password'] ?? $data['password'] ?? '');
        $encrypted = $password !== '' ? Crypto::encrypt($password) : (string)($existing['password_encrypted'] ?? '');
        $email = trim((string)($data['imap_email_address'] ?? $data['email_address'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ValidationException('Die IMAP-E-Mail-Adresse ist ungültig.');
        $host = trim((string)($data['imap_host'] ?? $data['host'] ?? ''));
        $enc = (string)($data['imap_encryption'] ?? $data['encryption'] ?? 'ssl');
        if (!in_array($enc, ['ssl','tls','none'], true)) $enc = 'ssl';
        $settings = [
            'active' => normalize_bool($data['imap_active'] ?? $data['active'] ?? 0),
            'host' => mb_substr($host, 0, 190),
            'port' => max(1, min(65535, (int)($data['imap_port'] ?? $data['port'] ?? 993))),
            'encryption' => $enc,
            'username' => mb_substr(trim((string)($data['imap_username'] ?? $data['username'] ?? '')), 0, 190),
            'email_address' => mb_substr($email, 0, 190),
            'folder' => self::safeFolder((string)($data['imap_folder'] ?? $data['folder'] ?? 'INBOX')),
            'timeout_seconds' => max(5, min(60, (int)($data['imap_timeout_seconds'] ?? $data['timeout_seconds'] ?? 15))),
            'password_encrypted' => $encrypted,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ((int)$settings['active'] && ($settings['host'] === '' || $settings['username'] === '')) {
            throw new ValidationException('Für aktives IMAP sind Server und Benutzername erforderlich.');
        }
        save_setting(self::SETTING_KEY, $settings);
        AuditLogger::record('imap_settings', 1, 'update', $old, self::settings(false), 'IMAP-Einstellungen gespeichert; Passwortinhalt geschützt');
        return self::settings(false);
    }

    public static function test(array $override = []): array
    {
        $cfg = array_merge(self::settings(true), $override);
        $client = new ImapSocketClient($cfg);
        try {
            $client->connect();
            $mailbox = $client->select((string)($cfg['folder'] ?? 'INBOX'));
            $client->logout();
            return ['ok' => true, 'message' => 'IMAP-Verbindung, Anmeldung und Postfachauswahl waren erfolgreich.', 'mailbox' => $mailbox, 'transcript' => $client->safeTranscript()];
        } catch (Throwable $e) {
            $client->close();
            throw new RuntimeException('IMAP-Test fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public static function fetchInbox(int $limit = 20): array
    {
        $cfg = self::settings(true);
        if (!(int)($cfg['active'] ?? 0)) throw new RuntimeException('IMAP ist nicht aktiviert.');
        $client = new ImapSocketClient($cfg);
        try {
            $client->connect();
            $mailbox = $client->select((string)($cfg['folder'] ?? 'INBOX'));
            $messages = $client->fetchHeaders(max(1, min(50, $limit)));
            $client->logout();
            AuditLogger::record('imap_inbox', 1, 'fetch', null, ['count' => count($messages), 'folder' => (string)($cfg['folder'] ?? 'INBOX')], 'Posteingang per IMAP abgerufen');
            return ['ok'=>true, 'mailbox'=>$mailbox, 'messages'=>$messages];
        } catch (Throwable $e) {
            $client->close();
            throw new RuntimeException('Posteingang konnte nicht abgerufen werden: ' . $e->getMessage());
        }
    }

    public static function fetchMessage(int $seq): array
    {
        $cfg = self::settings(true);
        if (!(int)($cfg['active'] ?? 0)) throw new RuntimeException('IMAP ist nicht aktiviert.');
        $seq = max(1, $seq);
        $client = new ImapSocketClient($cfg);
        try {
            $client->connect();
            $client->select((string)($cfg['folder'] ?? 'INBOX'));
            $message = $client->fetchMessage($seq);
            $client->logout();
            AuditLogger::record('imap_message', $seq, 'read', null, ['seq'=>$seq, 'subject'=>$message['subject'] ?? ''], 'IMAP-Nachricht gelesen (BODY.PEEK, Serverstatus unverändert)');
            return ['ok'=>true, 'message'=>$message];
        } catch (Throwable $e) {
            $client->close();
            throw new RuntimeException('E-Mail konnte nicht gelesen werden: ' . $e->getMessage());
        }
    }



    public static function listFolders(): array
    {
        $cfg = self::settings(true);
        if (!(int)($cfg['active'] ?? 0)) throw new RuntimeException('IMAP ist nicht aktiviert.');
        $client = new ImapSocketClient($cfg);
        try {
            $client->connect();
            $folders = $client->listFolders();
            $client->logout();
            return ['ok' => true, 'folders' => $folders];
        } catch (Throwable $e) {
            $client->close();
            throw new RuntimeException('IMAP-Ordner konnten nicht gelesen werden: ' . $e->getMessage());
        }
    }

    public static function messageAction(int $seq, string $action, string $targetFolder = ''): array
    {
        $cfg = self::settings(true);
        if (!(int)($cfg['active'] ?? 0)) throw new RuntimeException('IMAP ist nicht aktiviert.');
        $seq = max(1, $seq);
        $action = strtolower(trim($action));
        $client = new ImapSocketClient($cfg);
        try {
            $client->connect();
            $client->select((string)($cfg['folder'] ?? 'INBOX'));
            $message = $client->fetchMessage($seq);
            if ($action === 'seen') {
                $client->markSeen($seq);
                $label = 'als gelesen markiert';
            } elseif ($action === 'move') {
                $folder = self::safeFolder($targetFolder);
                if ($folder === '' || strcasecmp($folder, (string)($cfg['folder'] ?? 'INBOX')) === 0) throw new ValidationException('Zielordner fehlt oder entspricht dem aktuellen Ordner.');
                $client->moveToFolder($seq, $folder);
                $label = 'verschoben nach ' . $folder;
            } elseif ($action === 'archive') {
                $folder = self::preferredFolder($client->listFolders(), ['Archiv','Archive','Archives','INBOX.Archive','INBOX/Archive']);
                $client->moveToFolder($seq, $folder ?: 'Archive');
                $label = 'archiviert';
            } elseif ($action === 'trash') {
                $folder = self::preferredFolder($client->listFolders(), ['Papierkorb','Trash','Deleted Items','Deleted','INBOX.Trash','INBOX/Papierkorb']);
                if ($folder !== '') {
                    $client->moveToFolder($seq, $folder);
                    $label = 'in Papierkorb verschoben';
                } else {
                    $client->deleteMessage($seq);
                    $label = 'endgültig gelöscht, weil kein Papierkorb-Ordner gefunden wurde';
                }
            } else {
                throw new ValidationException('Unbekannte IMAP-Aktion.');
            }
            $client->logout();
            AuditLogger::record('imap_message', $seq, $action, null, ['subject'=>$message['subject'] ?? '', 'target'=>$targetFolder], 'IMAP-Nachricht ' . $label);
            return ['ok'=>true, 'message'=>'E-Mail wurde ' . $label . '.', 'action'=>$action, 'seq'=>$seq];
        } catch (Throwable $e) {
            $client->close();
            throw new RuntimeException('IMAP-Aktion fehlgeschlagen: ' . $e->getMessage());
        }
    }

    private static function preferredFolder(array $folders, array $candidates): string
    {
        $names = [];
        foreach ($folders as $folder) {
            $name = is_array($folder) ? (string)($folder['name'] ?? '') : (string)$folder;
            if ($name !== '') $names[] = $name;
        }
        foreach ($candidates as $candidate) {
            foreach ($names as $name) {
                if (strcasecmp($name, $candidate) === 0) return $name;
            }
        }
        foreach ($candidates as $candidate) {
            foreach ($names as $name) {
                if (stripos($name, $candidate) !== false) return $name;
            }
        }
        return '';
    }

    private static function safeFolder(string $folder): string
    {
        $folder = trim($folder) ?: 'INBOX';
        return preg_match('/^[A-Za-z0-9._\-\/ ]{1,120}$/', $folder) ? $folder : 'INBOX';
    }
}

final class ImapSocketClient
{
    private array $cfg;
    /** @var resource|null */
    private $socket = null;
    private int $tag = 1;
    private array $transcript = [];

    public function __construct(array $cfg) { $this->cfg = $cfg; }

    public function connect(): void
    {
        $host = trim((string)($this->cfg['host'] ?? ''));
        $port = (int)($this->cfg['port'] ?? 993);
        if ($host === '') throw new RuntimeException('IMAP-Server fehlt.');
        $scheme = (($this->cfg['encryption'] ?? 'ssl') === 'ssl') ? 'ssl://' : '';
        $timeout = max(5, min(60, (int)($this->cfg['timeout_seconds'] ?? 15)));
        $errno = 0; $errstr = '';
        $this->socket = @stream_socket_client($scheme . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!$this->socket) throw new RuntimeException("Verbindung zu {$host}:{$port} fehlgeschlagen ({$errno}: {$errstr}).");
        stream_set_timeout($this->socket, $timeout);
        $greeting = $this->readLine();
        if (!str_contains($greeting, 'OK')) throw new RuntimeException('IMAP-Server meldet keine OK-Begrüßung.');
        if (($this->cfg['encryption'] ?? 'ssl') === 'tls') {
            $this->command('STARTTLS');
            if (!@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('STARTTLS konnte nicht aktiviert werden.');
        }
        $this->login();
    }

    public function select(string $folder): array
    {
        $lines = $this->command('SELECT ' . $this->quote($folder));
        $exists = 0; $recent = 0; $unseen = null;
        foreach ($lines as $line) {
            if (preg_match('/\*\s+(\d+)\s+EXISTS/i', $line, $m)) $exists = (int)$m[1];
            if (preg_match('/\*\s+(\d+)\s+RECENT/i', $line, $m)) $recent = (int)$m[1];
            if (preg_match('/UNSEEN\s+(\d+)/i', $line, $m)) $unseen = (int)$m[1];
        }
        return ['folder'=>$folder, 'exists'=>$exists, 'recent'=>$recent, 'unseen'=>$unseen];
    }

    public function fetchHeaders(int $limit): array
    {
        $status = $this->select((string)($this->cfg['folder'] ?? 'INBOX'));
        $exists = (int)($status['exists'] ?? 0);
        if ($exists <= 0) return [];
        $from = max(1, $exists - $limit + 1);
        $range = $from . ':' . $exists;
        $lines = $this->command('FETCH ' . $range . ' (FLAGS BODY.PEEK[HEADER.FIELDS (DATE FROM TO SUBJECT MESSAGE-ID)])');
        $messages = [];
        $current = null;
        $headers = '';
        foreach ($lines as $line) {
            if (preg_match('/^\*\s+(\d+)\s+FETCH/i', $line, $m)) {
                if ($current !== null) $messages[] = $this->parseHeader($current, $headers);
                $current = (int)$m[1]; $headers = '';
                continue;
            }
            if ($current !== null) $headers .= $line . "\n";
        }
        if ($current !== null) $messages[] = $this->parseHeader($current, $headers);
        usort($messages, static fn($a, $b) => ($b['seq'] ?? 0) <=> ($a['seq'] ?? 0));
        return array_slice($messages, 0, $limit);
    }

    public function fetchMessage(int $seq): array
    {
        $seq = max(1, $seq);
        // V2.3.6.127: Vollstaendige RFC822-Nachricht literal-sicher lesen.
        // Der bisherige BODY[TEXT]-Abruf konnte bei Literal-Antworten nur den IMAP-Platzhalter
        // BODY[TEXT] {1234} anzeigen. BODY.PEEK[] liefert die komplette Mail, ohne sie als gelesen zu markieren.
        $response = $this->commandWithLiterals('FETCH ' . $seq . ' (FLAGS BODY.PEEK[])');
        $messageRaw = self::largestLiteral($response['literals'] ?? []);
        if ($messageRaw === '') {
            $messageRaw = (string)($response['raw'] ?? '');
            $messageRaw = preg_replace('/^\*\s+\d+\s+FETCH.*$/mi', '', $messageRaw) ?? $messageRaw;
            $messageRaw = preg_replace('/^A\d+\s+OK.*$/mi', '', $messageRaw) ?? $messageRaw;
        }
        [$header, $body] = self::splitHeaderBody($messageRaw);
        $parsed = $this->parseHeader($seq, $header);
        $text = self::extractBestText($header, $body);
        $text = self::cleanDisplayText($text);
        return array_merge($parsed, [
            'seq' => $seq,
            'body_text' => trim($text),
            'raw_excerpt' => mb_substr(trim($messageRaw), 0, 12000),
            'is_bounce' => self::looksLikeBounce(($parsed['subject'] ?? '') . "\n" . ($parsed['from'] ?? '') . "\n" . $text),
        ]);
    }


    private function commandWithLiterals(string $command, bool $secret = false): array
    {
        if (!$this->socket) throw new RuntimeException('Keine aktive IMAP-Verbindung.');
        $tag = 'A' . str_pad((string)$this->tag++, 4, '0', STR_PAD_LEFT);
        $this->transcript[] = 'C: ' . $tag . ' ' . ($secret ? '[geschützt]' : $command);
        fwrite($this->socket, $tag . ' ' . $command . "\r\n");
        $raw = '';
        $literals = [];
        while (($line = fgets($this->socket, 8192)) !== false) {
            $raw .= $line;
            $trimmed = rtrim($line, "\r\n");
            $this->transcript[] = 'S: ' . $trimmed;
            if (preg_match('/\{(\d+)\}\s*$/', $trimmed, $m)) {
                $need = (int)$m[1];
                $literal = '';
                while ($need > 0 && !feof($this->socket)) {
                    $chunk = fread($this->socket, min(8192, $need));
                    if ($chunk === false || $chunk === '') break;
                    $literal .= $chunk;
                    $need -= strlen($chunk);
                }
                $raw .= $literal;
                $literals[] = $literal;
                $this->transcript[] = 'S: [literal ' . strlen($literal) . ' bytes]';
                continue;
            }
            if (str_starts_with($trimmed, $tag . ' ')) {
                if (!preg_match('/^' . preg_quote($tag, '/') . '\s+OK\b/i', $trimmed)) throw new RuntimeException('IMAP-Befehl fehlgeschlagen: ' . $trimmed);
                return ['raw' => $raw, 'literals' => $literals];
            }
        }
        throw new RuntimeException('IMAP-Verbindung wurde unerwartet beendet.');
    }

    private static function largestLiteral(array $literals): string
    {
        $best = '';
        foreach ($literals as $literal) {
            if (is_string($literal) && strlen($literal) > strlen($best)) $best = $literal;
        }
        return $best;
    }

    private static function splitHeaderBody(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $raw = str_replace("\r", "\n", $raw);
        $parts = explode("\n\n", $raw, 2);
        return [trim((string)($parts[0] ?? '')), (string)($parts[1] ?? '')];
    }

    private static function headerValue(string $rawHeader, string $name): string
    {
        $folded = preg_replace('/\n[ \t]+/', ' ', str_replace("\r\n", "\n", $rawHeader)) ?? $rawHeader;
        if (preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/mi', $folded, $m)) return trim($m[1]);
        return '';
    }

    private static function extractBestText(string $header, string $body): string
    {
        $contentType = self::headerValue($header, 'Content-Type') ?: 'text/plain';
        $encoding = strtolower(self::headerValue($header, 'Content-Transfer-Encoding'));
        $charset = self::mimeParam($contentType, 'charset') ?: 'UTF-8';
        $boundary = self::mimeParam($contentType, 'boundary');
        if ($boundary !== '' && stripos($contentType, 'multipart/') !== false) {
            $plain = [];
            $html = [];
            foreach (self::multipartChunks($body, $boundary) as $chunk) {
                [$ph, $pb] = self::splitHeaderBody($chunk);
                $pt = self::headerValue($ph, 'Content-Type') ?: 'text/plain';
                if (stripos($pt, 'multipart/') !== false) {
                    $nested = self::extractBestText($ph, $pb);
                    if (trim($nested) !== '') $plain[] = $nested;
                    continue;
                }
                $decoded = self::decodeBody($pb, strtolower(self::headerValue($ph, 'Content-Transfer-Encoding')), self::mimeParam($pt, 'charset') ?: $charset);
                if (stripos($pt, 'text/plain') !== false) $plain[] = $decoded;
                elseif (stripos($pt, 'text/html') !== false) $html[] = self::htmlToReadableText($decoded);
            }
            $bestPlain = trim(implode("\n\n", array_filter(array_map('trim', $plain))));
            if ($bestPlain !== '') return $bestPlain;
            $bestHtml = trim(implode("\n\n", array_filter(array_map('trim', $html))));
            if ($bestHtml !== '') return $bestHtml;
            // Multipart korrekt erkannt und zerlegt, aber jeder Teil ergab nach dem
            // Dekodieren leeren Text (z. B. eine Nachricht ohne sichtbaren Inhalt).
            // Auf den rohen Gesamtbody zurueckzufallen wuerde nur unverstaendliche
            // MIME-Kopfzeilen/Grenzen anzeigen - stattdessen ein klarer Hinweis.
            if ($plain || $html) return '(Diese Nachricht enthält keinen sichtbaren Text.)';
        }
        $decoded = self::decodeBody($body, $encoding, $charset);
        if (stripos($contentType, 'text/html') !== false) return self::htmlToReadableText($decoded);
        return $decoded;
    }

    private static function multipartChunks(string $body, string $boundary): array
    {
        $body = str_replace("\r\n", "\n", $body);
        // Die schliessende Grenze (--boundary--) hat nicht immer einen Zeilenumbruch
        // danach, wenn sie das Ende der Nachricht ist (z. B. Outlook/Exchange laesst
        // ihn manchmal weg). Ohne "(?:\n|$)" bleibt die schliessende Grenze dann am
        // letzten Teil haengen und "verschmutzt" dessen extrahierten Text.
        $pattern = '/\n?--' . preg_quote($boundary, '/') . '(?:--)?\s*(?:\n|$)/';
        $chunks = preg_split($pattern, "\n" . $body) ?: [];
        $out = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '' || $chunk === '--') continue;
            if (str_starts_with($chunk, '--')) continue;
            $out[] = $chunk;
        }
        return $out;
    }

    private static function mimeParam(string $headerValue, string $param): string
    {
        if (preg_match('/(?:^|;)\s*' . preg_quote($param, '/') . '\s*=\s*("([^"]*)"|([^;\s]+))/i', $headerValue, $m)) {
            return trim((string)($m[2] ?? $m[3] ?? ''), " \t\"'");
        }
        return '';
    }

    private static function decodeBody(string $body, string $encoding, string $charset): string
    {
        $body = str_replace("\r\n", "\n", $body);
        if ($encoding === 'base64') {
            $decoded = base64_decode(preg_replace('/\s+/', '', $body) ?? $body, false);
            if (is_string($decoded)) $body = $decoded;
        } elseif ($encoding === 'quoted-printable') {
            $body = quoted_printable_decode($body);
        }
        $charset = trim($charset, " \t\"'") ?: 'UTF-8';
        if (strcasecmp($charset, 'UTF-8') !== 0 && function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($body, 'UTF-8', $charset);
            if (is_string($converted) && $converted !== '') $body = $converted;
        }
        return $body;
    }

    private static function htmlToReadableText(string $html): string
    {
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|div|tr|li|h[1-6])\s*>/i', "\n", $html) ?? $html;
        $text = strip_tags($html);
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function cleanDisplayText(string $text): string
    {
        $text = preg_replace('/BODY\[(?:TEXT|HEADER|[^\]]*)\]\s*\{\d+\}/i', '', $text) ?? $text;
        $text = preg_replace('/^\*\s+\d+\s+FETCH.*$/mi', '', $text) ?? $text;
        $text = preg_replace('/^A\d+\s+OK.*$/mi', '', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }

    private static function looksLikeBounce(string $text): bool
    {
        return (bool)preg_match('/(Undelivered Mail Returned|Mail Delivery System|MAILER-DAEMON|Delivery Status Notification|failure notice|returned to sender)/i', $text);
    }



    public function listFolders(): array
    {
        $lines = $this->command('LIST "" "*"');
        $folders = [];
        foreach ($lines as $line) {
            if (preg_match('/^\*\s+LIST\s+\(([^)]*)\)\s+"?([^"\s]*)"?\s+(.+)$/i', $line, $m)) {
                $name = trim((string)$m[3]);
                $name = trim($name, "\" ");
                if ($name === '') continue;
                $folders[] = ['name' => self::decodeMime($name), 'delimiter' => (string)$m[2], 'flags' => trim((string)$m[1])];
            }
        }
        $seen = [];
        $out = [];
        foreach ($folders as $f) {
            $k = strtolower($f['name']);
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $out[] = $f;
        }
        return $out;
    }

    public function markSeen(int $seq): void
    {
        $this->command('STORE ' . max(1, $seq) . ' +FLAGS.SILENT (\\Seen)');
    }

    public function moveToFolder(int $seq, string $folder): void
    {
        $seq = max(1, $seq);
        $this->command('COPY ' . $seq . ' ' . $this->quote($folder));
        $this->command('STORE ' . $seq . ' +FLAGS.SILENT (\\Deleted)');
        $this->command('EXPUNGE');
    }

    public function deleteMessage(int $seq): void
    {
        $seq = max(1, $seq);
        $this->command('STORE ' . $seq . ' +FLAGS.SILENT (\\Deleted)');
        $this->command('EXPUNGE');
    }

    public function logout(): void
    {
        if ($this->socket) { try { $this->command('LOGOUT', false); } catch (Throwable) {} $this->close(); }
    }
    public function close(): void { if (is_resource($this->socket)) fclose($this->socket); $this->socket = null; }
    public function safeTranscript(): array { return array_slice($this->transcript, -30); }

    private function login(): void
    {
        $user = (string)($this->cfg['username'] ?? '');
        $pass = (string)($this->cfg['password'] ?? '');
        if ($user === '') throw new RuntimeException('IMAP-Benutzer fehlt.');
        $this->command('LOGIN ' . $this->quote($user) . ' ' . $this->quote($pass), true);
    }

    private function command(string $command, bool $secret = false): array
    {
        if (!$this->socket) throw new RuntimeException('Keine aktive IMAP-Verbindung.');
        $tag = 'A' . str_pad((string)$this->tag++, 4, '0', STR_PAD_LEFT);
        $this->transcript[] = 'C: ' . $tag . ' ' . ($secret ? '[geschützt]' : $command);
        fwrite($this->socket, $tag . ' ' . $command . "\r\n");
        $lines = [];
        while (($line = fgets($this->socket, 8192)) !== false) {
            $line = rtrim($line, "\r\n");
            $this->transcript[] = 'S: ' . $line;
            $lines[] = $line;
            if (str_starts_with($line, $tag . ' ')) {
                if (!preg_match('/^' . preg_quote($tag, '/') . '\s+OK\b/i', $line)) throw new RuntimeException('IMAP-Befehl fehlgeschlagen: ' . $line);
                return $lines;
            }
        }
        throw new RuntimeException('IMAP-Verbindung wurde unerwartet beendet.');
    }

    private function readLine(): string
    {
        if (!$this->socket) throw new RuntimeException('Keine aktive IMAP-Verbindung.');
        $line = fgets($this->socket, 8192);
        if ($line === false) throw new RuntimeException('Keine Antwort vom IMAP-Server.');
        $line = rtrim($line, "\r\n");
        $this->transcript[] = 'S: ' . $line;
        return $line;
    }

    private function quote(string $value): string
    {
        return '"' . addcslashes($value, "\\\"\r\n") . '"';
    }

    private function parseHeader(int $seq, string $raw): array
    {
        $raw = preg_replace('/\r?\n[ \t]+/', ' ', $raw) ?? $raw;
        $get = static function(string $name) use ($raw): string {
            if (preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/mi', $raw, $m)) return trim($m[1]);
            return '';
        };
        return [
            'seq' => $seq,
            'date' => self::decodeMime($get('Date')),
            'from' => self::decodeMime($get('From')),
            'to' => self::decodeMime($get('To')),
            'subject' => self::decodeMime($get('Subject')),
            'message_id' => $get('Message-ID'),
        ];
    }

    private static function decodeMime(string $value): string
    {
        if ($value === '') return '';
        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if (is_string($decoded) && $decoded !== '') return $decoded;
        }
        return $value;
    }
}
