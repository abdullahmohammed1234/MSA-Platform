# SFU MSA Digital Platform — Phase 43: Production Deployment Safety, Media Token Security & Migration Integrity Audit Report

**Date:** October 23, 2026  
**Target Environment:** Local Disposable Development & Testing Environment (`D:\projects\MSA Platform`)  
**Lead Auditor / Pair-Programming Agent:** Antigravity Autonomous Pair-Programming Agent  
**Final Release Gate Decision:** **READY FOR SEPARATE DEPLOYMENT REVIEW**

---

## A. Repository Baseline & Environment Verification

### 1. Starting Git Context
- **Repository Root:** `D:\projects\MSA Platform`
- **Active Branch:** `main`
- **Starting Commit:** `881251e97dcc6c217f06d969140b0a22be7e5e4d` ("fixed securty issues")
- **Preservation of Pre-existing Work:** All existing modifications across EMS, Store, Account, Connect, and CMS were inspected and preserved. Zero destructive commands (`git reset --hard`, `git clean -fd`, `migrate:fresh`, or `migrate:refresh`) were executed.

### 2. Discrepancies Between Previous Phase Reports and Repository Reality
1. **Query-String Token Leakage in Phase 42:**
   - *Previous Phase Claim:* Phase 42 report classified query parameter token injection as a "Low" severity convenience fix (`F-04`) to allow `<img>` and `<video>` tags to load protected media.
   - *Repository Reality:* Appending persistent Sanctum tokens (`?token=${localStorage.getItem('auth_token')}`) in `frontend/src/constants/publicAssets.ts` created a significant credential exposure vulnerability. Any user clicking "Copy Link" in `MediaCms.vue` copied their persistent Bearer token to the clipboard; tokens were also exposed to browser history, proxy access logs, and referrer headers.
   - *Remediation in Phase 43:* Completely eliminated `?token=` query parameters from all media URLs and backend controllers. Implemented an authenticated `useAuthenticatedMedia` composable and `<SecureMedia>` component using header-based Bearer authentication and in-memory Blob object URLs.
2. **File Relocation Failure Recovery:**
   - *Previous Phase Claim:* Reported that media audience updates relocated files between public and private disks.
   - *Repository Reality:* If database execution failed after moving the file or deleting the old file, the media asset could be permanently lost with an orphaned database row. Furthermore, updating an already-restricted item that was erroneously on the public disk would not relocate it because `$oldAudience` matched `$newAudience`.
   - *Remediation in Phase 43:* Implemented explicit compensation in `MediaService::update()`: target files are verified on disk before DB write; if the DB transaction fails, the target copy is deleted (compensation), and the source file is preserved. Also created `php artisan cms:audit-media-storage` to audit and relocate any legacy exposed files.
3. **Deployment Configuration (`.cpanel.yml`):**
   - *Previous Phase Claim:* Reported that `.cpanel.yml` excluded `storage/` and deployed safely.
   - *Repository Reality:* Actual `.cpanel.yml` did not exclude `storage/` during backend rsync, risking overwriting production runtime storage (`storage/app/public`, `storage/app/private`, `storage/logs`). Furthermore, if `frontend/dist` or `connect/dist` were missing, the deploy tasks lacked fail-closed validation.
   - *Remediation in Phase 43:* Hardened `.cpanel.yml` with fail-closed build assertions (`test -f .../index.html`) and explicit exclusions for `storage/`, `connect/`, and `.env*`.

---

## B. Audit Findings Classified by Severity

