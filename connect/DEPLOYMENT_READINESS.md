# SFU MSA Connect — Deployment Readiness & Staging Operations Guide

**Document Version:** 1.0.0  
**Phase:** Phase 5 — Deployment Preparation & Staging Validation  
**Date:** October 2026  
**Application Path:** `connect/`  
**Status:** READY FOR CONTROLLED DEPLOYMENT REVIEW  

> [!CAUTION]
> **NO DEPLOYMENT WAS PERFORMED.**  
> This document details deployment readiness, build configuration, environmental separation, and administrative prerequisites for future controlled rollout. No live server, remote hosting, DNS record, TLS certificate, or production backend setting was modified during this phase.

---

## 1. Architecture & Intended Hostnames

MSA Connect is designed to operate within the unified multi-app ecosystem of the Simon Fraser University Muslim Students Association (SFU MSA):

| System Role | Intended Origin / Hostname | Technology Stack | Hosting & Routing Model |
| :--- | :--- | :--- | :--- |
| **Main Website & Admin Portal** | `https://sfumsa.ca` | Vue 3 + Tailwind CSS | Apache Document Root (`public_html/`) |
| **MSA Connect (Member App)** | `https://connect.sfumsa.ca` | Vue 3 + Tailwind v4 + Pinia | Subdomain Document Root (`connect_html/` or subdomain directory) |
| **Shared Laravel Central API** | `https://api.sfumsa.ca` | Laravel 12 + Sanctum PAT | Dedicated PHP-FPM / Laravel Application Directory (`msa-backend/`) |

### Cross-Origin Architecture
* **Client-to-API Decoupling:** Connect operates on its dedicated subdomain (`connect.sfumsa.ca`) and communicates with the shared Laravel backend API at `https://api.sfumsa.ca/api/v1`.
* **Stateless Personal Access Tokens:** Authentication is mediated exclusively via Laravel Sanctum Bearer tokens stored in `localStorage` under the isolated key `msa_connect_token`. Session cookies are disabled (`withCredentials: false`), avoiding third-party cross-site cookie restrictions (SameSite=Lax/Strict).
* **Strict Origin Boundaries:** Outgoing requests check destination URLs before attaching the Authorization header, preventing credential leakage to third parties.

---

## 2. Required Build Commands

All builds must be executed from the `connect/` directory using Node.js v22+:

```bash
# 1. Install dependencies (deterministic lockfile adherence)
npm ci

# 2. Run automated verification suite
npm test

# 3. Static type and template verification (vue-tsc)
npm run type-check

# 4. Compile staging bundle (resolves .env.staging)
npm run build:staging

# 5. Compile production bundle (resolves .env.production)
npm run build
```

### Build Artifact Details
* **Build tool:** Vite 8 + `@vitejs/plugin-vue` + `@tailwindcss/vite` v4.
* **TypeScript compiler:** `vue-tsc --noEmit` validates all SFC templates and TypeScript source prior to bundling.
* **Output directory:** `connect/dist/`.

---

## 3. Public Environment Variables & Example Configurations

Vite exposes only environment variables prefixed with `VITE_` to client-side bundles. **No secrets or private server credentials belong in client environment files.**

### 3.1 Local Development (`connect/.env.example` / `.env.local`)
```env
# In local development, leaving VITE_API_BASE_URL blank or setting to /api/v1
# directs traffic through Vite's local dev server proxy (http://localhost:5174/api -> http://localhost:8000/api)
VITE_API_BASE_URL=http://localhost:8000/api/v1
VITE_APP_NAME="MSA Connect"
VITE_BASE_PATH=/
```

### 3.2 Staging Build (`connect/.env.staging`)
```env
# Loaded via 'npm run build:staging'
VITE_API_BASE_URL=https://staging-api.sfumsa.ca/api/v1
VITE_APP_NAME="MSA Connect (Staging)"
VITE_BASE_PATH=/
```

### 3.3 Production Build (`connect/.env.production`)
```env
# Loaded via 'npm run build'
VITE_API_BASE_URL=https://api.sfumsa.ca/api/v1
VITE_APP_NAME="MSA Connect"
VITE_BASE_PATH=/
```

### 3.4 Runtime Fail-Safe Validation
In `connect/src/config/api.ts`, the runtime function `validateApiUrl(url, isProduction)` validates the resolved endpoint:
1. Rejects dangerous protocols (`javascript:`, `file:`, `data:`, `ftp:`, `ws:`).
2. Rejects protocol-relative URLs (`//attacker.com`).
3. Enforces HTTPS in production (`isProduction = true`) for all external origins.
4. Fails loudly with an explicit Error rather than silently connecting to insecure or unauthorized endpoints.

