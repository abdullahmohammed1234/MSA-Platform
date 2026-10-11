/**
 * Safe notification destination resolver.
 * Resolves internal application routes from validated notification payload types.
 * Guarantees fallback to safe account routes if payload metadata is missing or invalid.
 */
export function resolveNotificationDestination(notification?: { type?: string; data?: any } | null): string {
  if (!notification || !notification.type) {
    return '/account';
  }

  const type = String(notification.type).toLowerCase();
  const data = notification.data || {};

  // 1. EMS Events & Registrations
  if (
    type.includes('event') ||
    type.includes('registration') ||
    type.includes('ticket') ||
    data.type === 'ems' ||
    data.type === 'event'
  ) {
    if (data.event_slug && typeof data.event_slug === 'string') {
      return `/events/${encodeURIComponent(data.event_slug)}`;
    }
    return '/account/activity';
  }

  // 2. VMS Volunteering
  if (
    type.includes('vms') ||
    type.includes('volunteer') ||
    type.includes('shift') ||
    data.type === 'volunteer' ||
    data.type === 'vms'
  ) {
    if (data.opportunity_slug && typeof data.opportunity_slug === 'string') {
      return `/volunteer/${encodeURIComponent(data.opportunity_slug)}`;
    }
    return '/account/volunteer';
  }

  // 3. Store Merchandise
  if (type.includes('store') || type.includes('order') || data.type === 'store') {
    return '/store/my-orders';
  }

  // 4. CMS & Announcements
  if (type.includes('announcement') || data.type === 'announcement') {
    if (data.slug && typeof data.slug === 'string') {
      return `/announcements/${encodeURIComponent(data.slug)}`;
    }
    return '/dashboard';
  }

  // 5. Dawah Academy & Credentials
  if (
    type.includes('certificate') ||
    type.includes('course') ||
    type.includes('award') ||
    type.includes('training') ||
    data.type === 'certificate' ||
    data.type === 'course'
  ) {
    return '/account/activity';
  }

  // Safe Default Fallback
  return '/account';
}
