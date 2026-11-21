<?php
// Projects API scenarios. Fixture: project #1 «Оптовый канал» with one task «Собрать каталог» (2026-11-20).
$j = call('projects.php', 'get', ['id' => 1, 'date' => '2026-11-21']);
check(($j['project']['name'] ?? '') === 'Оптовый канал', 'project get', $j);
check(($j['project']['overdue_tasks'] ?? -1) === 1, 'overdue step counted', $j['project'] ?? null);

$j = call('projects.php', 'add_task', ['project_id' => 1, 'title' => '  Найти 20 магазинов  ', 'due_date' => '2026-11-25']);
check(($j['ok'] ?? false) && $j['id'] > 0, 'add step to project', $j);
$step = (int)$j['id'];
$j = call('projects.php', 'add_task', ['project_id' => 1, 'title' => 'Без даты']);
check($j['ok'] ?? false, 'step without date defaults to today', $j);
$j = call('projects.php', 'add_task', ['project_id' => 1, 'title' => '']);
check(($j['ok'] ?? true) === false, 'empty step rejected', $j);

$j = call('tasks.php', 'toggle', ['id' => $step, 'done' => true]);
check($j['ok'] ?? false, 'complete step', $j);
$j = call('projects.php', 'get', ['id' => 1, 'date' => '2026-11-21']);
check(($j['project']['done_tasks'] ?? 0) === 1 && ($j['project']['total_tasks'] ?? 0) === 3, 'progress counts', $j['project'] ?? null);
check(($j['project']['progress_percent'] ?? 0) === 33, 'progress percent', $j['project'] ?? null);
$titles = array_column($j['tasks'] ?? [], 'title');
check(end($titles) === 'Найти 20 магазинов', 'done steps listed last', $titles);
check(($j['project']['next_task_id'] ?? 0) === (int)$j['tasks'][0]['id'], 'next step = first open', $j['project'] ?? null);

$j = call('projects.php', 'list', ['date' => '2026-11-21']);
$p = array_values(array_filter($j['projects'] ?? [], fn($x) => (int)$x['id'] === 1))[0] ?? [];
check(($p['next_task']['title'] ?? '') === 'Собрать каталог', 'list: next task is the overdue one', $p['next_task'] ?? null);

$j = call('projects.php', 'status', ['id' => 1, 'status' => 'paused']);
$j = call('projects.php', 'get', ['id' => 1]);
check(($j['project']['status'] ?? '') === 'paused', 'pause project');
$j = call('projects.php', 'get', ['id' => 999]);
check(($j['ok'] ?? true) === false, 'unknown project rejected');
