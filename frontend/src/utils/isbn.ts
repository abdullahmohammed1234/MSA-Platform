/**
 * Shared ISBN helpers aligned with backend BookMetadataService:
 * - strip non [0-9X] characters
 * - uppercase X
 * - trim EAN+5 (≥18 digits starting 978/979) to ISBN-13
 */

export function normalizeIsbn(isbn: string): string {
  let clean = isbn.trim().toUpperCase().replace(/[^0-9X]/gi, '');

  // EAN-13 + 5-digit price addon (and any longer 978/979 payloads)
  if (clean.length >= 18 && (clean.startsWith('978') || clean.startsWith('979'))) {
    clean = clean.substring(0, 13);
  }

  return clean;
}

export function isValidIsbn(isbn: string): boolean {
  const clean = normalizeIsbn(isbn);

  if (clean.length === 10) {
    let checksum = 0;
    for (let i = 0; i < 9; i++) {
      const digit = Number(clean[i]);
      if (!Number.isInteger(digit)) {
        return false;
      }
      checksum += digit * (10 - i);
    }
    const lastChar = clean[9];
    checksum += lastChar === 'X' ? 10 : Number(lastChar);
    return checksum % 11 === 0;
  }

  if (clean.length === 13) {
    if (!/^\d{13}$/.test(clean)) {
      return false;
    }
    let checksum = 0;
    for (let i = 0; i < 12; i++) {
      checksum += Number(clean[i]) * (i % 2 === 0 ? 1 : 3);
    }
    const checkDigit = (10 - (checksum % 10)) % 10;
    return checkDigit === Number(clean[12]);
  }

  return false;
}

/** Book-land ISBN-13 (978/979), after normalization. */
export function isBookIsbn13(isbn: string): boolean {
  const clean = normalizeIsbn(isbn);
  return clean.length === 13 && (clean.startsWith('978') || clean.startsWith('979'));
}

/**
 * Sanitize a live barcode decode for MLibMS consumers.
 * Preserves MLIB-* copy barcodes; normalizes ISBN/EAN payloads like the backend.
 */
export function sanitizeScannedBarcode(input: string): string {
  const trimmed = input.trim();
  if (!trimmed) {
    return '';
  }

  if (trimmed.toUpperCase().startsWith('MLIB-')) {
    return trimmed;
  }

  const normalized = normalizeIsbn(trimmed);
  if (normalized.length >= 10) {
    return normalized;
  }

  return trimmed;
}

/**
 * Wide, short scan strip optimized for horizontal EAN-13 ISBN barcodes.
 * Keeps quiet zones in-frame while limiting vertical crop noise.
 */
export function buildIsbnScannerQrbox(
  viewfinderWidth: number,
  viewfinderHeight: number,
): { width: number; height: number } {
  const width = Math.max(280, Math.floor(viewfinderWidth * 0.92));
  const height = Math.max(
    80,
    Math.min(120, Math.floor(viewfinderHeight * 0.28)),
  );

  return {
    width: Math.min(width, viewfinderWidth),
    height: Math.min(height, viewfinderHeight),
  };
}
