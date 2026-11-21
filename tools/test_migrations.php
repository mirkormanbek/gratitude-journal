<?php
// CI: run all migrations twice against an empty MariaDB; the second run must apply nothing.
declare(strict_types=1);
require __DIR__ . '/../migrations_lib.php';

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=ci;charset=utf8mb4', 'root', 'ci',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
@unlink(growth_migrations_stamp_file());

$first = growth_run_migrations($pdo);
echo "first run: " . (implode(', ', $first) ?: '(none)') . "\n";
$second = growth_run_migrations($pdo);
if ($second) {
    echo "::error::second run applied again: " . implode(', ', $second) . "\n";
    exit(1);
}
@unlink(growth_migrations_stamp_file());
growth_auto_migrate($pdo); // must be a silent no-op
echo "OK\n";
