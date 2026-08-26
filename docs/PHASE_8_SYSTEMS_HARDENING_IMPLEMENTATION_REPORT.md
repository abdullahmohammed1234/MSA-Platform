# PHASE 8 — SYSTEMS CONTROL PLANE HARDENING & UX UNIFICATION

**Repository:** SFU MSA Platform  
**Date:** 2026-08-26  
**Mode:** Implementation / hardening  
**Prior:** Phase 7 — Systems Rebuild  

---

## 1. Executive Summary

Phase 8 turned `/admin/systems` into a coherent operational dashboard: **what is running, what is degraded, why, and where to investigate** — without inventing monitoring, alerting, or incident-management products.

| Area | Change |
|------|--------|
| Health explanations | `status_reason` + labeled checks + dependency messages |
| Dependency UX | `dependency_details[]` with human labels and per-dep status |
| Application detail | Unified `SystemApplicationDetailPage` for all five apps (registry-driven) |
| Platform services | Dedicated detail pages + richer queue partition metrics |
| Queues | Partition breakdown (high/default/low + EMS queues); workers remain `unknown` |
| Consoles | EMS / Main Website / Academy heavy UIs moved to `/console` links |
| Incidents | **Still none** (`incidents_supported: false`) |

`config/systems.php` remains the only application registry. Phase 2–7 boundaries and RBAC are unchanged.

---

## 2. Systems Architecture

```text
Systems (/admin/systems)
├── Overview (summary + filters + cards)
├── Applications → /admin/systems/{id}   (unified detail)
│     └── optional /console for EMS, Main Website, Dawah Academy
├── Platform Services → /admin/systems/services/{id}
└── Security → Security Center (link-out)
```

---

## 3. UX Changes

### Overview
- Counts for operational / degraded / unavailable / **unknown**
- Security summary includes `status_reason` pointing to Security Center
- Cards show degradation reasons when not operational
- Platform service cards link to **Investigate** detail pages

### Application detail (unified)
Displays: name, description, status badge, version, URL, last checked, health why/checks/diagnostics, dependency list, owns / does_not_own, Open application, optional operations console.

### Platform service detail
Displays: status, reason, metrics, queue partitions table (for queues), link to existing queue admin when applicable.

### Reusable components
- `SystemStatusBadge`
- `SystemHealthSummary`
- `SystemDependencyList`
- `SystemLastChecked`
- `SystemCard` / `PlatformServiceCard` / `SecurityCenterCard`

---

## 4. Health Probe Changes

Extended `SystemsControlPlaneService`:

| Addition | Purpose |
|----------|---------|
| `status_reason` | Explains rollup from failed/degraded checks |
| `dependency_details` | Label + status + message per dependency |
| Labeled `checks` | Database connection / application data check |
| Queue partitions | Counts per known queue name (no job payloads) |
| Cache key `overview.v2` | Avoid stale Phase 7 payloads |

Still **does not** invent reasons when probes lack data. Still **does not** send mail or write storage files.

---

## 5. Platform Service Observability

| Service | Visibility |
|---------|------------|
| Database | connection + driver (no credentials) |
| Storage | readable probe on public disk |
| Email | configured mailer (no send) |
| Queues | pending / failed / active + partitions; workers `unknown` |

API: `GET /api/v1/admin/systems/services/{service}`

---

## 6. Queue / Worker Observability

Implemented **lightweight** partition stats from existing `jobs` / `failed_jobs` tables for:

`high`, `default`, `low`, plus configured EMS queues (`ems-payments`, `ems-operations`, `ems-notifications`).

**Not implemented:** process-level worker heartbeats, Prometheus, tracing, new daemons.

---

## 7. Security Integration

Unchanged model:

```text
Systems summary (unknown + message) → Open Security Center → existing dashboard
```

No second security implementation. No RBAC changes.

---

## 8. Authorization

| Surface | Auth |
|---------|------|
| Overview / registry / services APIs | `system.view` or admin/super-admin |
| FE `/admin/systems*` | `meta.permissions: system.view` |
| Queue admin link | still `view_queue_status` |
| Security Center | still `view_security` |

No app-local auth or new permission namespaces.

---

## 9. API

| Endpoint | Role |
|----------|------|
| `GET /api/v1/admin/systems` | Overview (enriched) |
| `GET /api/v1/admin/systems/registry/{id}` | Application detail payload |
| `GET /api/v1/admin/systems/registry/{id}/health` | Health slice |
| `GET /api/v1/admin/systems/services/{id}` | **New** platform service detail |
| Existing `systems/{cms\|dams\|…}` | Preserved for compatibility |

---

## 10. Tests

### SystemsControlPlaneTest (9)
Auth, five apps, URLs, ownership, DAMS health honesty, status_reason + dependency_details, queue service (no secrets), **read-only** isolation (CMS/Academy/EMS counts unchanged).

### Vitest `systemsControlPlane.spec.ts`
Unified detail routes + service detail + console routes.

### Regression
`CmsEngineTest`, `DamsIsolationTest`, `CrossApplicationIsolationTest`, `AcademyAssetOwnershipTest` — **green** (57 PHPUnit in combined filter; 7 Vitest).

**Failed:** 0  

---

## 11. Known Limitations

1. Worker process liveness remains **unknown**.  
2. Security status on overview remains **unknown** by design.  
3. Email health is configuration-based only.  
4. No incident/alerting subsystem (explicit non-goal).  
5. Old CMS/DAMS Vue system pages are unused by primary routes (legacy files remain; unified page is canonical).  
6. Academy may still be feature-flagged while registered.

---

## 12. Explicit Confirmation

**No new incident-management subsystem was introduced.**  
`incidents_supported` remains `false`. Systems does not create incident tables, escalation, or alerting.

---

## 13. Definition of Done

| Criterion | Status |
|-----------|--------|
| Coherent operational dashboard | Done |
| Five apps from one registry | Done |
| Platform services separate | Done |
| Real health + explanations | Done |
| Dependencies visible | Done |
| Queue visibility via existing infra | Done |
| Security Center authoritative | Done |
| No domain ownership by Systems | Done |
| Auth/RBAC unchanged | Done |
| Phase 2–7 intact / tests green | Done |
| Documented | This file |

---

## 14. Recommended Next Phase (do not start here)

Stop after Phase 8 as requested. Candidates for a future phase (not started):

1. Optional worker process inspector (if ops requires it)  
2. Read-only bridge of SecurityEvent / failed_jobs summaries into Systems (still not IM)  
3. Legacy CMS events table retirement (Phase 6 plan)  
4. Unify remaining operations consoles onto shared chrome  
5. Production re-seed notes for `system.view` / Phase 6 permissions  

**Phase 8 complete. No further architectural phase initiated.**
