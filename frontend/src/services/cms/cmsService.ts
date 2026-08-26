import client from '@/services/api';
import type {
  CmsSection,
  Announcement,
  TeamMember,
  Resource,
  Media,
  MediaCategory,
  MediaUploadOptions,
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
    const response = await client.get('/cms/dashboard');
    return response.data;
  },

  // 2. Homepage Sections
  async getHomepageSections(): Promise<CmsSection[]> {
    const response = await client.get('/cms/homepage');
    return response.data.sections;
  },

  async updateHomepageSection(key: string, blocks: Record<string, string | null>): Promise<{ success: boolean; message: string }> {
    const response = await client.put(`/cms/homepage/${key}`, { blocks });
    return response.data;
  },

  // 3. Announcements
  async getAnnouncements(params: any = {}): Promise<PaginatedResponse<Announcement>> {
    const response = await client.get('/cms/announcements', { params });
    return response.data;
  },

  async getAnnouncement(uuid: string): Promise<Announcement> {
    const response = await client.get(`/cms/announcements/${uuid}`);
    return response.data;
  },

  async createAnnouncement(data: Partial<Announcement>): Promise<Announcement> {
    const response = await client.post('/cms/announcements', data);
    return response.data.announcement;
  },

  async updateAnnouncement(uuid: string, data: Partial<Announcement>): Promise<Announcement> {
    const response = await client.put(`/cms/announcements/${uuid}`, data);
    return response.data.announcement;
  },

  async deleteAnnouncement(uuid: string): Promise<void> {
    await client.delete(`/cms/announcements/${uuid}`);
  },

  async getAnnouncementRevisions(uuid: string): Promise<CmsRevision[]> {
    const response = await client.get(`/cms/announcements/${uuid}/revisions`);
    return response.data.revisions;
  },

  async rollbackAnnouncement(uuid: string, version: number): Promise<Announcement> {
    const response = await client.post(`/cms/announcements/${uuid}/rollback`, { version });
    return response.data.announcement;
  },

  // 4. Team Members
  async getTeamMembers(params: any = {}): Promise<PaginatedResponse<TeamMember>> {
    const response = await client.get('/cms/team', { params });
    return response.data;
  },

  async getTeamMember(uuid: string): Promise<TeamMember> {
    const response = await client.get(`/cms/team/${uuid}`);
    return normalizeCmsTeamMember(response.data);
  },

  async createTeamMember(data: Partial<TeamMember>): Promise<TeamMember> {
    const response = await client.post('/cms/team', data);
    const member = response.data?.member;
    return member ? normalizeCmsTeamMember(member) : (data as TeamMember);
  },

  async updateTeamMember(uuid: string, data: Partial<TeamMember>): Promise<TeamMember> {
    const response = await client.put(`/cms/team/${uuid}`, data);
    const member = response.data?.member;
    return member ? normalizeCmsTeamMember(member) : (data as TeamMember);
  },

  async deleteTeamMember(uuid: string): Promise<void> {
    await client.delete(`/cms/team/${uuid}`);
  },

  async reorderTeamMembers(uuids: string[]): Promise<void> {
    await client.post('/cms/team/reorder', { uuids });
  },

  async getTeamMemberRevisions(uuid: string): Promise<CmsRevision[]> {
    const response = await client.get(`/cms/team/${uuid}/revisions`);
    return response.data.revisions;
  },

  async rollbackTeamMember(uuid: string, version: number): Promise<TeamMember> {
    const response = await client.post(`/cms/team/${uuid}/rollback`, { version });
    return normalizeCmsTeamMember(response.data.member);
  },

  // 6. Resources
  async getResources(params: any = {}): Promise<PaginatedResponse<Resource>> {
    const response = await client.get('/cms/resources', { params });
    return response.data;
  },

  async getResource(uuid: string): Promise<Resource> {
    const response = await client.get(`/cms/resources/${uuid}`);
    return response.data;
  },

  async createResource(data: Partial<Resource>): Promise<Resource> {
    const response = await client.post('/cms/resources', data);
    return response.data.resource;
  },

  async updateResource(uuid: string, data: Partial<Resource>): Promise<Resource> {
    const response = await client.put(`/cms/resources/${uuid}`, data);
    return response.data.resource;
  },

  async deleteResource(uuid: string): Promise<void> {
    await client.delete(`/cms/resources/${uuid}`);
  },

  async getResourceRevisions(uuid: string): Promise<CmsRevision[]> {
    const response = await client.get(`/cms/resources/${uuid}/revisions`);
    return response.data.revisions;
  },

  async rollbackResource(uuid: string, version: number): Promise<Resource> {
    const response = await client.post(`/cms/resources/${uuid}/rollback`, { version });
    return response.data.resource;
  },

  // 7. Media Library
  async getMedia(params: Record<string, unknown> = {}): Promise<PaginatedResponse<Media>> {
    const response = await client.get('/cms/media', { params });
    return response.data;
  },

  async uploadMedia(file: File, options: MediaUploadOptions = {}): Promise<Media> {
    const formData = new FormData();
    formData.append('file', file);
    if (options.display_name?.trim()) {
      formData.append('display_name', options.display_name.trim());
    }
    if (options.category_id != null) {
      formData.append('category_id', String(options.category_id));
    }
    const response = await client.post('/cms/media', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data.media;
  },

  /** Upload an image for forms outside the media library (returns URL only). */
  async uploadAsset(file: File): Promise<{ success: boolean; message: string; url: string }> {
    const formData = new FormData();
    formData.append('file', file);
    const response = await client.post('/cms/assets/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  async getMediaCategories(): Promise<MediaCategory[]> {
    const response = await client.get('/cms/media/categories');
    return response.data.categories ?? [];
  },

  async createMediaCategory(name: string): Promise<MediaCategory> {
    const response = await client.post('/cms/media/categories', { name });
    return response.data.category;
  },

  async uploadTeamPhoto(file: File): Promise<{ success: boolean; message: string; url: string }> {
    const formData = new FormData();
    formData.append('file', file);
    const response = await client.post('/cms/team/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  async deleteMedia(uuid: string): Promise<void> {
    await client.delete(`/cms/media/${uuid}`);
  },
};

export default cmsService;
