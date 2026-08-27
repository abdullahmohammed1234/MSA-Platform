# SFU MSA Platform — Scheduling & Queue Infrastructure Audit

This document presents a comprehensive, read-only infrastructure audit of the **SFU MSA Platform**'s background processing, queue subsystems, and scheduler configurations.

---

## A. Executive Summary

The SFU MSA Platform relies on a decoupled, asynchronous queue and scheduler architecture to offload time-consuming tasks (email sending, PDF generation, database optimization, and Square API calls) from the main request-response lifecycle.

The current architecture is split into two layers:
1. **The Laravel Scheduler (cron-driven)**: Evaluates cron frequencies every minute and triggers maintenance operations, analytics rollups, and EMS polling jobs.
2. **The Queue Subsystem (worker-driven)**: Consumes queued tasks asynchronously. Queues are divided into 6 distinct channels (`ems-payments`, `ems-notifications`, `ems-operations`, `high`, `default`, `low`) to provide database and resource isolation.

Because the system is deployed in a **cPanel shared-hosting environment** without root access, it lacks a native daemon monitor (like Supervisor or systemd) to run persistent queue workers. The production deployment instead uses overlapping cron tasks (`queue:work --stop-when-empty` and `schedule:run`) to simulate background workers.

---

## B. Scheduler Inventory

