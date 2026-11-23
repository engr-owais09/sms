# EduCore SMS — Operations, Observability & Deployment Runbook (R8)

## 1. Health & Observability
- **Readiness Endpoint:** `GET /health` verifies PostgreSQL (`SELECT 1`), cache read/write, and local storage writability (`200 ok` / `503 degraded`).
- **Structured Request Tracing:** `RequestContextMiddleware` attaches `X-Request-Id` on every response and shares `request_id` + `tenant_id` (no PII) with Laravel logs.
- **Error Tracking:** Configure `SENTRY_LARAVEL_DSN` in production and `LOG_CHANNEL=stack` (JSON formatting via `LOG_STACK=single` or stderr for container shipping).

## 2. Alerting Rules (Recommended)
- `GET /health` non-200 for > 2 consecutive minutes → Pager/Alert.
- HTTP 5xx rate > 1% over 5 minutes → Alert.
- `failed_jobs` count > 0 or backup command failure log → Alert.
- PostgreSQL active connections > 80% of `max_connections` → Alert.

## 3. Zero-Downtime Deployment & Rollback Policy
- Follow **expand/contract** migrations: all new columns/tables must be additive and backward-compatible with the previous app release during rolling deploy.
- Never run `migrate:fresh`, `migrate:reset`, or destructive `DROP COLUMN` in deployment hooks.
- Rollback: switch app symlink/container tag to previous release and clear route/config caches (`php artisan optimize:clear`).
