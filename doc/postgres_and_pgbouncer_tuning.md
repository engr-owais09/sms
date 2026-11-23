# EduCore SMS — PostgreSQL & PgBouncer Tuning Guide (R3.8)

## 1. PgBouncer Configuration (`pgbouncer.ini`)
- `pool_mode = transaction` (compatible with EduCore's `TransactionBeginning` `SET LOCAL` listener from `R1.3`)
- `max_client_conn = 500`
- `default_pool_size = 25`
- `reserve_pool_size = 5`
- `server_reset_query = DISCARD ALL` (defense-in-depth when session settings are used)

## 2. PostgreSQL Guardrails (`postgresql.conf` / role-level)
```sql
ALTER ROLE educore_app SET statement_timeout = '10s';
ALTER ROLE educore_app SET lock_timeout = '3s';
ALTER ROLE educore_app SET idle_in_transaction_session_timeout = '15s';
```
- Enable `shared_preload_libraries = 'pg_stat_statements'` to track top slow queries by total_exec_time and mean_exec_time.
