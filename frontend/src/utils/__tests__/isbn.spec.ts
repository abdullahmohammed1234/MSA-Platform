import { describe, expect, it } from 'vitest';
import {
  buildIsbnScannerQrbox,
  isBookIsbn13,
  isValidIsbn,
  normalizeIsbn,
  sanitizeScannedBarcode,
} from '../isbn';

describe('normalizeIsbn (aligned with BookMetadataService)', () => {
  it('strips hyphens, spaces, and uppercases X', () => {
    expect(normalizeIsbn(' 978-0-13-235088-4 ')).toBe('9780132350884');
    expect(normalizeIsbn('0 13 235088 2')).toBe('0132350882');
    expect(normalizeIsbn('0-8044-2957-x')).toBe('080442957X');
  });

  it('trims EAN+5 price extensions of length 18+', () => {
    expect(normalizeIsbn('978013235088451999')).toBe('9780132350884');
    expect(normalizeIsbn('9780132350884519999')).toBe('9780132350884');
    expect(normalizeIsbn('979123456789612345')).toBe('9791234567896');
  });

  it('does not truncate shorter non-extension payloads', () => {
    expect(normalizeIsbn('97801323508845')).toBe('97801323508845');
    expect(normalizeIsbn('9780132350884')).toBe('9780132350884');
  });
});

describe('isValidIsbn checksum validation', () => {
  it('accepts valid ISBN-13 978 and 979 codes', () => {
    expect(isValidIsbn('9780132350884')).toBe(true);
    expect(isValidIsbn('978-0-13-235088-4')).toBe(true);
    expect(isValidIsbn('9791234567896')).toBe(true);
  });

  it('accepts valid ISBN-10 including trailing X', () => {
    expect(isValidIsbn('0132350882')).toBe(true);
    expect(isValidIsbn('080442957X')).toBe(true);
  });

  it('rejects invalid checksums and lengths without weakening validation', () => {
    expect(isValidIsbn('9780132350880')).toBe(false);
    expect(isValidIsbn('12345')).toBe(false);
    expect(isValidIsbn('97801323508845')).toBe(false);
    expect(isValidIsbn('')).toBe(false);
  });

  it('validates EAN+5 payloads via the trimmed ISBN-13', () => {
    expect(isValidIsbn('978013235088451999')).toBe(true);
  });
});

describe('isBookIsbn13', () => {
  it('detects 978/979 ISBN-13 after normalization', () => {
    expect(isBookIsbn13('978-0-13-235088-4')).toBe(true);
    expect(isBookIsbn13('9791234567896')).toBe(true);
    expect(isBookIsbn13('0132350882')).toBe(false);
    expect(isBookIsbn13('MLIB-C-123')).toBe(false);
  });
});

describe('sanitizeScannedBarcode', () => {
  it('preserves MLIB copy barcodes', () => {
    expect(sanitizeScannedBarcode(' MLIB-C-ABC123 ')).toBe('MLIB-C-ABC123');
  });

  it('normalizes ISBN/EAN decodes including EAN+5', () => {
    expect(sanitizeScannedBarcode('978-0-13-235088-4')).toBe('9780132350884');
    expect(sanitizeScannedBarcode('978013235088451999')).toBe('9780132350884');
  });

  it('returns trimmed original for short non-ISBN payloads', () => {
    expect(sanitizeScannedBarcode(' AB12 ')).toBe('AB12');
  });
});

describe('buildIsbnScannerQrbox', () => {
  it('builds a wide short strip suitable for EAN-13', () => {
    const box = buildIsbnScannerQrbox(640, 360);
    expect(box.width).toBeGreaterThanOrEqual(280);
    expect(box.width).toBeLessThanOrEqual(640);
    expect(box.height).toBeGreaterThanOrEqual(80);
    expect(box.height).toBeLessThanOrEqual(120);
    expect(box.width / box.height).toBeGreaterThan(2);
  });

  it('never exceeds the viewfinder bounds', () => {
    const box = buildIsbnScannerQrbox(200, 100);
    expect(box.width).toBeLessThanOrEqual(200);
    expect(box.height).toBeLessThanOrEqual(100);
  });
});
