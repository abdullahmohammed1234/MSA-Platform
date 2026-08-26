# PHASE 5 — DAMS EXTRACTION IMPLEMENTATION REPORT

**Repository:** SFU MSA Platform  
**Date:** 2026-08-26  
**Mode:** Implementation complete (incremental, non-breaking)  
**Prior:** Phase 2 boundaries → Phase 3 auth/RBAC contract → Phase 4 CMS extraction

---

## 1. Executive Summary

Academy administration is now a distinct application boundary — the **Dawah Academy Management System (DAMS)** — at **`/dams`**, following the Phase 4 CMS pattern (`GateLayout` + app `Layout` + redirects + Systems registry + ownership config/provider).

- **MSA Admin** no longer renders Academy management screens; legacy `/admin/academy/*` (and desks such as `/admin/achievements`) **redirect** to `/dams/*`.
- **Authentication** remains Platform Sanctum + shared `User` / roles / permissions. No DAMS users, passwords, or tokens.
- **APIs** remain at **`/api/v1/admin/academy/*`** for compatibility; DAMS owns them operationally.
- **Learner Academy** (`/academy`, `/api/v1/academy/*`) is untouched; **`VITE_ACADEMY_ENABLED=false`** preserved.
- **CMS** remains independent; announcements stay on CMS; course assets stay Academy-owned (`uploads/academy/`), not CMS Media.
- **Live Admin** classified as DAMS command-center UI (uses Platform notification/queue infra).

---

## 2. Files Changed

### Backend (new)
| Path | Role |
|------|------|
| `backend/config/dams.php` | DAMS ownership metadata + access permission pack |
| `backend/app/Dams/DamsServiceProvider.php` | Application boundary provider |
| `backend/app/Http/Controllers/Api/V1/Admin/DamsSystemController.php` | Systems registry API |
| `backend/tests/Feature/Dams/DamsIsolationTest.php` | Isolation / authz / asset / gap tests |

### Backend (touched)
| Path | Change |
|------|--------|
| `backend/bootstrap/providers.php` | Register `App\Dams\DamsServiceProvider` |
| `backend/routes/api.php` | `systems/dams` index/health/metrics |

### Frontend (new)
| Path | Role |
|------|------|
| `frontend/src/router/dams.ts` | `/dams` route map |
| `frontend/src/layouts/DamsGateLayout.vue` | Auth gate shell |
| `frontend/src/layouts/DamsLayout.vue` | DAMS chrome + nav |
| `frontend/src/pages/admin/system/DamsSystemPage.vue` | Systems UI |
| `frontend/src/services/dams/index.ts` | DAMS service boundary marker |
| `frontend/src/stores/dams/index.ts` | Re-exports admin academy stores |
| `frontend/src/__tests__/damsRoutes.spec.ts` | Redirect / ownership route tests |

### Frontend (touched)
| Path | Change |
|------|--------|
| `frontend/src/router/index.ts` | Mount `damsRoutes` |
| `frontend/src/router/admin.ts` | Academy routes → redirects; add `systems/dams` |
| `frontend/src/layouts/AdminLayout.vue` | Remove Academy Admin nav; Open DAMS; Systems DAMS entry |
| `frontend/src/pages/admin/academy/*.vue` | In-app links → `/dams/*` (except Platform user-management path) |

Controllers/services under `AdminAcademy*` / `Services\Academy*` were **not** mass-renamed (same principle as Phase 4 CMS).

---

## 3. New DAMS Application Structure

```text
/dams                          → DamsGateLayout → DamsLayout
├── /                          Dashboard
├── /courses[/create|/:id/edit]
├── /modules, /lessons
├── /quizzes, /quiz-management, /question-bank, /quiz-builder
├── /students, /mentors, /mentor-management, /assignments
├── /progress, /moderation
├── /learning-paths, /achievements, /badges
├── /analytics, /reports, /volunteer-analytics, /audit
├── /settings
└── /live-admin

Backend marker: App\Dams\DamsServiceProvider + config/dams.php
API contract:   /api/v1/admin/academy/*  (stable)
Systems:        /admin/systems/dams + GET /api/v1/admin/systems/dams*
```

