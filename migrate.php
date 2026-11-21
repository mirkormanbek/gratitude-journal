<?php
/**
 * Database migration runner (PHP 7.4 compatible).
 *
 * Run after each deploy by Plesk (CLI, no token needed):
 *   php migrate.php
 * or over HTTP if config['migrate_token'] is set:
 *   POST /migrate.php  with header  X-Migrate-Token: <config['migrate_token']>
 *
 * Applies files from migrations/ in name order (0001_*.sql, 0002_*.php, ...)
 * exactly once and records them in the schema_migrations table.
 * Without a configured token the endpoint pretends not to exist.
 */
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];
$token = isset($config['migrate_token']) ? (string)$config['migrate_token'] : '';
$sent = isset($_SERVER['HTTP_X_MIGRATE_TOKEN']) ? (string)$_SERVER['HTTP_X_MIGRATE_TOKEN'] : '';

$isCli = PHP_SAPI === 'cli';
if (!$isCli && (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || strlen($token) < 32 || !hash_equals($token, $sent))) {
    http_response_code(404);
    exit('Not found');
}

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    http_response_code(500);
    exit("DB connection failed\n");
}

$lock = $pdo->query("SELECT GET_LOCK('growth_os_migrate', 30) AS l")->fetch();
if ((int)$lock['l'] !== 1) {
    http_response_code(409);
    exit("Another migration is running\n");
}

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    name VARCHAR(190) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$applied = array_flip($pdo->query("SELECT name FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN));
$files = glob(__DIR__ . '/migrations/[0-9][0-9][0-9][0-9]_*.{sql,php}', GLOB_BRACE) ?: [];
sort($files, SORT_STRING);

$ran = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        continue;
    }
    try {
        if (substr($name, -4) === '.php') {
            // A PHP migration receives $pdo and must be idempotent.
            (function (PDO $pdo, string $file) { require $file; })($pdo, $file);
        } else {
            foreach (splitSql((string)file_get_contents($file)) as $stmt) {
                $pdo->exec($stmt);
            }
        }
        $pdo->prepare("INSERT INTO schema_migrations (name) VALUES (?)")->execute([$name]);
        echo "applied: $name\n";
        $ran++;
    } catch (Throwable $e) {
        http_response_code(500);
        echo "FAILED: $name\n" . $e->getMessage() . "\n";
        $pdo->query("SELECT RELEASE_LOCK('growth_os_migrate')");
        exit;
    }
}
$pdo->query("SELECT RELEASE_LOCK('growth_os_migrate')");
echo $ran ? "done, $ran applied\n" : "up to date\n";

/** Split SQL on semicolons at line ends; ignores -- comments. Keep migrations simple (no procedures). */
function splitSql(string $sql): array
{
    $lines = preg_split('/\R/', $sql);
    $buf = '';
    $out = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $buf .= $line . "\n";
        if (preg_match('/;\s*$/', $line)) {
            $stmt = trim(rtrim(trim($buf), ';'));
            if ($stmt !== '') {
                $out[] = $stmt;
            }
            $buf = '';
        }
    }
    if (trim($buf) !== '') {
        $out[] = trim($buf);
    }
    return $out;
}
