import { describe, it, expect, vi, beforeEach } from 'vitest';
import { permissionGuard } from '../permissionGuard';
import { useAuthStore } from '@/stores/auth';

vi.mock('@/stores/auth', () => ({
  useAuthStore: vi.fn(),
}));

describe('permissionGuard Router Guard', () => {
  let nextMock: any;

  beforeEach(() => {
    vi.clearAllMocks();
    nextMock = vi.fn();
  });

  it('should allow routes without metadata permissions', () => {
    (useAuthStore as any).mockReturnValue({
      isAuthenticated: true,
      roles: ['volunteer'],
      permissions: [],
    });

    const to = { matched: [] } as any;
    const from = {} as any;

    permissionGuard(to, from, nextMock);

    expect(nextMock).toHaveBeenCalledWith();
  });

  it('should redirect unauthorized users to the academy dashboard', () => {
    (useAuthStore as any).mockReturnValue({
      isAuthenticated: true,
      roles: ['volunteer'],
      permissions: [],
    });

    const to = {
      matched: [
        { meta: { permissions: ['manage_courses'] } }
      ]
    } as any;
    const from = {} as any;

    permissionGuard(to, from, nextMock);

    expect(nextMock).toHaveBeenCalledWith({ name: 'academy-dashboard' });
  });

  it('should allow users with the required permissions', () => {
    (useAuthStore as any).mockReturnValue({
      isAuthenticated: true,
      roles: ['dawah-coordinator'],
      permissions: ['manage_courses'],
    });

    const to = {
      matched: [
        { meta: { permissions: ['manage_courses'] } }
      ]
    } as any;
    const from = {} as any;

    permissionGuard(to, from, nextMock);

    expect(nextMock).toHaveBeenCalledWith();
  });

  it('should allow super-admin users regardless of permission listings', () => {
    (useAuthStore as any).mockReturnValue({
      isAuthenticated: true,
      roles: ['super-admin'],
      permissions: [],
    });

    const to = {
      matched: [
        { meta: { permissions: ['manage_users', 'manage_settings'] } }
      ]
    } as any;
    const from = {} as any;

    permissionGuard(to, from, nextMock);

    expect(nextMock).toHaveBeenCalledWith();
  });
});
