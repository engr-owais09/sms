# SMS (EduCore SaaS) — Security & Multi-Tenancy Audit Report

**Initial Audit Date:** 2026-11-18  
**Batch 1 Remediation Date:** 2026-11-18  
**Branch:** `develop` (`e6628f5` + working-tree `SESSION_DOMAIN=null` + Batch 1 Security & Runtime Remediation)  
**Scope:** Multi-tenancy isolation, PostgreSQL RLS, Spatie RBAC, IDOR, Authentication/Session security, PII Encryption, Queue Context Isolation, Mass Assignment, and XSS/SQLi.

---

## 1. Security Control Status Matrix (Post-Batch 1 Remediation)

| Control ID | Security Domain | Pre-Batch 1 | Post-Batch 1 | Evidence / Verification |
| :--- | :--- | :---: | :---: | :--- |
| **SEC-01** | PostgreSQL RLS Policy Catalog (`64` tables) | **PASS** | **PASS** | `pg_class` + `pg_policies` inspection confirms all 64 tenant-owned tables have `relrowsecurity = true`, `relforcerowsecurity = true`, and `tenant_isolation` policies (`PlatformSecurityAndRlsAuditTest`). |
| **SEC-02** | Runtime Web DB Connection RLS & Session Context (`DEF-S01`) | **FAIL** | **PASS (Remediated)** | `IdentifyTenantBySubdomain` sets `PostgresTenantContext::set($tenant->id, isLocal: false)` on every tenant HTTP request and resets on `terminate()`. Migration `000051` grants least-privilege permissions to `educore_app` (`rolsuper=false, rolbypassrls=false`), adds `resolve_tenant_id_by_domain()`, and separates `migrations.connection` (`pgsql`) from `pgsql_app` (`Batch1SecurityAndRuntimeRemediationTest`). |
| **SEC-03** | Eloquent `TenantScope` & Auto-Assign `tenant_id` | **PASS** | **PASS** | `BelongsToTenant` global scope filters queries by `TenantContext::getTenantId()` and fails closed (`WHERE 1 = 0`) when no tenant context is active. |
| **SEC-04** | Cross-Tenant Query Scope Escapes (`DEF-S02`, `DEF-S03`) | **FAIL** | **PASS (Remediated)** | `TimetableBuilder` filters `User::where('tenant_id', $tenant->id)->where('status', 'active')` and validates teacher ownership in `assignSlot()`. `StudentAdmissionForm` uses `whereIn('status', ['upcoming', 'in_progress'])` and `CashierTerminal` wraps `orWhere` inside grouped closures (`AttendanceLivewireComponentTest`, `StudentLivewireComponentTest`). |
| **SEC-05** | Queue Worker Tenant Isolation (`DEF-S04`) | **FAIL** | **PASS (Remediated)** | Both `GenerateSchoolMonthlyVouchersJob` and `DispatchAbsenteeNotificationsJob` implement `TenantAwareJob` and attach `EnforceTenantQueueMiddleware`, which also preserves outer request context during `dispatchSync` (`Batch1SecurityAndRuntimeRemediationTest`, `FinanceLivewireComponentTest`). |
| **SEC-06** | Unauthenticated User Fallback in Livewire (`DEF-S05`) | **FAIL** | **PASS (Remediated)** | All `?? User::first()` fallbacks removed across `LeaveApprovalConsole`, `RollCallRegister`, `MarksEntryConsole`, `CashierShiftManager`, `CashierTerminal`, and `PaymentProcessingService`. Unauthenticated calls throw `AuthenticationException` / `InvalidArgumentException` (`Batch1SecurityAndRuntimeRemediationTest`). |
| **SEC-07** | Provisioned Tenant Owner Credentials (`DEF-F01`) | **FAIL** | **PENDING (Batch 2)** | `OnboardingWizard.php#L163` hardcodes `'owner_password' => 'Owner123456!'` (scheduled for Batch 2 remediation). |
| **SEC-08** | IDOR Protection on Route-Model Bound Print/PDF Routes | **PASS** | **PASS** | `FeeInvoice`, `FeePayment`, `ExamResult`, and `Student` use `BelongsToTenant`. Cross-tenant route-model binding returns `404 Not Found` (`AuthAndPdfRoutesTest`). |
| **SEC-09** | Spatie Permission Team Context (`team_id`) | **PASS** | **PASS** | `IdentifyTenantBySubdomain` sets `setPermissionsTeamId($tenant->id)` per request and resets to `null` on termination (`TenantScopedRbacTest`). |
| **SEC-10** | Authentication, Session Fixation & Host-Only Cookies | **PASS** | **PASS** | `LoginController` regenerates session on login, invalidates + regenerates CSRF token on logout, blocks `status !== 'active'`, and uses `SESSION_DOMAIN=null` (`AuthAndPdfRoutesTest`). |
| **SEC-11** | PII Field Encryption & Blind Indexing | **PASS** | **PASS** | `Guardians` and `Students` use AES-256 encryption at rest with HMAC-SHA256 blind indexes (`StudentAdmissionAndPiiTest`). |
| **SEC-12** | Ledger & Audit Immutability | **PASS** | **PASS** | PostgreSQL triggers enforce double-entry ledger immutability and balance invariants (`GeneralLedgerTest`). |
| **SEC-13** | Mass Assignment, CSRF & SQL Injection | **PASS** | **PASS** | `web` middleware group enforces `VerifyCsrfToken`; models define explicit `$fillable`; parameterized bindings used throughout. |

