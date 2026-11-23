# PostgreSQL RLS Coverage Classification (81 Public Tables)

**Date:** 2026-11-19 (R1.4 Audit)

- **64 tables** have `FORCE ROW LEVEL SECURITY` + active tenant isolation policies (`app.current_tenant_id`).
- **17 tables** do not have RLS enabled. They are classified into two exact groups below and guarded by `PhaseR1SecurityHardeningTest::test_all_tenant_scoped_tables_have_rls_or_are_explicitly_classified()`.

## Group A: 12 Global / Framework / Platform Tables (No `tenant_id` column)
| Table | Purpose |
| :--- | :--- |
| `cache`, `cache_locks` | Laravel cache store |
| `jobs`, `job_batches`, `failed_jobs` | Laravel queue tables (tenant scoping enforced by `EnforceTenantQueueMiddleware`) |
| `migrations` | Laravel schema migration history |
| `password_reset_tokens`, `sessions` | Framework auth/session tables |
| `permissions`, `role_has_permissions` | Global Spatie permission definitions |
| `subscription_plans` | Platform SaaS plan catalog |
| `tenants` | Root tenant registry (resolved prior to tenant context) |

## Group B: 5 Mixed Global + Tenant Tables (Nullable `tenant_id` allowlist)
| Table | Why RLS is not forced on table | Guard Mechanism |
| :--- | :--- | :--- |
| `users` | Super Admins have `tenant_id = NULL`; tenant staff have UUID `tenant_id` | `LoginController`, `IdentifyTenantBySubdomain`, `TenantStaffManager` tenant checks |
| `roles` | `super_admin` has `tenant_id = NULL`; tenant roles use `tenant_id` | Spatie team scoping (`setPermissionsTeamId`) + `assertCanGrantRole()` |
| `model_has_roles`, `model_has_permissions` | Spatie pivot tables holding both central (`NULL`/0) and tenant assignments | Spatie team scoping (`setPermissionsTeamId`) |
| `platform_audit_logs` | Holds both platform-wide events (`tenant_id = NULL`) and tenant impersonation events | Super-admin-only route access (`platform.manage_tenants` / `platform.view_audit_logs`) |
