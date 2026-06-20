import { describe, it, expect, vi, beforeEach } from 'vitest';
import { roleGuard } from '../roleGuard';
import { useAuthStore } from '@/stores/auth';

vi.mock('@/stores/auth', () => ({
  useAuthStore: vi.fn(),
}));

describe('roleGuard Router Guard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should allow any route if the user is authenticated and is admin', () => {
    (useAuthStore as any).mockReturnValue({
      isAuthenticated: true,
      isPrivilegedAdmin: true,
      isAdmin: true,
      isMentor: false,
      isVolunteer: false,
      roles: ['admin'],
    });

    const to = {
      matched: [
        { meta: { requiresAdmin: true } }
      ]
    } as any;

    expect(roleGuard(to)).toBe(true);
  });

  it('should redirect unauthorized users to the academy dashboard for requiresAdmin routes', () => {
    (useAuthStore as any).mockReturnValue({
      isAuthenticated: true,
      isPrivilegedAdmin: false,
      isAdmin: false,
      isMentor: true,
      isVolunteer: false,
      roles: ['mentor'],
    });

    const to = {
      matched: [
        { meta: { requiresAdmin: true } }
      ]
    } as any;

    expect(roleGuard(to)).toEqual({ name: 'academy-dashboard' });
  });

  it('should allow mentor users for requiresMentor routes', () => {
    (useAuthStore as any).mockReturnValue({
      isAuthenticated: true,
      isPrivilegedAdmin: false,
      isAdmin: false,
      isMentor: true,
      isVolunteer: false,
      roles: ['mentor'],
    });

    const to = {
      matched: [
        { meta: { requiresMentor: true } }
      ]
    } as any;

    expect(roleGuard(to)).toBe(true);
  });

  it('should redirect non-mentor users to the academy dashboard for requiresMentor routes', () => {
    (useAuthStore as any).mockReturnValue({
      isAuthenticated: true,
      isPrivilegedAdmin: false,
      isAdmin: false,
      isMentor: false,
      isVolunteer: true,
      roles: ['volunteer'],
    });

    const to = {
      matched: [
        { meta: { requiresMentor: true } }
      ]
    } as any;

    expect(roleGuard(to)).toEqual({ name: 'academy-dashboard' });
  });

  it('should allow student (volunteer or mentor) users for requiresStudent routes', () => {
    (useAuthStore as any).mockReturnValue({
      isAuthenticated: true,
      isPrivilegedAdmin: false,
      isAdmin: false,
      isMentor: false,
      isVolunteer: true,
      roles: ['volunteer'],
    });

    const to = {
      matched: [
        { meta: { requiresStudent: true } }
      ]
    } as any;

    expect(roleGuard(to)).toBe(true);

    (useAuthStore as any).mockReturnValue({
      isAuthenticated: true,
      isPrivilegedAdmin: false,
      isAdmin: false,
      isMentor: true,
      isVolunteer: false,
      roles: ['mentor'],
    });

    expect(roleGuard(to)).toBe(true);
  });

  it('should redirect unauthenticated users to home for requiresStudent routes', () => {
    (useAuthStore as any).mockReturnValue({
      isAuthenticated: false,
      isPrivilegedAdmin: false,
      isAdmin: false,
      isMentor: false,
      isVolunteer: false,
      roles: [],
    });

    const to = {
      matched: [
        { meta: { requiresStudent: true } }
      ]
    } as any;

    expect(roleGuard(to)).toEqual({ name: 'home' });
  });

  it('should redirect members to home for requiresStudent routes', () => {
    (useAuthStore as any).mockReturnValue({
      isAuthenticated: true,
      isPrivilegedAdmin: false,
      isAdmin: false,
      isMentor: false,
      isVolunteer: false,
      roles: ['member'],
    });

    const to = {
      matched: [
        { meta: { requiresStudent: true } }
      ]
    } as any;

    expect(roleGuard(to)).toEqual({ name: 'home' });
  });

  it('should evaluate legacy roles array from route metadata', () => {
    (useAuthStore as any).mockReturnValue({
      isAuthenticated: true,
      isPrivilegedAdmin: false,
      isAdmin: false,
      isMentor: false,
      isVolunteer: false,
      roles: ['member'],
    });

    const to = {
      matched: [
        { meta: { roles: ['member'] } }
      ]
    } as any;

    expect(roleGuard(to)).toBe(true);

    (useAuthStore as any).mockReturnValue({
      isAuthenticated: true,
      isPrivilegedAdmin: false,
      isAdmin: false,
      isMentor: false,
      isVolunteer: false,
      roles: ['guest'],
    });

    expect(roleGuard(to)).toEqual({ name: 'home' });
  });
});
