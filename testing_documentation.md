# SFU MSA Platform - Testing & QA Developer Documentation

This document provides a comprehensive overview of the testing strategy, architecture, configurations, and commands for the SFU MSA Platform.

---

## 1. Testing Architecture

The platform's testing framework is layered into frontend, backend, and integration suites:

```text
Testing Architecture
│
├── Frontend (Vue 3, Vitest, Playwright)
│   ├── Component Tests (Rendering, Props, Emits, Slots)
│   ├── Page Tests (Major page views rendering)
│   ├── Router & Guard Tests (Auth, guest, role redirection guards)
│   ├── Pinia Store Tests (State transitions & actions)
│   ├── a11y & Responsiveness (ARIA attributes & viewports check)
│   └── Playwright E2E Tests (Full user registration, student, and admin flows)
│
└── Backend (Laravel, PHPUnit)
    ├── Service Unit Tests (Isolated services using DB/fakes)
    ├── Feature API Integration Tests (Response structure, validation keys)
    ├── Role & Permission Gates (8 roles protection verification)
    ├── Security Audits (CSRF, Rate limits, secure file uploads)
    ├── Queue Processing (Job dispatching, failure, and logging)
    └── Performance Audits (Query logs, API latency, cache invalidation)
```

---

## 2. Frontend Testing

### Unit & Integration (Vitest)
Vitest runs unit tests in a simulated browser environment (`jsdom`).

* **Configuration**: Located in [vite.config.ts](file:///d:/projects/msa%20+%20dawah/MSA%20Platform/frontend/vite.config.ts).
* **Test Directory**: Located in [src/\_\_tests\_\_/](file:///d:/projects/msa%20+%20dawah/MSA%20Platform/frontend/src/__tests__/).
* **Commands**:
  ```bash
  # Run all frontend unit tests
  npm run test:unit
  
  # Run tests with code coverage report
  npx vitest run --coverage
  ```

### End-to-End (Playwright)
Playwright runs browser automation tests across Chromium, Firefox, WebKit, and mobile user-agents.

* **Configuration**: Located in [playwright.config.ts](file:///d:/projects/msa%20+%20dawah/MSA%20Platform/frontend/playwright.config.ts).
* **Test Directory**: Located in [e2e/](file:///d:/projects/msa%20+%20dawah/MSA%20Platform/frontend/e2e/).
* **Commands**:
  ```bash
  # Run all E2E tests (requires dev server running)
  npx playwright test
  
  # Run E2E tests with UI runner
  npx playwright test --ui
  ```

---

## 3. Backend Testing (PHPUnit)

PHPUnit runs tests in isolation. Database tests utilize `RefreshDatabase` to maintain clean states.

* **Configuration**: Located in [phpunit.xml](file:///d:/projects/msa%20+%20dawah/MSA%20Platform/backend/phpunit.xml).
* **Test Directory**: Located in [tests/](file:///d:/projects/msa%20+%20dawah/MSA%20Platform/backend/tests/).
* **Commands**:
  ```bash
  # Run all backend tests
  php artisan test
  
  # Run specific test file
  php artisan test tests/Feature/Authorization/PermissionGateTest.php
  
  # Run with coverage report (requires Xdebug or PCOV)
  php artisan test --coverage
  ```

---

## 4. Test Data & Factories

Laravel Model Factories are used to generate testing datasets:

* **Location**: Located in [database/factories/](file:///d:/projects/msa%20+%20dawah/MSA%20Platform/backend/database/factories/).
* **Usage**:
  ```php
  // Create a single course model
  $course = Course::factory()->create();
  
  // Create a published course
  $publishedCourse = Course::factory()->published()->create();
  
  // Create modular relation structure
  $module = Module::factory()->for($course)->create();
  ```

---

## 5. Security & Performance Audits

* **Security Tests**: Validate XSS Sanitization Service, file double extension bypass blocks, and authentication rate-limiting. Check [SecurityHardeningTest.php](file:///d:/projects/msa%20+%20dawah/MSA%20Platform/backend/tests/Feature/Security/SecurityHardeningTest.php).
* **Performance Tests**: Audit database query logs to prevent N+1 queries, measure API latency, and verify cache hit rates. Check [PerformanceTest.php](file:///d:/projects/msa%20+%20dawah/MSA%20Platform/backend/tests/Feature/PerformanceTest.php).

---

## 6. CI/CD Pipeline Integration

The automated testing pipeline is managed by GitHub Actions:

* **Location**: Located in [.github/workflows/testing.yml](file:///d:/projects/msa%20+%20dawah/MSA%20Platform/.github/workflows/testing.yml).
* **Actions**:
  1. Spawns a MySQL test instance.
  2. Sets up PHP 8.2 and Node 20 environments.
  3. Installs Composer and NPM dependencies.
  4. Runs migrations.
  5. Executes Vitest (Frontend) and PHPUnit (Backend) in parallel.
  6. Blocks staging/production merges if any test fails.