---

## 4. Build Output Location & Structure

When bundled, `connect/dist/` contains:

```text
connect/dist/
├── .htaccess                     # Apache SPA rewrite fallback & security headers
├── favicon.svg                   # Brand favicon
├── index.html                    # Root HTML document with PWA tags & Google fonts
├── manifest.webmanifest          # PWA metadata configuration
└── assets/
    ├── index-*.css               # Compiled Tailwind CSS v4 design tokens & styles
    ├── index-*.js                # Core Vue 3 runtime & vendor chunk
    ├── DashboardView-*.js        # Lazy-loaded Dashboard module
    ├── EventsView-*.js           # Lazy-loaded EMS events catalogue
    ├── EventDetailView-*.js      # Lazy-loaded Event detail & registration modal
    ├── MyTicketsView-*.js        # Lazy-loaded Ticket wallet & QR renderer
    ├── NotificationsView-*.js    # Lazy-loaded Notifications & preferences
    ├── ProfileView-*.js          # Lazy-loaded Profile management
    └── [component chunks].js     # Async UI component chunks (Modal, Card, Spinner, Icons)
```

* **Source maps:** Disabled (`sourcemap: false`) in production to prevent leaking raw TypeScript source files, build paths, and dev comments.
* **Deterministic hashes:** All asset bundles include content hashes for aggressive immutable caching (`Cache-Control: public, max-age=31536000, immutable`).

---

## 5. SPA History Mode Fallback & Server Rewrite Requirements

Connect uses HTML5 History Mode (`vue-router` with `createWebHistory()`). Direct browser navigation, bookmarks, and page refreshes on nested routes must be rewritten to `index.html`.

### 5.1 Apache / cPanel Environment (`connect/public/.htaccess`)
Vite automatically copies `connect/public/.htaccess` into `connect/dist/.htaccess`:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>

<IfModule mod_headers.c>
  Header set X-Content-Type-Options "nosniff"
  Header set X-Frame-Options "SAMEORIGIN"
  Header set Referrer-Policy "strict-origin-when-cross-origin"
  Header set Permissions-Policy "camera=(), microphone=(), geolocation=()"
</IfModule>

Options -Indexes
```

### 5.2 Nginx Alternative (for containerized or VPS deployments)
```nginx
server {
    server_name connect.sfumsa.ca;
    root /var/www/msa-connect;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~* \.(?:css|js|svg|png|jpg|webp|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
    }
}
```

---

## 6. HTTPS & CORS Prerequisites (Backend Administrator Actions)

Before deploying Connect to `https://connect.sfumsa.ca`, the central Laravel backend (`https://api.sfumsa.ca`) requires CORS and proxy configuration.

> [!IMPORTANT]
> **No backend changes were made during Phase 5.** The following changes must be performed by the platform administrator in `backend/`:

### 6.1 Backend CORS Configuration (`backend/config/cors.php`)
Ensure `allowed_origins` includes the future Connect domain:

```php
'allowed_origins' => [
    'https://sfumsa.ca',
    'https://connect.sfumsa.ca',          // REQUIRED: Permits Connect origin
    'https://staging.connect.sfumsa.ca',  // Optional: Staging testing origin
],
'allowed_headers' => ['*'],
'allowed_methods' => ['*'],
'exposed_headers' => [],
'max_age' => 86400,
'supports_credentials' => false,          // Connect uses Bearer tokens, not cookies
```

### 6.2 Trusted Proxy Configuration (`backend/app/Http/Middleware/TrustProxies.php`)
If cPanel / Cloudflare / Nginx terminates TLS in front of PHP-FPM, ensure Laravel trusts headers (`X-Forwarded-For`, `X-Forwarded-Proto`, `X-Forwarded-Host`) so that generated links and rate limiters detect HTTPS correctly.

---

## 7. Authentication & API-Origin Security Considerations

### 7.1 Sanctum Bearer Token Security
* **Storage:** `localStorage.getItem('msa_connect_token')`.
* **Prefix Isolation:** Distinct from main portal token keys to prevent namespace collisions.
* **Origin Boundary Interceptor:** `apiClient.interceptors.request` verifies destination origin using `isAllowedApiDestination(url, baseURL)`. If a request targets an unauthorized external origin, the Bearer token is stripped immediately.
* **Automatic Eviction on 401:** When the API returns HTTP 401 Unauthorized, `localStorage` is cleared, the `msa:unauthorized` event fires, and the router safely navigates to `/login?redirect=...`.

