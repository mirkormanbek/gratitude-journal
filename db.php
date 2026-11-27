<?php
declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    exit('Не найден config.php. Скопируйте config.example.php в config.php и укажите пароль БД.');
}
$config = require $configFile;

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    exit('Ошибка подключения к базе данных.');
}

// Apply newly deployed DB migrations (cheap file check; see migrations_lib.php).
require_once __DIR__ . '/migrations_lib.php';
growth_auto_migrate($pdo);

session_name($config['session_name'] ?? 'growth_journal_session');
session_set_cookie_params([
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
// PHP file sessions are locked for the whole request. API endpoints only READ the session,
// so release the lock immediately: otherwise a slow request (OpenAI / Quran API, up to 80 s)
// blocks every other request of the same user (e.g. opening or finishing a workout).
// Pages that write to $_SESSION (login, logout, index) define GROWTH_SESSION_WRITE first.
if (!defined('GROWTH_SESSION_WRITE') && PHP_SAPI !== 'cli') {
    session_write_close();
}
