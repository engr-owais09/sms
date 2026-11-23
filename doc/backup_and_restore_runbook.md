# EduCore SMS — Backup & Restore Runbook (R2.2 / R2.3)

## 1. Backup Strategy
- **Logical Nightly Snapshot:** `php artisan educore:backup-database --retention-days=14` (scheduled daily at `02:00` via `routes/console.php` with `withoutOverlapping()` and `onOneServer()`).
  - Produces PostgreSQL custom-format archive (`pg_dump -Fc`).
  - Encrypts at rest using AES-256-CBC when `EDUCORE_BACKUP_PASSPHRASE` is set in the environment.
  - Prunes local artifacts older than 14 days.
- **Continuous WAL Archiving / PITR (Production Tier 2+):**
  - Enable `archive_mode = on` and `wal_level = replica` using `pgBackRest` or `WAL-G` shipping encrypted WAL segments to S3-compatible object storage (target **RPO <= 15 minutes**).
- **Alerting:** Monitor structured log events `Database backup completed.` and `Database backup failed.` in log aggregation / Sentry.

## 2. Restore Procedure (`pg_restore`)
1. Create a clean target database:
   ```sql
   CREATE DATABASE educore_saas_restore_target OWNER educore_migrator;
   ```
2. If encrypted (`.dump.enc`), decrypt first using `EDUCORE_BACKUP_PASSPHRASE`.
3. Restore with `pg_restore`:
   ```powershell
   pg_restore -h 127.0.0.1 -p 5432 -U educore_migrator -d educore_saas_restore_target --clean --if-exists --no-owner path\to\backup.dump
   ```
4. Verify row counts, RLS policies (`relrowsecurity = true` on 65 tenant tables), and run `php artisan educore:verify-db-roles`.
