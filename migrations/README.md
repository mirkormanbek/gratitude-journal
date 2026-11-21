# Migrations

Files run automatically after every deploy (via `migrate.php`), each exactly once, in name order.

- Name: `NNNN_short_description.sql` or `.php` (e.g. `0002_add_task_priority.sql`).
- Never edit a migration that is already on production — add a new one.
- Write them idempotent where possible: `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS` (MariaDB 10.0.2+).
- SQL files: one statement per `;` at line end, no stored procedures.
- PHP files receive `$pdo` and run inside `migrate.php`; use them for data migrations or conditional DDL.
- Destructive changes (DROP, data rewrite) need a backup step first and explicit user approval.
