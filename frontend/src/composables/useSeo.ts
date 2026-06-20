import { watchEffect } from 'vue';

interface SeoParams {
  title?: string;
  description?: string;
  keywords?: string;
  ogImage?: string;
  canonical?: string;
}

export function useSeo(params: SeoParams | (() => SeoParams)) {
  watchEffect(() => {
    const resolved = typeof params === 'function' ? params() : params;
    
    // Update Document Title
    if (resolved.title) {
      document.title = resolved.title;
    }
    
    // Update Meta Description
    let descMeta = document.querySelector('meta[name="description"]');
    if (!descMeta) {
      descMeta = document.createElement('meta');
      descMeta.setAttribute('name', 'description');
      document.head.appendChild(descMeta);
    }
    descMeta.setAttribute('content', resolved.description || 'A unified scholastic digital platform for Simon Fraser University Muslims, housing religious academic learning courses, volunteer outreach coordinates, and student assistance tools.');

    // Update Open Graph Title
    let ogTitle = document.querySelector('meta[property="og:title"]');
    if (!ogTitle) {
      ogTitle = document.createElement('meta');
      ogTitle.setAttribute('property', 'og:title');
      document.head.appendChild(ogTitle);
    }
    ogTitle.setAttribute('content', resolved.title || 'Simon Fraser University MSA');

    // Update Open Graph Image
    let ogImg = document.querySelector('meta[property="og:image"]');
    if (!ogImg) {
      ogImg = document.createElement('meta');
      ogImg.setAttribute('property', 'og:image');
      document.head.appendChild(ogImg);
    }
    ogImg.setAttribute('content', resolved.ogImage || '/logo.webp');
    
    // Update Canonical URL
    let canonical = document.querySelector('link[rel="canonical"]');
    if (!canonical) {
      canonical = document.createElement('link');
      canonical.setAttribute('rel', 'canonical');
      document.head.appendChild(canonical);
    }
    canonical.setAttribute('href', resolved.canonical || window.location.href);
  });
}
