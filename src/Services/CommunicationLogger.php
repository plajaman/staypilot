<?php
declare(strict_types=1);

final class CommunicationLogger
{
    public static function record(
        string $channel,
        string $entityType,
        int|string|null $entityId,
        string $recipientName,
        string $recipientAddress,
        string $subject,
        string $message,
        string $status,
        string $detail = ''
    ): int {
        $user = Auth::user();
        $excerpt = mb_substr(trim(preg_replace('/\s+/u', ' ', $message) ?? $message), 0, 1500);
        $params = [
            mb_substr($channel,0,30), mb_substr($entityType,0,60), $entityId === null ? null : (string)$entityId,
            mb_substr($recipientName,0,160), mb_substr($recipientAddress,0,255), mb_substr($subject,0,255),
            $excerpt, hash('sha256',$message), mb_substr($status,0,40), mb_substr($detail,0,1000), $user['id'] ?? null,
        ];
        if (self::supportsMessageBody()) {
            $stmt = db()->prepare('INSERT INTO communication_log(channel,entity_type,entity_id,recipient_name,recipient_address,subject,message_excerpt,message_body,message_sha256,status,detail,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
            $stmt->execute([
                mb_substr($channel,0,30), mb_substr($entityType,0,60), $entityId === null ? null : (string)$entityId,
                mb_substr($recipientName,0,160), mb_substr($recipientAddress,0,255), mb_substr($subject,0,255),
                $excerpt, $message, hash('sha256',$message), mb_substr($status,0,40), mb_substr($detail,0,1000), $user['id'] ?? null,
            ]);
        } else {
            $stmt = db()->prepare('INSERT INTO communication_log(channel,entity_type,entity_id,recipient_name,recipient_address,subject,message_excerpt,message_sha256,status,detail,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,NOW())');
            $stmt->execute($params);
        }
        return (int)db()->lastInsertId();
    }

    private static function supportsMessageBody(): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try {
            db()->query('SELECT message_body FROM communication_log LIMIT 0');
            return $ok = true;
        } catch (Throwable $e) {
            try {
                db()->exec('ALTER TABLE communication_log ADD COLUMN message_body LONGTEXT NULL AFTER message_excerpt');
                return $ok = true;
            } catch (Throwable $ignored) {
                return $ok = false;
            }
        }
    }
}
