# SMS (EduCore SaaS) — Batch 1: Critical Security & Runtime Remediation Report

**Date:** November 18, 2026  
**Branch:** `develop` (`e6628f5` + working-tree changes)  
**Test Suite Result:** **149 / 149 Tests Passing (959 Assertions, 0 Failures)**

---

## Executive Summary

Batch 1 remediated all 5 approved critical/high security and runtime findings (`DEF-S01`, `DEF-S02`, `DEF-S03`/`DEF-F04`, `DEF-S04`/`DEF-F06`, and `DEF-S05`) without modifying any Batch 2/Batch 3 scope or weakening any RBAC/RLS controls. Eight new regression tests (58 new assertions) were added and verified against PostgreSQL 16.10 under both the migration account (`educore_migrator`) and the least-privilege application account (`educore_app`).

---

## Finding-by-Finding Remediation Details

### 1. `DEF-S01` — PostgreSQL RLS Runtime Enforcement & Least-Privilege Role Separation

- **Original Issue:**
  1. `IdentifyTenantBySubdomain::handle()` never called `PostgresTenantContext::set($tenant->id)` during HTTP requests.
  2. `PostgresTenantContext::set()` hardcoded `is_local = true` (`SELECT set_config('app.current_tenant_id', ?, true)`), which PostgreSQL immediately reverted upon autocommitting the statement whenever called outside an explicit `BEGIN ... COMMIT` transaction block (`DB::transactionLevel() === 0`).
  3. The runtime database role `educore_app` (`rolsuper = false`, `rolbypassrls = false`) was missing table privileges on 18 central/framework tables and 8 sequences, and could not resolve custom verified domains in `domains` prior to `app.current_tenant_id` being set.
- **Root Cause:**
  - `IdentifyTenantBySubdomain` only set the PHP `TenantContext` singleton in `handle()` while calling `PostgresTenantContext::reset()` in `terminate()`.
  - `PostgresTenantContext` assumed every caller was inside `TenantTransactionManager::run()`, ignoring standalone HTTP `GET` queries.
  - Earlier migrations (`000007`, `000025`–`000049`) only granted table permissions on tenant-scoped tables and omitted framework/central tables (`tenants`, `users`, `sessions`, `cache`, `jobs`, `roles`, `permissions`, `platform_audit_logs`, `subscription_plans`) and sequences.
- **Files Modified / Created:**
  - [PostgresTenantContext.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Core/Tenancy/PostgresTenantContext.php)
  - [TenantTransactionManager.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Core/Tenancy/TenantTransactionManager.php)
  - [IdentifyTenantBySubdomain.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Core/Tenancy/Middleware/IdentifyTenantBySubdomain.php)
  - [SubscriptionBillingService.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/SuperAdmin/Services/SubscriptionBillingService.php)
  - [config/database.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/config/database.php)
  - [0001_01_01_000051_grant_runtime_privileges_to_educore_app.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/database/migrations/0001_01_01_000051_grant_runtime_privileges_to_educore_app.php)
- **Fix Implemented:**
  - Updated `PostgresTenantContext::set(string $tenantId, ?string $connection = null, ?bool $isLocal = null)` to support session-scoped configuration (`isLocal: false`) for HTTP requests and transaction-local configuration (`isLocal: true`) inside `TenantTransactionManager::run()`. Updated `reset()` to use `is_local = false` so both session and transaction settings are wiped cleanly.
  - Updated `IdentifyTenantBySubdomain::handle()` to call `PostgresTenantContext::set($tenant->id, isLocal: false)` immediately upon resolving a tenant (before querying `$tenant->branches()`), and `PostgresTenantContext::reset()` when no tenant is resolved or on `terminate()`.
  - Created migration `0001_01_01_000051_grant_runtime_privileges_to_educore_app.php` to grant `SELECT, INSERT, UPDATE, DELETE` on all application/framework tables (`SELECT` only on `migrations`), `USAGE, SELECT, UPDATE` on all sequences in `public`, and a narrow `SECURITY DEFINER` function `public.resolve_tenant_id_by_domain(text)` (`SET search_path = public`) for custom domain resolution before tenant context is established.
  - Added `'connection' => env('DB_MIGRATION_CONNECTION', 'pgsql')` to `migrations` in `config/database.php` so schema migrations always run on `pgsql` (`educore_migrator`), keeping runtime `pgsql_app` (`educore_app`) strictly separated.
- **Regression Tests Added:**
  - `Batch1SecurityAndRuntimeRemediationTest::test_def_s01_http_requests_set_and_reset_postgres_tenant_context_per_tenant`
  - `Batch1SecurityAndRuntimeRemediationTest::test_def_s01_non_superuser_educore_app_role_enforces_rls_and_has_least_privilege_grants`
  - `Batch1SecurityAndRuntimeRemediationTest::test_def_s01_dedicated_pgsql_app_connection_runs_as_educore_app_and_blocks_privilege_escalation`
