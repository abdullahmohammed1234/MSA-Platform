# SFU MSA Platform Architecture Documentation (Phase 1)

This document outlines the coding standards, folder structure, responsibilities, conventions, and architectural decisions established for the unified SFU MSA Platform.

---

## 1. Directory Structures

The platform consists of two primary modules:
1. `frontend/` - Vue 3, TypeScript, Vite, Tailwind CSS, Motion for Vue, Pinia, Vue Router
2. `backend/` - Laravel 11/12, Sanctum, MySQL (configured locally with SQLite)

### Frontend Structure (`frontend/src/`)
* **`assets/`**: Uncompiled assets (global logo SVG assets, brand graphics).
* **`components/`**: Divided by category:
  * `ui/`: Lower-level primitive UI inputs (e.g., [Button](file:///d:/projects/msa%20+%20dawah/MSA%20Platform/frontend/src/components/ui/Button.vue), [Input](file:///d:/projects/msa%20+%20dawah/MSA%20Platform/frontend/src/components/ui/Input.vue)).
  * `forms/`: Multi-field form layouts, checkboxes, validation wraps.
  * `navigation/`: Main navigation headers, footers, sidebars.
  * `feedback/`: Loaders, toasts, skeletons, spinners.
  * `data-display/`: Data tables, curriculum checklists, list groups.
  * `website/`, `academy/`, `admin/`: Component blocks specific to respective portals.
  * `shared/`: Generic components used across multiple sections.
* **`design-system/`**: Theme tokens, guidelines, and spring physics [presets](file:///d:/projects/msa%20+%20dawah/MSA%20Platform/frontend/src/design-system/animations/presets.ts).
* **`layouts/`**: Wrappers for layout routes (e.g. `PublicLayout.vue`, `AcademyLayout.vue`).
* **`pages/`**: View entry points corresponding directly to router routes.
* **`router/`**: Modular routing configuration.
* **`services/`**: API handlers, http connection instances.
* **`stores/`**: Pinia state management modules.
* **`types/`**, **`utils/`**: Reusable TypeScript types and helper functions.

### Backend Structure (`backend/`)
* **`app/Http/Controllers/`**: Thin API controllers mapping parameters directly to the Service layer.
* **`app/Services/`**: Encapsulates all application business logic (e.g. `AuthService.php`, `CourseService.php`). Business logic must never live directly in controllers.
* **`app/Repositories/`**: Database interaction queries. Controllers → Services → Repositories.
* **`app/Models/`**: Eloquent models representing database schemas.
* **`app/Policies/`**: Fine-grained authorization controls using Laravel Policies.
* **`app/Traits/`**: Shareable traits (e.g. [HasRolesAndPermissions](file:///d:/projects/msa%20+%20dawah/MSA%20Platform/backend/app/Traits/HasRolesAndPermissions.php) for RBAC).
* **`app/Jobs/`**: Asynchronous queue workers.
* **`app/Notifications/`**: Standard database and email system notifications.

---

## 2. Naming & Coding Conventions

### Frontend Conventions
* **Components**: PascalCase (e.g. `Button.vue`, `TextInput.vue`).
* **Composables**: camelCase prefixed with `use` (e.g. `useCourseProgress.ts`).
* **Stores**: camelCase stores defined in `src/stores/` (e.g. `auth.ts`, `academy.ts`).
* **Routing**: Page lazy loading is enforced using dynamic imports:
  ```typescript
  component: () => import('@/pages/public/Index.vue')
  ```

### Backend Conventions
* **Controllers**: PascalCase ending in `Controller` (e.g. `AuthController.php`).
* **Services**: PascalCase ending in `Service` (e.g. `CourseService.php`).
* **Repositories**: PascalCase ending in `Repository` (e.g. `CourseRepository.php`).
* **Policies**: PascalCase ending in `Policy` (e.g. `UserPolicy.php`).
* **Database Tables**: Lowercase, pluralized snake_case (e.g. `users`, `permission_role`).
* **Foreign Keys**: singular_table_name_id (e.g. `role_id`, `user_id`).

---

## 3. Environment Strategy

Both directories maintain standard environment configurations:
* **Local development**: Loaded from `.env`
* **Staging**: Loaded from `.env.staging` (API target URL `https://staging-api.sfumsa.ca`)
* **Production**: Loaded from `.env.production` (API target URL `https://api.sfumsa.ca`)

---

## 4. Verification and Tooling

* **Frontend**: Code style is verified via ESLint and Prettier.
* **Backend**: Coding standards are enforced via Laravel Pint and PHPUnit tests.
