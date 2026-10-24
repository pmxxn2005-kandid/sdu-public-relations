<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
$failures=0;
function readiness(bool $ok,string $message):void {global $failures;echo ($ok?'PASS: ':'CHECK: ').$message."\n";if(!$ok)$failures++;}
readiness(version_compare(PHP_VERSION,'8.2','>='),'PHP 8.2 ขึ้นไป');
foreach(['pdo_mysql','mbstring','gd','fileinfo'] as $extension)readiness(extension_loaded($extension),'PHP extension '.$extension);
readiness(env('APP_ENV')==='production','APP_ENV=production');
readiness(env('DB_DRIVER')==='mysql','ใช้ MySQL สำหรับระบบจริง');
readiness(str_starts_with(env('APP_URL'),'https://')&&!str_contains(env('APP_URL'),'.example'),'ตั้ง APP_URL เป็นโดเมนจริงผ่าน HTTPS');
readiness(env('SESSION_SECURE')==='1','SESSION_SECURE=1');
readiness((int)ini_get('max_input_vars')>=2000,'max_input_vars อย่างน้อย 2000 (ค่าของ CLI; ตรวจ PHP-FPM แยกด้วย)');
foreach(['storage/uploads','storage/sessions','storage/backups'] as $directory)readiness(is_writable(ROOT.'/'.$directory),'เขียน '.$directory.' ได้');
try {
 readiness(one('SELECT id FROM users LIMIT 1')!==null,'ฐานข้อมูลเชื่อมต่อได้และติดตั้งแล้ว');
 readiness(setting('demo_mode')!=='1','แทนข้อมูลตัวอย่างครบและปิดป้ายเว็บไซต์ตัวอย่าง');
 readiness((int)query('SELECT COUNT(*) FROM users WHERE active=1 AND email LIKE ?',['%@example.test'])->fetchColumn()===0,'เปลี่ยนอีเมลบัญชีทดสอบเป็นบัญชีจริง');
 readiness((int)query('SELECT COUNT(*) FROM media_folder_items')->fetchColumn()>=0,'ตารางโฟลเดอร์สื่อพร้อมใช้งาน');
} catch(Throwable $ex){readiness(false,'ตรวจฐานข้อมูลไม่สำเร็จ: ตรวจการติดตั้งและค่าการเชื่อมต่อ');}
echo "\n".($failures?"ยังมี $failures รายการที่ต้องตรวจ ก่อนเปิดเว็บไซต์จริง":"การตรวจค่าพื้นฐานผ่านแล้ว ต้องทดสอบ HTTPS, PHP-FPM และสำรอง/กู้คืนบนเซิร์ฟเวอร์จริงด้วย")."\n";
exit($failures?1:0);
