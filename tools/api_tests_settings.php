<?php
// Module switches: migration 0005 pauses Коран and Человек дня.
$j = call('settings.php', 'get');
check(($j['modules'] ?? null) === ['quran' => false, 'person' => false], 'quran & person paused by default', $j);
$j = call('quran.php', 'today', ['date' => date('Y-m-d')]);
check(($j['disabled'] ?? false) === true, 'quran API makes no external calls when paused', $j);
$j = call('ai.php', 'person_day', ['date' => date('Y-m-d')]);
check(($j['disabled'] ?? false) === true, 'person API makes no AI calls when paused', $j);
$j = call('settings.php', 'set_module', ['module' => 'person', 'enabled' => true]);
check(($j['modules']['person'] ?? false) === true && ($j['modules']['quran'] ?? true) === false, 'enable one module', $j);
$j = call('settings.php', 'set_module', ['module' => 'person', 'enabled' => false]);
check(($j['modules']['person'] ?? true) === false, 'disable again', $j);
$j = call('settings.php', 'set_module', ['module' => 'capital', 'enabled' => false]);
check(($j['ok'] ?? true) === false, 'unknown module rejected', $j);
