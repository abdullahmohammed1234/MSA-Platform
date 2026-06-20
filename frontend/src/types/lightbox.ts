export interface LightboxImage {
  id: string;
  url: string;
  title?: string;
  subtitle?: string;
  description?: string;
  category?: string;
  date?: string;
  downloadFilename?: string;
}

export function urlToLightboxImage(
  url: string,
  index: number,
  options?: Partial<Pick<LightboxImage, 'title' | 'subtitle' | 'description' | 'category' | 'date'>>,
): LightboxImage {
  const filename = url.split('/').pop()?.split('?')[0] ?? `photo-${index}`;
  const derivedTitle = filename
    .replace(/\.[^.]+$/, '')
    .replace(/[-_+]+/g, ' ')
    .trim();

  return {
    id: `lightbox-${index}-${filename}`,
    url,
    title: options?.title ?? derivedTitle,
    subtitle: options?.subtitle,
    description: options?.description,
    category: options?.category,
    date: options?.date,
    downloadFilename: filename,
  };
}
