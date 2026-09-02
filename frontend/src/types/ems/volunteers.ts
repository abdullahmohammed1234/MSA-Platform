export type VolunteerRegistrationStatus = 'new' | 'contacted' | 'accepted' | 'rejected' | 'archived';

export interface AssignedUser {
  id: number;
  name: string;
  email: string;
}

export interface VolunteerRegistration {
  id: string;
  uuid: string;
  name: string;
  email: string;
  student_number: string;
  department: string;
  interests: string;
  experience?: string | null;
  status: VolunteerRegistrationStatus;
  status_label: string;
  admin_notes?: string | null;
  assigned_to?: number | null;
  assigned_user?: AssignedUser | null;
  contacted_at?: string | null;
  processed_at?: string | null;
  created_at: string;
  updated_at: string;
}

export interface VolunteerRegistrationFilters {
  search?: string;
  status?: string;
  sort_by?: string;
  sort_order?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
}

export interface UpdateVolunteerRegistrationPayload {
  status?: VolunteerRegistrationStatus;
  admin_notes?: string | null;
  assigned_to?: number | null;
}
