import { describe, it, expect } from 'vitest';
import { resolveTeamMembers, normalizeTeamMembers, normalizeCmsTeamMember } from '@/utils/teamMembers';
import { toStorableImagePath } from '@/constants/publicAssets';
import { DEFAULT_TEAM_MEMBERS } from '@/data/teamMembers';
import { independentCoordinators, membersWithRoles, vicePresidentMembers, buildOrgBranches } from '@/data/teamOrg';

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

describe('team org chart matching', () => {
  it('places independent coordinators without a lead', () => {
    const independents = independentCoordinators(DEFAULT_TEAM_MEMBERS).map((member) => member.role);

    expect(independents).toEqual([
      'NCCM Coordinator',
      'Prayer Services Coordinator',
      'IT Coordinator',
    ]);
  });

  it('groups finance coordinators under the finance director', () => {
    const finance = membersWithRoles(DEFAULT_TEAM_MEMBERS, ['Director of Finance']);
    const coordinators = membersWithRoles(DEFAULT_TEAM_MEMBERS, [
      'Finance Coordinator',
      'Sponsorship Outreach Coordinator',
      'Logistics Outreach Coordinator',
    ]);

    expect(finance).toHaveLength(1);
    expect(coordinators).toHaveLength(4);
  });

  it('still finds VPs when CMS uses a longer title and the Vice Presidents department', () => {
    const cmsRoster = [
      { name: 'FATIMA HAYAT', role: 'President', dept: 'President', img: '/x.webp' },
      { name: 'HAMMAD ZAIDI', role: 'Vice President of Brothers', dept: 'Vice Presidents', img: '/x.webp' },
      { name: 'HAFSA IRSHAD', role: 'Sisters Vice President', dept: 'Vice Presidents', img: '/x.webp' },
      { name: 'LEEZA ABDULFATAH', role: 'Secretary', dept: 'Secretary', img: '/x.webp' },
    ];

    expect(vicePresidentMembers(cmsRoster).map((member) => member.name)).toEqual([
      'HAMMAD ZAIDI',
      'HAFSA IRSHAD',
    ]);
    expect(membersWithRoles(cmsRoster, ['President']).map((member) => member.name)).toEqual([
      'FATIMA HAYAT',
    ]);
  });

  it('does not list a graphics lead as a graphics designer too', () => {
    const graphics = buildOrgBranches(DEFAULT_TEAM_MEMBERS).find((branch) => branch.id === 'graphics');

    expect(graphics?.leads.map((member) => member.name)).toEqual(['MARYAM MOHSEN']);
    expect(graphics?.coordinators.map((member) => member.name)).toEqual([
      'ESHAAL PATEL',
      'SHARON DEO',
    ]);
  });
});
