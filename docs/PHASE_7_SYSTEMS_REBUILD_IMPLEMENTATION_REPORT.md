# PHASE 7 — SYSTEMS CONTROL PLANE REBUILD

**Repository:** SFU MSA Platform  
**Date:** 2026-08-26  
**Mode:** Build / Refactor / Verify  
**Prior:** Phases 2–6 (application boundaries + isolation)

---

## 1. Executive Summary

The MSA Admin **Systems** section is now an operational **control plane**: one overview, five applications, four platform services, and a separate Security Center entry.

| Delivered | Detail |
|-----------|--------|
| Canonical registry | `config/systems.php` — single source for the five apps |
| Overview API | `GET /api/v1/admin/systems` (+ registry show/health) |
| Real probes | DB / storage / email config / queues / app table reachability |
| Honest status | `operational` \| `degraded` \| `unavailable` \| `unknown` — not fabricated |
| UI | `/admin/systems` overview with cards, filters, Open application |
| Nav | Systems Overview first; app pages gated on `system.view` |
| Incidents | **Not** built as a new subsystem (`incidents_supported: false`) |
| Ownership | Systems = visibility/navigation only |

Per-app detail pages (`/admin/systems/{cms|dams|…}`) and their APIs remain for deep dives. DAMS/CMS health no longer return hardcoded `true` booleans.

---

## 2. Systems Architecture

```text
Systems (/admin/systems)

Applications
├── Main Website          → /
├── Content Management System → /cms
├── Dawah Academy         → /academy
├── Dawah Academy Management System → /dams
└── Event Management System → /ems

Platform Services
├── Queues      → /admin/system/queues
├── Database
├── Email
└── Storage

Security
└── Security Center → /admin/security
```

---

## 3. Registry

Canonical IDs (unchanged from Phase 6):

| id | Name | Launch URL | Admin detail |
|----|------|------------|--------------|
| `main-website` | Main Website | `/` | `/admin/systems/main-website` |
| `cms` | Content Management System | `/cms` | `/admin/systems/cms` |
| `dawah-academy` | Dawah Academy | `/academy` | `/admin/systems/dawah-academy` |
| `dams` | Dawah Academy Management System | `/dams` | `/admin/systems/dams` |
| `ems` | Event Management System | `/ems` | `/admin/systems/ems` |

Each entry includes description, dependencies, owns / does_not_own (boundary hints), version, status, connection_status, last_checked_at, errors.

**Source:** `backend/config/systems.php` only — no second registry.

---

## 4. Health Architecture

`SystemsControlPlaneService` probes:

| Probe | Method | Failure mode |
|-------|--------|--------------|
| Database | PDO + `select 1` | `unavailable` |
| Storage | `Storage::disk('public')->files('')` | `unavailable` |
| Email | Config presence (SMTP host / default mailer) — **no send** | `degraded` / `unknown` / `operational` |
| Queues | Count `jobs` / `failed_jobs` (no payloads) | `degraded` if failed jobs > 0 |
| Auth | Sanctum class + default guard | `degraded` if incomplete |
| App-specific | Lightweight table reachability (announcements / courses / ems_events) | `unavailable` if missing/unreachable |

Results cached (~45s, `SYSTEMS_HEALTH_CACHE_TTL`). Overview supports `?refresh=1`.

UI labels this **Last checked** (server poll), not “heartbeat”.

---

## 5. Connection Status

Per application, `connection_status` maps declared dependencies → probe results, e.g.:

```text
platform-auth → auth probe
database → database probe
storage → storage probe
queues → queues probe
cms-content-apis / ems-event-apis / academy-shared-schema → database probe
```

Application `status` is a rollup of database + app probe + worst dependency connection — distinct from individual connection entries.

---

## 6. Version Detection

| Source | Field |
|--------|-------|
| `APP_VERSION` / `SYSTEMS_PLATFORM_VERSION` | Platform summary |
| `MAIN_WEBSITE_VERSION`, `CMS_VERSION`, `DAWAH_ACADEMY_VERSION`, `DAMS_VERSION`, `EMS_VERSION` | Per-app (defaults in config) |
| `BUILD_ID`, `COMMIT_SHA` | Optional platform metadata (SHA truncated) |

Missing values surface as **`unknown`**, never invented semver.

---

## 7. Heartbeat / Last Check

**Last checked** = timestamp of the control-plane evaluation (cached).

Applications do **not** push heartbeats in Phase 7. The UI copy states this explicitly.

---

## 8. Dependencies (documented)

Aligned with Phase 6 contracts:

