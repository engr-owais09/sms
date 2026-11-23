# EduCore SMS — Redis & Queue Worker Setup (R3.1)

**Environment Note:** Local Windows PHP (`8.3.15`) does not currently have `ext-redis` (`phpredis`) loaded; staging/production nodes must enable `phpredis` (or `predis/predis`) and Redis 7+.

## 1. Recommended Production `.env` Settings
```dotenv
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_QUEUE_RETRY_AFTER=180
```

## 2. Queue Reliability & Dead-Letter Policy
- `config/queue.php` sets `'after_commit' => true` on `redis` so queued jobs never execute before the originating PostgreSQL transaction commits.
- Background jobs (`GenerateSchoolMonthlyVouchersJob`, `DispatchAbsenteeNotificationsJob`) use `$tries = 3`, `$backoff = [10, 60, 300]`, and `$timeout = 120`.
- Failed jobs land in `failed_jobs` for inspection via `php artisan queue:failed` and replay via `php artisan queue:retry`.
- Install `laravel/horizon` in Tier 2+ for worker auto-scaling and queue depth monitoring.
