import type { Permission } from '@/types/auth';

export interface PermissionSystemGroup {
  id: string;
  name: string;
  shortName: string;
  description: string;
}

export const PERMISSION_SYSTEMS: PermissionSystemGroup[] = [
  {
    id: 'all',
    name: 'All Systems',
    shortName: 'All Systems',
    description: 'Complete collection of platform capabilities across every system.',
  },
  {
    id: 'ems',
    name: 'EMS — Event Management System',
    shortName: 'EMS',
    description: 'Events, attendees, tickets, check-in operations, and event documents.',
  },
  {
    id: 'cms',
    name: 'CMS — Content Management System',
    shortName: 'CMS',
    description: 'Public website pages, announcements, media assets, and team directory.',
  },
  {
    id: 'store',
    name: 'Store — Merchandise & Orders',
    shortName: 'Store',
    description: 'Merchandise catalog, inventory levels, orders, and customer fulfillment.',
  },
  {
    id: 'dms',
    name: 'DMS — Donations Management System',
    shortName: 'DMS',
    description: 'Donations, donor records, financial reports, reconciliations, and refunds.',
  },
  {
    id: 'spms',
    name: 'SPMS — Sponsorship Management System',
    shortName: 'SPMS',
    description: 'Corporate partners, sponsorship tiers, packages, agreements, and deliverables.',
  },
  {
    id: 'vms',
    name: 'VMS — Volunteer Management System',
    shortName: 'VMS',
    description: 'Volunteer opportunities, rosters, team management, shifts, and signups.',
  },
  {
    id: 'mlibms',
    name: 'MLibMS — Library Management System',
    shortName: 'MLibMS',
    description: 'Library book cataloging, physical copy inventory, member loans, and overdue overrides.',
  },
  {
    id: 'dams',
    name: 'DAMS — Dawah Academy Management System',
    shortName: 'DAMS',
    description: 'Academy courses, modules, lessons, quizzes, certificates, and student progress.',
  },
  {
    id: 'connect',
    name: 'MSA Connect',
    shortName: 'Connect',
    description: 'MSA Connect application configuration, member feeds, and health.',
  },
  {
    id: 'general',
    name: 'General / Platform',
    shortName: 'General / Platform',
    description: 'User administration, role security, system background workers, and global analytics.',
  },
];

/**
 * Authoritative complete slug inventories for explicit verification & fallback resiliency.
 */
export const SYSTEM_PERMISSION_SLUGS: Record<string, string[]> = {
  ems: [
    'events.view',
    'events.view_all',
    'events.create',
    'events.update',
    'events.delete',
    'events.publish',
    'events.unpublish',
    'events.open_registration',
    'events.close_registration',
    'events.mark_live',
    'events.complete',
    'events.archive',
    'events.cancel',
    'event_categories.view',
    'event_categories.create',
    'event_categories.update',
    'event_categories.delete',
    'registrations.view',
    'registrations.create',
    'registrations.update',
    'registrations.delete',
    'tickets.view',
    'tickets.create',
    'tickets.update',
    'tickets.delete',
    'check_ins.view',
    'check_ins.perform',
    'check_ins.undo',
    'check_ins.override',
    'imports.view',
    'imports.create',
    'notifications.view',
    'notifications.send',
    'notifications.manage',
    'notification_templates.view',
    'notification_templates.manage',
    'system.view',
    'system.manage',
    'payments.refund',
    'volunteer.registrations.view',
    'volunteer.registrations.update',
    'volunteer.registrations.delete',
    'event_documents.view',
    'event_documents.manage',
    'templates.view',
    'templates.manage',
    'event_series.view',
    'event_series.manage',
    'promo_codes.view',
    'promo_codes.manage',
    'feedback.view',
    'feedback.submit',
    'analytics.view',
    'analytics.view_financial',
    'reports.manage',
  ],
  cms: [
    'manage_events', // Legacy CMS upload permission (Phase 9)
    'manage_announcements',
    'manage_resources',
    'manage_homepage',
    'manage_team',
    'manage_media',
    'manage_featured_opportunities',
  ],
  store: [
    'store.view',
    'store.manage_products',
    'store.manage_inventory',
    'store.manage_orders',
    'store.admin',
    'store.products.view',
    'store.products.create',
    'store.products.update',
    'store.products.delete',
    'store.inventory.view',
    'store.inventory.update',
    'store.orders.view',
    'store.orders.update',
    'store.orders.refund',
  ],
  dms: [
    'donations.view',
    'donations.manage',
    'donations.refund',
    'donations.reports',
    'donations.export',
    'donations.reconcile',
  ],
  spms: [
    'sponsorship.view',
    'sponsorship.create',
    'sponsorship.edit',
    'sponsorship.manage',
    'sponsorship.agreements',
    'sponsorship.payments',
    'sponsorship.fulfillment',
    'sponsorship.export',
    'sponsorship.admin',
  ],
  vms: [
    'vms.view',
    'vms.create',
    'vms.update',
    'vms.delete',
    'vms.manage',
    'vms.manage_teams',
    'vms.manage_shifts',
    'vms.manage_signups',
    'vms.export',
  ],
  mlibms: [
    'library.view',
    'library.catalog',
    'library.copies',
    'library.members',
    'library.loans',
    'library.reservations',
    'library.reports',
    'library.settings',
    'library.admin',
  ],
  dams: [
    'manage_courses',
    'manage_modules',
    'manage_lessons',
    'manage_quizzes',
    'manage_certificates',
    'manage_volunteers',
    'manage_mentors',
    'manage_students',
    'manage_learning_paths',
    'view_progress',
    'manage_progress',
    'manage_discussions',
    'manage_certificate_templates',
    'manage_achievements',
    'manage_badges',
  ],
  connect: [
    'connect.view',
    'connect.manage',
  ],
  general: [
    'manage_users',
    'manage_roles',
    'manage_permissions',
    'manage_settings',
    'send_notifications',
    'manage_notifications',
    'manage_notification_templates',
    'view_analytics',
    'view_reports',
    'manage_analytics',
    'export_analytics',
    'manage_queues',
    'view_queue_status',
    'retry_failed_jobs',
    'manage_scheduler',
    'view_security',
    'manage_security',
    'platform.view',
    'platform.health',
    'platform.audit',
    'platform.alerts',
    'platform.operations',
    'manage_support',
  ],
};

