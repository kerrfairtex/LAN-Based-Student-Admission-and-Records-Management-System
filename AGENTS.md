# AGENTS.md

## Cursor Cloud specific instructions

TRAC JHS SARMS is a single PHP 8 + MySQL (MariaDB) app (no Composer/npm/build step). It is served
directly from the repo root. Standard setup/run steps live in `README.md`; the notes below cover
only the non-obvious, cloud-VM specific details.

### Services

| Service | Required | Start command | Notes |
|---------|----------|---------------|-------|
| MariaDB (MySQL) | Yes | `sudo service mariadb start` | Not started on boot; start it each session. |
| PHP dev server | Yes | `php -S 0.0.0.0:8000` (run from repo root) | App at http://localhost:8000/ . Use instead of Apache/XAMPP. |

PHP 8.3 (`pdo_mysql`, `mbstring`, `xml`) and `mariadb-server` are provided by the VM snapshot — do
not reinstall them.

### Database

- DB name `trac_jhs_sarms`, schema + seed data in `database/schema.sql` (already includes Phase 2/3
  tables; the files under `database/migrations/` are only for upgrading older installs).
- The app connects with the README default `root` / empty password. To make that work for the PHP
  process (which runs as the `ubuntu` user, not `root`), two non-obvious changes were baked into the
  snapshot: `root@localhost` was switched to `mysql_native_password` with an empty password, and a
  PHP drop-in (`/etc/php/8.3/cli/conf.d/99-mariadb-socket.ini`) points the default MySQL socket to
  MariaDB's real socket at `/run/mysqld/mysqld.sock` (PHP's compiled default `/var/run/mysqld/...`
  does not exist here). Because of this, `host=localhost` works out of the box — no `DB_*` env vars
  needed. `config/database.php` still honors `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS` overrides.
- If the data dir is ever empty (no `trac_jhs_sarms` database after starting MariaDB), re-import:
  `sudo mysql < database/schema.sql`.
- Default app logins: `registrar` / `Registrar@2026` (admin) and `encoder` / `Encoder@2026` (staff).

### Lint / test

- No automated test suite exists. Lint PHP with `php -l <file>` (e.g. loop over `*.php`).
- Known pre-existing issue: `modules/admin/settings.php` has a syntax error (a stray `</div>` at
  the end with no page body) and fails `php -l`. This is a bug in the committed code, unrelated to
  environment setup — leave it unless you are explicitly fixing that file.

### Gotchas

- The PHP built-in server does **not** enforce `.htaccess`, so the deny rules for `config/`,
  `includes/`, `database/`, and `backups/` are inactive in local dev. Fine for development; do not
  rely on them locally.
