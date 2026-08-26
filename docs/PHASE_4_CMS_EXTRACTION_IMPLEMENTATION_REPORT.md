# PHASE 4 — CMS EXTRACTION IMPLEMENTATION REPORT

**Repository:** SFU MSA Platform  
**Date:** 2026-08-26  
**Mode:** Implementation complete (incremental, non-breaking)

---

## 1. Executive Summary

CMS is now a distinct application boundary at **`/cms`**, with MSA Admin retaining redirects from `/admin/cms/*`. Centralized Sanctum authentication and existing Website permission slugs are unchanged. Course thumbnails upload through a **DAMS/Academy-owned** endpoint and no longer require CMS permissions or create CMS `media` rows. Academy announcements management is rehomed to CMS. CMS Events were **not** deleted. Academy remains disabled; public `/resources` remains disabled.

---

## 2. Before Architecture

```text
MSA Admin (/admin)
├── CMS pages (/admin/cms/*)
├── Academy Admin (+ CMS announcements duplicate)
└── Platform / Security / Systems

Shared API: /api/v1/admin/cms/*
Course thumbnails → POST /admin/cms/assets/upload (permission OR included manage_courses)
```

---

## 3. After Architecture

```text
MSA Platform (identity / RBAC / infra)
│
├── CMS application (/cms + CmsLayout + App\Cms\CmsServiceProvider)
│     └── APIs: /api/v1/admin/cms/* (unchanged paths)
│     └── Public content: /api/v1/website/{homepage,announcements,team,resources,media}
│
├── MSA Admin (/admin) — Open CMS link; redirects /admin/cms/* → /cms/*
│
├── DAMS candidate (/admin/academy/*)
│     └── Course assets: POST /api/v1/admin/academy/assets/upload (manage_courses)
│
├── Dawah Academy learner (/academy) — still gated by VITE_ACADEMY_ENABLED
└── EMS — unchanged
```

---

## 4. Files/Modules Moved / Added

| Area | Path |
|------|------|
| CMS provider | `backend/app/Cms/CmsServiceProvider.php` |
| CMS config metadata | `backend/config/cms.php` (owns/does_not_own + disk_directory) |
| Academy assets | `AcademyAssetService`, `AcademyAssetController`, `UploadAcademyAssetRequest` |
| Systems CMS API | `CmsSystemController` |
| Frontend CMS app | `router/cms.ts`, `CmsGateLayout.vue`, `CmsLayout.vue` |
| Systems UI | `pages/admin/system/CmsSystemPage.vue` |
| Academy FE upload | `services/academy/academyAssetsService.ts` |
| Tests | `tests/Feature/Academy/AcademyAssetOwnershipTest.php` |

CMS page components remain at `pages/admin/cms/*` (shared UI owned by CMS routes) — no destructive move of Vue files.

---

## 5. Files/Modules Retained

- Platform auth, users, roles, permissions, Sanctum  
- `Admin\CMS\*` controllers, `Services\CMS\*`, `Models\CMS\*` (same namespaces; ownership documented via `App\Cms`)  
- Public `WebsiteController` CMS content endpoints  
- Legacy CMS `events` / `event_registrations` + `/website/events*`  
- EMS module untouched  
- Academy learner APIs / feature flag  
- Shared MySQL / public disk infrastructure  

---

## 6. CMS Application Boundary

- **Frontend shell:** `/cms` with `CmsLayout` (title “CMS”), Platform Sanctum session.  
- **Backend marker:** `App\Cms\CmsServiceProvider` registered in `bootstrap/providers.php`.  
- **API paths:** intentionally kept at `/api/v1/admin/cms/*` for consumer compatibility.  
- **Admin transition:** `/admin/cms/*` → redirect to `/cms/*`.

---

## 7. Authentication Integration

CMS uses existing Platform Sanctum bearer tokens (`auth:sanctum`). No CMS users, passwords, or token stores were added.

---

## 8. RBAC Integration

Unchanged permission slugs: `manage_homepage`, `manage_announcements`, `manage_team`, `manage_resources`, `manage_media`, `view_analytics` (dashboard), plus legacy `manage_events` still in CMS contextual upload OR-chain for EMS banner uploads via CMS endpoint.  
`admin` / `super-admin` bypass unchanged.  
**Removed:** `manage_courses` from CMS `assets/upload` middleware.

---

## 9. CMS API Changes

| Change | Detail |
|--------|--------|
| `POST /admin/cms/assets/upload` | No longer accepts `manage_courses`; response includes `owner: cms`; stores under `uploads/cms/` |
| `POST /admin/cms/media` | Library files also under `uploads/cms/` (new uploads) |
| `POST /admin/academy/assets/upload` | **New** — `manage_courses`; `owner: academy`; `uploads/academy/` |
| `GET /admin/systems/cms*` | **New** systems registry endpoints |

Public website CMS content APIs unchanged.

---

## 10. Frontend Changes

- New `/cms` router + layouts  
- MSA Admin: CMS nav group removed; “Open CMS” under Applications; Systems lists CMS  
- Announcements: `/admin/academy/announcements` → `/cms/announcements`  
- User management remains under Administration (still path `/admin/academy/user-management`)  
- Course create/edit: `ImageInput` uses `academyAssetsService.uploadImage`  
- `ImageInput` accepts optional `uploadFn` (default CMS)  

---

## 11. Backend Changes

- `MediaService::storeAsset` / library `upload` → `uploads/cms/`  
- `AcademyAssetService` → `uploads/academy/` (no media rows)  
- CMS systems controller for registry  

