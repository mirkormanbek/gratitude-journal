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

function wout($data,$status=200){
    http_response_code((int)$status);
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function wbody(){
    $raw=defined('GROWTH_CI_BODY')?GROWTH_CI_BODY:file_get_contents('php://input');
    $x=json_decode($raw ? $raw : '{}',true);
    return is_array($x)?$x:array();
}
function wcsrf(){
    $sent=isset($_SERVER['HTTP_X_CSRF_TOKEN'])?(string)$_SERVER['HTTP_X_CSRF_TOKEN']:'';
    $expected=isset($_SESSION['csrf'])?(string)$_SESSION['csrf']:'';
    if(!$sent||!$expected||!hash_equals($expected,$sent))wout(array('ok'=>false,'error'=>'csrf'),403);
}
function wdate($s){
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$s);
    return $d && $d->format('Y-m-d')===(string)$s;
}
function wmonth($s){
    return preg_match('/^\d{4}-\d{2}$/',(string)$s)===1;
}
function activeProgram($pdo){
    $p=$pdo->query("SELECT * FROM workout_programs WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch();
    if(!$p)throw new RuntimeException('Активная программа тренировок не найдена.');
    return $p;
}
function daysForProgram($pdo,$programId,$withArchived=false){
    $s=$pdo->prepare("SELECT * FROM workout_days WHERE program_id=? ORDER BY day_order");
    $s->execute(array((int)$programId));
    $days=$s->fetchAll();
    foreach($days as &$d){
        $e=$pdo->prepare("SELECT * FROM workout_exercises WHERE day_id=?".($withArchived?"":" AND is_archived=0")." ORDER BY exercise_order,id");
        $e->execute(array((int)$d['id']));
        $d['exercises']=$e->fetchAll();
        $d['exercise_count']=count($d['exercises']);
    }
    unset($d);
    return $days;
}
function scheduleForProgram($pdo,$programId){
    $s=$pdo->prepare("SELECT ws.*,wd.name day_name,wd.day_order
      FROM workout_schedule_slots ws
      JOIN workout_days wd ON wd.id=ws.day_id
      WHERE ws.program_id=? AND ws.is_active=1
      ORDER BY ws.weekday,wd.day_order");
    $s->execute(array((int)$programId));
    $rows=$s->fetchAll();
    foreach($rows as &$r){
        $r['id']=(int)$r['id'];$r['program_id']=(int)$r['program_id'];$r['day_id']=(int)$r['day_id'];$r['weekday']=(int)$r['weekday'];$r['day_order']=(int)$r['day_order'];
    }
    unset($r);
    return $rows;
}
function sessionMini($row){
    if(!$row)return null;
    return array(
        'id'=>(int)$row['id'],
        'scheduled_date'=>(string)$row['scheduled_date'],
        'origin_date'=>(string)(isset($row['origin_date'])&&$row['origin_date']?$row['origin_date']:$row['scheduled_date']),
        'status'=>(string)$row['status'],
        'day_id'=>(int)$row['day_id'],
        'day_name'=>(string)$row['day_name'],
        'is_auto'=>(int)(isset($row['is_auto'])?$row['is_auto']:0)
    );
}
function dateRange($from,$to){
    $out=array();
    $a=new DateTimeImmutable($from);$b=new DateTimeImmutable($to);
    for($d=$a;$d<=$b;$d=$d->modify('+1 day'))$out[]=$d;
    return $out;
}
function ensureScheduleSessions($pdo,$programId,$from,$to){
    if(!wdate($from)||!wdate($to))return;
    $slots=scheduleForProgram($pdo,$programId);
    if(!$slots)return;

    $slotByWeekday=array();
    foreach($slots as $slot){
        $wd=(int)$slot['weekday'];
        $slotByWeekday[$wd]=$slot;
    }

    $check=$pdo->prepare("SELECT id FROM workout_sessions WHERE program_id=? AND COALESCE(origin_date,scheduled_date)=? LIMIT 1");
    $skip=$pdo->prepare("SELECT id FROM workout_schedule_skips WHERE program_id=? AND original_date=? LIMIT 1");
    $insert=$pdo->prepare("INSERT INTO workout_sessions(program_id,day_id,scheduled_date,origin_date,is_auto,status) VALUES(?,?,?,?,1,'planned')");

    foreach(dateRange($from,$to) as $d){
        $weekday=(int)$d->format('N');
        if(!isset($slotByWeekday[$weekday]))continue;
        $slot=$slotByWeekday[$weekday];
        $date=$d->format('Y-m-d');
        if($date < (string)$slot['effective_from'])continue;

        $check->execute(array((int)$programId,$date));
        if($check->fetchColumn())continue;
        $skip->execute(array((int)$programId,$date));
        if($skip->fetchColumn())continue;

        $insert->execute(array((int)$programId,(int)$slot['day_id'],$date,$date));
    }
}
function monthWindow($month){
    $first=new DateTimeImmutable($month.'-01');
    $last=$first->modify('last day of this month');
    $gridStart=$first->modify('-'.((int)$first->format('N')-1).' days');
    $gridEnd=$last->modify('+'.(7-(int)$last->format('N')).' days');
    return array($gridStart->format('Y-m-d'),$gridEnd->format('Y-m-d'),$first->format('Y-m-d'),$last->format('Y-m-d'));
}
function nextDayFallback($pdo,$programId,$days){
    if(!$days)return null;
    $s=$pdo->prepare("SELECT wd.day_order
      FROM workout_sessions ws JOIN workout_days wd ON wd.id=ws.day_id
      WHERE ws.program_id=? AND ws.status='done'
      ORDER BY ws.completed_at DESC,ws.id DESC LIMIT 1");
    $s->execute(array((int)$programId));
    $last=$s->fetchColumn();
    $nextOrder=$last===false?(int)$days[0]['day_order']:(int)$last+1;
    $max=0;foreach($days as $d)$max=max($max,(int)$d['day_order']);
    if($nextOrder>$max)$nextOrder=(int)$days[0]['day_order'];
    foreach($days as $d)if((int)$d['day_order']===$nextOrder)return $d;
    return $days[0];
}
function summary($pdo,$today,$month){
    $program=activeProgram($pdo);
    $pid=(int)$program['id'];
    $days=daysForProgram($pdo,$pid);
    $schedule=scheduleForProgram($pdo,$pid);
    list($gridFrom,$gridTo,$monthFrom,$monthTo)=monthWindow($month);

    $ensureFrom=$gridFrom;
    $past=(new DateTimeImmutable($today))->modify('-14 days')->format('Y-m-d');
    if($past<$ensureFrom)$ensureFrom=$past;
    $ensureTo=$gridTo;
    $future=(new DateTimeImmutable($today))->modify('+60 days')->format('Y-m-d');
    if($future>$ensureTo)$ensureTo=$future;
    ensureScheduleSessions($pdo,$pid,$ensureFrom,$ensureTo);

    $s=$pdo->prepare("SELECT ws.*,wd.name day_name,wd.day_order
      FROM workout_sessions ws JOIN workout_days wd ON wd.id=ws.day_id
      WHERE ws.program_id=? AND ws.status='in_progress'
      ORDER BY ws.started_at DESC,ws.id DESC LIMIT 1");
    $s->execute(array($pid));$inprog=$s->fetch();

    $s=$pdo->prepare("SELECT ws.*,wd.name day_name,wd.day_order
      FROM workout_sessions ws JOIN workout_days wd ON wd.id=ws.day_id
      WHERE ws.program_id=? AND ws.status='planned' AND ws.scheduled_date=?
      ORDER BY ws.id LIMIT 1");
    $s->execute(array($pid,$today));$todaySession=$s->fetch();

    $s=$pdo->prepare("SELECT ws.*,wd.name day_name,wd.day_order
      FROM workout_sessions ws JOIN workout_days wd ON wd.id=ws.day_id
      WHERE ws.program_id=? AND ws.status='planned' AND ws.scheduled_date>=?
      ORDER BY ws.scheduled_date,wd.day_order,ws.id LIMIT 1");
    $s->execute(array($pid,$today));$nextSession=$s->fetch();

    $s=$pdo->prepare("SELECT ws.*,wd.name day_name,wd.day_order
      FROM workout_sessions ws JOIN workout_days wd ON wd.id=ws.day_id
      WHERE ws.program_id=? AND ws.status='planned' AND ws.scheduled_date<?
      ORDER BY ws.scheduled_date,wd.day_order,ws.id");
    $s->execute(array($pid,$today));$overdue=$s->fetchAll();
    foreach($overdue as &$o)$o=sessionMini($o);unset($o);

    $from28=(new DateTimeImmutable($today))->modify('-27 days')->format('Y-m-d');
    $s=$pdo->prepare("SELECT COUNT(*) FROM workout_sessions WHERE program_id=? AND status='done' AND scheduled_date BETWEEN ? AND ?");
    $s->execute(array($pid,$from28,$today));$last28=(int)$s->fetchColumn();

    $s=$pdo->prepare("SELECT ws.id,ws.scheduled_date,ws.origin_date,ws.started_at,ws.completed_at,ws.is_auto,wd.name day_name,
      (SELECT COUNT(*) FROM workout_sets x WHERE x.session_id=ws.id AND x.completed_at IS NOT NULL) completed_sets
      FROM workout_sessions ws JOIN workout_days wd ON wd.id=ws.day_id
      WHERE ws.program_id=? AND ws.status='done'
      ORDER BY ws.completed_at DESC,ws.id DESC LIMIT 12");
    $s->execute(array($pid));$history=$s->fetchAll();
    foreach($history as &$h){
        $mins=null;
        if(!empty($h['started_at'])&&!empty($h['completed_at'])){
            $a=strtotime($h['started_at']);$b=strtotime($h['completed_at']);
            if($a&&$b&&$b>=$a)$mins=(int)round(($b-$a)/60);
        }
        $h['id']=(int)$h['id'];$h['duration_minutes']=$mins;$h['completed_sets']=(int)$h['completed_sets'];$h['is_auto']=(int)$h['is_auto'];
    }
    unset($h);

    $s=$pdo->prepare("SELECT ws.id,ws.scheduled_date,ws.origin_date,ws.status,ws.is_auto,wd.name day_name,wd.day_order
      FROM workout_sessions ws JOIN workout_days wd ON wd.id=ws.day_id
      WHERE ws.program_id=? AND ws.scheduled_date BETWEEN ? AND ?
      ORDER BY ws.scheduled_date,wd.day_order,ws.id");
    $s->execute(array($pid,$gridFrom,$gridTo));$calendar=$s->fetchAll();
    foreach($calendar as &$c){
        $c['id']=(int)$c['id'];$c['day_order']=(int)$c['day_order'];$c['is_auto']=(int)$c['is_auto'];
    }
    unset($c);

    $agendaTo=(new DateTimeImmutable($today))->modify('+6 days')->format('Y-m-d');
    ensureScheduleSessions($pdo,$pid,$today,$agendaTo);
    $s=$pdo->prepare("SELECT ws.id,ws.scheduled_date,ws.status,wd.name day_name,wd.day_order
      FROM workout_sessions ws JOIN workout_days wd ON wd.id=ws.day_id
      WHERE ws.program_id=? AND ws.scheduled_date BETWEEN ? AND ?
      ORDER BY ws.scheduled_date,wd.day_order,ws.id");
    $s->execute(array($pid,$today,$agendaTo));$agenda=$s->fetchAll();
    foreach($agenda as &$a){$a['id']=(int)$a['id'];$a['day_order']=(int)$a['day_order'];}unset($a);

    $nextDay=null;
    if($nextSession){
        foreach($days as $d)if((int)$d['id']===(int)$nextSession['day_id']){$nextDay=$d;break;}
    }
    if(!$nextDay)$nextDay=nextDayFallback($pdo,$pid,$days);

    return array(
        'program'=>array('id'=>$pid,'name'=>(string)$program['name'],'target_per_week'=>(int)$program['target_per_week']),
        'days'=>$days,
        'schedule'=>$schedule,
        'next_day'=>$nextDay,
        'planned_session'=>sessionMini($nextSession),
        'today_session'=>sessionMini($todaySession),
        'in_progress'=>sessionMini($inprog),
        'overdue_sessions'=>$overdue,
        'agenda_sessions'=>$agenda,
        'calendar'=>$calendar,
        'calendar_month'=>$month,
        'calendar_from'=>$gridFrom,
        'calendar_to'=>$gridTo,
        'stats'=>array('last_28_days'=>$last28,'target_28_days'=>(int)$program['target_per_week']*4),
        'history'=>$history
    );
}
function getSession($pdo,$id){
    $s=$pdo->prepare("SELECT ws.*,wd.name day_name,wd.day_order
      FROM workout_sessions ws JOIN workout_days wd ON wd.id=ws.day_id
      WHERE ws.id=? LIMIT 1");
    $s->execute(array((int)$id));$session=$s->fetch();
    if(!$session)throw new RuntimeException('Тренировка не найдена.');

    // Active exercises of the day + archived ones that already have sets in this session.
    $e=$pdo->prepare("SELECT * FROM workout_exercises e WHERE e.day_id=? AND (e.is_archived=0 OR EXISTS(SELECT 1 FROM workout_sets x WHERE x.session_id=? AND x.exercise_id=e.id)) ORDER BY e.exercise_order,e.id");
    $e->execute(array((int)$session['day_id'],(int)$id));$exercises=$e->fetchAll();
    $before=$session['completed_at']?$session['completed_at']:($session['started_at']?$session['started_at']:date('Y-m-d H:i:s'));
    foreach($exercises as &$ex){
        $c=$pdo->prepare("SELECT set_no,weight_kg,reps,completed_at FROM workout_sets WHERE session_id=? AND exercise_id=? ORDER BY set_no");
        $c->execute(array((int)$id,(int)$ex['id']));$ex['sets']=$c->fetchAll();

        // Previous result = last finished session with this exercise NAME (any day of the program).
        $ids=exerciseIdsByName($pdo,(string)$ex['name']);
        $ex['previous_sets']=array();$ex['previous_date']=null;$ex['best']=null;
        if($ids){
            $in=implode(',',array_map('intval',$ids));
            $p=$pdo->prepare("SELECT ws2.id,ws2.scheduled_date FROM workout_sessions ws2
              WHERE ws2.status='done' AND ws2.id<>? AND ws2.completed_at <= ?
                AND EXISTS(SELECT 1 FROM workout_sets x WHERE x.session_id=ws2.id AND x.exercise_id IN ($in) AND x.completed_at IS NOT NULL)
              ORDER BY ws2.completed_at DESC,ws2.id DESC LIMIT 1");
            $p->execute(array((int)$id,$before));
            $prev=$p->fetch();
            if($prev){
                $ps=$pdo->prepare("SELECT set_no,weight_kg,reps FROM workout_sets WHERE session_id=? AND exercise_id IN ($in) AND completed_at IS NOT NULL ORDER BY set_no");
                $ps->execute(array((int)$prev['id']));$ex['previous_sets']=$ps->fetchAll();
                $ex['previous_date']=(string)$prev['scheduled_date'];
            }
            $b=$pdo->prepare("SELECT x.weight_kg,x.reps FROM workout_sets x JOIN workout_sessions s ON s.id=x.session_id
              WHERE x.exercise_id IN ($in) AND x.completed_at IS NOT NULL AND s.id<>? AND x.weight_kg IS NOT NULL
              ORDER BY x.weight_kg DESC,x.reps DESC LIMIT 1");
            $b->execute(array((int)$id));$best=$b->fetch();
            if($best)$ex['best']=array('weight_kg'=>(float)$best['weight_kg'],'reps'=>(int)$best['reps']);
        }
        $ex['rest_seconds']=isset($ex['rest_seconds'])&&$ex['rest_seconds']!==null?(int)$ex['rest_seconds']:90;
    }
    unset($ex);
    $session['id']=(int)$session['id'];$session['exercises']=$exercises;
    return $session;
}
function exerciseKey($name){
    $n=mb_strtolower(trim((string)$name),'UTF-8');
    $n=str_replace('ё','е',$n);
    return preg_replace('/\s+/u',' ',$n);
}
function exerciseIdsByName($pdo,$name){
    static $all=null;
    if($all===null){$all=$pdo->query("SELECT id,name FROM workout_exercises")->fetchAll();}
    $key=exerciseKey($name);$ids=array();
    foreach($all as $r)if(exerciseKey($r['name'])===$key)$ids[]=(int)$r['id'];
    return $ids;
}
/** Epley estimated 1RM; reps capped at 15 to keep high-rep sets sane. */
function e1rm($w,$r){
    if($w===null||$w<=0||$r<=0)return 0.0;
    return round((float)$w*(1+min((int)$r,15)/30),1);
}
function sessionDuration($a,$b){
    if(!$a||!$b)return null;$x=strtotime($a);$y=strtotime($b);
    if(!$x||!$y||$y<$x)return null;$m=(int)round(($y-$x)/60);
    return $m>0&&$m<600?$m:null;
}
function workoutStats($pdo,$today){
    $program=activeProgram($pdo);$pid=(int)$program['id'];$target=(int)$program['target_per_week'];
    $rows=$pdo->prepare("SELECT s.id,s.scheduled_date,s.started_at,s.completed_at,d.name day_name,
        x.exercise_id,e.name ex_name,x.set_no,x.weight_kg,x.reps
      FROM workout_sessions s JOIN workout_days d ON d.id=s.day_id
      LEFT JOIN workout_sets x ON x.session_id=s.id AND x.completed_at IS NOT NULL
      LEFT JOIN workout_exercises e ON e.id=x.exercise_id
      WHERE s.program_id=? AND s.status='done'
      ORDER BY s.scheduled_date,s.id,x.exercise_id,x.set_no");
    $rows->execute(array($pid));
    $sessions=array();$ex=array();
    foreach($rows->fetchAll() as $r){
        $sid=(int)$r['id'];
        if(!isset($sessions[$sid]))$sessions[$sid]=array('id'=>$sid,'date'=>(string)$r['scheduled_date'],'day_name'=>(string)$r['day_name'],
            'minutes'=>sessionDuration($r['started_at'],$r['completed_at']),'sets'=>0,'volume'=>0.0,'reps'=>0,'prs'=>0);
        if($r['exercise_id']===null)continue;
        $w=$r['weight_kg']!==null?(float)$r['weight_kg']:null;$reps=(int)$r['reps'];
        $sessions[$sid]['sets']++;$sessions[$sid]['reps']+=$reps;
        if($w!==null)$sessions[$sid]['volume']+=$w*$reps;
        $key=exerciseKey($r['ex_name']);
        if(!isset($ex[$key]))$ex[$key]=array('name'=>trim((string)$r['ex_name']),'points'=>array());
        if(!isset($ex[$key]['points'][$sid]))$ex[$key]['points'][$sid]=array('session_id'=>$sid,'date'=>(string)$r['scheduled_date'],'top_weight'=>null,'top_reps'=>0,'e1rm'=>0.0,'volume'=>0.0,'sets'=>array());
        $pt=&$ex[$key]['points'][$sid];
        $pt['sets'][]=array('w'=>$w,'r'=>$reps);
        if($w!==null){$pt['volume']+=$w*$reps;if($pt['top_weight']===null||$w>$pt['top_weight']||($w==$pt['top_weight']&&$reps>$pt['top_reps'])){$pt['top_weight']=$w;$pt['top_reps']=$reps;}}
        else if($reps>$pt['top_reps']&&$pt['top_weight']===null)$pt['top_reps']=$reps;
        $pt['e1rm']=max($pt['e1rm'],e1rm($w,$reps));
        unset($pt);
    }
    // Per exercise: trend, best, PR detection
    $exercises=array();
    foreach($ex as $key=>$e){
        $pts=array_values($e['points']);$best=0.0;$bestW=null;$bestReps=0;$prCount=0;
        foreach($pts as $i=>$p){
            $score=$p['e1rm']>0?$p['e1rm']:(float)$p['top_reps'];
            $isPr=$i>0&&$score>$best+0.001;
            if($isPr){$prCount++;$sessions[$p['session_id']]['prs']++;}
            $pts[$i]['pr']=$isPr;
            if($score>$best)$best=$score;
            if($p['top_weight']!==null&&($bestW===null||$p['top_weight']>$bestW))$bestW=$p['top_weight'];
            if($p['top_reps']>$bestReps)$bestReps=$p['top_reps'];
        }
        $last=end($pts);$first=reset($pts);
        $exercises[]=array('key'=>$key,'name'=>$e['name'],'sessions'=>count($pts),'last_date'=>$last['date'],
            'best_weight'=>$bestW,'best_reps'=>$bestReps,'best_e1rm'=>$best,
            'last_top_weight'=>$last['top_weight'],'last_top_reps'=>$last['top_reps'],'last_pr'=>!empty($last['pr']),
            'change_e1rm'=>($first['e1rm']>0&&$last['e1rm']>0)?round($last['e1rm']-$first['e1rm'],1):null,
            'bodyweight'=>$bestW===null,'prs'=>$prCount,'points'=>$pts);
    }
    usort($exercises,function($a,$b){return strcmp($b['last_date'],$a['last_date'])?:strcmp($a['name'],$b['name']);});

    // Weekly buckets (Mon..Sun), last 12 weeks
    $mon=(new DateTimeImmutable($today))->modify('monday this week');
    $weeks=array();
    for($i=11;$i>=0;$i--){$ws=$mon->modify("-$i weeks")->format('Y-m-d');$weeks[$ws]=array('week'=>$ws,'count'=>0,'volume'=>0.0,'sets'=>0);}
    $sessionList=array_values($sessions);
    foreach($sessionList as $s){
        $ws=(new DateTimeImmutable($s['date']))->modify('monday this week')->format('Y-m-d');
        if(isset($weeks[$ws])){$weeks[$ws]['count']++;$weeks[$ws]['volume']+=$s['volume'];$weeks[$ws]['sets']+=$s['sets'];}
    }
    // Streak: consecutive full weeks (before the current one) meeting the target; current week counts if already met
    $streak=0;$wk=array_values($weeks);
    for($i=count($wk)-1;$i>=0;$i--){
        $isCurrent=$i===count($wk)-1;
        if($wk[$i]['count']>=$target){$streak++;continue;}
        if($isCurrent)continue;
        break;
    }
    $month=substr($today,0,7);$monthCount=0;$totalVol=0.0;$totalSets=0;$mins=array();
    foreach($sessionList as $s){if(substr($s['date'],0,7)===$month)$monthCount++;$totalVol+=$s['volume'];$totalSets+=$s['sets'];if($s['minutes'])$mins[]=$s['minutes'];}
    $from28=(new DateTimeImmutable($today))->modify('-27 days')->format('Y-m-d');$c28=0;
    foreach($sessionList as $s)if($s['date']>=$from28&&$s['date']<=$today)$c28++;

    return array(
        'target_per_week'=>$target,
        'totals'=>array('workouts'=>count($sessionList),'sets'=>$totalSets,'volume'=>round($totalVol),'month'=>$monthCount,
            'avg_minutes'=>$mins?(int)round(array_sum($mins)/count($mins)):null,'week_streak'=>$streak,
            'last_28'=>$c28,'target_28'=>$target*4,'prs'=>array_sum(array_map(function($e){return $e['prs'];},$exercises))),
        'weeks'=>array_values($weeks),
        'sessions'=>array_reverse($sessionList),
        'exercises'=>$exercises
    );
}
function scheduleWeekdayMap($pdo,$programId){
    $rows=scheduleForProgram($pdo,$programId);$m=array();
    foreach($rows as $r)$m[(int)$r['day_id']]=(int)$r['weekday'];
    return $m;
}
function isScheduledOrigin($pdo,$programId,$dayId,$originDate){
    if(!$originDate||!wdate($originDate))return false;
    $wd=(int)(new DateTimeImmutable($originDate))->format('N');
    $s=$pdo->prepare("SELECT id FROM workout_schedule_slots WHERE program_id=? AND day_id=? AND weekday=? AND is_active=1 AND effective_from<=? LIMIT 1");
    $s->execute(array((int)$programId,(int)$dayId,$wd,$originDate));
    return (bool)$s->fetchColumn();
}

if((isset($_SERVER['REQUEST_METHOD'])?$_SERVER['REQUEST_METHOD']:'GET')!=='POST')wout(array('ok'=>false,'error'=>'method not allowed'),405);
wcsrf();$x=wbody();$action=isset($_GET['action'])?(string)$_GET['action']:'';

try{
    if($action==='summary'){
        $today=isset($x['date'])?(string)$x['date']:date('Y-m-d');
        $month=isset($x['month'])?(string)$x['month']:substr($today,0,7);
        if(!wdate($today)||!wmonth($month))throw new RuntimeException('Некорректная дата.');
        wout(array('ok'=>true,'summary'=>summary($pdo,$today,$month)));
    }

    if($action==='save_schedule'){
        $program=activeProgram($pdo);$pid=(int)$program['id'];
        $items=isset($x['items'])&&is_array($x['items'])?$x['items']:array();
        if(!$items)throw new RuntimeException('Расписание пустое.');
        $used=array();$clean=array();
        foreach($items as $it){
            $dayId=(int)(isset($it['day_id'])?$it['day_id']:0);$weekday=(int)(isset($it['weekday'])?$it['weekday']:0);
            if($dayId<=0||$weekday<1||$weekday>7)throw new RuntimeException('Некорректное расписание.');
            if(isset($used[$weekday]))throw new RuntimeException('На один день недели назначено две тренировки.');
            $used[$weekday]=true;$clean[]=array($dayId,$weekday);
        }
        $valid=$pdo->prepare("SELECT COUNT(*) FROM workout_days WHERE program_id=? AND id=?");
        foreach($clean as $it){$valid->execute(array($pid,$it[0]));if((int)$valid->fetchColumn()!==1)throw new RuntimeException('Неизвестный тренировочный день.');}

        $today=date('Y-m-d');
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM workout_schedule_slots WHERE program_id=?")->execute(array($pid));
        $ins=$pdo->prepare("INSERT INTO workout_schedule_slots(program_id,day_id,weekday,effective_from,is_active) VALUES(?,?,?,?,1)");
        foreach($clean as $it)$ins->execute(array($pid,$it[0],$it[1],$today));
        $pdo->prepare("DELETE ws FROM workout_sessions ws WHERE ws.program_id=? AND ws.status='planned' AND ws.is_auto=1 AND COALESCE(ws.origin_date,ws.scheduled_date)>=?")->execute(array($pid,$today));
        $pdo->prepare("DELETE FROM workout_schedule_skips WHERE program_id=? AND original_date>=?")->execute(array($pid,$today));
        $pdo->commit();
        ensureScheduleSessions($pdo,$pid,$today,(new DateTimeImmutable($today))->modify('+60 days')->format('Y-m-d'));
        wout(array('ok'=>true));
    }

    if($action==='move'){
        $id=(int)(isset($x['id'])?$x['id']:0);$date=isset($x['date'])?(string)$x['date']:'';
        if($id<=0||!wdate($date))throw new RuntimeException('Некорректные данные переноса.');
        $s=$pdo->prepare("SELECT * FROM workout_sessions WHERE id=? LIMIT 1");$s->execute(array($id));$row=$s->fetch();
        if(!$row)throw new RuntimeException('Тренировка не найдена.');
        if((string)$row['status']==='done')throw new RuntimeException('Завершённую тренировку сначала удали, если это тестовая запись.');
        $c=$pdo->prepare("SELECT id FROM workout_sessions WHERE program_id=? AND scheduled_date=? AND id<>? AND status IN ('planned','in_progress') LIMIT 1");
        $c->execute(array((int)$row['program_id'],$date,$id));
        if($c->fetchColumn())throw new RuntimeException('На эту дату уже назначена тренировка.');
        $origin=!empty($row['origin_date'])?(string)$row['origin_date']:(string)$row['scheduled_date'];
        $pdo->prepare("UPDATE workout_sessions SET scheduled_date=?,origin_date=? WHERE id=?")->execute(array($date,$origin,$id));
        wout(array('ok'=>true));
    }

    if($action==='delete'){
        $id=(int)(isset($x['id'])?$x['id']:0);
        if($id<=0)throw new RuntimeException('Некорректная тренировка.');
        $s=$pdo->prepare("SELECT * FROM workout_sessions WHERE id=? LIMIT 1");$s->execute(array($id));$row=$s->fetch();
        if(!$row)throw new RuntimeException('Тренировка не найдена.');
        $origin=!empty($row['origin_date'])?(string)$row['origin_date']:(string)$row['scheduled_date'];
        $pdo->beginTransaction();
        if(isScheduledOrigin($pdo,(int)$row['program_id'],(int)$row['day_id'],$origin)){
            $sql="INSERT INTO workout_schedule_skips(program_id,original_date,reason) VALUES(?,?,'deleted') ON DUPLICATE KEY UPDATE reason='deleted'";
            $pdo->prepare($sql)->execute(array((int)$row['program_id'],$origin));
        }
        $pdo->prepare("DELETE FROM workout_sets WHERE session_id=?")->execute(array($id));
        $pdo->prepare("DELETE FROM workout_sessions WHERE id=?")->execute(array($id));
        $pdo->commit();
        wout(array('ok'=>true));
    }

    if($action==='plan_next'){
        $date=isset($x['date'])?(string)$x['date']:date('Y-m-d');
        if(!wdate($date))throw new RuntimeException('Некорректная дата.');
        $program=activeProgram($pdo);$pid=(int)$program['id'];$days=daysForProgram($pdo,$pid);$next=nextDayFallback($pdo,$pid,$days);
        if(!$next)throw new RuntimeException('В программе нет тренировочных дней.');
        $pdo->prepare("INSERT INTO workout_sessions(program_id,day_id,scheduled_date,origin_date,is_auto,status) VALUES(?,?,?,?,0,'planned')")
            ->execute(array($pid,(int)$next['id'],$date,$date));
        wout(array('ok'=>true,'session_id'=>(int)$pdo->lastInsertId()));
    }

    if($action==='start'){
        $program=activeProgram($pdo);$pid=(int)$program['id'];
        $id=(int)(isset($x['session_id'])?$x['session_id']:0);
        $date=isset($x['date'])?(string)$x['date']:date('Y-m-d');
        if(!wdate($date))throw new RuntimeException('Некорректная дата.');

        $s=$pdo->prepare("SELECT id FROM workout_sessions WHERE program_id=? AND status='in_progress' ORDER BY started_at DESC,id DESC LIMIT 1");
        $s->execute(array($pid));$running=$s->fetchColumn();
        if($running)wout(array('ok'=>true,'session_id'=>(int)$running,'existing'=>true));

        if($id>0){
            $s=$pdo->prepare("SELECT id FROM workout_sessions WHERE id=? AND program_id=? AND status='planned' LIMIT 1");$s->execute(array($id,$pid));
            if(!$s->fetchColumn())throw new RuntimeException('Запланированная тренировка не найдена.');
        }else{
            ensureScheduleSessions($pdo,$pid,$date,$date);
            $s=$pdo->prepare("SELECT id FROM workout_sessions WHERE program_id=? AND status='planned' AND scheduled_date=? ORDER BY id LIMIT 1");
            $s->execute(array($pid,$date));$id=(int)$s->fetchColumn();
            if(!$id){
                $s=$pdo->prepare("SELECT id FROM workout_sessions WHERE program_id=? AND status='planned' AND scheduled_date<? ORDER BY scheduled_date,id LIMIT 1");
                $s->execute(array($pid,$date));$id=(int)$s->fetchColumn();
            }
        }
        if(!$id)throw new RuntimeException('На сегодня нет запланированной тренировки. Выбери дату в календаре.');
        $pdo->prepare("UPDATE workout_sessions SET status='in_progress',started_at=COALESCE(started_at,NOW()) WHERE id=?")->execute(array($id));
        wout(array('ok'=>true,'session_id'=>$id));
    }

    if($action==='session'){
        $id=(int)(isset($x['id'])?$x['id']:0);
        wout(array('ok'=>true,'session'=>getSession($pdo,$id)));
    }

    if($action==='save_set'){
        $sid=(int)(isset($x['session_id'])?$x['session_id']:0);
        $eid=(int)(isset($x['exercise_id'])?$x['exercise_id']:0);
        $setno=(int)(isset($x['set_no'])?$x['set_no']:0);
        $weight=isset($x['weight_kg'])&&$x['weight_kg']!==null&&$x['weight_kg']!==''?(float)$x['weight_kg']:null;
        $reps=(int)(isset($x['reps'])?$x['reps']:0);
        if($sid<=0||$eid<=0||$setno<=0||$reps<=0)throw new RuntimeException('Некорректные данные подхода.');
        $s=$pdo->prepare("SELECT id FROM workout_sessions WHERE id=? AND status='in_progress' LIMIT 1");$s->execute(array($sid));
        if(!$s->fetchColumn())throw new RuntimeException('Тренировка не активна.');
        $sql="INSERT INTO workout_sets(session_id,exercise_id,set_no,weight_kg,reps,completed_at)
              VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE weight_kg=VALUES(weight_kg),reps=VALUES(reps),completed_at=NOW()";
        $pdo->prepare($sql)->execute(array($sid,$eid,$setno,$weight,$reps));
        wout(array('ok'=>true));
    }

    if($action==='complete'){
        $sid=(int)(isset($x['session_id'])?$x['session_id']:0);
        $s=$pdo->prepare("SELECT id FROM workout_sessions WHERE id=? AND status='in_progress' LIMIT 1");$s->execute(array($sid));
        if(!$s->fetchColumn())throw new RuntimeException('Тренировка не активна.');
        $pdo->prepare("UPDATE workout_sessions SET status='done',completed_at=NOW() WHERE id=?")->execute(array($sid));
        wout(array('ok'=>true));
    }

    if($action==='delete_set'){
        $sid=(int)(isset($x['session_id'])?$x['session_id']:0);
        $eid=(int)(isset($x['exercise_id'])?$x['exercise_id']:0);
        $setno=(int)(isset($x['set_no'])?$x['set_no']:0);
        $s=$pdo->prepare("SELECT id FROM workout_sessions WHERE id=? AND status='in_progress' LIMIT 1");$s->execute(array($sid));
        if(!$s->fetchColumn())throw new RuntimeException('Тренировка не активна.');
        $pdo->prepare("DELETE FROM workout_sets WHERE session_id=? AND exercise_id=? AND set_no=?")->execute(array($sid,$eid,$setno));
        wout(array('ok'=>true));
    }

    if($action==='stats'){
        $today=isset($x['date'])?(string)$x['date']:date('Y-m-d');
        if(!wdate($today))throw new RuntimeException('Некорректная дата.');
        wout(array('ok'=>true,'stats'=>workoutStats($pdo,$today)));
    }

    // ----- Program editor -----
    if($action==='program'){
        $program=activeProgram($pdo);$pid=(int)$program['id'];
        $days=daysForProgram($pdo,$pid,true);
        $used=$pdo->query("SELECT exercise_id,COUNT(*) n FROM workout_sets GROUP BY exercise_id")->fetchAll();
        $usedMap=array();foreach($used as $u)$usedMap[(int)$u['exercise_id']]=(int)$u['n'];
        foreach($days as &$d){
            $d['id']=(int)$d['id'];
            foreach($d['exercises'] as &$e){
                $e['id']=(int)$e['id'];$e['is_archived']=(int)$e['is_archived'];$e['sets_logged']=isset($usedMap[$e['id']])?$usedMap[$e['id']]:0;
                $e['rest_seconds']=$e['rest_seconds']!==null?(int)$e['rest_seconds']:null;
            }
            unset($e);
        }
        unset($d);
        wout(array('ok'=>true,'program'=>array('id'=>$pid,'name'=>(string)$program['name'],'target_per_week'=>(int)$program['target_per_week']),'days'=>$days,'schedule'=>scheduleForProgram($pdo,$pid)));
    }

    if($action==='exercise_save'){
        $program=activeProgram($pdo);$pid=(int)$program['id'];
        $id=(int)(isset($x['id'])?$x['id']:0);
        $dayId=(int)(isset($x['day_id'])?$x['day_id']:0);
        $name=trim(preg_replace('/\s+/u',' ',(string)(isset($x['name'])?$x['name']:'')));
        $sets=(int)(isset($x['target_sets'])?$x['target_sets']:3);
        $reps=mb_substr(trim((string)(isset($x['target_reps'])?$x['target_reps']:'')),0,60);
        $weight=isset($x['default_weight'])&&$x['default_weight']!==''&&$x['default_weight']!==null?(float)str_replace(',','.',(string)$x['default_weight']):null;
        $rest=isset($x['rest_seconds'])&&$x['rest_seconds']!==''&&$x['rest_seconds']!==null?(int)$x['rest_seconds']:null;
        $notes=mb_substr(trim((string)(isset($x['notes'])?$x['notes']:'')),0,500);
        if($name==='')throw new RuntimeException('Название упражнения пустое.');
        $name=mb_substr($name,0,220);
        if($sets<1||$sets>12)throw new RuntimeException('Подходов должно быть от 1 до 12.');
        if($weight!==null&&($weight<0||$weight>1000))throw new RuntimeException('Некорректный вес.');
        if($rest!==null&&($rest<15||$rest>900))throw new RuntimeException('Отдых — от 15 до 900 секунд.');
        $chk=$pdo->prepare("SELECT id FROM workout_days WHERE id=? AND program_id=?");$chk->execute(array($dayId,$pid));
        if(!$chk->fetchColumn())throw new RuntimeException('Тренировочный день не найден.');
        if($id>0){
            $s=$pdo->prepare("SELECT day_id FROM workout_exercises WHERE id=?");$s->execute(array($id));$cur=$s->fetchColumn();
            if($cur===false)throw new RuntimeException('Упражнение не найдено.');
            $pdo->prepare("UPDATE workout_exercises SET name=?,target_sets=?,target_reps=?,default_weight=?,rest_seconds=?,notes=? WHERE id=?")
                ->execute(array($name,$sets,$reps!==''?$reps:null,$weight,$rest,$notes!==''?$notes:null,$id));
        }else{
            $s=$pdo->prepare("SELECT COALESCE(MAX(exercise_order),0)+1 FROM workout_exercises WHERE day_id=?");$s->execute(array($dayId));$ord=(int)$s->fetchColumn();
            $pdo->prepare("INSERT INTO workout_exercises(day_id,exercise_order,name,target_sets,target_reps,default_weight,rest_seconds,notes,is_archived) VALUES(?,?,?,?,?,?,?,?,0)")
                ->execute(array($dayId,$ord,$name,$sets,$reps!==''?$reps:null,$weight,$rest,$notes!==''?$notes:null));
            $id=(int)$pdo->lastInsertId();
        }
        wout(array('ok'=>true,'id'=>$id));
    }

    if($action==='exercise_archive'||$action==='exercise_restore'){
        $id=(int)(isset($x['id'])?$x['id']:0);
        $s=$pdo->prepare("SELECT id FROM workout_exercises WHERE id=?");$s->execute(array($id));
        if(!$s->fetchColumn())throw new RuntimeException('Упражнение не найдено.');
        $pdo->prepare("UPDATE workout_exercises SET is_archived=? WHERE id=?")->execute(array($action==='exercise_archive'?1:0,$id));
        wout(array('ok'=>true));
    }

    if($action==='exercise_move'){
        $id=(int)(isset($x['id'])?$x['id']:0);$dir=(int)(isset($x['dir'])?$x['dir']:0)<0?-1:1;
        $s=$pdo->prepare("SELECT id,day_id FROM workout_exercises WHERE id=?");$s->execute(array($id));$row=$s->fetch();
        if(!$row)throw new RuntimeException('Упражнение не найдено.');
        $s=$pdo->prepare("SELECT id FROM workout_exercises WHERE day_id=? AND is_archived=0 ORDER BY exercise_order,id");$s->execute(array((int)$row['day_id']));
        $ids=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));
        $pos=array_search($id,$ids,true);
        if($pos!==false){
            $to=$pos+$dir;
            if($to>=0&&$to<count($ids)){$tmp=$ids[$to];$ids[$to]=$ids[$pos];$ids[$pos]=$tmp;}
            $u=$pdo->prepare("UPDATE workout_exercises SET exercise_order=? WHERE id=?");
            $pdo->beginTransaction();foreach($ids as $i=>$eid)$u->execute(array($i+1,$eid));$pdo->commit();
        }
        wout(array('ok'=>true));
    }

    if($action==='day_rename'){
        $program=activeProgram($pdo);$pid=(int)$program['id'];
        $id=(int)(isset($x['id'])?$x['id']:0);
        $name=mb_substr(trim(preg_replace('/\s+/u',' ',(string)(isset($x['name'])?$x['name']:''))),0,120);
        if($name==='')throw new RuntimeException('Название дня пустое.');
        $s=$pdo->prepare("UPDATE workout_days SET name=? WHERE id=? AND program_id=?");$s->execute(array($name,$id,$pid));
        wout(array('ok'=>true));
    }

    wout(array('ok'=>false,'error'=>'unknown action'),400);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    wout(array('ok'=>false,'error'=>$e->getMessage()),500);
}
