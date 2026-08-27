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

  if (!wantedTokens.every((token) => actualTokens.has(token))) {
    return false;
  }

  // "Lead Graphics Designer" must not also match "Graphics Designer".
  const seniority = new Set(['lead', 'director', 'head', 'chief', 'vp', 'vice', 'president']);
  const extraSeniority = [...actualTokens].some(
    (token) => seniority.has(token) && !wantedTokens.includes(token)
  );

  return !extraSeniority;
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

export function buildOrgBranches(members: TeamMember[]) {
  const branches = ORG_BRANCHES.map((branch) => {
    const leads = membersWithRoles(members, branch.leadRoles);
    const leadNames = new Set(leads.map((lead) => lead.name));
    const coordinators = membersWithRoles(members, branch.coordinatorRoles)
      .filter((member) => !leadNames.has(member.name));

    return {
      ...branch,
      leads,
      coordinators,
    };
  });

  const assignedNames = new Set<string>();
  for (const b of branches) {
    for (const l of b.leads) assignedNames.add(l.name);
    for (const c of b.coordinators) assignedNames.add(c.name);
  }

  const president = membersMatching(members, EXEC_ROLES.president, EXEC_DEPTS.president);
  for (const p of president) assignedNames.add(p.name);

  const vicePres = vicePresidentMembers(members);
  for (const vp of vicePres) assignedNames.add(vp.name);

  const secretary = membersMatching(members, EXEC_ROLES.secretary, EXEC_DEPTS.secretary);
  for (const s of secretary) assignedNames.add(s.name);

  // Find remaining unassigned Directors
  const unassignedDirectors = members.filter((member) => {
    if (assignedNames.has(member.name)) return false;
    const role = member.role.toLowerCase();
    const dept = member.dept.toLowerCase();
    return role.includes('director') || dept.includes('director') || dept === 'directors';
  });

  // Convert each unassigned Director into a dynamic branch card
  const dynamicBranches = unassignedDirectors.map((director) => {
    const label = director.role.toLowerCase() === 'director'
      ? 'Director'
      : director.role.replace(/^(Director\s+of\s+)/i, '');
    const capitalizedLabel = label.charAt(0).toUpperCase() + label.slice(1);
    
    return {
      id: `dynamic-${director.name.toLowerCase().replace(/\s+/g, '-')}`,
      label: capitalizedLabel,
      leadRoles: [director.role],
      coordinatorRoles: [],
      leads: [director],
      coordinators: [],
    };
  });

  return [...branches, ...dynamicBranches].filter(
    (branch) => branch.leads.length > 0 || branch.coordinators.length > 0
  );
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

  for (const branch of buildOrgBranches(members)) {
    take(branch.leads);
    take(branch.coordinators);
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
