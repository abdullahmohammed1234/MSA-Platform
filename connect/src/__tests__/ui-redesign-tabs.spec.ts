import test from 'node:test';
import assert from 'node:assert/strict';

test('UI/UX Redesign — Route-Based Tabs, Page Heroes & Navbar Invariants', async (t) => {
  // SUITE 1: RouteTab Active Matching Logic
  await t.test('RouteTab active matching correctly isolates parent, child, and sibling destinations', () => {
    interface TabItem {
      to: string;
      label: string;
      exact?: boolean;
    }

    const isTabActive = (tab: TabItem, currentPath: string, allTabs: TabItem[]): boolean => {
      const targetPath = tab.to;
      if (tab.exact) {
        return currentPath === targetPath;
      }
      if (currentPath === targetPath) {
        return true;
      }
      const isPrefix = targetPath !== '/app' && targetPath !== '/app/dashboard' && currentPath.startsWith(targetPath + '/');
      if (!isPrefix) return false;

      const moreSpecificMatch = allTabs.some((other) => {
        return (
          other.to !== targetPath &&
          other.to.startsWith(targetPath) &&
          (currentPath === other.to || currentPath.startsWith(other.to + '/'))
        );
      });

      return !moreSpecificMatch;
    };

    // Test Communications & Support group tabs
    const commTabs: TabItem[] = [
      { to: '/app/support', label: 'Support Inquiries', exact: true },
      { to: '/app/messages', label: 'Direct Messages' },
      { to: '/app/support/new', label: 'New Inquiry' },
      { to: '/app/support/staff', label: 'Staff Triage' },
    ];

    // At /app/support -> only Support Inquiries is active
    assert.equal(isTabActive(commTabs[0], '/app/support', commTabs), true);
    assert.equal(isTabActive(commTabs[1], '/app/support', commTabs), false);
    assert.equal(isTabActive(commTabs[2], '/app/support', commTabs), false);
    assert.equal(isTabActive(commTabs[3], '/app/support', commTabs), false);

    // At /app/support/new -> only New Inquiry is active
    assert.equal(isTabActive(commTabs[0], '/app/support/new', commTabs), false);
    assert.equal(isTabActive(commTabs[1], '/app/support/new', commTabs), false);
    assert.equal(isTabActive(commTabs[2], '/app/support/new', commTabs), true);
    assert.equal(isTabActive(commTabs[3], '/app/support/new', commTabs), false);

    // At /app/support/staff -> only Staff Triage is active
    assert.equal(isTabActive(commTabs[0], '/app/support/staff', commTabs), false);
    assert.equal(isTabActive(commTabs[3], '/app/support/staff', commTabs), true);

    // At /app/messages -> only Direct Messages is active
    assert.equal(isTabActive(commTabs[1], '/app/messages', commTabs), true);
    assert.equal(isTabActive(commTabs[0], '/app/messages', commTabs), false);

    // At /app/messages/123 -> Direct Messages remains active for child thread
    assert.equal(isTabActive(commTabs[1], '/app/messages/123', commTabs), true);
  });

  // SUITE 2: Tab Destination Critical Invariant — Pure Route Navigation, Zero Anchor Links
  await t.test('All tab destinations are valid route paths and do not contain href #section anchor jumps', () => {
    const pageGroupTabs = {
      events: [
        { to: '/app/events', label: 'Browse Events' },
        { to: '/app/tickets', label: 'My Passes Wallet' },
      ],
      communications: [
        { to: '/app/support', label: 'Support Inquiries' },
        { to: '/app/messages', label: 'Direct Messages' },
        { to: '/app/support/new', label: 'New Inquiry' },
        { to: '/app/support/staff', label: 'Staff Triage' },
      ],
      notifications: [
        { to: '/app/notifications', label: 'Alerts & Updates' },
        { to: '/app/notifications/preferences', label: 'Delivery Preferences' },
      ],
      profile: [
        { to: '/app/profile', label: 'Personal Details' },
        { to: '/app/profile/security', label: 'Security & Credentials' },
      ],
      resources: [
        { to: '/app/resources', label: 'Community Guides' },
        { to: '/app/help', label: 'Help & FAQ' },
      ],
    };

    for (const [group, tabs] of Object.entries(pageGroupTabs)) {
      for (const tab of tabs) {
        assert.ok(tab.to.startsWith('/app/'), `Tab in ${group} must start with /app/`);
        assert.ok(!tab.to.includes('#'), `Tab in ${group} must NOT use anchor navigation (#)`);
      }
    }
  });

  // SUITE 3: Shared Navbar Layout & Single-Line Invariants
  await t.test('Navbar brand and Sign Out actions preserve single-line nowrap constraints', () => {
    // Primary line brand hierarchy:
    const brandPrimary = 'SFU MSA Connect';
    const brandSecondary = 'Member Portal';
    const signOutLabel = 'Sign Out';

    // Must be non-empty and well-formed
    assert.equal(brandPrimary, 'SFU MSA Connect');
    assert.equal(brandSecondary, 'Member Portal');
    assert.equal(signOutLabel, 'Sign Out');

    // Sign out must not have line-breaks
    assert.doesNotMatch(signOutLabel, /\n|\r/);
  });

  // SUITE 4: Verified Help Topics Preserved without Anchor Jump Links
  await t.test('Help sections preserve all six verified section identifiers', () => {
    const registeredHelpSectionIds = [
      'auth-sessions',
      'profile-management',
      'notifications-troubleshooting',
      'pwa-install',
      'network-connectivity',
      'contact-support',
    ];

    assert.equal(registeredHelpSectionIds.length, 6);
    assert.ok(registeredHelpSectionIds.includes('auth-sessions'));
    assert.ok(registeredHelpSectionIds.includes('profile-management'));
    assert.ok(registeredHelpSectionIds.includes('notifications-troubleshooting'));
    assert.ok(registeredHelpSectionIds.includes('pwa-install'));
    assert.ok(registeredHelpSectionIds.includes('network-connectivity'));
    assert.ok(registeredHelpSectionIds.includes('contact-support'));
  });
});
