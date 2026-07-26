# Intentionally Deferred after Phase 5

Phase 5 delivered asynchronous attendee communications via Platform Queues,
configurable reminders, templates, preferences, history, and organizer tools.
The items below remain deferred.

## Phase 6 — Analytics & finance

| Deferred | Foundation already in place |
| --- | --- |
| Advanced revenue dashboards | Lightweight payment summary on operations |
| Attendance / no-show analytics charts | Check-in + operations summary |
| Demographic / trend reporting | Attendee metadata foundation |
| Full Square refund capture | Refund workflow initiated + refund notification types |

## Phase 7 / Advanced (later)

| Deferred | Notes |
| --- | --- |
| Event templates / recurring events | Not modelled |
| Promo codes | Belong with ticket pricing |
| Feedback survey product | Feedback *request* emails ship in Phase 5 |
| Calendar integrations | Can use public slug/UUID |
| Stripe / PayPal live drivers | Enum + `PaymentProvider` contract reserved |
| Checked-out attendance | Enum foundation only (`check_out`) |
| Certificate generation | Certificate-*available* notification foundation only |

## Future notification channels

| Deferred | Foundation |
| --- | --- |
| SMS / WhatsApp / push / in-app | `NotificationChannel` enum + dispatcher seam |

## Explicit non-goals of Phase 5

- No SMS / push / WhatsApp delivery
- No marketing campaign builder
- No automatic Square refund settlement
- No certificate PDF generation
- No Phase 6 analytics charts
- Homepage events section may still read the legacy CMS feed (public `/events` is EMS)
