<?php
declare(strict_types=1);

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(array('ok'=>false,'error'=>'unauthorized'), JSON_UNESCAPED_UNICODE);
    exit;
}

date_default_timezone_set(isset($config['timezone']) ? (string)$config['timezone'] : 'Asia/Almaty');

function pout($data, $status=200) {
    http_response_code((int)$status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function pbody() {
    $raw = defined('GROWTH_CI_BODY') ? GROWTH_CI_BODY : file_get_contents('php://input');
    $x = json_decode($raw ? $raw : '{}', true);
    return is_array($x) ? $x : array();
}
function pcsrf() {
    $sent = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string)$_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    $expected = isset($_SESSION['csrf']) ? (string)$_SESSION['csrf'] : '';
    if (!$sent || !$expected || !hash_equals($expected, $sent)) {
        pout(array('ok'=>false,'error'=>'csrf'),403);
    }
}
function pdate($s) {
    if ($s === null || $s === '') return true;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$s);
    return $d && $d->format('Y-m-d') === (string)$s;
}
function ptext($v, $max=500) {
    $s = trim((string)$v);
    $s = preg_replace('/[ \t]+/u',' ',$s);
    return mb_substr($s,0,(int)$max);
}
function projectById($pdo,$id) {
    $s=$pdo->prepare("SELECT * FROM projects WHERE id=? LIMIT 1");
    $s->execute(array((int)$id));
    $r=$s->fetch();
    return $r ? $r : null;
}
function normalizeStatus($v) {
    $v=(string)$v;
    return in_array($v,array('active','paused','done'),true) ? $v : 'active';
}

