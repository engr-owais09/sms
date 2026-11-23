# EduCore SMS — Environment & Database Setup Guide

This guide documents the separation between development, isolated testing, schema migrations, and least-privilege runtime database access.

---

## 1. Database Isolation (Dev vs Testing)

- **Development DB:** `educore_saas` (configured in `.env`)
- **Automated Testing DB:** `educore_saas_testing` (configured in `phpunit.xml`)
- `phpunit.xml` forces `APP_ENV=testing`, `DB_DATABASE=educore_saas_testing`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, and `MAIL_MAILER=array` so running `php artisan test` never mutates development data.

---

## 2. Database Roles (`educore_migrator` vs `educore_app`)

PostgreSQL Row-Level Security (RLS) is bypassed by `SUPERUSER` or `BYPASSRLS` roles. Therefore:

1. **Migration / Schema Role (`pgsql` connection -> `educore_migrator`):**
   - Used exclusively by `php artisan migrate` and schema DDL operations.
2. **Runtime Application Role (`pgsql_app` connection -> `educore_app`):**
   - Non-superuser, no `BYPASSRLS` privilege.
   - Granted least-privilege `SELECT, INSERT, UPDATE, DELETE` via migration `0001_01_01_000051_grant_runtime_privileges_to_educore_app.php`.
   - Set `DB_APP_USERNAME=educore_app` and `DB_APP_PASSWORD=...` in `.env` so runtime queries enforce PostgreSQL RLS policies (`app.current_tenant_id`).

---

## 3. Local Verification Commands (Dry-Run CI)

```powershell
# Dependency security audit
composer audit

# Code style check
php vendor/bin/pint --test

# Full test suite against isolated educore_saas_testing
php artisan test
```

---

## 4. Super Admin & Pilot Seeding (No Hardcoded Credentials)

`RoleAndPermissionSeeder` seeds roles and permissions only; it no longer embeds a default Super Admin password.

- **Provision Super Admin interactively or via CLI options:**
  ```powershell
  php artisan educore:create-super-admin
  ```
  (Or set `EDUCORE_SUPER_ADMIN_EMAIL` and `EDUCORE_SUPER_ADMIN_PASSWORD` with at least 12 characters before running `RoleAndPermissionSeeder`.)
- **Seed local pilot campus (development/staging only, never production):**
  ```powershell
  php artisan educore:seed-pilot
  ```

