# Square Integration — EMS Deployment Guide

Square is the commerce and payment provider. The EMS owns events, ticket
types, attendees, registrations, tickets, QR codes, and check-in.

Square owns catalog commerce representation, payments, refunds, and
card-present hardware (POS, Reader, Terminal).

**Business rule:** EMS ticket types must be associated with EMS events and,
for paid tickets, with Square Catalog variations via a durable mapping. Do
not rely on matching names or prices.

Square API version used by this application: **2026-07-15**
(`SQUARE_API_VERSION`, default in `config/ems.php`).

---

## 1. Square application configuration

1. Open [Square Developer](https://developer.squareup.com/).
2. Create or select the MSA application.
3. Use **Sandbox** for development and **Production** for live events.
4. Copy:
   - Application ID
   - Access Token (Sandbox or Production)
   - Location ID
5. Optional, for EMS-initiated Terminal checkout: copy a Terminal **device ID**.
6. Keep a **single seller access token**. This integration does not use
   multi-merchant OAuth.

Never put the access token or webhook signature key in frontend env files,
Vite config, or client bundles.

## 2. Required permissions

The seller access token must allow:

| Permission | Why |
|---|---|
| `ITEMS_READ` | Read Square Catalog for sync and POS mapping |
| `ITEMS_WRITE` | Create/update EMS-managed catalog items and variations |
| `ORDERS_READ` | Retrieve POS/online orders during ingest and reconciliation |
| `ORDERS_WRITE` | Create Payment Link / order line items |
| `PAYMENTS_READ` | Webhooks, payment retrieve, refunds, Terminal status |
| `PAYMENTS_WRITE` | Hosted Checkout, Terminal checkout, Refunds API |

Do not request extra OAuth scopes this application does not use.

## 3. Required environment variables

Set these on the Laravel backend only.

```env
EMS_PAYMENTS_ENABLED=true
EMS_PAYMENT_PROVIDER=square
EMS_PAYMENTS_QUEUE=ems-payments
EMS_CHECKOUT_TTL_MINUTES=1440

SQUARE_ENVIRONMENT=sandbox   # or production
SQUARE_APPLICATION_ID=...
SQUARE_ACCESS_TOKEN=...
SQUARE_LOCATION_ID=...
SQUARE_WEBHOOK_SIGNATURE_KEY=...
SQUARE_WEBHOOK_NOTIFICATION_URL="${APP_URL}/api/v1/webhooks/square"
SQUARE_API_VERSION=2026-07-15

# Optional. Required only for EMS-initiated Square Terminal checkout.
SQUARE_TERMINAL_DEVICE_ID=
```

Frontend needs no Square secrets. Public pages receive a `checkout_url`
from the EMS API and redirect the browser.

## 4. Webhook endpoint

Notification URL:

```
https://<your-api-host>/api/v1/webhooks/square
```

The string used for HMAC verification **must exactly match** the URL
configured in the Square Developer Dashboard (scheme, host, path, no
trailing-slash mismatch).

Signature: HMAC-SHA256 of `notificationUrl + rawBody`, base64 encoded
(`X-Square-Hmacsha256-Signature`).

## 5. Webhook event subscriptions

Subscribe at minimum to:

- `payment.created`
- `payment.updated`
- `order.created` (optional but useful)
- `order.updated` (optional but useful)
- `refund.created`
- `refund.updated`
- `catalog.version.updated`
- `terminal.checkout.created` (if using Terminal)
- `terminal.checkout.updated` (if using Terminal)

Unmatched events are stored as `unmatched` and can be retried. They are
**not** discarded as `processed`.

## 6. Catalog synchronization

Authority model:

- **EMS** owns event identity, ticket type identity, price for EMS-managed
  items, capacity, attendees, registrations, tickets, and check-in.
- **Square** owns catalog commerce representation, payments, and refunds.

When an administrator creates or updates a paid EMS ticket type, EMS upserts:

- Square Catalog Item named after the **event** (for example `Frosh 2026`)
- Square Catalog Variation named after the **ticket type** (for example
  `General Admission`) at the ticket price

Durable mapping is stored in `ems_square_catalog_mappings`:

- `square_catalog_item_id`
- `square_catalog_variation_id`
- `square_location_id`
- sync status / last synced / last error / conflict summary

Square custom attributes (EMS namespace):

- `ems_managed`
- `ems_ticket_type_uuid`
- `ems_event_uuid`

EMS provisions those definitions automatically before the first catalog
sync. See **6.1 Catalog Custom Attribute Provisioning**.

Idempotency keys are stable per ticket type so retries do not create
duplicates.

Square-side edits to EMS-managed items are detected as **conflicts** and
surfaced in the admin ticket UI. They are not silently overwritten. Use
**Refresh from Square** to apply Square as the source for name/price, or
**Sync to Square** to push EMS as the source.

Unrelated Square merchandise is **never** imported automatically.

To attach an existing Square variation to an EMS ticket type, use the
explicit import endpoint:

```
POST /api/v1/ems/events/{event}/tickets/{ticketType}/import-square
{ "square_catalog_variation_id": "..." }
```

Existing production ticket types: run sync once per type (admin **Sync to
Square**, or create/update the type). If a mapping already exists, EMS
updates that object. It does not create a second item.

Free ticket types are not published to Square Catalog.

## 6.1 Catalog Custom Attribute Provisioning

EMS identifies its own Square Catalog objects with three seller-scoped Catalog
custom attribute definitions:

| Key | Type | Purpose |
|---|---|---|
| `ems_managed` | STRING (`"true"`) | Marks the item/variation as EMS-owned. Unrelated merchandise must not have this mapping. |
| `ems_ticket_type_uuid` | STRING | Durable EMS ticket type UUID on the Square variation. |
| `ems_event_uuid` | STRING | Durable EMS event UUID on the Square item and variation. |

These are **Catalog custom attribute definitions** (`CUSTOM_ATTRIBUTE_DEFINITION`
objects), not Order or Customer custom attributes. They belong to the Square
seller/catalog that the access token authenticates against. Sandbox and
Production are separate catalogs; each environment must have its own
definitions.

**Administrators do not need to create these in the Square Dashboard.** Before
the first catalog upsert, EMS lists existing `CUSTOM_ATTRIBUTE_DEFINITION`
objects (`GET /v2/catalog/list?types=CUSTOM_ATTRIBUTE_DEFINITION`) and creates
only the missing keys (`POST /v2/catalog/batch-upsert`, one definition per
request, stable idempotency key `ems-cad-v1-{key}`). Existing definitions are
reused. The step is safe to run repeatedly.

Definitions are created with `APP_VISIBILITY_HIDDEN` and
`SELLER_VISIBILITY_HIDDEN` so they do not appear as editable item fields and
do not consume the seller-visible custom-attribute quota. EMS still reads and
writes the values because it owns the definitions.

If a previous EMS version recorded `catalog_attr_defs=ready` without verifying
that all three keys exist, the next sync lists Square again and creates any
missing definitions (including `ems_event_uuid`) before attaching values.

### Diagnosing provisioning failures

Symptom: ticket Square Sync = Failed, last error similar to:

```
Custom attribute definition with key "ems_event_uuid" not found
Unable to provision Square Catalog custom attribute "ems_event_uuid": ...
```

What to check:

1. `ITEMS_READ` and `ITEMS_WRITE` on the production access token.
2. EMS logs `ems.square.catalog.attr_defs.create_failed` and
   `ems.square.catalog.attr_defs.failed` (keys only; no tokens).
3. Square seller custom-attribute limits (10 seller-visible and 10 seller-hidden
   per account). Hidden EMS definitions count toward the hidden quota.
4. Name uniqueness: definition **names** (`EMS managed`, `EMS ticket type UUID`,
   `EMS event UUID`) must be unique per seller/application pair.
5. Retry **Sync to Square** after fixing credentials or quota. Do not create
   duplicate catalog items; EMS updates the stored mapping.

Do not attach `ems_managed` / `ems_ticket_type_uuid` / `ems_event_uuid` values
to unrelated Square merchandise. Import remains an explicit admin action.

## 7. Online checkout

Flow:

1. Buyer starts checkout on the public event page.
2. EMS creates a pending order, registration, and payment, and reserves
   inventory.
3. EMS creates a Square Payment Link (`POST /v2/online-checkout/payment-links`)
   with a stable idempotency key `ems-plink-{payment.uuid}`.
4. The Payment Link URL is persisted on the EMS payment.
5. After Square payment, webhooks mark the payment paid and EMS issues tickets.

## 8. Resume / abandoned checkout

If the buyer leaves Square:

- `POST /api/v1/ems/public/events/{slug}/checkout` with the same email
  resumes the stored Payment Link when it is still valid.
- `POST /api/v1/ems/public/events/{slug}/checkout/resume` does the same
  explicitly.
- `POST /api/v1/ems/public/events/{slug}/checkout/cancel` cancels the
  session, deletes the Payment Link when possible, and releases inventory.

Expired sessions are cleaned by `ExpireAbandonedCheckoutsJob` (every 15
minutes). A delayed webhook after expiration still fulfills the payment
and re-reserves inventory if Square actually captured funds.

## 9. POS workflow (Square POS app + Reader)

This is **not** the same as Square Terminal API.

1. Sync EMS paid ticket types to Square Catalog.
2. Staff open the Square POS app and sell the event item / variation.
3. Customer pays with a Square Reader.
4. Square sends `payment.updated`.
5. EMS maps `catalog_object_id` → catalog mapping → ticket type → event.
6. EMS creates order, walk-in registration (not assigned to staff), payment,
   ticket, and QR.

If attendee identity is missing, EMS uses a labeled **Walk-in** attendee
with empty email. Multiple walk-ins by the same staff member are allowed.

Unmapped POS sales stay unmatched and are not turned into EMS tickets.

## 10. Terminal workflow

Requires `SQUARE_TERMINAL_DEVICE_ID` or a `device_id` on the request.

```
POST /api/v1/ems/events/{event}/terminal-checkout
```

Flow: EMS pending order → Square Terminal checkout → customer pays on the
Terminal → `terminal.checkout.updated` → EMS confirms payment and issues
tickets.

Statuses handled: pending, completed, canceled/failed.

If no device ID is configured, Terminal checkout returns a validation
error. POS app + Reader sales still work without this setting.

## 11. Refund workflow

EMS → Square:

```
POST /api/v1/ems/payments/{payment}/refund
{ "amount": 15.00, "reason": "..." }   # amount optional = full refund
```

Requires `payments.refund`. EMS creates a Square refund with a unique
idempotency key and stores it as **pending** until Square reports
`COMPLETED`, `FAILED`, or `REJECTED`.

- Full completed refund: payment/order/registration refunded, ticket revoked.
- Partial completed refund: payment `partially_refunded`, ticket remains valid.
- Failed/rejected refund: EMS payment stays paid, ticket remains valid.

Square → EMS: `refund.created` / `refund.updated` (Dashboard, POS, Terminal)
are matched by Square payment ID.

A fully refunded ticket cannot be checked in. Reason: **Ticket refunded.**

## 12. Reconciliation

```bash
php artisan ems:square-reconcile
php artisan ems:square-reconcile --since=2026-08-01T00:00:00Z
```

Scheduled hourly. Safe to re-run. Only imports payments whose catalog
variations map to EMS ticket types. Never duplicates tickets.

Also retries webhook rows in `unmatched`, `failed`, or `retry_pending`.

## 13. Sandbox testing

1. Enable `EMS_PAYMENTS_ENABLED=true` with sandbox credentials.
2. Create a paid ticket type and confirm Square Sync = Connected.
3. Public checkout → Square sandbox card → webhook → ticket + QR.
4. Leave checkout and resume the same Payment Link.
5. Sell the synced item in Square POS sandbox (if available).
6. Refund from EMS and from the Square Dashboard.
7. Confirm a refunded ticket is denied at check-in.

## 14. Production setup

1. Switch `SQUARE_ENVIRONMENT=production` and production credentials.
2. Point the webhook URL at the production API host over HTTPS.
3. Subscribe to the events listed above.
4. Confirm tokens are server-side only.
5. Keep `EMS_PAYMENTS_ENABLED=false` until credentials and webhooks are verified.
6. Sync existing paid ticket types once; do not create duplicate catalog items.

## 15. Failure recovery

| Symptom | What to do |
|---|---|
| Ticket type Square Sync = Failed | Open the ticket, **Retry sync**. Check ITEMS_WRITE and logs `ems.square.catalog.sync_failed`. If the error mentions a custom attribute definition key, see **6.1**. |
| Buyer cannot resume checkout | Confirm `checkout_url` / expiry. If expired, start a new checkout (inventory should be released). |
| POS sale missing from EMS | Confirm the variation is mapped. Run `php artisan ems:square-reconcile`. Check unmatched webhooks. |
| Payment paid in Square, EMS still pending | Replay is safe. Check webhook signature URL. Run reconciliation. |
| Refund pending forever | Check `refund.updated` subscription. Inspect `ems_square_refunds.status`. |
| Unmatched webhooks grow | Mapping missing, or sale is unrelated merchandise. Do not import blindly. |

## 16. Diagnosing unmatched transactions

Admin → EMS system → Integrations shows:

- unmatched webhook count
- failed catalog sync count
- last successful catalog sync
- Catalog / Orders / Payments / Refunds API probe status
- Terminal configured vs not configured

Inspect `ems_webhook_events` where `status = unmatched`. Correlate
`event_id`, Square payment ID, and Square order ID from the redacted
payload. Never log access tokens, PAN, CVV, or webhook secrets.

Queue workers:

```bash
php artisan queue:work --queue=ems-payments,ems-notifications,ems-operations,default
```

## 17. Security notes

- Webhook signatures are verified before persistence.
- Duplicate Square `event_id` values are not re-fulfilled once processed.
- Previously unmatched events may be reprocessed after a mapping exists.
- Card data never touches EMS servers for hosted checkout or POS.
- Logs redact tokens (`config/ems.php` → `logging.redacted_keys`).
