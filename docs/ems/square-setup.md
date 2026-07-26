# Square Hosted Checkout — EMS Deployment Guide

Square is the payment provider only. The EMS owns events, orders, registrations,
tickets and QR codes.

## 1. Square Developer Dashboard

1. Open [Square Developer](https://developer.squareup.com/).
2. Create or select the MSA application.
3. Enable **Sandbox** for development and **Production** for live events.
4. Copy:
   - Application ID
   - Access Token (Sandbox or Production)
   - Location ID
5. Under **Webhooks**, create a subscription:
   - Notification URL: `https://<your-api-host>/api/v1/webhooks/square`
   - Events: `payment.updated`, `payment.created` (and refund events if desired)
6. Copy the **Webhook Signature Key**.

The notification URL string used for HMAC verification **must exactly match**
the URL configured in Square (scheme, host, path, no trailing slash mismatch).

## 2. Environment variables

Set these on the Laravel backend only. Never expose them to Vite / the browser.

```env
EMS_PAYMENTS_ENABLED=true
EMS_PAYMENT_PROVIDER=square
EMS_PAYMENTS_QUEUE=ems-payments

SQUARE_ENVIRONMENT=sandbox   # or production
SQUARE_APPLICATION_ID=...
SQUARE_ACCESS_TOKEN=...
SQUARE_LOCATION_ID=...
SQUARE_WEBHOOK_SIGNATURE_KEY=...
SQUARE_WEBHOOK_NOTIFICATION_URL="${APP_URL}/api/v1/webhooks/square"
```

Frontend needs no Square secrets. Public pages only receive a `checkout_url`
from the EMS API and redirect the browser.

## 3. Queue workers

Run workers that consume EMS queues:

```bash
php artisan queue:work --queue=ems-payments,ems-notifications,default
```

## 4. Sandbox checklist

1. `EMS_PAYMENTS_ENABLED=true` with sandbox credentials.
2. Create a paid ticket type on a public event with registration open.
3. Complete public checkout → redirect to Square sandbox.
4. Pay with Square sandbox test cards.
5. Confirm webhook arrives at `/api/v1/webhooks/square`.
6. Verify:
   - Payment `paid`
   - Order `completed`
   - Registration `confirmed` (Registered)
   - Ticket + QR exist
   - Confirmation notification row queued

## 5. Production checklist

1. Switch `SQUARE_ENVIRONMENT=production` and production credentials.
2. Update webhook URL to the production API host.
3. Confirm HTTPS only.
4. Confirm access token / webhook secret are not in frontend env files,
   source control or client bundles.
5. Keep `EMS_PAYMENTS_ENABLED=false` until credentials are verified.

## 6. Security notes

- Webhook signatures are verified with HMAC-SHA256 over
  `notification_url + raw_body`.
- Duplicate Square `event_id` values are ignored via `ems_webhook_events`.
- Card data never touches EMS servers (Hosted Checkout).
- Logs redact tokens and payment secrets (`config/ems.php` → `logging.redacted_keys`).

## 7. Troubleshooting

| Symptom | Check |
| --- | --- |
| Checkout creation fails | Access token, location id, `EMS_PAYMENTS_ENABLED` |
| Webhook 401 | Signature key + exact notification URL |
| Payment paid but no ticket | Queue worker running; inspect `ems` log channel |
| Duplicate tickets | Should not happen — webhook ledger blocks replays |
