<?php
/**
 * Forward-only DB migrations (PHP 7.4 compatible).
 *
 * Used by:
 *  - db.php: growth_auto_migrate($pdo) on every request — a cheap file check;
 *    the DB is touched only when a new migration file has been deployed.
 *  - migrate.php: CLI runner (Plesk scheduled task, backup path).
 */
declare(strict_types=1);

function growth_migration_files(): array
{
    $files = glob(__DIR__ . '/migrations/[0-9][0-9][0-9][0-9]_*.{sql,php}', GLOB_BRACE) ?: [];
    sort($files, SORT_STRING);
    return $files;
}

function growth_migrations_stamp_file(): string
{
    return __DIR__ . '/.migrations_stamp';
}

/** Applies pending migrations. Returns names applied. Throws on failure. */
function growth_run_migrations(PDO $pdo): array
{
    $lock = $pdo->query("SELECT GET_LOCK('growth_os_migrate', 30) AS l")->fetch(PDO::FETCH_ASSOC);
    if ((int)$lock['l'] !== 1) {
        throw new RuntimeException('Another migration is running');
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            name VARCHAR(190) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $applied = array_flip($pdo->query("SELECT name FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN));
        $done = [];
        foreach (growth_migration_files() as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                continue;
            }
            try {
                if (substr($name, -4) === '.php') {
                    // A PHP migration receives $pdo and must be idempotent.
                    (function (PDO $pdo, string $file) { require $file; })($pdo, $file);
                } else {
                    foreach (growth_split_sql((string)file_get_contents($file)) as $stmt) {
                        $pdo->exec($stmt);
                    }
                }
            } catch (Throwable $e) {
                throw new RuntimeException("Migration $name failed: " . $e->getMessage(), 0, $e);
            }
            $pdo->prepare("INSERT INTO schema_migrations (name) VALUES (?)")->execute([$name]);
            $done[] = $name;
        }
        growth_write_stamp();
        return $done;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('growth_os_migrate')");
    }
}

function growth_current_signature(): string
{
    $names = array_map('basename', growth_migration_files());
    return md5(implode('|', $names));
}

function growth_write_stamp(): void
{
    @file_put_contents(growth_migrations_stamp_file(), growth_current_signature(), LOCK_EX);
}

/** Cheap per-request check; never breaks the page — failures are logged and retried. */
function growth_auto_migrate(PDO $pdo): void
{
    $stamp = @file_get_contents(growth_migrations_stamp_file());
    if ($stamp === growth_current_signature()) {
        return;
    }
    try {
        $done = growth_run_migrations($pdo);
        if ($done) {
            error_log('[growth-os] migrations applied: ' . implode(', ', $done));
        }
    } catch (Throwable $e) {
        error_log('[growth-os] ' . $e->getMessage());
    }
}

/** Split SQL on semicolons at line ends; ignores -- comments. Keep migrations simple (no procedures). */
function growth_split_sql(string $sql): array
{
    $out = [];
    $buf = '';
    foreach (preg_split('/\R/', $sql) as $line) {
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

/** App setting (app_settings table); returns $default if missing or table absent. */
function growth_setting(PDO $pdo, string $name, ?string $default = null): ?string
{
    try {
        $s = $pdo->prepare("SELECT value FROM app_settings WHERE name=?");
        $s->execute([$name]);
        $v = $s->fetchColumn();
        return $v === false ? $default : (string)$v;
    } catch (Throwable $e) {
        return $default;
    }
}
function growth_module_enabled(PDO $pdo, string $module): bool
{
    return growth_setting($pdo, 'module.' . $module, '1') !== '0';
}