Page components remain under `pages/admin/academy/*` (ownership via `/dams` routes), mirroring CMS pages under `pages/admin/cms/*`.

---

## 4. Routes Created

| SPA path | Name (examples) | Permission meta |
|----------|-----------------|-----------------|
| `/dams` | `dams-dashboard` | OR pack (analytics + academy manage perms) |
| `/dams/courses*` | `dams-courses*` | `manage_courses` |
| `/dams/modules` | `dams-modules` | `manage_modules` |
| `/dams/lessons` | `dams-lessons` | `manage_lessons` |
| `/dams/quizzes*` / question-bank / quiz-builder | `dams-quizzes*` | `manage_quizzes` |
| `/dams/students` | `dams-students` | `manage_students` |
| `/dams/mentors*` / assignments | `dams-mentors*` | `manage_mentors` |
| `/dams/progress` | `dams-progress` | `view_progress` |
| `/dams/moderation` | `dams-moderation` | `view_analytics` (FE; API uses `manage_discussions`) |
| `/dams/learning-paths` | `dams-learning-paths` | `manage_learning_paths` |
| `/dams/achievements` / badges | | `manage_achievements` / `manage_badges` |
| `/dams/analytics*` / reports / audit | | `view_analytics` |
| `/dams/settings` | | `manage_settings` |
| `/dams/live-admin` | | `manage_notifications` |
| `/admin/systems/dams` | `admin-systems-dams` | `system.view` |

---

## 5. Routes Redirected

| Legacy MSA Admin | DAMS target |
|------------------|-------------|
| `/admin/academy/dashboard` | `/dams` |
| `/admin/academy/courses` (+ create/edit) | `/dams/courses*` |
| `/admin/academy/modules` | `/dams/modules` |
| `/admin/academy/lessons` | `/dams/lessons` |
| `/admin/academy/quizzes*` / question-bank / quiz-builder / quiz-management | `/dams/...` |
| `/admin/academy/students` / mentors / mentor-management / assignments | `/dams/...` |
| `/admin/academy/progress` / analytics / reports / volunteer-analytics / audit | `/dams/...` |
| `/admin/academy/moderation` / settings / live-admin | `/dams/...` |
| `/admin/achievements` | `/dams/achievements` |
| `/admin/badges` | `/dams/badges` |
| `/admin/learning-paths` | `/dams/learning-paths` |

**Not extracted (intentional):**

| Path | Owner |
|------|--------|
| `/admin/academy/announcements` | CMS → `/cms/announcements` (Phase 4) |
| `/admin/academy/user-management` | Platform (identity) |

---

## 6. Backend Ownership Changes

| Concern | Ownership |
|---------|-----------|
| Academy admin HTTP (`/api/v1/admin/academy/*`) | **DAMS** (operational); paths unchanged |
| Course asset upload | **DAMS/Academy** (`AcademyAssetService`, `uploads/academy/`) |
| Systems registry for DAMS | **Platform Systems** + `DamsSystemController` |
| Identity / Sanctum / RBAC tables | **Platform** |
| Learner runtime (`/api/v1/academy/*`) | **Dawah Academy** (learner) |
| CMS resources/media/announcements | **CMS** |

No database split. No DAMS-specific auth tables. No mass controller namespace rename.

---

## 7. Frontend Ownership Changes

| Concern | Location |
|---------|----------|
| DAMS shell | `router/dams.ts`, `DamsGateLayout`, `DamsLayout` |
| MSA Admin | Redirects + “Open DAMS”; no Academy Admin sidebar group |
| Admin Pinia stores | Still `stores/admin/academy/*`; re-exported via `stores/dams` |
| Learner stores/services | Unchanged under `stores/academy`, `services/academy` |
| Course asset client | `academyAssetsService` (also exported from `services/dams`) |

---

## 8. Authentication Behavior

Unchanged Platform model:

