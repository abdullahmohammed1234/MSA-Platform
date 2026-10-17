import apiClient from './api';
import type { AnnouncementsResponse, Announcement } from '@/types/announcement';

export const announcementService = {
  /**
   * Fetch published official MSA announcements.
   */
  async getAnnouncements(): Promise<Announcement[]> {
    const response = await apiClient.get<AnnouncementsResponse>('/website/announcements');
    return response.data?.announcements || [];
  },
};

export default announcementService;
