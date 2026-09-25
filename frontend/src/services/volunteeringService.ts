import api from './api/client';

export interface VolunteerShift {
  id: number;
  opportunity_id: number;
  team_id?: number | null;
  name?: string | null;
  start_at: string;
  end_at: string;
  capacity: number;
  status: string;
}

export interface VolunteerTeam {
  id: number;
  opportunity_id: number;
  name: string;
  description?: string | null;
  capacity?: number | null;
  status: string;
  ordering: number;
  shifts?: VolunteerShift[];
}

export interface VolunteerOpportunity {
  id: number;
  uuid: string;
  title: string;
  slug: string;
  description?: string | null;
  event_id?: number | null;
  start_at?: string | null;
  end_at?: string | null;
  location?: string | null;
  capacity?: number | null;
  status: string;
  published_at?: string | null;
  event?: {
    id: number;
    name: string;
    slug: string;
    start_at?: string;
    location?: string;
  };
  teams?: VolunteerTeam[];
  shifts?: VolunteerShift[];
  signups_count?: number;
}

export interface VolunteerSignup {
  id: number;
  uuid: string;
  opportunity_id: number;
  team_id?: number | null;
  shift_id?: number | null;
  name: string;
  email: string;
  phone?: string | null;
  experience?: string | null;
  notes?: string | null;
  status: string;
  admin_notes?: string | null;
  created_at: string;
  opportunity?: VolunteerOpportunity;
  team?: VolunteerTeam;
  shift?: VolunteerShift;
}

export const volunteeringService = {
  // Public APIs
  async getPublicOpportunities(params?: { search?: string; event_id?: number; per_page?: number; page?: number }) {
    const response = await api.get('/volunteering/opportunities', { params });
    return response.data;
  },

  async getOpportunityBySlug(slug: string) {
    const response = await api.get(`/volunteering/opportunities/${slug}`);
    return response.data;
  },

  async submitSignup(payload: {
    opportunity_id: number;
    team_id?: number | null;
    shift_id?: number | null;
    name: string;
    email: string;
    phone?: string | null;
    experience?: string | null;
    notes?: string | null;
  }) {
    const response = await api.post('/volunteering/signups', payload);
    return response.data;
  },

  async cancelSignup(uuid: string) {
    const response = await api.post(`/volunteering/signups/${uuid}/cancel`);
    return response.data;
  },

  async getMyHistory() {
    const response = await api.get('/volunteering/my-history');
    return response.data;
  },

  // Admin APIs
  async getEligibleEvents() {
    const response = await api.get('/admin/volunteering/eligible-events');
    return response.data;
  },

  async getAdminOpportunities(params?: { status?: string; search?: string; per_page?: number; page?: number }) {
    const response = await api.get('/admin/volunteering/opportunities', { params });
    return response.data;
  },

  async createOpportunity(payload: Partial<VolunteerOpportunity> & { teams?: any[]; shifts?: any[] }) {
    const response = await api.post('/admin/volunteering/opportunities', payload);
    return response.data;
  },

  async getAdminOpportunity(id: number) {
    const response = await api.get(`/admin/volunteering/opportunities/${id}`);
    return response.data;
  },

  async updateOpportunity(id: number, payload: Partial<VolunteerOpportunity>) {
    const response = await api.put(`/admin/volunteering/opportunities/${id}`, payload);
    return response.data;
  },

  async deleteOpportunity(id: number) {
    const response = await api.delete(`/admin/volunteering/opportunities/${id}`);
    return response.data;
  },

  async getSignupsForOpportunity(id: number, params?: { status?: string; search?: string; per_page?: number; page?: number }) {
    const response = await api.get(`/admin/volunteering/opportunities/${id}/signups`, { params });
    return response.data;
  },

  async updateSignupStatus(signupId: number, payload: { status: string; admin_notes?: string }) {
    const response = await api.put(`/admin/volunteering/signups/${signupId}/status`, payload);
    return response.data;
  },

  async getAnalytics() {
    const response = await api.get('/admin/volunteering/analytics');
    return response.data;
  },
};
