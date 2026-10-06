<?php
declare(strict_types=1);

final class BackupManager
{
    public static function create(string $reason = 'manual'): array
    {
        $dir = root_path('storage/backups');
        self::ensureWritableDirectory($dir, 'Backup-Ordner');

        $safeReason = preg_replace('/[^a-z0-9_-]+/i', '-', $reason) ?: 'backup';
        $filename = 'staypilot-' . date('Ymd-His') . '-' . trim($safeReason, '-') . '.sql';
        $path = $dir . '/' . $filename;
        $tmp = $path . '.tmp';
        $pdo = db();
        $fh = fopen($tmp, 'wb');
        if (!$fh) throw new RuntimeException('Backup-Datei konnte nicht geöffnet werden.');

        try {
            fwrite($fh, "-- StayPilot database backup\n-- Created: " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $quotedTable = str_replace('`', '``', (string)$table);
                $row = $pdo->query("SHOW CREATE TABLE `{$quotedTable}`")->fetch(PDO::FETCH_NUM);
                if (!$row || empty($row[1])) continue;
                fwrite($fh, "DROP TABLE IF EXISTS `{$quotedTable}`;\n" . $row[1] . ";\n\n");
                $stmt = $pdo->query("SELECT * FROM `{$quotedTable}`");
                while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $columns = array_map(static fn($c) => '`' . str_replace('`', '``', (string)$c) . '`', array_keys($data));
                    $values = array_map(static function ($v) use ($pdo) {
                        if ($v === null) return 'NULL';
                        return $pdo->quote((string)$v);
                    }, array_values($data));
                    fwrite($fh, "INSERT INTO `{$quotedTable}` (" . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n");
                }
                fwrite($fh, "\n");
            }
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fh);
            if (!rename($tmp, $path)) throw new RuntimeException('Backup-Datei konnte nicht abgeschlossen werden.');
            @chmod($path, 0640);
            $size = filesize($path) ?: 0;
            self::record($filename, $reason, $size, hash_file('sha256', $path) ?: '');
            self::prune((int)setting('backup_retention_count', 12));
            return ['filename' => $filename, 'size' => $size, 'created_at' => date('Y-m-d H:i:s'), 'reason' => $reason];
        } catch (Throwable $e) {
            if (is_resource($fh)) fclose($fh);
            @unlink($tmp);
            throw $e;
        }
    }

    public static function list(): array
    {
        $dir = root_path('storage/backups');
        if (!is_dir($dir)) return [];
        $files = glob($dir . '/staypilot-*.sql') ?: [];
        rsort($files, SORT_STRING);
        return array_map(static fn($path) => [
            'filename' => basename($path),
            'size' => filesize($path) ?: 0,
            'created_at' => date('Y-m-d H:i:s', filemtime($path) ?: time()),
        ], $files);
    }


    public static function verifyLatest(): array
    {
        $backups = self::list();
        $latest = $backups[0] ?? null;
        if (!$latest || empty($latest['filename'])) {
            return ['ok' => false, 'filename' => null, 'issues' => ['Kein Backup vorhanden.']];
        }
        return self::verify((string)$latest['filename']);
    }

    public static function verify(string $filename): array
    {
        $path = self::path($filename);
        $size = filesize($path) ?: 0;
        $issues = [];
        if ($size < 1024) {
            $issues[] = 'Backup-Datei ist sehr klein.';
        }
        $fh = fopen($path, 'rb');
        $sample = '';
        $tail = '';
        if ($fh) {
            $sample = (string)fread($fh, 1024 * 1024);
            if ($size > 0) {
                fseek($fh, max(0, $size - (1024 * 256)));
                $tail = (string)fread($fh, 1024 * 256);
            }
            fclose($fh);
        }
        $fullProbe = $sample . "\n" . $tail;
        if ($sample === '' && $tail === '') {
            $issues[] = 'Backup-Datei ist nicht lesbar.';
        }
        foreach ([
            '-- StayPilot database backup' => 'StayPilot-Header fehlt.',
            'SET NAMES utf8mb4' => 'Zeichensatz-Anweisung fehlt.',
            'SET FOREIGN_KEY_CHECKS=0' => 'Start der Fremdschlüssel-Deaktivierung fehlt.',
            'CREATE TABLE' => 'CREATE-TABLE-Struktur fehlt.',
        ] as $needle => $message) {
            if (stripos($fullProbe, $needle) === false) {
                $issues[] = $message;
            }
        }
        if (stripos($tail, 'SET FOREIGN_KEY_CHECKS=1') === false) {
            $issues[] = 'Ende der Fremdschlüssel-Reaktivierung fehlt.';
        }
        $checksum = hash_file('sha256', $path) ?: '';
        $recordedChecksum = '';
        try {
            if (self::tableExists('system_backups')) {
                $stmt = db()->prepare('SELECT checksum_sha256 FROM system_backups WHERE filename=? ORDER BY id DESC LIMIT 1');
                $stmt->execute([basename($filename)]);
                $recordedChecksum = (string)($stmt->fetchColumn() ?: '');
                if ($recordedChecksum !== '' && !hash_equals($recordedChecksum, $checksum)) {
                    $issues[] = 'Prüfsumme weicht vom Backup-Protokoll ab.';
                }
            }
        } catch (Throwable) {}
        return [
            'ok' => empty($issues),
            'filename' => basename($filename),
            'size' => $size,
            'size_human' => self::formatBytes($size),
            'checksum_sha256' => $checksum,
            'recorded_checksum_sha256' => $recordedChecksum,
            'issues' => $issues,
            'checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    private static function formatBytes(int $bytes): string
    {
        $units = ['B','KB','MB','GB'];
        $value = (float)$bytes;
        $i = 0;
        while ($value >= 1024 && $i < count($units) - 1) { $value /= 1024; $i++; }
        return number_format($value, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
    }

    public static function path(string $filename): string
    {
        $base = basename($filename);
        if ($base !== $filename || !preg_match('/^staypilot-[a-z0-9_-]+\.sql$/i', $base)) throw new ValidationException('Ungültiger Backup-Dateiname.');
        $path = root_path('storage/backups/' . $base);
        if (!is_file($path)) throw new NotFoundException('Backup-Datei wurde nicht gefunden.');
        return $path;
    }

    private static function ensureWritableDirectory(string $dir, string $label): void
    {
        $parent = dirname($dir);
        if (!is_dir($parent)) {
            @mkdir($parent, 0775, true);
        }
        if (is_dir($parent)) {
            @chmod($parent, 0775);
        }
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException($label . ' konnte nicht angelegt werden: ' . $dir);
        }
        @chmod($dir, 0775);
        clearstatcache(true, $dir);
        if (!is_writable($dir)) {
            throw new RuntimeException($label . ' ist nicht beschreibbar: ' . $dir . ' – bitte per FTP/Hosting-Dateimanager Schreibrechte setzen, meistens 775 oder 755 je nach Server.');
        }
        $probe = $dir . '/.staypilot-write-test';
        if (@file_put_contents($probe, 'ok') === false) {
            throw new RuntimeException($label . ' ist vorhanden, aber StayPilot kann keine Datei darin schreiben: ' . $dir);
        }
        @unlink($probe);
    }

    private static function record(string $filename, string $reason, int $size, string $checksum): void
    {
        try {
            if (!self::tableExists('system_backups')) return;
            $user = Auth::user();
            db()->prepare('INSERT INTO system_backups(filename,reason,size_bytes,checksum_sha256,created_by) VALUES(?,?,?,?,?)')
                ->execute([$filename, $reason, $size, $checksum, $user['id'] ?? null]);
        } catch (Throwable $e) {
            AppLogger::error($e, ['filename' => $filename], 'backup');
        }
    }

    private static function prune(int $keep): void
    {
        if ($keep < 1) return;
        $files = self::list();
        foreach (array_slice($files, $keep) as $file) @unlink(root_path('storage/backups/' . $file['filename']));
    }

    private static function tableExists(string $table): bool
    {
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable) { return false; }
    }
}
