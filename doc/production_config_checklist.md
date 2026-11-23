# EduCore SMS — Production Configuration Checklist

Verify every setting below before deploying to staging or production:

## 1. Application & Debug
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false` (never `true` outside local development)
- [ ] `APP_KEY` generated uniquely per environment
- [ ] `APP_BLIND_INDEX_KEY` set to a high-entropy 32+ byte secret (required for deterministic CNIC/phone blind indexing)
- [ ] `APP_URL` set to `https://<central-domain>`

## 2. Database & PostgreSQL RLS
- [ ] `DB_CONNECTION=pgsql_app` (or runtime connection uses non-superuser `educore_app` without `BYPASSRLS`)
- [ ] `DB_SSLMODE=require` (or `verify-full` with CA cert)
- [ ] Migrations run separately under `educore_migrator`

## 3. Sessions & Cookies
- [ ] `SESSION_DOMAIN=null` (host-only cookies so central and tenant subdomains never collide)
- [ ] `SESSION_SECURE_COOKIE=true` (HTTPS only)
- [ ] `SESSION_HTTP_ONLY=true`
- [ ] `SESSION_SAME_SITE=lax`
- [ ] `SESSION_ENCRYPT=true`

## 4. Caching, Queues & Storage (Target Tier 2+)
- [ ] `CACHE_STORE=redis` (replacing `database`)
- [ ] `QUEUE_CONNECTION=redis` with supervised Horizon/queue workers
- [ ] `SESSION_DRIVER=redis`
- [ ] `FILESYSTEM_DISK=s3` (private bucket; never public read for student documents)
- [ ] `MAIL_MAILER=smtp` / transactional provider (never `log` in production)

## 5. Logging & Seeding
- [ ] `LOG_LEVEL=warning` (or `info` without PII)
- [ ] Do **not** run development pilot seeders against production; provision Super Admin via env-driven / interactive setup (`R0.5`).