All scheduled tasks are registered in [bootstrap/app.php](file:///d:/projects/MSA%20Platform/backend/bootstrap/app.php#L37-L76).

| Task Class | Frequency | Trigger / Time | Purpose | Target Queue | Criticality | Max Acceptable Delay | EMS Related? |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `AggregateAnalyticsMetricsJob` | Daily | `00:00` | Aggregates learner and site engagement metrics, flushes cached APIs | `low` | 🟢 Non-critical | 12 hours | No |
| `CertificateVerificationCleanupJob` | Daily | `00:00` | Prunes certificate verification logs older than 180 days | `low` | 🟢 Non-critical | 24 hours | No |
| `DatabaseMaintenanceJob` | Daily | `00:00` | Runs table optimizations, purges old PDF reports and performance metrics | `low` | 🟢 Non-critical | 24 hours | No |
| `NotificationCleanupJob` | Daily | `00:00` | Deletes standard notifications and logs older than 60 days | `low` | 🟢 Non-critical | 24 hours | No |
| `ArchiveOldLogsJob` | Monthly | 1st of month at `01:00` | Archives job logs older than 30 days to JSONL files | `low` | 🟢 Non-critical | 24 hours | No |
| `GenerateDailyReportJob` | Daily | `23:55` | Generates the daily analytics summary and emails it to admins | `low` | 🟢 Non-critical | 6 hours | No |
| `GenerateWeeklyReportJob` | Weekly | Sunday at `23:59` | Generates the weekly analytics summary and emails it to admins | `low` | 🟢 Non-critical | 12 hours | No |
| `GenerateMonthlyReportJob` | Monthly | Last day at `23:59` | Generates the monthly analytics summary and emails it to admins | `low` | 🟢 Non-critical | 24 hours | No |
| `ProcessDueRemindersJob` | Every Minute | Continuous | Scrapes pending event reminders and schedules notifications | `ems-notifications` | 🟡 Important | 5 minutes | **Yes** |
| `ProcessDueNotificationsJob` | Every Minute | Continuous | Dispatches scheduled notifications whose send-time has arrived | `ems-notifications` | 🟡 Important | 5 minutes | **Yes** |
| `ExpireAbandonedCheckoutsJob` | Every 15 mins | Continuous | Releases ticket capacities from checkouts unpaid past TTL (default 24h) | `ems-payments` | 🔴 Critical | 15 minutes | **Yes** |
| `ReconcileSquareSalesJob` | Hourly | Continuous | Ingests missing payments and processes refunds directly from Square | `ems-payments` | 🟡 Important | 1 hour | **Yes** |

---

## C. Queue Job Inventory

Every job implementing `ShouldQueue` registered in the codebase:

| Job Class | File Path | Default Queue | Dispatched By | Trigger Mechanism | Delayed? | Max Attempts | Unique? |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `ProcessAnalyticsEventJob` | [ProcessAnalyticsEventJob.php](file:///d:/projects/MSA%20Platform/backend/app/Jobs/Analytics/ProcessAnalyticsEventJob.php) | `low` | Controllers / Middleware | Page View / Action | No | Infinite | No |
| `TrackEventJob` | [TrackEventJob.php](file:///d:/projects/MSA%20Platform/backend/app/Jobs/Analytics/TrackEventJob.php) | `default` | Analytics Controllers | Generic event tracking | No | 0 (default) | No |
| `GenerateCertificateJob` | [GenerateCertificateJob.php](file:///d:/projects/MSA%20Platform/backend/app/Jobs/Certificates/GenerateCertificateJob.php) | `default` | `CourseCompletedEvent` | Course completion | No | 5 | No |
| `GenerateCertificatePdfJob` | [GenerateCertificatePdfJob.php](file:///d:/projects/MSA%20Platform/backend/app/Jobs/GenerateCertificatePdfJob.php) | `default` | `GenerateCertificateJob` | Rendering PDF download | No | 0 (default) | No |
| `SendCourseCompletionEmailJob` | [SendCourseCompletionEmailJob.php](file:///d:/projects/MSA%20Platform/backend/app/Jobs/Email/SendCourseCompletionEmailJob.php) | `default` | `NotificationEventSubscriber` | Course complete event | No | 3 | No |
| `SendCertificateEmailJob` | [SendCertificateEmailJob.php](file:///d:/projects/MSA%20Platform/backend/app/Jobs/Email/SendCertificateEmailJob.php) | `default` | `NotificationEventSubscriber` | Certificate earned | No | 3 | No |
| `SendAnnouncementEmailJob` | [SendAnnouncementEmailJob.php](file:///d:/projects/MSA%20Platform/backend/app/Jobs/Email/SendAnnouncementEmailJob.php) | `default` | `NotificationEventSubscriber` | Announcement publish | No | 3 | No |
| `SendTrainingReminderEmailJob` | [SendTrainingReminderEmailJob.php](file:///d:/projects/MSA%20Platform/backend/app/Jobs/Email/SendTrainingReminderEmailJob.php) | `default` | `NotificationEventSubscriber` | Upcoming training event | No | 3 | No |
| `SendNewsletterAnnouncementEmailsJob` | [SendNewsletterAnnouncementEmailsJob.php](file:///d:/projects/MSA%20Platform/backend/app/Jobs/Email/SendNewsletterAnnouncementEmailsJob.php) | `default` | `NewsletterService` | Broadcaster subscriber email | No | 3 | No |
| `ProcessDueRemindersJob` | [ProcessDueRemindersJob.php](file:///d:/projects/MSA%20Platform/backend/app/Ems/Jobs/ProcessDueRemindersJob.php) | `ems-notifications` | Scheduler | Cron tick | No | 0 (default) | No |
| `ProcessDueNotificationsJob` | [ProcessDueNotificationsJob.php](file:///d:/projects/MSA%20Platform/backend/app/Ems/Jobs/ProcessDueNotificationsJob.php) | `ems-notifications` | Scheduler | Cron tick | No | 0 (default) | No |
| `SendEventNotificationJob` | [SendEventNotificationJob.php](file:///d:/projects/MSA%20Platform/backend/app/Ems/Jobs/SendEventNotificationJob.php) | `ems-notifications` | `QueuedEventNotificationDispatcher` | Event trigger / Reminder | No | 3 | No |
| `QueueRegistrationConfirmation` | [QueueRegistrationConfirmation.php](file:///d:/projects/MSA%20Platform/backend/app/Ems/Jobs/QueueRegistrationConfirmation.php) | `ems-notifications` | `CheckoutService` / `PaymentFulfillmentService` | Ticket checkout success | No | 0 (default) | No |
| `ExpireAbandonedCheckoutsJob` | [ExpireAbandonedCheckoutsJob.php](file:///d:/projects/MSA%20Platform/backend/app/Ems/Jobs/ExpireAbandonedCheckoutsJob.php) | `ems-payments` | Scheduler | Cron tick | No | 0 (default) | No |
| `ReconcileSquareSalesJob` | [ReconcileSquareSalesJob.php](file:///d:/projects/MSA%20Platform/backend/app/Ems/Jobs/ReconcileSquareSalesJob.php) | `ems-payments` | Scheduler | Cron tick | No | 0 (default) | No |
| `ProcessSquareWebhookJob` | [ProcessSquareWebhookJob.php](file:///d:/projects/MSA%20Platform/backend/app/Ems/Jobs/ProcessSquareWebhookJob.php) | `ems-payments` | `SquareWebhookService` | Square API webhook callback | No | 5 | No |
| `ReconcilePaymentJob` | [ReconcilePaymentJob.php](file:///d:/projects/MSA%20Platform/backend/app/Ems/Jobs/ReconcilePaymentJob.php) | `ems-payments` | `PaymentFulfillmentService` | Webhook stale capture resolve | No | 0 (default) | No |
| `SyncTicketTypeToSquareJob` | [SyncTicketTypeToSquareJob.php](file:///d:/projects/MSA%20Platform/backend/app/Ems/Jobs/SyncTicketTypeToSquareJob.php) | `ems-payments` | `TicketTypeService` | Admin modifies ticket details | No | 3 | **Yes (120s)** |
| `ProcessAttendeeImportJob` | [ProcessAttendeeImportJob.php](file:///d:/projects/MSA%20Platform/backend/app/Ems/Jobs/ProcessAttendeeImportJob.php) | `ems-operations` | `AttendeeImportService` | Admin uploads check-in CSV | No | 2 | No |
| `GenerateReportJob` | [GenerateReportJob.php](file:///d:/projects/MSA%20Platform/backend/app/Ems/Jobs/GenerateReportJob.php) | `ems-operations` | `AnalyticsController` | Admin requests event report | No | 2 | No |

---

## D. Queue Inventory

| Queue Channel | Assigned Jobs | General Purpose | Business Criticality | Active Workers |
| :--- | :--- | :--- | :--- | :--- |
| `ems-payments` | `ExpireAbandonedCheckoutsJob`, `ReconcileSquareSalesJob`, `ProcessSquareWebhookJob`, `ReconcilePaymentJob`, `SyncTicketTypeToSquareJob` | Handles payment status, webhooks, checkouts, and catalog updates | 🔴 Critical | Yes (combined worker) |
| `ems-notifications` | `ProcessDueRemindersJob`, `ProcessDueNotificationsJob`, `SendEventNotificationJob`, `QueueRegistrationConfirmation` | Handles attendee event communication, ticket deliveries, and reminders | 🟡 Important | Yes (combined worker) |
| `ems-operations` | `ProcessAttendeeImportJob`, `GenerateReportJob` | Processes long-running attendee imports and report generations | 🟡 Important | Yes (combined worker) |
| `high` | None | Reserved for real-time user-facing high priority actions | 🟡 Important | Yes (combined worker) |
| `default` | `GenerateCertificateJob`, `GenerateCertificatePdfJob`, `TrackEventJob` | Core platform processes, certificate issuing, standard user emails | 🟡 Important | Yes (combined worker) |
| `low` | `ProcessAnalyticsEventJob`, `DatabaseMaintenanceJob` (and all daily cleanups) | Non-critical logs cleanup, system backups, daily analytics rollups | 🟢 Non-critical | Yes (combined worker) |

---

## E. Worker Inventory

Since the cPanel environment lacks standard daemon monitoring tools, workers are launched as overlapping tasks.

| Worker Process | Command Signature | Queues List | Persistent? | Invoked By | Concurrency Safety |
| :--- | :--- | :--- | :--- | :--- | :--- |
| Combined Worker | `php artisan queue:work --queue=ems-payments,ems-notifications,ems-operations,high,default,low --sleep=1 --tries=3` | All queues (ordered by priority) | **No** (runs until empty, then exits via cron command) | cPanel Task Scheduler (every minute) | Uses Laravel's locking driver (coalescing handles on unique jobs) |

---

## F. Production Cron Inventory

Below is the active production crontab configuration for the hosting server:

```cron
# 1. Run the Laravel Scheduler every minute
* * * * * cd /home2/sfums1d5/msa-backend && php artisan schedule:run >> /dev/null 2>&1

# 2. Spawn a queue worker every minute to process pending database jobs
* * * * * cd /home2/sfums1d5/msa-backend && php artisan queue:work --stop-when-empty --queue=ems-payments,ems-notifications,ems-operations,high,default,low --sleep=1 --tries=3 >> /dev/null 2>&1
```

### Explanatory Analysis
1. `schedule:run`: Fires every minute. Boots Laravel to inspect tasks registered in `bootstrap/app.php`. If a scheduled execution window matches the current timestamp, the job is dispatched.
2. `queue:work --stop-when-empty`: Spawns a worker thread that processes all current jobs in the database queue, in order of priority (`ems-payments` first, `low` last). Once the queue becomes empty, the worker process exits cleanly to prevent running out of host execution memory.

---

## G. Webhook → Queue Map

```
[Square Webhook HTTP POST]
       │
       ▼
[SquareWebhookController]
       │
       ▼ (Verify HMAC signature)
[SquareWebhookService] (Persist WebhookEvent record)
       │
       ├───► (If default connection is 'sync') ─► processRecord() [Synchronous]
       │
       └───► (If default connection is 'database')
                  │
                  ▼
         [ProcessSquareWebhookJob]
                  │
                  ▼
         Queue [ems-payments]
                  │
                  ▼
         Queue Worker Thread
                  │
                  ▼
         processRecord() [Asynchronous]
                  │
                  ▼
         Fulfill Order / Update Payment / Ingest POS Sales
```

---

## H. EMS Lifecycle Map

```
   +------------------+
   |   Registration   | (Checkout creates order and registration in 'awaiting_payment' state)
   +--------+---------+
            |
            v
   +------------------+
   |      Payment     | (Payment captures Square transaction; triggers PaymentFulfillmentService)
   +--------+---------+
            |
            ├──────────────────────────────────────────┐
            ▼ (Success)                                ▼ (Unpaid Checkout TTL)
   +------------------+                       +------------------+
   |  Ticket Issued   |                       |    Cancelled     |
   +--------+---------+                       +--------+---------+
            |                                          |
            v (Admin Refunded)                         v (Scheduler Expired)
   +------------------+                       +------------------+
   |  Ticket Revoked  |                       | Capacity Restored|
   +------------------+                       +------------------+
```

### Expiration Lifecycle Detail
- **Trigger**: `ExpireAbandonedCheckoutsJob` runs every 15 minutes.
- **Query**: Searches `ems_registrations` where `status = awaiting_payment` and `created_at` exceeds `EMS_CHECKOUT_TTL_MINUTES` (default 24 hours).
- **Execution**: Releases ticket type quantity holds (`quantity_sold`), transitions registrations and orders to `expired` status, and marks payments as `cancelled`.

---

## I. Dependency Graph

```
[External Trigger (User Checkout / Cron / Square Webhook)]
  │
  ▼
[Controller / Service Lifecycle]
  │
  ▼
[Queue Dispatch / Event Emit]
  │
  ▼
[Database / jobs table]
  │
  ▼
[cPanel Cron tick (every minute)]
  │
  ▼
[php artisan queue:work]
  │
  ▼
[Job Execution (Database Mutex / External API Call)]
```

---

## J. Criticality Matrix

- 🔴 **Critical (Tier 1 & Tier 2)**:
  - `ProcessSquareWebhookJob` (EMS webhook payment processing)
  - `ExpireAbandonedCheckoutsJob` (Ticket capacity hold releases)
  - `SyncTicketTypeToSquareJob` (Catalog pricing updates)
  - `ReconcilePaymentJob` (Payment verification)
- 🟡 **Important (Tier 2 & Tier 3)**:
  - `QueueRegistrationConfirmation` (Sending tickets/receipts)
  - `SendEventNotificationJob` (Delivering attendee emails)
  - `ProcessAttendeeImportJob` (Bulk CSV check-in imports)
  - `GenerateReportJob` (Event statistics compiles)
  - `ProcessDueRemindersJob` / `ProcessDueNotificationsJob` (Timed event alerts)
  - `GenerateCertificateJob` / `SendCourseCompletionEmailJob` (Learner dashboard updates)
- 🟢 **Non-critical (Tier 4)**:
  - `DatabaseMaintenanceJob` / `ArchiveOldLogsJob` (System cleanups)
  - `AggregateAnalyticsMetricsJob` (Daily rollup charts)
  - `GenerateDailyReportJob` / `GenerateWeeklyReportJob` (Admin notification reports)

---

## K. Failure Analysis

| Failure Case | Technical Consequence | Business/User Impact | Mitigation/Idempotency |
| :--- | :--- | :--- | :--- |
| **Scheduler stops for 10 minutes** | Abandoned checkouts won't expire; reminders delayed | Ticket capacity held hostage, blocking new registrations | Runs automatically once scheduler comes back online |
| **Scheduler stops for 24 hours** | Reconciliations and daily maintenance fail | No metrics rolled up, ticket capacity locked | Backlog processed incrementally upon recovery |
| **Queue worker stops** | Webhooks queue up; confirmation emails and ticket issues halt | Users checkout but do not receive tickets; admins cannot run imports | Database acts as a buffer; jobs resume without loss when worker restarts |
| **Job executes twice** | Webhook processed twice, or catalog synced twice | Double ticket issuance, or duplicate email spam | Idempotency keys (`ems-rfnd-{uuid}`, `ems-sq-cat-{id}`) and DB locks block duplicates |
| **Job fails permanently** | Job moves to the `failed_jobs` table | User actions fail; logs record error details | Support reviews failed jobs in Systems Control Panel; triggers manual retry |
| **Overlapping workers run** | Multiple worker threads lock and query the same `jobs` table | Minor database connection contention | Database locking (`lockForUpdate`) ensures only one worker claims a job row |

---

## L. Current Risks in Shared Hosting Setup

1. **Worker Boot Latency**: Spawning `queue:work` via cron every minute means there is a delay of up to 60 seconds before webhooks or registrations are processed. This degrades real-time responsiveness.
2. **Resource Exhaustion**: If a heavy import job (`ProcessAttendeeImportJob`) runs on the same worker, it may consume the available CPU allowance, causing cPanel to throttle the site or terminate the process.
3. **Overlapping Race Conditions**: While unique lock safeguards exist for Catalog syncs, standard payment fulfillment or registration creations rely on database transaction levels. Under highly concurrent registration spikes, duplicate ticket assignments or state exceptions could occur if database tables are locked out-of-order.
4. **Degraded Performance Log**: Running `queue:work` boots the entire Laravel framework container from scratch. Doing this every minute on a shared host adds significant, unnecessary disc and memory overhead.

---

## M. Recommendations

1. **Separate Heavy Operations**: Isolate `ems-operations` queue worker execution. Do not let heavy report generation or CSV imports block `ems-payments` checkouts.
2. **Move to Redis for Queues (Optional)**: If hosting supports it, switch `QUEUE_CONNECTION` from `database` to `redis` to avoid constant database table read-write polling, improving latency.
3. **Migrate to VPS (Recommended)**: As traffic grows, migrate from cPanel shared hosting to a virtual private server (VPS). This will allow running persistent, dedicated daemon workers managed by **Supervisor**:
   - `queue:work --queue=ems-payments --tries=3` (Persistent execution)
   - `queue:work --queue=ems-notifications,default --tries=3`
   - `queue:work --queue=ems-operations,low --tries=1` (Low priority)
4. **Transition to systemd/Supervisor**: Persistent workers eliminate the 60-second polling lag, processing webhooks instantly.

---

## N. Proposed Target Architecture

```
                       [ Incoming Square Webhook / User Checkout ]
                                           │
                                           ▼
                                 [ Laravel Backend Controller ]
                                           │
                                           ▼
                                [ Database Queue (jobs) ]
                                           │
                    ┌──────────────────────┼──────────────────────┐
                    │                      │                      │
                    ▼                      ▼                      ▼
         [ Worker 1 (Supervisor) ] [ Worker 2 (Supervisor) ] [ Worker 3 (Cron) ]
            Queue: ems-payments       Queue: ems-notifications   Queue: ems-operations, low
            (Persistent Daemon)       (Persistent Daemon)        (Stop-when-empty cron)
```
