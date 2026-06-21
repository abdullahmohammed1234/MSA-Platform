/** Public folder layout: Hero/, Media/, Team/, plus logo files at root. */

export const PUBLIC_LOGO = '/logo.webp';
export const PUBLIC_LOGO_PNG = '/logo.PNG';
export const TEAM_FALLBACK_IMAGE = '/Team/Sample_User_Icon.webp';

export const HERO_IMAGES = {
  foto2: '/Hero/FOTO2.webp',
  dsc0516: '/Hero/DSC_0516.webp',
  jumuahPrayer: '/Hero/jumuahprayer.webp',
  msaSs2024_29: '/Hero/MSA_SS_2024-29.webp',
  msaSs2024_76: '/Hero/MSA_SS_2024-76.webp',
  msaSs2024_87: '/Hero/MSA_SS_2024-87.webp',
  sfuMsaFnd2024_38: '/Hero/SFU_MSA_FND_2024-38.webp',
  juneMsa3: '/Hero/23+June+MSA-3.webp',
  teamPhoto: '/Hero/Team+Photo.webp',
} as const;

const HERO_FILENAMES = new Set([
  '23+June+MSA-3.webp',
  'DSC_0516.webp',
  'FOTO2.webp',
  'jumuahprayer.webp',
  'MSA_SS_2024-29.webp',
  'MSA_SS_2024-76.webp',
  'MSA_SS_2024-87.webp',
  'SFU_MSA_FND_2024-38.webp',
  'Team+Photo.webp',
  'WhatsApp+Image+2024-03-22+at+12.43.53+PM.webp',
]);

const TEAM_FILENAMES = new Set([
  'Abdullah+Elboraei.webp',
  'Ahad+Ali.webp',
  'Amna+Siddiqui.webp',
  'Chorouk_Rafi.webp',
  'Fatima+hayat.webp',
  'Hafsa+Irshad.webp',
  'Hammad+Zaidi.webp',
  'hamza.webp',
  'Leena_Kanadil.webp',
  'Mahnoor+Wasim.webp',
  'Manal_Fatima.webp',
  'Maryam+Shah.webp',
  'Omar+Salem.webp',
  'Rassell+Labash.webp',
  'Rumejsa+Rexhaj.webp',
  'Sample_User_Icon.webp',
  'sharon.webp',
  'Toqa+Abu+Alkas.webp',
  'Wajihah+Malik.webp',
  'Zakariya+Hamdy.webp',
  'Zayed+Khan.webp',
  'Zulkifl+Bin+Anowar.webp',
]);

export function heroImage(filename: string): string {
  const clean = filename.replace(/^\/?Hero\//, '').replace(/^\//, '');
  return `/Hero/${clean}`;
}

export function teamImage(filename: string): string {
  const clean = filename.replace(/^\/?Team\//, '').replace(/^\//, '');
  return `/Team/${clean}`;
}

export function mediaImage(category: string, filename: string): string {
  const categoryPath = category.split('/').map(encodeURIComponent).join('/');
  const filePath = filename.split('/').map(encodeURIComponent).join('/');
  return `/Media/${categoryPath}/${filePath}`;
}

import { getApiOrigin as resolveApiOrigin, rewriteLocalhostUrl } from '@/config/api.js';

/** Re-export for callers that resolve CMS/storage URLs against the API host. */
export function getApiOrigin(): string {
  return resolveApiOrigin();
}

/** Resolve CMS uploads and storage paths to a loadable URL (API origin in dev). */
export function resolveCmsMediaUrl(path: string | null | undefined): string {
  if (!path) {
    return '';
  }

  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) {
    if (/^https?:\/\/localhost(?::\d+)?/i.test(path)) {
      return rewriteLocalhostUrl(path);
    }
    return path;
  }

  const normalized = path.startsWith('/') ? path : `/${path}`;

  if (normalized.startsWith('/storage/') || normalized.startsWith('/uploads/')) {
    const storagePath = normalized.startsWith('/uploads/')
      ? `/storage${normalized}`
      : normalized;
    return `${getApiOrigin()}${storagePath}`;
  }

  return normalized;
}

/** Map legacy root-level public paths to Hero/ or Team/ folders. */
export function resolvePublicImagePath(path: string): string {
  if (!path || path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) {
    return path ? resolveCmsMediaUrl(path) : path;
  }

  const normalized = path.startsWith('/') ? path : `/${path}`;

  if (normalized.startsWith('/storage/') || normalized.startsWith('/uploads/')) {
    return resolveCmsMediaUrl(normalized);
  }

  if (
    normalized.startsWith('/Hero/')
    || normalized.startsWith('/Team/')
    || normalized.startsWith('/Media/')
    || normalized === PUBLIC_LOGO
    || normalized === PUBLIC_LOGO_PNG
  ) {
    return normalized;
  }

  const filename = normalized.slice(1);

  if (TEAM_FILENAMES.has(filename)) {
    return teamImage(filename);
  }

  if (HERO_FILENAMES.has(filename)) {
    return heroImage(filename);
  }

  return normalized;
}
