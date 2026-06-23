/** Strip HTML tags and collapse whitespace for plain-text previews. */
export function stripHtml(value: string | null | undefined): string {
  if (!value) return '';

  return value
    .replace(/<br\s*\/?>/gi, ' ')
    .replace(/<\/(p|div|li|h[1-6])>/gi, ' ')
    .replace(/<[^>]*>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&quot;/gi, '"')
    .replace(/&#39;/gi, "'")
    .replace(/\s+/g, ' ')
    .trim();
}

/** Plain-text preview with optional character limit. */
export function textPreview(value: string | null | undefined, maxLength?: number): string {
  const plain = stripHtml(value);
  if (!maxLength || plain.length <= maxLength) return plain;
  return `${plain.slice(0, maxLength).trimEnd()}…`;
}
