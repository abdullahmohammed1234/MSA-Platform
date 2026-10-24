import { describe, it, expect } from 'vitest';
import {
  PERMISSION_SYSTEMS,
  SYSTEM_PERMISSION_SLUGS,
  TOTAL_PLATFORM_PERMISSIONS,
  classifyPermissionSystem,
  filterPermissionsByGroup,
  isKnownPermission,
} from '../permissionSystems';
import type { Permission } from '@/types/auth';

describe('Authoritative Platform Permission Inventory & Classification', () => {
  it('contains exactly 149 registered platform permissions across 10 system groups', () => {
    expect(TOTAL_PLATFORM_PERMISSIONS).toBe(149);

    expect(SYSTEM_PERMISSION_SLUGS.ems.length).toBe(55);
    expect(SYSTEM_PERMISSION_SLUGS.cms.length).toBe(7);
    expect(SYSTEM_PERMISSION_SLUGS.store.length).toBe(14);
    expect(SYSTEM_PERMISSION_SLUGS.dms.length).toBe(6);
    expect(SYSTEM_PERMISSION_SLUGS.spms.length).toBe(9);
    expect(SYSTEM_PERMISSION_SLUGS.vms.length).toBe(9);
    expect(SYSTEM_PERMISSION_SLUGS.mlibms.length).toBe(9);
    expect(SYSTEM_PERMISSION_SLUGS.dams.length).toBe(15);
    expect(SYSTEM_PERMISSION_SLUGS.connect.length).toBe(2);
    expect(SYSTEM_PERMISSION_SLUGS.general.length).toBe(23);

    // Sum of all system permissions must equal 149
    const totalSlugs = Object.values(SYSTEM_PERMISSION_SLUGS).reduce((sum, list) => sum + list.length, 0);
    expect(totalSlugs).toBe(149);
  });

  it('classifies every single one of the 149 inventory permissions into its authoritative system', () => {
    for (const [expectedSystem, slugs] of Object.entries(SYSTEM_PERMISSION_SLUGS)) {
      for (const slug of slugs) {
        expect(isKnownPermission(slug)).toBe(true);

        const perm: Pick<Permission, 'slug' | 'module'> = {
          slug,
          module: expectedSystem.toUpperCase(),
        };

        const result = classifyPermissionSystem(perm);
        expect(result).toBe(expectedSystem);
      }
    }
  });

  it('correctly classifies EMS permissions and keeps legacy manage_events in CMS', () => {
    // Standard EMS
    expect(classifyPermissionSystem({ slug: 'events.view', module: 'EMS' })).toBe('ems');
    expect(classifyPermissionSystem({ slug: 'events.publish', module: 'EMS' })).toBe('ems');
    expect(classifyPermissionSystem({ slug: 'check_ins.perform', module: 'EMS' })).toBe('ems');
    expect(classifyPermissionSystem({ slug: 'tickets.create', module: 'EMS' })).toBe('ems');
    expect(classifyPermissionSystem({ slug: 'payments.refund', module: 'EMS' })).toBe('ems');
    expect(classifyPermissionSystem({ slug: 'volunteer.registrations.view', module: 'EMS' })).toBe('ems');
    expect(classifyPermissionSystem({ slug: 'templates.manage', module: 'EMS' })).toBe('ems');
    expect(classifyPermissionSystem({ slug: 'event_series.view', module: 'EMS' })).toBe('ems');

    // Ambiguous name: manage_events is a legacy Website / CMS media-upload OR-chain permission, NOT EMS
    expect(classifyPermissionSystem({ slug: 'manage_events', module: 'Website' })).toBe('cms');
    expect(classifyPermissionSystem({ slug: 'manage_events', module: '' })).toBe('cms');
  });

  it('correctly classifies Store, DMS, SPMS, MLibMS, VMS, DAMS, and Connect permissions', () => {
    expect(classifyPermissionSystem({ slug: 'store.view', module: 'Store' })).toBe('store');
    expect(classifyPermissionSystem({ slug: 'store.orders.refund', module: 'Store' })).toBe('store');

    expect(classifyPermissionSystem({ slug: 'donations.manage', module: 'Donations' })).toBe('dms');
    expect(classifyPermissionSystem({ slug: 'donations.reconcile', module: 'Donations' })).toBe('dms');

    expect(classifyPermissionSystem({ slug: 'sponsorship.view', module: 'Sponsorship' })).toBe('spms');
    expect(classifyPermissionSystem({ slug: 'sponsorship.agreements', module: 'Sponsorship' })).toBe('spms');

    expect(classifyPermissionSystem({ slug: 'library.catalog', module: 'Library' })).toBe('mlibms');
    expect(classifyPermissionSystem({ slug: 'library.loans', module: 'Library' })).toBe('mlibms');

    expect(classifyPermissionSystem({ slug: 'vms.view', module: 'VMS' })).toBe('vms');
    expect(classifyPermissionSystem({ slug: 'vms.manage_shifts', module: 'VMS' })).toBe('vms');

    expect(classifyPermissionSystem({ slug: 'manage_courses', module: 'Academy' })).toBe('dams');
    expect(classifyPermissionSystem({ slug: 'manage_students', module: 'Academy' })).toBe('dams');

    expect(classifyPermissionSystem({ slug: 'connect.view', module: 'Connect' })).toBe('connect');
    expect(classifyPermissionSystem({ slug: 'connect.manage', module: 'Connect' })).toBe('connect');
  });

  it('correctly classifies core platform and operations permissions as General / Platform', () => {
    expect(classifyPermissionSystem({ slug: 'manage_users', module: 'Admin' })).toBe('general');
    expect(classifyPermissionSystem({ slug: 'manage_roles', module: 'Admin' })).toBe('general');
    expect(classifyPermissionSystem({ slug: 'send_notifications', module: 'Admin' })).toBe('general');
    expect(classifyPermissionSystem({ slug: 'view_analytics', module: 'Analytics' })).toBe('general');
    expect(classifyPermissionSystem({ slug: 'manage_queues', module: 'System' })).toBe('general');
    expect(classifyPermissionSystem({ slug: 'view_security', module: 'Security' })).toBe('general');
    expect(classifyPermissionSystem({ slug: 'platform.view', module: 'Platform' })).toBe('general');
    expect(classifyPermissionSystem({ slug: 'platform.health', module: 'Platform' })).toBe('general');
    expect(classifyPermissionSystem({ slug: 'manage_support', module: 'Support' })).toBe('general');
  });

  it('filters entire permission collection by system group with complete exclusion of other systems', () => {
    // Generate mock list of all 149 permissions
    let idCounter = 1;
    const fullMockPermissions: Permission[] = [];
    for (const [sysId, slugs] of Object.entries(SYSTEM_PERMISSION_SLUGS)) {
      for (const slug of slugs) {
        fullMockPermissions.push({
          id: idCounter++,
          uuid: `uuid-${slug}`,
          name: `Perm: ${slug}`,
          slug,
          module: sysId.toUpperCase(),
          description: `Description for ${slug}`,
        });
      }
    }
    expect(fullMockPermissions.length).toBe(149);

    // All Systems view has all 149
    const all = filterPermissionsByGroup(fullMockPermissions, 'all');
    expect(all.length).toBe(149);

    // EMS view has exactly 55 and excludes non-EMS
    const ems = filterPermissionsByGroup(fullMockPermissions, 'ems');
    expect(ems.length).toBe(55);
    expect(ems.every((p) => SYSTEM_PERMISSION_SLUGS.ems.includes(p.slug))).toBe(true);
    expect(ems.some((p) => p.slug === 'manage_events')).toBe(false);

    // CMS view has exactly 7
    const cms = filterPermissionsByGroup(fullMockPermissions, 'cms');
    expect(cms.length).toBe(7);
    expect(cms.some((p) => p.slug === 'manage_events')).toBe(true);

    // Store view has exactly 14
    const store = filterPermissionsByGroup(fullMockPermissions, 'store');
    expect(store.length).toBe(14);

    // DMS view has exactly 6
    const dms = filterPermissionsByGroup(fullMockPermissions, 'dms');
    expect(dms.length).toBe(6);

    // SPMS view has exactly 9
    const spms = filterPermissionsByGroup(fullMockPermissions, 'spms');
    expect(spms.length).toBe(9);

    // VMS view has exactly 9
    const vms = filterPermissionsByGroup(fullMockPermissions, 'vms');
    expect(vms.length).toBe(9);

    // MLibMS view has exactly 9
    const mlibms = filterPermissionsByGroup(fullMockPermissions, 'mlibms');
    expect(mlibms.length).toBe(9);

    // DAMS view has exactly 15
    const dams = filterPermissionsByGroup(fullMockPermissions, 'dams');
    expect(dams.length).toBe(15);

    // Connect view has exactly 2
    const connect = filterPermissionsByGroup(fullMockPermissions, 'connect');
    expect(connect.length).toBe(2);

    // General view has exactly 23
    const general = filterPermissionsByGroup(fullMockPermissions, 'general');
    expect(general.length).toBe(23);
  });

  it('scopes search queries to the currently selected system group', () => {
    const fullMockPermissions: Permission[] = [];
    let idCounter = 1;
    for (const [sysId, slugs] of Object.entries(SYSTEM_PERMISSION_SLUGS)) {
      for (const slug of slugs) {
        fullMockPermissions.push({
          id: idCounter++,
          uuid: `uuid-${slug}`,
          name: `Perm: ${slug}`,
          slug,
          module: sysId.toUpperCase(),
          description: `Description for ${slug}`,
        });
      }
    }

    // Searching 'orders' in Store
    const storeOrders = filterPermissionsByGroup(fullMockPermissions, 'store', 'orders');
    expect(storeOrders.length).toBe(4); // store.manage_orders, store.orders.view, store.orders.update, store.orders.refund
    expect(storeOrders.every((p) => p.slug.startsWith('store.'))).toBe(true);

    // Searching 'refund' in EMS
    const emsRefund = filterPermissionsByGroup(fullMockPermissions, 'ems', 'refund');
    expect(emsRefund.length).toBe(1);
    expect(emsRefund[0].slug).toBe('payments.refund');

    // Searching 'refund' in DMS
    const dmsRefund = filterPermissionsByGroup(fullMockPermissions, 'dms', 'refund');
    expect(dmsRefund.length).toBe(1);
    expect(dmsRefund[0].slug).toBe('donations.refund');
  });

  it('safely classifies completely unknown permissions to general fallback without throwing', () => {
    expect(isKnownPermission('unknown_future_capability')).toBe(false);
    expect(classifyPermissionSystem({ slug: 'unknown_future_capability', module: '' })).toBe('general');
  });
});