```text
User → Sanctum bearer → DAMS SPA/API → hasPermission / policies / admin bypass
```

No DAMS login, OAuth, or separate identity store.

---

## 9. RBAC Behavior

- Existing permission slugs preserved; **no renames**.
- `admin` / `super-admin` global bypass preserved (`HasRolesAndPermissions` + `Gate::before`).
- Frontend `permissionGuard` continues to OR-match route `meta.permissions` with privileged-admin bypass.
- Cross-app isolation enforced server-side:
  - CMS-only → DAMS admin APIs **403**
  - EMS-only (`events.view`) → DAMS admin APIs **403**
  - Learner (`volunteer`) → DAMS admin APIs **403**
  - DAMS `manage_courses` → CMS homepage **403**
  - DAMS operator without learner roles → `/api/v1/academy/*` **403** (role gate)

---

## 10. Permission Gaps Discovered

Still present (Phase 3); **not silently “fixed”** in Phase 5:

| Slug | Status |
|------|--------|
| `manage_students` | Used by students admin routes; **not** in `DatabaseSeeder` Academy pack |
| `assign_mentors` | Referenced in product intent; routes use **`manage_mentors`** |
| `view_student_progress` | Referenced; routes use **`view_progress`** |
| `manage_question_bank` | Referenced; question bank uses **`manage_quizzes`** |

**FE/API mismatch (preserved):** moderation SPA meta uses `view_analytics`; API uses `manage_discussions`.

Covered by `DamsIsolationTest::permission_gaps_exist_in_seed_catalog_relative_to_phase_3_pack`.

**Practical effect:** non-admin operators cannot use Students desk until `manage_students` is seeded and assigned; admins still work via bypass.

---

## 11. Database Ownership

Shared Academy schema unchanged. DAMS is **operational** owner of administration writes; learner Academy **reads/writes learner runtime** on the same tables.

No DAMS duplicate tables. No migrations for extraction.

**Student management boundary (documented):**

| Concept | Owner |
|---------|--------|
| `users` / roles / platform identity | Platform (`/admin/academy/user-management`) |
| Academy student roster / suspend / enrollments admin | DAMS (`manage_students` APIs) |
| Volunteer learner participation | Academy learner + shared enrollments |

No second user identity invented.

---

## 12. Storage Ownership

| Domain | Path | Notes |
|--------|------|-------|
| Academy/DAMS course assets | `uploads/academy/` | `manage_courses`; no `media` row |
| CMS | `uploads/cms/` | Unchanged from Phase 4 |
| Certificates | Existing certificate paths | Unchanged |

---

## 13. Academy Learner Boundary

| Item | Status |
|------|--------|
| `/academy` | Separate; feature-flagged |
| `/api/v1/academy/*` | Separate; role middleware |
| `VITE_ACADEMY_ENABLED` | **`false`** (`.env`, `.env.production`, `.env.example`) |
| Learner UX moved into DAMS? | **No** |

---

## 14. CMS Boundary

| Item | Status |
|------|--------|
| CMS app `/cms` | Independent |
| Announcements | CMS (legacy admin path redirects to CMS) |
| Resources / media | CMS-owned |
| DAMS course assets via CMS Media? | **No** |

---

## 15. Systems Registry Changes

Target applications now include:

1. Main Website  
2. Content Management System  
3. Dawah Academy *(learner)*  
4. **Dawah Academy Management System (DAMS)** *(new)*  
5. Event Management System  

DAMS and Dawah Academy are **separate** Systems entries.

---

## 16. Live Admin Classification

**Decision:** `/dams/live-admin` — **DAMS**

`LiveAdminSection.vue` is Academy operational command-center UX. It **consumes** Platform notification + queue APIs (infrastructure remains Platform). Not duplicated into Platform Admin screens.

---

## 17. Certificates UI Note

Certificate **admin APIs** remain under `/api/v1/admin/academy/*` (DAMS-owned operations). There is **no dedicated certificates SPA page** today; Phase 5 did not invent one. Templates/issuance continue via existing API/admin tooling where present.

