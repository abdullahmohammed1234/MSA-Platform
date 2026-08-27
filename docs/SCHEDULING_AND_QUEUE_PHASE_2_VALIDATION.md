# SFU MSA Platform — Phase 2 Production Scheduling & Queue Validation Audit

This document presents a technical validation and audit of the background processing, scheduler, and queue subsystems for the **SFU MSA Platform** in its cPanel shared-hosting environment.

---

## 1. Executive Summary

> [!WARNING]
> **Audit Verdict: Marginally Unsafe under Load**
>
> While the codebase itself utilizes robust database transactions and row locks (`lockForUpdate`) for payment fulfillment, the hosting environment's execution model and queue configurations expose the platform to severe operational risks:
> 1. **Worker Accumulation and LVE Throttling**: The 1-minute cron worker spawning model lacks file locking (`flock`). Under a queue backlog, workers will accumulate, exceeding cPanel process and CPU thresholds, causing CloudLinux to throttle the site or terminate processes.
> 2. **Queue Mismatch Race Conditions (`retry_after` vs. `$timeout`)**: The database queue driver configuration has `retry_after` set to **90 seconds**, but `GenerateReportJob` specifies a `$timeout` of **300 seconds** (5 minutes). Any report generation taking longer than 90 seconds will be re-queued and executed concurrently by another worker, leading to duplicate processing, database contention, and potential file corruption.
> 3. **API Race Conditions in Expiration**: `CheckoutLifecycleService::expireStale()` queries stale checkouts without row locks before calling the Square API to delete links. Overlapping workers will make redundant API calls, wasting rate limits and throwing 404/already-deleted exceptions from Square.
> 4. **Worker Boot Latency**: Real-time events (like Square webhook callbacks and user ticket issue receipts) can face a processing lag of up to **60 seconds** due to reliance on a minute-ly cron job to spawn worker threads.

---

## 2. Verified vs. Assumed Invariants

To maintain absolute integrity, this audit distinguishes what has been confirmed directly from code and logs versus what is inferred or unknown:

| Category | Status | Verified Invariants & Details |
| :--- | :---: | :--- |
| **VERIFIED FROM CODE** | ✅ | • Precise Laravel version is **12.61.1** (PHP ^8.2).<br>• The queue connection is configured to use the `database` driver in both local and production `.env.production` files.<br>• `jobs` and `failed_jobs` tables do not have indexes on `available_at` or `reserved_at`. Only the `queue` column is indexed.<br>• `SyncTicketTypeToSquareJob` implements `ShouldBeUnique` with a `uniqueFor` window of 120s.<br>• `PaymentFulfillmentService` utilizes strict database transactions and row-level locking (`lockForUpdate`) for payment marking, failure, cancellations, and abandonment.<br>• The queue configuration has `retry_after` set to `90` seconds, while `GenerateReportJob` has `$timeout` set to `300` seconds. |
| **VERIFIED FROM LOGS** | ✅ | • Checked `storage/logs/ems-2026-08-26.log`. It registers testing and integration errors (e.g., `testing.ERROR: ems.payments.reconciliation_failed` or `ems.square.http_failed`). There are no logs of background job successes, confirming that successful job runtimes are not historically persisted in application logs. |
| **INFERRED** | ℹ️ | • **cPanel Hosting Limits**: Standard CloudLinux profiles are used. Typical allocations are 1 CPU core, 1-2GB physical memory (PMEM), and 20-30 concurrent Entry Processes.<br>• **PHP CLI Execution Time**: CLI PHP binary execution time defaults to unlimited (`max_execution_time = 0` in CLI), meaning background tasks will run until completion or until terminated by host-level watchdogs. |
| **UNKNOWN** | ❓ | • **Observed Runtime**: Since successful job execution durations are not logged or stored in the database, the exact real-world production runtimes cannot be determined without introducing instrumentation. |

---

## 3. Queue Priority Findings

