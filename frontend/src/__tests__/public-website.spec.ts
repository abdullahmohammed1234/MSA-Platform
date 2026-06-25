import { describe, it, expect, vi, beforeEach } from 'vitest';
import { websiteService } from '../services/website/websiteService';
import { usePrayerTimes } from '../composables/usePrayerTimes';
import publicRoutes from '../router/public';

// Mock global fetch for prayer times composable test
const mockFetch = vi.fn();
vi.stubGlobal('fetch', mockFetch);

// Mock api client to force offline fallbacks
vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn().mockRejectedValue(new Error('Network Error')),
    post: vi.fn().mockRejectedValue(new Error('Network Error'))
  }
}));

describe('SFU MSA Public Routes Configuration', () => {
  it('should define all expected routes and redirects', () => {
    const parentRoute = publicRoutes.find(r => r.path === '/');
    expect(parentRoute).toBeDefined();
    
    const children = parentRoute?.children || [];
    const paths = children.map(c => c.path);

    expect(paths).toContain('');
    expect(paths).toContain('about');
    expect(paths).toContain('team');
    expect(paths).toContain('events');
    expect(paths).toContain('resources');
    expect(paths).toContain('contact');
    expect(paths).toContain('sponsors');
    expect(paths).toContain('donations');
    expect(paths).toContain('prayer');
    expect(paths).toContain('media');
    expect(paths).toContain('volunteer');
  });

  it('should have SEO metadata (title/desc) defined on public routes', () => {
    const parentRoute = publicRoutes.find(r => r.path === '/');
    const children = parentRoute?.children || [];
    
    // Check key routes have titles defined for SEO guard
    const homeRoute = children.find(c => c.path === '');
    expect(homeRoute?.meta?.title).toBe('Simon Fraser University MSA');

    const aboutRoute = children.find(c => c.path === 'about');
    expect(aboutRoute?.meta?.title).toContain('About Us');

    const prayerRoute = children.find(c => c.path === 'prayer');
    expect(prayerRoute?.meta?.title).toContain('Prayer Times');
  });
});

describe('Website API Service', () => {
  it('should reject announcements requests when API is offline', async () => {
    await expect(websiteService.getAnnouncements()).rejects.toThrow('Network Error');
  });

  it('should reject events requests when API is offline', async () => {
    await expect(websiteService.getEvents()).rejects.toThrow('Network Error');
  });

  it('should reject sponsors requests when API is offline', async () => {
    await expect(websiteService.getSponsors()).rejects.toThrow('Network Error');
  });
});

describe('Prayer Times Composable', () => {
  beforeEach(() => {
    mockFetch.mockReset();
  });

  it('should initialize with loading state', () => {
    mockFetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({ times: {} })
    });
    
    const { isLoading, error } = usePrayerTimes();
    expect(isLoading.value).toBe(true);
    expect(error.value).toBeNull();
  });
});