- **Before / After Verification:**
  - *Before:* Outside an explicit SQL transaction, `PostgresTenantContext::get()` returned `NULL` immediately after `PostgresTenantContext::set()`; HTTP requests had empty `current_setting('app.current_tenant_id', true)`; `educore_app` lacked privileges on 18 tables and 8 sequences.
  - *After:* HTTP requests to `http://alpha.localhost` and `http://beta.localhost` populate `current_setting('app.current_tenant_id', true)` with each tenant's UUID and reset to `NULL` on response termination; `educore_app` (`rolsuper = false, rolbypassrls = false`) has grants on all application tables/sequences, cannot escalate via `SET ROLE educore_migrator` (`42501`), resolves custom domains via `resolve_tenant_id_by_domain()`, sees `0 rows` from other tenants, and is blocked by PostgreSQL RLS (`42501`) on cross-tenant `INSERT`.
- **Remaining Limitations:**
  - Local `.env` and `phpunit.xml` retain `DB_CONNECTION=pgsql` as the default connection because existing multi-tenant test `setUp()` methods seed records across multiple tenants before setting a tenant context, and Super Admin central dashboard aggregates (`Branch::withoutGlobalScopes()->count()`, `SubscriptionInvoice::latest()->get()`) query across tenants without a single `app.current_tenant_id`. To switch `DB_CONNECTION=pgsql_app` globally for Central Super Admin routes as well, Central Super Admin aggregate queries can either use a dedicated read model or a signed session variable policy in a future phase.

---

### 2. `DEF-S02` — `TimetableBuilder` Cross-Tenant Teacher Exposure