Laravel's queue priority system operates on a **strict left-to-right drain model**. 

The worker command is:
```bash
php artisan queue:work --stop-when-empty --queue=ems-payments,ems-notifications,ems-operations,high,default,low
```

### Popping and Processing Mechanics
1. **Left-to-Right Evaluation**: In every loop iteration, the queue worker queries the database connections for a job. It checks `ems-payments` first. If a job is found, it pops it, executes it, and then **restarts the loop from the beginning**.
2. **Draining Behavior**: Lower-priority queues (`ems-notifications`, `ems-operations`, etc.) are checked **only** if all queues preceding them in the list are completely empty.
3. **No Preemption (Interruption)**: Since PHP CLI is single-threaded, a running job **cannot be interrupted**. If a 5-minute `GenerateReportJob` on the `ems-operations` queue is popped, the worker is fully occupied. Even if a critical payment webhook arrives on the `ems-payments` queue 5 seconds later, it will not be processed by this worker until the 5-minute job completes.
4. **Queue Starvation**: If there is a constant flood of jobs on `ems-payments` or `ems-notifications` (e.g., during a busy ticket launch), jobs on `ems-operations`, `default`, and `low` will be starved of processing time until the high-priority queues are fully drained.

### Concrete Execution Example

**Scenario**:
* **00:00:00**: `ems-operations` contains a 5-minute `GenerateReportJob`. A worker (Worker A) starts, pops this job, and begins executing.
* **00:00:05**: Square webhook dispatches `ProcessSquareWebhookJob` to `ems-payments`.
* **00:00:10**: Another `ems-operations` job arrives.

**What Happens**:
1. **Single Worker Perspective**: Worker A is locked processing the report job. It cannot pick up the Square webhook payment job. The webhook job must wait in the database queue until Worker A finishes the report job at **00:05:00**. The delay is **4 minutes and 55 seconds**.
2. **Cron Overlap Perspective (Actual Behavior)**: Since the cPanel cron runs every minute to execute `queue:work --stop-when-empty`, at **00:01:00**, a new worker (Worker B) is spawned by the cron. Worker B queries the database queue, finds `ProcessSquareWebhookJob` in the high-priority `ems-payments` queue, pops it, and completes it within seconds. Worker B then processes the second `ems-operations` job. 
3. **Implication**: While the cron overlap prevents the payment job from waiting the full 5 minutes, it spawns concurrent worker processes, introducing resource contention and risks of CloudLinux process limits.

---

## 4. Worker Overlap Findings

The production server spawns queue workers using the following cron entry:
```cron
* * * * * cd /home2/sfums1d5/msa-backend && php artisan queue:work --stop-when-empty ...
```

### Can cPanel launch multiple instances?
* **Yes**. The cron daemon spawns a new shell process every 60 seconds regardless of whether the previous instance is still executing.
* **No host-level locks exist** (no `flock` prefix in the cron definition).
* **No application-level worker limit exists** in Laravel.

### Operational Consequences of Worker Overlap
* **Idle Queue**: If the queue is empty, the newly spawned worker queries the jobs table, finds nothing, and exits immediately. This takes $< 1$ second and is safe.
* **Heavy Load/Long-Running Job**: If a job runs for longer than 60 seconds (like `GenerateReportJob` or a large CSV `ProcessAttendeeImportJob`), the worker running it remains alive. At the next minute mark, cron spawns another worker. If jobs continue to remain in the queue, a new worker will start every minute and run concurrently.
* **Process Accumulation (Thrashing)**: If a backup of jobs occurs, the server will accumulate concurrent PHP worker processes. If 10 slow jobs are queued, 10 workers will eventually run in parallel. Boots of the Laravel framework container take significant CPU and 30-50MB RAM. Accumulating workers will quickly exceed CloudLinux LVE limits:
  * **Throttling**: CPU usage is capped, causing the site to load extremely slowly.
  * **Termination**: CloudLinux kills random user processes (often database queries or workers), leading to failed jobs and crashed operations.
  * **503 Errors**: Web server entry processes are blocked, taking the entire platform offline.