---

## 2. Batch 1 Security Vulnerability Remediation Log

### `DEF-S01` — PostgreSQL RLS Runtime Enforcement & Least-Privilege Role Separation (**REMEDIATED IN BATCH 1**)
- **Severity:** **Critical** -> **RESOLVED**
- **Root Cause:**
  1. `IdentifyTenantBySubdomain::handle()` never called `PostgresTenantContext::set($tenant->id)`.
  2. `PostgresTenantContext::set()` hardcoded `is_local = true` (`SELECT set_config('app.current_tenant_id', ?, true)`), which PostgreSQL immediately reverted after autocommitting the statement whenever called outside an explicit `BEGIN ... COMMIT` block (`DB::transactionLevel() === 0`).
  3. The non-superuser runtime role `educore_app` (`rolsuper = false, rolbypassrls = false`) lacked grants on 18 central/framework tables and 8 sequences, and could not resolve custom verified domains in `domains` before `app.current_tenant_id` was established.
- **Fix Implemented:**
  - Updated [PostgresTenantContext.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Core/Tenancy/PostgresTenantContext.php) so `set()` supports `$isLocal` (`false` for HTTP request session scope, `true` inside `TenantTransactionManager::run()`) and `reset()` clears both session and transaction state (`is_local = false`).
  - Updated [IdentifyTenantBySubdomain.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Core/Tenancy/Middleware/IdentifyTenantBySubdomain.php) to call `PostgresTenantContext::set($tenant->id, isLocal: false)` in `handle()`, `PostgresTenantContext::reset()` on central requests and `terminate()`, and `public.resolve_tenant_id_by_domain(?)` for pre-context custom domain resolution.
  - Added [0001_01_01_000051_grant_runtime_privileges_to_educore_app.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/database/migrations/0001_01_01_000051_grant_runtime_privileges_to_educore_app.php) granting least-privilege table/sequence permissions to `educore_app` and creating `public.resolve_tenant_id_by_domain(text)`.
  - Configured `'connection' => env('DB_MIGRATION_CONNECTION', 'pgsql')` under `migrations` in [config/database.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/config/database.php#L145-L149).
  - Updated [SubscriptionBillingService.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/SuperAdmin/Services/SubscriptionBillingService.php) to set `PostgresTenantContext::set($tenantId)` inside billing transactions.
- **Verification:** `Batch1SecurityAndRuntimeRemediationTest` (`test_def_s01_http_requests_set_and_reset_postgres_tenant_context_per_tenant`, `test_def_s01_non_superuser_educore_app_role_enforces_rls_and_has_least_privilege_grants`, `test_def_s01_dedicated_pgsql_app_connection_runs_as_educore_app_and_blocks_privilege_escalation`).

---

### `DEF-S02` — Cross-Tenant User Enumeration in `TimetableBuilder` (**REMEDIATED IN BATCH 1**)
- **Severity:** **High** -> **RESOLVED**
- **Root Cause:** [TimetableBuilder.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Attendance/Livewire/TimetableBuilder.php) queried `User::all()` in `render()` and did not verify teacher tenant ownership in `assignSlot()`.
- **Fix Implemented:** Scoped `$teachers` to `User::where('tenant_id', $tenant->id)->where('status', 'active')->orderBy('name')->get()` and added tenant ownership validation for `$this->teacherId` in `assignSlot()`.
- **Verification:** `AttendanceLivewireComponentTest::test_timetable_builder_isolates_teachers_by_tenant_and_rejects_cross_tenant_teacher`.

---

### `DEF-S03` / `DEF-F04` — Ungrouped `orWhere` Escaping `TenantScope` (**REMEDIATED IN BATCH 1**)
- **Severity:** **High** -> **RESOLVED**
- **Root Cause:** [StudentAdmissionForm.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Student/Livewire/StudentAdmissionForm.php#L164) chained an ungrouped `->orWhere('status', 'in_progress')`, and [CashierTerminal.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Finance/Livewire/CashierTerminal.php#L76-L99) chained ungrouped `->orWhere(...)` clauses on `FeeInvoice` and `Student`.
- **Fix Implemented:** Replaced with `->whereIn('status', ['upcoming', 'in_progress'])` in `StudentAdmissionForm` and grouped closures `->where(function ($q) use ($term) { ... })` in `CashierTerminal`.
- **Verification:** `StudentLivewireComponentTest::test_academy_student_admission_form_does_not_leak_upcoming_batches_from_other_tenants`.

---

### `DEF-S04` / `DEF-F06` — Queued Jobs Missing `TenantAwareJob` & `EnforceTenantQueueMiddleware` (**REMEDIATED IN BATCH 1**)
- **Severity:** **High / Critical** -> **RESOLVED**
- **Root Cause:** Neither [GenerateSchoolMonthlyVouchersJob.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Finance/Jobs/GenerateSchoolMonthlyVouchersJob.php) nor [DispatchAbsenteeNotificationsJob.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Attendance/Jobs/DispatchAbsenteeNotificationsJob.php) implemented `TenantAwareJob` or attached `EnforceTenantQueueMiddleware`, and `GenerateSchoolMonthlyVouchersJob` called undefined `TenantContext::setTenantId()`.
- **Fix Implemented:** Implemented `TenantAwareJob` and `middleware(): array { return [new EnforceTenantQueueMiddleware()]; }` on both jobs, fixed `GenerateSchoolMonthlyVouchersJob::handle()`, and updated [EnforceTenantQueueMiddleware.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Core/Tenancy/Middleware/EnforceTenantQueueMiddleware.php) to restore any outer HTTP request context after `dispatchSync`.
- **Verification:** `Batch1SecurityAndRuntimeRemediationTest::test_def_s04_queue_jobs_implement_tenant_aware_contract_and_attach_middleware` & `FinanceLivewireComponentTest::test_voucher_generation_console_generates_monthly_vouchers_and_preserves_tenant_context`.

---

### `DEF-S05` — Unsafe `auth()->user() ?? User::first()` Fallback (**REMEDIATED IN BATCH 1**)
- **Severity:** **High** -> **RESOLVED**
- **Root Cause:** Five Livewire components (`LeaveApprovalConsole`, `RollCallRegister`, `MarksEntryConsole`, `CashierShiftManager`, `CashierTerminal`) and `PaymentProcessingService` fell back to `User::first()` (`id = 1`, Platform Super Admin) when invoked without an authenticated user.
- **Fix Implemented:** Removed all `?? User::first()` expressions across `app/` and enforced `AuthenticationException` / `InvalidArgumentException` when unauthenticated.
- **Verification:** `Batch1SecurityAndRuntimeRemediationTest::test_def_s05_unauthenticated_livewire_and_service_calls_do_not_fallback_to_super_admin`.

---

## 3. Remaining Security / Functional Items (Scheduled for Batch 2)

- **`DEF-F01` (Onboarding Wizard Default Password):** [OnboardingWizard.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Onboarding/Livewire/OnboardingWizard.php#L163) hardcodes `'owner_password' => 'Owner123456!'` (Batch 2).
