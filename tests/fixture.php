<?php
declare(strict_types=1);
putenv('APP_ENV=test');putenv('DB_DRIVER=sqlite');putenv('ADMIN_EMAIL=browser-admin@example.test');putenv('ADMIN_PASSWORD=B8!test2Kx3Q');
require dirname(__DIR__).'/bin/setup.php';
$admin=one("SELECT * FROM users WHERE role='admin'");
if(!one("SELECT id FROM users WHERE email='browser-author@example.test'"))query('INSERT INTO users (name,email,password_hash,role,active,created_at) VALUES (?,?,?,?,1,?)',['ผู้เขียนทดสอบ','browser-author@example.test',password_hash('B8!test2Kx3Q',PASSWORD_DEFAULT),'author',now()]);
if(query('SELECT COUNT(*) FROM contents')->fetchColumn())exit;
copy(ROOT.'/public/assets/campus.webp',ROOT.'/storage/uploads/browser-test-campus.webp');
query('INSERT INTO media (name,path,mime,size,alt,uploaded_by,created_at) VALUES (?,?,?,?,?,?,?)',['ภาพทดสอบ.webp','browser-test-campus.webp','image/webp',filesize(ROOT.'/public/assets/campus.webp'),'ภาพตัวอย่างสำหรับการทดสอบ',$admin['id'],now()]);$cover=(int)db()->lastInsertId();
$_SESSION=['user_id'=>$admin['id']];$cat=(int)query('SELECT id FROM categories LIMIT 1')->fetchColumn();
foreach(['post'=>'ข่าวทดสอบสวนดุสิต','event'=>'กิจกรรมทดสอบ','album'=>'อัลบั้มทดสอบ'] as $type=>$title){$id=save_content(['type'=>$type,'title'=>$title,'slug'=>'test-'.$type,'body'=>'เนื้อหาตัวอย่างสำหรับทดสอบระบบ','excerpt'=>'ตัวอย่างข่าวและกิจกรรม','status'=>'published','cover_id'=>$cover,'category_id'=>$cat,'media_ids'=>$type==='album'?[$cover]:[]],$admin,null);}
query('INSERT INTO personnel (name,position,department,photo_id,sort_order,published,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)',['บุคลากรทดสอบ','ตำแหน่งตัวอย่าง','กลุ่มงานตัวอย่าง',null,0,1,now(),now()]);
echo "Browser fixture ready.\n";
