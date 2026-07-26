# EMS API Reference — Phase 1

Base path: `/api/v1/ems` (configurable via `EMS_API_PREFIX`).

Every route requires a Sanctum bearer token obtained from
`POST /api/v1/auth/login`. There are no EMS login or logout endpoints.

Rate limit: `ems_api` (default 120 requests / minute / authenticated user).

## Envelope

Success:

```json
{
  "success": true,
  "message": "…",
  "data": {},
  "meta": {}
}
```

Paginated lists hoist pagination into `meta.pagination`:

```json
{
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 42,
      "last_page": 3,
      "from": 1,
      "to": 15
    }
  }
}
```

Error:

```json
{
  "success": false,
  "message": "…",
  "errors": { "field": ["…"] }
}
```

| Status | Meaning |
| --- | --- |
| 401 | Unauthenticated |
| 403 | Authenticated but not permitted |
| 404 | Resource or endpoint not found |
| 409 | Business-rule conflict (illegal transition, category in use) |
| 422 | Validation failed |
| 429 | Rate limited |
| 500 | Unexpected server error (no stack trace in production) |

---

## Identity & access

### `GET /users/me`

Returns the authenticated user with EMS permissions only.

**Auth:** any Sanctum user  
**Permission:** none beyond being signed in

### `GET /roles`

EMS roles and the permissions attached to each.

**Permission:** `system.view`

### `GET /permissions`

EMS permission catalogue, grouped for display.

**Permission:** `system.view`

---

## Dashboard

### `GET /dashboard`

```json
{
  "summary": {
    "total": 12,
    "upcoming": 3,
    "draft": 2,
    "published": 1,
    "registration_open": 2,
    "registration_closed": 1,
    "live": 1,
    "completed": 3,
    "archived": 2
  },
  "upcoming_events": [ /* EventResource[] */ ],
  "recent_activity": [ /* ActivityLogResource[] */ ],
  "quick_actions": [
    { "key": "create_event", "label": "Create Event", "route": "/ems/events/create" }
  ]
}
```

Everything is scoped to what the viewer may see. Quick actions arrive
pre-filtered by permission.

**Permission:** `events.view` (via `EventPolicy::viewAny`)

---

## Events

### `GET /events`

Query parameters:

| Param | Notes |
| --- | --- |
| `search` | Name / location |
| `status` | One of the lifecycle values |
| `category_id` | Integer FK |
| `organizer_id` | Integer FK |
| `upcoming` | Boolean |
| `starts_after` / `starts_before` | ISO dates |
| `sort_by` | `start_at`, `name`, `status`, `created_at`, `updated_at` |
| `sort_direction` | `asc`, `desc` |
| `per_page`, `page` | Pagination |

**Permission:** `events.view`  
Results are further scoped by organizer / staff / `events.view_all`.

### `POST /events`

Body:

```json
{
  "name": "MSA Welcome Night",
  "slug": "msa-welcome-night",
  "short_description": "…",
  "description": "…",
  "category_id": 1,
  "organizer_id": 4,
  "location": "SFU Burnaby, SUB",
  "start_at": "2026-09-10T18:00:00-07:00",
  "end_at": "2026-09-10T21:00:00-07:00",
  "timezone": "America/Vancouver",
  "capacity": 200,
  "is_public": true
}
```

`status` and lifecycle timestamps are rejected — new events are always
`draft`. Slug is optional; unique slug is generated from the name when omitted.

**Permission:** `events.create`  
**Response:** `201` with `EventResource`

### `GET /events/{event}`

`{event}` is the event UUID.

**Permission:** `events.view` + record scope

### `PUT /events/{event}` / `PATCH /events/{event}`

Same fields as create; all optional under `sometimes`. Status cannot be set.

**Permission:** `events.update` + record scope

### `DELETE /events/{event}`

Soft delete.

**Permission:** `events.delete` + record scope

---

## Event lifecycle

### `GET /events/lifecycle`

Publishes the state machine:

```json
{
  "states": [
    { "value": "draft", "label": "Draft", "tone": "neutral" }
  ],
  "transitions": [
    {
      "action": "publish",
      "label": "Publish",
      "from": "draft",
      "to": "published",
      "permission": "events.publish",
      "confirmation": "Publish this event? …",
      "irreversible": false
    }
  ]
}
```

**Permission:** `events.view`

### `POST /events/{event}/transitions`

```json
{ "action": "publish" }
```

Valid `action` values: `publish`, `unpublish`, `open_registration`,
`close_registration`, `mark_live`, `complete`, `archive`.

The policy checks the transition's permission; the lifecycle service then
validates the edge against the current state. Illegal edges return `409`.

**Permission:** the permission attached to that transition  
**Response:** `EventResource` in its new state, including the next
`available_transitions`

---

## Event categories

### `GET /event-categories`

Unpaginated. Optional filters: `is_active`, `search`.

**Permission:** `event_categories.view`

### `POST /event-categories`

```json
{
  "name": "Community",
  "slug": "community",
  "description": "…",
  "color": "#640c0e",
  "is_active": true,
  "sort_order": 0
}
```

**Permission:** `event_categories.create`

### `GET /event-categories/{category}`

**Permission:** `event_categories.view`

### `PUT /event-categories/{category}` / `PATCH`

**Permission:** `event_categories.update`

### `DELETE /event-categories/{category}`

Fails with `409` when events still reference the category.

**Permission:** `event_categories.delete`

---

## EventResource (selected fields)

