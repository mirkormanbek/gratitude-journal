<?php
/**
 * CLI migration runner (Plesk scheduled task, every 5 min; no-op when up to date).
 * Normally migrations are already applied by db.php on the first request after deploy.
 * Web access is blocked in .htaccess and below.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

$config = require __DIR__ . '/config.php';
require __DIR__ . '/migrations_lib.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $done = growth_run_migrations($pdo);
    echo $done ? 'applied: ' . implode(', ', $done) . "\n" : "up to date\n";
} catch (Throwable $e) {
    // stderr → Plesk e-mails the owner ("errors only")
    fwrite(STDERR, '[growth-os migrate] ' . $e->getMessage() . "\n");
    exit(1);
}
