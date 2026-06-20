import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useAuthorization } from '../useAuthorization';
import { useAuthStore } from '@/stores/auth';

vi.mock('@/stores/auth', () => ({
  useAuthStore: vi.fn(),
}));

describe('useAuthorization Composable', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should grant everything to super-admin', () => {
    (useAuthStore as any).mockReturnValue({
      roles: ['super-admin'],
      permissions: [],
    });

    const { can, hasRole, hasPermission } = useAuthorization();

    expect(hasRole('admin')).toBe(true);
    expect(hasPermission('manage_users')).toBe(true);
    expect(can('some_random_permission')).toBe(true);
  });

  it('should check exact role memberships for non-super-admins', () => {
    (useAuthStore as any).mockReturnValue({
      roles: ['mentor'],
      permissions: ['manage_volunteers'],
    });

    const { hasRole, hasPermission } = useAuthorization();

    expect(hasRole('mentor')).toBe(true);
    expect(hasRole('admin')).toBe(false);
    expect(hasPermission('manage_volunteers')).toBe(true);
    expect(hasPermission('manage_users')).toBe(false);
  });
});
