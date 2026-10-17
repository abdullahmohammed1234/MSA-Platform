/**
 * Types matching GET /api/v1/website/announcements from WebsiteController.
 */

export interface Announcement {
  id: string;
  title: string;
  slug: string;
  content: string;
  summary: string;
  date: string | null;
  category: string;
  featured_image: string | null;
}

export interface AnnouncementsResponse {
  announcements: Announcement[];
}
