# EMS Architecture

Phase 1 of the MSA Event Management System is a bounded module inside the
existing MSA platform. It does not stand up a second application, a second
authentication system or a second user database.

## 1. Module boundaries

| Concern | Owned by EMS | Reused from platform |
| --- | --- | --- |
| Tables | `ems_*` | `users`, `roles`, `permissions`, `audit_logs` |
| API | `/api/v1/ems/*` | `/api/v1/auth/*` (login / logout / me) |
| Auth | — | Laravel Sanctum bearer tokens |
| RBAC | EMS roles + permissions (seeded into platform tables) | `HasRolesAndPermissions` trait |
| Frontend | `/ems` routes, layout, pages, stores | Shared design system, toast, axios client, auth store |
| Logging | `ems` log channel + `ems.*` audit actions | Platform `AuditLogger` |

The module boots through a single entry point: `App\Ems\EmsServiceProvider`.
That provider merges `config/ems.php`, registers policies, mounts the routes,
registers the activity subscriber and publishes the rate limiter. Nothing EMS-
specific is wired from `AppServiceProvider`.

## 2. Backend layering

```text
HTTP request
    │
    ▼
routes/ems.php                  auth:sanctum + ems_api throttle
    │
    ▼
Form Request                    validation only (authorize() always true)
    │
    ▼
Controller                      authorize via Policy, call Service, return ApiResponse
    │
    ▼
Service                         business rules, transactions, domain events
    │
    ├─► Eloquent model          persistence
    ├─► Domain event            EventCreated / EventStatusChanged / …
    └─► EmsActivityLogger       audit_logs + ems log channel
```

Controllers stay thin. Policies decide *whether* an action is allowed;
services decide *how* it happens. Status is never an editable field on an
event — it is the outcome of a named transition applied by
`EventLifecycleService`.

## 3. API contract

Every EMS endpoint returns the same envelope:

```json
{
  "success": true,
  "message": "Event created successfully.",
  "data": { },
  "meta": { }
}
```

Failures:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "name": ["The event name field is required."]
  }
}
```

`App\Ems\Support\ApiResponse` is the only writer of this shape.
`EmsExceptionHandler` catches exceptions on EMS routes and renders them into
the same envelope, without leaking stack traces. Laravel's
`prepareException()` wraps some exceptions before render callbacks run, so the
handler listens for `AccessDeniedHttpException` and `NotFoundHttpException` in
addition to the original types.

## 4. Event lifecycle

States (`EventStatus`):

```text
draft → published → registration_open → registration_closed → live → completed → archived
```

Plus one reversible edge: `published → draft` (`unpublish`).

Edges (`EventTransition`) each carry:

- a single source state and a single target state
- the permission required to perform them
- a UI label and a confirmation message
- an `irreversible` flag (`complete`, `archive`)

`EventLifecycleService::apply()` is the only writer of `Event::status`. It
validates the edge, stamps the matching lifecycle timestamp
(`published_at`, `registration_open_at`, …), and dispatches
`EventStatusChanged`. Illegal transitions raise
`InvalidEventTransitionException` (HTTP 409).

The graph is published at `GET /api/v1/ems/events/lifecycle`. Each event
resource also includes `available_transitions`, already filtered by the
viewer's permissions, so the lifecycle panel renders buttons from data.

## 5. Authorization

Authorization is permission-based, never role-name-based.

Permissions live in `App\Ems\Support\EmsPermissions` and are seeded into the
platform `permissions` table under module `EMS`. Display names are prefixed
`EMS:` so they do not collide with academy / CMS permission names.

Roles live in `App\Ems\Support\EmsRoles`:

| Role | Scope |
| --- | --- |
| `super-admin` | Platform role, reused. Full EMS access including `system.manage`. |
| `event-administrator` | Full event programme, categories, lifecycle. No `system.manage`. |
| `event-organizer` | Create / manage own events. No `events.view_all`. |
| `event-staff` | Read assigned events. Check-in arrives in Phase 4. |
| `attendee` | No EMS permissions in Phase 1. Registration arrives later. |

Record-level scoping lives on `Event::scopeVisibleTo` and `EventPolicy`:

- `events.view_all` → every event
- otherwise → events the user organizes or is staffed on

Policies enforce every write. The frontend navigation is permission-aware for
usability only; the server is the authority.

## 6. Database

Nine migrations, all prefixed `ems_`:

| Table | Purpose |
| --- | --- |
| `ems_event_categories` | Full CRUD |
| `ems_events` | Full CRUD + lifecycle + optional `banner_url` (Phase 2) |
| `ems_event_organizers` | Pivot foundation |
| `ems_event_staff` | Pivot foundation |
| `ems_ticket_types` | Schema only (Phase 3 sales) |
| `ems_registrations` | Free public registration (Phase 2); paid path reserved |
| `ems_payments` | Schema only (Phase 3) |
| `ems_tickets` | Issued on free registration; QR payload stamped |
| `ems_check_ins` | Schema only (Phase 4) |
| `ems_notifications` | Schema only (Phase 5) |

Public addressing uses UUIDs (`HasEmsUuid`). Soft deletes are on the entities
that later phases will restore. Foreign keys, unique constraints and indexes
are declared in the migrations — uniqueness of slugs and ticket codes is not
left to the application alone.

## 7. Logging & auditing

`EmsActivityLogger` writes two places for every significant action:

1. Platform `audit_logs`, with actions namespaced `ems.*`
2. Dedicated `ems` daily log channel for operational tooling

Domain events (`EventCreated`, `EventUpdated`, `EventDeleted`,
`EventStatusChanged`, `EventCategoryChanged`) are collected by
`EmsActivitySubscriber`, so services do not call the logger by hand.
Authorization denials are logged at warning level from the exception handler.
Sensitive keys (`password`, `token`, `square_access_token`, `cvv`, …) are
redacted before either destination is written.

## 8. Frontend architecture

```text
/ems
  EmsLayout                 sidebar + header + user menu
    Dashboard               summary, upcoming, activity, quick actions
    Events                  list / create / detail / edit
    Categories              taxonomy management
    Roles & Permissions     read-only access model
    Unauthorized / 404
