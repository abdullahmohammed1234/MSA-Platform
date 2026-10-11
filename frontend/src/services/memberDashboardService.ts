import api from '@/services/api';
import type { ApplicationAccessItem } from '@/services/account/accountService';

export interface DashboardUser {
  uuid: string;
  name: string;
  email: string;
  avatar?: string | null;
  community_status: string;
  email_verified: boolean;
}

export interface DashboardActionItem {
  id: string;
  type: 'urgent' | 'warning' | 'info';
  title: string;
  description: string;
  action_label: string;
  action_path: string;
}

export interface DashboardNextEvent {
  registration_id: number;
  registration_uuid: string;
  reference: string;
  status: string;
  event_title: string;
  event_slug: string;
  start_at?: string;
  end_at?: string;
  location?: string;
  category?: string;
  ticket_code?: string | null;
  amount_due: number;
}

export interface VolunteerCommitment {
  id: number;
  opportunity_title: string;
  opportunity_slug: string;
  shift_name?: string | null;
  status: string;
  start_at?: string | null;
  end_at?: string | null;
  location?: string | null;
}

export interface VolunteerPendingApplication {
  id: number;
  opportunity_title: string;
  opportunity_slug: string;
  status: string;
  applied_at?: string | null;
}

export interface VolunteerAttendanceSummary {
  attended_count: number;
  absent_count: number;
  attendance_note: string;
}

export interface RecommendedOpportunity {
  id: number;
  title: string;
  slug: string;
  category?: string | null;
  location?: string | null;
  start_at?: string | null;
  end_at?: string | null;
  reason_label: string;
  required_skills?: string[];
}

export interface DashboardVolunteerSnapshot {
  status: string;
  completion_percentage: number;
  upcoming_shifts: number;
  completed_shifts: number;
  skills: string[];
  commitments?: VolunteerCommitment[];
  pending_applications?: VolunteerPendingApplication[];
  attendance_summary?: VolunteerAttendanceSummary;
}

export interface DashboardLearningSnapshot {
  active_course?: {
    id: number;
    title: string;
    slug?: string;
    status: string;
    enrolled_at?: string;
  } | null;
  completed_courses: number;
  certificates_count: number;
}

export interface DashboardRecentOrder {
  id: number;
  order_number: string;
  status: string;
  payment_status: string;
  fulfillment_status: string;
  formatted_total: string;
  created_at?: string;
}

export interface DashboardNotificationItem {
  id: number;
  uuid: string;
  type: string;
  title: string;
  message: string;
  read_at?: string | null;
  created_at?: string;
}

export interface DashboardDataResponse {
  user: DashboardUser;
  action_items: DashboardActionItem[];
  recommended_opportunities?: RecommendedOpportunity[];
  next_event?: DashboardNextEvent | null;
  upcoming_events_count: number;
  volunteer?: DashboardVolunteerSnapshot | null;
  learning: DashboardLearningSnapshot;
  recent_order?: DashboardRecentOrder | null;
  notifications: {
    unread_count: number;
    latest: DashboardNotificationItem[];
  };
  applications: ApplicationAccessItem[];
}

export const memberDashboardService = {
  async getDashboardData(): Promise<DashboardDataResponse> {
    const response = await api.get('/dashboard');
    return response.data;
  },

  async markNotificationRead(idOrUuid: string | number): Promise<void> {
    await api.put(`/notifications/${idOrUuid}/read`);
  },
};

