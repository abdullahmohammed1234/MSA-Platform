import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useEmsAccessStore } from '../emsAccess';
import { accessService } from '@/services/ems';
import { useAuthStore } from '@/stores/auth';
import type { EmsCurrentUser } from '@/types/ems';

vi.mock('@/services/ems', async () => {
  const actual = await vi.importActual<typeof import('@/services/ems')>('@/services/ems');

  return { ...actual, accessService: { me: vi.fn(), roles: vi.fn(), permissions: vi.fn() } };
});

vi.mock('@/stores/auth', () => ({ useAuthStore: vi.fn() }));

const profile = (permissions: string[]): EmsCurrentUser => ({
  id: 1,
  uuid: 'user-uuid',
  name: 'Aisha Rahman',
  email: 'aisha@sfu.ca',
  avatar: null,
  is_active: true,
  roles: [{ slug: 'event-organizer', name: 'Event Organizer' }],
  permissions,
  has_ems_access: permissions.length > 0,
  created_at: null,
});

describe('useEmsAccessStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    (useAuthStore as any).mockReturnValue({ token: 'platform-sanctum-token' });
  });

  it('resolves EMS permissions from the API rather than the platform user', async () => {
    (accessService.me as any).mockResolvedValue(profile(['events.view', 'events.create']));

    const store = useEmsAccessStore();
    await store.resolve();

    expect(store.hasEmsAccess).toBe(true);
    expect(store.can('events.create')).toBe(true);
    expect(store.can('events.delete')).toBe(false);
  });

  it('caches the access model and refetches only when forced', async () => {
    (accessService.me as any).mockResolvedValue(profile(['events.view']));

    const store = useEmsAccessStore();
    await store.resolve();
    await store.resolve();

    expect(accessService.me).toHaveBeenCalledTimes(1);

    await store.resolve(true);

    expect(accessService.me).toHaveBeenCalledTimes(2);
  });

  it('does not call the API without a platform session', async () => {
    (useAuthStore as any).mockReturnValue({ token: null });

    const store = useEmsAccessStore();

    await expect(store.resolve()).resolves.toBeNull();
    expect(accessService.me).not.toHaveBeenCalled();
    expect(store.hasEmsAccess).toBe(false);
  });

  it('treats a signed-in user with no EMS permissions as having no access', async () => {
    (accessService.me as any).mockResolvedValue(profile([]));

    const store = useEmsAccessStore();
    await store.resolve();

    expect(store.hasEmsAccess).toBe(false);
    expect(store.canAny(['events.view'])).toBe(false);
  });

  it('requires every permission for canAll and any one for canAny', async () => {
    (accessService.me as any).mockResolvedValue(profile(['events.view', 'events.update']));

    const store = useEmsAccessStore();
    await store.resolve();

    expect(store.canAny(['events.delete', 'events.update'])).toBe(true);
    expect(store.canAll(['events.view', 'events.update'])).toBe(true);
    expect(store.canAll(['events.view', 'events.delete'])).toBe(false);
  });

  it('clears the cached model on reset, e.g. after logout', async () => {
    (accessService.me as any).mockResolvedValue(profile(['events.view']));

    const store = useEmsAccessStore();
    await store.resolve();
    store.reset();

    expect(store.profile).toBeNull();
    expect(store.hasEmsAccess).toBe(false);
  });
});
