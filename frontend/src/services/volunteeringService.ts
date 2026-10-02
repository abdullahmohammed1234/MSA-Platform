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
  skills?: Skill[];
  interests?: Interest[];
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
  attendance_status?: string | null;
  attended_at?: string | null;
  admin_notes?: string | null;
  created_at: string;
  opportunity?: VolunteerOpportunity;
  team?: VolunteerTeam;
  shift?: VolunteerShift;
}

export interface Skill {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  category?: string | null;
  is_active: boolean;
  sort_order: number;
  pivot?: {
    proficiency_level?: string;
    is_required?: boolean;
    min_proficiency?: string;
  };
}

export interface Interest {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  is_active: boolean;
  sort_order: number;
}

export interface VolunteerExperience {
  id: number;
  profile_id: number;
  organization: string;
  role_title: string;
  description?: string | null;
  start_date?: string | null;
  end_date?: string | null;
  is_current: boolean;
}

export interface VolunteerProfile {
  id: number;
  user_id: number;
  bio?: string | null;
  experience_level: string;
  years_experience: number;
  preferred_hours_per_week?: number | null;
  availability_days?: string[] | null;
  availability_times?: string[] | null;
  preferred_categories?: string[] | null;
  profile_completion_percentage: number;
  privacy_level: string;
  skills?: Skill[];
  interests?: Interest[];
  experiences?: VolunteerExperience[];
  user?: {
    id: number;
    name: string;
    email: string;
  };
}

export interface CandidateMatch {
  profile_id: number;
  user_id: number;
  user_name: string;
  user_email: string;
  experience_level: string;
  score: number;
  grade: string;
  reasons: string[];
  verified_service_hours: number;
}

export interface OpportunityRecommendation {
  opportunity: VolunteerOpportunity;
  match_score: number;
  match_grade: string;
  reasons: string[];
}

export interface VolunteerInvitation {
  id: number;
  uuid: string;
  opportunity_id: number;
  team_id?: number | null;
  shift_id?: number | null;
  user_id: number;
  invited_by: number;
  status: string;
  message?: string | null;
  accepted_at?: string | null;
  declined_at?: string | null;
  expires_at?: string | null;
  opportunity?: VolunteerOpportunity;
  team?: VolunteerTeam;
  shift?: VolunteerShift;
  inviter?: {
    id: number;
    name: string;
  };
}

export interface Achievement {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  category: string;
  icon: string;
  rule_type: string;
  criteria_config?: Record<string, any> | null;
  points: number;
  is_active: boolean;
  sort_order: number;
}

export interface UserAchievement {
  id: number;
  user_id: number;
  achievement_id: number;
  awarded_at: string;
  awarded_by?: number | null;
  trigger_type: string;
  award_reason?: string | null;
  metadata?: Record<string, any> | null;
  achievement?: Achievement;
}

export interface NextMilestone {
  threshold_hours: number;
  current_hours: number;
  remaining_hours: number;
  progress_percentage: number;
}

export interface VolunteerRecognitionData {
  summary: {
    earned_count: number;
    total_points: number;
    total_service_hours: number;
    completed_count: number;
    retention_status: string;
  };
  next_milestone?: NextMilestone | null;
  newly_awarded: UserAchievement[];
  achievements: UserAchievement[];
}

export interface RetentionOverview {
  active: number;
  returning: number;
  inactive: number;
  dormant: number;
  new: number;
  total_volunteers: number;
}

export interface ReengagementCandidate {
  user_id: number;
  user_name: string;
  user_email: string;
  retention_status: string;
  days_since_last_participation?: number | null;
  total_service_hours: number;
  top_match: {
    opportunity_id: number;
    opportunity_title: string;
    match_score: number;
    tier: string;
    explanations: string[];
  };
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
    join_waitlist?: boolean;
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

  // VMS-7 Volunteer Profile & Matching APIs
  async getActiveSkills() {
    const response = await api.get('/volunteering/skills');
    return response.data;
  },

  async getActiveInterests() {
    const response = await api.get('/volunteering/interests');
    return response.data;
  },

  async getProfile() {
    const response = await api.get('/volunteering/profile');
    return response.data;
  },

  async updateProfile(payload: Partial<VolunteerProfile>) {
    const response = await api.put('/volunteering/profile', payload);
    return response.data;
  },

  async attachSkill(skillId: number, proficiencyLevel: string = 'intermediate') {
    const response = await api.post('/volunteering/profile/skills', { skill_id: skillId, proficiency_level: proficiencyLevel });
    return response.data;
  },

  async detachSkill(skillId: number) {
    const response = await api.delete(`/volunteering/profile/skills/${skillId}`);
    return response.data;
  },

  async attachInterest(interestId: number) {
    const response = await api.post('/volunteering/profile/interests', { interest_id: interestId });
    return response.data;
  },

  async detachInterest(interestId: number) {
    const response = await api.delete(`/volunteering/profile/interests/${interestId}`);
    return response.data;
  },

  async addExperience(payload: Partial<VolunteerExperience>) {
    const response = await api.post('/volunteering/profile/experiences', payload);
    return response.data;
  },

  async deleteExperience(experienceId: number) {
    const response = await api.delete(`/volunteering/profile/experiences/${experienceId}`);
    return response.data;
  },

  async getRecommendations() {
    const response = await api.get('/volunteering/recommendations');
    return response.data;
  },

  async getInvitations() {
    const response = await api.get('/volunteering/invitations');
    return response.data;
  },

  async acceptInvitation(uuid: string) {
    const response = await api.post(`/volunteering/invitations/${uuid}/accept`);
    return response.data;
  },

  async declineInvitation(uuid: string) {
    const response = await api.post(`/volunteering/invitations/${uuid}/decline`);
    return response.data;
  },

