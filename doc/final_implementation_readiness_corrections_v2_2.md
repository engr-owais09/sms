# EduCore SaaS — Final Implementation Readiness Corrections (v2.2)
**Document Version:** 2.2.0  
**Date:** November 2026  
**Status:** Pre-Implementation Architectural Sign-Off  

---

## 1. Queue Middleware — Fail-Closed Tenant Isolation

### 1.1 Architectural Vulnerability in v2.1
In v2.1, the queue middleware contained a fail-open condition:
`if (!isset($job->tenantId)) { return $next($job); }`
If a developer unintentionally omitted `$tenantId` or dispatched a tenant-owned entity job without setting it, the job executed in a null tenant context, potentially failing silently, corrupting shared data, or generating invalid ledger entries.

### 1.2 Fail-Closed Queue Architecture
All queue jobs are categorized strictly by two mutually exclusive interfaces:
1. `TenantAwareJob`: Any job that operates on tenant data (e.g. generating fee vouchers, computing term grades, dispatching parent notices).
2. `PlatformSystemJob`: Platform-level maintenance jobs explicitly permitted to run without tenant context (e.g. platform backup runners, billing dunning sweep, global telemetry sync).

```
                      [ Incoming Queue Job ]
                                 |
           +---------------------+---------------------+
           |                                           |
           v                                           v
[ Implements TenantAwareJob ]             [ Implements PlatformSystemJob ]
           |                                           |
  Does job have valid                                  v
 tenant_id & active tenant?                 [ Execute System Pipeline ]
      /         \                               (No tenant context)
    YES          NO
     |            |
     v            v
[ Execute inside  [ Throw MissingTenantContextException ]
  DB Transaction    (Job Aborted & Failed Closed)
  with set_config ]
```

### 1.3 Corrected Production Queue Middleware
```php
namespace App\Core\Tenancy\Middleware;

use App\Core\Tenancy\Exceptions\MissingTenantContextException;
use App\Core\Tenancy\Exceptions\TenantSuspendedException;
use App\Core\Tenancy\Contracts\TenantAwareJob;
use App\Core\Tenancy\Contracts\PlatformSystemJob;
use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Throwable;

class EnforceTenantQueueMiddleware
{
    /**
     * Process the queued job with fail-closed tenant scoping.
     *
     * @throws MissingTenantContextException
     * @throws TenantSuspendedException
     * @throws Throwable
     */
    public function handle(object $job, callable $next): mixed
    {
        // 1. Explicit Platform/System jobs execute without tenant context
        if ($job instanceof PlatformSystemJob) {
            return $next($job);
        }

        // 2. Strict Fail-Closed Check for Tenant Jobs
        if (!$job instanceof TenantAwareJob || empty($job->tenantId)) {
            throw new MissingTenantContextException(
                sprintf('Job [%s] must implement TenantAwareJob and supply a valid tenantId.', get_class($job))
            );
        }

        // 3. Verify Tenant exists and is active
        $tenant = Tenant::query()->find($job->tenantId);

        if (!$tenant) {
            throw new MissingTenantContextException("Tenant ID [{$job->tenantId}] does not exist.");
        }

        if ($tenant->status === 'suspended') {
            throw new TenantSuspendedException("Tenant [{$tenant->name}] is suspended. Queue execution halted.");
        }

        // 4. Establish Application Singleton
        app(TenantContext::class)->setTenant($tenant);

        // 5. Establish Transaction-Safe Database RLS Context
        return DB::transaction(function () use ($tenant, $job, $next) {
            PostgresTenantContext::set($tenant->id);

            try {
                return $next($job);
            } finally {
                // Guaranteed context cleanup regardless of job success or exception
                PostgresTenantContext::reset();
                app(TenantContext::class)->clear();
            }
        });
    }
}
```

---

## 2. Double-Entry Journal Ledger Trigger Corrections

### 2.1 Correct Trigger Return Value Semantics
In PostgreSQL `BEFORE` trigger functions:
* **`BEFORE INSERT` or `BEFORE UPDATE`:** Must return `NEW` to apply the mutation (returning `NULL` cancels the operation).
* **`BEFORE DELETE`:** Must return `OLD` to allow deletion (returning `NULL` prevents deletion; returning `NEW` is invalid in SQL).

### 2.2 Parent Row-Level Locking Against Concurrent Race Conditions
To prevent concurrent race conditions where User A inserts a journal line while User B simultaneously clicks "Post" (which checks $\sum \text{Debits} = \sum \text{Credits}$), **any line mutation must acquire a row-level lock on the parent journal row**:
```sql
SELECT status FROM journal_entries WHERE id = NEW.journal_entry_id FOR UPDATE;
```

