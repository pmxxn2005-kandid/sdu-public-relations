"""Build a private local upload package; never publishes or changes source data."""
from pathlib import Path
import sqlite3, zipfile, json, os, subprocess, secrets, shutil
root=Path(__file__).resolve().parents[1]
out=root/'storage/deployment/infinityfree'
out.mkdir(parents=True,exist_ok=True)
site=out/'site'
if site.exists(): shutil.rmtree(site)
private=site/'_private'
private.mkdir(parents=True)
for directory in ['app','views']:
 shutil.copytree(root/directory,private/directory)
for directory in ['uploads','sessions','backups']:(private/'storage'/directory).mkdir(parents=True)
shutil.copytree(root/'public/assets',site/'assets')
index=(root/'public/index.php').read_text().replace("dirname(__DIR__) . '/app/bootstrap.php'","__DIR__ . '/_private/app/bootstrap.php'")
(site/'index.php').write_text(index)
(site/'.htaccess').write_text('Options -Indexes\nRewriteEngine On\nRewriteRule ^_private(?:/|$) - [F,L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteRule ^ index.php [QSA,L]\n<FilesMatch "^(\\.env|\\.user\\.ini|router\\.php)$">\nRequire all denied\n</FilesMatch>\n')
(private/'.htaccess').write_text('Require all denied\n')
(site/'.user.ini').write_text('upload_max_filesize=10M\npost_max_size=12M\nmax_input_vars=2000\ndisplay_errors=Off\nlog_errors=On\n')
(private/'.env').write_text((root/'deploy/infinityfree.env.example').read_text().replace('ADMIN_PASSWORD=REPLACE_WITH_12_CHAR_PASSWORD','ADMIN_PASSWORD='))
source=sqlite3.connect(root/'storage/app.sqlite')
backup=sqlite3.connect(':memory:');source.backup(backup);source.close()
tables=['users','categories','media','contents','content_media','personnel','settings','revisions','audit_logs','login_attempts','slug_redirects','media_folders','media_folder_items']
password=secrets.token_hex(6)
hashcode=subprocess.check_output(['php','-r','$p=stream_get_contents(STDIN);echo password_hash($p,PASSWORD_DEFAULT);'],input=password.encode()).decode()
admin=backup.execute("SELECT id,email FROM users WHERE role='admin' AND active=1 ORDER BY id LIMIT 1").fetchone()
if not admin:raise RuntimeError('No active admin')
backup.execute('UPDATE users SET password_hash=? WHERE id=?',(hashcode,admin[0]))
backup.execute("UPDATE settings SET value=? WHERE name='site_url'",('https://sdu-public-relations.rf.gd',))
def value(v):
 if v is None:return 'NULL'
 if isinstance(v,(int,float)):return str(v)
 return "CONVERT(X'"+str(v).encode().hex()+"' USING utf8mb4)"
sql=['-- Import into a NEW EMPTY MySQL database only. Contains private user hashes.','SET NAMES utf8mb4;',"SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO';",(root/'database/schema.mysql.sql').read_text(),'START TRANSACTION;']
counts={}
for table in tables:
 rows=backup.execute('SELECT * FROM '+table);columns=[d[0] for d in rows.description];data=rows.fetchall();counts[table]=len(data)
 if table=='login_attempts':continue
 for row in data:sql.append('INSERT INTO `'+table+'` ('+','.join('`'+c+'`' for c in columns)+') VALUES ('+','.join(value(v) for v in row)+');')
sql+=['COMMIT;']
(out/'database.sql').write_text('\n'.join(sql));os.chmod(out/'database.sql',0o600)
(out/'trial-admin.txt').write_text('Trial deployment only; local login unchanged\nEmail: '+admin[1]+'\nPassword: '+password+'\n');os.chmod(out/'trial-admin.txt',0o600)
with zipfile.ZipFile(out/'01-website.zip','w',zipfile.ZIP_DEFLATED) as z:
 for file in site.rglob('*'):
  if file.is_file():z.write(file,str(file.relative_to(site)))
files=[]
for (path,) in backup.execute('SELECT path FROM media ORDER BY id'):
 file=root/'storage/uploads'/Path(path).name
 if not file.is_file():raise RuntimeError('Referenced file missing: '+file.name)
 files.append(file)
batch=[];size=0;batches=[]
for file in files:
 if file.stat().st_size>9*1024*1024:raise RuntimeError('File exceeds package limit')
 if size+file.stat().st_size>8*1024*1024 and batch:batches.append(batch);batch=[];size=0
 batch.append(file);size+=file.stat().st_size
if batch:batches.append(batch)
for n,batch in enumerate(batches,1):
 with zipfile.ZipFile(out/f'photos-{n:03}.zip','w',zipfile.ZIP_DEFLATED) as z:
  for file in batch:z.write(file,'_private/storage/uploads/'+file.name)
(out/'manifest.json').write_text(json.dumps({'domain':'sdu-public-relations.rf.gd','tables':counts,'image_archives':len(batches),'files':len(files),'mysql_tested':False},ensure_ascii=False,indent=2))
for archive in out.glob('*.zip'):
 with zipfile.ZipFile(archive) as z:assert z.testzip() is None
 assert archive.stat().st_size<10*1024*1024
print(json.dumps({'website_package':'01-website.zip','photo_packages':len(batches),'media_files':len(files),'tables':counts,'SQL_MB':round((out/'database.sql').stat().st_size/1024/1024,2)},ensure_ascii=False))
