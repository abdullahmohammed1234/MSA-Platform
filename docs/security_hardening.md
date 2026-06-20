# Platform Security Hardening & Architecture Guide

This developer guide describes the security configurations, reusable services, middlewares, and policies implemented to secure the SFU MSA Platform for production deployment.

---

## 1. Security Architecture Overview

The platform implements a multi-layered defense-in-depth model securing the entry points (middle-tier and edge), controllers, database layers, and background systems:

```text
Request Pipeline
  │
  ├── [Layer 1] Global Security Headers (SecurityHeaders Middleware)
  ├── [Layer 2] Granular Rate Limiters (Throttle Middleware)
  ├── [Layer 3] Stateful SPA & Session Controls (Laravel Sanctum & Cookies)
  ├── [Layer 4] Role-Based Access Control (Policies, Gates, Role/Permission Middleware)
  │
  └── [Layer 5] Mutating Controllers & Request Validation
        ├── Input Sanitization (SanitizationService)
        ├── File Upload Security (FileUploadService)
        └── SQL Parameter Binding (Eloquent / Query Builder)
```

---

## 2. API & Endpoint Protection

### 2.1 Route Guarding & RBAC
Every protected administrative and database mutating endpoint requires three levels of verification:
1. **Authenticated User**: Validated via Sanctum (`auth:sanctum` middleware) or stateful SPA session cookie.
2. **Account Verification**: Users must verify their email address (`verified` middleware) before performing course progress or core administrative activities.
3. **Role & Permission Validation**: Enforced at the routing layer via `permission:permission_slug` middleware and at the controller layer via Eloquent Policies.

### 2.2 Granular Rate Limiting
Granular, named rate limiters are configured in `AppServiceProvider.php` to prevent brute force and denial of service attacks:

| Rate Limiter | Target Endpoints | Limit Threshold | Action on Breach |
| :--- | :--- | :--- | :--- |
| `auth` | Register, Login, Forgot Password, Reset, Verify | 5 requests/min per IP | Returns `429`, logs `rate_limit_violation` (Severity: Low) |
| `public_forms` | Contact, Sponsor, Volunteer Form Mutators | 5 requests/min per IP | Returns `429`, logs `rate_limit_violation` (Severity: Low) |
| `admin_api` | Admin LMS CRUD, CMS settings, Analytics | 60 requests/min per User/IP | Returns `429`, logs `rate_limit_violation` (Severity: Medium) |

---

## 3. Session & CSRF Security

### 3.1 Cookie Settings (`config/session.php` & `config/cors.php`)
For production, the cookies and sessions are hardened:
* **HttpOnly**: Session cookies cannot be read by Javascript, preventing token extraction via XSS (`'http_only' => true`).
* **Secure**: Cookies are only transmitted over TLS/HTTPS connections (`'secure' => true` / `SESSION_SECURE_COOKIE=true`).
* **SameSite**: Set to `'lax'` to mitigate Cross-Site Request Forgery (CSRF).
* **Domain Restrictions**: Stateful API domain configuration constraints in `sanctum.php` restrict SPA access to approved clients.

### 3.2 Token Hashing
Custom email verification tokens are stored using SHA-256 hashing. In case of database read access leaks, valid verification links cannot be inferred.
* Storing: `hash('sha256', $plainToken)`
* Validating: `where('token', hash('sha256', $inputToken))`

---

## 4. Input Sanitization & XSS Mitigation

User-generated content (e.g. rich text editor data for Announcements, Events, Lessons, and User Bios) is sanitized using `SanitizationService`:
* **Sanitize HTML**: Removes `<script>` tags, inline javascript event handlers (`onload`, `onerror`, `onclick`), frames, embedded objects, and malicious protocols (`javascript:`, `data:`, `vbscript:`).
* **Sanitize Plain String**: Striptags and htmlspecialchars encoding.
* **Controller usage**:
```php
$sanitizedContent = app(SanitizationService::class)->sanitizeHtml($request->input('content'));
```

---

## 5. File Upload Security

All files uploaded across the CMS and other systems must pass through `FileUploadService` to prevent file execution exploits:
1. **Size Limits**: Enforces a strict 10MB size ceiling.
2. **Whitelist Validation**: Restricts uploads to explicit MIME types (e.g. `image/jpeg`, `application/pdf`) and matching extensions.
3. **Double Extension Filtering**: Rejects files with multiple extensions (e.g., `backdoor.php.jpg`).
4. **Secure Renaming**: Renames files using random UUIDs (e.g., `45f8-b3d2...jpg`) to completely isolate original user-provided naming schemes and block directory traversal attacks.
5. **Malware Scan Hook**: Includes execution stubs checking for signature anomalies (e.g. EICAR strings) before writing to disk.

---

## 6. Global Security Headers

The `SecurityHeaders` middleware attaches security compliance headers to all HTTP responses (except binary file downloads):

```http
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()
Content-Security-Policy: default-src 'self'; script-src 'self' ...
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
```

---

## 7. Incident Tracking & Auditing

### 7.1 Immutable Audit Logs
Tracks administrative mutations (e.g. role mappings, content deletions, settings changes) recording:
* Target Entity type & Identifier (Polymorphic morphs)
* Description & original payload
* Client IP address & Browser User Agent

### 7.2 Security Incident Logging
Specific security incidents (e.g., failed logins, rate limit breaches, unauthorized endpoint probes) are captured in `security_events` table and duplicated to standard log files:
* Critical warnings write to `Log::critical()`.
* Formatted in structured format (`[SECURITY_EVENT] type=failed_login severity=medium...`) for seamless log forwarders (SIEM) integration.

---

## 8. Incident Investigation & Dashboard

Authorized administrators (holding `view_security` permission) can access `/admin/security` to inspect live platform parameters:
* **SVG Incident Chart**: Line charts visualizing logins vs rate limits trend.
* **Audit Trail**: Feed of administrative adjustments.
* **Incident Alerts**: Realtime display of failed login IPs, rates limit violations, and payload details.
* **System Health Indicators**: Checks app environment variables, debugger warnings, failed queue items, database connectivity, and active sessions.
