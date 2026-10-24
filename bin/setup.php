<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$sql = file_get_contents(ROOT . '/database/schema.mysql.sql');
if (env('DB_DRIVER', 'sqlite') === 'sqlite') {
    $sql = preg_replace('/BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY/', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = str_replace(['BIGINT UNSIGNED', 'LONGTEXT', 'TINYINT'], ['INTEGER', 'TEXT', 'INTEGER'], $sql);
    $sql = preg_replace('/\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;/', ');', $sql);
    $sql = preg_replace('/,\s*INDEX \w+ \([^)]*\)/', '', $sql);
}
db()->exec($sql);
if (!one('SELECT id FROM users LIMIT 1')) {
    if (env('APP_ENV', 'local') === 'production' && !env('ADMIN_PASSWORD')) throw new RuntimeException('Set ADMIN_PASSWORD before production setup');
    $password = env('ADMIN_PASSWORD') ?: bin2hex(random_bytes(6));
    validate_new_password($password);
    $email = env('ADMIN_EMAIL', 'admin@example.test');
    query('INSERT INTO users (name, email, password_hash, role, active, created_at) VALUES (?, ?, ?, ?, 1, ?)', ['ผู้ดูแลระบบ', $email, password_hash($password, PASSWORD_DEFAULT), 'admin', now()]);
    if (env('APP_ENV', 'local') === 'local') {
        file_put_contents(ROOT . '/storage/setup-credentials.txt', "Local development credentials\nEmail: $email\nPassword: $password\n");
        chmod(ROOT . '/storage/setup-credentials.txt', 0600);
        echo "Local login saved in storage/setup-credentials.txt\n";
    }
}
foreach (['site_name'=>'กองประชาสัมพันธ์', 'university'=>'มหาวิทยาลัยสวนดุสิต', 'address'=>'', 'phone'=>'', 'email'=>'', 'hours'=>'', 'map_url'=>'', 'facebook_url'=>'', 'youtube_url'=>'', 'line_url'=>'', 'hero_title'=>'เชื่อมทุกเรื่องราว', 'hero_accent'=>'สู่รั้วสวนดุสิต', 'hero_description'=>'ข่าวสาร กิจกรรม และเรื่องราวจากมหาวิทยาลัยสวนดุสิต', 'demo_mode'=>'1'] as $key => $value) {
    if (!one('SELECT name FROM settings WHERE name = ?', [$key])) set_setting($key, $value);
}
foreach (['ข่าวมหาวิทยาลัย', 'กิจกรรม', 'ประกาศ', 'ประชาสัมพันธ์'] as $name) if (!one('SELECT id FROM categories WHERE name = ?', [$name])) query('INSERT INTO categories (name) VALUES (?)', [$name]);
echo 'Schema ready (' . env('DB_DRIVER', 'sqlite') . "). Run php bin/seed.php for optional example content.\n";
