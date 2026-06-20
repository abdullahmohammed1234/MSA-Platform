import type { TeamMember } from '@/services/website/websiteService';
import type { TeamMember as CmsTeamMember } from '@/types/cms';
import { DEFAULT_TEAM_MEMBERS } from '@/data/teamMembers';
import { TEAM_FALLBACK_IMAGE, resolvePublicImagePath } from '@/constants/publicAssets';

function extractTeamPayload(payload: unknown): unknown[] {
  if (Array.isArray(payload)) {
    return payload;
  }

  if (!payload || typeof payload !== 'object') {
    return [];
  }

  const record = payload as Record<string, unknown>;

  if (Array.isArray(record.team)) {
    return record.team;
  }

  if (record.data && typeof record.data === 'object') {
    const nested = record.data as Record<string, unknown>;
    if (Array.isArray(nested.team)) {
      return nested.team;
    }
    if (Array.isArray(nested.data)) {
      return nested.data;
    }
  }

  return [];
}

export function normalizeCmsTeamMember(member: CmsTeamMember): CmsTeamMember {
  return {
    ...member,
    img: member.img ? resolvePublicImagePath(member.img) : null,
  };
}

export function normalizeTeamMembers(payload: unknown): TeamMember[] {
  return extractTeamPayload(payload)
    .map((entry) => {
      if (!entry || typeof entry !== 'object') {
        return null;
      }

      const member = entry as Record<string, unknown>;
      const name = String(member.name ?? '').trim();
      if (!name) {
        return null;
      }

      return {
        name,
        role: String(member.role ?? ''),
        dept: String(member.dept ?? member.department ?? 'Coordinators'),
        img: resolvePublicImagePath(String(member.img ?? member.image ?? TEAM_FALLBACK_IMAGE)),
      };
    })
    .filter((member): member is TeamMember => member !== null);
}

export function resolveTeamMembers(payload: unknown): TeamMember[] {
  const normalized = normalizeTeamMembers(payload);
  return normalized.length > 0 ? normalized : [...DEFAULT_TEAM_MEMBERS];
}
