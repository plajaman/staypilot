<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connect(array $config): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        if (!class_exists(PDO::class)) {
            throw new RuntimeException('PDO ist auf diesem Server nicht verfügbar.');
        }
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        self::$pdo = new PDO($config['dsn'], $config['user'] ?? '', $config['password'] ?? '', $options);
        return self::$pdo;
    }
}
