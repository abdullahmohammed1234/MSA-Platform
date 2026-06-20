import client from '@/services/api/client';
import type {
  CmsSection,
  Announcement,
  Event,
  EventRegistration,
  TeamMember,
  Resource,
  Media,
  CmsRevision,
  CmsDashboardStats,
  CmsDashboardActivity
} from '@/types/cms';
import { normalizeCmsTeamMember } from '@/utils/teamMembers';

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export const cmsService = {
  // 1. Dashboard
  async getDashboard(): Promise<{ stats: CmsDashboardStats; recentLogs: CmsDashboardActivity[] }> {
    const response = await client.get('/admin/cms/dashboard');
    return response.data;
  },

  // 2. Homepage Sections
  async getHomepageSections(): Promise<CmsSection[]> {
    const response = await client.get('/admin/cms/homepage');
    return response.data.sections;
  },

  async updateHomepageSection(key: string, blocks: Record<string, string | null>): Promise<{ success: boolean; message: string }> {
    const response = await client.put(`/admin/cms/homepage/${key}`, { blocks });
    return response.data;
  },

  // 3. Announcements
  async getAnnouncements(params: any = {}): Promise<PaginatedResponse<Announcement>> {
    const response = await client.get('/admin/cms/announcements', { params });
    return response.data;
  },

  async getAnnouncement(uuid: string): Promise<Announcement> {
    const response = await client.get(`/admin/cms/announcements/${uuid}`);
    return response.data;
  },

  async createAnnouncement(data: Partial<Announcement>): Promise<Announcement> {
    const response = await client.post('/admin/cms/announcements', data);
    return response.data.announcement;
  },

  async updateAnnouncement(uuid: string, data: Partial<Announcement>): Promise<Announcement> {
    const response = await client.put(`/admin/cms/announcements/${uuid}`, data);
    return response.data.announcement;
  },

  async deleteAnnouncement(uuid: string): Promise<void> {
    await client.delete(`/admin/cms/announcements/${uuid}`);
  },

  async getAnnouncementRevisions(uuid: string): Promise<CmsRevision[]> {
    const response = await client.get(`/admin/cms/announcements/${uuid}/revisions`);
    return response.data.revisions;
  },

  async rollbackAnnouncement(uuid: string, version: number): Promise<Announcement> {
    const response = await client.post(`/admin/cms/announcements/${uuid}/rollback`, { version });
    return response.data.announcement;
  },

  // 4. Events
  async getEvents(params: any = {}): Promise<PaginatedResponse<Event>> {
    const response = await client.get('/admin/cms/events', { params });
    return response.data;
  },

  async getEvent(uuid: string): Promise<Event> {
    const response = await client.get(`/admin/cms/events/${uuid}`);
    return response.data;
  },

  async createEvent(data: Partial<Event>): Promise<Event> {
    const response = await client.post('/admin/cms/events', data);
    return response.data.event;
  },

  async updateEvent(uuid: string, data: Partial<Event>): Promise<Event> {
    const response = await client.put(`/admin/cms/events/${uuid}`, data);
    return response.data.event;
  },

  async deleteEvent(uuid: string): Promise<void> {
    await client.delete(`/admin/cms/events/${uuid}`);
  },

  async getEventRevisions(uuid: string): Promise<CmsRevision[]> {
    const response = await client.get(`/admin/cms/events/${uuid}/revisions`);
    return response.data.revisions;
  },

  async rollbackEvent(uuid: string, version: number): Promise<Event> {
    const response = await client.post(`/admin/cms/events/${uuid}/rollback`, { version });
    return response.data.event;
  },

  async getEventRegistrations(uuid: string, params: { page?: number; per_page?: number } = {}): Promise<PaginatedResponse<EventRegistration> & {
    event: { uuid: string; title: string; spots_left: number; registrations_count: number };
  }> {
    const response = await client.get(`/admin/cms/events/${uuid}/registrations`, { params });
    return response.data;
  },

  // 5. Team Members
  async getTeamMembers(params: any = {}): Promise<PaginatedResponse<TeamMember>> {
    const response = await client.get('/admin/cms/team', { params });
    return {
      ...response.data,
      data: response.data.data.map(normalizeCmsTeamMember),
    };
  },

  async getTeamMember(uuid: string): Promise<TeamMember> {
    const response = await client.get(`/admin/cms/team/${uuid}`);
    return normalizeCmsTeamMember(response.data);
  },

  async createTeamMember(data: Partial<TeamMember>): Promise<TeamMember> {
    const response = await client.post('/admin/cms/team', data);
    return normalizeCmsTeamMember(response.data.member);
  },

  async updateTeamMember(uuid: string, data: Partial<TeamMember>): Promise<TeamMember> {
    const response = await client.put(`/admin/cms/team/${uuid}`, data);
    return normalizeCmsTeamMember(response.data.member);
  },

  async deleteTeamMember(uuid: string): Promise<void> {
    await client.delete(`/admin/cms/team/${uuid}`);
  },

  async reorderTeamMembers(uuids: string[]): Promise<void> {
    await client.post('/admin/cms/team/reorder', { uuids });
  },

  async getTeamMemberRevisions(uuid: string): Promise<CmsRevision[]> {
    const response = await client.get(`/admin/cms/team/${uuid}/revisions`);
    return response.data.revisions;
  },

  async rollbackTeamMember(uuid: string, version: number): Promise<TeamMember> {
    const response = await client.post(`/admin/cms/team/${uuid}/rollback`, { version });
    return normalizeCmsTeamMember(response.data.member);
  },

  // 6. Resources
  async getResources(params: any = {}): Promise<PaginatedResponse<Resource>> {
    const response = await client.get('/admin/cms/resources', { params });
    return response.data;
  },

  async getResource(uuid: string): Promise<Resource> {
    const response = await client.get(`/admin/cms/resources/${uuid}`);
    return response.data;
  },

  async createResource(data: Partial<Resource>): Promise<Resource> {
    const response = await client.post('/admin/cms/resources', data);
    return response.data.resource;
  },

  async updateResource(uuid: string, data: Partial<Resource>): Promise<Resource> {
    const response = await client.put(`/admin/cms/resources/${uuid}`, data);
    return response.data.resource;
  },

  async deleteResource(uuid: string): Promise<void> {
    await client.delete(`/admin/cms/resources/${uuid}`);
  },

  async getResourceRevisions(uuid: string): Promise<CmsRevision[]> {
    const response = await client.get(`/admin/cms/resources/${uuid}/revisions`);
    return response.data.revisions;
  },

  async rollbackResource(uuid: string, version: number): Promise<Resource> {
    const response = await client.post(`/admin/cms/resources/${uuid}/rollback`, { version });
    return response.data.resource;
  },

  // 7. Media Library
  async getMedia(params: any = {}): Promise<PaginatedResponse<Media>> {
    const response = await client.get('/admin/cms/media', { params });
    return response.data;
  },

  async uploadMedia(file: File): Promise<Media> {
    const formData = new FormData();
    formData.append('file', file);
    const response = await client.post('/admin/cms/media', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data.media;
  },

  async deleteMedia(uuid: string): Promise<void> {
    await client.delete(`/admin/cms/media/${uuid}`);
  },
};

export default cmsService;
