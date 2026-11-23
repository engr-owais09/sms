# EduCore SMS — PostgreSQL Restore Drill Report (R2.3)

**Date:** 2026-11-19  
**Source Database:** `educore_saas_testing` (PostgreSQL 16.10)  
**Target Drill Database:** `educore_saas_restore_drill` (isolated temporary DB, dropped after verification)  
**Tooling:** `C:\Program Files\PostgreSQL\16\bin\pg_dump.exe` (`-Fc`) & `pg_restore.exe`

## Execution Summary
1. Created custom-format logical dump (`pg_dump -Fc`) from `educore_saas_testing`.
2. Created clean database `educore_saas_restore_drill` owned by `educore_migrator`.
3. Restored dump using `pg_restore --no-owner`.
4. Executed verification query against `educore_saas_restore_drill`:
   - Public tables restored: **82**
   - RLS-enabled tables restored: **65**
   - Migration records restored: **54**
5. Dropped `educore_saas_restore_drill` and removed temporary dump directory.

## Measured Result
- **Status:** Passed
- **Measured RTO (schema + baseline test dataset):** **4.07 seconds**
- **Operational Finding Caught & Fixed:** System PATH defaulted to PostgreSQL 15 `pg_dump.exe` (15.15), which aborted against PostgreSQL 16.10 server. Updated `BackupDatabaseCommand` to support `EDUCORE_PG_DUMP_PATH` and auto-prefer PostgreSQL 16 on Windows when present.
