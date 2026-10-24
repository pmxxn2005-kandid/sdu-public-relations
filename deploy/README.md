# ติดตั้งเว็บไซต์กองประชาสัมพันธ์สำหรับใช้งานจริง

สถานะปัจจุบัน: เว็บไซต์ในเครื่องใช้ SQLite มีข้อมูลและรูปที่ผู้ใช้เพิ่มแล้ว ยังไม่ได้เผยแพร่บนอินเทอร์เน็ตและยังไม่ได้ทดสอบ MySQL จริง

## สิ่งที่ต้องมี

- โดเมนของหน่วยงาน และเซิร์ฟเวอร์ PHP 8.2+ พร้อม MySQL 8+ ที่รองรับ HTTPS
- PHP extensions: PDO MySQL, mbstring, GD, fileinfo
- กำหนด DocumentRoot ไปที่ `public/` ได้ โฟลเดอร์ `app`, `storage`, `.env` ต้องอยู่ภายนอกพื้นที่เว็บสาธารณะ
- พื้นที่จัดเก็บตามจำนวนรูปจริง และพื้นที่สำรองข้อมูล

## ขั้นตอนติดตั้ง

1. สำรอง `storage/app.sqlite` และ `storage/uploads/` จากเครื่องเดิม เก็บสำรองนอกเว็บสาธารณะ
2. อัปโหลดโค้ดโดยไม่ส่ง `node_modules`, `tests`, `artifacts`, ข้อมูลเข้าสู่ระบบใน `storage/setup-credentials.txt` หรือเซสชันทดสอบไปเซิร์ฟเวอร์
3. สร้างฐานข้อมูล MySQL `utf8mb4` และบัญชีฐานข้อมูลเฉพาะงานนี้
4. ใช้ `production.env.example` เป็น `.env` ที่รากโครงการ แล้วกรอกโดเมน ฐานข้อมูล และบัญชี Admin จริง ใช้รหัสผ่านสุ่ม 12 ตัวสำหรับ Admin และรหัสฐานข้อมูลที่ยาวกว่าได้
5. ให้ผู้ใช้ PHP-FPM เขียน `storage/uploads`, `storage/sessions`, `storage/backups` ได้ ห้ามตั้ง permission 777
6. กำหนด PHP-FPM: `upload_max_filesize=10M`, `post_max_size=12M`, `max_input_vars=2000`, `display_errors=Off`, `log_errors=On` และเปิด HTTPS ก่อนใช้ `SESSION_SECURE=1`
7. รัน `php bin/setup.php` แล้วตรวจด้วย `php -d max_input_vars=2000 bin/check-production.php`
8. **การ setup ไม่ย้ายข้อมูล SQLite เข้า MySQL** ต้องย้าย contents, media, content_media, media_folders, media_folder_items, personnel, settings และตารางอ้างอิงที่เกี่ยวข้องโดยรักษา ID เดิม พร้อมคัดลอกไฟล์รูป ทดสอบการย้ายบนฐานข้อมูลสำรองก่อน ยังไม่มีเครื่องมือย้ายอัตโนมัติในโครงการนี้
9. หากเริ่มฐานข้อมูลใหม่โดยไม่ย้ายเนื้อหาเดิม สามารถรัน `php bin/import-pr-profile.php` เพื่อนำเข้าข้อมูลบุคลากรจากเอกสารได้
10. ตรวจข่าว กิจกรรม อัลบั้ม รูปปก ดาวน์โหลดรูป ลิงก์ภายนอก แผนที่ และ Admin ด้วยข้อมูลจริง ตั้งอีเมลผู้ดูแลจริง เปลี่ยนรหัสเดิม และลบ `ADMIN_PASSWORD` ออกจาก `.env` หลังสร้างบัญชีแล้ว
11. แทนที่ภาพ/เนื้อหาตัวอย่างก่อนปิดป้ายเว็บไซต์ตัวอย่างใน Admin → ตั้งค่า ตั้งสำรองฐานข้อมูลและไฟล์รูปเป็นประจำ และทดสอบกู้คืน

Apache ใช้ `public/.htaccess` และเปิด mod_rewrite; Nginx ใช้ `try_files $uri /index.php?$query_string;` พร้อม PHP-FPM และห้ามเข้าถึง `router.php` กับ `.user.ini` ไม่ใช้ `php -S` เป็นเซิร์ฟเวอร์สาธารณะจริง

การตรวจ CLI ไม่ทดแทนการตรวจค่า PHP-FPM ของโฮสติ้ง ใช้เครื่องมือของโฮสติ้งตรวจค่าเดียวกัน และไม่เผยแพร่หน้า phpinfo สาธารณะ
