<?php
// Import only source-backed public personnel. Existing records and photos are preserved.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$profile = json_decode(file_get_contents(ROOT . '/database/imports/pr-profile-2026.json'), true, 512, JSON_THROW_ON_ERROR);
db()->beginTransaction();
try {
    set_setting('personnel_profile', json_encode($profile, JSON_UNESCAPED_UNICODE));
    $added = 0;
    foreach ($profile['people'] as $order => $name) {
        if (one('SELECT id FROM personnel WHERE name=?', [$name])) continue;
        query('INSERT INTO personnel (name, position, department, photo_id, sort_order, published, updated_at, created_at) VALUES (?, ?, ?, NULL, ?, 1, ?, ?)', [$name, $order === 0 ? 'ผู้อำนวยการกองประชาสัมพันธ์' : 'บุคลากรกองประชาสัมพันธ์', '', $order + 1, now(), now()]);
        $added++;
    }
    db()->commit();
    echo "Imported profile; added $added personnel. Existing records preserved.\n";
} catch (Throwable $ex) { db()->rollBack(); throw $ex; }
