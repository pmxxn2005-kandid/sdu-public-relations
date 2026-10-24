<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
try {
    if (!one('SELECT id FROM users LIMIT 1')) abort(503, 'กรุณาติดตั้งฐานข้อมูลด้วย php bin/setup.php');
    $path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $path = rtrim($path, '/') ?: '/';
    if (str_starts_with($path, '/admin')) { require ROOT . '/app/admin.php'; exit; }
    if (preg_match('#^/files/(\d+)$#', $path, $m)) {
        $file = one('SELECT * FROM media WHERE id = ?', [(int)$m[1]]); if (!$file) abort(404);
        $public = one('SELECT c.id FROM contents c LEFT JOIN content_media cm ON cm.content_id = c.id WHERE (c.cover_id = ? OR cm.media_id = ?) AND ' . public_clause() . ' LIMIT 1', [$file['id'], $file['id'], now()]) || one('SELECT id FROM personnel WHERE photo_id = ? AND published = 1 LIMIT 1', [$file['id']]) || (int)setting('hero_media_id') === (int)$file['id'];
        if (!$public && !current_user()) abort(404);
        if(isset($_GET['download'])) {
            $original=one('SELECT path,mime,size FROM media_originals WHERE media_id=?',[$file['id']]);
            if($original){$file['path']=$original['path'];$file['mime']=$original['mime'];$file['size']=$original['size'];}
        }
        $filename = ROOT . '/storage/uploads/' . basename($file['path']); if (!is_file($filename)) abort(404, 'ไม่พบไฟล์ที่ต้องการ');
        header('Content-Type: ' . $file['mime']); header('Content-Length: ' . filesize($filename));
        header('Cache-Control: private, no-store');
        if (isset($_GET['download']) || $file['mime'] === 'application/pdf') {
            $extension = ['application/pdf'=>'pdf','image/webp'=>'webp','image/jpeg'=>'jpg','image/png'=>'png'][$file['mime']] ?? 'bin';
            $downloadName = pathinfo(basename($file['name']), PATHINFO_FILENAME) . '.' . $extension;
            header("Content-Disposition: attachment; filename=\"download.$extension\"; filename*=UTF-8''" . rawurlencode($downloadName));
        }
        readfile($filename); exit;
    }
    if ($path === '/robots.txt') { header('Content-Type: text/plain'); echo "User-agent: *\nDisallow: /admin\nDisallow: /api/admin\nSitemap: " . rtrim(env('APP_URL', 'http://127.0.0.1:8080'), '/') . "/sitemap.xml\n"; exit; }
    if ($path === '/sitemap.xml') {
        header('Content-Type: application/xml; charset=utf-8'); $base = rtrim(env('APP_URL', 'http://127.0.0.1:8080'), '/');
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (['/', '/news', '/events', '/media', '/personnel', '/contact'] as $link) echo '<url><loc>' . e($base . $link) . '</loc></url>';
        foreach (all("SELECT c.* FROM contents c WHERE c.type IN ('post','event','album','page') AND " . public_clause(), [now()]) as $item) if(!($item['type']==='post' && safe_url($item['external_url']??''))) echo '<url><loc>' . e($base . public_link($item)) . '</loc></url>';
        echo '</urlset>'; exit;
    }
    if ($path === '/api/news' || $path === '/api/events') { $type = $path === '/api/news' ? 'post' : 'event'; json_response(content_list($type, $_GET, max(1, (int)($_GET['page'] ?? 1)))); }
    if ($path === '/api/personnel') json_response(['items'=>all('SELECT id, name, position, department, photo_id FROM personnel WHERE published = 1 ORDER BY sort_order, id')]);
    if ($path === '/') {
        render('home', ['title'=>'ข่าวสารและเรื่องราวจากมหาวิทยาลัย', 'news'=>content_list('post', [], 1, 6)['items'], 'events'=>content_list('event', [], 1, 3)['items'], 'albums'=>content_list('album', [], 1, 3)['items'], 'banners'=>content_list('banner', [], 1, 3)['items']]); exit;
    }
    if ($path === '/news' || $path === '/events') {
        $type = $path === '/news' ? 'post' : 'event'; $page = max(1, (int)($_GET['page'] ?? 1));
        render('listing', ['title'=>$type === 'post' ? 'ข่าวประชาสัมพันธ์' : 'กิจกรรมมหาวิทยาลัย', 'type'=>$type, 'page'=>$page, ...content_list($type, $_GET, $page), 'categories'=>all('SELECT * FROM categories ORDER BY id')]); exit;
    }
    if (preg_match('#^/(news|events)/([^/]+)$#', $path, $m) || preg_match('#^/media/(albums)/([^/]+)$#', $path, $m) || preg_match('#^/(page)/([^/]+)$#', $path, $m)) {
        $type = match($m[1]) { 'news'=>'post', 'events'=>'event', 'albums'=>'album', default=>'page' };
        $item = visible_content($m[2], $type);
        if (!$item) {
            $old = one('SELECT c.* FROM slug_redirects r JOIN contents c ON c.id = r.content_id WHERE r.old_slug = ? AND c.type = ? AND ' . public_clause(), [$m[2], $type, now()]);
            if ($old) { header('Location: ' . public_link($old), true, 301); exit; } abort(404);
        }
        if($type==='post' && safe_url($item['external_url']??'')) { header('Location: '.safe_url($item['external_url']),true,302);exit; }
        $files = all('SELECT m.* FROM content_media cm JOIN media m ON m.id = cm.media_id WHERE cm.content_id = ? ORDER BY cm.sort_order', [$item['id']]);
        $related = all('SELECT c.*, cat.name AS category_name FROM contents c LEFT JOIN categories cat ON cat.id=c.category_id WHERE c.type = ? AND c.id <> ? AND ' . public_clause() . ' ORDER BY c.published_at DESC LIMIT 3', [$type, $item['id'], now()]);
        render('detail', ['title'=>$item['title'], 'item'=>$item, 'files'=>$files, 'related'=>$related]); exit;
    }
    if ($path === '/media') {
        $tab = in_array($_GET['tab'] ?? '', ['album', 'video', 'document'], true) ? $_GET['tab'] : 'album'; $page = max(1, (int)($_GET['page'] ?? 1));
        render('media', ['title'=>'สื่อและดาวน์โหลด', 'tab'=>$tab, 'page'=>$page, ...content_list($tab, $_GET, $page)]); exit;
    }
    if ($path === '/personnel') { render('personnel', ['title'=>'บุคลากรกองประชาสัมพันธ์', 'people'=>all('SELECT * FROM personnel WHERE published = 1 ORDER BY sort_order, id')]); exit; }
    if ($path === '/contact') { render('contact', ['title'=>'ติดต่อกองประชาสัมพันธ์']); exit; }
    if ($path === '/search') {
        $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 200)); $type = in_array($_GET['type'] ?? '', ['post','event','document'], true) ? $_GET['type'] : ''; $page = max(1, (int)($_GET['page'] ?? 1));
        $items = []; $count = 0;
        if ($q !== '') {
            $where = [public_clause(), "c.type IN ('post','event','document')", '(c.title LIKE ? OR c.excerpt LIKE ? OR c.body LIKE ?)']; $params = [now(), '%' . $q . '%', '%' . $q . '%', '%' . $q . '%'];
            if ($type) { $where[] = 'c.type = ?'; $params[] = $type; }
            $where = implode(' AND ', $where); $count = (int)query('SELECT COUNT(*) FROM contents c WHERE ' . $where, $params)->fetchColumn();
            $items = all('SELECT c.* FROM contents c WHERE ' . $where . ' ORDER BY c.published_at DESC LIMIT 9 OFFSET ' . (($page - 1) * 9), $params);
        }
        render('search', compact('q', 'type', 'page', 'items', 'count') + ['title'=>'ค้นหาข้อมูล']); exit;
    }
    abort(404);
} catch (Throwable $ex) {
    error_log($ex->__toString()); http_response_code(503);
    if (str_starts_with((string)($_SERVER['REQUEST_URI'] ?? ''), '/api/')) json_response(['error'=>'ระบบยังไม่พร้อมใช้งาน'], 503);
    render('error', ['title'=>'ระบบยังไม่พร้อมใช้งาน กรุณาลองใหม่ภายหลัง', 'status'=>503]);
}
