import api from '@/services/api';

export interface AccountSummaryUser {
  uuid: string;
  name: string;
  email: string;
  avatar?: string | null;
  is_active: boolean;
  email_verified: boolean;
  email_verified_at?: string | null;
  member_since?: string;
  created_at?: string;
  community_status: string;
  roles: string[];
}

export interface ApplicationAccessItem {
  slug: string;
  title: string;
  description: string;
  path: string;
  is_admin: boolean;
  source: string;
}

export interface AccountSummaryCounts {
  upcoming_events: number;
  orders: number;
  volunteer_shifts: number;
  courses: number;
  unread_notifications: number;
}

export interface VolunteerSummary {
  status: string;
  completion_percentage: number;
  skills: string[];
  completed_shifts_count: number;
  upcoming_shifts_count: number;
}

export interface AcademySummary {
  enrolled_courses: number;
  completed_courses: number;
  certificates: number;
  recent_courses: Array<{
    id: number;
    status: string;
    enrolled_at?: string;
    completed_at?: string;
    course?: {
      id: number;
      title: string;
      slug?: string;
    };
  }>;
}

export interface RecentEmsEvent {
  id: number;
  uuid: string;
  reference: string;
  status: string;
  event?: {
    id: number;
    title: string;
    slug: string;
    start_at?: string;
    location?: string;
    category?: string;
  };
  ticket_type?: {
    name: string;
    price: string | number;
  };
  tickets_count: number;
  created_at?: string;
}

export interface RecentStoreOrder {
  id: number;
  order_number: string;
  status: string;
  payment_status: string;
  fulfillment_status: string;
  total_amount: string | number;
  currency: string;
  items_count: number;
  created_at?: string;
}

export interface AccountSummaryResponse {
  user: AccountSummaryUser;
  applications: ApplicationAccessItem[];
  counts: AccountSummaryCounts;
  volunteer?: VolunteerSummary | null;
  academy: AcademySummary;
  activity: {
    events: RecentEmsEvent[];
    orders: RecentStoreOrder[];
  };
}

export const accountService = {
  async getSummary(): Promise<AccountSummaryResponse> {
    const response = await api.get('/account/summary');
    return response.data.data;
  },

  async updatePassword(payload: {
    current_password: string;
    new_password: string;
    new_password_confirmation: string;
  }): Promise<{ message: string; token: string }> {
    const response = await api.put('/account/password', payload);
    return response.data;
  },

  async updateProfile(payload: {
    name?: string;
    email?: string;
  }): Promise<{ message: string; user: any }> {
    const response = await api.put('/users/profile', payload);
    return response.data;
  },
};
