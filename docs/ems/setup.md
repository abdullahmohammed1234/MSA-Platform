# EMS Setup Guide

## Prerequisites

- PHP 8.2+, Composer
- Node.js 20+, npm
- MySQL 8 (required for application runtime; PHPUnit uses in-memory SQLite only)
- The MSA Platform backend and frontend already runnable

The EMS is not a separate install. It is a module inside the existing platform.

## 1. Backend

### Environment

Copy any new keys from `backend/.env.example` into your local `backend/.env`.
The EMS-specific block is documented there in full. The important ones for Phases 1–2:

| Variable | Default | Purpose |
| --- | --- | --- |
| `EMS_API_PREFIX` | `api/v1/ems` | Where the API is mounted |
| `EMS_API_RATE_LIMIT` | `120` | Authenticated requests / minute / user |
| `EMS_PUBLIC_RATE_LIMIT` | `60` | Public discovery requests / minute / IP |
| `EMS_REGISTRATION_RATE_LIMIT` | `5` | Registration attempts / minute / IP |
| `EMS_TICKETS_ENABLED` | `true` | Free ticket issuance |
| `EMS_TICKET_QR_ENABLED` | `true` | QR generation |
| `EMS_TICKET_PREFIX` | `MSA` | Ticket code prefix |
| `EMS_DEFAULT_TIMEZONE` | `America/Vancouver` | Default for new events |
| `EMS_OPERATIONS_QUEUE` | `ems-operations` | Queue for large attendee imports |
| `EMS_IMPORT_SYNC_THRESHOLD` | `50` | Imports at/under this size run sync |
| `EMS_CHECK_IN_RATE_LIMIT` | `60` | Check-in / validate attempts per minute |
| `EMS_NOTIFICATIONS_ENABLED` | `false` | Master switch for EMS email delivery |
| `EMS_NOTIFICATIONS_QUEUE` | `ems-notifications` | Queue for EMS notification jobs |
| `EMS_MAIL_FROM_ADDRESS` | `MAIL_FROM_ADDRESS` | From address for EMS mail |
| `EMS_MAIL_FROM_NAME` | `SFU MSA Events` | From name for EMS mail |
| `EMS_SEED_DEV_USERS` | `false` | Seed one user per EMS role (non-production only) |
| `EMS_LOG_CHANNEL` | `ems` | Dedicated daily log |

Enable notifications after mail + queue workers are configured:

```env
EMS_NOTIFICATIONS_ENABLED=true
QUEUE_CONNECTION=database
```

Worker:

```bash
php artisan queue:work --queue=ems-notifications,ems-operations,ems-payments,default
```

See [phase5.md](./phase5.md) for the full communications guide.

The EMS reuses the platform's existing:

- `APP_URL`, `FRONTEND_URL`
- Database connection
- Sanctum / session configuration
- Mail and queue settings
- CORS (`FRONTEND_URL` + `CORS_ALLOWED_ORIGINS`)

### Migrate and seed

```bash
cd backend
php artisan migrate
php artisan db:seed --class=EmsDatabaseSeeder
```

`EmsDatabaseSeeder` is also registered from the main `DatabaseSeeder`, so a
full platform seed includes EMS data.

With `EMS_SEED_DEV_USERS=true` and `APP_ENV` not `production`, the seeder
creates four development users (`event-admin@ems.test`,
`organizer@ems.test`, `staff@ems.test`, `attendee@ems.test`) and prints a
random shared password to the console. Never use these accounts in production.

### Verify

```bash
php vendor/bin/phpunit --filter Ems
```

## 2. Frontend

### Environment

The EMS is served by the same Vue app:

- Admin operations UI at `/ems`
- Public discovery at `/events`, calendar at `/events/calendar`,
  tickets at `/tickets/:code` (legacy `/ems-events/*` redirects to `/events/*`)

It talks to `${VITE_API_URL}/ems` (including `/ems/public/...`). No EMS-specific
API URL is required. Confirm:

```env
VITE_APP_URL=http://localhost:5173
VITE_API_URL=http://localhost:8000/api/v1
```

### Run

```bash
cd frontend
npm install
npm run dev
```

Open `http://localhost:5173/ems` after signing in through the platform login
(`/login`) with an account that has an EMS role.

### Verify

```bash
npm run build          # vue-tsc + Vite production build
npm run test:unit      # includes the EMS specs
```

## 3. Signing in

There is no EMS login page. Authenticate against the platform:

```http
POST /api/v1/auth/login
```

Send the resulting Sanctum bearer token on every EMS request. The EMS shell
reads the same `auth_token` the rest of the platform uses.

To grant yourself access on an existing account, assign an EMS role through
the platform RBAC admin, or run the development user seeder above.

## 4. Quick smoke checklist

1. `php artisan migrate` — EMS migrations through `001200` (Phase 4 operations)
2. `php artisan db:seed --class=EmsDatabaseSeeder` — EMS permissions/roles
   (re-run after upgrading to Phase 4 so `check_ins.*` and `imports.*` exist)
3. Sign in as an Event Administrator
4. Open `/ems` — dashboard loads with summary cards
5. Create an event — starts as Draft
6. Publish → Open Registration → Close Registration → Mark Live → Complete →
   Archive from the lifecycle panel
7. Attempt an illegal transition via the API — expect HTTP 409
8. Sign in as Event Staff — see assigned events only; no Create Event / Import;
   can open Staff check-in and scan tickets
9. Import a small CSV on an event, then check in via QR or search
10. Run a queue worker that includes `ems-operations` for large imports

Camera check-in needs HTTPS on real devices (localhost is exempt). See
[phase4.md](./phase4.md).

## 5. Logging

EMS activity appears in:

- `storage/logs/ems-YYYY-MM-DD.log`
- Platform `audit_logs` (actions prefixed `ems.`)

Authorization denials are logged at warning level with the request path.