if ((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET') !== 'POST') {
    pout(array('ok'=>false,'error'=>'method not allowed'),405);
}
pcsrf();
$x=pbody();
$action=isset($_GET['action']) ? (string)$_GET['action'] : '';

try {
    if ($action==='list') {
        $today=isset($x['date']) ? (string)$x['date'] : date('Y-m-d');
        if (!pdate($today)) throw new RuntimeException('Некорректная дата.');

        $projects=$pdo->query("SELECT * FROM projects ORDER BY status='done', status='paused', COALESCE(deadline,'9999-12-31'), id DESC")->fetchAll();

        $taskStmt=$pdo->query("SELECT id,project_id,title,due_date,status,priority FROM tasks WHERE project_id IS NOT NULL ORDER BY due_date,priority,id");
        $taskRows=$taskStmt->fetchAll();

        $map=array();
        $byProject=array();
        foreach($taskRows as $t){
            $tid=(int)$t['id'];
            $pid=(int)$t['project_id'];
            $map[(string)$tid]=$pid;
            if(!isset($byProject[$pid])) $byProject[$pid]=array();
            $byProject[$pid][]=$t;
        }

        foreach($projects as &$p){
            $pid=(int)$p['id'];
            $list=isset($byProject[$pid]) ? $byProject[$pid] : array();
            $total=count($list); $done=0; $active=0; $overdue=0; $next=null;

            foreach($list as $t){
                if($t['status']==='done'){
                    $done++;
                    continue;
                }
                $active++;
                if((string)$t['due_date'] < $today) $overdue++;
                if($next===null){
                    $next=$t;
                } else {
                    $aOver=((string)$t['due_date'] < $today) ? 0 : 1;
                    $bOver=((string)$next['due_date'] < $today) ? 0 : 1;
                    if(
                        $aOver < $bOver ||
                        ($aOver===$bOver && (
                            (string)$t['due_date'] < (string)$next['due_date'] ||
                            ((string)$t['due_date']===(string)$next['due_date'] && (int)$t['priority'] < (int)$next['priority'])
                        ))
                    ) $next=$t;
                }
            }

            $p['total_tasks']=$total;
            $p['done_tasks']=$done;
            $p['active_tasks']=$active;
            $p['overdue_tasks']=$overdue;
            $p['progress_percent']=$total>0 ? (int)round(($done/$total)*100) : 0;
            $p['next_task']=$next ? array(
                'id'=>(int)$next['id'],
                'title'=>(string)$next['title'],
                'due_date'=>(string)$next['due_date'],
                'priority'=>(int)$next['priority']
            ) : null;
        }
        unset($p);

        pout(array('ok'=>true,'projects'=>$projects,'task_project_map'=>$map));
    }

    if ($action==='get') {
        $id=(int)(isset($x['id'])?$x['id']:0);
        $p=projectById($pdo,$id);
        if(!$p) throw new RuntimeException('Проект не найден.');
        $today=isset($x['date']) && pdate($x['date']) ? (string)$x['date'] : date('Y-m-d');
        $s=$pdo->prepare("SELECT id,title,due_date,status,priority,source_type,completed_at,created_at FROM tasks WHERE project_id=?
            ORDER BY status='done', CASE WHEN status='done' THEN completed_at END DESC, due_date, priority, id");
        $s->execute(array($id));
        $tasks=$s->fetchAll();
        $done=0;$open=0;$overdue=0;$next=null;
        foreach($tasks as &$t){
            $t['id']=(int)$t['id'];$t['priority']=(int)$t['priority'];
            if($t['status']==='done'){$done++;continue;}
            $open++;
            if((string)$t['due_date']<$today)$overdue++;
            if($next===null)$next=$t['id'];
        }
        unset($t);
        $p['id']=(int)$p['id'];
        $p['total_tasks']=count($tasks);$p['done_tasks']=$done;$p['active_tasks']=$open;$p['overdue_tasks']=$overdue;
        $p['progress_percent']=count($tasks)?(int)round($done/count($tasks)*100):0;
        $p['next_task_id']=$next;
        pout(array('ok'=>true,'project'=>$p,'tasks'=>$tasks));
    }

    if ($action==='add_task') {
        $pid=(int)(isset($x['project_id'])?$x['project_id']:0);
        if(!projectById($pdo,$pid)) throw new RuntimeException('Проект не найден.');
        $title=ptext(isset($x['title'])?$x['title']:'',500);
        if($title==='') throw new RuntimeException('Шаг пустой.');
        $due=isset($x['due_date'])?(string)$x['due_date']:'';
        if($due===''||!pdate($due)) $due=date('Y-m-d');
        $prio=(int)(isset($x['priority'])?$x['priority']:3);
        if(!in_array($prio,array(1,2,3),true)) $prio=3;
        $pdo->prepare("INSERT INTO tasks(title,due_date,status,priority,project_id,source_type,sort_order) VALUES(?,?,'pending',?,?,'manual',0)")
            ->execute(array($title,$due,$prio,$pid));
        pout(array('ok'=>true,'id'=>(int)$pdo->lastInsertId()));
    }

    if ($action==='add') {
        $name=ptext(isset($x['name'])?$x['name']:'',180);
        $outcome=ptext(isset($x['outcome'])?$x['outcome']:'',1200);
        $deadline=isset($x['deadline']) ? trim((string)$x['deadline']) : '';
        if($name==='') throw new RuntimeException('Название проекта пустое.');
        if($deadline!=='' && !pdate($deadline)) throw new RuntimeException('Некорректный дедлайн.');

        $s=$pdo->prepare("INSERT INTO projects(name,outcome,deadline,status) VALUES(?,?,?,'active')");
        $s->execute(array($name,$outcome,$deadline!==''?$deadline:null));
        pout(array('ok'=>true,'id'=>(int)$pdo->lastInsertId()));
    }

    if ($action==='update') {
        $id=(int)(isset($x['id'])?$x['id']:0);
        if(!projectById($pdo,$id)) throw new RuntimeException('Проект не найден.');
        $name=ptext(isset($x['name'])?$x['name']:'',180);
        $outcome=ptext(isset($x['outcome'])?$x['outcome']:'',1200);
        $deadline=isset($x['deadline']) ? trim((string)$x['deadline']) : '';
        if($name==='') throw new RuntimeException('Название проекта пустое.');
        if($deadline!=='' && !pdate($deadline)) throw new RuntimeException('Некорректный дедлайн.');

        $s=$pdo->prepare("UPDATE projects SET name=?,outcome=?,deadline=? WHERE id=?");
        $s->execute(array($name,$outcome,$deadline!==''?$deadline:null,$id));
        pout(array('ok'=>true));
    }

    if ($action==='status') {
        $id=(int)(isset($x['id'])?$x['id']:0);
        $status=normalizeStatus(isset($x['status'])?$x['status']:'active');
        if(!projectById($pdo,$id)) throw new RuntimeException('Проект не найден.');

        if($status==='done'){
            $s=$pdo->prepare("UPDATE projects SET status='done',completed_at=COALESCE(completed_at,NOW()) WHERE id=?");
        }else{
            $s=$pdo->prepare("UPDATE projects SET status=?,completed_at=NULL WHERE id=?");
        }
        $s->execute($status==='done' ? array($id) : array($status,$id));
        pout(array('ok'=>true));
    }

    if ($action==='delete') {
        $id=(int)(isset($x['id'])?$x['id']:0);
        if(!projectById($pdo,$id)) throw new RuntimeException('Проект не найден.');
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE tasks SET project_id=NULL WHERE project_id=?")->execute(array($id));
        $pdo->prepare("DELETE FROM projects WHERE id=?")->execute(array($id));
        $pdo->commit();
        pout(array('ok'=>true));
    }

    if ($action==='assign_task') {
        $taskId=(int)(isset($x['task_id'])?$x['task_id']:0);
        $projectId=(int)(isset($x['project_id'])?$x['project_id']:0);

        $s=$pdo->prepare("SELECT id FROM tasks WHERE id=? LIMIT 1");
        $s->execute(array($taskId));
        if(!$s->fetchColumn()) throw new RuntimeException('Задача не найдена.');

        if($projectId>0 && !projectById($pdo,$projectId)) throw new RuntimeException('Проект не найден.');
        $pdo->prepare("UPDATE tasks SET project_id=? WHERE id=?")->execute(array($projectId>0?$projectId:null,$taskId));
        pout(array('ok'=>true));
    }

    pout(array('ok'=>false,'error'=>'unknown action'),400);
} catch(Throwable $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    pout(array('ok'=>false,'error'=>$e->getMessage()),500);
}
