<?php
/**
 * Notes service API (notes + ideas/knowledge). PHP 7.4.
 * POST notes.php?action=list|save|delete|pin|archive  (session auth + CSRF)
 */
declare(strict_types=1);

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

function nout($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (empty($_SESSION['user_id'])) {
    nout(['ok' => false, 'error' => 'unauthorized'], 401);
}
date_default_timezone_set(isset($config['timezone']) ? (string)$config['timezone'] : 'Asia/Almaty');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    nout(['ok' => false, 'error' => 'method not allowed'], 405);
}
$sent = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$expected = (string)($_SESSION['csrf'] ?? '');
if ($sent === '' || $expected === '' || !hash_equals($expected, $sent)) {
    nout(['ok' => false, 'error' => 'csrf'], 403);
}
$raw = defined('GROWTH_CI_BODY') ? GROWTH_CI_BODY : file_get_contents('php://input');
$x = json_decode($raw ?: '{}', true);
if (!is_array($x)) $x = [];
$action = (string)($_GET['action'] ?? '');

const NOTE_KINDS = ['note', 'idea'];
const IDEA_STATUSES = ['Идея', 'Нужно протестировать', 'Тестируется', 'Сработало', 'Не сработало', 'Стало системой'];

function ntext($v, int $max): ?string
{
    $s = trim(str_replace(["\r\n", "\r"], "\n", (string)$v));
    if ($s === '') return null;
    return mb_substr($s, 0, $max);
}
function nline($v, int $max): ?string
{
    $s = trim(preg_replace('/\s+/u', ' ', (string)$v));
    return $s === '' ? null : mb_substr($s, 0, $max);
}
function ntags($v): ?string
{
    $parts = is_array($v) ? $v : explode(',', (string)$v);
    $out = [];
    foreach ($parts as $p) {
        $p = trim(preg_replace('/\s+/u', ' ', ltrim((string)$p, "# \t")));
        if ($p === '') continue;
        $p = mb_strtolower(mb_substr($p, 0, 40));
        if (!in_array($p, $out, true)) $out[] = $p;
        if (count($out) >= 12) break;
    }
    return $out ? implode(', ', $out) : null;
}
function noteRow(array $r): array
{
    return [
        'id' => (int)$r['id'], 'kind' => (string)$r['kind'], 'title' => $r['title'], 'body' => $r['body'],
        'category' => $r['category'], 'source' => $r['source'], 'why' => $r['why'], 'apply' => $r['apply'],
        'result' => $r['result'], 'status' => $r['status'], 'tags' => $r['tags'],
        'project_id' => $r['project_id'] !== null ? (int)$r['project_id'] : null,
        'project_name' => $r['project_name'] ?? null,
        'pinned' => (int)$r['pinned'], 'archived' => (int)$r['archived'],
        'date' => (string)$r['note_date'], 'created_at' => (string)$r['created_at'], 'updated_at' => (string)$r['updated_at'],
    ];
}
function noteById(PDO $pdo, int $id): ?array
{
    $s = $pdo->prepare("SELECT n.*, p.name project_name FROM notes n LEFT JOIN projects p ON p.id=n.project_id WHERE n.id=?");
    $s->execute([$id]);
    $r = $s->fetch();
    return $r ? noteRow($r) : null;
}

try {
    if ($action === 'list') {
        $archived = !empty($x['archived']) ? 1 : 0;
        $s = $pdo->prepare("SELECT n.*, p.name project_name FROM notes n LEFT JOIN projects p ON p.id=n.project_id
            WHERE n.archived=? ORDER BY n.pinned DESC, n.updated_at DESC, n.id DESC");
        $s->execute([$archived]);
        $notes = array_map('noteRow', $s->fetchAll());
        $counts = $pdo->query("SELECT SUM(archived=0) active, SUM(archived=1) archived FROM notes")->fetch();
        nout(['ok' => true, 'notes' => $notes, 'counts' => ['active' => (int)$counts['active'], 'archived' => (int)$counts['archived']],
            'statuses' => IDEA_STATUSES]);
    }

    if ($action === 'save') {
        $id = (int)($x['id'] ?? 0);
        $kind = in_array($x['kind'] ?? 'note', NOTE_KINDS, true) ? (string)$x['kind'] : 'note';
        $title = nline($x['title'] ?? '', 200);
        $body = ntext($x['body'] ?? '', 20000);
        if ($title === null && $body === null) throw new RuntimeException('Пустая заметка.');
        $status = null;
        if ($kind === 'idea') {
            $status = in_array($x['status'] ?? '', IDEA_STATUSES, true) ? (string)$x['status'] : 'Идея';
        }
        $projectId = (int)($x['project_id'] ?? 0);
        if ($projectId > 0) {
            $c = $pdo->prepare("SELECT id FROM projects WHERE id=?");
            $c->execute([$projectId]);
            if (!$c->fetchColumn()) throw new RuntimeException('Проект не найден.');
        }
        $date = (string)($x['date'] ?? '');
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) $date = date('Y-m-d');
        $vals = [
            $kind, $title, $body, nline($x['category'] ?? '', 100), nline($x['source'] ?? '', 255),
            $kind === 'idea' ? ntext($x['why'] ?? '', 5000) : null,
            $kind === 'idea' ? ntext($x['apply'] ?? '', 5000) : null,
            $kind === 'idea' ? ntext($x['result'] ?? '', 5000) : null,
            $status, ntags($x['tags'] ?? ''), $projectId > 0 ? $projectId : null,
            !empty($x['pinned']) ? 1 : 0, $date,
        ];
        if ($id > 0) {
            if (!noteById($pdo, $id)) throw new RuntimeException('Заметка не найдена.');
            $vals[] = $id;
            $pdo->prepare("UPDATE notes SET kind=?,title=?,body=?,category=?,source=?,why=?,apply=?,result=?,status=?,tags=?,project_id=?,pinned=?,note_date=? WHERE id=?")->execute($vals);
        } else {
            $pdo->prepare("INSERT INTO notes(kind,title,body,category,source,why,apply,result,status,tags,project_id,pinned,note_date) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute($vals);
            $id = (int)$pdo->lastInsertId();
        }
        nout(['ok' => true, 'note' => noteById($pdo, $id)]);
    }

    if ($action === 'pin' || $action === 'archive') {
        $id = (int)($x['id'] ?? 0);
        if (!noteById($pdo, $id)) throw new RuntimeException('Заметка не найдена.');
        $col = $action === 'pin' ? 'pinned' : 'archived';
        // updated_at is kept as is: pinning/archiving shouldn't reorder by "last edited"
        $pdo->prepare("UPDATE notes SET $col=?, updated_at=updated_at WHERE id=?")->execute([!empty($x['value']) ? 1 : 0, $id]);
        nout(['ok' => true]);
    }

    if ($action === 'delete') {
        $id = (int)($x['id'] ?? 0);
        $pdo->prepare("DELETE FROM notes WHERE id=?")->execute([$id]);
        nout(['ok' => true]);
    }

    nout(['ok' => false, 'error' => 'unknown action'], 400);
} catch (Throwable $e) {
    nout(['ok' => false, 'error' => $e->getMessage()], 500);
}
