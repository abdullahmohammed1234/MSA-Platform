import { describe, it, expect, vi, beforeEach } from 'vitest';
import { emsGuard } from '../emsGuard';
import { useAuthStore } from '@/stores/auth';
import { useEmsAccessStore } from '@/stores/ems/emsAccess';

vi.mock('@/stores/auth', () => ({ useAuthStore: vi.fn() }));
vi.mock('@/stores/ems/emsAccess', () => ({ useEmsAccessStore: vi.fn() }));

const mockAuth = (token: string | null) => {
  (useAuthStore as any).mockReturnValue({ token });
};

const mockAccess = (permissions: string[]) => {
  const store = {
    permissions,
    hasEmsAccess: permissions.length > 0,
    resolve: vi.fn().mockResolvedValue(null),
    canAny: (required: string[]) => required.some((p) => permissions.includes(p)),
  };

  (useEmsAccessStore as any).mockReturnValue(store);

  return store;
};

const route = (path: string, meta: Record<string, unknown> = {}) =>
  ({ path, fullPath: path, matched: [{ meta }], meta }) as any;

describe('emsGuard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('ignores routes outside the EMS', async () => {
    mockAuth(null);

    await expect(emsGuard(route('/academy/dashboard'))).resolves.toBe(true);
    expect(useEmsAccessStore).not.toHaveBeenCalled();
  });

  it('sends signed-out visitors to the platform login with a return path', async () => {
    mockAuth(null);
    mockAccess([]);

    await expect(emsGuard(route('/ems/events'))).resolves.toEqual({
      name: 'login',
      query: { redirect: '/ems/events' },
    });
  });

  it('does not duplicate the platform login: an existing token is accepted as-is', async () => {
    mockAuth('platform-sanctum-token');
    const access = mockAccess(['events.view']);

    await expect(emsGuard(route('/ems'))).resolves.toBe(true);
    expect(access.resolve).toHaveBeenCalled();
  });

  it('redirects a signed-in user with no EMS grant to the access-denied screen', async () => {
    mockAuth('platform-sanctum-token');
    mockAccess([]);

    await expect(emsGuard(route('/ems/events'))).resolves.toEqual({
      name: 'ems-unauthorized',
      query: { from: '/ems/events' },
    });
  });

  it('blocks a route whose permission the user does not hold', async () => {
    mockAuth('platform-sanctum-token');
    mockAccess(['events.view']);

    const to = route('/ems/categories', { emsPermissions: 'event_categories.view' });

    await expect(emsGuard(to)).resolves.toEqual({
      name: 'ems-unauthorized',
      query: { from: '/ems/categories' },
    });
  });

  it('admits a route when the user holds any one of its permissions', async () => {
    mockAuth('platform-sanctum-token');
    mockAccess(['event_categories.view']);

    const to = route('/ems/categories', {
      emsPermissions: ['event_categories.view', 'system.manage'],
    });

    await expect(emsGuard(to)).resolves.toBe(true);
  });

  it('keeps the access-denied screen reachable so the redirect cannot loop', async () => {
    mockAuth('platform-sanctum-token');
    mockAccess([]);

    const to = route('/ems/unauthorized', { emsPublic: true });
    to.meta.emsPublic = true;

    await expect(emsGuard(to)).resolves.toBe(true);
  });
});
