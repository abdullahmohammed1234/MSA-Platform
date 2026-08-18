import type { TeamMember } from '@/services/website/websiteService';

export interface OrgBranch {
  id: string;
  label: string;
  leadRoles: string[];
  coordinatorRoles: string[];
}

export const EXEC_ROLES = {
  president: ['President'],
  vicePresidents: ['Brothers VP', 'Sisters VP'],
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

function normalizeRole(role: string): string {
  return role.trim().toLowerCase();
}

export function membersWithRoles(members: TeamMember[], roles: readonly string[]): TeamMember[] {
  const wanted = new Set(roles.map(normalizeRole));
  return members.filter((member) => wanted.has(normalizeRole(member.role)));
}

function collectAssigned(members: TeamMember[]): Set<string> {
  const assigned = new Set<string>();
  const take = (roles: readonly string[]) => {
    for (const member of membersWithRoles(members, roles)) {
      assigned.add(member.name);
    }
  };

  take(EXEC_ROLES.president);
  take(EXEC_ROLES.vicePresidents);
  take(EXEC_ROLES.secretary);

  for (const branch of ORG_BRANCHES) {
    take(branch.leadRoles);
    take(branch.coordinatorRoles);
  }

  take(INDEPENDENT_COORDINATOR_ROLES);
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
