import type { TeamMember } from '@/services/website/websiteService';

export interface OrgBranch {
  id: string;
  label: string;
  leadRoles: string[];
  coordinatorRoles: string[];
}

export const EXEC_ROLES = {
  president: ['President'],
  vicePresidents: ['Brothers VP', 'Sisters VP', 'Vice President', 'VP'],
  secretary: ['Secretary'],
} as const;

export const EXEC_DEPTS = {
  president: ['President'],
  vicePresidents: ['Vice Presidents', 'Vice President'],
  secretary: ['Secretary'],
} as const;

export const ORG_BRANCHES: OrgBranch[] = [
  {
    id: 'finance',
    label: 'Finance',
    leadRoles: ['Director of Finance'],
    coordinatorRoles: [
      'Finance Coordinator',
      'Sponsorship Outreach Coordinator',
      'Logistics Outreach Coordinator',
    ],
  },
  {
    id: 'marketing',
    label: 'Marketing',
    leadRoles: ['Marketing Lead'],
    coordinatorRoles: ['Marketing Coordinator'],
  },
  {
    id: 'graphics',
    label: 'Graphics',
    leadRoles: ['Lead Graphics Designer'],
    coordinatorRoles: ['Graphics Designer'],
  },
  {
    id: 'events',
    label: 'Events',
    leadRoles: ['Director of Events'],
    coordinatorRoles: ['Events Coordinator'],
  },
  {
    id: 'education',
    label: 'Education',
    leadRoles: ['Director of Education'],
    coordinatorRoles: ['Education Coordinator'],
  },
];

export const INDEPENDENT_COORDINATOR_ROLES = [
  'NCCM Coordinator',
  'Prayer Services Coordinator',
  'IT Coordinator',
];

function normalize(value: string): string {
  return value
    .trim()
    .toLowerCase()
    .replace(/['’]/g, '')
    .replace(/[_./-]+/g, ' ')
    .replace(/\s+/g, ' ');
}

function tokens(value: string): string[] {
  return normalize(value)
    .replace(/\bvp\b/g, 'vice president')
    .split(' ')
    .filter((token) => token && token !== 'of' && token !== 'the');
}

function looksLikeVicePresident(member: TeamMember): boolean {
  const role = normalize(member.role);
  const dept = normalize(member.dept);

  if (dept === 'vice presidents' || dept === 'vice president') {
    return true;
  }

  return /\bvp\b/.test(role) || role.includes('vice president');
}

function roleFuzzyMatch(memberRole: string, wantedRole: string): boolean {
  const actual = normalize(memberRole);
  const wanted = normalize(wantedRole);

  if (actual === wanted) {
    return true;
  }

  const wantedTokens = tokens(wantedRole);
  const actualTokens = new Set(tokens(memberRole));

  if (wantedTokens.length === 0) {
    return false;
  }

  // "President" must not match "Vice President".
  if (wantedTokens.length === 1) {
    return actualTokens.size === 1 && actualTokens.has(wantedTokens[0]);
  }

  return wantedTokens.every((token) => actualTokens.has(token));
}

export function membersWithRoles(members: TeamMember[], roles: readonly string[]): TeamMember[] {
  return members.filter((member) =>
    roles.some((role) => roleFuzzyMatch(member.role, role))
  );
}

export function membersMatching(
  members: TeamMember[],
  roles: readonly string[],
  departments: readonly string[] = [],
): TeamMember[] {
  const depts = new Set(departments.map(normalize));
  const seen = new Set<string>();
  const matched: TeamMember[] = [];

  for (const member of members) {
    const byDept = depts.has(normalize(member.dept));
    const byRole = roles.some((role) => roleFuzzyMatch(member.role, role));
    if (!byDept && !byRole) {
      continue;
    }
    if (seen.has(member.name)) {
      continue;
    }
    seen.add(member.name);
    matched.push(member);
  }

  return matched;
}

export function vicePresidentMembers(members: TeamMember[]): TeamMember[] {
  const matched = membersMatching(members, EXEC_ROLES.vicePresidents, EXEC_DEPTS.vicePresidents);
  const extras = members.filter(
    (member) => looksLikeVicePresident(member) && !matched.some((entry) => entry.name === member.name)
  );
  return [...matched, ...extras];
}

function collectAssigned(members: TeamMember[]): Set<string> {
  const assigned = new Set<string>();
  const take = (list: TeamMember[]) => {
    for (const member of list) {
      assigned.add(member.name);
    }
  };

  take(membersMatching(members, EXEC_ROLES.president, EXEC_DEPTS.president));
  take(vicePresidentMembers(members));
  take(membersMatching(members, EXEC_ROLES.secretary, EXEC_DEPTS.secretary));

  for (const branch of ORG_BRANCHES) {
    take(membersWithRoles(members, branch.leadRoles));
    take(membersWithRoles(members, branch.coordinatorRoles));
  }

  take(membersWithRoles(members, INDEPENDENT_COORDINATOR_ROLES));
  return assigned;
}

/** Coordinators who are not under a lead and not already listed as independent. */
export function unassignedCoordinators(members: TeamMember[]): TeamMember[] {
  const assigned = collectAssigned(members);
  return members.filter(
    (member) => member.dept === 'Coordinators' && !assigned.has(member.name)
  );
}

export function independentCoordinators(members: TeamMember[]): TeamMember[] {
  const named = membersWithRoles(members, INDEPENDENT_COORDINATOR_ROLES);
  const leftovers = unassignedCoordinators(members);
  const seen = new Set(named.map((member) => member.name));
  return [...named, ...leftovers.filter((member) => !seen.has(member.name))];
}
