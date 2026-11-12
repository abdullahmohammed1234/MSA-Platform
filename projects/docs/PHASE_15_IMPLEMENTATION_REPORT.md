# Phase 15 — Official MSA Branding, Responsive UX & PWA

**Project:** SFU MSA Project Management System  
**Frontend:** https://projects.sfumsa.ca  
**API:** https://api.sfumsa.ca  
**Date:** 2026-11-12

## 1. Architecture & branding audit

| Asset | Authoritative path | PMS usage |
|-------|--------------------|-----------|
| Logo | `frontend/public/logo.webp` (copied to `projects/public/logo.webp`) | Login, navbar, drawer, sidebar footer |
| Favicon | `projects/public/favicon.svg` + `logo.webp` | `index.html` |
| PWA icons | `frontend/public/icons/msa-*` → `projects/public/icons/msa-*` | Manifest + Apple touch |
| Brand colors | Primary `#640c0e`, secondary `#b02e32`, cream `#fffbf4` | CSS theme + chrome |
| Dark logo variants | None in monorepo | N/A |

**Removed / replaced:** Lucide `FolderKanban` gradient mark as application branding; broken `/favicon.ico`; cream `#fbfbf9` (aligned to `#fffbf4`); bottom `MobileNav` (replaced by branded drawer).

## 2. CORS root-cause analysis

In-repo CORS already whitelists `https://projects.sfumsa.ca` via `backend/config/cors.php` (canonical production origins + `PROJECTS_URL`).

**Most likely production failure causes (not missing allow-list code):**

1. Stale `php artisan config:cache` after deploy.
2. Incomplete live `.env` (`PROJECTS_URL` / overridden `SANCTUM_STATEFUL_DOMAINS` without Projects).
3. PMS build without `projects/.env.production` (API calls falling back to same-origin `/api/v1`).
4. Edge/WAF blocking `OPTIONS` before Laravel.
5. Prior `withCredentials: true` on PMS Axios (now `false`, Bearer-only).

**Code hardening shipped:**

- `projects/src/api/client.ts` → `withCredentials: false`
- `msa:production-check` asserts `PROJECTS_URL` + Projects in `cors.allowed_origins`
- Smoke/preflight scripts include Projects CORS OPTIONS checks

## 3. SSO investigation

Single-use 60s audience-bound tickets remain intact (`SsoController`, `PmSsoTicketTest`). PMS reads `#sso_ticket=` (preferred) and still accepts query tickets but **scrubs both from history immediately** after read (`projects/src/stores/auth.ts`). PWA uses the same Sanctum Bearer auth and `app.access:projects` gate—no offline auth bypass.

## 4. Branding implementation

- Official crest on login, navbar, drawer, sidebar
- Manifest / Apple / theme-color `#640c0e`
- Document titles: `… — SFU MSA Project Management System`

## 5–6. Responsive mobile & tablet

- **Desktop `lg+`:** full sidebar
- **Below `lg`:** hamburger + branded `MobileDrawer` (includes Resources)
- Calendar: agenda list `<md`, month grid `md+`
- Kanban: horizontal snap-scroll on small screens; status `<select>` on cards as accessible move control
- Gantt: narrower sticky labels; confined overflow
- Modals: bottom-sheet style on small screens; safe-area padding

## 7. PWA

| Item | Status |
|------|--------|
| Manifest | `public/manifest.webmanifest` (+ `manifest.json`) |
| Icons | 192 / 512 / maskable / apple-touch (`msa-*`) |
| Service worker | `public/sw.js` — shell + static only; API network-only |
| Offline | `public/offline.html` |
| Install | `usePwaInstall` + `PwaInstallBanner` (native + iOS instructions) |
| Updates | `PwaUpdateBanner` + `SKIP_WAITING` |
| Push | **Deferred** — no PMS push backend/subscription lifecycle |

## 8. Security offline policy

**Cached:** shell HTML, static JS/CSS under `/assets/`, icons, logos, offline page, manifest.  
**Never cached:** `/api/*`, `api.sfumsa.ca`, Sanctum, authenticated JSON, tokens, private attachments.

Logout clears `localStorage` tokens (existing). SW does not store private API responses.

## 9. Production deployment instructions

1. Backend host `.env`: set `PROJECTS_URL=https://projects.sfumsa.ca`. If `SANCTUM_STATEFUL_DOMAINS` is set, include `projects.sfumsa.ca`. Avoid `CORS_ALLOWED_ORIGINS=*`.
2. `php artisan config:clear && php artisan config:cache`
3. `php artisan msa:production-check --strict`
4. Build PMS with production env:
   ```bash
   cd projects
   npm ci
   npm run build
   ```
   Confirm `dist` JS contains `https://api.sfumsa.ca/api/v1`.
5. Deploy `projects/dist` to Projects docroot (`projects.sfumsa.ca`).
6. Verify:
   ```bash
   curl -i -X OPTIONS "https://api.sfumsa.ca/api/v1/pm/departments" \
     -H "Origin: https://projects.sfumsa.ca" \
     -H "Access-Control-Request-Method: GET" \
     -H "Access-Control-Request-Headers: authorization,content-type,x-requested-with"
   ```
7. Run `scripts/post-deployment-smoke-test.sh` (now includes Projects + CORS).

## 10. Test matrix

| Area | Local | Staging | Production |
|------|-------|---------|------------|
| Official logo / favicon / cream | Implemented | Pending deploy | Unverified |
| Drawer nav + Resources | Implemented | Pending | Unverified |
| Calendar agenda / Kanban scroll | Implemented | Pending | Unverified |
| Manifest + SW + offline.html | Implemented + `npm run test:pwa` | Pending | Unverified |
| Install / update banners | Implemented | Pending | Unverified |
| Login / SSO (code path) | Unchanged + scrub | Pending | Unverified |
| CORS preflight Projects origin | Tooling ready | Pending | Unverified (ops curl) |
| Private API not cached | SW policy | Pending | Unverified |
| Push notifications | Deferred | N/A | N/A |

### Local automated

```bash
cd projects && npm run test:pwa
cd projects && npm run type-check
cd projects && npm run build
```

### Functional regression (manual after deploy)

Login, SSO, Dashboard, Departments, Projects, Tasks, Assignments, Comments, Attachments, Kanban, Calendar, Timeline/Gantt, Notifications, Settings/access admin (portal), Logout.

## 11. Final security notes

- No wildcard CORS workaround.
- PWA cannot bypass RBAC or authentication.
- Offline mode does not expose prior users’ private data from SW caches.
- Push notifications remain a future enhancement requiring subscription lifecycle, permission UX, and non-sensitive payloads.