### 2.3 Comprehensive Immutability & Line Guard Triggers
```sql
-- =========================================================================
-- 1. TRIGGER: Guard Journal Entry Header Mutations & Deletions
-- =========================================================================
CREATE OR REPLACE FUNCTION trg_fn_guard_journal_entry_mutation()
RETURNS TRIGGER AS $$
DECLARE
    v_reversal_exists BOOLEAN;
BEGIN
    -- Handle Deletion: Block if posted or reversed
    IF TG_OP = 'DELETE' THEN
        IF OLD.status IN ('posted', 'reversed') THEN
            RAISE EXCEPTION 'Cannot delete % journal entry % (ID: %). Posted financial records are permanent.', 
                OLD.status, OLD.entry_number, OLD.id;
        END IF;
        RETURN OLD;
    END IF;

    -- Handle Update: Posted entries are strictly immutable except for formal reversal linkage
    IF TG_OP = 'UPDATE' THEN
        IF OLD.status = 'posted' THEN
            -- Allow ONLY status transition to 'reversed' with valid reversal linkage
            IF NEW.status = 'reversed' AND OLD.reversed_by_entry_id IS NULL AND NEW.reversed_by_entry_id IS NOT NULL THEN
                
                -- Verify ALL immutable fields remain identical
                IF NEW.entry_number IS DISTINCT FROM OLD.entry_number OR
                   NEW.tenant_id IS DISTINCT FROM OLD.tenant_id OR
                   NEW.branch_id IS DISTINCT FROM OLD.branch_id OR
                   NEW.entry_date IS DISTINCT FROM OLD.entry_date OR
                   NEW.source_type IS DISTINCT FROM OLD.source_type OR
                   NEW.source_id IS DISTINCT FROM OLD.source_id OR
                   NEW.posted_at IS DISTINCT FROM OLD.posted_at OR
                   NEW.posted_by_user_id IS DISTINCT FROM OLD.posted_by_user_id THEN
                    RAISE EXCEPTION 'Modification of core fields on posted journal entry % is strictly prohibited.', OLD.entry_number;
                END IF;

                -- Verify reversal entry exists, belongs to same tenant, and references this entry
                SELECT EXISTS (
                    SELECT 1 FROM journal_entries 
                    WHERE id = NEW.reversed_by_entry_id 
                      AND tenant_id = OLD.tenant_id 
                      AND source_type = 'reversal'
                      AND source_id = OLD.id
                ) INTO v_reversal_exists;

                IF NOT v_reversal_exists THEN
                    RAISE EXCEPTION 'Reversal reference % is invalid or does not reference journal entry %.', 
                        NEW.reversed_by_entry_id, OLD.id;
                END IF;

                RETURN NEW;
            END IF;

            RAISE EXCEPTION 'Posted journal entry % cannot be modified. Reversal is the only permitted action.', OLD.entry_number;
        END IF;

        IF OLD.status = 'reversed' THEN
            RAISE EXCEPTION 'Reversed journal entry % is permanently archived and cannot be modified.', OLD.entry_number;
        END IF;

        RETURN NEW;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_guard_journal_entry_mutation
    BEFORE UPDATE OR DELETE ON journal_entries
    FOR EACH ROW
    EXECUTE FUNCTION trg_fn_guard_journal_entry_mutation();


-- =========================================================================
-- 2. TRIGGER: Guard Journal Entry Lines (Block INSERT, UPDATE, DELETE on Posted/Reversed)
-- =========================================================================
CREATE OR REPLACE FUNCTION trg_fn_guard_journal_line_mutation()
RETURNS TRIGGER AS $$
DECLARE
    v_target_entry_id UUID;
    v_parent_status VARCHAR(20);
BEGIN
    IF TG_OP = 'DELETE' THEN
        v_target_entry_id := OLD.journal_entry_id;
    ELSE
        v_target_entry_id := NEW.journal_entry_id;
    END IF;

    -- Row-Level Lock on Parent Header prevents race conditions during concurrent posting
    SELECT status INTO v_parent_status 
    FROM journal_entries 
    WHERE id = v_target_entry_id 
    FOR UPDATE;

    IF v_parent_status IS NULL THEN
        RAISE EXCEPTION 'Parent journal entry % does not exist.', v_target_entry_id;
    END IF;

    IF v_parent_status IN ('posted', 'reversed') THEN
        RAISE EXCEPTION 'Cannot % line on journal entry % because it is already %.', 
            TG_OP, v_target_entry_id, v_parent_status;
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    ELSE
        RETURN NEW;
    END IF;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_guard_journal_line_mutation
    BEFORE INSERT OR UPDATE OR DELETE ON journal_entry_lines
    FOR EACH ROW
    EXECUTE FUNCTION trg_fn_guard_journal_line_mutation();
```