### 7.2 Safe Redirect Sanitization
* Direct navigation parameters (`?redirect=...`) pass through `sanitizeRedirectPath()`.
* Rejects open redirect attacks, protocol-relative evasion (`//attacker.com`), backslash path traversal (`/\attacker.com`), control characters, and scheme prefixes (`/https://...`).

### 7.3 Multi-Account State Purge
* Calling `authStore.logout()` or `authStore.purgeAllUserData()` cascades down to:
  * `dashboardStore.clearState()`
  * `ticketsStore.clearState()`
  * `eventsStore.clearState()`
  * `notificationsStore.clearState()`
* This ensures zero in-memory caching of personal registrations, unread counts, or profile data when switching accounts or signing out.

### 7.4 Zero Sensitive Logging
* No tokens, passwords, or PII are logged to `console.log`.

---

## 8. PWA Manifest & Asset-Path Requirements

* **Manifest Location:** `connect/public/manifest.webmanifest` (copied to `dist/manifest.webmanifest`).
* **Display Mode:** `standalone`.
* **Theme Color:** `#640c0e` (SFU MSA deep burgundy).
* **Background Color:** `#fffbf4` (warm parchment cream).
* **Start URL:** `/`.
* **No Offline Service Worker Caching:** Connect **does not** register a service worker that caches tickets, QR images, or authenticated endpoints in persistent browser storage. This guarantees that cancelled or revoked tickets are never rendered offline from a stale cache.

---

## 9. Rollback & Artifact-Retention Recommendations

When deploying to cPanel or a static host:

1. **Artifact Archiving:** Before overwriting the live subdomain root, archive the preceding deployment artifact:
   ```bash
   tar -czf /home2/sfums1d5/backups/connect-$(date +%Y%m%d_%H%M%S).tar.gz -C /home2/sfums1d5/connect_html .
   ```
2. **Atomic Symlink Deployment (Recommended):**
   * Deploy releases to `/home2/sfums1d5/connect-releases/v1.0.0/`
   * Symlink `/home2/sfums1d5/connect_html -> /home2/sfums1d5/connect-releases/v1.0.0/`
   * Rollback is immediate: switch symlink back to previous release directory.
3. **Keep 5 Most Recent Builds:** Maintain 5 previous build tarballs for rapid instant rollback.

---

## 10. Post-Deployment Smoke-Test Checklist

After the administrator deploys `connect/dist/` to `https://connect.sfumsa.ca`, complete this checklist using a synthetic test account:

- [ ] **1. Public Landing:** Visit `https://connect.sfumsa.ca/`. Page renders with brand styling, typography, and "Sign In" button.
- [ ] **2. Direct Route Navigation:** Navigate directly to `https://connect.sfumsa.ca/login`. Verify Apache rewrite loads the login page (no 404).
- [ ] **3. Authentication:** Submit valid test credentials. Verify successful redirect to `/app/dashboard`.
- [ ] **4. Origin Inspection:** Open Browser DevTools Network tab. Verify that all API requests go exclusively to `https://api.sfumsa.ca/api/v1/` and contain `Authorization: Bearer msa_connect_token`.
- [ ] **5. Live Dashboard:** Verify upcoming event spotlight, recent announcements, and quick-action navigation cards render.
- [ ] **6. EMS Events Catalogue:** Navigate to `/app/events`. Verify search, category filter, and event detail view `/app/events/:slug`.
- [ ] **7. Ticket Wallet & QR:** Navigate to `/app/tickets`. Verify active registrations render valid raster PNG QR codes (`data:image/png;base64,iVBOR...`).
- [ ] **8. Calendar Integration:** Click Google / Outlook calendar button on an event. Verify URL opens genuine calendar composer with https protocol.
- [ ] **9. Notifications Center:** Navigate to `/app/notifications`. Test mark-as-read, delete, and notification preference toggles.
- [ ] **10. Nested Route Refresh:** Refresh browser on `/app/tickets`. Page successfully restores session without redirecting to login.
- [ ] **11. Logout & State Wipe:** Click Sign Out. Verify all tickets, notifications, and dashboard state are cleared. Confirm localStorage token is deleted.

---

## 11. Explicit Steps Requiring Administrator Approval

The following items cannot be executed autonomously by the AI agent and require authorized human operator action:

1. **DNS Provisioning:** Create CNAME or A record for `connect.sfumsa.ca` pointing to the web server IP.
2. **TLS / SSL Certificate:** Issue Let's Encrypt or AutoSSL certificate for `connect.sfumsa.ca`.
3. **cPanel Subdomain Setup:** Configure subdomain `connect.sfumsa.ca` with document root (e.g. `/home2/sfums1d5/connect_html`).
4. **Backend CORS Whitelist:** Add `https://connect.sfumsa.ca` to `backend/config/cors.php`.
5. **Deployment Sync:** Transfer `connect/dist/` files to the production subdomain root.
6. **Live Production Smoke Testing:** Verify all checklist items in section 10.

