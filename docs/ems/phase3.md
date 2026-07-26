# EMS Phase 3 — Ticketing & Payments

Phase 3 adds paid-event ticketing on top of the Phase 1/2 foundation. The EMS
remains the system of record for events, ticket types, orders, registrations,
tickets and QR codes. Square handles hosted checkout and payment processing only.

## Workflow

### Free

```text
Select free ticket → Order + Registration (Registered) → Ticket + QR → Confirmation queued
```

### Paid

```text
Select paid ticket
  → Validate capacity / sales window / limits
  → Create Order (Pending)
  → Create Registration (Pending Payment)
  → Create Payment (Pending → Processing)
  → Create Square Hosted Checkout
  → Redirect attendee to Square
  → Square webhook (signature verified, idempotent)
  → Payment = Paid, Order = Completed, Registration = Registered
  → Ticket issued + QR generated
  → Confirmation email queued
```

Paid tickets are **never** issued before verified payment.

## Database (additive)

Migration: `2026_07_25_001100_add_phase3_ticketing_and_payments.php`

| Table / column | Purpose |
| --- | --- |
| `ems_orders` | Purchase attempts |
| `ems_order_items` | Line items per order |
| `ems_waitlist_entries` | Ordered waitlist queue |
| `ems_webhook_events` | Idempotent webhook ledger |
| `ems_events.waitlist_enabled` | Toggle waitlist |
| `ems_events.max_tickets_per_order` | Per-order limit |
| `ems_events.max_registrations_per_attendee` | Per-attendee limit |
| `ems_events.registration_deadline_at` | Hard deadline |
| `ems_ticket_types.is_visible` / `max_per_order` | Public visibility & purchase cap |
| `ems_registrations.order_id` / `waitlist_position` | Order + waitlist linkage |
| `ems_payments.order_id` / checkout & transaction ids | Provider references |

## API

### Public (no auth)

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/api/v1/ems/public/events/{slug}/tickets` | Public ticket types |
| POST | `/api/v1/ems/public/events/{slug}/register` | Free registration (Phase 2 + ticket type) |
| POST | `/api/v1/ems/public/events/{slug}/checkout` | Paid/free checkout |
| POST | `/api/v1/ems/public/events/{slug}/waitlist` | Join waitlist |
| DELETE | `/api/v1/ems/public/events/{slug}/waitlist/{entry}` | Leave waitlist |

### Authenticated organizer

| Method | Path | Purpose |
| --- | --- | --- |
| CRUD | `/api/v1/ems/events/{event}/tickets` | Ticket type management |
| POST | `…/tickets/reorder` | Reorder |
| POST | `…/tickets/{id}/disable` | Disable |
| POST | `…/tickets/{id}/duplicate` | Duplicate |
| GET | `/api/v1/ems/events/{event}/payment-summary` | Lightweight revenue summary |
| GET | `/api/v1/ems/orders/{order}` | Order detail |
| GET | `/api/v1/ems/payments/{payment}` | Payment detail |
| GET | `/api/v1/ems/registrations/{registration}` | Registration detail |

### Webhooks

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/api/v1/webhooks/square` | Square payment events |

## Payment states

`pending` → `authorized` / `processing` → `paid` | `failed` | `cancelled` → `refunded` (foundation)

Invalid transitions are rejected.

## Registration states (labels)

| Value | Label |
| --- | --- |
| `awaiting_payment` | Pending Payment |
| `confirmed` | Registered |
| `waitlisted` | Waitlisted |
| `cancelled` | Cancelled |

## Architecture seams

| Component | Role |
| --- | --- |
| `PaymentProvider` contract | Provider-agnostic checkout / webhook / refund |
| `SquarePaymentProvider` | Square Hosted Checkout (Payment Links) + HMAC verify |
| `PaymentProviderManager` | Resolves configured driver (`square` today; Stripe/PayPal stubs reserved) |
| `CheckoutService` | Free + paid order creation |
| `PaymentFulfillmentService` | Paid → confirm → issue tickets |
| `SquareWebhookService` | Signature + idempotency + fulfillment |
| `PaymentReconciliationService` | Amount / currency / ticket consistency checks |
| `WaitlistService` | Join / leave / promote |
| `TicketTypeService` | Organizer ticket CRUD |

## Queues

| Job | Queue | Purpose |
| --- | --- | --- |
| `QueueRegistrationConfirmation` | `ems-notifications` | Persist confirmation notification row |
| `ReconcilePaymentJob` | `ems-payments` | Post-payment reconciliation |

## Frontend

| Surface | Path |
| --- | --- |
| Organizer ticket + payment summary | `/ems/events/:uuid` |
| Public registration / checkout | `/events/:slug` |
| Checkout success | `/events/:slug/checkout/success` |
| Checkout cancel | `/events/:slug/checkout/cancel` |

## Tests

`tests/Feature/Ems/EmsPhase3TicketingPaymentsTest.php` covers ticket CRUD,
capacity, free issuance, paid checkout, fulfillment, webhook signature,
idempotency, waitlist, registration limits and payment summary.

## Square setup

See [square-setup.md](./square-setup.md).