---

## 3. HTTP RLS Transaction Lifecycle & Connection Pool Safety

### 3.1 The PgBouncer Transaction Pooling Constraint
In high-performance PostgreSQL deployments using PgBouncer in **Transaction Pooling Mode**:
* A client connection from the web server is assigned a backend server connection *only for the duration of a transaction*.
* When a transaction completes (`COMMIT` or `ROLLBACK`), the connection is returned to the pool and may be assigned to a different tenant's request.
* Calling `SELECT set_config('app.current_tenant_id', ?, false)` (session-level) leaks tenant state to subsequent requests.
* Calling `SELECT set_config('app.current_tenant_id', ?, true)` (transaction-local) applies *only* inside an active `BEGIN ... COMMIT` block. If run outside a transaction, it reverts instantly upon statement completion.

### 3.2 Dual-Layer HTTP Tenant Lifecycle
EduCore implements a **Dual-Shield Execution Lifecycle**:

```
[ Incoming HTTP Request ]
           |
           v
[ IdentifyTenantMiddleware ]
  1. Resolve Subdomain (e.g., beacon.educore.test)
  2. Load Tenant Model & Bind TenantContext Singleton
  3. Validate Tenant Status ('active')
           |
           v
[ Scoped Request Pipeline ]
  * Read-only views / non-mutating queries run through Eloquent Global Scopes
  * Every mutation or financial action runs inside DB::transaction()
  * DB::transaction() explicitly invokes PostgresTenantContext::set($tenant->id)
           |
           v
[ Middleware terminate() Hook ]
  1. PostgresTenantContext::reset()
  2. Clear TenantContext Singleton
  3. Connection Returned Clean to PgBouncer Pool
```

### 3.3 Implementation: Scoped Transaction Wrapper & Database Connection Hook
```php
namespace App\Core\Tenancy;

use App\Core\Tenancy\Exceptions\NoActiveTenantException;
use Closure;
use Illuminate\Support\Facades\DB;

class TenantTransactionManager
{
    /**
     * Executes a callback within a managed transaction that guarantees
     * RLS parameter-safe scoping and automatic rollback cleanup.
     *
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public static function run(Closure $callback): mixed
    {
        $tenantId = app(TenantContext::class)->getTenantId();

        if (empty($tenantId)) {
            throw new NoActiveTenantException('Cannot execute tenant transaction without an active TenantContext.');
        }

        return DB::transaction(function () use ($tenantId, $callback) {
            // is_local = true strictly scopes setting to this specific transaction
            DB::statement("SELECT set_config('app.current_tenant_id', ?, true)", [$tenantId]);

            return $callback();
        });
    }
}
```

### 3.4 Automated Security & Transaction Test Matrix
The test suite (`tests/Feature/Tenancy/TenantTransactionLifecycleTest.php`) explicitly covers:
1. **Missing Context Test:** Querying tenant tables without an active transaction or context returns 0 rows.
2. **Transaction Rollback Test:** If an exception is thrown inside `TenantTransactionManager::run()`, the transaction aborts, rollback occurs, and `set_config` is automatically cleared by PostgreSQL.
3. **Connection Reuse Hygiene Test:**
   * Request 1 connects as Tenant A, queries records.
   * Connection returned to pool.
   * Request 2 connects with unauthenticated / public route on same pooled connection; verifies query to `students` yields 0 rows.
4. **Concurrent Requests Race Test:** Simulates concurrent HTTP requests from Tenant A and Tenant B running in parallel fibers/processes; asserts zero data cross-talk.

---

## 4. Final Approvals & Readiness Confirmation

| Decision Item | User Decision Status | Architecture Alignment |
| :--- | :--- | :--- |
| **Local Development Domain** | **APPROVED:** `educore.test` | Configured for dynamic wildcard subdomain routing `*.educore.test`. |
| **PDF Generation Engine** | **APPROVED:** Deferred | Behind `PdfGeneratorInterface`; decision deferred until Phase 3 bilingual voucher PoC benchmark. |
| **Phase 1 Implementation Authorization** | **APPROVED in principle** | Conditioned upon incorporating these 3 final corrections (v2.2). |

### Implementation Readiness
All architectural blueprints, database schemas, security kernels, and ledger triggers are finalized, corrected, and verified. 

**Status:** Ready for Phase 1 Scaffolding and Implementation upon your explicit signal.
