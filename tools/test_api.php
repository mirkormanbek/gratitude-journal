<?php
// CI: end-to-end API scenarios against the CI MariaDB (tools/ci_baseline.sql + migrations).
// Each call runs in a separate PHP process via tools/api_call.php (endpoints call exit()).
declare(strict_types=1);

$fail = 0;
function call(string $endpoint, string $action, array $body = []): array {
    $cmd = 'php ' . escapeshellarg(__DIR__ . '/api_call.php') . ' ' . escapeshellarg($endpoint) . ' '
        . escapeshellarg($action) . ' ' . escapeshellarg(json_encode($body, JSON_UNESCAPED_UNICODE)) . ' 2>&1';
    $out = shell_exec($cmd);
    $j = json_decode((string)$out, true);
    if (!is_array($j)) {
        echo "::error::$endpoint/$action returned non-JSON: " . str_replace(["\r", "\n"], ' ', substr((string)$out, 0, 600)) . "\n";
        exit(1);
    }
    return $j;
}
function check(bool $cond, string $label, $ctx = null): void {
    global $fail;
    if ($cond) { echo "✓ $label\n"; return; }
    $fail++;
    echo "::error::$label" . ($ctx !== null ? ': ' . substr(json_encode($ctx, JSON_UNESCAPED_UNICODE), 0, 600) : '') . "\n";
}

require __DIR__ . '/api_tests_workouts.php';
foreach (glob(__DIR__ . '/api_tests_*.php') as $f) {
    if (basename($f) !== 'api_tests_workouts.php') require $f;
}

if ($fail) { fwrite(STDERR, "$fail check(s) failed\n"); exit(1); }
echo "All API checks passed\n";
