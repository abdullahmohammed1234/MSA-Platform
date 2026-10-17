/**
 * Types matching GET /api/v1/dashboard from MemberDashboardService.
 */

export interface DashboardUser {
  uuid: string;
  name: string;
  email: string;
  avatar: string | null;
  community_status: string;
  email_verified: boolean;
}

export interface DashboardNextEvent {
  registration_id: number;
  registration_uuid: string;
  reference: string;
  status: string;
  event_title: string;
  event_slug: string;
  start_at: string | null;
  end_at: string | null;
  location: string | null;
  category: string | null;
  ticket_code: string | null;
  amount_due: number;
}

export interface ActionItem {
  id: string;
  type: 'warning' | 'urgent' | 'info' | 'suggestion';
  priority: number;
  title: string;
  description: string;
  action_label: string;
  action_path: string;
}

export interface NotificationItem {
  id: number;
  uuid: string;
  type: string;
  title: string;
  message: string;
  read_at: string | null;
  created_at: string;
}

export interface NotificationsSummary {
  unread_count: number;
  latest: NotificationItem[];
}

export interface ApplicationAccessItem {
  slug: string;
  title: string;
  description: string;
  path: string;
  is_admin: boolean;
  source: string;
}

export interface DashboardData {
  user: DashboardUser;
  action_items: ActionItem[];
  next_event: DashboardNextEvent | null;
  upcoming_events_count: number;
  notifications: NotificationsSummary;
  applications: ApplicationAccessItem[];
  recommended_opportunities?: Array<{
    id: number;
    uuid: string;
    title: string;
    slug: string;
    excerpt: string;
    start_at: string | null;
    location: string | null;
    reason: string;
    path: string;
  }>;
}
