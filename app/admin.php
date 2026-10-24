<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
if ($path === '/admin/login') {
    if (current_user()) redirect('/admin');
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { verify_csrf(); if (login_attempt(trim((string)($_POST['email'] ?? '')), (string)($_POST['password'] ?? ''))) redirect('/admin'); $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง หรือมีการลองเข้าสู่ระบบมากเกินไป กรุณาลองใหม่ภายหลัง'; }
    render('admin/login', ['title'=>'เข้าสู่ระบบเจ้าหน้าที่', 'error'=>$error], true); return;
}
$u = require_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') verify_csrf();
if ($path === '/admin/media/upload') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); json_response(['error'=>'กรุณาใช้คำขออัปโหลด'],405); }
    try {
        $folderId=media_folder_id($_POST);
        $id=upload_media($_FILES['file']??[],trim((string)($_POST['alt']??'')),(int)$u['id']);
        assign_media_folder($id,$folderId);
        json_response(['id'=>$id,'name'=>one('SELECT name FROM media WHERE id=?',[$id])['name'],'message'=>'อัปโหลดไฟล์เรียบร้อย'],201);
    } catch (InvalidArgumentException $ex) { json_response(['error'=>$ex->getMessage()],422); }
    catch (Throwable $ex) { error_log($ex->__toString()); json_response(['error'=>'บันทึกไฟล์ไม่สำเร็จ กรุณาลองใหม่'],500); }
}
if ($path === '/admin/logout' && $_SERVER['REQUEST_METHOD'] === 'POST') { audit('logout', 'users', (int)$u['id']); $_SESSION=[]; session_destroy(); redirect('/admin/login'); }
if (preg_match('#^/admin/preview/(\d+)$#', $path, $m)) {
    $item = one('SELECT c.*, cat.name AS category_name FROM contents c LEFT JOIN categories cat ON cat.id=c.category_id WHERE c.id = ?', [(int)$m[1]]);
    if (!$item) abort(404); if (!can_edit($item,$u)) abort(403);
    $files=all('SELECT m.* FROM content_media cm JOIN media m ON m.id=cm.media_id WHERE cm.content_id=? ORDER BY cm.sort_order',[$item['id']]);
    render('detail',['title'=>'ตัวอย่าง · '.$item['title'],'item'=>$item,'files'=>$files,'related'=>[]]); return;
}
if ($path === '/admin') {
    $where=$u['role']==='author'?' WHERE author_id = ?':''; $params=$u['role']==='author'?[$u['id']]:[];
    $stats=all('SELECT status, COUNT(*) AS total FROM contents'.$where.' GROUP BY status',$params);
    $recent=all('SELECT c.*, usr.name AS author_name FROM contents c LEFT JOIN users usr ON usr.id=c.author_id'.$where.' ORDER BY c.updated_at DESC LIMIT 8',$params);
    render('admin/dashboard',compact('u','stats','recent')+['title'=>'ภาพรวมเว็บไซต์'],true); return;
}
if ($path === '/admin/content') {
    $type=in_array($_GET['type']??'',array_keys(CONTENT_TYPES),true)?$_GET['type']:'post';
    if($u['role']==='author'&&$type!=='post') abort(403);
    $status=in_array($_GET['status']??'',array_keys(STATUSES),true)?$_GET['status']:'';
    $where=['c.type = ?']; $params=[$type];
    if($u['role']==='author'){ $where[]='c.author_id = ?'; $params[]=$u['id']; }
    if($status){$where[]='c.status = ?';$params[]=$status;}
    if(!empty($_GET['q'])){$where[]='c.title LIKE ?';$params[]='%'.mb_substr((string)$_GET['q'],0,200).'%';}
    $where=implode(' AND ',$where); $page=max(1,(int)($_GET['page']??1));
    $count=(int)query('SELECT COUNT(*) FROM contents c WHERE '.$where,$params)->fetchColumn();
    $rows=all('SELECT c.*, usr.name AS author_name FROM contents c LEFT JOIN users usr ON usr.id=c.author_id WHERE '.$where.' ORDER BY c.updated_at DESC LIMIT 15 OFFSET '.(($page-1)*15),$params);
    render('admin/content-list',compact('u','type','status','rows','count','page')+['title'=>CONTENT_TYPES[$type]],true); return;
}
if ($path === '/admin/content/new' || preg_match('#^/admin/content/(\d+)$#',$path,$m)) {
    $old=isset($m[1])?one('SELECT * FROM contents WHERE id = ?',[(int)$m[1]]):null;
    if(isset($m[1])&&!$old) abort(404); if($old&&!can_edit($old,$u)) abort(403);
    $type=$old['type']??(string)($_GET['type']??'post'); if(!isset(CONTENT_TYPES[$type])) abort(404);
    if($u['role']==='author'&&$type!=='post') abort(403);
    $formError=''; $row=$old??['type'=>$type,'title'=>'','slug'=>'','excerpt'=>'','body'=>'','status'=>'draft','category_id'=>null,'cover_id'=>null,'tags'=>'','published_at'=>'','starts_at'=>'','ends_at'=>'','location'=>'','event_status'=>'normal','external_url'=>'','seo_title'=>'','seo_description'=>'','version'=>0];
    $selected=$old?array_column(all('SELECT media_id FROM content_media WHERE content_id = ? ORDER BY sort_order',[$old['id']]),'media_id'):[];
    if($_SERVER['REQUEST_METHOD']==='POST') {
        try {
            if(($_POST['action']??'')==='trash') {
                require_editor(); if(!$old) abort(404); query("UPDATE contents SET status='trash', version=version+1, updated_at=? WHERE id=?",[now(),$old['id']]); audit('trash','contents',(int)$old['id']); flash('ย้ายรายการเข้าถังขยะแล้ว'); redirect('/admin/content?type='.$type);
            }
            $_POST['type']=$type;
            if(($_POST['action']??'')==='draft') $_POST['status']='draft';
            if(($_POST['action']??'')==='publish') $_POST['status']='published';
            $id=save_content($_POST,$u,$old); flash('บันทึกข้อมูลเรียบร้อยแล้ว'); redirect('/admin/content/'.$id);
        } catch(InvalidArgumentException|RuntimeException $ex){$formError=$ex->getMessage();$row=array_merge($row,array_intersect_key($_POST,$row));$selected=array_map('intval',(array)($_POST['media_ids']??[]));if(str_starts_with($formError,'URL นี้ถูกใช้แล้ว'))$row['slug']=available_content_slug(slugify(trim((string)($_POST['slug']??''))?:$row['title']),(int)($old['id']??0));}
    }
    $folders=all('SELECT * FROM media_folders ORDER BY name,id');$categories=all('SELECT * FROM categories ORDER BY id');$media=all('SELECT m.*, COALESCE(fi.folder_id,0) AS folder_id FROM media m LEFT JOIN media_folder_items fi ON fi.media_id=m.id ORDER BY m.id DESC');$revisions=$old?all('SELECT r.created_at, usr.name FROM revisions r LEFT JOIN users usr ON usr.id=r.user_id WHERE content_id=? ORDER BY r.id DESC LIMIT 5',[$old['id']]):[];
    render('admin/content-form',compact('u','row','old','type','selected','formError','categories','media','revisions','folders')+['title'=>($old?'แก้ไข':'สร้าง').CONTENT_TYPES[$type]],true); return;
}
if($path==='/admin/media') {
    $error='';
    if($_SERVER['REQUEST_METHOD']==='POST') {
        try {
            if(($_POST['action']??'')==='create_folder') {
                $name=trim((string)($_POST['folder_name']??''));
                if(!$name||mb_strlen($name)>250) throw new InvalidArgumentException('กรอกชื่อโฟลเดอร์ไม่เกิน 250 ตัวอักษร');
                query('INSERT INTO media_folders (name,created_at) VALUES (?,?)',[$name,now()]);
                $newFolder=(int)db()->lastInsertId();audit('create','media_folders',$newFolder);flash('สร้างโฟลเดอร์แล้ว');redirect('/admin/media?folder='.$newFolder);
            } elseif(($_POST['action']??'')==='delete_folder') {
                require_editor();$folderId=media_folder_id($_POST);
                if(!$folderId) throw new InvalidArgumentException('กรุณาเลือกโฟลเดอร์ที่ต้องการลบ');
                try {query('DELETE FROM media_folders WHERE id=?',[$folderId]);}
                catch(PDOException $ex) {if(one('SELECT media_id FROM media_folder_items WHERE folder_id=? LIMIT 1',[$folderId])) throw new InvalidArgumentException('โฟลเดอร์นี้ยังมีไฟล์อยู่ กรุณาย้ายไฟล์ออกก่อนลบโฟลเดอร์');throw $ex;}
                audit('delete','media_folders',$folderId);flash('ลบโฟลเดอร์แล้ว');redirect('/admin/media');
            } elseif(($_POST['action']??'')==='move_folder') {
                $folderId=media_folder_id($_POST);$id=(int)($_POST['id']??0);$file=one('SELECT * FROM media WHERE id=?',[$id]);
                if(!$file) throw new InvalidArgumentException('ไม่พบไฟล์');
                if($u['role']==='author'&&(int)$file['uploaded_by']!==(int)$u['id']) abort(403);
                assign_media_folder($id,$folderId);audit('move','media',$id);flash('ย้ายไฟล์เข้าโฟลเดอร์แล้ว');
            } elseif(($_POST['action']??'')==='delete') {
                require_editor();$id=(int)($_POST['id']??0);$file=one('SELECT * FROM media WHERE id=?',[$id]);
                if(!$file) throw new InvalidArgumentException('ไม่พบไฟล์');
                if(one('SELECT id FROM contents WHERE cover_id=? LIMIT 1',[$id])||one('SELECT content_id FROM content_media WHERE media_id=? LIMIT 1',[$id])||one('SELECT id FROM personnel WHERE photo_id=? LIMIT 1',[$id])||(int)setting('hero_media_id')===$id) throw new InvalidArgumentException('ไฟล์นี้ถูกใช้งานอยู่ กรุณาถอดออกจากเนื้อหาก่อนลบ');
                query('DELETE FROM media WHERE id=?',[$id]);$filename=ROOT.'/storage/uploads/'.basename($file['path']);if(is_file($filename))unlink($filename);audit('delete','media',$id);flash('ลบไฟล์แล้ว');
            } elseif(($_POST['action']??'')==='alt') {
                $id=(int)($_POST['id']??0);$file=one('SELECT * FROM media WHERE id=?',[$id]);if(!$file)abort(404);if($u['role']==='author'&&(int)$file['uploaded_by']!==(int)$u['id'])abort(403);
                query('UPDATE media SET alt=? WHERE id=?',[mb_substr(trim((string)($_POST['alt']??'')),0,500),$id]);audit('update','media',$id);flash('ปรับปรุงคำอธิบายภาพแล้ว');
            } else {$folderId=media_folder_id($_POST);$newId=upload_media($_FILES['file']??[],trim((string)($_POST['alt']??'')),(int)$u['id']);assign_media_folder($newId,$folderId);flash('อัปโหลดไฟล์เรียบร้อย');}
            redirect('/admin/media'.(isset($_GET['folder'])?'?folder='.(int)$_GET['folder']:''));
        }catch(InvalidArgumentException|RuntimeException $ex){$error=$ex->getMessage();}
    }
    $folders=all('SELECT * FROM media_folders ORDER BY name,id');$folderFilter=isset($_GET['folder'])?(int)$_GET['folder']:null;
    $where=$folderFilter===null?'':($folderFilter===0?' WHERE fi.folder_id IS NULL':' WHERE fi.folder_id=?');$params=$folderFilter?[$folderFilter]:[];
    $page=max(1,(int)($_GET['page']??1));$count=(int)query('SELECT COUNT(*) FROM media m LEFT JOIN media_folder_items fi ON fi.media_id=m.id'.$where,$params)->fetchColumn();
    $files=all('SELECT m.*, COALESCE(fi.folder_id,0) AS folder_id FROM media m LEFT JOIN media_folder_items fi ON fi.media_id=m.id'.$where.' ORDER BY m.id DESC LIMIT 18 OFFSET '.(($page-1)*18),$params);
    render('admin/media',compact('u','files','error','page','count','folders','folderFilter')+['title'=>'คลังสื่อ'],true);return;
}
if($path==='/admin/personnel') {
    require_editor(); $error=''; $edit=isset($_GET['edit'])?one('SELECT * FROM personnel WHERE id=?',[(int)$_GET['edit']]):null;
    if($_SERVER['REQUEST_METHOD']==='POST') {
        try {
            $id=(int)($_POST['id']??0);
            if($id&&!one('SELECT id FROM personnel WHERE id=?',[$id]))throw new InvalidArgumentException('ไม่พบรายการ');
            if(($_POST['action']??'')==='delete') {query('DELETE FROM personnel WHERE id=?',[$id]);audit('delete','personnel',$id);flash('ลบข้อมูลบุคลากรแล้ว');redirect('/admin/personnel');}
            $name=trim((string)($_POST['name']??''));$position=trim((string)($_POST['position']??''));
            if(!$name||!$position||mb_strlen($name)>250||mb_strlen($position)>250)throw new InvalidArgumentException('กรุณากรอกชื่อและตำแหน่งไม่เกิน 250 ตัวอักษร');
            $photo=!empty($_POST['photo_id'])?(int)$_POST['photo_id']:null;
            if($photo&&!one("SELECT id FROM media WHERE id=? AND mime LIKE 'image/%'",[$photo]))throw new InvalidArgumentException('รูปไม่ถูกต้อง');
            $values=[$name,$position,mb_substr(trim((string)($_POST['department']??'')),0,250),$photo,(int)($_POST['sort_order']??0),isset($_POST['published'])?1:0,now()];
            if($id)query('UPDATE personnel SET name=?, position=?, department=?, photo_id=?, sort_order=?, published=?, updated_at=? WHERE id=?',[...$values,$id]);
            else {query('INSERT INTO personnel (name, position, department, photo_id, sort_order, published, updated_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',[...$values,now()]);$id=(int)db()->lastInsertId();}
            audit('save','personnel',$id);flash('บันทึกข้อมูลบุคลากรเรียบร้อย');redirect('/admin/personnel');
        }catch(InvalidArgumentException $ex){$error=$ex->getMessage();$edit=$_POST;}
    }
    $people=all('SELECT * FROM personnel ORDER BY sort_order,id');$images=all("SELECT * FROM media WHERE mime LIKE 'image/%' ORDER BY id DESC");
    render('admin/personnel',compact('u','people','images','edit','error')+['title'=>'บุคลากร'],true);return;
}
if($path==='/admin/categories') {
    require_editor();$error='';
    if($_SERVER['REQUEST_METHOD']==='POST') { $name=trim((string)($_POST['name']??''));try {if(!$name||mb_strlen($name)>250)throw new InvalidArgumentException('กรุณากรอกชื่อหมวดหมู่ไม่เกิน 250 ตัวอักษร');$id=(int)($_POST['id']??0);if($id)query('UPDATE categories SET name=? WHERE id=?',[$name,$id]);else {query('INSERT INTO categories (name) VALUES (?)',[$name]);$id=(int)db()->lastInsertId();}audit('save','categories',$id);flash('บันทึกหมวดหมู่แล้ว');redirect('/admin/categories');}catch(Throwable $ex){$error=$ex instanceof InvalidArgumentException?$ex->getMessage():'ชื่อหมวดหมู่นี้อาจถูกใช้แล้ว';} }
    $categories=all('SELECT * FROM categories ORDER BY id');render('admin/categories',compact('u','categories','error')+['title'=>'หมวดหมู่ข่าว'],true);return;
}
if($path==='/admin/settings') {
    require_admin();$error='';$fields=['site_name'=>'ชื่อหน่วยงาน','university'=>'มหาวิทยาลัย','hero_title'=>'หัวเรื่องหน้าแรก','hero_accent'=>'หัวเรื่องบรรทัดที่สอง','hero_description'=>'คำอธิบายหน้าแรก','address'=>'ที่อยู่','phone'=>'โทรศัพท์','email'=>'อีเมลติดต่อ','hours'=>'เวลาทำการ','map_url'=>'ลิงก์แผนที่','facebook_url'=>'Facebook URL','youtube_url'=>'YouTube URL','line_url'=>'LINE URL'];
    if($_SERVER['REQUEST_METHOD']==='POST') {
        try {
            $values=[];foreach($fields as $key=>$label){$value=trim((string)($_POST[$key]??''));if(mb_strlen($value)>2000)throw new InvalidArgumentException($label.' ยาวเกินไป');if(str_ends_with($key,'_url')&&$value&&!safe_url($value))throw new InvalidArgumentException($label.' ต้องเป็น URL http/https');if($key==='email'&&$value&&!filter_var($value,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('อีเมลไม่ถูกต้อง');if(in_array($key,['site_name','university'],true)&&!$value)throw new InvalidArgumentException('กรุณากรอกชื่อหน่วยงานและมหาวิทยาลัย');$values[$key]=$value;}
            $heroId=(int)($_POST['hero_media_id']??0);if($heroId&&!one("SELECT id FROM media WHERE id=? AND mime LIKE 'image/%'",[$heroId]))throw new InvalidArgumentException('ภาพหน้าแรกไม่ถูกต้อง');
            db()->beginTransaction();foreach($values as $key=>$value)set_setting($key,$value);set_setting('hero_media_id',(string)$heroId);set_setting('demo_mode',isset($_POST['demo_mode'])?'1':'0');audit('update','settings',0);db()->commit();flash('บันทึกการตั้งค่าแล้ว');redirect('/admin/settings');
        }catch(InvalidArgumentException $ex){$error=$ex->getMessage();}
    }
    render('admin/settings',compact('u','fields','error')+['title'=>'ตั้งค่าเว็บไซต์'],true);return;
}
if($path==='/admin/users') {
    require_admin();$error='';$edit=isset($_GET['edit'])?one('SELECT id,name,email,role,active FROM users WHERE id=?',[(int)$_GET['edit']]):null;
    if($_SERVER['REQUEST_METHOD']==='POST') {
        try {
            $id=(int)($_POST['id']??0);$old=$id?one('SELECT * FROM users WHERE id=?',[$id]):null;if($id&&!$old)throw new InvalidArgumentException('ไม่พบบัญชี');
            $name=trim((string)($_POST['name']??''));$email=trim((string)($_POST['email']??''));$role=(string)($_POST['role']??'author');$active=isset($_POST['active'])?1:0;$password=(string)($_POST['password']??'');
            if(!$name||mb_strlen($name)>250||strlen($email)>250||!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($role,['admin','editor','author'],true))throw new InvalidArgumentException('กรุณาตรวจชื่อ อีเมล และบทบาท');
            if($password!==''||!$id)validate_new_password($password);
            if($id===(int)$u['id']&&(!$active||$role!=='admin'))throw new InvalidArgumentException('ไม่สามารถระงับหรือเปลี่ยนสิทธิ์บัญชีของตนเอง');
            if($old&&$old['role']==='admin'&&(!$active||$role!=='admin')&&(int)query("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1 AND id<>?",[$id])->fetchColumn()===0)throw new InvalidArgumentException('ต้องมีผู้ดูแลระบบที่ใช้งานได้อย่างน้อยหนึ่งคน');
            if(one('SELECT id FROM users WHERE email=? AND id<>?',[$email,$id]))throw new InvalidArgumentException('อีเมลนี้ถูกใช้แล้ว');
            if($id){query('UPDATE users SET name=?,email=?,role=?,active=? WHERE id=?',[$name,$email,$role,$active,$id]);if($password!=='')query('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);}
            else{query('INSERT INTO users (name,email,password_hash,role,active,created_at) VALUES (?,?,?,?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),$role,$active,now()]);$id=(int)db()->lastInsertId();}
            audit('save','users',$id);flash('บันทึกบัญชีผู้ใช้แล้ว');redirect('/admin/users');
        }catch(InvalidArgumentException $ex){$error=$ex->getMessage();$edit=array_diff_key($_POST,['password'=>true]);}
    }
    $users=all('SELECT id,name,email,role,active FROM users ORDER BY id');render('admin/users',compact('u','users','edit','error')+['title'=>'ผู้ใช้งาน'],true);return;
}
if($path==='/admin/logs') { require_admin();$page=max(1,(int)($_GET['page']??1));$count=(int)query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();$logs=all('SELECT a.*, usr.name FROM audit_logs a LEFT JOIN users usr ON usr.id=a.user_id ORDER BY a.id DESC LIMIT 30 OFFSET '.(($page-1)*30));render('admin/logs',compact('u','logs','page','count')+['title'=>'ประวัติการใช้งาน'],true);return; }
abort(404);
