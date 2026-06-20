# SFU MSA Platform - Master QA Validation Checklist

This checklist acts as the official QA sign-off sheet for staging and production deployments. It lists specific criteria that must be verified manually or through automated testing.

---

## 1. Authentication System
- [ ] **Registration**: Validate that registration succeeds, enforces password complexity, and triggers email verification events.
- [ ] **Email Verification**: Ensure unverified users are blocked from protected routes, and that using a valid verification token updates status.
- [ ] **Login & Session**: Verify that valid credentials retrieve a Sanctum token and store details in Pinia; test expiration/auto-logout on unauthorized events.
- [ ] **Password Reset**: Check that the "Forgot Password" link triggers reset emails, and the token-based reset form successfully updates password.

## 2. CMS Engine
- [ ] **Homepage Sections**: Verify that homepage content blocks render correctly and can be updated from the Admin CMS page.
- [ ] **Resource Library**: Test that document attachments and public links can be browsed, filtered by category, and downloaded.
- [ ] **Event Board**: Ensure upcoming events are listed, and public users can register.
- [ ] **Media Library**: Confirm images/files upload correctly, prevent double extensions, assign secure UUID names, and support soft deletes.

## 3. Dawah Academy
- [ ] **Course Catalog**: Verify courses list correctly with difficulty badges, filters, and dynamic search matching.
- [ ] **Course Enrollment**: Check that enrolling in a course initializes user progress to 0% and opens access to modules.
- [ ] **Lesson Progress**: Ensure that reading lessons, completing videos, and marking them complete updates the progress percentage.
- [ ] **Quiz Builder & Attempts**: Verify that quiz timers work, answer selections save, passing/failing updates attempt scores, and attempts constraints are enforced.

## 4. Certification & Achievements
- [ ] **Certificate Templates**: Test template styling rendering (landscape/portrait layouts, logos, signatures, colors).
- [ ] **Eligibility Checks**: Ensure certificates are automatically awarded *only* when the criteria (lesson percentage, passing scores) are fully met.
- [ ] **PDF Generation**: Confirm that background jobs generate high-fidelity PDFs and save them to private storage paths.
- [ ] **Verification**: Validate that scanning a certificate QR code or inputting its unique code on the public route shows a verification status.
- [ ] **Achievements & Badges**: Verify that learning milestones (lessons count, perfect score) trigger badge awards and unlock notifications.

## 5. Notifications & Preferences
- [ ] **In-App Notification Center**: Confirm that real-time and DB-backed notifications show in the navbar dropdown and support "Mark as Read".
- [ ] **User Preferences**: Test the toggle settings for channels (Email, In-App) to verify that unselected channels are not dispatched.
- [ ] **Email Templates**: Validate responsiveness and formatting of HTML templates (Registration, Course Completed, Security alert).

## 6. Centralized Analytics
- [ ] **Session Sync**: Confirm that user activities aggregate under a single UUID session, capturing browser, platform, and pageviews.
- [ ] **Conversion Tracking**: Ensure specific events (quiz passed, course finished) record correct metadata parameters.
- [ ] **Admin Reports**: Test that admins can build custom query filters and export analytics details as PDF or Excel reports.

## 7. Security Hardening (Phase 14)
- [ ] **Security Headers**: Verify that responses include HSTS, CSP, X-Frame-Options, X-Content-Type-Options, and Referrer-Policy headers.
- [ ] **CSRF & CORS**: Verify request blocking on unauthorized cross-origin requests.
- [ ] **Rate Limiting**: Ensure API and login attempts enforce rate limits (429 status code) and log security violations in the database.
- [ ] **Audit Logging**: Confirm that administrative role adjustments, user bans, and failed logins register in the audit logs.

## 8. Performance Optimizations (Phase 15)
- [ ] **Database Queries**: Verify query counts are optimized via eager loading, avoiding N+1 queries on nested lists (e.g. Courses -> Modules -> Lessons).
- [ ] **Route Load Times**: Check that major API routes load in <200ms under standard loads.
- [ ] **Caching Layer**: Validate redis/in-memory cache retrieval for public catalog data and check that admin changes invalidate appropriate caches.

## 9. Mobile & Responsive Layouts
- [ ] **Viewports**: Test on Mobile (375px), Tablet (768px), and Desktop viewports.
- [ ] **Collapsible Layouts**: Confirm sidebars, tables, and forms compress/collapse cleanly without clipping.
- [ ] **Interactive Elements**: Check that touch targets are at least 44x44px for smooth mobile navigation.

## 10. Accessibility (WCAG Compliance)
- [ ] **Keyboard Navigation**: Ensure all interactive elements can be reached via `Tab` and triggered using `Enter` or `Space`.
- [ ] **Focus Management**: Confirm that modal dialogs trap focus and return focus to the trigger element on close.
- [ ] **ARIA Labels**: Verify that images have descriptive `alt` texts, and icon-only buttons define an `aria-label`.
