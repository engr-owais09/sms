# EduCore SMS — Gate A Review Report (Safe Pilot Readiness)

**Date:** 2026-11-19  
**Roadmap Phases Covered:** R0 (Foundations), R1 (Security Blockers), R2 (Data Durability)  
**Gate A Status:** **Ready on Backend/DB; Blocked on `R2.7` (Live Browser Acceptance Round 1)**

---

## 1. What Was Verified

### Phase R0 — Foundations & Safety Net (`R0.1–R0.5` Complete)
- **R0.1:** Uncommitted working tree inventoried in [roadmap_changelog.md](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/doc/roadmap_changelog.md) (Decision D1: no commit without owner instruction).
- **R0.2:** CI pipeline created at [.github/workflows/ci.yml](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/.github/workflows/ci.yml) (`composer audit`, `npm audit`, Postgres 16 migrate, `php artisan test`, Pint check).
- **R0.3 & R0.4:** Isolated test DB (`educore_saas_testing`), `educore_migrator` vs `educore_app` separation, and production checklist documented in [environment_setup.md](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/doc/environment_setup.md) and [production_config_checklist.md](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/doc/production_config_checklist.md).
- **R0.5:** Hardcoded super-admin password removed from [RoleAndPermissionSeeder.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/database/seeders/RoleAndPermissionSeeder.php); added `php artisan educore:create-super-admin` ([CreateSuperAdminCommand.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Console/Commands/CreateSuperAdminCommand.php)).

### Phase R1 — Security Blockers (`R1.1–R1.11` Complete)
- **R1.1 & R1.2:** Per-email+IP login throttling (HTTP `429` after 5 failures) + security headers middleware (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Content-Security-Policy-Report-Only`, prod HSTS).
- **R1.3 & R1.4:** `TransactionBeginning` `SET LOCAL` listener + [adr_001_tenant_context_pool_safety.md](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/doc/adr_001_tenant_context_pool_safety.md), and full classification of all 17 non-RLS tables + CI schema guard in [rls_classification.md](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/doc/rls_classification.md).
- **R1.5 & R1.6:** Idle session timeout (`30m`), `logoutOtherDevices`, `Password::defaults()`, and `must_change_password` first-login enforcement for provisioned staff.
- **R1.7:** RFC 6238 TOTP 2FA (`TotpService`) + recovery codes + `/two-factor-challenge` + `/account/password` security settings.
- **R1.8 & R1.9:** Immutable `tenant_audit_logs` table with `FORCE ROW LEVEL SECURITY` + `/settings/audit-logs` viewer + signed private student document downloads (`/students/documents/{document}/download`).
- **R1.10 & R1.11:** `composer audit` (0 advisories), `npm audit` in CI, and `php artisan educore:verify-db-roles` verifying `educore_app` is non-superuser without `BYPASSRLS`.

### Phase R2 — Data Durability (`R2.1–R2.6` Complete, `R2.7` Blocked)
- **R2.1:** 4 scheduled tasks configured in [console.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/routes/console.php) (`withoutOverlapping`, `onOneServer`).
- **R2.2 & R2.3:** `educore:backup-database` (`pg_dump -Fc`, AES-256-CBC optional encryption, 14-day retention) + [backup_and_restore_runbook.md](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/doc/backup_and_restore_runbook.md). Isolated PostgreSQL 16 restore drill passed (`82` tables, `65` RLS tables, `54` migrations, **4.07s RTO** — [restore_drill_report.md](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/doc/restore_drill_report.md)).
- **R2.4, R2.5 & R2.6:** Isolated/signed/audited per-tenant ZIP export (`/settings/export`), configurable `documents_disk` + `educore:migrate-documents-to-s3`, and `TenantOffboardingService` with `legal_hold` protection.

---

## 2. Automated Test Baseline

- **`php artisan test`:** **193 passed, 0 failed (1,489 assertions)** in `66.43s`.

---

## 3. What Was NOT Verified & Residual Risks

1. **`R2.7` Browser Acceptance Round 1 — Blocked:** `DevToolsActivePort` is not active (`false`), so live Chrome rendering of **MT-01…MT-23** (desktop/tablet/mobile, Urdu RTL, A4/80mm PDF visual layout) has not yet been executed.
2. **Pre-existing Code Style & Static Analysis (`R0.2`):** `pint --test` has `continue-on-error: true` in CI due to pre-existing formatting diffs; Larastan is not yet installed.
3. **Live PgBouncer & S3:** `SET LOCAL` pool safety and `educore:migrate-documents-to-s3` were verified via tests and `--dry-run`; live PgBouncer daemon and S3 bucket (`league/flysystem-aws-s3-v3`) are not provisioned in local dev.

---

## 4. Owner Decision Needed to Clear Gate A

- Enable Chrome remote debugging (`chrome://inspect/#remote-debugging`) or run [sms_manual_testing_checklist.md](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/doc/sms_manual_testing_checklist.md) manually to close `R2.7`, then approve proceeding to **Phase R3 (Performance & Scalability)** and **Phase R4 (Bulk Data Entry & Export)**.
