<?php
declare(strict_types=1);
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now(): string { return date('Y-m-d H:i:s'); }
function redirect(string $path): never { header('Location: ' . $path, true, 303); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="_token" value="' . e(csrf()) . '">'; }
function verify_csrf(): void { if (!hash_equals(csrf(), (string)($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) abort(419, 'คำขอหมดอายุ กรุณารีเฟรชหน้าและลองใหม่'); }
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'] = ['message' => $message, 'type' => $type]; }
function take_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function render(string $view, array $data = [], bool $admin = false): void { extract($data); $template = ROOT . '/views/' . $view . '.php'; require ROOT . '/views/' . ($admin ? 'admin/layout' : 'layout') . '.php'; }
function abort(int $status, string $message = 'ไม่พบหน้าที่ต้องการ'): never { http_response_code($status); render('error', ['title' => $message, 'status' => $status]); exit; }
function json_response(mixed $data, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function thai_date(?string $date): string {
    if (!$date) return 'วันและเวลารอประกาศ';
    $t = strtotime($date); $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return date('j', $t) . ' ' . $months[(int)date('n', $t) - 1] . ' ' . ((int)date('Y', $t) + 543);
}
function safe_url(string $url): string {
    if ($url==='' || preg_match('/[\x00-\x20\x7f\\\\]/',$url) || preg_match('/%0[ad]/i',$url)) return '';
    // Encode non-ASCII UTF-8 bytes without changing URL separators or existing escapes.
    $encoded=preg_replace_callback('/[\x80-\xff]/',fn($match)=>rawurlencode($match[0]),$url);
    return filter_var($encoded,FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($encoded,PHP_URL_SCHEME)),['https','http'],true) ? $encoded : '';
}
function slugify(string $text): string { $s = mb_strtolower(trim($text)); $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $s); return mb_substr(trim((string)$s, '-'), 0, 160) ?: bin2hex(random_bytes(6)); }
function icon(string $name, int $size = 22): string {
    $paths = ['arrow'=>'<path d="M5 12h14m-5-5 5 5-5 5"/>', 'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>', 'news'=>'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h5"/>', 'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18M7 15h2m4 0h2"/>', 'image'=>'<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8" cy="8" r="1.5"/><path d="m3 17 6-6 4 4 3-3 5 5"/>', 'download'=>'<path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/>', 'users'=>'<circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3m2-17a3 3 0 0 1 0 6m1 5a5 5 0 0 1 3 6"/>', 'pin'=>'<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>', 'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>', 'phone'=>'<path d="m5 3 4 4-2 3a16 16 0 0 0 7 7l3-2 4 4c-3 5-8 2-12-2S1 6 5 3Z"/>', 'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>', 'grid'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>', 'plus'=>'<path d="M12 4v16M4 12h16"/>', 'check'=>'<path d="m5 12 4 4L19 6"/>', 'logout'=>'<path d="M9 3H3v18h6m6-14 5 5-5 5m-9-5h14"/>', 'settings'=>'<path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM9 3h6l1 3 3 1 2 5-2 5-3 1-1 3H9l-1-3-3-1-2-5 2-5 3-1Z"/>', 'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>'];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['news']) . '</svg>';
}
function image_url(?int $id, string $fallback = '/assets/campus.svg'): string { return $id ? '/files/' . $id : $fallback; }
function inline_body(string $text): string {
    $parts=preg_split('/(\*\*[^*\r\n]+\*\*|(?<!\*)\*[^*\r\n]+\*|\[[^\]\r\n]+\]\(https?:\/\/[^\s)]+\))/u',$text,-1,PREG_SPLIT_DELIM_CAPTURE);
    $html='';foreach($parts as $part){
        if(preg_match('/^\*\*(.+)\*\*$/u',$part,$m))$html.='<strong>'.e($m[1]).'</strong>';
        elseif(preg_match('/^\*(.+)\*$/u',$part,$m))$html.='<em>'.e($m[1]).'</em>';
        elseif(preg_match('/^\[([^\]]+)\]\((.+)\)$/u',$part,$m)&&safe_url($m[2]))$html.='<a href="'.e($m[2]).'" target="_blank" rel="noopener noreferrer">'.e($m[1]).'</a>';
        else $html.=e($part);
    }return $html;
}
function formatted_body(string $text): string {
    $html='';$paragraph=[];$list=false;
    $flush=function()use(&$html,&$paragraph){if($paragraph){$html.='<p>'.implode('<br>',array_map('inline_body',$paragraph)).'</p>';$paragraph=[];}};
    foreach(preg_split('/\R/u',$text) as $line){
        if(preg_match('/^(#{2,3})\s+(.+)$/u',$line,$m)){$flush();if($list){$html.='</ul>';$list=false;}$tag=strlen($m[1])===2?'h2':'h3';$html.="<$tag>".inline_body($m[2])."</$tag>";}
        elseif(preg_match('/^-\s+(.+)$/u',$line,$m)){$flush();if(!$list){$html.='<ul>';$list=true;}$html.='<li>'.inline_body($m[1]).'</li>';}
        else {if($list){$html.='</ul>';$list=false;}if(trim($line)==='')$flush();else $paragraph[]=$line;}
    }$flush();if($list)$html.='</ul>';return $html;
}
function public_link(array $item): string { if($item['type']==='post' && safe_url($item['external_url']??'')) return safe_url($item['external_url']); return match ($item['type']) { 'post' => '/news/', 'event' => '/events/', 'album' => '/media/albums/', default => '/page/' } . rawurlencode($item['slug']); }
function pagination(int $count, int $page, int $size = 9): void {
    $pages = (int)ceil($count / $size); if ($pages <= 1) return;
    echo '<nav class="pagination" aria-label="แบ่งหน้า">';
    foreach (range(max(1, $page - 2), min($pages, $page + 2)) as $n) { $params = $_GET; $params['page'] = $n; echo '<a class="' . ($n === $page ? 'active' : '') . '" href="?' . e(http_build_query($params)) . '"' . ($n === $page ? ' aria-current="page"' : '') . '>' . $n . '</a>'; }
    echo '</nav>';
}
