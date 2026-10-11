# Phase 33 — Community Engagement & Notifications: Interactive QA & Demonstration Guide

**Project:** SFU MSA Platform  
**Phase:** 33 — Community Engagement & Notifications  
**Target Audience:** Developers, QA Testers, and Demonstration Auditors  

---

## 1. Overview & System Capabilities

Phase 33 introduces a unified **Notification Center**, **Notification Preferences**, and automated **in-app notifications** for EMS (Event Management System), VMS (Volunteer Management System), and Store merchandise orders.

### Key Capabilities Installed
1. **Top-Level Notification Center (`/notifications`):** Accessible to all authenticated platform users without role or student restrictions. Supports category filtering, search, tabbed views (All / Unread / Read), single mark-as-read, mark-all-as-read, and deletion.
2. **Account Notification Preferences (`/account/notifications`):** Integrated into the Account Portal. Allows toggling global delivery channels (`email_enabled`, `in_app_enabled`) and category subscriptions (`new_announcements`, `upcoming_training`, `course_completion`, `certificate_earned`).
3. **Subsystem Notification Triggers:**
   - **EMS:** Event registration confirmation & ticket issuance notifications.
   - **VMS:** Volunteer shift assignments, waitlist promotions, shift updates, and status transitions.
   - **Store:** Order fulfillment (`fulfilled`, `completed`) status notifications.
4. **Safe Navigation (`notificationResolver.ts`):** Validates all notification action targets against approved internal route patterns (`/events/:slug`, `/volunteer/:slug`, `/store/my-orders`, `/announcements/:slug`, `/account/activity`, fallback `/account`).
5. **Security & Privacy Isolation:** Enforces per-user IDOR protection. Guest checkouts, signups, and registrations without an explicit `user_id` are strictly excluded (no email-matching heuristic).

---

## 2. Local Environment Setup

### Prerequisites
- PHP 8.2+ with Laravel 12
- Node.js 18+ and npm
- Local SQLite or MySQL database

### Step 1: Start Backend Server
From the `backend/` directory:
```bash
cd backend
php artisan serve --port=8000
```
*Backend API will run at `http://localhost:8000`.*

### Step 2: Start Frontend Vite Server
From the `frontend/` directory:
```bash
cd frontend
npm run dev
```
*Frontend application will run at `http://localhost:5173`.*

---

## 3. Generating Interactive Demo Test Data

To seed the local database with realistic, isolated test notifications and dedicated test accounts, run the built-in Artisan command:

```bash
cd backend
php artisan msa:phase33-demo
```

### Generated Test Accounts
| Account Role | Email Address | Password | Initial State |
|---|---|---|---|
| **Primary Test Member (User A)** | `msa.phase33.member1@example.test` | `password123` | 5 Unread, 1 Read, 1 Unsafe Destination Test |
| **Secondary Test Member (User B)** | `msa.phase33.member2@example.test` | `password123` | 1 Unread Notification (Ownership Isolation Test) |

---

## 4. Step-by-Step UI Walkthrough & Verification Scenarios

### Scenario A: Bell Dropdown & Notification Center Navigation
1. Open `http://localhost:5173/login` and log in as `msa.phase33.member1@example.test` / `password123`.
2. **Notification Bell:** Observe the red unread badge indicator on the top navbar bell icon (`PublicNavbar.vue`).
3. **Dropdown Menu:** Click the bell icon to reveal the quick notification dropdown. Verify unread items are highlighted with gold indicator badges.
4. **View All Link:** Click **"View All Notifications"** at the bottom of the dropdown. Verify browser navigates to top-level `/notifications`.

### Scenario B: Notification Center Filtering & Operations
1. Open `http://localhost:5173/notifications`.
2. **Category Filtering:** Select **"Events & Tickets"** from the category dropdown. Verify list filters down to the EMS Gala notification (`Registration Confirmed: Annual MSA Gala 2026`).
3. **Tab Filtering:** Click the **"Unread"** tab. Verify only unread notifications appear. Click **"Read"** tab — verify `New Announcement: Fall Semester Orientation` appears.
4. **Search:** Type `"Friday"` into the search box. Verify the VMS shift notification (`Volunteer Shift Assigned: Friday Prayer Setup`) matches.
5. **Mark as Read:** Click the checkmark icon on an unread notification. Verify unread badge count decrements optimistically.
6. **Mark All as Read:** Click **"Mark All as Read"** button in top right. Verify all items transition to read state.
7. **Delete Notification:** Click the trash icon on a notification. Verify item is removed cleanly with success toast.

### Scenario C: Safe Destination Resolution & Malicious Link Protection
1. In `/notifications`, locate the **"Platform Security Alert"** notification (generated with external URL payload `https://malicious-external-phish.com/hack`).
2. Click the notification row to trigger destination navigation.
3. **Expected Behavior:** `notificationResolver.ts` detects the untrusted external domain, blocks external redirect, and safely navigates to `/account`.

### Scenario D: Account Notification Preferences Sync
1. Open `http://localhost:5173/account/notifications` (or click **"Notifications"** tab in Account Portal).
2. **Toggle Channels:** Toggle off **"Email Notifications"** or **"In-App Notifications"**.
3. **Toggle Categories:** Toggle off **"Upcoming Events & Training"**.
4. **Page Reload:** Refresh the browser window (`F5`).
5. **Expected Behavior:** The updated preference toggles load from `GET /api/v1/notifications/preferences` and maintain their persisted state.

### Scenario E: Ownership Isolation Verification (IDOR Check)
1. In a private/incognito window, log in as **User B** (`msa.phase33.member2@example.test` / `password123`).
2. Open `http://localhost:5173/notifications`.
3. **Expected Behavior:** User B sees **ONLY** their private notification (`Private Order Update for Fatima`). None of User A's notifications (`msa.phase33.member1@example.test`) are visible or accessible.

---

## 5. Automated Regression Test Commands

Run the full automated verification matrix locally using standard test runners:

### Backend Feature Tests
```bash
cd backend

# Run Notification System Test Suite
php artisan test --filter=NotificationSystemTest

# Run VMS Notification & Feature Suite
php artisan test --filter=Vms

# Run Account & Dashboard Scoping Suites
php artisan test --filter=Account
php artisan test --filter=Dashboard
```

### Frontend Unit & Component Tests
```bash
cd frontend

# Run Vitest suite
npx vitest run

# Run TypeScript typechecking and Vite production build
npm run build
```

---

## 6. Cleaning Up Demo Data

To safely remove all test notifications and dedicated test accounts generated by the demo workflow without affecting real users or other platform data, execute:

```bash
cd backend
php artisan msa:phase33-demo --cleanup
```

### Cleanup Summary
- Deletes all `Notification` records where `data->demo_tag = 'phase33_demo'`.
- Deletes demo accounts `msa.phase33.member1@example.test` and `msa.phase33.member2@example.test`.

---

## 7. Troubleshooting & FAQ

| Problem | Cause | Solution |
|---|---|---|
| **Notification count is 0 after seeding** | User token is associated with a different account. | Ensure you logged in as `msa.phase33.member1@example.test` with password `password123`. |
| **`GET /api/v1/notifications` returns 401** | Sanctum authentication token is expired or missing. | Log out and log back in via `/login`. |
| **Preferences toggle does not save** | Backend API connection issue or CSRF mismatch. | Verify backend server is running on `http://localhost:8000`. |
| **Test runner fails with SQLite error** | Memory database configuration mismatch. | Ensure `.env.testing` uses `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`. |
