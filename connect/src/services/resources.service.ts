import apiClient from './api.ts';
import { supportService } from './support.service.ts';
import type { CommunityResource } from '../types/support.ts';

let cachedStatus: boolean | null = null;
let lastStatusChecked = 0;
const STATUS_CACHE_TTL_MS = 60000; // 1 minute

function getClientFlag(): boolean {
  try {
    const envVal =
      typeof import.meta !== 'undefined' && import.meta.env
        ? import.meta.env.VITE_ENABLE_RESOURCES
        : typeof process !== 'undefined'
          ? process.env?.VITE_ENABLE_RESOURCES
          : undefined;
    return envVal === 'true';
  } catch {
    return false;
  }
}

export const resourcesService = {
  /**
   * Determine if the Community Resources section is published and accessible.
   * By default, returns false unless BOTH the client environment toggle is enabled
   * AND the backend server feature flag reports enabled: true.
   */
  async isEnabled(): Promise<boolean> {
    const clientFlag = getClientFlag();
    if (!clientFlag) {
      return false;
    }

    const now = Date.now();
    if (cachedStatus !== null && now - lastStatusChecked < STATUS_CACHE_TTL_MS) {
      return cachedStatus;
    }

    try {
      const response = await supportService.getResourcesStatus();
      cachedStatus = !!response.enabled;
      lastStatusChecked = now;
      return cachedStatus;
    } catch {
      // In case of network errors or missing backend flag, fail closed (hidden)
      cachedStatus = false;
      return false;
    }
  },

  /**
   * Synchronous check based strictly on environment flag for fast route guards / nav visibility.
   */
  isClientFlagEnabled(): boolean {
    return getClientFlag();
  },

  /**
   * Clear cache for testing or re-evaluating state.
   */
  clearCache(): void {
    cachedStatus = null;
    lastStatusChecked = 0;
  },

  /**
   * Fetch published community resources.
   */
  async getResources(): Promise<CommunityResource[]> {
    const enabled = await this.isEnabled();
    if (!enabled) {
      return [];
    }

    const response = await apiClient.get<{ resources: CommunityResource[] }>('/website/resources');
    return response.data.resources || [];
  },
};

export default resourcesService;
