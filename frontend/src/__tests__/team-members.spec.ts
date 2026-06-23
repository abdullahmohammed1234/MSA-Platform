import { describe, it, expect } from 'vitest';
import { resolveTeamMembers, normalizeTeamMembers, normalizeCmsTeamMember } from '@/utils/teamMembers';
import { toStorableImagePath } from '@/constants/publicAssets';
import { DEFAULT_TEAM_MEMBERS } from '@/data/teamMembers';

describe('resolveTeamMembers', () => {
  it('returns defaults when API payload is empty', () => {
    expect(resolveTeamMembers({ team: [] })).toEqual(DEFAULT_TEAM_MEMBERS);
    expect(resolveTeamMembers(null)).toEqual(DEFAULT_TEAM_MEMBERS);
  });

  it('normalizes API team members', () => {
    const result = normalizeTeamMembers({
      team: [{ name: 'Test User', role: 'President', dept: 'President', img: '/test.webp' }],
    });

    expect(result).toEqual([
      { name: 'Test User', role: 'President', dept: 'President', img: '/test.webp' },
    ]);
  });

  it('falls back when members are missing names', () => {
    expect(resolveTeamMembers({ team: [{ role: 'President' }] })).toEqual(DEFAULT_TEAM_MEMBERS);
  });

  it('normalizes legacy CMS team image paths to /Team/', () => {
    const result = normalizeCmsTeamMember({
      uuid: 'test-uuid',
      name: 'HAMZA',
      role: 'Marketing Lead',
      dept: 'Directors',
      img: '/hamza.webp',
      bio: null,
      email: null,
      linkedin: null,
      display_order: 0,
      status: 'published',
    });

    expect(result.img).toBe('/Team/hamza.webp');
  });

  it('stores uploaded media paths without the API origin', () => {
    expect(toStorableImagePath('http://localhost:8000/storage/uploads/team-photo.webp'))
      .toBe('/storage/uploads/team-photo.webp');
    expect(toStorableImagePath('/Team/hamza.webp')).toBe('/Team/hamza.webp');
    expect(toStorableImagePath('')).toBeNull();
  });
});