- **Original Issue:** `TimetableBuilder::render()` executed `$teachers = User::all();`, exposing all users from every tenant and Platform Super Admins in the teacher dropdown.
- **Root Cause:** `User` is a global table without `BelongsToTenant`, and `TimetableBuilder` omitted `->where('tenant_id', $tenant->id)`.
- **Files Modified:**
  - [TimetableBuilder.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Attendance/Livewire/TimetableBuilder.php#L63-L105)
- **Fix Implemented:**
  - Scoped `$teachers` in `render()` to `User::where('tenant_id', $tenant->id)->where('status', 'active')->orderBy('name')->get()`.
  - Added explicit tenant ownership validation in `assignSlot()` (`User::where('tenant_id', $tenant->id)->where('id', $this->teacherId)->exists()`).
- **Regression Test Added:**
  - `AttendanceLivewireComponentTest::test_timetable_builder_isolates_teachers_by_tenant_and_rejects_cross_tenant_teacher`
- **Before / After Verification:**
  - *Before:* Mounting `TimetableBuilder` returned teachers from all tenants and Super Admins.
  - *After:* Only active users belonging to the current tenant are returned, and attempting to submit another tenant's teacher ID returns `'Selected teacher does not belong to this workspace.'`.
- **Remaining Limitations:** None.

---

### 3. `DEF-S03` / `DEF-F04` — `StudentAdmissionForm` (and `CashierTerminal`) Ungrouped `orWhere` Query Escape

- **Original Issue:** `StudentAdmissionForm::render()` ran `AcademyBatch::with('course')->where('status', 'upcoming')->orWhere('status', 'in_progress')->get()`, which compiled to `WHERE status = 'upcoming' OR (status = 'in_progress' AND tenant_id = ?)`, leaking `upcoming` batches from other tenants when RLS was bypassed. A similar ungrouped `orWhere` pattern existed in `CashierTerminal::updatedSearchQuery()`.
- **Root Cause:** Top-level `.orWhere()` clauses without grouping closures or `whereIn()` break boolean precedence when combined with Eloquent global scopes (`AND tenant_id = ?`).
- **Files Modified:**
  - [StudentAdmissionForm.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Student/Livewire/StudentAdmissionForm.php#L164)
  - [CashierTerminal.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Finance/Livewire/CashierTerminal.php#L75-L99)
- **Fix Implemented:**
  - Replaced `->where('status', 'upcoming')->orWhere('status', 'in_progress')` with `->whereIn('status', ['upcoming', 'in_progress'])` in `StudentAdmissionForm.php`.
  - Wrapped the invoice and student `orWhere` search conditions in `CashierTerminal.php` inside `->where(function ($q) use ($term) { ... })`.
- **Regression Test Added:**
  - `StudentLivewireComponentTest::test_academy_student_admission_form_does_not_leak_upcoming_batches_from_other_tenants`
- **Before / After Verification:**
  - *Before:* `Academy Tenant A`'s admission form displayed `upcoming` batches belonging to `Academy Tenant B`.
  - *After:* `Academy Tenant A` only sees its own `upcoming` and `in_progress` batches (`assertDontSee('LEAKED-STEP-UPCOMING-BATCH')`).
- **Remaining Limitations:** Academy guardian linking (`DEF-F05`) remains scheduled for Batch 2 per scope rules.

---

### 4. `DEF-S04` / `DEF-F06` — Tenant-Aware Queue Jobs & Monthly Voucher Generation Crash

- **Original Issue:**
  1. Clicking "Generate Class Vouchers" in `/finance/vouchers` crashed with `Error: Call to undefined method App\Core\Tenancy\TenantContext::setTenantId()` at `GenerateSchoolMonthlyVouchersJob.php#L34`.
  2. Neither `GenerateSchoolMonthlyVouchersJob` nor `DispatchAbsenteeNotificationsJob` implemented `TenantAwareJob` or attached `EnforceTenantQueueMiddleware`.
  3. Synchronous job dispatch (`dispatchSync`) inside an active HTTP request caused `EnforceTenantQueueMiddleware` to wipe the parent request's `TenantContext` and `PostgresTenantContext` in its `finally` block.
- **Root Cause:**
  - `GenerateSchoolMonthlyVouchersJob` called a non-existent `setTenantId()` method instead of `setTenant()` and omitted `TenantAwareJob`.
  - `EnforceTenantQueueMiddleware` assumed it only ran in daemon workers and unconditionally cleared context after execution.
- **Files Modified:**
  - [GenerateSchoolMonthlyVouchersJob.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Finance/Jobs/GenerateSchoolMonthlyVouchersJob.php)
  - [DispatchAbsenteeNotificationsJob.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Attendance/Jobs/DispatchAbsenteeNotificationsJob.php)
  - [EnforceTenantQueueMiddleware.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Core/Tenancy/Middleware/EnforceTenantQueueMiddleware.php)
- **Fix Implemented:**
  - Implemented `TenantAwareJob` (`getTenantId(): string`) and `middleware(): array { return [new EnforceTenantQueueMiddleware()]; }` on both `GenerateSchoolMonthlyVouchersJob` and `DispatchAbsenteeNotificationsJob`.
  - Replaced the broken `setTenantId()` call in `GenerateSchoolMonthlyVouchersJob::handle()` with `Tenant::findOrFail($this->tenantId)` + `setTenant($tenant)` + `PostgresTenantContext::set($tenant->id)` and removed `Student::withoutGlobalScopes()`.
  - Updated `EnforceTenantQueueMiddleware::handle()` to snapshot any pre-existing tenant/branch/Postgres context prior to running the job and restore it in `finally` when `dispatchSync` runs inside an active HTTP request for that tenant (while still clearing completely in standalone queue workers).
- **Regression Tests Added:**
  - `FinanceLivewireComponentTest::test_voucher_generation_console_generates_monthly_vouchers_and_preserves_tenant_context`
  - `Batch1SecurityAndRuntimeRemediationTest::test_def_s04_queue_jobs_implement_tenant_aware_contract_and_attach_middleware`
- **Before / After Verification:**
  - *Before:* `VoucherGenerationConsole::generateBatch()` caught `Call to undefined method TenantContext::setTenantId()` and produced 0 invoices.
  - *After:* `VoucherGenerationConsole::generateBatch()` generates monthly invoices and GL journal entries cleanly, preserves `TenantContext` after `dispatchSync`, and displays `"Batch voucher generation complete! Invoices for period 2026-11 processed. Total vouchers in period: 1."`.
- **Remaining Limitations:** None.

---

### 5. `DEF-S05` — Unsafe `User::first()` Authentication Fallback

- **Original Issue:** Five Livewire components and `PaymentProcessingService` used `auth()->user() ?? User::first()`, silently attributing unauthenticated calls to `User::first()` (`id = 1`, Platform Super Admin).
- **Root Cause:** Convenience fallback left in Livewire actions and payment service during initial development.
- **Files Modified:**
  - [LeaveApprovalConsole.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Attendance/Livewire/LeaveApprovalConsole.php)
  - [RollCallRegister.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Attendance/Livewire/RollCallRegister.php)
  - [MarksEntryConsole.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Exam/Livewire/MarksEntryConsole.php)
  - [CashierShiftManager.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Finance/Livewire/CashierShiftManager.php)
  - [CashierTerminal.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Finance/Livewire/CashierTerminal.php)
  - [PaymentProcessingService.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Modules/Finance/Services/PaymentProcessingService.php)
- **Fix Implemented:**
  - Removed all `?? User::first()` expressions across `app/` (verified with recursive search returning 0 matches).
  - Enforced `AuthenticationException` on unauthenticated mutating Livewire actions (`openShift`, `verifySession`, `processPayment`, `saveAttendance`, `approve`, `reject`, `saveMarks`) and `InvalidArgumentException` in `PaymentProcessingService::processPayment()` when neither `received_by_user_id` nor `auth()->id()` is present.
- **Regression Test Added:**
  - `Batch1SecurityAndRuntimeRemediationTest::test_def_s05_unauthenticated_livewire_and_service_calls_do_not_fallback_to_super_admin`
- **Before / After Verification:**
  - *Before:* Unauthenticated calls executed under `User::first()` (`superadmin@educore.test`).
  - *After:* Unauthenticated calls immediately throw `AuthenticationException` / `InvalidArgumentException` with zero database mutations.
- **Remaining Limitations:** None.
