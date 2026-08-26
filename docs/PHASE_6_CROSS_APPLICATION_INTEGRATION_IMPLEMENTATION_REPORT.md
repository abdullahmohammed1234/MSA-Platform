# PHASE 6 — CROSS-APPLICATION INTEGRATION & BOUNDARY HARDENING

**Repository:** SFU MSA Platform  
**Date:** 2026-08-26  
**Mode:** Build / Verify / Harden  
**Prior:** Phases 2–5 (CMS `/cms`, DAMS `/dams`, Platform identity, EMS events)

---

## 1. Executive Summary

Phase 6 hardens the five-application boundary model without redesigning Phases 2–5.

| Outcome | Result |
|---------|--------|
| **EMS event cutover** | Main Website homepage now consumes **EMS public events** (`publicEventsService`), not CMS `/website/events` |
| **Legacy CMS events** | Classified and retained (tables/API not deleted); homepage no longer depends on them for display |
| **RBAC gaps** | `manage_students` **seeded**; dead aliases **removed** from controllers |
| **Academy roles** | Frontend learner access aligned to API: `volunteer\|mentor\|admin\|super-admin` |
| **Notifications** | `NotificationSeeder` wired into `DatabaseSeeder` |
| **Isolation** | New Phase 6 cross-app tests (CMS ↔ DAMS ↔ Academy ↔ EMS ↔ Platform) |
| **Assets / Systems / Redirects** | Verified preserved; Systems still lists five apps |
| **Academy launch** | `VITE_ACADEMY_ENABLED=false` unchanged |

**Phase 7 readiness:** Yes — Systems registry data model is consistent enough to rebuild the Systems UI as an operational view.

---

## 2. Event Cutover (Task 1)

### Target flow

```text
EMS (ems_events)
  └── GET /api/v1/ems/public/events
          └── Main Website HomePage (featured cards → /events/{slug})
```

### What changed

| File | Change |
|------|--------|
| `frontend/src/pages/public/HomePage.vue` | Uses `publicEventsService.listEvents({ upcoming, per_page: 2 })`; cards link to `/events/{slug}` |
| `frontend/src/services/website/websiteService.ts` | `getEvents()` marked **@deprecated** (legacy CMS path) |
| `MainWebsiteSystemController` metrics | Counts **EMS** `ems_events` / `ems_registrations`; exposes `events_source: ems` |
| `MainWebsiteSystemPage.vue` | Copy updated: “EMS public events consumed by Main Website” |

### What did **not** change (intentional)

- `GET /api/v1/website/events*` and CMS `events` / `event_registrations` tables remain for RSVP/history
- No table deletion
- Public `/events` SPA was already EMS (unchanged)

---

## 3. Legacy Event Analysis & Retirement Readiness

| Reference | Classification | Notes |
|-----------|----------------|-------|
| `WebsiteController::events` / RSVP | **LEGACY** · **ACTIVE** | Still live; not homepage source |
| CMS `Event` / `EventRegistration` models | **LEGACY** · **BLOCKED** | Keep until RSVP/QR decommission |
| `CMS\EventService` / Repository / SaveEventRequest | **DEPRECATED** | No admin controller |
| `cmsService` admin event CRUD (FE) | **DEPRECATED** | Backend admin CMS events 404 |
| `EventsPage.vue` / `EventDetailPage.vue` | **DEPRECATED** | Not in router |
| Homepage featured events | **ACTIVE** → **EMS CONSUMER** | Cut over in Phase 6 |
| Public `/events*` | **ACTIVE** EMS | Pre-existing |
| Main Website systems metrics | **ACTIVE** → EMS counts | Updated |
| Permission `manage_events` | **LEGACY** Website slug | ≠ EMS `events.*`; still used in CMS contextual upload OR-chain |
| Tables `events` / `event_registrations` | **BLOCKED** | Not safe to drop |

### Retirement plan (deferred)

1. Confirm no external clients hit `/website/events*` / RSVP  
2. Feature-flag or 410 legacy Website event routes  
3. Archive historical RSVP/QR if needed  
4. Delete orphan FE pages + dead CMS EventService stack  
5. Only then drop tables  

**Deletion is out of scope for Phase 6.**

---

## 4. RBAC Audit (Task 2)

