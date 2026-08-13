/**
 * CMS media upload limits (kilobytes), aligned with backend `config/cms.php`.
 * Optional Vite overrides keep frontend/backend in sync without a new API.
 */
const parseKb = (value: unknown, fallback: number): number => {
  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : fallback;
};

export const CMS_MEDIA_MAX_IMAGE_KB = parseKb(
  import.meta.env.VITE_CMS_MEDIA_MAX_IMAGE_KB,
  10240,
);

export const CMS_MEDIA_MAX_VIDEO_KB = parseKb(
  import.meta.env.VITE_CMS_MEDIA_MAX_VIDEO_KB,
  51200,
);

export const CMS_MEDIA_MAX_DOCUMENT_KB = parseKb(
  import.meta.env.VITE_CMS_MEDIA_MAX_DOCUMENT_KB,
  10240,
);

const VIDEO_EXTENSIONS = new Set(['mp4', 'webm', 'mov', 'ogv']);
const IMAGE_EXTENSIONS = new Set(['jpeg', 'jpg', 'png', 'gif', 'svg', 'webp']);

export type CmsMediaFileKind = 'image' | 'video' | 'document';

export function getCmsMediaFileKind(file: File): CmsMediaFileKind {
  const mime = (file.type || '').toLowerCase();
  const extension = file.name.includes('.')
    ? file.name.split('.').pop()!.toLowerCase()
    : '';

  if (mime.startsWith('video/') || VIDEO_EXTENSIONS.has(extension)) {
    return 'video';
  }

  if (mime.startsWith('image/') || IMAGE_EXTENSIONS.has(extension)) {
    return 'image';
  }

  return 'document';
}

export function getCmsMediaMaxKb(kind: CmsMediaFileKind): number {
  if (kind === 'video') return CMS_MEDIA_MAX_VIDEO_KB;
  if (kind === 'image') return CMS_MEDIA_MAX_IMAGE_KB;
  return CMS_MEDIA_MAX_DOCUMENT_KB;
}

export function formatCmsMediaLimitMb(maxKb: number): string {
  const mb = maxKb / 1024;
  return Number.isInteger(mb) ? String(mb) : mb.toFixed(1);
}

/**
 * Returns a user-facing error when the file exceeds the configured limit,
 * or null when the upload may proceed to the API.
 */
export function validateCmsMediaFileSize(file: File): string | null {
  const kind = getCmsMediaFileKind(file);
  const maxKb = getCmsMediaMaxKb(kind);
  const sizeKb = Math.ceil(file.size / 1024);

  if (sizeKb > maxKb) {
    return `The ${kind} may not be greater than ${maxKb} kilobytes (${formatCmsMediaLimitMb(maxKb)} MB).`;
  }

  return null;
}