| App | Depends on |
|-----|------------|
| Main Website | Platform auth, DB, CMS content APIs, EMS event APIs, storage |
| CMS | Platform auth, DB, storage, queues |
| Dawah Academy | Platform auth, DB, CMS resources, Academy shared schema |
| DAMS | Platform auth, DB, storage, queues, Academy shared schema |
| EMS | Platform auth, DB, storage, queues, email |

---

## 9. Authorization

| Surface | Permission |
|---------|------------|
| Systems overview + registry APIs | `system.view` **or** `admin` / `super-admin` |
| Queues manage link | `view_queue_status` (existing) |
| Security Center link | `view_security` (existing) |
| FE routes `/admin/systems*` | `meta.permissions: system.view` |

No Systems-specific auth. Server-side checks mandatory.

---

## 10. Security

Health/overview responses intentionally omit:

- credentials, tokens, env dumps, connection strings  
- filesystem paths, stack traces, job payloads  

Errors are short severity + message summaries only.

---

## 11. Frontend

| Path | Role |
|------|------|
| `pages/admin/system/SystemsPage.vue` | Overview control plane |
| `components/admin/systems/SystemCard.vue` | Application card + Open / Details |
| `PlatformServiceCard.vue` | Platform service card |
| `SecurityCenterCard.vue` | Security entry |
| `SystemStatusBadge.vue` | Icon + label status (not color-only) |
| `services/admin/systemsControlPlane.ts` | API client |
| `types/systems.ts` | Types |

Filters: All / Applications / Platform Services / Security / status filters.  
One failed app does not break the page (per-card status).

---

## 12. API

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/admin/systems` | Overview (apps + platform + security + summary) |
| GET | `/api/v1/admin/systems/registry/{system}` | Single app from control plane |
| GET | `/api/v1/admin/systems/registry/{system}/health` | App health slice |
| GET | `/api/v1/admin/systems/{cms\|dams\|…}` | **Preserved** per-app detail APIs |
| GET | `/api/v1/admin/systems/{…}/health` | **Preserved** (CMS/DAMS now use real probes) |

Registry paths use `/systems/registry/{system}` to avoid colliding with existing `systems/cms` route groups.

---

## 13. Tests

### New
| Suite | Result |
|-------|--------|
| `SystemsControlPlaneTest` (6) | Passed — auth, five apps, URLs, ownership, no secret leakage, DAMS health honesty |
| `systemsControlPlane.spec.ts` (1) | Passed — overview + five detail routes |

### Regression (passed)
`CmsEngineTest`, `DamsIsolationTest`, `CrossApplicationIsolationTest`, `MainWebsiteSystemTest`, `AcademyAssetOwnershipTest`, `damsRoutes.spec.ts`

**Totals in combined run:** 58 PHPUnit passed; 6 Vitest passed for Systems/DAMS route specs. **0 failures.**

---

## 14. Remaining Limitations

1. Worker process liveness is **`unknown`** (not inspected).  
2. Security Center status is **`unknown`** on overview (link-out; no duplicate security tooling).  
3. No Systems incident model — use Security Center / failed_jobs / existing logs.  
4. Main Website / Dawah Academy / EMS detail pages still use their heavier tab UIs (unchanged).  
5. Email “health” is configuration-based, not delivery proof.  
6. Existing deployments must grant `system.view` (EMS seeder) for non-admin Systems access.  
7. Academy may still be feature-flagged off while Systems correctly lists it as a registered app.

---

## 15. Phase 8 Readiness

Suggested next phase themes:

1. **Systems UX polish** — unify detail pages onto shared SystemDetail component  
2. **Optional push heartbeats** if multi-host deployment needs them  
3. **Worker observability** without exposing payloads  
4. **Incident bridge** — read-only feed from SecurityEvent / failed_jobs (still not a full IM product)  
5. Continue **legacy CMS events retirement** (Phase 6 plan) outside Systems ownership  

---

## 16. Definition of Done

| Criterion | Status |
|-----------|--------|
| Five applications represented | Done |
| Apps vs Platform Services vs Security separated | Done |
| One canonical registry | Done |
| Status not fabricated | Done |
| Safe health checks | Done |
| Connection ≠ app status | Done |
| Version or unknown | Done |
| Last checked semantics | Done |
| Dependencies | Done |
| Open application URLs | Done |
| Errors summarized safely | Done |
| Incidents minimal/absent | Done (`false`) |
| Platform RBAC | Done |
| Page usable when one app fails | Done |
| Phase 2–6 intact / tests green | Done |
| Documentation | This file |

---

## 17. Final Principle

```text
SYSTEMS = operational visibility + navigation
         ≠ ownership of CMS / DAMS / EMS / Academy / identity
```