| Permission | Prior state | Decision | Seed status | Roles | Routes | Controller | Frontend | Final |
|------------|-------------|----------|-------------|-------|--------|------------|----------|-------|
| `manage_students` | Unseeded; middleware required it | **SEED** | Seeded in `DatabaseSeeder` | admin, dawah-coordinator | `permission:manage_students` | Canonical only | `/dams/students` | **Resolved** |
| `assign_mentors` | Dead alias | **REMOVE** | Not seeded | — | Uses `manage_mentors` | Alias removed | `manage_mentors` | **Resolved** |
| `view_student_progress` | Dead alias | **REMOVE** | Not seeded | — | Uses `view_progress` | Alias removed | `view_progress` | **Resolved** |
| `manage_question_bank` | Dead alias | **REMOVE** | Not seeded | — | Uses `manage_quizzes` | Alias removed | `manage_quizzes` | **Resolved** |

**Also aligned:** DAMS moderation FE meta + nav → `manage_discussions` (matches API).

Admin/super-admin bypass **preserved**.

---

## 5. Academy Role Alignment (Task 3)

| Layer | Before | After (canonical) |
|-------|--------|-------------------|
| API middleware | `volunteer\|mentor\|admin\|super-admin` | Unchanged (authoritative) |
| `roleGuard` `requiresStudent` | Also director, dawah-coordinator | **volunteer / mentor / admin** (+ privileged short-circuit) |
| `authStore.canAccessAcademy` | Included director / coordinator | **volunteer / mentor / privileged admin** |
| Role semantics | director = org/analytics; coordinator = DAMS operator | Coordinator uses **`/dams`**, not learner `/academy` |

Server was not weakened to match frontend.

---

## 6. Notification Seeding (Task 4)

| Item | Status |
|------|--------|
| `NotificationSeeder` called from `DatabaseSeeder` | **Yes** (step 9) |
| Slugs | `send_notifications`, `manage_notifications`, `manage_notification_templates` (unchanged) |
| Role grants | super-admin all; admin all three; dawah-coordinator send + manage |
| Preference backfill | Unchanged in seeder |

---

## 7. Cross-Application Isolation Matrix (Tasks 5 & 10)

| Source | CMS | DAMS admin | Academy learner | EMS admin | Platform users |
|--------|-----|------------|-----------------|-----------|----------------|
| CMS (`manage_homepage`) | **ALLOW** | **DENY** | DENY admin | **DENY** | **DENY** |
| DAMS (`manage_courses` pack) | **DENY** | **ALLOW** | DENY admin* | **DENY** | **DENY** |
| Academy learner (`volunteer`) | DENY admin | **DENY** | **ALLOW** learner | DENY | DENY |
| EMS (`events.view` via organizer) | **DENY** | **DENY** | DENY | **ALLOW** | DENY |
| Platform (`manage_users`) | DENY† | DENY | DENY | DENY | **ALLOW** |
| `admin` / `super-admin` | ALLOW via bypass | ALLOW via bypass | ALLOW where role middleware permits | ALLOW via bypass | ALLOW |

\* DAMS operators without volunteer/mentor/admin roles are **denied** `/api/v1/academy/*` (role gate).  
† Not specially asserted beyond CMS/DAMS isolation; Platform user-management remains `manage_users`.

**Intentional exception:** Legacy Website `manage_events` ≠ EMS authorization (asserted: does **not** grant `/api/v1/ems/events`).

---

## 8. Course Asset Ownership (Task 6)

Verified unchanged and re-tested:

- `POST /api/v1/admin/academy/assets/upload` → `owner: academy`, `uploads/academy/`, **no** `media` row  
- `manage_courses` does **not** unlock CMS contextual upload  
- CMS Media remains CMS-owned  

---

## 9. Shared Infrastructure Verification (Task 7)

| Capability | Owner | CMS/DAMS behavior |
|------------|-------|-------------------|
| Auth | Platform Sanctum | Shared bearer; no app-local auth |
| Users / RBAC | Platform tables | Shared |
| Queues / mail / notifications | Platform | DAMS Live Admin consumes Platform APIs |
| Storage | Shared disk + path namespaces | `uploads/cms/` vs `uploads/academy/` |
| Audit / security | Platform | Unchanged |
| Analytics | Shared where applicable | App-specific dashboards only |

No parallel auth, queue, mail, or notification stacks introduced.

---

## 10. Systems Registry (Task 8)

Confirmed five applications (API + Admin routes/pages):

1. Main Website — `/admin/systems/main-website`  
2. CMS — `/admin/systems/cms`  
3. Dawah Academy (learner) — `/admin/systems/dawah-academy`  
4. DAMS — `/admin/systems/dams`  
5. EMS — `/admin/systems/ems`  

Systems UI rebuild deferred to **Phase 7**.

