# MSA Event Management System (EMS)

Phases 1–5 — Foundation through communications & notification automation.

The EMS is a dedicated event-management application inside the SFU MSA digital
ecosystem. It owns events, ticket types, orders, registrations, tickets, QR
codes, day-of-event check-in, and attendee communications. Square is used only
as the payment provider. Email delivery uses Platform Queues.

## Documentation

| Document | What it covers |
| --- | --- |
| [architecture.md](./architecture.md) | Module boundaries, layering, state machine, RBAC |
| [setup.md](./setup.md) | Installation, environment variables, migrations, seeders |
| [api.md](./api.md) | Endpoint reference |
| [openapi.yaml](./openapi.yaml) | OpenAPI 3.1 specification |
| [phase2.md](./phase2.md) | Phase 2 free registration summary |
| [phase3.md](./phase3.md) | Phase 3 ticketing & payments summary |
| [phase4.md](./phase4.md) | Phase 4 operations, import & check-in |
| [phase5.md](./phase5.md) | Phase 5 communications & notification automation |
| [square-setup.md](./square-setup.md) | Square sandbox/production deployment |
| [deferred.md](./deferred.md) | Phase 6+ intentionally deferred work |

## Where the code lives

```text
backend/
  app/Ems/                      module namespace (services, payments, jobs, HTTP)
  app/Ems/Services/Notifications/  Phase 5 dispatcher, reminders, templates
  app/Ems/Services/Operations/  Phase 4 check-in, import, attendees
  config/ems.php                module configuration (incl. Square + notifications)
  routes/ems.php                authenticated + /public API surface
  database/migrations/          2026_07_25_0001..0013  (ems_* tables)
  tests/Feature/Ems/            Phase 1–5 feature coverage

frontend/src/
  router/ems.ts                 admin routes at /ems
  router/public.ts              /events (EMS public), checkout success/cancel, /tickets/:code
  pages/ems/                    organizer dashboard, tickets, operations, communications
  pages/public/ems/             public discovery + registration/checkout
  services/ems/                 emsClient, operationsService, notificationsService
```

## Current status

| Phase | Status |
| --- | --- |
| 1 Foundation | Complete |
| 2 Public discovery & free registration | Complete |
| 3 Ticketing & Square payments | Complete |
| 4 Operations & check-in | Complete |
| 5 Communications | Complete — see [phase5.md](./phase5.md) |
| 6 Analytics | Deferred |
| 7 Advanced features | Deferred |
