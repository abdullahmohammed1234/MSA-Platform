import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useCoursesStore } from '../stores/academy/courses';
import { useCmsStore } from '../stores/cms';
import { useNotificationsStore } from '../stores/notifications';
import { coursesService } from '../services/academy/coursesService';
import cmsService from '../services/cms/cmsService';
import { notificationsService } from '../services/notifications';

describe('Frontend Performance & Client Store Caching', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.restoreAllMocks();
  });

  it('should cache courses listing in useCoursesStore to prevent duplicate requests', async () => {
    const mockCourses = [
      { id: 1, title: 'Course 1', slug: 'c-1', difficulty: 'beginner', status: 'published' }
    ];

    const getCoursesSpy = vi.spyOn(coursesService, 'getCourses').mockResolvedValue(mockCourses as any);
    const store = useCoursesStore();

    // First fetch: should trigger api request
    await store.fetchCourses();
    expect(getCoursesSpy).toHaveBeenCalledTimes(1);
    expect(store.courses).toEqual(mockCourses);

    // Second fetch: should load from memory store (no extra api call)
    await store.fetchCourses();
    expect(getCoursesSpy).toHaveBeenCalledTimes(1);

    // Force fetch: should bypass cache
    await store.fetchCourses(undefined, true);
    expect(getCoursesSpy).toHaveBeenCalledTimes(2);
  });

  it('should cache course details in useCoursesStore', async () => {
    const mockDetails = {
      course: { id: 1, title: 'Course 1', slug: 'c-1', difficulty: 'beginner', status: 'published' },
      enrollment: null,
      progress: null
    };

    const getDetailsSpy = vi.spyOn(coursesService, 'getCourseDetails').mockResolvedValue(mockDetails as any);
    const store = useCoursesStore();

    // First fetch: should trigger api request
    await store.fetchCourseDetails('c-1');
    expect(getDetailsSpy).toHaveBeenCalledTimes(1);

    // Second fetch: should load from memory store (no extra api call)
    await store.fetchCourseDetails('c-1');
    expect(getDetailsSpy).toHaveBeenCalledTimes(1);

    // Force fetch: should bypass cache
    await store.fetchCourseDetails('c-1', true);
    expect(getDetailsSpy).toHaveBeenCalledTimes(2);
  });

  it('should cache homepage sections and dashboard stats in useCmsStore', async () => {
    const mockSections = [
      { id: 1, key: 'hero', title: 'Hero', content: {} }
    ];
    const mockDashboard = {
      stats: {
        announcements: { total: 2, drafts: 1, published: 1 },
        events: { total: 1, upcoming: 1, past: 0 },
        team: { total: 3 },
        resources: { total: 5 }
      },
      recentLogs: []
    };

    const getSectionsSpy = vi.spyOn(cmsService, 'getHomepageSections').mockResolvedValue(mockSections as any);
    const getDashboardSpy = vi.spyOn(cmsService, 'getDashboard').mockResolvedValue(mockDashboard);
    
    const store = useCmsStore();

    // 1. Dashboard Cache Check
    await store.fetchDashboard();
    expect(getDashboardSpy).toHaveBeenCalledTimes(1);

    await store.fetchDashboard();
    expect(getDashboardSpy).toHaveBeenCalledTimes(1);

    await store.fetchDashboard(true);
    expect(getDashboardSpy).toHaveBeenCalledTimes(2);

    // 2. Homepage Sections Cache Check
    await store.fetchHomepageSections();
    expect(getSectionsSpy).toHaveBeenCalledTimes(1);

    await store.fetchHomepageSections();
    expect(getSectionsSpy).toHaveBeenCalledTimes(1);

    await store.fetchHomepageSections(true);
    expect(getSectionsSpy).toHaveBeenCalledTimes(2);
  });

  it('should cache user notification preferences in useNotificationsStore', async () => {
    const mockPrefs = {
      email_announcements: true,
      email_events: true,
      email_certificates: true,
      in_app_announcements: true,
      in_app_events: true,
      in_app_certificates: true
    };

    const getPrefsSpy = vi.spyOn(notificationsService, 'getPreferences').mockResolvedValue(mockPrefs);
    const store = useNotificationsStore();

    // First fetch: should trigger api request
    await store.fetchPreferences();
    expect(getPrefsSpy).toHaveBeenCalledTimes(1);
    expect(store.preferences).toEqual(mockPrefs);

    // Second fetch: should load from memory store (no extra api call)
    await store.fetchPreferences();
    expect(getPrefsSpy).toHaveBeenCalledTimes(1);

    // Force fetch: should bypass cache
    await store.fetchPreferences(true);
    expect(getPrefsSpy).toHaveBeenCalledTimes(2);
  });
});
