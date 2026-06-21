import api from '@/services/api';
import { resolveTeamMembers } from '@/utils/teamMembers';
import { resolvePublicImagePath } from '@/constants/publicAssets';

export interface AnnouncementItem {
  id: string;
  title: string;
  content: string;
  date: string;
  category?: string;
  featured_image?: string | null;
}

export interface MediaGalleryItem {
  id: string;
  url: string;
  title: string;
  description: string;
  category: string;
  date: string;
  isLandscape?: boolean;
}

export interface EventItem {
  id: string;
  title: string;
  date: string;
  time: string;
  location: string;
  category: 'Jummah' | 'Social' | 'Lecture' | 'Workshop' | 'Charity' | 'Dinner';
  image: string;
  description: string;
  spotsLeft: number;
  featured?: boolean;
  registrationDeadline: string;
  startDate?: string;
  endDate?: string;
}

export interface TeamMember {
  name: string;
  role: string;
  dept: string;
  img: string;
}

export interface ResourceItem {
  id: string;
  title: string;
  description: string;
  category: string;
  iconName: string;
  link: string;
  thumbnail?: string | null;
  file?: string | null;
  isExternal: boolean;
  tags: string[];
}

export interface SponsorItem {
  id: string;
  name: string;
  tier: 'Platinum' | 'Gold' | 'Silver' | 'Bronze';
  logoUrl: string;
  websiteUrl?: string;
}

export interface ContactSubmission {
  name: string;
  email: string;
  subject: string;
  message: string;
}

export interface SponsorSubmission {
  companyName: string;
  contactName: string;
  email: string;
  tierPreference: string;
  message: string;
}

export interface VolunteerSubmission {
  name: string;
  email: string;
  department: string;
  interests: string;
  experience?: string;
}

export interface NewsletterSubscription {
  email: string;
}

export interface EventRsvpSubmission {
  firstName: string;
  lastName: string;
  email: string;
  studentId: string;
}

export interface EventRsvpResponse {
  success: boolean;
  message: string;
  spotsLeft?: number;
  registrationId?: string;
}

export interface EventRegistrationStatus {
  eventId: string;
  registrationId: string;
  registeredAt?: string;
}

const LOCAL_REGISTRATIONS_KEY = 'msa_event_registrations';

function readLocalEventRegistrations(): EventRegistrationStatus[] {
  try {
    const raw = localStorage.getItem(LOCAL_REGISTRATIONS_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
}

function writeLocalEventRegistrations(registrations: EventRegistrationStatus[]): void {
  localStorage.setItem(LOCAL_REGISTRATIONS_KEY, JSON.stringify(registrations));
}

export const websiteService = {
  async getHomepageData(): Promise<any> {
    const response = await api.get('/website/homepage');
    const homepage = response.data?.homepage ?? null;
    if (homepage?.hero?.background_image) {
      homepage.hero.background_image = resolvePublicImagePath(homepage.hero.background_image);
    }
    return homepage;
  },

  async getAnnouncements(): Promise<AnnouncementItem[]> {
    const response = await api.get('/website/announcements');
    return (response.data?.announcements ?? []).map((item: AnnouncementItem) => ({
      ...item,
      featured_image: item.featured_image ? resolvePublicImagePath(item.featured_image) : null,
    }));
  },

  async getEvents(): Promise<EventItem[]> {
    const response = await api.get('/website/events');
    return (response.data?.events ?? []).map((event: EventItem) => ({
      ...event,
      image: resolvePublicImagePath(event.image),
    }));
  },

  async getTeamMembers(): Promise<TeamMember[]> {
    try {
      const response = await api.get('/website/team');
      return resolveTeamMembers(response.data);
    } catch {
      return resolveTeamMembers(null);
    }
  },

  async getResources(): Promise<ResourceItem[]> {
    const response = await api.get('/website/resources');
    return (response.data?.resources ?? []).map((resource: ResourceItem) => ({
      ...resource,
      link: resource.link?.startsWith('/storage/') || resource.link?.startsWith('/uploads/')
        ? resolvePublicImagePath(resource.link)
        : resource.link,
      thumbnail: resource.thumbnail ? resolvePublicImagePath(resource.thumbnail) : null,
      file: resource.file ? resolvePublicImagePath(resource.file) : null,
    }));
  },

  async getMediaGallery(): Promise<MediaGalleryItem[]> {
    const response = await api.get('/website/media');
    return (response.data?.media ?? []).map((item: MediaGalleryItem) => ({
      ...item,
      url: resolvePublicImagePath(item.url),
    }));
  },

  async getSponsors(): Promise<SponsorItem[]> {
    const response = await api.get('/website/sponsors');
    return response.data?.sponsors ?? [];
  },

  async submitContact(data: ContactSubmission): Promise<{ success: boolean; message: string }> {
    const response = await api.post('/website/contact', data);
    return {
      success: true,
      message: response.data?.message || 'Your message has been sent successfully.',
    };
  },

  async submitSponsorApplication(data: SponsorSubmission): Promise<{ success: boolean; message: string }> {
    const response = await api.post('/website/sponsors', data);
    return {
      success: true,
      message: response.data?.message || 'Your sponsorship request has been received.',
    };
  },

  async submitVolunteerApplication(data: VolunteerSubmission): Promise<{ success: boolean; message: string }> {
    const response = await api.post('/website/volunteer', data);
    return {
      success: true,
      message: response.data?.message || 'Your application has been submitted successfully.',
    };
  },

  async subscribeNewsletter(email: string): Promise<{ success: boolean; message: string }> {
    const response = await api.post('/website/newsletter/subscribe', { email });
    return {
      success: response.data?.success ?? true,
      message: response.data?.message || 'Thank you for subscribing to our newsletter!',
    };
  },

  async submitEventRsvp(eventId: string, data: EventRsvpSubmission): Promise<EventRsvpResponse> {
    const response = await api.post(`/website/events/${eventId}/rsvp`, data);
    return {
      success: response.data?.success ?? true,
      message: response.data?.message || 'Registration successful! Check your email for confirmation.',
      spotsLeft: response.data?.spotsLeft,
      registrationId: response.data?.registrationId,
    };
  },

  async getMyEventRegistrations(): Promise<EventRegistrationStatus[]> {
    const response = await api.get('/website/events/registrations');
    return response.data?.registrations ?? [];
  },

  async cancelEventRsvp(eventId: string, registrationId?: string): Promise<EventRsvpResponse> {
    const response = await api.delete(`/website/events/${eventId}/rsvp`, {
      data: registrationId ? { registrationId } : undefined,
    });
    return {
      success: response.data?.success ?? true,
      message: response.data?.message || 'Your registration has been cancelled.',
      spotsLeft: response.data?.spotsLeft,
    };
  },

  saveLocalEventRegistration(registration: EventRegistrationStatus): void {
    const existing = readLocalEventRegistrations().filter((item) => item.eventId !== registration.eventId);
    writeLocalEventRegistrations([...existing, registration]);
  },

  removeLocalEventRegistration(eventId: string): void {
    writeLocalEventRegistrations(
      readLocalEventRegistrations().filter((item) => item.eventId !== eventId),
    );
  },

  getLocalEventRegistrations(): EventRegistrationStatus[] {
    return readLocalEventRegistrations();
  },
};

export default websiteService;
