# ADR-001: PostgreSQL Tenant Context & PgBouncer Transaction-Pool Safety

**Status:** Accepted (R1.3)  
**Date:** 2026-11-19

## Context
EduCore uses PostgreSQL Row-Level Security (`app.current_tenant_id`) across 64 tenant-scoped tables. In `IdentifyTenantBySubdomain`, the HTTP request sets `PostgresTenantContext::set($tenant->id, isLocal: false)` so read queries outside explicit transactions still see the active tenant, and resets it in `terminate()`.

However, under **PgBouncer transaction pooling**, backend PostgreSQL connections are reassigned between clients at transaction boundaries (`COMMIT`/`ROLLBACK`). Relying solely on session-level `set_config(..., false)` risks either losing context on a newly assigned backend connection or leaking context if a worker crashes before `terminate()`.

## Decision
1. **Automatic Transaction-Local Binding (`TransactionBeginning` listener):** In `AppServiceProvider::boot()`, whenever a PostgreSQL transaction starts while `TenantContext::getTenantId()` is set, `PostgresTenantContext::set($tenantId, $event->connectionName, isLocal: true)` (`SET LOCAL`) is executed automatically.
2. **Queue & Write-Path Guarantee:** `EnforceTenantQueueMiddleware` and `TenantTransactionManager::run()` wrap work in `DB::transaction()` and explicitly set `isLocal: true`, then `reset()` on exit.
3. **HTTP Defense-in-Depth:** `IdentifyTenantBySubdomain` clears `TenantContext` and resets `PostgresTenantContext` on central-domain requests and in `terminate()`.
4. **Pooling Guidance:** Deploy PgBouncer in **transaction pooling** mode only after verifying all tenant-scoped reads/writes either run inside `DB::transaction()` (where `TransactionBeginning` binds `isLocal: true`) or use `TenantTransactionManager`. For long-term scale (R3.8), wrap tenant HTTP read paths in a lightweight read-only transaction or `SET LOCAL` middleware when PgBouncer is introduced.
