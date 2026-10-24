<?php
declare(strict_types=1);
function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $driver = env('DB_DRIVER', 'sqlite');
    if ($driver === 'mysql') {
        $dsn = 'mysql:host=' . env('DB_HOST', '127.0.0.1') . ';port=' . env('DB_PORT', '3306') . ';dbname=' . env('DB_DATABASE', 'sdu_pr') . ';charset=utf8mb4';
        $pdo = new PDO($dsn, env('DB_USERNAME'), env('DB_PASSWORD'));
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    } elseif ($driver === 'sqlite') {
        $pdo = new PDO('sqlite:' . env('DB_SQLITE_PATH', ROOT . '/storage/app.sqlite'));
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    } else throw new RuntimeException('Unsupported DB_DRIVER');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}
function query(string $sql, array $params = []): PDOStatement { $s = db()->prepare($sql); $s->execute($params); return $s; }
function one(string $sql, array $params = []): ?array { return query($sql, $params)->fetch() ?: null; }
function all(string $sql, array $params = []): array { return query($sql, $params)->fetchAll(); }
function setting(string $key, string $default = ''): string { try { return (string)(one('SELECT value FROM settings WHERE name = ?', [$key])['value'] ?? $default); } catch (PDOException $ex) { return $default; } }
function set_setting(string $key, string $value): void {
    if (one('SELECT name FROM settings WHERE name = ?', [$key])) query('UPDATE settings SET value = ? WHERE name = ?', [$value, $key]);
    else query('INSERT INTO settings (name, value) VALUES (?, ?)', [$key, $value]);
}
function audit(string $action, string $entity, int $id, array $details = []): void {
    query('INSERT INTO audit_logs (user_id, action, entity, entity_id, details, created_at) VALUES (?, ?, ?, ?, ?, ?)', [current_user()['id'] ?? null, $action, $entity, $id, json_encode($details, JSON_UNESCAPED_UNICODE), now()]);
}
