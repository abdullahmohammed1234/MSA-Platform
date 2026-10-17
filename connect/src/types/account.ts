import type { User } from './auth';

/**
 * Types matching AccountController and UserController API contracts.
 */

export interface UpdateProfilePayload {
  name?: string;
  email?: string;
}

export interface UpdateProfileResponse {
  message: string;
  user: User;
}

export interface UpdatePasswordPayload {
  current_password: string;
  new_password: string;
  new_password_confirmation: string;
}

export interface UpdatePasswordResponse {
  message: string;
  token?: string;
}

export interface AccountSummaryUser {
  uuid: string;
  name: string;
  email: string;
  avatar: string | null;
  is_active: boolean;
  email_verified: boolean;
  email_verified_at: string | null;
  member_since: string | null;
  created_at: string | null;
  community_status: string;
  roles: string[];
}

export interface AccountActivityCounts {
  upcoming_events_count?: number;
  unread_notifications_count?: number;
  active_courses_count?: number;
  completed_courses_count?: number;
  certificates_count?: number;
  volunteer_hours?: number;
}

export interface AccountSummaryData {
  user: AccountSummaryUser;
  applications?: Array<{
    slug: string;
    title: string;
    description: string;
    path: string;
    is_admin: boolean;
  }>;
  summary?: AccountActivityCounts;
  recent_activity?: {
    ems_registrations?: Array<{
      id: number;
      uuid: string;
      reference: string;
      status: string;
      event?: {
        id: number;
        title: string;
        slug: string;
        start_at: string | null;
        location: string | null;
        category: string | null;
      } | null;
      created_at: string | null;
    }>;
    [key: string]: any;
  };
}

export interface AccountSummaryResponse {
  message: string;
  data: AccountSummaryData;
}
