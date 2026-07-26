# EMS Phase 2 — Public Events & Registration

Phase 2 turns the EMS into a complete **free-event** public platform on top of
the Phase 1 foundation. No database restructuring was required; Phase 1 tables,
enums, contracts and permissions were extended and activated.

## Workflow delivered

```text
Public Event
      ↓
Browse / search / filter / calendar
      ↓
View Event (/events/{slug})
      ↓
Register (free)
      ↓
Registration Created (confirmed)
      ↓
Ticket Generated (unique code)
      ↓
QR Code Generated
      ↓
Ticket Page (/tickets/{code})
```

## Public frontend routes

| Route | Purpose |
| --- | --- |
| `/events` | Discovery listing (search, category, upcoming/past, registration filters) |
| `/events/calendar` | Monthly and weekly calendar |
| `/events/:slug` | Event landing page + one-page free registration |
| `/tickets/:code` | Ticket page with QR, print and download |

Legacy `/ems-events/*` URLs redirect to `/events/*`. The homepage events
section may still preview CMS content until that feed is migrated.

## Public API (no auth)

Base: `/api/v1/ems/public`

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/events` | Paginated public listing |
| GET | `/events/calendar` | Calendar feed for a date window |
| GET | `/events/{slug}` | Public landing payload |
| POST | `/events/{slug}/register` | Free registration (rate limited) |
| GET | `/categories` | Active categories |
| GET | `/tickets/{code}` | Attendee ticket + QR image |
| GET | `/tickets/{code}/qr` | PNG QR binary |
| GET | `/tickets/validate/{code}` | Validate without redeeming |

Draft and non-public events are hidden. Ticket validation never marks a ticket
as used and does not expose attendee email / phone / notes.

## Database change in Phase 2

| Change | Notes |
| --- | --- |
| `ems_events.banner_url` | Optional nullable URL for public heroes (migration `001000`) |

Guest registration, ticket codes, QR payload columns and statuses already
existed from Phase 1.

## Services & seams activated

| Component | Role |
| --- | --- |
| `PublicEventService` | Discovery, calendar, ticket lookup / validate |
| `RegistrationService` | Free registration, capacity lock, duplicate prevention |
| `DefaultTicketIssuer` | Implements `TicketIssuer` — issue / QR / revoke |
| `TicketCodeGenerator` | Non-sequential `MSA-…` codes + `REG-…` references |
| `QrCodeGenerator` | Server-side PNG/SVG/data-URI via Endroid |

## Tests

- `tests/Feature/Ems/EmsPublicEventsTest.php` — listing, search, filters,
  registration, duplicates, capacity, ticket page, validation, QR, drafts
- `tests/Unit/Ems/TicketIssuerTest.php` — issuance, code format, QR payload

## Intentionally deferred to Phase 3+

### Phase 3 — Paid ticketing
- Square Checkout, payment processing, webhooks, refunds
- Ticket type selection / purchasing UI
- `AwaitingPayment` → paid confirmation path (schema already supports it)

### Phase 4 — Check-in
- QR scanning / camera, marking tickets `redeemed`
- Staff check-in UI, walk-ins

### Phase 5 — Communications
- Confirmation / reminder / cancellation emails

### Phase 6 — Analytics
- Revenue, attendance dashboards, reports

### Phase 8 — Advanced
- Promo codes, templates, recurring events, feedback, external calendar sync

## Phase 3 compatibility

Free registrations confirm immediately and issue tickets. Paid flow will:

1. Create registration as `awaiting_payment` with `type=paid`
2. Collect payment via `PaymentGateway` (Square)
3. Confirm registration on webhook
4. Call the same `TicketIssuer::issueFor()`

No major model changes are required for that path.
