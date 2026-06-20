export interface ShareableImage {
  url: string;
  title?: string;
  text?: string;
}

function absoluteUrl(url: string): string {
  if (url.startsWith('http://') || url.startsWith('https://')) {
    return url;
  }
  return `${window.location.origin}${url.startsWith('/') ? url : `/${url}`}`;
}

function filenameFromUrl(url: string, fallback = 'sfu-msa-image'): string {
  const segment = url.split('/').pop()?.split('?')[0] ?? '';
  return segment || fallback;
}

export async function downloadImage(url: string, filename?: string): Promise<void> {
  const targetName = filename ?? filenameFromUrl(url);

  try {
    const response = await fetch(url);
    if (!response.ok) {
      throw new Error('Download failed');
    }

    const blob = await response.blob();
    const blobUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = targetName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(blobUrl);
  } catch {
    const link = document.createElement('a');
    link.href = absoluteUrl(url);
    link.download = targetName;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }
}

export async function shareImage(
  item: ShareableImage,
): Promise<'shared' | 'copied' | 'failed'> {
  const shareUrl = absoluteUrl(item.url);
  const sharePayload = {
    title: item.title ?? 'SFU MSA',
    text: item.text,
    url: shareUrl,
  };

  if (navigator.share) {
    try {
      await navigator.share(sharePayload);
      return 'shared';
    } catch (error) {
      if ((error as Error).name === 'AbortError') {
        return 'failed';
      }
    }
  }

  try {
    await navigator.clipboard.writeText(
      [sharePayload.title, sharePayload.text, shareUrl].filter(Boolean).join('\n'),
    );
    return 'copied';
  } catch {
    return 'failed';
  }
}
