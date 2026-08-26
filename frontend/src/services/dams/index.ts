/**
 * DAMS (Dawah Academy Management System) frontend services boundary.
 *
 * Admin HTTP contract remains `/api/v1/admin/academy/*` (Platform Sanctum).
 * Learner Academy clients stay under `@/services/academy/*`.
 * Course assets: `@/services/academy/academyAssetsService` (not CMS media).
 */

export { academyAssetsService } from '@/services/academy/academyAssetsService';
export { default } from '@/services/academy/academyAssetsService';

/** Marker for application ownership / Systems docs. */
export const DAMS_APP = {
  name: 'Dawah Academy Management System',
  slug: 'dams',
  frontendPath: '/dams',
  apiPrefix: '/api/v1/admin/academy',
} as const;
