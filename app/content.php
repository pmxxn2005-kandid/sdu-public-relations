<?php
declare(strict_types=1);
const CONTENT_TYPES = ['post' => 'ข่าวประชาสัมพันธ์', 'event' => 'กิจกรรม', 'album' => 'อัลบั้มภาพ', 'video' => 'วิดีโอ', 'document' => 'เอกสาร', 'page' => 'หน้าเว็บไซต์', 'banner' => 'แบนเนอร์'];
const STATUSES = ['draft' => 'ร่าง', 'scheduled' => 'ตั้งเวลา', 'published' => 'เผยแพร่', 'archived' => 'เก็บเข้าคลัง', 'trash' => 'ถังขยะ'];
function public_clause(string $alias = 'c'): string { return "($alias.status = 'published' OR $alias.status = 'scheduled') AND $alias.published_at IS NOT NULL AND $alias.published_at <= ?"; }
function visible_content(string $slug, string $type): ?array { return one('SELECT c.*, cat.name AS category_name FROM contents c LEFT JOIN categories cat ON cat.id = c.category_id WHERE c.slug = ? AND c.type = ? AND ' . public_clause(), [$slug, $type, now()]); }
function content_list(string $type, array $filters = [], int $page = 1, int $size = 9): array {
    $where = ['c.type = ?', public_clause()]; $params = [$type, now()];
    if (!empty($filters['q'])) { $where[] = '(c.title LIKE ? OR c.excerpt LIKE ? OR c.body LIKE ?)'; $q = '%' . $filters['q'] . '%'; array_push($params, $q, $q, $q); }
    if (!empty($filters['category'])) { $where[] = 'c.category_id = ?'; $params[] = (int)$filters['category']; }
    foreach (['from' => '>=', 'to' => '<='] as $key => $operator) if (!empty($filters[$key]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$key])) { $where[] = "c.published_at $operator ?"; $params[] = $filters[$key] . ($key === 'to' ? ' 23:59:59' : ' 00:00:00'); }
    $order = 'c.published_at DESC, c.id DESC';
    if ($type === 'event') {
        if (($filters['tab'] ?? '') === 'past') { $where[] = 'c.ends_at < ?'; $params[] = now(); $order = 'c.starts_at DESC'; }
        else { $where[] = '(c.ends_at >= ? OR c.ends_at IS NULL)'; $params[] = now(); $order = 'CASE WHEN c.starts_at IS NULL THEN 1 ELSE 0 END, c.starts_at ASC'; }
        if (!empty($filters['month']) && preg_match('/^\d{4}-\d{2}$/', $filters['month'])) { $where[] = 'c.starts_at >= ? AND c.starts_at < ?'; $params[] = $filters['month'] . '-01 00:00:00'; $params[] = date('Y-m-d H:i:s', strtotime($filters['month'] . '-01 +1 month')); }
    }
    $sql = implode(' AND ', $where); $count = (int)query('SELECT COUNT(*) FROM contents c WHERE ' . $sql, $params)->fetchColumn();
    $offset = max(0, $page - 1) * $size;
    $items = all('SELECT c.*, cat.name AS category_name FROM contents c LEFT JOIN categories cat ON cat.id = c.category_id WHERE ' . $sql . " ORDER BY $order LIMIT " . max(1, $size) . ' OFFSET ' . $offset, $params);
    return compact('items', 'count');
}
function save_content(array $input, array $u, ?array $old): int {
    if(isset($input['media_ids_json'])) {
        try {$ids=json_decode((string)$input['media_ids_json'],true,512,JSON_THROW_ON_ERROR);}catch(JsonException $ex){throw new InvalidArgumentException('รายการไฟล์แนบไม่ถูกต้อง กรุณาเปิดหน้าแก้ไขใหม่');}
        if(!is_array($ids)||!array_is_list($ids)||count($ids)>1000)throw new InvalidArgumentException('แนบไฟล์ได้สูงสุด 1000 รายการ');
        foreach($ids as $id)if(!is_int($id)||$id<1)throw new InvalidArgumentException('ไฟล์แนบไม่ถูกต้อง');
        $input['media_ids']=$ids;
    }

    $type = $old['type'] ?? (string)($input['type'] ?? 'post');
    if (!isset(CONTENT_TYPES[$type])) throw new InvalidArgumentException('ประเภทเนื้อหาไม่ถูกต้อง');
    if ($u['role'] === 'author' && ($type !== 'post' || ($old && !can_edit($old, $u)))) throw new RuntimeException('ไม่มีสิทธิ์แก้ไขเนื้อหานี้');
    $status = (string)($input['status'] ?? 'draft');
    if (!isset(STATUSES[$status])) throw new InvalidArgumentException('สถานะไม่ถูกต้อง');
    if ($u['role'] === 'author' && $status !== 'draft') throw new RuntimeException('ผู้เขียนบันทึกได้เฉพาะร่าง');
    $title = trim((string)($input['title'] ?? '')); if (!$title || mb_strlen($title) > 250) throw new InvalidArgumentException('กรุณากรอกชื่อไม่เกิน 250 ตัวอักษร');
    $body = trim((string)($input['body'] ?? '')); if (mb_strlen($body) > 100000) throw new InvalidArgumentException('เนื้อหายาวเกินไป');
    $pub = trim((string)($input['published_at'] ?? '')); $pub = $pub ? normalize_datetime($pub) : ($status === 'published' ? now() : null);
    if ($status === 'scheduled' && (!$pub || $pub <= now())) throw new InvalidArgumentException('กรุณาตั้งเวลาเผยแพร่ในอนาคต');
    $category = !empty($input['category_id']) ? (int)$input['category_id'] : null;
    if ($category && !one('SELECT id FROM categories WHERE id = ?', [$category])) throw new InvalidArgumentException('หมวดหมู่ไม่ถูกต้อง');
    $cover = !empty($input['cover_id']) ? (int)$input['cover_id'] : null;
    if ($cover && !one("SELECT id FROM media WHERE id = ? AND mime LIKE 'image/%'", [$cover])) throw new InvalidArgumentException('ภาพปกไม่ถูกต้อง');
    $url = trim((string)($input['external_url'] ?? ''));
    if ($url) { $url=safe_url($url); if(!$url) throw new InvalidArgumentException('กรุณากรอก URL http:// หรือ https:// ที่ถูกต้อง'); }
    if($type==='post' && ($input['post_mode']??($url?'link':'article'))==='link' && !$url) throw new InvalidArgumentException('กรุณาใส่ลิงก์เว็บไซต์ปลายทาง');
    if($type==='post' && ($input['post_mode']??($url?'link':'article'))==='article') $url='';
    if (in_array($status, ['published', 'scheduled'], true) && $type === 'post' && !$url && (!$body || !$category || !$cover)) throw new InvalidArgumentException('ก่อนเผยแพร่ข่าว กรุณากรอกเนื้อหา หมวดหมู่ และภาพปก');
    if ($status === 'published' && $pub > now()) throw new InvalidArgumentException('วันเวลาอยู่ในอนาคต กรุณาเลือกสถานะตั้งเวลา');
    $start = !empty($input['starts_at']) ? normalize_datetime($input['starts_at']) : null;
    $end = !empty($input['ends_at']) ? normalize_datetime($input['ends_at']) : null;
    if ($start && !$end) $end = $start;
    if ($end && (!$start || $end < $start)) throw new InvalidArgumentException('วันสิ้นสุดต้องไม่ก่อนวันเริ่ม');
    $manualSlug=trim((string)($input['slug']??''));
    $slug=slugify($manualSlug?:$title);
    $available=available_content_slug($slug,(int)($old['id']??0));
    if($available!==$slug && $manualSlug!=='') throw new InvalidArgumentException('URL นี้ถูกใช้แล้ว ระบบแนะนำ URL ใหม่: '.$available.' กดบันทึกอีกครั้งเพื่อใช้ URL นี้');
    $slug=$available;
    $eventStatus = (string)($input['event_status'] ?? 'normal'); if (!in_array($eventStatus, ['normal', 'cancelled'], true)) throw new InvalidArgumentException('สถานะกิจกรรมไม่ถูกต้อง');
    $fields = ['type'=>$type, 'title'=>$title, 'slug'=>$slug, 'excerpt'=>mb_substr(trim((string)($input['excerpt'] ?? '')), 0, 1000), 'body'=>$body, 'status'=>$status, 'category_id'=>$category, 'cover_id'=>$cover, 'tags'=>mb_substr(trim((string)($input['tags'] ?? '')), 0, 500), 'published_at'=>$pub, 'starts_at'=>$start, 'ends_at'=>$end, 'location'=>mb_substr(trim((string)($input['location'] ?? '')), 0, 500), 'event_status'=>$eventStatus, 'external_url'=>$url, 'seo_title'=>mb_substr(trim((string)($input['seo_title'] ?? '')), 0, 250), 'seo_description'=>mb_substr(trim((string)($input['seo_description'] ?? '')), 0, 500), 'updated_at'=>now()];
    db()->beginTransaction();
    try {
        if ($old) {
            if ((int)($input['version'] ?? 0) !== (int)$old['version']) throw new RuntimeException('มีผู้อื่นแก้ไขรายการนี้ กรุณาเปิดหน้าใหม่ก่อนบันทึก');
            $assign = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
            $s = query("UPDATE contents SET $assign, version = version + 1 WHERE id = ? AND version = ?", [...array_values($fields), $old['id'], $old['version']]);
            if ($s->rowCount() !== 1) throw new RuntimeException('มีผู้อื่นแก้ไขรายการนี้ กรุณาเปิดหน้าใหม่ก่อนบันทึก');
            $id = (int)$old['id'];
            if ($old['slug'] !== $slug) query('INSERT INTO slug_redirects (old_slug, content_id) VALUES (?, ?)', [$old['slug'], $id]);
        } else {
            $fields['author_id'] = $u['id']; $fields['created_at'] = now(); $fields['version'] = 1;
            $columns = implode(', ', array_keys($fields)); $marks = implode(', ', array_fill(0, count($fields), '?'));
            query("INSERT INTO contents ($columns) VALUES ($marks)", array_values($fields)); $id = (int)db()->lastInsertId();
        }
        $mediaIds = array_unique(array_map('intval', (array)($input['media_ids'] ?? [])));
        if (count($mediaIds) > 1000) throw new InvalidArgumentException('แนบไฟล์ได้สูงสุด 1000 รายการ');
        query('DELETE FROM content_media WHERE content_id = ?', [$id]);
        foreach ($mediaIds as $position => $mid) {
            if (!one('SELECT id FROM media WHERE id = ?', [$mid])) throw new InvalidArgumentException('ไฟล์แนบไม่ถูกต้อง');
            query('INSERT INTO content_media (content_id, media_id, sort_order) VALUES (?, ?, ?)', [$id, $mid, $position]);
        }
        query('INSERT INTO revisions (content_id, user_id, snapshot, created_at) VALUES (?, ?, ?, ?)', [$id, $u['id'], json_encode($fields, JSON_UNESCAPED_UNICODE), now()]);
        audit($old ? 'update' : 'create', 'contents', $id, ['status'=>$status]); db()->commit(); return $id;
    } catch (Throwable $ex) { db()->rollBack(); throw $ex; }
}
function normalize_datetime(string $value): string { $d = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value) ?: DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value); $errors = DateTimeImmutable::getLastErrors(); if (!$d || ($errors && ($errors['warning_count'] || $errors['error_count']))) throw new InvalidArgumentException('วันเวลาไม่ถูกต้อง'); return $d->format('Y-m-d H:i:s'); }
function upload_media(array $file, string $alt, int $userId): int {
    if (in_array($file['error'] ?? UPLOAD_ERR_NO_FILE, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new InvalidArgumentException('ไฟล์เกินขนาดที่เซิร์ฟเวอร์รับได้ กรุณาลดขนาดไฟล์');
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('อัปโหลดไม่สำเร็จ กรุณาเลือกไฟล์ใหม่');
    if ($file['size'] > 10 * 1024 * 1024 || $file['size'] < 1) throw new InvalidArgumentException('ไฟล์ต้องมีขนาดไม่เกิน 10 MB');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'application/pdf'=>'pdf'][$mime] ?? null;
    if (!$ext) throw new InvalidArgumentException('รองรับ JPG, PNG, WebP และ PDF เท่านั้น');
    if ($mime === 'application/pdf' && file_get_contents($file['tmp_name'], false, null, 0, 5) !== '%PDF-') throw new InvalidArgumentException('ไฟล์ PDF ไม่ถูกต้อง');
    $path = bin2hex(random_bytes(20)) . '.' . $ext;
    $created=[];$original=null;
    try {
        if (str_starts_with($mime, 'image/')) {
            $info = getimagesize($file['tmp_name']); if (!$info || $info[0] * $info[1] > 24000000) throw new InvalidArgumentException('ภาพต้องไม่เกิน 24 ล้านพิกเซล');
            $im = match($mime) { 'image/jpeg'=>imagecreatefromjpeg($file['tmp_name']), 'image/png'=>imagecreatefrompng($file['tmp_name']), default=>imagecreatefromwebp($file['tmp_name']) };
            if (!$im) throw new InvalidArgumentException('อ่านภาพไม่สำเร็จ');
            $originalPath=bin2hex(random_bytes(20)).'.'.$ext;
            $original=['path'=>$originalPath,'mime'=>$mime,'size'=>filesize($file['tmp_name']),'sha256'=>hash_file('sha256',$file['tmp_name'])];
            if(!move_uploaded_file($file['tmp_name'],ROOT.'/storage/uploads/'.$originalPath)) throw new RuntimeException('บันทึกภาพต้นฉบับไม่สำเร็จ');
            $created[]=$originalPath;
            $scale = min(1, 3840 / max($info[0], $info[1]));
            if ($scale < 1) {
                $scaled = imagescale($im, (int)round($info[0] * $scale), (int)round($info[1] * $scale));
                if(!$scaled) throw new RuntimeException('สร้างภาพตัวอย่างไม่สำเร็จ');
                $im = $scaled;
            }
            $path = bin2hex(random_bytes(20)) . '.webp';$created[]=$path;
            if(!imagewebp($im, ROOT . '/storage/uploads/' . $path, 90)) throw new RuntimeException('บันทึกภาพตัวอย่างไม่สำเร็จ');
            unset($im); $mime = 'image/webp';
        } else {
            if(!move_uploaded_file($file['tmp_name'], ROOT . '/storage/uploads/' . $path)) throw new RuntimeException('บันทึกไฟล์ไม่สำเร็จ');
            $created[]=$path;
        }
        $name = mb_substr(basename((string)$file['name']), 0, 250);
        db()->beginTransaction();
        query('INSERT INTO media (name, path, mime, size, alt, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$name, $path, $mime, filesize(ROOT . '/storage/uploads/' . $path), mb_substr($alt, 0, 500), $userId, now()]);
        $id = (int)db()->lastInsertId();
        if($original) query('INSERT INTO media_originals (media_id,path,mime,size,sha256) VALUES (?,?,?,?,?)',[$id,$original['path'],$original['mime'],$original['size'],$original['sha256']]);
        audit('upload', 'media', $id);db()->commit();return $id;
    } catch(Throwable $ex) {
        if(db()->inTransaction())db()->rollBack();
        foreach($created as $saved){$target=ROOT.'/storage/uploads/'.$saved;if(is_file($target))unlink($target);}
        throw $ex;
    }
}

function media_folder_id(array $input): int {
    $id=(int)($input['folder_id']??0);
    if ($id && !one('SELECT id FROM media_folders WHERE id=?',[$id])) throw new InvalidArgumentException('ไม่พบโฟลเดอร์');
    return $id;
}
function assign_media_folder(int $mediaId,int $folderId): void {
    query('DELETE FROM media_folder_items WHERE media_id=?',[$mediaId]);
    if($folderId) query('INSERT INTO media_folder_items (media_id,folder_id) VALUES (?,?)',[$mediaId,$folderId]);
}

function available_content_slug(string $base,int $excludeId=0): string {
    $base=slugify($base);$candidate=$base;$suffix=2;
    while(one('SELECT id FROM contents WHERE slug=? AND id<>?',[$candidate,$excludeId]) || one('SELECT content_id FROM slug_redirects WHERE old_slug=? AND content_id<>?',[$candidate,$excludeId])) {
        $ending='-'.$suffix++;$candidate=mb_substr($base,0,160-mb_strlen($ending)).$ending;
    }
    return $candidate;
}
