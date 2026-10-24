<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (env('APP_ENV', 'local') !== 'local') throw new RuntimeException('Example seeding is allowed only in local environment');
$user = one("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
if (!$user) throw new RuntimeException('Run php bin/setup.php first');
if (query('SELECT COUNT(*) FROM contents')->fetchColumn()) { echo "Content already exists; no changes made.\n"; exit; }
$cover = null;
if (is_file(ROOT . '/public/assets/campus.webp')) {
    copy(ROOT . '/public/assets/campus.webp', ROOT . '/storage/uploads/sample-campus.webp');
    query('INSERT INTO media (name, path, mime, size, alt, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)', ['ภาพมหาวิทยาลัยจำลอง.webp', 'sample-campus.webp', 'image/webp', filesize(ROOT . '/storage/uploads/sample-campus.webp'), 'ภาพประกอบมหาวิทยาลัยจำลอง ไม่ใช่ภาพสถานที่จริง', $user['id'], now()]);
    $cover = (int)db()->lastInsertId();
}
$body = "พื้นที่แห่งการเรียนรู้และการเติบโตไปด้วยกัน\n\nเนื้อหานี้เป็นตัวอย่างสำหรับทดสอบเว็บไซต์กองประชาสัมพันธ์ มหาวิทยาลัยสวนดุสิต ไม่ใช่ข่าวหรือประกาศอย่างเป็นทางการ\n\nเว็บไซต์รวบรวมข่าวสาร กิจกรรม และสื่อประชาสัมพันธ์ เพื่อให้ทุกคนเข้าถึงข้อมูลได้สะดวกจากทุกอุปกรณ์";
$cat = (int)query('SELECT id FROM categories ORDER BY id LIMIT 1')->fetchColumn();
$titles = ['เรื่องราวจากรั้วสวนดุสิต', 'พื้นที่แห่งการเรียนรู้', 'เชื่อมโยงความรู้ สู่โอกาสใหม่', 'แลกเปลี่ยนมุมมองและประสบการณ์', 'ติดตามข่าวสารมหาวิทยาลัย', 'ทุกเรื่องราว เริ่มต้นที่สวนดุสิต'];
foreach ($titles as $i => $title) {
    query('INSERT INTO contents (type, title, slug, excerpt, body, status, category_id, cover_id, author_id, published_at, external_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', ['post', $title, 'sample-news-' . ($i + 1), 'ข่าวตัวอย่าง · เรียนรู้ เชื่อมโยง และแบ่งปันเรื่องราวจากมหาวิทยาลัย', $body, 'published', $cat, $cover, $user['id'], date('Y-m-d H:i:s', time() - $i * 86400), '', now(), now()]);
}
foreach ([['event','เปิดพื้นที่การเรียนรู้','sample-event-1'], ['event','แลกเปลี่ยนมุมมองใหม่','sample-event-2'], ['album','บรรยากาศสวนดุสิต','sample-album-1'], ['album','ภาพกิจกรรมมหาวิทยาลัย','sample-album-2']] as [$type, $title, $slug]) {
    query('INSERT INTO contents (type, title, slug, excerpt, body, status, cover_id, author_id, published_at, external_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$type, $title, $slug, 'ข้อมูลตัวอย่างสำหรับการออกแบบและทดสอบ', $body, 'published', $cover, $user['id'], now(), '', now(), now()]);
    $id = (int)db()->lastInsertId();
    if ($cover && $type === 'album') query('INSERT INTO content_media (content_id, media_id, sort_order) VALUES (?, ?, 0)', [$id, $cover]);
}
set_setting('demo_mode', '1');
echo "Example content created. Personnel and contact data remain empty until verified.\n";
