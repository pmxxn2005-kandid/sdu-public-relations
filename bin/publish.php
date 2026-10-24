<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$count=0;
db()->beginTransaction();
try {
    foreach(all("SELECT id,version FROM contents WHERE status='scheduled' AND published_at<=?",[now()]) as $row){
        $s=query("UPDATE contents SET status='published',version=version+1 WHERE id=? AND version=? AND status='scheduled'",[$row['id'],$row['version']]);
        if($s->rowCount()){audit('scheduled_publish','contents',(int)$row['id']);$count++;}
    }
    query('DELETE FROM login_attempts WHERE created_at<?',[date('Y-m-d H:i:s',time()-86400)]);
    db()->commit();
}catch(Throwable $ex){db()->rollBack();throw $ex;}
echo "$count scheduled items published.\n";
