# Sprint 0 — Test cases

## Prerequisites

- [ ] PHP 8.2+
- [ ] Composer dependencies installed
- [ ] MySQL 8 running; `.env` configured
- [ ] `php database/migrate.php` completed
- [ ] `php database/seed.php` completed

## Manual tests

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 1 | Health endpoint | `curl http://localhost:8080/health` | JSON `status`, `db`, `redis`, `timestamp`; HTTP 200 if DB up | ☐ |
| 2 | Migrations | Run `php database/migrate.php` twice | No fatal errors; 46 tables exist | ☐ |
| 3 | Module seed | `SELECT COUNT(*) FROM module_catalog` | ≥ 29 rows | ☐ |
| 4 | Drug seed | `SELECT COUNT(*) FROM drugs` | ≥ 1000 rows | ☐ |
| 5 | Remedy seed | `SELECT COUNT(*) FROM remedies` | ≥ 500 rows | ☐ |
| 6 | Demo tenant | `SELECT slug FROM tenants WHERE slug='demo'` | 1 row | ☐ |
| 7 | Tenant API | `DEV_CLINIC_SLUG=demo` → `curl localhost:8080/api/v1/ping` | `{"pong":true}` | ☐ |
| 8 | Admin route | `curl localhost:8080/admin/` | `{"admin":"ok"}` (no tenant required) | ☐ |

## Automated tests

- [ ] `vendor/bin/phpunit` — QueryBuilder forClinic test passes

## Sign-off

- Date:
- Notes:
