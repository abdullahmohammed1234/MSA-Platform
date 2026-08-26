# SFU MSA Platform — Production Operations Checklist

**Scope:** SFU MSA Platform only (five applications + Platform control plane).  
**Not in scope:** UmmahOS, multi-MSA, multi-tenancy, Academy learner launch.

Use with: `docs/PHASE_12_PRODUCTION_READINESS_IMPLEMENTATION_REPORT.md`, `docs/PLATFORM_GUIDE.md`.

---

## BEFORE DEPLOYMENT

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL=https://…` (API)
- [ ] `FRONTEND_URL=https://…`
- [ ] `FORCE_HTTPS=true` (when TLS terminates at proxy/cPanel)
- [ ] `TRUSTED_PROXIES` set (`*` behind private reverse proxy, or explicit IPs)
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] Database credentials configured (not committed)
- [ ] Migrations reviewed — **no** `migrate:fresh` / `migrate:refresh`
- [ ] `QUEUE_CONNECTION=database` (or redis) — **not** `sync`
- [ ] SMTP configured (`MAIL_MAILER=smtp` + host/user/pass) — secrets outside git
- [ ] Square credentials configured if `EMS_PAYMENTS_ENABLED=true`
- [ ] Square webhook URL matches Dashboard notification URL
- [ ] Storage / public disk configured; `storage:link` planned
- [ ] Database backup verified (**REQUIRES HOSTING-ENVIRONMENT VERIFICATION**)
- [ ] Uploaded-file backup verified (`storage/`, `uploads/cms`, `uploads/academy`) (**REQUIRES HOSTING-ENVIRONMENT VERIFICATION**)
- [ ] Frontend built with `VITE_ACADEMY_ENABLED=false` and correct `VITE_API_URL`
- [ ] CORS / Sanctum domains match production hosts

---

## DEPLOYMENT (cPanel model)

`.cpanel.yml` **only rsyncs** code. It does **not** run migrate, queues, or cron.

1. Build frontend: `cd frontend && npm ci && npm run build`
2. Allow cPanel sync (backend → `msa-backend`, `frontend/dist` → `public_html`)
3. On backend host, preserve existing `.env` (do not overwrite with local secrets)
4. Run prepare script **or** manual steps below

### Safe commands (backend root)

```bash
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan storage:link   # once
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan msa:production-check --strict
php artisan queue:restart
```

Or: `bash scripts/prepare-production.sh` / `scripts/prepare-production.ps1`

### Forbidden on production

```bash
php artisan migrate:fresh
php artisan migrate:refresh
php artisan db:wipe
```

---

## AFTER DEPLOYMENT

- [ ] Migrations completed without error
- [ ] `php artisan msa:production-check` passes (or warnings understood)
- [ ] Application / Main Website loads
- [ ] Login + `/api/v1/auth/me` works
- [ ] Logout invalidates token
- [ ] CMS `/cms` works for authorized user
- [ ] DAMS `/dams` works for authorized user
- [ ] EMS `/ems` works for authorized user
- [ ] Public events from EMS (`/api/v1/ems/public/events`)
- [ ] Legacy `/api/v1/website/events` returns **410**
- [ ] Academy learner remains disabled (`VITE_ACADEMY_ENABLED=false`)
- [ ] Queue workers running for:  
      `ems-payments,ems-notifications,ems-operations,high,default,low`
- [ ] Cron every minute: `php artisan schedule:run`
- [ ] Systems `/admin/systems` statuses probe-derived; five applications
- [ ] Security Center reachable; `APP_DEBUG` shows secure when false
- [ ] Failed jobs reviewed (`/admin/system/queues` or DB)
- [ ] Course asset upload → Academy-owned; **no** CMS Media row
- [ ] Unauthorized API access → 401/403

---

## QUEUE WORKERS (required)

```bash
php artisan queue:work --queue=ems-payments,ems-notifications,ems-operations,high,default,low --sleep=1 --tries=3
```

After each deploy: `php artisan queue:restart`

Systems may report workers as **unknown** — process liveness is not faked.

---

## SCHEDULER (required)

```cron
* * * * * cd /home2/sfums1d5/msa-backend && php artisan schedule:run >> /dev/null 2>&1
```

Critical tasks include EMS abandoned checkout expiry, reminders, Square reconcile, analytics/maintenance.

---

## BACKUPS (**REQUIRES HOSTING-ENVIRONMENT VERIFICATION**)

| Asset | Frequency (recommended) | Notes |
|-------|-------------------------|-------|
| MySQL database | Daily + before migrate | Include archived `events` / `event_registrations` |
| Uploads (`storage/app`, public uploads) | Daily | CMS `uploads/cms/`, Academy `uploads/academy/` |
| Secrets (`.env`, Square, SMTP) | On change | Stored outside web root / password manager |

Restore: restore DB → restore files → verify `.env` → `config:cache` → smoke checklist.

The repository does **not** contain backup binaries. Confirm with hosting that jobs exist.

---

## SMOKE MATRIX (manual)

| Area | Test | Expected |
|------|------|----------|
| Public | Website loads | 200 |
| Public | EMS events | 200 from `/ems/public/events` |
| Public | Legacy CMS events | 410 |
| Auth | Login / me / logout | Token lifecycle OK |
| CMS | Authorized CRUD | Success; unauthorized 403 |
| DAMS | Course asset | `owner=academy`, no media row |
| Academy | Feature flag | Disabled in FE build |
| EMS | Admin + public | Operational |
| Systems | Overview | 5 apps; probe statuses |
| Security | Protected routes | 401/403 without grants |
