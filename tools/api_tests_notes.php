<?php
// Notes API scenarios. Fixture has 1 knowledge_base row (migrated by 0003) and project #1.
$j = call('notes.php', 'list');
check($j['ok'] ?? false, 'notes list ok', $j);
$legacy = array_values(array_filter($j['notes'] ?? [], fn($n) => $n['kind'] === 'idea' && strpos((string)$n['body'], 'стабильность') !== false));
check(count($legacy) === 1, 'knowledge base entry migrated as idea', $j['notes'] ?? null);
check(($legacy[0]['why'] ?? '') === 'Повторные заказы' && ($legacy[0]['date'] ?? '') === '2026-10-01', 'migrated fields kept', $legacy[0] ?? null);

$j = call('notes.php', 'save', ['body' => "Купить ремни для тяги\nи мел"]);
check(($j['ok'] ?? false) && ($j['note']['kind'] ?? '') === 'note', 'quick note saved', $j);
$quick = (int)($j['note']['id'] ?? 0);

$j = call('notes.php', 'save', ['kind' => 'idea', 'title' => 'Каталог для опта', 'body' => '30 SKU', 'why' => 'Быстрее продажи',
    'status' => 'Тестируется', 'tags' => '#Опт, продажи, опт', 'project_id' => 1]);
check(($j['note']['status'] ?? '') === 'Тестируется', 'idea status saved', $j);
check(($j['note']['tags'] ?? '') === 'опт, продажи', 'tags normalized & deduped', $j['note']['tags'] ?? null);
check(($j['note']['project_name'] ?? '') === 'Оптовый канал', 'linked to project', $j['note'] ?? null);
$idea = (int)($j['note']['id'] ?? 0);

$j = call('notes.php', 'save', ['id' => $quick, 'kind' => 'note', 'body' => 'x', 'status' => 'Сработало', 'why' => 'w']);
check(array_key_exists('status', $j['note'] ?? []) && $j['note']['status'] === null && $j['note']['why'] === null, 'plain note ignores idea fields', $j['note'] ?? null);

$j = call('notes.php', 'save', ['body' => '   ']);
check(($j['ok'] ?? true) === false, 'empty note rejected', $j);
$j = call('notes.php', 'save', ['body' => 'x', 'project_id' => 999]);
check(($j['ok'] ?? true) === false, 'unknown project rejected', $j);

$j = call('notes.php', 'pin', ['id' => $idea, 'value' => 1]);
$j = call('notes.php', 'list');
check(($j['notes'][0]['id'] ?? 0) === $idea, 'pinned note first', array_column($j['notes'] ?? [], 'id'));

$j = call('notes.php', 'archive', ['id' => $quick, 'value' => 1]);
$j = call('notes.php', 'list');
check(!in_array($quick, array_column($j['notes'], 'id'), true) && ($j['counts']['archived'] ?? 0) === 1, 'archived hidden from list', $j['counts'] ?? null);
$j = call('notes.php', 'list', ['archived' => 1]);
check(in_array($quick, array_column($j['notes'], 'id'), true), 'archived list shows it');

$j = call('notes.php', 'delete', ['id' => $quick]);
$j = call('notes.php', 'list', ['archived' => 1]);
check(count($j['notes'] ?? []) === 0, 'deleted');
