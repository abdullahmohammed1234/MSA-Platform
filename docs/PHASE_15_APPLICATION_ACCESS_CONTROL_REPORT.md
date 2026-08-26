# Phase 15 Implementation Report — Application Access Control & RBAC Layer

## 1. System Design Overview

Phase 15 introduces a dedicated **Application Access Control** layer to decouple application-level entry authorization from standard role-based access control (RBAC). 

```
                                  +-----------------------+
                                  |   Application Access  |
                                  |     (Feature Gate)    |
                                  +-----------+-----------+
                                              |
                       +----------------------+----------------------+
                       |                      |                      |
                       v                      v                      v
             [ app.access:cms ]     [ app.access:dams ]     [ app.access:ems ]
                       |                      |                      |
                       +----------------------+----------------------+
                                              |
                                              v
                              +---------------+---------------+
                              |    Centralized RBAC Checks    |
                              |   (permissions: manage_*)     |
                              +-------------------------------+
```

The system behaves as a high-level gate (similar to a license or seat check) that verifies whether a user is allowed to load a specific first-class application:
- **CMS** (Content Management System)
- **DAMS** (Dawah Academy Management System)
- **EMS** (Event Management System)
- **Dawah Academy** (Learner Application)
- **Admin Portal** (Platform Control Plane)

---

## 2. Backend Implementation Details

### A. Database Migration & Schema
We created the migration `2026_08_26_180000_create_application_access_table.php` defining the `application_access` table:

```sql
CREATE TABLE application_access (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    application VARCHAR(50) NOT NULL,
    granted_by INT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY user_application_unique (user_id, application)
);
```

### B. Service & Middleware Layer
- **Service**: [ApplicationAccessService.php](file:///d:/projects/MSA%20Platform/backend/app/Services/ApplicationAccessService.php) handles evaluation (`canAccess`), aggregation (`accessibleApplications`), grants (`grant`), and revokes (`revoke`).
- **Middleware**: [ApplicationAccessMiddleware.php](file:///d:/projects/MSA%20Platform/backend/app/Http/Middleware/ApplicationAccessMiddleware.php) intercepts incoming API requests, throwing standard authentication (`AuthenticationException`) or authorization (`AccessDeniedHttpException`) exceptions if access is not verified.
- **Exceptions**: Exposing exception throwing from the middleware integrates cleanly with [EmsExceptionHandler.php](file:///d:/projects/MSA%20Platform/backend/app/Ems/Support/EmsExceptionHandler.php), securing the API error envelopes and writing audit trail events (e.g. `ems.request.forbidden`) correctly.

### C. Selective Route Isolation
To prevent breaking existing API contracts and to maintain absolute backward compatibility, route prefixes in [api.php](file:///d:/projects/MSA%20Platform/backend/routes/api.php) and [ems.php](file:///d:/projects/MSA%20Platform/backend/routes/ems.php) are wrapped selectively:
- Core Platform admin endpoints `/admin/*` are protected by `app.access:admin-portal`.
- Legacy CMS admin endpoints `/admin/cms/*` are protected by `app.access:cms`.
- Legacy DAMS admin endpoints `/admin/academy/*` are protected by `app.access:dams`.
- Access-resolution endpoints (like `/api/v1/cms/users/me`, `/api/v1/dams/users/me`, `/api/v1/ems/users/me`) are open to any authenticated user to fetch capabilities.

### D. Centralized Audit Trails & Logging
All explicit grants and revokes of application access are logged to the platform's central audit trail:
- Action: `grant_application_access`
- Action: `revoke_application_access`
- Action: `ems.request.forbidden` (on rejected EMS operations)

---

## 3. Frontend Implementation Details

### A. Access Control Composable
- **App Access Composable**: [useAppAccess.ts](file:///d:/projects/MSA%20Platform/frontend/src/composables/auth/useAppAccess.ts) has been upgraded to resolve permissions using the backend-supplied payload flags:
  - `has_cms_access`
  - `has_dams_access`
  - `has_ems_access`
  - `has_admin_portal_access`

### B. Admin Management Dashboard
- **Sidebar Integration**: Registered the route in [admin.ts](file:///d:/projects/MSA%20Platform/frontend/src/router/admin.ts) and added a management navigation option in [AdminLayout.vue](file:///d:/projects/MSA%20Platform/frontend/src/components/layouts/AdminLayout.vue).
- **Control Panel UI**: Created [ApplicationAccess.vue](file:///d:/projects/MSA%20Platform/frontend/src/pages/admin/ApplicationAccess.vue), a high-fidelity control dashboard allowing administrators to search users, view assigned roles, and toggle application access in real time.

---

## 4. Verification & Testing

### A. Backend PHPUnit Suite
We added comprehensive test cases inside [ApplicationAccessTest.php](file:///d:/projects/MSA%20Platform/backend/tests/Feature/Phase15/ApplicationAccessTest.php) verifying all access control permutations.

**All 598 backend tests passed successfully:**
```bash
Tests:    598 passed (3441 assertions)
Duration: 115.28s
```

### B. Frontend Vitest Suite
Updated the client unit test file [navbarAccess.spec.ts](file:///d:/projects/MSA%20Platform/frontend/src/__tests__/navbarAccess.spec.ts).

**All 173 unit tests passed successfully:**
```bash
Test Files  29 passed (29)
     Tests  173 passed (173)
  Duration  25.36s
```

### C. Production Compilation
Executed Vite client bundling to guarantee code structure integrity:
```bash
✓ built in 30.12s
```
