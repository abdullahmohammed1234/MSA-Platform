# Phase 5 — Communications & Notification Automation

Phase 5 makes the EMS responsible for creating attendee communication requests
and the existing Platform Queues infrastructure responsible for delivering them.

Nothing in Phase 5 sends email synchronously on a user request path.

```text
Event Action
  → Notification Created (ems_notifications)
  → Notification Service
  → Queue Job (ems-notifications)
  → Platform Queues
  → Email Worker
  → Email Provider
  → Delivery Status → Notification History Updated
```

## What shipped

| Capability | Implementation |
| --- | --- |
| Registration confirmation | `QueueRegistrationConfirmation` → `EventCommunicationService` |
| Ticket + QR delivery | Bundled with confirmation; reissue via resend API |
| Payment confirmation / failure | Paid fulfillment + `markFailed` hooks |
| Configurable reminders | `ems_event_reminders` + `ReminderService` |
| Event update notifications | `notify_audience` on event update |
| Event cancellation | `EventTransition::Cancel` → `EventCancellationService` |
| Refund notifications | Initiated on cancel; completed/failed APIs foundation |
| Waitlist communications | Join / leave / promote hooks |
| Post-event | Thank-you + feedback on Complete; recap/certificate foundation |
| Templates | `ems_email_templates` + `EmsEmailTemplateSeeder` |
| Preferences | `ems_notification_preferences` (transactional always send) |
| History / resend / retry | Organizer APIs + `/ems/events/:uuid/notifications` UI |
| Queue integration | `SendEventNotificationJob`, due processors on scheduler |

## Architecture

- **Contract:** `App\Ems\Contracts\EventNotificationDispatcher`
- **Implementation:** `App\Ems\Services\Notifications\QueuedEventNotificationDispatcher`
- **Ledger:** `ems_notifications` (`EventNotification`)
- **Delivery job:** `App\Ems\Jobs\SendEventNotificationJob`
- **Queue name:** `ems-notifications` (`EMS_NOTIFICATIONS_QUEUE`)

Future channels (SMS, push, WhatsApp, in-app) plug in through
`NotificationChannel` and alternate jobs without restructuring EMS flows.

## Reminder configuration

Reminders are per-event, not hardcoded intervals.

Fields:

- `offset_value` / `offset_unit` (`minutes` | `hours` | `days`)
- `enabled`
- `template_key`
- `audience` (`all` | `confirmed` | `ticket_holders`)

Defaults (disabled) are seeded when registration opens:

- 7 days, 3 days, 1 day, 6 hours, 1 hour before start

Duplicate prevention uses `ems_reminder_dispatches` unique on
`(reminder_id, registration_id)`.

Scheduler (every minute):

- `ProcessDueRemindersJob`
- `ProcessDueNotificationsJob`

## Email templates

Editable by users with `notification_templates.manage`.

Placeholders include:

```text
{{ attendee_name }} {{ event_name }} {{ event_date }} {{ event_time }}
{{ event_location }} {{ ticket_type }} {{ registration_number }}
{{ ticket_number }} {{ qr_code }} {{ ticket_download_link }}
{{ event_details_link }} {{ order_number }} {{ amount_paid }}
{{ currency }} {{ payment_reference }} {{ square_transaction_reference }}
{{ refund_amount }} {{ change_summary }} {{ feedback_link }}
{{ organizer_name }} {{ cancellation_reason }}
```

Seed with:

```bash
php artisan db:seed --class=Database\\Seeders\\Ems\\EmsEmailTemplateSeeder
```

## Notification preferences

Attendees may toggle:

- event reminders
- event updates
- feedback requests
- marketing emails (foundation)
- post-event

**Transactional** emails (registration, tickets, payments, cancellations,
refunds, waitlist) always deliver.

## Organizer UI

`/ems/events/:uuid/notifications`

- Summary cards (total / queued / sent / failed / pending reminders)
- History with filters + failed retry
- Manual resend by registration UUID
- Reminder CRUD

## API (prefix `/api/v1/ems`)

```text
GET    /events/{event}/notifications/summary
GET    /events/{event}/notifications
POST   /events/{event}/notifications/resend
POST   /events/{event}/notifications/{notification}/retry
GET    /events/{event}/reminders
POST   /events/{event}/reminders
PUT    /events/{event}/reminders/{reminder}
DELETE /events/{event}/reminders/{reminder}
GET    /notifications/{notification}
GET    /email-templates
PUT    /email-templates/{template}
GET    /notification-preferences
PUT    /notification-preferences
```

Event update accepts optional `notify_audience`:
`everyone` | `registered` | `ticket_holders` | `none`.

Cancel event via existing transitions endpoint:

```text
POST /events/{event}/transitions  { "action": "cancel" }
```

## Permissions

| Slug | Purpose |
| --- | --- |
| `notifications.view` | History / summary |
| `notifications.send` | Resend / retry |
| `notifications.manage` | Reminder configuration |
| `notification_templates.view` | View templates |
| `notification_templates.manage` | Edit templates |
| `events.cancel` | Cancel lifecycle transition |

## Environment

```env
EMS_NOTIFICATIONS_ENABLED=true
EMS_NOTIFICATIONS_QUEUE=ems-notifications
EMS_MAIL_FROM_ADDRESS=events@example.com
EMS_MAIL_FROM_NAME="SFU MSA Events"

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=...
MAIL_FROM_NAME=...
QUEUE_CONNECTION=database
```

Worker:

```bash
php artisan queue:work --queue=ems-notifications,ems-operations,ems-payments,default
```

Scheduler (already registered in `bootstrap/app.php`):

```bash
php artisan schedule:work
```

## Security & logging

- Policy gates: `viewNotifications`, `sendNotifications`, `manageNotifications`
- Audit-style logs on channel `ems` (no card data / tokens)
- Resend / template edits / reminder execution / delivery failures logged

## Explicitly not in Phase 5

- SMS / push / WhatsApp / in-app messaging delivery
- Certificate generation (notification foundation only)
- Automatic Square refund capture (workflow initiated only)
- Marketing campaign builder
- Phase 6 analytics dashboards
