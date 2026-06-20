import { mediaImage } from '@/constants/publicAssets';

export interface MediaGalleryItem {
  id: string;
  url: string;
  category: string;
  title: string;
  description: string;
  date: string;
  isLandscape?: boolean;
}

function titleFromFilename(filename: string): string {
  const base = filename.replace(/\.[^.]+$/, '');
  return base.replace(/[-_+]+/g, ' ').trim();
}

function createItem(
  category: string,
  filename: string,
  index: number,
  options?: { title?: string; description?: string; date?: string; isLandscape?: boolean },
): MediaGalleryItem {
  return {
    id: `${category}-${index}-${filename}`,
    url: mediaImage(category, filename),
    category,
    title: options?.title ?? titleFromFilename(filename),
    description: options?.description ?? `Moments from ${category}.`,
    date: options?.date ?? '2026',
    isLandscape: options?.isLandscape,
  };
}

/** Gallery sourced from public/Media — one folder per category. */
export const MEDIA_GALLERY: MediaGalleryItem[] = [
  ...[
    'Untitled-2.jpeg',
    'Untitled-3.jpeg',
    'Untitled-4.jpeg',
    'Untitled-5.jpeg',
  ].map((filename, index) =>
    createItem('Eid Al fitr 2026', filename, index, {
      title: `Eid Al Fitr 2026 ${index + 1}`,
      description: 'Celebrating Eid Al Fitr with the SFU MSA community.',
      date: 'Apr 2026',
    }),
  ),
  ...[
    'DSC06917.jpeg',
    'DSC06936.jpeg',
    'DSC06938.jpeg',
    'DSC06939.jpeg',
    'DSC06941.jpeg',
  ].map((filename, index) =>
    createItem('Fundraiser', filename, index, {
      description: 'Highlights from MSA fundraiser events.',
      date: '2024',
    }),
  ),
  ...[
    'DSC09214.jpeg',
    'DSC09216.jpeg',
    'DSC09220.jpeg',
    'DSC09245.jpeg',
  ].map((filename, index) =>
    createItem('IAW', filename, index, {
      description: 'Islam Awareness Week at SFU.',
      date: '2024',
    }),
  ),
];

export const MEDIA_CATEGORIES = [
  'All',
  ...Array.from(new Set(MEDIA_GALLERY.map((item) => item.category))),
];

/** Browser-displayable gallery items (excludes raw camera formats like .ARW). */
export const DISPLAYABLE_MEDIA_GALLERY = MEDIA_GALLERY.filter((item) =>
  /\.(webp|jpe?g|png|gif|avif|svg)$/i.test(item.url),
);
