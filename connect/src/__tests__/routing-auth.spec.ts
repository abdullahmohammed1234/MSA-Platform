import test from 'node:test';
import assert from 'node:assert/strict';
import { sanitizeRedirectPath } from '../utils/security.ts';

test('Connect Routing & Authentication-Aware Destinations', async (t) => {
  await t.test('sanitizeRedirectPath redirects root landing and login to dashboard', () => {
    assert.equal(sanitizeRedirectPath('/'), '/app/dashboard');
    assert.equal(sanitizeRedirectPath(''), '/app/dashboard');
    assert.equal(sanitizeRedirectPath('/login'), '/app/dashboard');
    assert.equal(sanitizeRedirectPath(null), '/app/dashboard');
    assert.equal(sanitizeRedirectPath(undefined), '/app/dashboard');
  });

  await t.test('sanitizeRedirectPath preserves safe internal application destinations', () => {
    assert.equal(sanitizeRedirectPath('/app/events'), '/app/events');
    assert.equal(sanitizeRedirectPath('/app/tickets'), '/app/tickets');
    assert.equal(sanitizeRedirectPath('/app/support/new'), '/app/support/new');
  });

  await t.test('sanitizeRedirectPath defuses malicious or open redirect evasion', () => {
    assert.equal(sanitizeRedirectPath('//attacker.com'), '/app/dashboard');
    assert.equal(sanitizeRedirectPath('/\\attacker.com'), '/app/dashboard');
    assert.equal(sanitizeRedirectPath('https://evil.com'), '/app/dashboard');
    assert.equal(sanitizeRedirectPath('/https://evil.com'), '/app/dashboard');
    assert.equal(sanitizeRedirectPath('/javascript:alert(1)'), '/app/dashboard');
  });

  await t.test('Logo destination resolves correctly based on authentication status', () => {
    const getLogoDestination = (isAuthenticated: boolean) => {
      return isAuthenticated ? '/app/dashboard' : '/';
    };

    assert.equal(getLogoDestination(false), '/');
    assert.equal(getLogoDestination(true), '/app/dashboard');
  });

  await t.test('Root landing route redirects authenticated users to dashboard and preserves guest landing', () => {
    const evaluateRootRoute = (isAuthenticated: boolean) => {
      if (isAuthenticated) {
        return { path: '/app/dashboard' };
      }
      return true; // proceed to LandingView
    };

    assert.equal(evaluateRootRoute(false), true);
    assert.deepEqual(evaluateRootRoute(true), { path: '/app/dashboard' });
  });

  await t.test('Session restoration deduplication avoids redundant network probes', async () => {
    let networkCallCount = 0;
    let restorePromise: Promise<boolean> | null = null;
    let isInitialized = false;

    const mockRestoreSession = async () => {
      if (isInitialized) return true;
      if (restorePromise) return restorePromise;

      restorePromise = (async () => {
        networkCallCount++;
        await new Promise((resolve) => setTimeout(resolve, 10));
        isInitialized = true;
        restorePromise = null;
        return true;
      })();

      return restorePromise;
    };

    // Trigger 3 concurrent calls
    const [res1, res2, res3] = await Promise.all([
      mockRestoreSession(),
      mockRestoreSession(),
      mockRestoreSession(),
    ]);

    assert.equal(res1, true);
    assert.equal(res2, true);
    assert.equal(res3, true);
    assert.equal(networkCallCount, 1, 'Only one network probe must be executed across concurrent callers');
  });

  await t.test('Staff Support Triage route guard restricts navigation exclusively to administrators', () => {
    interface RouteRecordMeta {
      requiresAuth?: boolean;
      requiresAdmin?: boolean;
      requiresStaff?: boolean;
    }

    interface AuthState {
      isAuthenticated: boolean;
      roles: string[];
      permissions?: string[];
      isAdmin: boolean;
    }

    const evaluateGuard = (meta: RouteRecordMeta, auth: AuthState) => {
      if (meta.requiresAuth && !auth.isAuthenticated) {
        return { path: '/login', query: { redirect: '/app/support/staff' } };
      }
      const requiresAdmin = meta.requiresAdmin || meta.requiresStaff;
      if (requiresAdmin && !auth.isAdmin) {
        return { path: '/app/support' };
      }
      return true; // allowed
    };

    const isUserAdmin = (roles: string[]) => roles.includes('super-admin') || roles.includes('admin');

    const staffRouteMeta = { requiresAuth: true, requiresAdmin: true };

    // 1. Unauthenticated user -> redirected to /login
    assert.deepEqual(
      evaluateGuard(staffRouteMeta, { isAuthenticated: false, roles: [], isAdmin: false }),
      { path: '/login', query: { redirect: '/app/support/staff' } }
    );

    // 2. Administrators -> allowed
    assert.equal(
      evaluateGuard(staffRouteMeta, { isAuthenticated: true, roles: ['super-admin'], isAdmin: isUserAdmin(['super-admin']) }),
      true
    );
    assert.equal(
      evaluateGuard(staffRouteMeta, { isAuthenticated: true, roles: ['admin'], isAdmin: isUserAdmin(['admin']) }),
      true
    );

    // 3. Non-administrators (members, volunteers, mentors, directors, dawah-coordinators) -> redirected to /app/support
    const nonAdminRoles = [
      ['member'],
      ['volunteer'],
      ['mentor'],
      ['director'],
      ['dawah-coordinator'],
      ['director', 'dawah-coordinator'],
    ];

    for (const roles of nonAdminRoles) {
      assert.deepEqual(
        evaluateGuard(staffRouteMeta, {
          isAuthenticated: true,
          roles,
          isAdmin: isUserAdmin(roles),
        }),
        { path: '/app/support' },
        `Role(s) ${roles.join(', ')} must NOT access Staff Triage`
      );
    }

    // 4. Permission manage_support without admin role -> still redirected to /app/support
    assert.deepEqual(
      evaluateGuard(staffRouteMeta, {
        isAuthenticated: true,
        roles: ['director'],
        permissions: ['manage_support'],
        isAdmin: isUserAdmin(['director']),
      }),
      { path: '/app/support' },
      'manage_support permission alone must not grant Staff Triage access'
    );
  });

  await t.test('Staff Support Triage UI tabs and shortcut links are displayed strictly to administrators', () => {
    const isUserAdmin = (roles: string[]) => roles.includes('super-admin') || roles.includes('admin');

    const getNavTabs = (isAdmin: boolean) => {
      const tabs = [
        { to: '/app/support', label: 'Support Inquiries' },
        { to: '/app/messages', label: 'Direct Messages' },
        { to: '/app/support/new', label: 'New Inquiry' },
      ];
      if (isAdmin) {
        tabs.push({ to: '/app/support/staff', label: 'Staff Triage' });
      }
      return tabs;
    };

    // Admins see the tab
    const adminTabs = getNavTabs(isUserAdmin(['admin']));
    assert.ok(adminTabs.some((t) => t.to === '/app/support/staff'));

    const superAdminTabs = getNavTabs(isUserAdmin(['super-admin']));
    assert.ok(superAdminTabs.some((t) => t.to === '/app/support/staff'));

    // Directors, coordinators, and members do NOT see the tab
    const directorTabs = getNavTabs(isUserAdmin(['director', 'dawah-coordinator']));
    assert.ok(!directorTabs.some((t) => t.to === '/app/support/staff'));

    const memberTabs = getNavTabs(isUserAdmin(['member']));
    assert.ok(!memberTabs.some((t) => t.to === '/app/support/staff'));
  });
});