// Total count of all authoritative registered permissions in the platform
export const TOTAL_PLATFORM_PERMISSIONS = Object.values(SYSTEM_PERMISSION_SLUGS).reduce(
  (acc, slugs) => acc + slugs.length,
  0
); // Exactly 149

/**
 * Checks whether a permission slug is known in the authoritative registry.
 */
export function isKnownPermission(slug: string): boolean {
  const cleanSlug = slug.trim().toLowerCase();
  return Object.values(SYSTEM_PERMISSION_SLUGS).some((slugs) => slugs.includes(cleanSlug));
}

/**
 * Authoritatively classify a permission into its corresponding system group.
 * Follows strongest source-of-truth:
 * 1. Authoritative module identifier from the backend registry
 * 2. Explicit slug lookup in the system registry
 * 3. Namespace prefix conventions
 * 4. Fallback to 'general' for new / unmapped capabilities
 */
export function classifyPermissionSystem(perm: Pick<Permission, 'slug' | 'module'>): string {
  const mod = (perm.module || '').trim().toLowerCase();
  const slug = (perm.slug || '').trim().toLowerCase();

  // Explicit slug override:
  // manage_events is a legacy Website / CMS media-upload OR-chain permission, NOT EMS.
  if (slug === 'manage_events') {
    return 'cms';
  }

  // 1. Direct registry lookup
  for (const [sysId, slugs] of Object.entries(SYSTEM_PERMISSION_SLUGS)) {
    if (slugs.includes(slug)) {
      // Disambiguate EMS vs General where slugs may share a generic word
      if (sysId === 'ems' && (slug === 'analytics.view' || slug === 'analytics.view_financial' || slug === 'reports.manage' || slug === 'system.view' || slug === 'system.manage' || slug.startsWith('notifications.'))) {
        // If module is explicitly General/Admin/System/Analytics, respect it
        if (mod === 'analytics' || mod === 'admin' || mod === 'system') {
          return 'general';
        }
        return 'ems';
      }
      return sysId;
    }
  }

  // 2. Module matching
  if (mod === 'ems') return 'ems';
  if (mod === 'website' || mod === 'cms') return 'cms';
  if (mod === 'store') return 'store';
  if (mod === 'donations' || mod === 'dms') return 'dms';
  if (mod === 'sponsorship' || mod === 'spms') return 'spms';
  if (mod === 'library' || mod === 'mlibms') return 'mlibms';
  if (mod === 'vms') return 'vms';
  if (mod === 'academy' || mod === 'dams') return 'dams';
  if (mod === 'connect') return 'connect';

  // 3. Namespace prefix matching
  if (
    slug.startsWith('events.') ||
    slug.startsWith('event_categories.') ||
    slug.startsWith('registrations.') ||
    slug.startsWith('tickets.') ||
    slug.startsWith('check_ins.') ||
    slug.startsWith('imports.') ||
    slug.startsWith('event_documents.') ||
    slug.startsWith('volunteer.registrations.')
  ) {
    return 'ems';
  }

  if (slug.startsWith('store.')) return 'store';
  if (slug.startsWith('donations.')) return 'dms';
  if (slug.startsWith('sponsorship.')) return 'spms';
  if (slug.startsWith('library.')) return 'mlibms';
  if (slug.startsWith('vms.')) return 'vms';
  if (slug.startsWith('connect.')) return 'connect';
  if (slug.startsWith('cms.')) return 'cms';

  // 4. Default fallback to general platform administration
  return 'general';
}

/**
 * Filter permissions based on the active system group filter and an optional search query.
 */
export function filterPermissionsByGroup(
  permissions: Permission[],
  groupId: string,
  searchQuery = ''
): Permission[] {
  const query = searchQuery.trim().toLowerCase();

  return permissions.filter((perm) => {
    // 1. Group filter
    if (groupId !== 'all') {
      const assignedGroup = classifyPermissionSystem(perm);
      if (assignedGroup !== groupId) {
        return false;
      }
    }

    // 2. Search query filter
    if (query) {
      const name = (perm.name || '').toLowerCase();
      const slug = (perm.slug || '').toLowerCase();
      const desc = (perm.description || '').toLowerCase();
      const mod = (perm.module || '').toLowerCase();

      return (
        name.includes(query) ||
        slug.includes(query) ||
        desc.includes(query) ||
        mod.includes(query)
      );
    }

    return true;
  });
}
