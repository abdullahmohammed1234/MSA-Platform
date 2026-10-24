import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import Permissions from '../Permissions.vue';
import { rolePermissionService } from '@/services/admin/rolePermission.service';
import client from '@/services/api';

vi.mock('@/components/feedback/toast', () => ({
  useToastStore: () => ({
    success: vi.fn(),
    error: vi.fn(),
  }),
}));

vi.mock('@/composables/auth/useAuthorization', () => ({
  useAuthorization: () => ({
    can: () => true,
  }),
}));

const mockPermissions = [
  { id: 1, uuid: 'p1', name: 'EMS: View Events', slug: 'events.view', module: 'EMS', description: 'View events' },
  { id: 2, uuid: 'p2', name: 'EMS: Check-in Attendees', slug: 'check_ins.perform', module: 'EMS', description: 'Perform check-in' },
  { id: 3, uuid: 'p3', name: 'CMS Upload Access', slug: 'manage_events', module: 'Website', description: 'Legacy media upload' },
  { id: 4, uuid: 'p4', name: 'Manage Announcements', slug: 'manage_announcements', module: 'Website', description: 'Announcements' },
  { id: 5, uuid: 'p5', name: 'View Store Admin', slug: 'store.view', module: 'Store', description: 'Store view' },
  { id: 6, uuid: 'p6', name: 'Manage Users', slug: 'manage_users', module: 'Admin', description: 'User administration' },
];

const mockRoles = [
  { id: 1, uuid: 'r1', name: 'Admin', slug: 'admin', description: 'Administrator' },
];

const mockUsers = [
  {
    id: 10,
    uuid: 'u-10',
    name: 'Brother Ahmad',
    email: 'ahmad@example.com',
    roles: ['admin'],
    permissions: ['events.view'],
  },
];

describe('Admin Portal — Permissions.vue Direct Permission Grouping by System', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    vi.spyOn(rolePermissionService, 'getPermissions').mockResolvedValue({
      permissions: mockPermissions as any,
    });
    vi.spyOn(rolePermissionService, 'getRoles').mockResolvedValue({
      roles: mockRoles as any,
    });
    vi.spyOn(rolePermissionService, 'getAuditLogs').mockResolvedValue({
      audit_logs: { data: [], current_page: 1, last_page: 1, total: 0 },
    });
    vi.spyOn(client, 'get').mockImplementation(async (url: string) => {
      if (url === '/admin/users') {
        return { data: { users: mockUsers } };
      }
      return { data: {} };
    });
  });

  it('renders Assigned Permissions Group dropdown when a user is selected', async () => {
    const wrapper = mount(Permissions);
    await flushPromises();

    // Select the user
    const select = wrapper.find('select');
    await select.setValue('u-10');
    await flushPromises();

    // The Assigned Permissions Group dropdown should now be rendered
    const groupSelect = wrapper.find('#assigned-permissions-group-select');
    expect(groupSelect.exists()).toBe(true);

    // Verify option labels contain system groups
    const options = groupSelect.findAll('option');
    const optionTexts = options.map((o) => o.text());

    expect(optionTexts.some((t) => t.includes('All Systems'))).toBe(true);
    expect(optionTexts.some((t) => t.includes('EMS — Event Management System'))).toBe(true);
    expect(optionTexts.some((t) => t.includes('CMS — Content Management System'))).toBe(true);
    expect(optionTexts.some((t) => t.includes('Store — Merchandise & Orders'))).toBe(true);
    expect(optionTexts.some((t) => t.includes('General / Platform'))).toBe(true);
  });

  it('filters visible direct permissions by selected system and preserves unselected groups state', async () => {
    const wrapper = mount(Permissions);
    await flushPromises();

    // Select user
    await wrapper.find('select').setValue('u-10');
    await flushPromises();

    const groupSelect = wrapper.find('#assigned-permissions-group-select');

    // 1. Filter by EMS
    await groupSelect.setValue('ems');
    await flushPromises();

    // Check visible permissions: should contain EMS permissions, but not CMS or Store
    const textAfterEms = wrapper.find('.max-h-\\[380px\\]').text();
    expect(textAfterEms).toContain('EMS: View Events');
    expect(textAfterEms).toContain('events.view');
    expect(textAfterEms).toContain('check_ins.perform');
    expect(textAfterEms).not.toContain('manage_announcements');
    expect(textAfterEms).not.toContain('store.view');

    // events.view should be checked (as Ahmad has it)
    const emsCheckboxes = wrapper.findAll('.max-h-\\[380px\\] input[type="checkbox"]');
    expect((emsCheckboxes[0].element as HTMLInputElement).checked).toBe(true);

    // Toggle check_ins.perform
    await emsCheckboxes[1].setValue(true);
    await flushPromises();

    // 2. Switch to CMS
    await groupSelect.setValue('cms');
    await flushPromises();

    const textAfterCms = wrapper.find('.max-h-\\[380px\\]').text();
    expect(textAfterCms).toContain('manage_announcements');
    expect(textAfterCms).toContain('CMS Upload Access');
    expect(textAfterCms).not.toContain('events.view');

    // Toggle CMS permission
    const cmsCheckboxes = wrapper.findAll('.max-h-\\[380px\\] input[type="checkbox"]');
    await cmsCheckboxes[0].setValue(true);
    await flushPromises();

    // 3. Switch back to EMS
    await groupSelect.setValue('ems');
    await flushPromises();

    // Both events.view and check_ins.perform should STILL be checked!
    const emsCheckboxesReturned = wrapper.findAll('.max-h-\\[380px\\] input[type="checkbox"]');
    expect((emsCheckboxesReturned[0].element as HTMLInputElement).checked).toBe(true);
    expect((emsCheckboxesReturned[1].element as HTMLInputElement).checked).toBe(true);
  });

  it('calls assignUserPermissions with all accumulated user permissions on save', async () => {
    const assignSpy = vi.spyOn(rolePermissionService, 'assignUserPermissions').mockResolvedValue({
      user: {},
    });

    const wrapper = mount(Permissions);
    await flushPromises();

    // Select user
    await wrapper.find('select').setValue('u-10');
    await flushPromises();

    const groupSelect = wrapper.find('#assigned-permissions-group-select');

    // Filter to Store
    await groupSelect.setValue('store');
    await flushPromises();

    // Check store.view
    const storeCheckbox = wrapper.find('.max-h-\\[380px\\] input[type="checkbox"]');
    await storeCheckbox.setValue(true);
    await flushPromises();

    // Click Save Direct Permissions
    const saveButton = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Save Direct Permissions'));
    expect(saveButton).toBeDefined();

    await saveButton?.trigger('click');
    await flushPromises();

    // Verify spy was called with original 'events.view' PLUS the new 'store.view'
    expect(assignSpy).toHaveBeenCalledWith('u-10', expect.arrayContaining(['events.view', 'store.view']));
  });
});
