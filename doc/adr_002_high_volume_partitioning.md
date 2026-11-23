# ADR-002: High-Volume Table Partitioning & Archival Strategy (R3.9)

**Status:** Accepted  
**Date:** 2026-11-20

## Context
At scale (e.g., 500+ tenants with period-wise attendance and double-entry GL lines), `school_attendance_records` and `journal_entry_lines` grow fastest (~8M+ rows/year for a 5,000-student school).

## Decision & Thresholds
1. **Trigger Threshold:** Initiate declarative PostgreSQL range partitioning when any single high-volume table exceeds **50,000,000 rows** or **25 GB** heap size, or when `pg_stat_statements` shows index bloat degrading p95 write latency > 250 ms.
2. **Partitioning Scheme:**
   - `school_attendance_records`: Declarative `PARTITION BY RANGE (attendance_date)` — monthly partitions (`y2026m04`, `y2026m05`, ...), leading composite index `(tenant_id, attendance_date, student_id)`.
   - `journal_entry_lines`: Declarative `PARTITION BY RANGE (created_at)` — yearly/fiscal-year partitions, preserving immutable GL triggers and `FORCE ROW LEVEL SECURITY` on parent + child partitions.
3. **Archival:** Detach partitions older than 3 academic years (`ALTER TABLE ... DETACH PARTITION CONCURRENTLY`) to cold tablespace/export archive after legal-hold check.