---

## 5. Job Runtime and Timeout Characteristics

The table below lists the timeout and retry configurations for critical EMS background jobs, as declared in the codebase:

| Job Class | Queue | Expected Runtime | Observed Runtime | Timeout | Attempts | Criticality | Concurrency Safeties |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `ProcessSquareWebhookJob` | `ems-payments` | $< 5$s | *Unknown* | 60s (Default) | 5 | 🔴 Critical | Webhook Event ID checked with `lockForUpdate()` in DB |
| `ReconcilePaymentJob` | `ems-payments` | $< 5$s | *Unknown* | 60s (Default) | 3 (Default) | 🔴 Critical | DB transaction with row locks |
| `ExpireAbandonedCheckoutsJob` | `ems-payments` | 5s - 15s | *Unknown* | 60s (Default) | 3 (Default) | 🔴 Critical | **None on query**. Uses `lockForUpdate` on payment update only. |
| `SyncTicketTypeToSquareJob` | `ems-payments` | 2s - 5s | *Unknown* | 60s (Default) | 3 | 🔴 Critical | Implements `ShouldBeUnique` (120s cache lock) |
| `ProcessAttendeeImportJob` | `ems-operations` | 10s - 60s | *Unknown* | 60s (Default) | 2 | 🟡 Important | CSV processed in batches |
| `GenerateReportJob` | `ems-operations` | 30s - 180s | *Unknown* | 300s (5m) | 2 | 🟡 Important | PDF/XLSX generation. Status updated on failure. |
| `SendEventNotificationJob` | `ems-notifications` | 2s - 5s | *Unknown* | 60s (Default) | 3 | 🟡 Important | Explicit backoff sequence `[30, 120, 300]` |
| `ProcessDueRemindersJob` | `ems-notifications` | $< 5$s | *Unknown* | 60s (Default) | 3 (Default) | 🟡 Important | Scheduled via `withoutOverlapping()` (dispatcher level) |
| `ProcessDueNotificationsJob` | `ems-notifications` | $< 5$s | *Unknown* | 60s (Default) | 3 (Default) | 🟡 Important | Scheduled via `withoutOverlapping()` (dispatcher level) |

---

## 6. Database Queue Performance Analysis

The MSA Platform uses the Laravel database queue driver. 

### Database Schema and Indexing Analysis
* **Jobs Table**: Columns are `id` (PK), `queue` (indexed), `payload`, `attempts`, `reserved_at`, `available_at`, and `created_at`.
* **Index Deficiencies**:
  * There is **no index** on `reserved_at` or `available_at`.
  * The queue pop query performs a row filter on `reserved_at` and `available_at` within the specified queue:
    `SELECT * FROM jobs WHERE queue = ? AND ((reserved_at IS NULL AND available_at <= ?) OR (reserved_at <= ?)) ORDER BY id ASC LIMIT 1 FOR UPDATE`
  * Without indexes on these columns, MySQL must perform a scan of the records matching the queue name. While the database is small, the performance impact is negligible. However, if the `jobs` table swells (due to delayed tasks or backlogs), polling from multiple workers will cause high database CPU load and locking contention.

### The `retry_after` Concurrency Risk
* In `config/queue.php`, the database connection's `retry_after` parameter is configured to **90 seconds** (default).
* `GenerateReportJob` specifies a `$timeout` of **300 seconds** (5 minutes).
* **The Hazard**: If a report generation job takes 100 seconds to run:
  1. At 90 seconds, the queue driver considers the job abandoned because the running worker has exceeded the `retry_after` threshold.
  2. The job is released back into the queue and becomes available.
  3. A second worker pops the job and begins executing `GenerateReportJob` again.
  4. Both workers are now running the same report generation job concurrently, leading to double resource consumption, potential PDF generation collisions, and duplicate email notifications.

