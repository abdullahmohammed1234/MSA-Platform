import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '../..');

test('MSA Projects PWA Web App Manifest Verification', async (t) => {
  const manifestPath = path.join(projectRoot, 'public', 'manifest.webmanifest');
  const manifestJsonPath = path.join(projectRoot, 'public', 'manifest.json');
  const swPath = path.join(projectRoot, 'public', 'sw.js');
  const offlinePath = path.join(projectRoot, 'public', 'offline.html');

  await t.test('manifest, service worker, and offline page exist', () => {
    assert.ok(fs.existsSync(manifestPath), 'manifest.webmanifest must exist');
    assert.ok(fs.existsSync(manifestJsonPath), 'manifest.json must exist');
    assert.ok(fs.existsSync(swPath), 'sw.js must exist');
    assert.ok(fs.existsSync(offlinePath), 'offline.html must exist');
  });

  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf-8'));

  await t.test('manifest contains required PWA identification fields', () => {
    assert.equal(manifest.name, 'SFU MSA Project Management System');
    assert.equal(manifest.short_name, 'MSA PMS');
    assert.equal(manifest.id, '/?source=pwa');
    assert.equal(manifest.start_url, '/');
    assert.equal(manifest.scope, '/');
    assert.equal(manifest.display, 'standalone');
  });

  await t.test('manifest branding colors match SFU MSA brand guidelines', () => {
    assert.equal(manifest.theme_color.toLowerCase(), '#640c0e');
    assert.equal(manifest.background_color.toLowerCase(), '#fffbf4');
  });

  await t.test('manifest icons are defined and point to existing files on disk', () => {
    assert.ok(Array.isArray(manifest.icons), 'icons must be an array');
    assert.ok(manifest.icons.length >= 4, 'must have at least 4 icon definitions');

    const iconSizes = manifest.icons.map((icon: { sizes: string }) => icon.sizes);
    assert.ok(iconSizes.includes('192x192'), 'must have 192x192 icon');
    assert.ok(iconSizes.includes('512x512'), 'must have 512x512 icon');

    const hasMaskable = manifest.icons.some(
      (icon: { purpose?: string }) => icon.purpose && icon.purpose.includes('maskable')
    );
    assert.ok(hasMaskable, 'must include maskable icon definition');

    for (const icon of manifest.icons) {
      const relativePath = icon.src.replace(/^\//, '');
      const fullPath = path.join(projectRoot, 'public', relativePath);
      assert.ok(fs.existsSync(fullPath), `Icon file ${icon.src} must exist at ${fullPath}`);
      const stats = fs.statSync(fullPath);
      assert.ok(stats.size > 0, `Icon file ${icon.src} must not be empty`);
    }
  });

  await t.test('service worker uses conservative offline policy markers', () => {
    const sw = fs.readFileSync(swPath, 'utf-8');
    assert.ok(sw.includes('msa-pms-shell-v1'), 'versioned cache name required');
    assert.ok(sw.includes('api.sfumsa.ca'), 'API host must be excluded from caching');
    assert.ok(sw.includes('/offline.html'), 'offline fallback required');
    assert.ok(sw.includes('SKIP_WAITING'), 'update activation message required');
  });
});