| Finding ID | Severity | Component | Description & Concrete Risk | Corrective Action & Verification |
|---|---|---|---|---|
| **F-01** | **High** | Security / Auth | **Persistent Sanctum Token Exposure in Media URLs:** `publicAssets.ts` appended `?token=...` to CMS media URLs. Tokens leaked to clipboard via "Copy Link", browser history, and server access logs. | Removed `attachToken` from `publicAssets.ts`. Removed `$request->query('token')` from `WebsiteController.php` (lines 62 & 630), `StoreProductResource.php` (line 13), and `StoreCatalogueController.php` (line 64). Built `useAuthenticatedMedia` with in-memory Blob URLs. Tested in `MediaTokenSecurityAndDeploymentSafetyTest`. |
| **F-02** | **Medium** | Backend / Storage | **Uncompensated File Relocation on Database Failure:** If DB update threw an exception during audience relocation, the original file could be deleted or orphaned on the target disk. | Rewrote `MediaService::update()` with 3-step transactional compensation: write target &rarr; DB transaction (with compensation cleanup on catch) &rarr; delete source only after DB commit. Tested in `test_file_relocation_compensates_when_database_fails`. |
| **F-03** | **Medium** | Deployment / cPanel | **Missing Build Guards & Storage Overwrite in `.cpanel.yml`:** Backend sync did not exclude `storage/`, risking overwrite of production storage. Frontend sync lacked build verification, risking deploying empty or broken assets. | Added fail-closed checks (`test -f frontend/dist/index.html` and `test -f connect/dist/index.html`). Added `--exclude='storage/'` and `--exclude='connect*'` to rsync tasks in `.cpanel.yml`. |
| **F-04** | **Medium** | CMS / Storage | **Inability to Audit Legacy Exposed Restricted Assets:** If a legacy record was assigned restricted audience (`brothers`/`sisters`) while its file remained on public disk, no automated audit or dry-run remediation existed. | Created `AuditMediaStorageCommand` (`cms:audit-media-storage {--fix} {--dry-run}`). Tested in `test_media_storage_audit_command_dry_run_and_fix`. |
| **F-05** | **Low** | Streaming / Range | **Video Range Requests & Memory Consumption:** `WebsiteController::streamMedia` loaded entire file into memory using `Storage::get()` and returned 200 without supporting RFC 7233 range requests. | Replaced with `response()->file($fullPath, $headers)` with explicit private cache headers, supporting HTTP 206 Partial Content and native timeline seeking without memory exhaustion. |
| **F-06** | **Informational**| Migration / Rollback | **Audience Migration is Lossy for Legacy NULLs:** `2026_10_23_000100_update_media_audience_canonical_values.php` maps legacy `NULL` &rarr; `both`. Reversing via `down()` maps `brothers` &rarr; `male` and `sisters` &rarr; `female`, but cannot distinguish which `both` records were originally `NULL`. | Documented limitation in migration audit. Added `orWhereNull('audience')` to public query fallback in `WebsiteController::media` as defense in depth. |

---

## C. Token Security & Authenticated Media Architecture

### 1. Complete Elimination of URL Token Exposure
- **Zero URL Tokens:** Neither `frontend/` nor `connect/` appends bearer tokens to query parameters, URL fragments, or HTML attributes.
- **Backend Enforced:** Backend routes and controllers reject query-string tokens. Only RFC 6750 `Authorization: Bearer <token>` headers are accepted for Sanctum authentication:
  - `WebsiteController::media()`: `if (!$viewer && ($token = $request->bearerToken()))`
  - `WebsiteController::streamMedia()`: `if (!$user && ($token = $request->bearerToken()))`
  - `StoreProductResource::toArray()`: `if (!$viewer && ($token = $request->bearerToken()))`
  - `StoreCatalogueController::streamImage()`: `if (!$user && ($token = $request->bearerToken()))`
- **Clipboard Safety:** In `MediaCms.vue`, clicking "Copy URL" copies clean canonical URLs (`https://api.sfumsa.ca/api/v1/website/media/{uuid}/file`) with zero credentials.

