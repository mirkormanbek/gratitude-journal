<?php
/**
 * App settings API. POST settings.php?action=get|set_module (session auth + CSRF). PHP 7.4.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

function sout($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
if (empty($_SESSION['user_id'])) sout(['ok' => false, 'error' => 'unauthorized'], 401);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') sout(['ok' => false, 'error' => 'method not allowed'], 405);
$sent = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$expected = (string)($_SESSION['csrf'] ?? '');
if ($sent === '' || $expected === '' || !hash_equals($expected, $sent)) sout(['ok' => false, 'error' => 'csrf'], 403);
$raw = defined('GROWTH_CI_BODY') ? GROWTH_CI_BODY : file_get_contents('php://input');
$x = json_decode($raw ?: '{}', true);
if (!is_array($x)) $x = [];
$action = (string)($_GET['action'] ?? '');

const MODULES = ['quran', 'person'];
function modulesState(PDO $pdo): array
{
    $out = [];
    foreach (MODULES as $m) $out[$m] = growth_module_enabled($pdo, $m);
    return $out;
}

try {
    if ($action === 'get') sout(['ok' => true, 'modules' => modulesState($pdo)]);
    if ($action === 'set_module') {
        $m = (string)($x['module'] ?? '');
        if (!in_array($m, MODULES, true)) throw new RuntimeException('Неизвестный модуль.');
        $pdo->prepare("INSERT INTO app_settings(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)")
            ->execute(['module.' . $m, !empty($x['enabled']) ? '1' : '0']);
        sout(['ok' => true, 'modules' => modulesState($pdo)]);
    }
    sout(['ok' => false, 'error' => 'unknown action'], 400);
} catch (Throwable $e) {
    sout(['ok' => false, 'error' => $e->getMessage()], 500);
}