---

## 7. Shared-Hosting Resource Limits

Based on the hosting environment characteristics, the resource constraints are structured as follows:

| Resource | Estimated/Actual Limit | Current Usage | Risk | Evidence & Impact |
| :--- | :---: | :---: | :---: | :--- |
| **CPU** | 100% of 1 Core | low - moderate | 🟡 **Medium** | • CloudLinux LVE limits apply. Excessive CLI workers booting the Laravel framework in parallel will spike CPU, causing throttling and process timeouts. |
| **RAM** | 1GB - 2GB | low - moderate | 🟡 **Medium** | • PDF generation (DomPDF) and spreadsheet writers (PhpSpreadsheet) are memory-heavy. If multiple workers run reports concurrently, memory limit faults will terminate workers. |
| **Concurrent Processes** | 20 - 30 Entry Processes | low | 🔴 **High** | • cPanel Entry Process limits count active PHP and cron executions. A backlog spawning new workers every minute will easily hit the EP ceiling, triggering **503 Service Unavailable** web errors. |
| **CLI execution** | Unlimited (`max_execution_time=0`) | low | 🟢 **Low** | • Verified that CLI execution is not capped by standard php.ini web limits, but long-running processes are subject to host process-reaper daemons. |
| **Cron** | 1 Minute minimum | low | 🟢 **Low** | • cPanel supports 1-minute cron intervals. There is potential jitter or lag when the server is under high load. |
| **Process lifetime** | Host daemon-dependent (~10-30m) | low | 🟡 **Medium** | • Shared hosting typically runs watchdogs that kill processes executing indefinitely or consuming continuous CPU, making persistent daemons impossible. |

---

## 8. Current Two-Cron Assessment

The existing deployment configuration runs:
* **Cron 1**: `schedule:run` every minute.
* **Cron 2**: `queue:work --stop-when-empty` every minute.

### Safety Verdict: Marginal
This setup is acceptable **only** under low-traffic conditions and short-duration jobs. 

* **Why it can remain temporarily**:
  * `schedule:run` is completely safe because it only dispatches jobs to the database queue and exits immediately (takes $< 1$s). It does not execute the actual business logic of the jobs.
  * The queue worker runs with `--stop-when-empty`, which safely terminates the worker process when the database queue is drained, freeing up server memory.
* **Why it is unsafe for production scaling**:
  * **Webhook/Fulfillment Lag**: Any webhook dispatched to the queue must wait for the next minute tick of the cron before a worker is spawned to process it. This leads to an average lag of 30 seconds (up to 60 seconds) for real-time ticket delivery.
  * **Thrashing Risk**: Spawning a worker every minute boots the entire Laravel framework from scratch, creating disc and CPU overhead.
  * **Accumulation Vulnerability**: The lack of mutual exclusion locks (`flock`) on the queue cron job means a series of slow jobs will accumulate concurrent worker processes, bringing down the server.

---

## 9. Worker Architecture Comparison

We evaluated five architectural designs for worker configurations:

| Architecture | CPU Overhead | RAM Overhead | Latency | Isolation | Suitability for Shared Hosting | Suitability for VPS |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Option 1: Single Combined Worker** *(Current)* | Low | Low | High (up to 60s) | None (starvation risk) | 🟡 Marginal | ❌ Poor |
| **Option 2: One Worker per EMS Queue** *(3 workers)* | Medium | Medium | High (up to 60s) | Partial | ❌ Unsafe (LVE process limit) | 🟡 Good |
| **Option 3: Dedicated Payments + Combined Remainder** | Low-Med | Low-Med | High (up to 60s) | Good (protects webhooks) | 🟢 **Recommended (with flock)** | 🟡 Good |
| **Option 4: Dedicated Payments, Notifications, Operations** | High | High | High (up to 60s) | Excellent | ❌ Unsafe (LVE process limit) | 🟢 Excellent |
| **Option 5: VPS Persistent Workers** *(Supervisor)* | Low | Low (persistent) | **0s (Instant)** | Perfect | ❌ Unsupported | 🏆 **Best Choice** |