---

## 18. Tests Added

| Suite | Coverage |
|-------|----------|
| `tests/Feature/Dams/DamsIsolationTest.php` | DAMS allow; CMS/EMS/learner deny; admin/super-admin allow; CMS isolation; learner API role gate; representative endpoints; academy assets ≠ CMS media; systems registry; permission gaps |
| `frontend/src/__tests__/damsRoutes.spec.ts` | `/dams` registered; legacy redirects; announcements→CMS; user-management stays Platform; systems/dams |

---

## 19. Test Results

```text
DamsIsolationTest                 12 passed
AcademyAssetOwnershipTest          5 passed
AdminAcademyTest                   4 passed
AdminDiscussionTest                2 passed
CourseTest                         6 passed
LearningPathTest                   4 passed
CmsEngineTest                     19 passed
damsRoutes.spec.ts (Vitest)        5 passed
```

Combined filter run (Academy + CMS + DAMS isolation): **40+** PHPUnit tests green in the relevant suites above.

---

## 20. Known Limitations

1. Permission seed gaps (`manage_students`, etc.) remain; Students desk relies on admin bypass or manual permission creation until a later RBAC phase.
2. Moderation FE vs API permission mismatch unchanged.
3. Vue pages/stores still physically under `pages/admin/academy` / `stores/admin/academy` (logical ownership via `/dams`).
4. Backend classes largely still `AdminAcademy*` namespaces (intentional non-risky rename).
5. `permissionGuard` denial still redirects toward academy-named route in some cases (pre-existing; not redesigned).

---

## 21. Deferred Work

- Seed/correct Phase 3 permission gaps (minimal seeder change in a dedicated RBAC hardening phase).
- Optional physical move of Vue files to `pages/dams/*` and PHP to `App\Dams\Controllers\*`.
- Certificates admin SPA if/when product requires it.
- Align moderation FE meta with `manage_discussions`.
- Enable Academy learner launch (`VITE_ACADEMY_ENABLED`) — **out of scope**.

---

## 22. Phase 6 Recommendations

1. **RBAC hardening:** seed `manage_students` (and decide fate of unused slugs); do not remove admin bypass without a migration plan.  
2. **EMS/CMS events:** resolve legacy CMS events vs EMS ownership (still deferred from Phase 4).  
3. **Academy launch readiness:** feature flag, public resources policy, learner QA — only after DAMS/CMS boundaries are stable.  
4. **Optional namespace cleanup** after redirects prove stable in production.  
5. **Observability:** Systems health checks for DAMS could expand beyond course/quiz/enrollment counts.

---

## 23. Acceptance Criteria Checklist

| Criterion | Status |
|-----------|--------|
| DAMS shell / routing / layout / gate | Done |
| DAMS in Systems | Done |
| Academy admin not rendered in MSA Admin | Done (redirects) |
| Courses…Settings preserved via same pages/APIs | Done |
| Sanctum / User / no DAMS auth | Done |
| Permissions + admin bypass preserved | Done |
| CMS / learner / EMS isolation | Tested |
| Academy assets ≠ CMS media | Tested |
| Learner `/academy` + flag false | Done |
| Announcements stay CMS; user-mgmt Platform | Done |
| Legacy redirects | Done + Vitest |
| Report delivered | This document |

---

## 24. Final Architecture (Phase 5)

```text
                         MSA PLATFORM
                Identity · RBAC · Security
              Queues · Email · Storage · Systems
                              │
          ┌───────────────────┼───────────────────┐
          │                   │                   │
         CMS                 DAMS              EMS
          │                   │                   │
     Website content    Academy management    Events
          │                   │
          └──────────────┐    │
                         │    │
                  Main Website
                         │
                  Dawah Academy (learner)
```

All applications continue using **MSA Platform Identity → Sanctum User → Shared RBAC**.

**Outcome:** Staff enter `/dams` to manage the Academy without MSA Admin screens; learners remain on `/academy`; CMS/EMS/Platform stay separate despite one database and one identity plane.
