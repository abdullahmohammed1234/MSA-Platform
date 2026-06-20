# SFU MSA Platform — Analytics Platform Documentation

This document describes the design, implementation, tracking strategy, database schema, APIs, and future extension specifications for the centralized Analytics Platform.

---

## 1. System Architecture

The Analytics Engine is structured into decoupled components:

```text
Analytics Engine
│
├── Event Tracking (Service -> Queue Job -> raw events)
├── Aggregation Services (Hourly/Daily cron -> aggregated metrics)
├── Metrics Engine (Cached fast queries)
├── Reporting APIs (Versioned JSON endpoints)
├── Dashboard UI (Vue 3 SVG charts & widgets)
├── Scheduled Reports (Laravel Scheduler -> PDF -> Email alerts)
└── Data Export (CSV stream + DomPDF compiles)
```

1. **Raw Tracking**: Page views, clicks, and conversions are tracked via `/api/v1/analytics/track` and saved asynchronously through `TrackEventJob` to avoid performance bottlenecks.
2. **Aggregated Caching**: To support instant dashboard loads and avoid querying millions of raw logs, the `AggregateMetricsJob` calculates daily aggregates (e.g. daily visitors, course completion rates) and caches them in `analytics_metrics`.
3. **Scheduled Dispatch**: Reports are generated automatically by cron schedules, saved as `analytics_reports`, converted to PDF, and emailed directly to administrative users.

---

## 2. Database Design

```sql
-- Track user sessions
CREATE TABLE analytics_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    duration INT UNSIGNED NULL, -- seconds
    device VARCHAR(255) NULL,
    browser VARCHAR(255) NULL,
    platform VARCHAR(255) NULL,
    referrer VARCHAR(255) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Track raw action events
CREATE TABLE analytics_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    session_id BIGINT UNSIGNED NULL,
    module VARCHAR(255) NOT NULL, -- website, academy, events, certificates
    event_type VARCHAR(255) NOT NULL, -- page_view, click, submission, award
    event_name VARCHAR(255) NOT NULL, -- course_completed, cta_click
    entity_type VARCHAR(255) NULL,
    entity_id BIGINT UNSIGNED NULL,
    metadata JSON NULL,
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (session_id) REFERENCES analytics_sessions(id) ON DELETE CASCADE
);

-- Pre-aggregated metrics cache
CREATE TABLE analytics_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    metric_key VARCHAR(255) NOT NULL,
    metric_value DOUBLE NOT NULL,
    period VARCHAR(255) NOT NULL, -- daily, weekly, monthly, overall
    recorded_at TIMESTAMP NOT NULL,
    UNIQUE(metric_key, period, recorded_at)
);

-- Reports generated
CREATE TABLE analytics_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(255) NOT NULL, -- daily, weekly, monthly, custom
    filters JSON NULL,
    generated_by BIGINT UNSIGNED NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_path VARCHAR(255) NULL,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
);
```

---

## 3. Event Tracking Strategy

All analytics must flow through `App\Services\Analytics\AnalyticsService`.

### Backend Usage
Inject `AnalyticsService` and trigger events directly:
```php
use App\Services\Analytics\AnalyticsService;

class QuizController extends Controller {
    public function submit(Request $request, Quiz $quiz, AnalyticsService $analytics) {
        // ... grading logic
        $analytics->trackQuizSubmission(
            $request->user()->id, 
            $quiz->id, 
            $score, 
            $passed, 
            $request->header('X-Session-ID')
        );
    }
}
```

### Frontend Client Usage
For guest traffic and single-page apps, trigger posts to Versioned routes:
```typescript
import client from '@/services/api/client';

// 1. Sync session on page mount
const syncSession = async () => {
  let sessionUuid = localStorage.getItem('analytics_session_uuid');
  if (!sessionUuid) {
    sessionUuid = crypto.randomUUID();
    localStorage.setItem('analytics_session_uuid', sessionUuid);
  }
  
  await client.post('/analytics/session', {
    session_uuid: sessionUuid,
    browser: navigator.userAgent,
    referrer: document.referrer
  });
};

// 2. Track page view
const trackPageView = async (url: string, title: string) => {
  await client.post('/analytics/track', {
    session_uuid: localStorage.getItem('analytics_session_uuid'),
    module: 'website',
    event_type: 'page_view',
    event_name: 'page_view',
    metadata: { url, title }
  });
};
```

---

## 4. API Endpoints

All admin endpoints reside behind versioned routes and require the `view_analytics` permission.

| Method | Endpoint | Description | Query Parameters |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/v1/analytics/session` | Syncs session metadata | None |
| `POST` | `/api/v1/analytics/track` | Submits tracking events | None |
| `GET` | `/api/v1/analytics/overview` | Core Dashboard Metrics | `start_date`, `end_date` |
| `GET` | `/api/v1/analytics/website` | Page views, referrers, CTAs | `start_date`, `end_date` |
| `GET` | `/api/v1/analytics/academy` | Course progress, quiz averages | `start_date`, `end_date` |
| `GET` | `/api/v1/analytics/events` | Event registrations, conversions | `start_date`, `end_date` |
| `GET` | `/api/v1/analytics/reports` | Paginated report history | None |
| `GET` | `/api/v1/analytics/export` | Triggers CSV/PDF downloads | `format`, `type`, `start_date` |

---

## 5. Integrating Future Modules

The engine is modular and supports third-party additions. When creating future modules like **MSA Connect**, **Reimbursements**, or **Books Library**, follow this flow:

### 1. Define Module Event Names
Add custom tracking keys (e.g. `'connect_message_sent'`, `'reimbursement_submitted'`, `'book_borrowed'`).

### 2. Log Events using Analytics Service
Submit custom metadata using the `track` method:
```php
$analytics->track([
    'user_id' => $userId,
    'module' => 'connect',
    'event_type' => 'click',
    'event_name' => 'connect_channel_created',
    'metadata' => [
        'channel_type' => 'study_group',
    ]
]);
```

### 3. Add Metric Aggregations
Extend `AggregateMetricsJob` to compute sums or active statistics daily:
```php
// Inside AggregateMetricsJob
$reimbursementsTotal = AnalyticsEvent::where('event_name', 'reimbursement_submitted')
    ->whereBetween('occurred_at', [$startOfDay, $endOfDay])
    ->count();
$this->saveMetric('reimbursements_submitted_daily', $reimbursementsTotal, 'daily', $startOfDay);
```

### 4. Render on Dashboard
Create a new tab or section in `/admin/analytics` and feed it using a corresponding sub-route under `/api/v1/analytics/connect` or similar!
