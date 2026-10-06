<?php
declare(strict_types=1);

final class AppLogger
{
    private static ?string $requestId = null;

    public static function requestId(): string
    {
        return self::$requestId ??= bin2hex(random_bytes(6));
    }

    public static function error(Throwable|string $error, array $context = [], string $source = 'php'): string
    {
        $requestId = self::requestId();
        $message = $error instanceof Throwable ? $error->getMessage() : $error;
        $entry = [
            'time' => date('c'),
            'request_id' => $requestId,
            'source' => $source,
            'level' => 'error',
            'message' => mb_substr($message, 0, 4000),
            'file' => $error instanceof Throwable ? $error->getFile() : null,
            'line' => $error instanceof Throwable ? $error->getLine() : null,
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'user_id' => $_SESSION['user_id'] ?? null,
            'context' => self::sanitize($context),
        ];
        self::writeFile($entry);
        self::writeDatabase($entry);
        return $requestId;
    }

    public static function info(string $message, array $context = [], string $source = 'system'): string
    {
        $entry = [
            'time' => date('c'),
            'request_id' => self::requestId(),
            'source' => $source,
            'level' => 'info',
            'message' => mb_substr($message, 0, 4000),
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'user_id' => $_SESSION['user_id'] ?? null,
            'context' => self::sanitize($context),
        ];
        self::writeFile($entry);
        return $entry['request_id'];
    }

    private static function writeFile(array $entry): void
    {
        try {
            $dir = root_path('storage/logs');
            if (!is_dir($dir)) @mkdir($dir, 0770, true);
            @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
        }
    }

    private static function writeDatabase(array $entry): void
    {
        try {
            $pdo = db();
            $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='app_errors'")->fetchColumn();
            if (!$exists) return;
            $stmt = $pdo->prepare('INSERT INTO app_errors(request_id,source,level,message,file_name,line_number,url,http_method,user_id,context_json) VALUES(?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $entry['request_id'], $entry['source'], $entry['level'], $entry['message'], $entry['file'] ?? null,
                $entry['line'] ?? null, $entry['url'] ?? null, $entry['method'] ?? null, $entry['user_id'] ?? null,
                json_encode($entry['context'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable) {
        }
    }

    private static function sanitize(array $context): array
    {
        $masked = [];
        foreach ($context as $key => $value) {
            $name = mb_strtolower((string)$key);
            if (preg_match('/password|secret|token|authorization|cookie|key|wifi_password/', $name)) {
                $masked[$key] = '[geschützt]';
            } elseif (is_array($value)) {
                $masked[$key] = self::sanitize($value);
            } elseif (is_scalar($value) || $value === null) {
                $masked[$key] = mb_substr((string)$value, 0, 2000);
            }
        }
        return $masked;
    }
}
