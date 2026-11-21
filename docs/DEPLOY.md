# Deploy & development workflow

## Facts (verified 2026-11-21)
- Hosting: PS.KZ shared Plesk, server `srv-plesk24.ps.kz`, subscription `luckysport.kz`, system user `luckysp`
- Site docroot: `/journal.luckysport.kz` (Plesk domain id 1008)
- PHP: **7.4.33** → no PHP 8 syntax/functions
- GitHub repo: `mirkormanbek/gratitude-journal` (public — never commit secrets)

## Pipeline
```
feature branch → PR → GitHub Actions checks
             → merge to main → checks again → Actions pushes the commit to `production`
             → Plesk scheduled task `tools/plesk_pull.php <webhook-url>` (PHP 7.4, */2) POSTs the Plesk Git webhook
               → Plesk Git pulls `production` into /journal.luckysport.kz
             → first request after deploy: db.php applies new migrations (migrations_lib.php)
               (backup: Plesk scheduled task `journal.luckysport.kz/migrate.php`, PHP 7.4, */5)
             → Actions smoke-tests https://journal.luckysport.kz/login.php
```
Plesk deploys ONLY `production`; never push to it by hand.

## Server-only files (not in git, never overwritten by deploy)
`config.php` (DB password, OpenAI key, Quran Foundation credentials), uploads/backups if any.

## Not web-accessible
`.htaccess` blocks `docs/`, `tools/`, `migrations/`, `.github/`, `CLAUDE.md`, `README.txt`, `migrate.php`, `config*.php`, `db.php`.

## Migrations
- New file `migrations/NNNN_name.sql|php`, never edit an applied one (see `migrations/README.md`).
- Applied automatically: `db.php` → `growth_auto_migrate()` compares migration file list with `.migrations_stamp`
  and runs pending ones under a DB lock; failures go to the PHP error log and are retried on the next request.
- Backup runner: Plesk scheduled task `migrate.php` every 5 min (CLI only; HTTP blocked; errors e-mailed).
- CI runs all migrations twice on MariaDB 10.6 (`tools/test_migrations.php`) — they must be idempotent.
- Deploy delivers code and migration together: write migrations so the OLD code keeps working too
  (add columns/tables first; remove things only in a later release).
- Applied names are stored in table `schema_migrations`.
- Before destructive changes: Plesk → Databases → export dump.

## Plesk objects (do not delete)
- Git → repository `growth-os` (branch `production`, automatic deploy to `/journal.luckysport.kz`)
- Scheduled tasks: migrate.php (*/5) and the Plesk Git webhook poll (*/2)

## Rollback
`git revert <sha>` on main → pipeline releases the previous code. Migrations are forward-only.
