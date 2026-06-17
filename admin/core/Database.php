<?php

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dbConfigPath = defined('APP_DATABASE_CONFIG') ? APP_DATABASE_CONFIG : __DIR__ . '/../config/database.php';
            $config = require $dbConfigPath;

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            self::$instance = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$instance;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int) self::getInstance()->lastInsertId();
    }

    private static array $columnCache = [];
    private static array $tableCache = [];

    public static function clearSchemaCache(): void
    {
        self::$columnCache = [];
        self::$tableCache = [];
    }

    public static function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;

        if (!array_key_exists($key, self::$columnCache)) {
            $result = self::fetch(
                'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            );
            self::$columnCache[$key] = ((int) ($result['c'] ?? 0)) > 0;
        }

        return self::$columnCache[$key];
    }

    public static function tableExists(string $table): bool
    {
        if (!array_key_exists($table, self::$tableCache)) {
            $result = self::fetch(
                'SELECT COUNT(*) AS c FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            );
            self::$tableCache[$table] = ((int) ($result['c'] ?? 0)) > 0;
        }

        return self::$tableCache[$table];
    }
}
