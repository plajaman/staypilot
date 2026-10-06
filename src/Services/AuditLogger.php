<?php
declare(strict_types=1);

final class AuditLogger
{
    public static function record(string $entityType, int|string|null $entityId, string $action, ?array $oldValues = null, ?array $newValues = null, string $note = ''): void
    {
        try {
            $user = Auth::user();
            $stmt = db()->prepare('INSERT INTO audit_log(entity_type,entity_id,action,old_values_json,new_values_json,note,user_id,user_name,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                mb_substr($entityType, 0, 80), $entityId === null ? null : (string)$entityId, mb_substr($action, 0, 60),
                self::json($oldValues), self::json($newValues), mb_substr($note, 0, 500),
                $user['id'] ?? null, $user['name'] ?? null, mb_substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 64),
                mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
        } catch (Throwable $e) {
            AppLogger::error($e, ['entity_type' => $entityType, 'entity_id' => $entityId], 'audit');
        }
    }

    private static function json(?array $values): ?string
    {
        if ($values === null) return null;
        foreach ($values as $key => &$value) {
            if (preg_match('/password|secret|token|wifi_password|password_hash/i', (string)$key)) $value = '[geschützt]';
        }
        return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
