# SFU MSA Platform — Complete Guide

This document explains how the **MSA Platform** works: one Vue.js application for the public website, **Dawah Academy** (LMS), admin panel, and CMS — all backed by **Laravel**.

---

## Architecture overview

```
┌─────────────────────────────────────────────────────────────────┐
│  MSA Platform (Vue 3 + Vite)          http://localhost:5173      │
│  • Public website (/, /about, /events, …)                        │
│  • Login / Register (Laravel Sanctum)                            │
│  • Dawah Academy LMS (/academy/*)  ← Vue academy in this repo    │
│  • Admin panel (/admin)                                          │
│  • CMS (/admin/cms/*)                                            │
└───────────────────────────────┬─────────────────────────────────┘
                                │ REST API
                                ▼
┌───────────────────────────┐
│  Laravel API :8000         │
│  /api/v1/*                 │
│  Auth, CMS, Academy, Admin │
└───────────────────────────┘
```

**There is no separate Dawah app in production flow.** The LMS lives at `/academy/*` inside MSA Platform and uses the same login session as the main website.

**User journey:**

1. User visits the main website and logs in at `/login`.
2. After login they stay on the **main site** (`/` home). Admins go to `/admin`.
3. They click **Dawah** in the navbar → `/academy/dashboard` (same Vue app, same auth token).
4. All academy features (courses, modules, quizzes, practice lab, discussions) use Laravel API endpoints.

> **Note:** The `Dawah/` folder in the monorepo is a legacy Next.js/React app and is **not** used by MSA Platform.

---

## Quick start (local development)

### 1. Laravel backend

```powershell
Set-Location "D:\projects\msa + dawah\MSA Platform\backend"
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate --seed
php artisan serve    # http://localhost:8000
```

**Default seeded accounts** (password: `password`):

| Email | Role |
|-------|------|
| superadmin@example.com | Super Admin |
| admin@example.com | Admin |
| coordinator@example.com | Dawah Coordinator |
| mentor@example.com | Mentor |
| volunteer@example.com | Volunteer |

### 2. MSA Platform frontend (website + academy)

```powershell
Set-Location "D:\projects\msa + dawah\MSA Platform\frontend"
npm install
npm run dev    # http://localhost:5173
```

Set in `frontend/.env`:

```env
VITE_API_URL=http://localhost:8000/api/v1
```

That is the only required frontend env for local dev.

---

## Authentication flow

| Step | Behavior |
|------|----------|
| Login | `POST /api/v1/auth/login` → Sanctum token stored in `localStorage` |
| After login (volunteer) | Redirect to `/` (main website) |
| After login (admin) | Redirect to `/admin` |
| `?redirect=/academy/...` | Honored after login (e.g. unauthenticated user tried to open academy) |
| Dawah button | Navigates to `/academy` (requires auth; guard sends to login if needed) |
| Logout | Clears token; returns to public site |

Academy routes use `requiresAuth: true` in `router/academy.ts`. The shared `authGuard` redirects unauthenticated users to `/login?redirect=...`.

---

## Accessing Dawah Academy (Vue LMS)

| URL | Purpose |
|-----|---------|
| http://localhost:5173/academy | Academy shell (redirects to dashboard) |
| `/academy/dashboard` | Student dashboard, streak, continue learning |
| `/academy/courses` | Course catalog |
| `/academy/modules` | Module list |
| `/academy/quizzes` | Quiz hub |
| `/academy/scenarios` | Practice Lab (branching scenarios) |
| `/academy/discussions` | Peer discussions |
| `/academy/mentor/*` | Mentor Studio (mentor/admin roles) |

**API base:** `http://localhost:8000/api/v1/academy/*` and related endpoints (`/simulations`, `/discussions`, `/mentor`, etc.).

---

## Accessing Admin

| URL | Who | Purpose |
|-----|-----|---------|
| http://localhost:5173/login | Everyone | Login |
| http://localhost:5173/admin | Admin, Super Admin | Platform admin dashboard |
| `/admin/users` | `manage_users` | User management |
| `/admin/roles` | `manage_roles` | Roles & permissions |
| `/admin/academy/*` | Coordinator, Admin | Courses, quizzes, mentors, students |
| `/admin/security` | `view_security` | Security dashboard |
| `/admin/analytics` | `view_analytics` | Analytics |

**API:** `http://localhost:8000/api/v1/admin/*` (Sanctum token + permission middleware).

---

## Accessing CMS

CMS is under Admin (same login):

| URL | Permission | Content |
|-----|------------|---------|
| `/admin/cms` | `view_analytics` | CMS dashboard stats |
| `/admin/cms/homepage` | `manage_homepage` | Homepage sections & blocks |
| `/admin/cms/announcements` | `manage_announcements` | Announcements |
| `/admin/cms/events` | `manage_events` | Public events |
| `/admin/cms/team` | `manage_team` | Team members |
| `/admin/cms/resources` | `manage_resources` | Public resource library |
| `/admin/cms/media` | `manage_media` | Media uploads |

**Public API** (no auth): `GET /api/v1/website/homepage`, `/events`, `/announcements`, `/team`, `/resources`.

---

## LMS feature map (Vue `/academy`)

| Feature | Route | API |
|---------|-------|-----|
| Dashboard | `/academy/dashboard` | `GET /academy/dashboard` |
| Courses | `/academy/courses` | `GET /academy/courses` |
| Modules | `/academy/modules` | Course details include modules |
| Lessons | `/academy/courses/:id/lessons/:id` | Complete via `POST .../lessons/{id}/complete` |
| Quizzes | `/academy/quizzes` | Submit via `POST .../quizzes/{id}/submit` |
| Gamification | Badges, achievements, streak | `/academy/badges`, `/achievements`, dashboard stats |
| Discussions | `/academy/discussions` | `/discussions/*` + reactions/bookmarks/reports |
| Practice Lab | `/academy/scenarios` | `/simulations/scenarios`, `/simulations/history` |
| Mentor Studio | `/academy/mentor/*` | `/mentor/dashboard`, grade submissions |

**Removed by design:** AI Mentor, Certificates/Certification.

---

## Running tests

```powershell
# Frontend
Set-Location "D:\projects\msa + dawah\MSA Platform\frontend"
npm run test:unit

# Backend
Set-Location "D:\projects\msa + dawah\MSA Platform\backend"
php artisan test
```

---

## Environment variables

### Laravel (`backend/.env`)

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Public URL of the site |
| `DB_*` | Database connection |

### Frontend (`frontend/.env`)

| Variable | Purpose |
|----------|---------|
| `VITE_API_URL` | Laravel API base (default `http://localhost:8000/api/v1`) |

---

## Production deployment notes

1. Build Vue frontend: `npm run build` → serve static files from `public/` or CDN.
2. Point `VITE_API_URL` to production Laravel API.
3. Run queue worker: `php artisan queue:work`.
4. Seed permissions after deploy if needed: `php artisan db:seed --class=DatabaseSeeder`.

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Academy 401 / redirect to login | Token expired or missing; log in at `/login` |
| Admin 403 | User lacks permission; use superadmin or assign role in `/admin/roles` |
| API connection failed | Check `VITE_API_URL` and that `php artisan serve` (or production API) is running |
| Empty academy data | Run migrations + seeders; enroll in a course from catalog |

---

## Related docs

- `docs/architecture.md` — high-level system design
- `docs/academy_student_experience.md` — student LMS flows
- `docs/analytics_platform.md` — analytics modules
- `docs/security_hardening.md` — security checklist
