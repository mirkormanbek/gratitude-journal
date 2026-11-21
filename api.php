<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function bodyJson(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}
function out($data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function n($v): float { return is_numeric($v) ? (float)$v : 0; }
function requireCsrf(): void {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf'] ?? '';
    if (!$expected || !$sent || !hash_equals($expected, $sent)) {
        http_response_code(403);
        out(['ok'=>false,'error'=>'csrf']);
    }
}

$resource = $_GET['resource'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && $resource === 'meta') {
    out(['ok'=>true,'csrf'=>$_SESSION['csrf']]);
}

if ($method === 'GET') {
    switch ($resource) {
        case 'daily':
            $rows=$pdo->query("SELECT * FROM daily_entries ORDER BY entry_date DESC")->fetchAll();
            out(array_map(fn($r)=>[
                'date'=>$r['entry_date'],'allah'=>$r['allah'],'self'=>$r['self_gratitude'],'lesson'=>$r['lesson'],
                'trust'=>$r['trust_allah'],'action'=>$r['tomorrow_action'],'money'=>$r['money_action'],
                'focusloss'=>$r['focus_loss'],'mood'=>(int)$r['mood']
            ],$rows));
        case 'weekly':
            $rows=$pdo->query("SELECT * FROM ceo_reviews ORDER BY review_date DESC")->fetchAll();
            out(array_map(fn($r)=>[
                'date'=>$r['review_date'],'money'=>(float)$r['money_earned'],'best'=>$r['best_money_source'],
                'leaks'=>$r['money_leaks'],'actions'=>$r['best_actions'],'time'=>$r['lost_focus'],
                'lever'=>$r['x2_lever'],'goal'=>$r['next_week_goal'],'tasks'=>$r['next_week_tasks']
            ],$rows));
        case 'knowledge':
            // Knowledge Base now lives in the Notes service (notes.php); this read-only view feeds export & counters.
            $rows=$pdo->query("SELECT * FROM notes WHERE archived=0 ORDER BY note_date DESC,id DESC")->fetchAll();
            out(array_map(fn($r)=>[
                'id'=>(string)$r['id'],'date'=>$r['note_date'],'category'=>$r['category']??($r['kind']==='idea'?'Идея':'Заметка'),'source'=>$r['source'],
                'insight'=>trim(($r['title']?$r['title']."\n":'').($r['body']??'')),'why'=>$r['why'],'apply'=>$r['apply'],
                'status'=>$r['status']??($r['kind']==='idea'?'Идея':'Заметка'),'result'=>$r['result'],'tags'=>$r['tags']
            ],$rows));
        case 'capital':
            $rows=$pdo->query("SELECT * FROM capital_entries ORDER BY entry_date DESC")->fetchAll();
            out(array_map(fn($r)=>[
                'date'=>$r['entry_date'],'cash'=>(float)$r['cash'],'wb'=>(float)$r['wb_receivable'],
                'stock'=>(float)$r['stock_cost'],'suppliers'=>(float)$r['supplier_debt'],
                'taxes'=>(float)$r['taxes'],'other'=>(float)$r['other_obligations'],
                'purchases'=>(float)$r['upcoming_purchases'],'reserve'=>(float)$r['reserve_amount'],'notes'=>$r['notes']
            ],$rows));
        default: http_response_code(400); out(['ok'=>false,'error'=>'unknown resource']);
    }
}

if ($method === 'POST') {
    requireCsrf();
    $x=bodyJson();
    switch ($resource) {
        case 'daily':
            $sql="INSERT INTO daily_entries(entry_date,allah,self_gratitude,lesson,trust_allah,tomorrow_action,money_action,focus_loss,mood)
                  VALUES(?,?,?,?,?,?,?,?,?)
                  ON DUPLICATE KEY UPDATE allah=VALUES(allah),self_gratitude=VALUES(self_gratitude),lesson=VALUES(lesson),
                  trust_allah=VALUES(trust_allah),tomorrow_action=VALUES(tomorrow_action),money_action=VALUES(money_action),
                  focus_loss=VALUES(focus_loss),mood=VALUES(mood)";
            $pdo->prepare($sql)->execute([$x['date'],$x['allah']??'',$x['self']??'',$x['lesson']??'',$x['trust']??'',$x['action']??'',$x['money']??'',$x['focusloss']??'',(int)($x['mood']??7)]);
            out(['ok'=>true]);
        case 'weekly':
            $sql="INSERT INTO ceo_reviews(review_date,money_earned,best_money_source,money_leaks,best_actions,lost_focus,x2_lever,next_week_goal,next_week_tasks)
                  VALUES(?,?,?,?,?,?,?,?,?)
                  ON DUPLICATE KEY UPDATE money_earned=VALUES(money_earned),best_money_source=VALUES(best_money_source),money_leaks=VALUES(money_leaks),
                  best_actions=VALUES(best_actions),lost_focus=VALUES(lost_focus),x2_lever=VALUES(x2_lever),next_week_goal=VALUES(next_week_goal),next_week_tasks=VALUES(next_week_tasks)";
            $pdo->prepare($sql)->execute([$x['date'],n($x['money']??0),$x['best']??'',$x['leaks']??'',$x['actions']??'',$x['time']??'',$x['lever']??'',$x['goal']??'',$x['tasks']??'']);
            out(['ok'=>true]);
        case 'knowledge':
            http_response_code(410);out(['ok'=>false,'error'=>'Используйте сервис «Заметки» (notes.php)']);
        case 'capital':
            $sql="INSERT INTO capital_entries(entry_date,cash,wb_receivable,stock_cost,supplier_debt,taxes,other_obligations,upcoming_purchases,reserve_amount,notes)
                  VALUES(?,?,?,?,?,?,?,?,?,?)
                  ON DUPLICATE KEY UPDATE cash=VALUES(cash),wb_receivable=VALUES(wb_receivable),stock_cost=VALUES(stock_cost),
                  supplier_debt=VALUES(supplier_debt),taxes=VALUES(taxes),other_obligations=VALUES(other_obligations),
                  upcoming_purchases=VALUES(upcoming_purchases),reserve_amount=VALUES(reserve_amount),notes=VALUES(notes)";
            $pdo->prepare($sql)->execute([$x['date'],n($x['cash']??0),n($x['wb']??0),n($x['stock']??0),n($x['suppliers']??0),n($x['taxes']??0),n($x['other']??0),n($x['purchases']??0),n($x['reserve']??0),$x['notes']??'']);
            out(['ok'=>true]);
        default: http_response_code(400); out(['ok'=>false,'error'=>'unknown resource']);
    }
}

if ($method === 'DELETE') {
    requireCsrf();
    $x=bodyJson();
    switch ($resource) {
        case 'daily':
            $stmt=$pdo->prepare("DELETE FROM daily_entries WHERE entry_date=?");
            $stmt->execute([$x['date']??'']);
            out(['ok'=>true,'deleted'=>$stmt->rowCount()]);
        case 'weekly':
            $stmt=$pdo->prepare("DELETE FROM ceo_reviews WHERE review_date=?");
            $stmt->execute([$x['date']??'']);
            out(['ok'=>true,'deleted'=>$stmt->rowCount()]);
        case 'knowledge':
            http_response_code(410);out(['ok'=>false,'error'=>'Используйте сервис «Заметки» (notes.php)']);
        case 'capital':
            $stmt=$pdo->prepare("DELETE FROM capital_entries WHERE entry_date=?");
            $stmt->execute([$x['date']??'']);
            out(['ok'=>true,'deleted'=>$stmt->rowCount()]);
        default: http_response_code(400); out(['ok'=>false,'error'=>'unknown resource']);
    }
}

http_response_code(405);
out(['ok'=>false,'error'=>'method not allowed']);