---

## 12. Database Ownership

Logical ownership unchanged; **no migrations**, no table moves, no deletes:

CMS-owned: `homepage_*`, `announcements`, `team_members`, `resources`, `media`, `media_categories`, `cms_revisions`.  
Legacy retained: `events`, `event_registrations`.

---

## 13. Storage Ownership

| Domain | New path prefix | Notes |
|--------|-----------------|-------|
| CMS | `uploads/cms/` | New contextual + library uploads |
| Academy/DAMS courses | `uploads/academy/` | Course thumbnails |
| Team photos | `team/` | Existing (unchanged) |
| Legacy files | `uploads/` | Still served; not migrated |

Shared disk: `public`. No broad file migration.

---

## 14. Course Asset Ownership Changes

**Before:** Course `ImageInput` → CMS `/admin/cms/assets/upload` (authorized via `manage_courses` OR CMS perms).  
**After:** Course forms → `/admin/academy/assets/upload` (`manage_courses` only) → `uploads/academy/` → URL on `courses.thumbnail`.  
**Guarantees:** no CMS `media` row; CMS-only users cannot upload academy assets; course managers cannot use CMS contextual upload.

---

## 15. Main Website Integration

Consumers of `/api/v1/website/homepage|announcements|team|resources|media` unchanged.  
HomePage still may call legacy `/website/events` (CMS Events) — **follow-up**, not deleted.

---

## 16. Academy Resource Integration

`GET /api/v1/academy/resources` still reads published CMS `resources`. Ownership unchanged. Academy flag not enabled.

---

## 17. CMS Events Status

**LEGACY BUT REFERENCED — left intact.**  
No table deletion. No admin CMS events routes added. HomePage → EMS cutover remains a follow-up.

---

## 18. Academy Announcements Rehome

`/admin/academy/announcements` redirects to `/cms/announcements`. Removed from Academy Admin sidebar. Canonical UI: CMS Announcements.

---

## 19. Platform User Management Rehome

User Management moved in **navigation** to Administration group; route path kept (`/admin/academy/user-management`) for compatibility — still Platform `manage_users`, not CMS.

---

## 20. Systems Registry Changes

- API: `/api/v1/admin/systems/cms`, `/health`, `/metrics`  
- UI: `/admin/systems/cms` (`CmsSystemPage.vue`)  
- Admin System nav includes “Content Management System”

---

## 21. Security Changes

- Cross-pack leak fixed: course managers no longer authorized on CMS asset upload.  
- Isolation tests added (CMS-only vs course-manager vs learner).  
- Server-side middleware remains the security boundary.

---

## 22. Tests Added/Updated

- `AcademyAssetOwnershipTest` (5 cases)  
- `CmsEngineTest::contextual_asset_upload_*` asserts `uploads/cms/`  

---

## 23. Test Results

```text
AcademyAssetOwnershipTest — 5 passed
CmsEngineTest — 19 passed
```

---

## 24. Regression Results

- Full `CmsEngineTest` green (dashboard, announcements, media library, team photo, contextual upload).  
- Manual follow-up recommended: smoke Main Website homepage/team/media in browser; open `/cms` as admin.

---

## 25. Rollback Strategy

1. Revert this branch / commits.  
2. Restore `/admin/cms` page routes (remove redirects) and AdminLayout CMS children.  
3. Re-add `manage_courses` to CMS assets middleware if needed.  
4. Point course forms back to `cmsService.uploadAsset`.  
5. Unregister `CmsServiceProvider` if desired.  

**Database:** no rollback required (no schema changes).  
**Storage:** new files under `uploads/cms/` and `uploads/academy/` can remain; legacy URLs under `uploads/` unaffected.

---

## 26. Remaining Technical Debt

1. HomePage featured events still use CMS `/website/events` while product events are EMS.  
2. EMS event banners still use CMS contextual upload endpoint (unchanged by design this phase).  
3. CMS Vue pages still live under `pages/admin/cms/` path (logical ownership is `/cms` routes).  
4. User management URL still under `/admin/academy/...`.  
5. Academy `ResourcesPage.vue` still calls admin CMS media APIs (Academy disabled; clean up in DAMS/Academy phase).  
6. Physical move of controllers into `App\Cms\Http\...` deferred.  
7. `manage_students` seed gap (Phase 3) untouched.

---

## 27. Phase 5 DAMS Readiness

CMS boundary is stable enough to begin DAMS extraction design/implementation. Prerequisites from Phase 3 (`manage_students` seeding, FE/API role alignment) still apply for DAMS completeness. Do not extract DAMS course schema into a separate DB yet.

---

## 28. Final Success Criteria

| Criterion | Status |
|-----------|--------|
| CMS explicit application boundary (`/cms` + provider) | Done |
| CMS not conceptually inside MSA Admin nav | Done (redirects + Open CMS) |
| Centralized auth / RBAC / unchanged slugs / bypass | Done |
| CMS owns homepage, announcements, team, resources, media | Done |
| Academy consumes CMS resources; Academy disabled; `/resources` off | Preserved |
| Announcements rehomed to CMS | Done |
| User management Platform-owned | Done (nav) |
| CMS Events not deleted | Done |
| Course assets not CMS media; DAMS auth; no CMS perm required | Done |
| Tests for asset ownership / isolation | Done |
| No destructive DB migration / no second auth | Done |

---

### Recommended Next Phase

**Phase 5 — DAMS extraction** (Academy administration boundary), after optional HomePage → EMS events cutover if product prioritizes clearing CMS Events legacy references.