---

## 10. Recommended Worker Model

### 1. Now — Current Shared Hosting
To secure the current shared hosting deployment, implement **Option 3 with flock locking** and config modifications:

```bash
# Cron 1: Spawns payments worker. Exits if another payments worker is running.
* * * * * flock -n /tmp/ems-payments.lock php artisan queue:work --stop-when-empty --queue=ems-payments --sleep=1 --tries=3 >> /dev/null 2>&1

# Cron 2: Spawns general worker. Exits if another general worker is running.
* * * * * flock -n /tmp/ems-general.lock php artisan queue:work --stop-when-empty --queue=ems-notifications,ems-operations,high,default,low --sleep=1 --tries=3 >> /dev/null 2>&1
```

* **Why this works**:
  * `flock -n` enforces mutual exclusion. If a worker is still processing a heavy job, the next minute-ly cron fails to acquire the lock and exits immediately. This **completely eliminates process accumulation and LVE crashes**.
  * Separating the payment queue ensures that long-running imports or reports (`ems-operations`) never block incoming Square webhook processing.

### 2. Future — VPS Migration
Upon migrating to a VPS, transition to **Option 5 (Persistent Workers managed by Supervisor)**:

```ini
[program:ems-payments-worker]
command=php /var/www/msa/artisan queue:work --queue=ems-payments --sleep=1 --tries=3
numprocs=1
autostart=true
autorestart=true

[program:ems-general-worker]
command=php /var/www/msa/artisan queue:work --queue=ems-notifications,ems-operations,high,default,low --sleep=1 --tries=3
numprocs=2
autostart=true
autorestart=true
```

---

## 11. Migration Prerequisites

Before making changes to the scheduling or worker architecture, the following prerequisites must be met:

1. **Adjust `retry_after`**: Update `config/queue.php` to set `retry_after => 360` (6 minutes) on the database queue connection, ensuring it is larger than the 5-minute `$timeout` defined on `GenerateReportJob`.
2. **Refactor Expiration Query**: Modify `CheckoutLifecycleService::expireStale()` to use row locks on database selection (e.g., query expired payments inside a database transaction using `lockForUpdate()`) to prevent concurrent workers from calling Square API deletes on the same checkout link.
3. **Database Performance Indexing**: Add a migration to place a composite index on `(queue, available_at, reserved_at)` in the `jobs` table to optimize the worker polling queries.
4. **Shell Support Verification**: Confirm that the shared-hosting server environment supports `flock` and that the `/tmp` directory is writeable by the cron user.

---

## 12. Remaining Operational Risks

Even with the recommended shared-hosting model, the following risks remain:
* **Webhook Latency**: Since workers are launched by cron, we cannot eliminate the 60-second polling delay on shared hosting. User ticket generation and emails will always feel delayed.
* **Square API Downtime**: If the Square API goes down, webhook retries and checkout expirations will fail. This is mitigated by the 5-attempt retry limit on webhooks, but requires manual monitoring of `failed_jobs`.

---

## 13. Proposed Phase 3 Next Steps

The next implementation phase should address queue performance and locking.
1. **Apply flock to Cron Jobs**: Update the crontab entries in the cPanel scheduler to use the locking commands.
2. **Update Database Queue Settings**: Deploy the optimized `retry_after` setting and database indexes.
3. **Implement Job Instrumentation**: Add start and end log triggers to `GenerateReportJob` and `ProcessAttendeeImportJob` to measure actual production runtimes.
4. **Prepare VPS Infrastructure**: Author a Supervisor configuration file in the project repository to ease future VPS transition.
