<?php
declare(strict_types=1);
function current_user(): ?array {
    if (isset($_SESSION['last_active']) && time() - (int)$_SESSION['last_active'] > 7200) { unset($_SESSION['user_id'], $_SESSION['last_active']); }
    $id = $_SESSION['user_id'] ?? null;
    if ($id) $_SESSION['last_active'] = time();
    return $id ? one('SELECT id, name, email, role, active FROM users WHERE id = ? AND active = 1', [$id]) : null;
}
function require_user(): array { $u = current_user(); if (!$u) redirect('/admin/login'); return $u; }
function require_editor(): array { $u = require_user(); if (!in_array($u['role'], ['admin', 'editor'], true)) abort(403, 'บัญชีนี้ไม่มีสิทธิ์ทำรายการ'); return $u; }
function require_admin(): array { $u = require_user(); if ($u['role'] !== 'admin') abort(403, 'เฉพาะผู้ดูแลระบบ'); return $u; }
function can_edit(array $row, array $u): bool { return $u['role'] !== 'author' || ($row['type'] === 'post' && (int)$row['author_id'] === (int)$u['id'] && $row['status'] === 'draft'); }
function login_attempt(string $email, string $password): bool {
    $key = hash('sha256', strtolower($email) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
    $since = date('Y-m-d H:i:s', time() - 900);
    if ((int)query('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND created_at > ?', [$key, $since])->fetchColumn() >= 5) return false;
    $u = one('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
    $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    $valid = password_verify($password, $u['password_hash'] ?? $dummy);
    if (!$u || !$valid) { query('INSERT INTO login_attempts (identifier, created_at) VALUES (?, ?)', [$key, now()]); return false; }
    query('DELETE FROM login_attempts WHERE identifier = ?', [$key]);
    session_regenerate_id(true); $_SESSION['user_id'] = (int)$u['id']; $_SESSION['last_active'] = time(); $_SESSION['csrf'] = bin2hex(random_bytes(32));
    audit('login', 'users', (int)$u['id']); return true;
}

function validate_new_password(string $password): void {
    $length=mb_strlen($password);
    if($length<6||$length>12||str_contains($password,"\0")) throw new InvalidArgumentException('รหัสผ่านต้องมี 6–12 ตัวอักษร');
    if(preg_match('/^(.)\1+$/us',$password)||in_array(mb_strtolower($password),['123456','1234567','12345678','123456789','1234567890','password','password123','qwerty','qwerty123','abcdef','111111','admin123'],true)) throw new InvalidArgumentException('รหัสผ่านนี้เดาง่าย กรุณาใช้รหัสที่ไม่ซ้ำและคาดเดายาก');
}