---

## 13. Phase 7 Architecture: Private Member Support, Messaging & Hidden Resources

### A. Database Migrations & Permissions
* **Migration:** `2026_10_11_120000_create_support_inquiries_and_messages_tables.php`
  * `support_inquiries`: Primary inquiries table storing UUID, reference code (`INQ-YYYYMM-XXXX`), member ID, category, priority, status (`open`, `in_progress`, `resolved`, `closed`), read receipts (`is_read_by_member`, `is_read_by_staff`), assigned staff ID, and timestamps.
  * `support_messages`: Message thread entries with foreign key to inquiry, sender user ID, sender name, body text, and `is_staff_reply` flag.
  * **RBAC Permission Seeded:** Automatically registers `manage_support` permission for authorized staff and coordinator triage access.

### B. API Surface & Authentication Boundaries
All endpoints enforce strict Sanctum Bearer token authentication and policy-based authorization (`SupportInquiryPolicy`):
* **Member Endpoints:**
  * `GET /api/v1/support/categories` — Supported inquiry topics and guidance.
  * `GET /api/v1/support/inquiries` — Member's own inquiries only (strict IDOR isolation).
  * `POST /api/v1/support/inquiries` — Submit new inquiry with transaction guarantee.
  * `GET /api/v1/support/inquiries/{reference_code}` — Full conversation thread (marks as read for member).
  * `POST /api/v1/support/inquiries/{reference_code}/messages` — Send follow-up reply in open thread.
  * `POST /api/v1/support/inquiries/{reference_code}/close` — Voluntarily close inquiry.
  * `GET /api/v1/support/resources/status` — Returns `{ enabled: boolean }`.
* **Staff Triage Endpoints (`/api/v1/staff/support`):**
  * Requires `manage_support` permission or `super-admin`/`admin`/`director`/`dawah-coordinator` role.
  * `GET /api/v1/staff/support/inquiries` — Filterable triage feed (status, category, search, pagination).
  * `GET /api/v1/staff/support/inquiries/{reference_code}` — Detail thread view (marks as read for staff).
  * `POST /api/v1/staff/support/inquiries/{reference_code}/messages` — Send staff response and dispatch queued `SupportReplyNotification`.
  * `PATCH /api/v1/staff/support/inquiries/{reference_code}/status` — Transition inquiry status.
  * `PATCH /api/v1/staff/support/inquiries/{reference_code}/assign` — Assign/reassign staff member.
  * `GET /api/v1/staff/support/stats` — Real-time triage KPI counters.

### C. Polling Transport Architecture
* **Rationale:** The backend does not utilize WebSockets or external broadcasting services (e.g. Pusher/Reverb).
* **Transport Strategy:** Connect implements an intelligent, visibility-aware polling transport:
  * **Interval:** 7 seconds during active member thread interaction.
  * **Tab Visibility:** Subscribes to `document.visibilitychange`. Polling immediately pauses when the user switches tabs or minimizes the window (`document.hidden === true`), and triggers an immediate sync and resumes when the window is refocused.
  * **Error Backoff:** Polling halts after 5 consecutive network errors to prevent denial-of-service against failing infrastructure.
  * **Explicit Indication:** Clearly documented as live polling without deceptive websocket claims.

### D. Community Resources Publication Controls
* **Gated by Default:** The Community Resources section is completely hidden from members across all production and staging builds by default.
* **Dual Gate Security Model:**
  1. **Server Gate:** `GET /api/v1/support/resources/status` evaluates `config('features.connect_resources_enabled')` (defaults to `false` in `backend/config/features.php`).
  2. **Client Gate:** `VITE_ENABLE_RESOURCES` environment variable (defaults to `false`).
  3. **Router & Navigation Protection:** If inactive, router guards redirect requests from `/app/resources` to `/app/dashboard`, and all navigation cards and links are hidden.

### E. Store Purge & Session Isolation
* On logout or 401 token invalidation, `useAuthStore().purgeAllUserData()` invokes `useSupportStore().clearState()`, immediately stopping all polling timers, removing visibility event listeners, and wiping cached conversation threads from memory to guarantee zero cross-account leakage.

---

## 14. Verification Statement

**THIS IS A STAGING PREPARATION DOCUMENT. NO LIVE SERVER WAS ACCESSED OR MODIFIED. ALL VALIDATION TO DATE HAS BEEN EXECUTED WITHIN THE LOCAL REPOSITORY TESTBED.**
