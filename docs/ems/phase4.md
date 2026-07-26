# EMS Phase 4 — Event Operations & Check-in

Phase 4 replaces manual Excel attendance tracking with a unified digital
workflow for EMS registrations and imported attendees.

## Operational workflow

```text
Event Day
  → EMS registrations + imported Excel/CSV attendees
  → Unified attendee list
  → Ticket & QR validation
  → QR scan or manual search
  → Successful check-in
  → Live operations dashboard updates
```

## Features delivered

| Area | Capability |
| --- | --- |
| Operations dashboard | Live counts: registered, checked-in, remaining, capacity, waitlist, walk-ins, attendance %, recent check-ins |
| Attendee management | Search, filter, sort, pagination, bulk selection UI |
| Excel/CSV import | Upload → map columns → validate → preview → import → tickets + QR |
| QR scanner | Camera (`html5-qrcode`) + manual code entry |
| Ticket validation | Exists, active, correct event, registration/payment valid |
| Check-in | QR, manual, walk-in; duplicate detection; undo with audit |
| Event Staff mode | Scanner, search, walk-ins, event info, live count — no revenue/settings |
| Queues | Large imports on `ems-operations` via `ProcessAttendeeImportJob` |
| Audit | Check-ins, undos, imports, walk-ins, validation failures |

## Status labels

| Domain | Values |
| --- | --- |
| Registration | Pending Payment, Registered, Waitlisted, Cancelled, Refunded |
| Payment | Pending, Paid, Failed, Cancelled, Refunded |
| Check-in | Not Checked In, Checked In, Checked Out (foundation only) |

## API (authenticated, `/api/v1/ems`)

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/events/{event}/operations` | Live operations summary |
| GET | `/events/{event}/attendees` | Attendee roster |
| POST | `/events/{event}/import/preview` | Validate spreadsheet |
| POST | `/events/{event}/import` | Commit import |
| GET/POST | `/events/{event}/import/mappings` | Reusable column mappings |
| POST | `/events/{event}/validate-ticket` | Validate without redeeming |
| POST | `/events/{event}/check-in` | QR / code check-in |
| POST | `/events/{event}/manual-check-in` | Search-based check-in |
| POST | `/events/{event}/walk-in` | Walk-in (Square checkout if paid) |
| POST | `/events/{event}/undo-check-in` | Undo with reason |
| GET | `/events/{event}/check-ins/recent` | Recent check-ins |

## Permissions

| Permission | Typical roles |
| --- | --- |
| `registrations.view` / `create` / `update` / `delete` | Admin, Organizer; Staff has view + create |
| `check_ins.view` / `perform` | Admin, Organizer, Staff |
| `check_ins.undo` / `override` | Admin, Organizer |
| `imports.view` / `create` | Admin, Organizer |

Event Staff **cannot** update events, manage tickets, import lists, or undo
check-ins.

## Database (additive)

Migration: `2026_07_25_001200_add_phase4_event_operations.php`

- `ems_check_ins.device`, undo columns (undo deletes the active row after audit)
- `ems_check_in_audits` — full operational audit trail
- `ems_attendee_imports` — import batches + preview summary
- `ems_import_column_mappings` — reusable column maps

## Frontend routes

| Path | Page |
| --- | --- |
| `/ems/events/:uuid/operations` | Operations dashboard |
| `/ems/events/:uuid/attendees` | Attendee management |
| `/ems/events/:uuid/import` | Import wizard |
| `/ems/events/:uuid/check-in` | Scanner |
| `/ems/events/:uuid/staff` | Event Staff mode |

## Camera permissions (deployment)

QR scanning requires a camera-capable browser:

- **HTTPS** is required on most mobile browsers (except `localhost`)
- Users must grant camera permission when prompted
- If permission is denied, staff can still enter ticket codes manually
- Prefer rear camera (`facingMode: environment`) on phones
- Test on Chrome/Safari before event day; keep a spare device ready

## Queue workers

Process the operations queue alongside payments/notifications:

```bash
php artisan queue:work --queue=ems-operations,ems-payments,ems-notifications,default
```

## Testing

```bash
php artisan test --filter=EmsPhase4OperationsTest
```

Covers CSV import, validation, QR check-in, wrong event, duplicates, manual
check-in, undo, walk-ins, and Event Staff RBAC.