---

## 11. Legacy Admin Redirects (Task 9)

Verified still present in `frontend/src/router/admin.ts` (+ Vitest `damsRoutes.spec.ts`):

- `/admin/cms/*` → `/cms/*`  
- `/admin/academy/*` desks → `/dams/*`  
- `/admin/achievements|badges|learning-paths` → `/dams/*`  
- Exceptions: announcements → CMS; user-management stays Platform  

Redirects **not** deleted.

---

## 12. Public Website & Resources Contracts (Tasks 11–12)

| Consumer | Source | Status |
|----------|--------|--------|
| Homepage content | CMS homepage API | Unchanged |
| Homepage events | **EMS public API** | Cut over |
| Announcements / team / media | CMS | Unchanged |
| Resources | CMS-owned; Academy consumes published | Unchanged |
| Public `/resources` | Still disabled / not exposed | **Not enabled** |

---

## 13. Files Changed (implementation)

### Backend
- `database/seeders/DatabaseSeeder.php` — `manage_students` + NotificationSeeder call + coordinator grants  
- `app/Http/Controllers/Api/V1/AdminAcademyController.php` — remove dead permission aliases  
- `app/Http/Controllers/Api/V1/Admin/MainWebsiteSystemController.php` — EMS metrics  
- `tests/Feature/Phase6/CrossApplicationIsolationTest.php` — **new**  
- `tests/Feature/Phase6/NotificationSeederWiringTest.php` — **new**  
- `tests/Feature/Dams/DamsIsolationTest.php` — gap resolution assertions updated  

### Frontend
- `pages/public/HomePage.vue` — EMS featured events  
- `services/website/websiteService.ts` — deprecate CMS getEvents  
- `stores/auth.ts`, `router/guards/roleGuard.ts` — learner role alignment  
- `router/dams.ts`, `layouts/DamsLayout.vue` — moderation → `manage_discussions`  
- `pages/admin/system/MainWebsiteSystemPage.vue` — metrics copy  
- `router/guards/__tests__/roleGuard.spec.ts` — director/coordinator denied  

---

## 14. Tests

### New
| Suite | Count |
|-------|-------|
| `CrossApplicationIsolationTest` | 12 |
| `NotificationSeederWiringTest` | 2 |
| roleGuard director/coordinator denial | +1 |

### Re-run (passed)
| Suite | Result |
|-------|--------|
| Phase 6 + DAMS + Academy assets + Main Website systems | **35 passed** |
| CmsEngineTest + AdminAcademyTest + CourseTest | **29 passed** |
| Vitest `roleGuard.spec` + `damsRoutes.spec` | **14 passed** |
| Failures / regressions | **0** |

---

## 15. Remaining Technical Debt

1. Legacy CMS event tables/API/RSVP still live — retire after migration policy  
2. Orphan CMS event FE pages and `cmsService` event methods  
3. Platform `manage_events` still in Website pack / CMS upload OR-chain — separate from EMS  
4. Physical Vue/PHP namespace moves (`pages/dams`, `App\Dams\Controllers`) still optional  
5. Existing DBs need re-seed or migration of permission rows to pick up `manage_students` + notification perms  

---

## 16. Phase 7 Readiness

**Ready for Systems Rebuild** when Phase 7 starts:

- Five applications are distinct and registered  
- Ownership contracts are explicit (CMS content, EMS events, DAMS admin, Academy learner, Platform identity)  
- Isolation is covered by automated tests  
- Main Website event display no longer creates CMS/EMS ambiguity for normal UX  

Phase 7 should consume this registry as truth—not invent parallel application lists.

---

## 17. Definition of Done Checklist

| Criterion | Status |
|-----------|--------|
| EMS authoritative for Main Website event display | Done |
| Legacy CMS event deps classified | Done |
| RBAC gaps have explicit decisions | Done |
| Academy FE/API roles aligned | Done |
| Notification permissions seeded on standard path | Done |
| Isolation tests | Done |
| Course assets outside CMS Media | Done |
| Shared infra Platform-owned | Verified |
| Five apps in Systems registry | Verified |
| Legacy redirects intact | Verified |
| Phase 4/5 tests green | Verified |
| Academy flag unchanged | Verified |
| No auth redesign / no premature table delete | Verified |

---

## 18. Final Principle (achieved)

```text
Five applications
  → Clear ownership
  → Shared Platform identity & infrastructure
  → Explicit data contracts (CMS content · EMS events · DAMS admin · Academy learner)
  → Server-side authorization
  → Tested isolation
```
