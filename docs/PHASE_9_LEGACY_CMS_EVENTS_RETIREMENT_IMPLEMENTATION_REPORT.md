# Phase 9 — Legacy CMS Events Retirement Implementation Report

**Date:** 2026-08-26  
**Mode:** Implementation / migration / architectural cleanup  
**Prior:** Phase 8 — Systems Control Plane Hardening

## Summary

EMS is the sole authoritative Event Management System. Legacy CMS event application code and public `/website/events*` APIs are retired (HTTP **410 Gone**). Main Website continues to consume EMS public events. Historical `events` / `event_registrations` tables are **retained** (archived) — not dropped — because no CMS→EMS identity mapping was proven for production rows.

## Ownership model (confirmed)

| Application | Owns |
|-------------|------|
| **CMS** | Homepage, announcements, team, resources, media |
| **EMS** | Events, categories, registrations, tickets, check-ins, event notifications, Square |
| **Main Website** | Public UX; event data via EMS public API |
| **Academy / DAMS** | Learning (unchanged) |
| **Platform** | Identity / RBAC / Systems |

## Dependency audit (Stage 1)

### Live (pre-Phase 9)

| Surface | Finding |
|---------|---------|
| Main Website homepage | Already EMS (`publicEventsService`) — Phase 6 |
| Public `/events` routes | Already EMS pages (`EmsPublicEventsPage`) |
| `GET/POST/DELETE /api/v1/website/events*` | Live CMS Event + RSVP stack |
| Admin `/admin/cms/events` | Already unregistered (404) |
| `manage_events` | Website module slug; CMS media OR-chain; ≠ EMS `events.*` |

### Dead / unused (removed)

| Artifact | Action |
|----------|--------|
| `EventService`, `EventRepository`, `SaveEventRequest` | Deleted |
| `App\Policies\EventPolicy` (CMS) | Deleted |
| `EventCheckInQrService`, `EventRsvpConfirmation`, RSVP blade | Deleted |
| Orphan `EventsPage.vue`, `EventDetailPage.vue` | Deleted |
| `cmsService` event CRUD / check-in methods | Removed |
| CMS `Event` / `EventRegistration` TypeScript types | Removed |
| `websiteService` event/RSVP client methods | Removed |
| `CmsSeeder` Event block | Removed |
| `canManageEvents()` trait helper | Removed |

### Retained (archive)

| Artifact | Reason |
|----------|--------|
| Tables `events`, `event_registrations` | Historical data; no proven EMS mapping |
| Eloquent `App\Models\CMS\Event` (+ Registration) | Marked `@deprecated`; archive/inspection only |
| Permission slug `manage_events` | Still in CMS media upload OR-chain for directors; **not** event ownership |

## API changes

| Endpoint | Status |
|----------|--------|
| `GET /api/v1/website/events` | **410 Gone** (`retired: true`, `replacement: /api/v1/ems/public/events`) |
| `GET /api/v1/website/events/{id}` | **410 Gone** |
| `GET /api/v1/website/events/registrations` | **410 Gone** |
| `POST /api/v1/website/events/{id}/rsvp` | **410 Gone** |
| `DELETE /api/v1/website/events/{id}/rsvp` | **410 Gone** |
| `GET /api/v1/ems/public/events` | Unchanged — Main Website authority |
| Admin CMS events routes | Remain unregistered (404) |

Handler: `WebsiteController::legacyCmsEventsRetired()`.

## Permission changes

| Permission | Decision |
|------------|----------|
| `manage_events` | **Retired as event ownership.** Seed name/description updated. Not mapped to EMS `events.*`. Kept in admin/director packs + CMS media OR-chain for upload access only. |
| EMS `events.*`, `registrations.*`, `tickets.*`, `check_ins.*` | **Unchanged** |

## Data migration / archive decision

**No CMS → EMS migration executed.**

Rationale:

1. Main Website already displays EMS events (Phase 6).
2. Legacy RSVP API is retired; no active product consumer requires row-level mapping.
3. Production legacy rows may still exist; destroying them without identity/slug/date mapping would risk irreversible loss.
4. Config: `cms.legacy_events` → `status: archived`, `drop_schema: false`, `api: retired_410`.

**Future optional work:** Explicit ETL (legacy uuid → `ems_events`) with dry-run mapping report, then drop tables.

## Schema

| Change | Status |
|--------|--------|
| Drop `events` / `event_registrations` | **Not done** (intentional) |
| EMS schema | Untouched |
| New migrations | None |

## Main Website

- `/events` → EMS public pages (unchanged)
- Homepage featured events → EMS (unchanged)
- Regression: FE tests assert `websiteService` has no legacy event methods; public events route uses EMS page

## Systems / CMS metadata

- `config/cms.php` documents `legacy_events` archival policy
- `does_not_own` already lists `legacy_cms_events` / `ems_events`
- Performance probe cached URL list: replaced `/website/events` with `/ems/public/events`
- `PLATFORM_GUIDE.md` updated (CMS no longer lists events admin)

## Tests added / updated

| Test | Coverage |
|------|----------|
| `tests/Feature/Phase9/LegacyCmsEventsRetirementTest.php` | 410 API, EMS authority, manage_events ≠ EMS, EMS ↛ CMS admin, tables retained, no FK from `ems_events` → legacy |
| `Phase6/CrossApplicationIsolationTest` | Expects 410 on legacy website events |
| `frontend/src/__tests__/public-website.spec.ts` | No legacy client methods; EMS public route |

## Remaining technical debt

1. **Schema drop** deferred until archive dump + optional ETL complete.
2. **`manage_events` slug** still exists for media OR-chain / director packs — consider renaming directors onto `manage_media`/`manage_homepage` in a later RBAC cleanup (out of Phase 9 scope).
3. **`AnalyticsService::trackEventRegistration`** remains as a no-caller helper (entity_type `legacy_cms_event`); can remove in analytics cleanup.
4. Deprecated Eloquent models can be deleted after schema drop.

## Definition of Done

| Criterion | Status |
|-----------|--------|
| EMS sole event authority | Yes |
| Main Website consumes EMS | Yes |
| CMS has no active event management | Yes |
| `manage_events` not treated as EMS | Yes |
| Legacy APIs retired (410) | Yes |
| Tables removed or archived with justification | **Archived** (not dropped) |
| No accidental historical data destruction | Yes |
| Boundaries CMS/DAMS/Academy/EMS intact | Yes |
| Documented | This report |

## Verdict

**Legacy CMS event system is fully retired from application use.** Schema remains archived pending a future explicit migration/drop decision.
