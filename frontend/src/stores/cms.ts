import { defineStore } from 'pinia';
import { ref } from 'vue';
import cmsService from '@/services/cms/cmsService';
import type { CmsSection, Media, CmsDashboardStats, CmsDashboardActivity } from '@/types/cms';

export const useCmsStore = defineStore('cms', () => {
  const sections = ref<CmsSection[]>([]);
  const mediaList = ref<Media[]>([]);
  const stats = ref<CmsDashboardStats | null>(null);
  const recentActivity = ref<CmsDashboardActivity[]>([]);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  const lastFetchedDashboard = ref<number | null>(null);
  const lastFetchedHomepage = ref<number | null>(null);
  const cacheDuration = 5 * 60 * 1000; // 5 minutes

  async function fetchDashboard(force = false) {
    const now = Date.now();
    if (!force && lastFetchedDashboard.value && (now - lastFetchedDashboard.value < cacheDuration) && stats.value) {
      return;
    }

    isLoading.value = true;
    error.value = null;
    try {
      const data = await cmsService.getDashboard();
      stats.value = data.stats;
      recentActivity.value = data.recentLogs;
      lastFetchedDashboard.value = now;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to load CMS dashboard statistics.';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchHomepageSections(force = false) {
    const now = Date.now();
    if (!force && lastFetchedHomepage.value && (now - lastFetchedHomepage.value < cacheDuration) && sections.value.length > 0) {
      return;
    }

    isLoading.value = true;
    error.value = null;
    try {
      sections.value = await cmsService.getHomepageSections();
      lastFetchedHomepage.value = now;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to load homepage sections.';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function updateHomepageSection(key: string, blocks: Record<string, string | null>) {
    isLoading.value = true;
    error.value = null;
    try {
      await cmsService.updateHomepageSection(key, blocks);
      lastFetchedHomepage.value = null; // invalidate
      await fetchHomepageSections(true);
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to save homepage edits.';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchMedia(params: any = {}) {
    isLoading.value = true;
    error.value = null;
    try {
      const response = await cmsService.getMedia(params);
      mediaList.value = response.data;
      return response;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to retrieve media library assets.';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function uploadMedia(file: File) {
    isLoading.value = true;
    error.value = null;
    try {
      const media = await cmsService.uploadMedia(file);
      mediaList.value.unshift(media);
      return media;
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to upload file.';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  async function deleteMedia(uuid: string) {
    isLoading.value = true;
    error.value = null;
    try {
      await cmsService.deleteMedia(uuid);
      mediaList.value = mediaList.value.filter(item => item.uuid !== uuid);
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to delete file.';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  return {
    sections,
    mediaList,
    stats,
    recentActivity,
    isLoading,
    error,
    fetchDashboard,
    fetchHomepageSections,
    updateHomepageSection,
    fetchMedia,
    uploadMedia,
    deleteMedia,
  };
});