### 2. Authenticated Media Loading Implementation
- **Composable:** [useAuthenticatedMedia.ts](file:///D:/projects/MSA%20Platform/frontend/src/composables/useAuthenticatedMedia.ts)
  - Distinguishes public vs protected media using `isProtectedMediaUrl()`.
  - Public assets (`/storage/uploads/cms/...`, static assets) resolve immediately with zero overhead or fetch calls.
  - Protected endpoints (`/api/v1/website/media/{uuid}/file`, `/api/v1/store/products/{uuid}/images/{uuid}`) are requested via Axios with standard `Authorization: Bearer` headers.
  - Generates temporary browser object URLs (`URL.createObjectURL(blob)`).
  - Maintains in-memory session reference counting (`Map<string, { blobUrl: string; refCount: number }>`) to avoid duplicate requests when items re-render.
  - Handles concurrent source changes and cancellations via `AbortController`.
  - Handles 401/403 errors cleanly, setting `isForbidden = true` and `mediaSrc = ''` without credential leakage.
- **Component:** [SecureMedia.vue](file:///D:/projects/MSA%20Platform/frontend/src/components/shared/SecureMedia.vue)
  - Replaces raw `<img>` and `<video>` tags in [MediaCms.vue](file:///D:/projects/MSA%20Platform/frontend/src/pages/admin/cms/MediaCms.vue), [MediaPage.vue](file:///D:/projects/MSA%20Platform/frontend/src/pages/public/MediaPage.vue), and [ImageLightbox.vue](file:///D:/projects/MSA%20Platform/frontend/src/components/shared/ImageLightbox.vue).
  - Renders skeleton pulse during load and clean fallback icons on forbidden/error states.
- **Video Controls & Seeking:**
  - `<video :src="blobUrl">` supports immediate timeline seeking across the buffered Blob in all modern browsers.
  - Backend streaming uses `BinaryFileResponse`, supporting HTTP 206 Range headers.
- **Teardown & Stale Media Prevention:**
  - Global listeners on `auth:logout` and `auth:unauthorized` invoke `clearAuthenticatedMediaCache()`, revoking all active object URLs (`URL.revokeObjectURL`) and clearing the map.
  - `auth.ts` explicitly clears media cache on `logout()` and `setSession()`.
  - Component `onUnmounted` decrements refCount and revokes unreferenced object URLs.

---

## D. Storage Isolation & Migration Integrity

### 1. Physical Storage Disks & Symlinks
- **Configuration:** [backend/config/filesystems.php](file:///D:/projects/MSA%20Platform/backend/config/filesystems.php)
  - Disk `'local'`: Root is `storage_path('app/private')`. **NOT symlinked.** Web server cannot serve files directly.
  - Disk `'public'`: Root is `storage_path('app/public')`. Symlinked to `public/storage`.
- **Restricted Assets:** Stored exclusively under `private/uploads/cms/` on the `'local'` disk. Direct HTTP requests (`/storage/private/...`) return 404 from the web server.
- **Public Assets:** Stored under `uploads/cms/` on the `'public'` disk for high-performance direct web server delivery.

### 2. Transactional File Movement with Compensation
In [MediaService.php](file:///D:/projects/MSA%20Platform/backend/app/Services/CMS/MediaService.php#L105-L175):
1. **Pre-flight & Copy:** File is copied to target disk and verified (`exists()` and `size() > 0`) before touching the database.
2. **Database Transaction:** `repository->update()` is executed inside `DB::transaction()`.
3. **Compensation on Catch:** If the database transaction fails, the target disk copy is immediately deleted (`Storage::disk($targetDisk)->delete($targetPath)`), preventing orphaned files.
4. **Post-Commit Cleanup:** The source disk file is deleted **only after** the database transaction has committed.
5. **Cache Invalidation:** Calls `forgetMediaCaches()` clearing `website_media`, `website_media_public`, `website_media_male`, and `website_media_female`.

### 3. Media Storage Audit Command
- Command: `php artisan cms:audit-media-storage {--fix} {--dry-run}`
- Class: [AuditMediaStorageCommand.php](file:///D:/projects/MSA%20Platform/backend/app/Console/Commands/AuditMediaStorageCommand.php)
- **Dry-run Mode (Default):** Inspects all media records, verifies physical presence on `public` and `local` disks, and flags any restricted media currently exposed on the `public` disk without making mutations.
- **Fix Mode (`--fix`):** Safely relocates exposed restricted assets to `local` private storage and updates database URLs.

### 4. Migration Reversibility Analysis
- Migration: `backend/database/migrations/2026_10_23_000100_update_media_audience_canonical_values.php`
- **`up()` behavior:** Normalizes `male` &rarr; `brothers`, `female` &rarr; `sisters`, and `NULL` / other &rarr; `both`.
- **`down()` behavior:** Reverts `brothers` &rarr; `male`, and `sisters` &rarr; `female`.
- **Reversibility Assessment:** **Partially Reversible / Lossy for NULL values.** Any pre-existing records with `audience = NULL` that were normalized to `both` remain `both` upon rollback. Rollback cannot reconstruct whether a record was originally missing or explicitly public.
- **Rollback Warning:** Running `php artisan migrate:rollback --step=1` rolls back the **entire last batch**, not just a single migration. Operators must verify batch numbers before running rollback commands.

---

## E. cPanel Deployment Configuration Audit

### 1. Actual `.cpanel.yml` Synchronization Analysis
The audited deployment file [.cpanel.yml](file:///D:/projects/MSA%20Platform/.cpanel.yml) now enforces:
```yaml
deployment:
  tasks:
    # 1. Sync backend outside public_html; protect production runtime storage and env
    - /bin/rsync -avz --exclude='node_modules' --exclude='.git' --exclude='.env*' --exclude='tests' --exclude='storage/' --exclude='*.sqlite*' backend/ /home2/sfums1d5/msa-backend/

    # 2. Deploy main frontend directly to public_html with fail-closed build assertion
    - test -f frontend/dist/index.html || { echo "ERROR: frontend/dist/index.html missing! Build frontend before deploying." >&2; exit 1; }
    - /bin/mkdir -p /home2/sfums1d5/public_html
    - /bin/rsync -avz --exclude='.env*' --exclude='*.map' --exclude='*.bak' --exclude='connect*' --exclude='storage*' frontend/dist/ /home2/sfums1d5/public_html/

    # 3. Deploy MSA Connect to connect document root with fail-closed build assertion
    - test -f connect/dist/index.html || { echo "ERROR: connect/dist/index.html missing! Build connect before deploying." >&2; exit 1; }
    - /bin/mkdir -p /home2/sfums1d5/public_html/connect
    - /bin/rsync -avz --exclude='.env*' --exclude='*.map' --exclude='*.bak' connect/dist/ /home2/sfums1d5/public_html/connect/
```

### 2. Deployment Invariant Audits
- **Fail-Closed Protection:** If `frontend/dist/index.html` or `connect/dist/index.html` is missing, deployment aborts immediately before modifying `public_html`.
- **Sub-app Protection:** Main frontend rsync explicitly excludes `connect*`, ensuring that MSA Connect files at `/home2/sfums1d5/public_html/connect/` are preserved.
- **Public Symlink Protection:** Main frontend rsync explicitly excludes `storage*`, ensuring the `public/storage` symlink pointing to public uploads is never removed.
- **Runtime Backend Storage:** Backend rsync explicitly excludes `storage/`, preserving production uploads (`storage/app/public` and `storage/app/private`), logs, and framework sessions.
- **Secrets Protection:** All tasks exclude `.env*`.

---

## F. Complete Verification Matrix

Every command below was directly executed locally on the repository.

| Test / Build Gate | Command Executed | Result / Output Summary | Status |
|---|---|---|---|
| **Phase 43 Token Security & Deployment Tests** | `php artisan test tests/Feature/Phase43` | 7 passed (27 assertions, 2.14s) | **PASS** |
| **Phase 42 Audience Segregation Suite** | `php artisan test tests/Feature/Phase42` | 8 passed (58 assertions, 2.28s) | **PASS** |
| **Phase 41B Feature & Security Suite** | `php artisan test tests/Feature/Phase41B` | 6 passed (56 assertions, 2.13s) | **PASS** |
| **Combined Phase 41B, 42, 43 Verification** | `php artisan test tests/Feature/Phase41B tests/Feature/Phase42 tests/Feature/Phase43` | 21 passed (141 assertions, 9.47s) | **PASS** |
| **Auth, Account, Store Regression Suite** | `php artisan test tests/Feature/Auth tests/Feature/Account tests/Feature/StoreTest.php` | 144 passed (606 assertions, 15.70s) | **PASS** |
| **Storage Audit Command (Dry Run)** | `php artisan cms:audit-media-storage --dry-run` | Exited code 0 (Scanned cleanly, read-only) | **PASS** |
| **Frontend Token Security Unit Tests** | `npx vitest run src/__tests__/authenticatedMedia.spec.ts` | 10 passed (0 warnings, 2.71s) | **PASS** |
| **Full Frontend Vitest Suite** | `npm run test:unit` (in `frontend/`) | 55 test files passed, 321 passed, 1 skipped | **PASS** |
| **Connect Unit Test Suite** | `npm test` (in `connect/`) | 21 test suites passed, 130 passed, 1 skipped | **PASS** |
| **Main Frontend Production Build** | `npm run build` (in `frontend/`) | `vue-tsc -b && vite build` built in 21.07s — 0 errors | **PASS** |
| **MSA Connect Production Build** | `npm run build` (in `connect/`) | `vue-tsc --noEmit && vite build` built in 1.17s — 0 errors | **PASS** |

---

## G. Final Gate Decision

# **READY FOR SEPARATE DEPLOYMENT REVIEW**

### Decision Rationale:
1. **Zero URL Token Exposure:** Persistent Sanctum Bearer tokens have been purged from all frontend URLs, helpers, clipboard copies, and backend controllers.
2. **Physically Private Restricted Media:** Restricted media is segregated on the non-symlinked `'local'` disk, served via authenticated streaming with RFC anti-caching headers.
3. **Fail-Safe File Operations:** Media audience transitions implement verified transactional compensation.
4. **Hardened Deployment Configuration:** `.cpanel.yml` fails closed on missing build outputs and protects production runtime storage and sub-applications.
5. **All Verification Tests Pass:** 21 Phase 41B/42/43 tests, 144 regression tests, 321 frontend tests, 130 Connect tests, and both production builds compiled cleanly with 0 errors.

*Note: This decision certifies local readiness and safety hardening only. It does not authorize or perform live deployment.*

---

## H. Safe Ordered Production Deployment Checklist

The following sequence must be executed by an authorized human operator during a designated maintenance window:

### Step 1: Pre-Deployment Backups (SSH / cPanel Terminal)
```bash
# 1. Create timestamped database backup
mysqldump -u sfums1d5_dbuser -p sfums1d5_msa_db > /home2/sfums1d5/backups/db_backup_pre_phase43_$(date +%Y%m%d_%H%M%S).sql

# 2. Create archive of existing storage (public and private)
tar -czf /home2/sfums1d5/backups/storage_pre_phase43_$(date +%Y%m%d_%H%M%S).tar.gz -C /home2/sfums1d5/msa-backend/storage/app .
```

### Step 2: Deployment Synchronization (cPanel Git Version Control)
1. Deploy updated commit via cPanel Git Version Control or pull from origin.
2. The fail-closed checks in `.cpanel.yml` will ensure `frontend/dist/index.html` and `connect/dist/index.html` are present before synchronizing.

### Step 3: Backend Maintenance & Migration (SSH / cPanel Terminal)
```bash
cd /home2/sfums1d5/msa-backend

# 1. Put application into maintenance mode
php artisan down --message="SFU MSA Platform is upgrading security and media controls."

# 2. Run pending database migrations
php artisan migrate --force

# 3. Audit existing media storage for any legacy exposed restricted assets
php artisan cms:audit-media-storage --dry-run
# If any legacy exposed items are reported, apply safe remediation:
# php artisan cms:audit-media-storage --fix

# 4. Refresh framework caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 5. Restart background queue workers
php artisan queue:restart

# 6. Bring application back online
php artisan up
```

### Step 4: Post-Deployment Smoke Verification
- Verify public site: `https://sfumsa.ca`
- Verify Connect site: `https://sfumsa.ca/connect`
- Verify public media loads in gallery without authentication.
- Verify restricted media returns 403 Forbidden when requested directly by guests.
- Log in as an administrator, upload a test image to CMS, set audience to "Brothers Only", and verify that copying URL copies clean URL without token parameters.