```json
{
  "uuid": "…",
  "name": "MSA Welcome Night",
  "slug": "msa-welcome-night",
  "status": "registration_open",
  "status_label": "Registration Open",
  "status_tone": "success",
  "available_transitions": [
    {
      "action": "close_registration",
      "label": "Close Registration",
      "to": "registration_closed",
      "to_label": "Registration Closed",
      "confirmation": "Close registration for this event? …",
      "irreversible": false,
      "permitted": true
    }
  ],
  "category": { "uuid": "…", "name": "Community", "color": "#…" },
  "organizer": { "uuid": "…", "name": "…", "email": "…" },
  "start_at": "2026-09-10T18:00:00-07:00",
  "end_at": "2026-09-10T21:00:00-07:00",
  "location": "…",
  "capacity": 200
}
```

`available_transitions` is the source of truth for the lifecycle panel. A
transition the state allows but the viewer may not perform still appears with
`permitted: false`.

---

## Phase 2 — Public API (unauthenticated)

Base path: `/api/v1/ems/public`

Rate limits: `ems_public` (default 60/min/IP) on all public routes;
`ems_registration` (default 5/min/IP) additionally on registration.

Draft, archived and non-public events are never returned. Public resources omit
administrative fields (`available_transitions`, `created_by`, internal ids of
team members, attendee email on validation, etc.).

### `GET /public/events`

Query: `search`, `category_id`, `category_slug`, `upcoming`, `past`,
`registration_open`, `registration_closed`, `status`, `sort_by`
(`start_at`|`name`), `sort_direction`, `per_page`, `page`.

### `GET /public/events/calendar`

Query: `starts_after`, `starts_before`, `category_slug`, `upcoming`, `past`,
`search`. Returns a lightweight collection for calendar grids.

### `GET /public/events/{slug}`

Full public landing payload including description and organizer name.

### `POST /public/events/{slug}/register`

Body:

```json
{
  "first_name": "Amina",
  "last_name": "Hassan",
  "email": "amina@example.com",
  "phone": "604-555-0100",
  "student_id": "301234567",
  "notes": "Optional"
}
```

On success (`201`): confirmed free registration + issued ticket(s) with QR
image data URI. Conflicts (`409`): registration closed, capacity exceeded,
duplicate email. Validation errors (`422`) use the standard envelope.

### `GET /public/categories`

Active categories ordered for discovery filters.

### `GET /public/tickets/{code}`

Attendee ticket page payload (event summary, registration status label,
QR image). Does **not** expose `holder_email`.

### `GET /public/tickets/{code}/qr`

`image/png` binary of the ticket QR.

### `GET /public/tickets/validate/{code}`

Infrastructure-only validation. Returns `{ valid: true, code, status, … }` for
an `issued` ticket. Does **not** mark the ticket used. `404` if missing,
`409` if not active.

---

## Phase 3 — Ticketing & payments

### Public

#### `GET /public/events/{slug}/tickets`

Visible, active ticket types for the event.

#### `POST /public/events/{slug}/checkout`

Body includes attendee fields plus required `ticket_type_id` and optional
`quantity`. Returns `checkout_url` when payment is required; otherwise issues
tickets immediately for free types.

#### `POST /public/events/{slug}/waitlist`

Join the waitlist when the event is sold out and waitlists are enabled.

#### `DELETE /public/events/{slug}/waitlist/{entry}`

Leave the waitlist. Optional `?email=` must match when provided.

### Organizer (auth)

| Method | Path |
| --- | --- |
| GET/POST | `/events/{event}/tickets` |
| GET/PUT/DELETE | `/events/{event}/tickets/{ticketType}` |
| POST | `/events/{event}/tickets/reorder` |
| POST | `/events/{event}/tickets/{ticketType}/disable` |
| POST | `/events/{event}/tickets/{ticketType}/duplicate` |
| GET | `/events/{event}/payment-summary` |
| GET | `/orders/{order}` |
| GET | `/payments/{payment}` |
| GET | `/registrations/{registration}` |

### Webhooks

#### `POST /api/v1/webhooks/square`

Square HMAC-SHA256 signature required (`X-Square-Hmacsha256-Signature`).
Processing is idempotent on Square `event_id`.

See [phase3.md](./phase3.md) and [square-setup.md](./square-setup.md).

## Phase 4 — Operations & check-in

Authenticated routes (Sanctum). `{event}` is the event UUID.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/events/{event}/operations` | Live operations summary |
| GET | `/events/{event}/attendees` | Attendee roster (search/filter/sort) |
| GET | `/events/{event}/check-ins/recent` | Recent check-ins |
| POST | `/events/{event}/validate-ticket` | Validate ticket for this event |
| POST | `/events/{event}/check-in` | Redeem via QR / code |
| POST | `/events/{event}/manual-check-in` | Redeem via registration UUID |
| POST | `/events/{event}/walk-in` | Walk-in registration (+ optional check-in) |
| POST | `/events/{event}/undo-check-in` | Undo check-in (audit reason required) |
| POST | `/events/{event}/import/preview` | Multipart CSV/XLSX preview |
| POST | `/events/{event}/import` | Commit previewed import |
| GET/POST | `/events/{event}/import/mappings` | Reusable column mappings |

Check-in failure responses include `data.code` with values such as
`wrong_event`, `already_checked_in`, `payment_required`, `ticket_not_found`.

See [phase4.md](./phase4.md).

## Machine-readable specification

See [openapi.yaml](./openapi.yaml) for the OpenAPI 3.1 description. Expand it
in later phases by adding paths under the same `/api/v1/ems` tag structure.
See also [phase2.md](./phase2.md), [phase3.md](./phase3.md) and [phase4.md](./phase4.md).
