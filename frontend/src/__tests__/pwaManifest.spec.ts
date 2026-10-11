import { describe, it, expect } from 'vitest';
import fs from 'fs';
import path from 'path';

describe('PWA Manifest & Icon Declarations', () => {
  const publicDir = path.resolve(__dirname, '../../public');
  const manifestPath = path.join(publicDir, 'manifest.webmanifest');
  const manifestJsonPath = path.join(publicDir, 'manifest.json');

  it('should have valid manifest.webmanifest file', () => {
    expect(fs.existsSync(manifestPath)).toBe(true);
    const raw = fs.readFileSync(manifestPath, 'utf8');
    const manifest = JSON.parse(raw);

    expect(manifest.name).toBe('SFU Muslim Students Association');
    expect(manifest.short_name).toBe('SFU MSA');
    expect(manifest.start_url).toBe('/');
    expect(manifest.scope).toBe('/');
    expect(manifest.display).toBe('standalone');
    expect(manifest.theme_color).toBe('#640c0e');
    expect(manifest.background_color).toBe('#fffbf4');
    expect(Array.isArray(manifest.icons)).toBe(true);
    expect(manifest.icons.length).toBeGreaterThanOrEqual(3);
  });

  it('should have matching manifest.json compatibility file', () => {
    expect(fs.existsSync(manifestJsonPath)).toBe(true);
    const rawJson = fs.readFileSync(manifestJsonPath, 'utf8');
    const manifestJson = JSON.parse(rawJson);
    expect(manifestJson.name).toBe('SFU Muslim Students Association');
  });

  it('should verify all referenced PWA icon assets exist on disk', () => {
    const raw = fs.readFileSync(manifestPath, 'utf8');
    const manifest = JSON.parse(raw);

    manifest.icons.forEach((icon: { src: string }) => {
      const iconRelativePath = icon.src.replace(/^\//, '');
      const iconFullPath = path.join(publicDir, iconRelativePath);
      expect(fs.existsSync(iconFullPath)).toBe(true);
    });
  });
});
