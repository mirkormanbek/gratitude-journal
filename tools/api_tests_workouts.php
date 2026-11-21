<?php
// Workout API scenarios. Fixture: День 1 (Mon) = Жим лёжа, Тяга верхняя; День 2 (Wed) = Брусья, Жим лёжа.
$mon = '2026-11-23'; $wed = '2026-11-25';

$j = call('workouts.php', 'summary', ['date' => $mon, 'month' => '2026-11']);
check($j['ok'] ?? false, 'summary ok', $j);
check(($j['summary']['today_session']['day_name'] ?? '') === 'День 1', 'Monday is День 1', $j['summary']['today_session'] ?? null);

$j = call('workouts.php', 'start', ['date' => $mon]);
check(($j['ok'] ?? false) && ($j['session_id'] ?? 0) > 0, 'start today', $j);
$s1 = (int)$j['session_id'];

$j = call('workouts.php', 'session', ['id' => $s1]);
$ex = $j['session']['exercises'] ?? [];
check(count($ex) === 2, 'session has 2 exercises', count($ex));
check(($ex[0]['rest_seconds'] ?? 0) === 90, 'default rest 90s', $ex[0] ?? null);
$bench = (int)$ex[0]['id'];

foreach ([[1, 60, 8], [2, 62.5, 7], [3, 70, 1]] as [$no, $w, $r]) {
    $j = call('workouts.php', 'save_set', ['session_id' => $s1, 'exercise_id' => $bench, 'set_no' => $no, 'weight_kg' => $w, 'reps' => $r]);
    check($j['ok'] ?? false, "save set $no", $j);
}
$j = call('workouts.php', 'delete_set', ['session_id' => $s1, 'exercise_id' => $bench, 'set_no' => 3]);
check($j['ok'] ?? false, 'delete mistaken set', $j);
$j = call('workouts.php', 'session', ['id' => $s1]);
check(count($j['session']['exercises'][0]['sets'] ?? []) === 2, 'two sets remain');

$j = call('workouts.php', 'complete', ['session_id' => $s1]);
check($j['ok'] ?? false, 'complete', $j);

$j = call('workouts.php', 'stats', ['date' => $mon]);
$st = $j['stats'] ?? [];
check(($st['totals']['workouts'] ?? 0) === 1, 'stats: 1 workout', $st['totals'] ?? null);
check(($st['totals']['sets'] ?? 0) === 2, 'stats: 2 sets', $st['totals'] ?? null);
check(abs(($st['totals']['volume'] ?? 0) - (60*8 + 62.5*7)) < 1, 'stats: volume', $st['totals'] ?? null);
$benchStat = array_values(array_filter($st['exercises'] ?? [], fn($e) => $e['key'] === 'жим лежа'));
check(count($benchStat) === 1 && (float)$benchStat[0]['best_weight'] === 62.5, 'stats: bench best 62.5 (ё normalized)', $benchStat);
check(count($st['weeks'] ?? []) === 12, 'stats: 12 weekly buckets');

// Same exercise name on another day → previous result is found by name
$j = call('workouts.php', 'summary', ['date' => $wed, 'month' => '2026-11']);
$wedSession = $j['summary']['today_session']['id'] ?? 0;
$j = call('workouts.php', 'start', ['session_id' => $wedSession, 'date' => $wed]);
$s2 = (int)($j['session_id'] ?? 0);
$j = call('workouts.php', 'session', ['id' => $s2]);
$wedBench = array_values(array_filter($j['session']['exercises'] ?? [], fn($e) => $e['name'] === 'Жим лёжа'));
check(count($wedBench) === 1 && count($wedBench[0]['previous_sets']) === 2, 'previous sets found across days by name', $wedBench);
check(($wedBench[0]['best']['weight_kg'] ?? 0) == 62.5, 'personal best shown', $wedBench[0]['best'] ?? null);

// Program editor
$j = call('workouts.php', 'program');
check(count($j['days'] ?? []) === 3, 'program: 3 days');
$j = call('workouts.php', 'exercise_save', ['day_id' => 1, 'name' => '  Подтягивания ', 'target_sets' => 4, 'target_reps' => '8', 'rest_seconds' => 120]);
check(($j['ok'] ?? false) && $j['id'] > 0, 'add exercise', $j);
$newId = (int)$j['id'];
$j = call('workouts.php', 'exercise_move', ['id' => $newId, 'dir' => -1]);
check($j['ok'] ?? false, 'move exercise up');
$j = call('workouts.php', 'program');
$names = array_column($j['days'][0]['exercises'], 'name');
check($names === ['Жим лёжа', 'Подтягивания', 'Тяга верхняя'], 'order after move', $names);
$j = call('workouts.php', 'exercise_save', ['id' => $newId, 'day_id' => 1, 'name' => 'Подтягивания', 'target_sets' => 0]);
check(($j['ok'] ?? true) === false, 'validation: 0 sets rejected', $j);
$j = call('workouts.php', 'exercise_archive', ['id' => $bench]);
check($j['ok'] ?? false, 'archive exercise with history');
$j = call('workouts.php', 'summary', ['date' => $wed, 'month' => '2026-11']);
$d1 = array_values(array_filter($j['summary']['days'], fn($d) => $d['name'] === 'День 1'))[0];
check(!in_array('Жим лёжа', array_column($d1['exercises'], 'name'), true), 'archived hidden from program');
$j = call('workouts.php', 'session', ['id' => $s1]);
check(in_array('Жим лёжа', array_column($j['session']['exercises'], 'name'), true), 'archived still visible in past session with sets');
$j = call('workouts.php', 'stats', ['date' => $wed]);
check(count(array_filter($j['stats']['exercises'], fn($e) => $e['key'] === 'жим лежа')) === 1, 'archived exercise keeps progress history');
$j = call('workouts.php', 'exercise_restore', ['id' => $bench]);
check($j['ok'] ?? false, 'restore exercise');
$j = call('workouts.php', 'day_rename', ['id' => 1, 'name' => 'Верх А']);
check($j['ok'] ?? false, 'rename day');

// cleanup of the in-progress Wednesday session
$j = call('workouts.php', 'delete', ['id' => $s2]);
check($j['ok'] ?? false, 'delete session');
