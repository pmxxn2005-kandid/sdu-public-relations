<?php
declare(strict_types=1);
// Included only in the private deployment package, never in public/.
$private=__DIR__.'/_private';
$lock=$private.'/storage/install.lock';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if(is_file($lock)||!is_file($private.'/setup.token')) {http_response_code(404);exit('Installation unavailable');}
$local=in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true);
$https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
if(!$local&&!$https){http_response_code(403);exit('Please enable HTTPS before installation.');}
ini_set('display_errors','0');
session_save_path($private.'/storage/sessions');session_name('sdu_install_session');
session_set_cookie_params(['httponly'=>true,'secure'=>$https,'samesite'=>'Strict','path'=>'/']);session_start();
$_SESSION['install_csrf']??=bin2hex(random_bytes(32));
$error='';$complete=false;
function escape_install(string $v):string{return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!hash_equals($_SESSION['install_csrf'],(string)($_POST['csrf']??'')))throw new RuntimeException('คำขอหมดอายุ กรุณาเปิดหน้าใหม่');
  if(!hash_equals(trim(file_get_contents($private.'/setup.token')),trim((string)($_POST['token']??''))))throw new RuntimeException('รหัสติดตั้งไม่ถูกต้อง');
  $values=[];foreach(['host','database','username','password'] as $field){$values[$field]=(string)($_POST[$field]??'');if($values[$field]===''||preg_match('/[\r\n\x00]/',$values[$field]))throw new RuntimeException('กรุณากรอกข้อมูลฐานข้อมูลให้ครบ');}
  foreach(['host','database','username'] as $field)if(!preg_match('/^[a-zA-Z0-9_.-]+$/',$values[$field]))throw new RuntimeException('ชื่อโฮสต์ ฐานข้อมูล หรือผู้ใช้ไม่ถูกต้อง');
  if(str_contains($values['password'],'"')||str_contains($values['password'],"'"))throw new RuntimeException('รหัสฐานข้อมูลมีเครื่องหมายคำพูด กรุณาตั้งค่า .env ด้วยตัวเอง');
  $port=(int)($_POST['port']??3306);if($port<1||$port>65535)throw new RuntimeException('พอร์ต MySQL ไม่ถูกต้อง');
  $pdo=new PDO('mysql:host='.$values['host'].';port='.$port.';dbname='.$values['database'].';charset=utf8mb4',$values['username'],$values['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $mutex=fopen($private.'/storage/install.mutex','c');if(!$mutex||!flock($mutex,LOCK_EX|LOCK_NB))throw new RuntimeException('มีการติดตั้งอยู่ กรุณารอสักครู่');
  if(is_file($lock))throw new RuntimeException('ติดตั้งแล้ว');
  if($pdo->query('SHOW TABLES')->fetchColumn()!==false)throw new RuntimeException('ต้องใช้ฐานข้อมูลใหม่ที่ยังไม่มีตาราง เพื่อป้องกันข้อมูลเดิมถูกเขียนทับ');
  $manifest=json_decode(file_get_contents($private.'/install-manifest.json'),true,512,JSON_THROW_ON_ERROR);
  foreach($manifest['media_paths'] as $path)if(!is_file($private.'/storage/uploads/'.basename($path)))throw new RuntimeException('ยังอัปโหลดรูปไม่ครบ กรุณาแตก ZIP รูปทุกชุดก่อนติดตั้ง');
  $pdo->exec(file_get_contents($private.'/database.sql'));
  foreach($manifest['tables'] as $table=>$expected){if($table==='login_attempts')continue;if((int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()!==$expected)throw new RuntimeException('จำนวนข้อมูลหลังนำเข้าไม่ตรง กรุณาตรวจฐานข้อมูลก่อนเปิดเว็บ');}
  $env='APP_ENV=production'."\nAPP_URL=https://sdu-public-relations.rf.gd\nDB_DRIVER=mysql\nSESSION_SECURE=1\n";
  $env.='DB_PORT='.$port."\n";
  foreach(['host'=>'DB_HOST','database'=>'DB_DATABASE','username'=>'DB_USERNAME','password'=>'DB_PASSWORD'] as $key=>$name){if(str_contains($values[$key],'"')||str_contains($values[$key],"'"))throw new RuntimeException('รหัสฐานข้อมูลมีเครื่องหมายคำพูด กรุณาตั้งค่า .env ด้วยตัวเอง');$env.=$name.'="'.$values[$key].'"'."\n";}
  $temp=$private.'/.env.install';if(file_put_contents($temp,$env,LOCK_EX)===false)throw new RuntimeException('เขียนไฟล์ตั้งค่าไม่สำเร็จ');chmod($temp,0600);
  if(!rename($temp,$private.'/.env'))throw new RuntimeException('บันทึกการตั้งค่าไม่สำเร็จ');
  if(file_put_contents($lock,date(DATE_ATOM),LOCK_EX)===false)throw new RuntimeException('ล็อกหน้าติดตั้งไม่สำเร็จ');chmod($lock,0600);
  unlink($private.'/setup.token');unlink($private.'/database.sql');session_destroy();$complete=true;
 }catch(Throwable $ex){http_response_code(422);error_log('SDU installation failed: '.get_class($ex));$error=$ex instanceof RuntimeException&&!($ex instanceof PDOException)?$ex->getMessage():'เชื่อมต่อหรือนำเข้าฐานข้อมูลไม่สำเร็จ ตรวจข้อมูล MySQL ใน Control Panel หากมีตารางนำเข้าแล้วต้องใช้ฐานข้อมูลว่างชุดใหม่';}
}
?>
<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>ติดตั้งเว็บไซต์กองประชาสัมพันธ์</title><style>body{font:16px Tahoma,sans-serif;background:#f2f7fb;color:#172633;margin:0}main{max-width:580px;margin:35px auto;padding:28px;background:white;border-radius:12px}h1{font-size:24px}p{line-height:1.8}label{display:block;margin:18px 0}input{display:block;box-sizing:border-box;width:100%;padding:12px;margin-top:8px;border:1px solid #becfdd;border-radius:6px}button,a{display:inline-block;padding:12px 18px;border:0;border-radius:6px;background:#0075b2;color:white;text-decoration:none}.error{color:#9d2630}small{line-height:1.7;display:block}</style><main><h1>ติดตั้งเว็บไซต์กองประชาสัมพันธ์</h1><?php if($complete): ?><p>นำเข้าข้อมูลและตั้งค่า MySQL เรียบร้อยแล้ว หน้าติดตั้งถูกล็อกอัตโนมัติ</p><a href="/">เปิดเว็บไซต์</a> <a href="/admin">เข้าสู่ระบบ Admin</a><p>ลบ install.php และ ZIP ที่อัปโหลดออกจากโฮสติ้งหลังตรวจระบบแล้ว</p><?php else: ?><p>อัปโหลดเว็บไซต์และรูปให้ครบก่อน จากนั้นกรอกข้อมูลฐานข้อมูลใหม่จาก InfinityFree รหัสผ่านไม่ถูกแสดงในหน้านี้</p><?php if($error): ?><p role="alert" class="error"><?= escape_install($error) ?></p><?php endif ?><form method="post"><input type="hidden" name="csrf" value="<?= escape_install($_SESSION['install_csrf']) ?>"><label>รหัสติดตั้งจากไฟล์ในเครื่อง<input type="password" name="token" required autocomplete="off"></label><label>MySQL Hostname<input name="host" placeholder="sqlXXX.infinityfree.com" required></label><label>MySQL Port<input name="port" type="number" value="3306" min="1" max="65535" required></label><label>Database Name (ชื่อเต็ม)<input name="database" required></label><label>MySQL Username<input name="username" required></label><label>MySQL Password<input type="password" name="password" autocomplete="new-password" required></label><small>ใช้ฐานข้อมูลว่างเท่านั้น ระบบจะนำเข้าข่าว รูป โฟลเดอร์ บุคลากร และบัญชีจากชุดติดตั้ง และปิดหน้าติดตั้งหลังทำสำเร็จ</small><p><button>ตรวจข้อมูลและติดตั้ง</button></p></form><?php endif ?></main></html>
