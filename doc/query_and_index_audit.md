# EduCore SMS — Query, Lazy-Loading & Index Audit (R3.2 / R3.3)

**Date:** 2026-11-20

## 1. N+1 Prevention (`Model::preventLazyLoading`)
- Enabled `Model::preventLazyLoading(!$this->app->isProduction())` in [AppServiceProvider.php](file:///c:/Users/Consultant%204/Desktop/Owais/Personal/Development/SMS/app/Providers/AppServiceProvider.php).
- **Detected & Fixed:** `StudentAdvanceService` line 176 (`->with('glAccount')` added before iterating `$advances` to build GL settlement memos) plus eager-load updates in finance feature tests (`DualInvoicingEngineTest`, `PaymentProcessingAndExpenseTest`).
- **Result:** All 196 automated tests pass with `preventLazyLoading` enforced.

## 2. Unbounded `->get()` Audit Summary
- **Paginated / Bounded List Views:** `StudentDirectory` (`paginate`), `TenantAuditLog` viewer (`/settings/audit-logs`, `paginate(25)`), `MigrateDocumentsToS3Command` (`chunk(100)`).
- **Bounded Tenant Reference Lookups:** Dropdowns for `AcademicSession`, `SchoolClass`, `SchoolSection`, `Subject`, `FeeCategory`, `Branch`, and `ChartOfAccount` are intentionally small per-tenant configuration sets (`< 200` rows per tenant) and remain `->get()` scoped by `tenant_id`.

## 3. PostgreSQL `EXPLAIN (ANALYZE, BUFFERS)` Sample
- **`students` (`WHERE tenant_id = ? AND status = 'active' LIMIT 25`):**
  - Plan: `Index Scan using students_tenant_id_id_unique on students`
  - Buffers: `shared hit=1` · Planning: `8.344 ms` · Execution: `0.126 ms`
- **Index Design Rule:** All 65 tenant-scoped tables lead composite unique/secondary indexes with `tenant_id` (164+ index definitions across migrations).