  // VMS-8 Recognition & Progression APIs
  async getRecognition() {
    const response = await api.get('/volunteering/recognition');
    return response.data;
  },

  async evaluateRecognition() {
    const response = await api.post('/volunteering/recognition/evaluate');
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

  async getSignupsForOpportunity(id: number, params?: { status?: string; attendance_status?: string; search?: string; per_page?: number; page?: number }) {
    const response = await api.get(`/admin/volunteering/opportunities/${id}/signups`, { params });
    return response.data;
  },

  async getAllSignups(params?: {
    opportunity_id?: number;
    team_id?: number;
    shift_id?: number;
    status?: string;
    attendance_status?: string;
    search?: string;
    per_page?: number;
    page?: number;
  }) {
    const response = await api.get('/admin/volunteering/signups', { params });
    return response.data;
  },

  async updateSignupStatus(signupId: number, payload: { status: string; admin_notes?: string }) {
    const response = await api.put(`/admin/volunteering/signups/${signupId}/status`, payload);
    return response.data;
  },

  async updateAttendance(signupId: number, payload: { attendance_status: string; admin_notes?: string }) {
    const response = await api.put(`/admin/volunteering/signups/${signupId}/attendance`, payload);
    return response.data;
  },

  async batchAttendance(payload: { signup_ids: number[]; attendance_status: string; admin_notes?: string }) {
    const response = await api.post('/admin/volunteering/signups/batch-attendance', payload);
    return response.data;
  },

  async promoteWaitlistedSignup(signupId: number) {
    const response = await api.post(`/admin/volunteering/signups/${signupId}/promote`);
    return response.data;
  },

  async exportSignupsCsv(params?: {
    opportunity_id?: number;
    status?: string;
    attendance_status?: string;
    search?: string;
  }) {
    const response = await api.get('/admin/volunteering/export', {
      params,
      responseType: 'blob',
    });
    return response.data;
  },

  async getAnalytics() {
    const response = await api.get('/admin/volunteering/analytics');
    return response.data;
  },

  // VMS-7 Admin Taxonomy & Candidate Matching APIs
  async getAdminSkills() {
    const response = await api.get('/admin/volunteering/skills');
    return response.data;
  },

  async createAdminSkill(payload: Partial<Skill>) {
    const response = await api.post('/admin/volunteering/skills', payload);
    return response.data;
  },

  async updateAdminSkill(id: number, payload: Partial<Skill>) {
    const response = await api.put(`/admin/volunteering/skills/${id}`, payload);
    return response.data;
  },

  async deleteAdminSkill(id: number) {
    const response = await api.delete(`/admin/volunteering/skills/${id}`);
    return response.data;
  },

  async getAdminInterests() {
    const response = await api.get('/admin/volunteering/interests');
    return response.data;
  },

  async createAdminInterest(payload: Partial<Interest>) {
    const response = await api.post('/admin/volunteering/interests', payload);
    return response.data;
  },

  async updateAdminInterest(id: number, payload: Partial<Interest>) {
    const response = await api.put(`/admin/volunteering/interests/${id}`, payload);
    return response.data;
  },

  async deleteAdminInterest(id: number) {
    const response = await api.delete(`/admin/volunteering/interests/${id}`);
    return response.data;
  },

  async updateOpportunityRequirements(
    opportunityId: number,
    payload: {
      skills?: { skill_id: number; is_required?: boolean; min_proficiency?: string }[];
      interests?: number[];
    }
  ) {
    const response = await api.put(`/admin/volunteering/opportunities/${opportunityId}/requirements`, payload);
    return response.data;
  },

  async getOpportunityMatches(opportunityId: number, limit: number = 20) {
    const response = await api.get(`/admin/volunteering/opportunities/${opportunityId}/matches`, { params: { limit } });
    return response.data;
  },

  async inviteVolunteer(opportunityId: number, payload: { user_id: number; team_id?: number; shift_id?: number; message?: string }) {
    const response = await api.post(`/admin/volunteering/opportunities/${opportunityId}/invite`, payload);
    return response.data;
  },

  async getAdminProfiles(params?: { experience_level?: string; skill_id?: number; search?: string; per_page?: number; page?: number }) {
    const response = await api.get('/admin/volunteering/profiles', { params });
    return response.data;
  },

  async getAdminProfileDetail(id: number) {
    const response = await api.get(`/admin/volunteering/profiles/${id}`);
    return response.data;
  },

  // VMS-8 Admin Recognition, Retention & Re-engagement APIs
  async getAdminAchievements() {
    const response = await api.get('/admin/volunteering/achievements');
    return response.data;
  },

  async createAdminAchievement(payload: Partial<Achievement>) {
    const response = await api.post('/admin/volunteering/achievements', payload);
    return response.data;
  },

  async updateAdminAchievement(id: number, payload: Partial<Achievement>) {
    const response = await api.put(`/admin/volunteering/achievements/${id}`, payload);
    return response.data;
  },

  async awardVolunteerAchievement(userId: number, achievementId: number, reason?: string) {
    const response = await api.post(`/admin/volunteering/volunteers/${userId}/award`, { achievement_id: achievementId, reason });
    return response.data;
  },

  async getAdminRetentionOverview() {
    const response = await api.get('/admin/volunteering/retention');
    return response.data;
  },

  async getAdminReengagementCandidates() {
    const response = await api.get('/admin/volunteering/reengagement');
    return response.data;
  },

  async logAdminReengagementOutreach(userId: number, opportunityId?: number, channel: string = 'email') {
    const response = await api.post('/admin/volunteering/reengagement/outreach', { user_id: userId, opportunity_id: opportunityId, channel });
    return response.data;
  },
};

