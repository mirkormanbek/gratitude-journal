<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}
date_default_timezone_set((string)($config['timezone'] ?? 'Asia/Almaty'));

function tout(array $x, int $status=200): void {
    http_response_code($status);
    echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function tbody(): array {
    $raw=file_get_contents('php://input');
    $x=json_decode($raw ?: '{}', true);
    return is_array($x)?$x:[];
}
function tcsrf(): void {
    $sent=$_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected=$_SESSION['csrf'] ?? '';
    if(!$sent || !$expected || !hash_equals($expected,$sent)) tout(['ok'=>false,'error'=>'csrf'],403);
}
function tdate(string $s): bool {
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);
    return $d && $d->format('Y-m-d')===$s;
}
function ttitle($v): string {
    $s=trim((string)$v);
    $s=preg_replace('/\s+/u',' ',$s);
    return mb_substr($s,0,500);
}
function tpriority($v): int {
    $p=(int)$v;
    return in_array($p,[1,2,3],true)?$p:3;
}
function cleanTaskLine(string $line): string {
    $line=trim($line);
    $line=preg_replace('/^\s*(?:[-–—•*]+|\d+[\.\)]|\[[ xX✓✔]\])\s*/u','',$line);
    return ttitle($line);
}
function parseTasks(string $text): array {
    $text=str_replace(["\r\n","\r"],"\n",$text);
    $lines=explode("\n",$text);
    $out=[];
    foreach($lines as $line){
        $line=cleanTaskLine($line);
        if($line==='')continue;
        $out[]=$line;
        if(count($out)>=50)break;
    }
    return $out;
}
function stats(PDO $pdo,string $today): array {
    $s=$pdo->prepare("SELECT
      SUM(CASE WHEN due_date=? THEN 1 ELSE 0 END) AS today_total,
      SUM(CASE WHEN due_date=? AND status='done' THEN 1 ELSE 0 END) AS today_done,
      SUM(CASE WHEN due_date=? AND status<>'done' THEN 1 ELSE 0 END) AS today_pending,
      SUM(CASE WHEN due_date<? AND status<>'done' THEN 1 ELSE 0 END) AS overdue
      FROM tasks");
    $s->execute([$today,$today,$today,$today]);
    $r=$s->fetch() ?: [];
    $from=(new DateTimeImmutable($today))->modify('-6 days')->format('Y-m-d');
    $s=$pdo->prepare("SELECT COUNT(*) total,
      SUM(CASE WHEN status='done' THEN 1 ELSE 0 END) done
      FROM tasks WHERE due_date BETWEEN ? AND ?");
    $s->execute([$from,$today]);
    $w=$s->fetch() ?: [];
    $total=(int)($w['total']??0);$done=(int)($w['done']??0);
    return [
      'today_total'=>(int)($r['today_total']??0),
      'today_done'=>(int)($r['today_done']??0),
      'today_pending'=>(int)($r['today_pending']??0),
      'overdue'=>(int)($r['overdue']??0),
      'week_percent'=>$total>0?(int)round(($done/$total)*100):null,
      'week_total'=>$total,
      'week_done'=>$done
    ];
}
function taskById(PDO $pdo,int $id): ?array {
    $s=$pdo->prepare("SELECT * FROM tasks WHERE id=? LIMIT 1");$s->execute([$id]);$r=$s->fetch();
    return $r?:null;
}

if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST') tout(['ok'=>false,'error'=>'method not allowed'],405);
tcsrf();
$x=tbody();
$action=(string)($_GET['action'] ?? '');

try{
    if($action==='list'){
        $today=(string)($x['date']??date('Y-m-d'));
        if(!tdate($today))throw new RuntimeException('Некорректная дата.');
        $from=(new DateTimeImmutable($today))->modify('-60 days')->format('Y-m-d');
        $to=(new DateTimeImmutable($today))->modify('+120 days')->format('Y-m-d');
        $s=$pdo->prepare("SELECT id,title,due_date,status,priority,source_type,source_date,source_line_no,is_rescheduled,completed_at,created_at,updated_at
                         FROM tasks
                         WHERE (status<>'done' AND due_date<=?) OR (due_date BETWEEN ? AND ?)
                         ORDER BY status='done', due_date, priority, id");
        $s->execute([$to,$from,$to]);
        tout(['ok'=>true,'tasks'=>$s->fetchAll(),'stats'=>stats($pdo,$today)]);
    }

    if($action==='add'){
        $title=ttitle($x['title']??'');$due=(string)($x['due_date']??'');$priority=tpriority($x['priority']??3);
        if($title==='')throw new RuntimeException('Задача пустая.');
        if(!tdate($due))throw new RuntimeException('Некорректная дата.');
        $pdo->prepare("INSERT INTO tasks(title,due_date,status,priority,source_type,sort_order) VALUES(?,?,'pending',?,'manual',0)")
            ->execute([$title,$due,$priority]);
        tout(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
    }

    if($action==='update'){
        $id=(int)($x['id']??0);$row=taskById($pdo,$id);
        if(!$row)throw new RuntimeException('Задача не найдена.');
        if(($row['source_type']??'')!=='manual')throw new RuntimeException('Текст этой задачи меняется через исходную запись ежедневника.');
        $title=ttitle($x['title']??'');$due=(string)($x['due_date']??'');$priority=tpriority($x['priority']??3);
        if($title==='')throw new RuntimeException('Задача пустая.');
        if(!tdate($due))throw new RuntimeException('Некорректная дата.');
        $pdo->prepare("UPDATE tasks SET title=?,due_date=?,priority=?,is_rescheduled=1 WHERE id=?")
            ->execute([$title,$due,$priority,$id]);
        tout(['ok'=>true]);
    }

    if($action==='toggle'){
        $id=(int)($x['id']??0);$done=!empty($x['done']);
        if(!taskById($pdo,$id))throw new RuntimeException('Задача не найдена.');
        if($done)$pdo->prepare("UPDATE tasks SET status='done',completed_at=COALESCE(completed_at,NOW()) WHERE id=?")->execute([$id]);
        else $pdo->prepare("UPDATE tasks SET status='pending',completed_at=NULL WHERE id=?")->execute([$id]);
        tout(['ok'=>true]);
    }

    if($action==='priority'){
        $id=(int)($x['id']??0);$priority=tpriority($x['priority']??3);
        if(!taskById($pdo,$id))throw new RuntimeException('Задача не найдена.');
        $pdo->prepare("UPDATE tasks SET priority=? WHERE id=?")->execute([$priority,$id]);
        tout(['ok'=>true]);
    }

    if($action==='move'){
        $id=(int)($x['id']??0);$due=(string)($x['due_date']??'');
        if(!tdate($due))throw new RuntimeException('Некорректная дата.');
        if(!taskById($pdo,$id))throw new RuntimeException('Задача не найдена.');
        $pdo->prepare("UPDATE tasks SET due_date=?,is_rescheduled=1,status='pending',completed_at=NULL WHERE id=?")->execute([$due,$id]);
        tout(['ok'=>true]);
    }

    if($action==='delete'){
        $id=(int)($x['id']??0);
        $pdo->prepare("DELETE FROM tasks WHERE id=?")->execute([$id]);
        tout(['ok'=>true]);
    }

    if($action==='delete_source'){
        $source=(string)($x['source_date']??'');
        if(!tdate($source))throw new RuntimeException('Некорректная дата источника.');
        // Исторически выполненные задачи сохраняем; удаляем только незавершённые.
        $pdo->prepare("DELETE FROM tasks WHERE source_type='daily' AND source_date=? AND status<>'done'")->execute([$source]);
        tout(['ok'=>true]);
    }

    if($action==='sync_daily'){
        $source=(string)($x['source_date']??'');$text=(string)($x['text']??'');
        if(!tdate($source))throw new RuntimeException('Некорректная дата дневника.');
        $due=(new DateTimeImmutable($source))->modify('+1 day')->format('Y-m-d');
        $lines=parseTasks($text);

        $pdo->beginTransaction();
        $s=$pdo->prepare("SELECT * FROM tasks WHERE source_type='daily' AND source_date=? ORDER BY source_line_no,id");
        $s->execute([$source]);$existing=$s->fetchAll();
        $byLine=[];
        foreach($existing as $r){
            $ln=(int)($r['source_line_no']??0);
            if(!isset($byLine[$ln]))$byLine[$ln]=[];
            $byLine[$ln][]=$r;
        }

        $kept=[];$created=0;$updated=0;
        foreach($lines as $i=>$title){
            $lineNo=$i+1;$candidate=null;
            if(isset($byLine[$lineNo])){
                // Предпочитаем незавершённую запись этой строки.
                foreach($byLine[$lineNo] as $r){if(($r['status']??'pending')!=='done'){$candidate=$r;break;}}
                // Если строка уже выполнена и текст тот же — сохраняем как историю без дубликата.
                if(!$candidate){
                    foreach($byLine[$lineNo] as $r){
                        if(cleanTaskLine((string)$r['title'])===cleanTaskLine($title)){$candidate=$r;break;}
                    }
                }
            }
            if($candidate){
                $kept[(int)$candidate['id']]=true;
                if(($candidate['status']??'pending')!=='done'){
                    $newDue=((int)($candidate['is_rescheduled']??0)===1)?(string)$candidate['due_date']:$due;
                    $pdo->prepare("UPDATE tasks SET title=?,due_date=?,sort_order=? WHERE id=?")
                        ->execute([$title,$newDue,$lineNo,(int)$candidate['id']]);
                    $updated++;
                }
            }else{
                $pdo->prepare("INSERT INTO tasks(title,due_date,status,priority,source_type,source_date,source_line_no,sort_order)
                               VALUES(?,?,'pending',3,'daily',?,?,?)")
                    ->execute([$title,$due,$source,$lineNo,$lineNo]);
                $kept[(int)$pdo->lastInsertId()]=true;$created++;
            }
        }

        // Удаляем только старые НЕвыполненные автозадачи, которых больше нет в поле дневника.
        foreach($existing as $r){
            $id=(int)$r['id'];
            if(($r['status']??'pending')!=='done' && !isset($kept[$id])){
                $pdo->prepare("DELETE FROM tasks WHERE id=?")->execute([$id]);
            }
        }
        $pdo->commit();

        $s=$pdo->prepare("SELECT COUNT(*) FROM tasks WHERE source_type='daily' AND source_date=? AND status<>'done'");
        $s->execute([$source]);$active=(int)$s->fetchColumn();
        tout(['ok'=>true,'created'=>$created,'updated'=>$updated,'active'=>$active,'due_date'=>$due]);
    }

    tout(['ok'=>false,'error'=>'unknown action'],400);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    tout(['ok'=>false,'error'=>$e->getMessage()],500);
}
