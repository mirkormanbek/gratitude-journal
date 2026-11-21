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
             → GitHub webhook → Plesk Git pulls `production` into /journal.luckysport.kz
             → Plesk deployment action: php migrate.php (DB migrations)
             → Actions smoke-tests https://journal.luckysport.kz/login.php
```
Plesk deploys ONLY `production`; never push to it by hand.

## Server-only files (not in git, never overwritten by deploy)
`config.php` (DB password, OpenAI key, Quran Foundation credentials), uploads/backups if any.

## Not web-accessible
`.htaccess` blocks `docs/`, `tools/`, `migrations/`, `.github/`, `CLAUDE.md`, `README.txt`, `migrate.php`, `config*.php`, `db.php`.

## Migrations
- New file `migrations/NNNN_name.sql|php`, never edit an applied one (see `migrations/README.md`).
- Applied automatically by Plesk deployment action `php migrate.php` (CLI only; HTTP is blocked).
- Applied names are stored in table `schema_migrations`.
- Before destructive changes: Plesk → Databases → export dump.

## Rollback
`git revert <sha>` on main → pipeline releases the previous code. Migrations are forward-only.