```

- **Auth**: reuses the platform Pinia auth store and Sanctum token. There is
  no EMS login page.
- **Access model**: `useEmsAccessStore` loads `GET /ems/users/me` and drives
  navigation. `emsGuard` owns `/ems` in the router pipeline so failures land
  on EMS screens, not the academy fallback.
- **API client**: `services/ems/emsClient.ts` wraps the shared axios instance
  with the EMS envelope and a single `EmsApiError` type. Views never inspect
  axios internals.
- **Lifecycle UI**: `EventLifecyclePanel` renders
  `event.available_transitions`. It contains no copy of the state machine.

## 9. Future-ready seams

Interfaces already exist so later phases plug in without rewriting the core:

| Contract | Phase | Status |
| --- | --- | --- |
| `TicketIssuer` | 2 | **Implemented** (`DefaultTicketIssuer`) |
| `PaymentGateway` | 3 (Square) | Interface only |
| `EventNotificationDispatcher` | 5 | Interface only |

## 10. Phase 2 — Public surface

Unauthenticated routes mount under `/api/v1/ems/public` with IP-keyed
rate limiters (`ems_public`, `ems_registration`). Controllers stay thin and
reuse `ApiResponse`.

Public visibility rule:

```text
is_public = true
AND status.isPubliclyVisible()   // published … completed (not draft/archived)
```

Free registration flow:

```text
RegisterForEventRequest
    → RegistrationService::registerFree (lock event, capacity, duplicates)
    → status = confirmed, type = free
    → TicketIssuer::issueFor
    → QR payload stamped; PNG generated on demand
```

Ticket codes are non-sequential (`MSA-` + Crockford Base32). Validation
(`GET …/tickets/validate/{code}`) confirms existence and `issued` status only —
it never redeems a ticket (Phase 4).

Frontend discovery lives at `/events` (listing), `/events/calendar`,
`/events/:slug` and `/tickets/:code`, matching the public SFU MSA design
language while leaving the legacy CMS `/events` routes intact.

See [phase2.md](./phase2.md) for the full Phase 2 inventory.

## 11. What this architecture deliberately avoids

- A parallel authentication / SSO system
- Role-name checks scattered through the code
- Editable `status` fields on the event update endpoint
- Hard-coded categories or lifecycle rules in the frontend
- Phase 3–8 feature implementations disguised as "hooks"
