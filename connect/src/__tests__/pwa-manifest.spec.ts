import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '../..');

test('MSA Connect PWA Web App Manifest Verification', async (t) => {
  const manifestPath = path.join(projectRoot, 'public', 'manifest.webmanifest');
  const manifestJsonPath = path.join(projectRoot, 'public', 'manifest.json');

  await t.test('manifest.webmanifest and manifest.json exist and are valid JSON', () => {
    assert.ok(fs.existsSync(manifestPath), 'manifest.webmanifest must exist');
    assert.ok(fs.existsSync(manifestJsonPath), 'manifest.json must exist');

    const rawWebmanifest = fs.readFileSync(manifestPath, 'utf-8');
    const rawJson = fs.readFileSync(manifestJsonPath, 'utf-8');

    assert.doesNotThrow(() => JSON.parse(rawWebmanifest), 'manifest.webmanifest must be valid JSON');
    assert.doesNotThrow(() => JSON.parse(rawJson), 'manifest.json must be valid JSON');
  });

  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf-8'));

  await t.test('manifest contains required PWA identification fields', () => {
    assert.equal(manifest.name, 'SFU MSA Connect');
    assert.equal(manifest.short_name, 'MSA Connect');
    assert.equal(manifest.id, '/?source=pwa');
    assert.equal(manifest.start_url, '/');
    assert.equal(manifest.scope, '/');
    assert.equal(manifest.display, 'standalone');
  });

  await t.test('manifest branding colors match SFU MSA brand guidelines', () => {
    // SFU MSA brand: burgundy primary #640c0e, cream background #fffbf4
    assert.equal(manifest.theme_color.toLowerCase(), '#640c0e');
    assert.equal(manifest.background_color.toLowerCase(), '#fffbf4');
  });

  await t.test('manifest icons are defined and point to existing files on disk', () => {
    assert.ok(Array.isArray(manifest.icons), 'icons must be an array');
    assert.ok(manifest.icons.length >= 4, 'must have at least 4 icon definitions');

    const iconSizes = manifest.icons.map((icon: any) => icon.sizes);
    assert.ok(iconSizes.includes('192x192'), 'must have 192x192 icon');
    assert.ok(iconSizes.includes('512x512'), 'must have 512x512 icon');

    const hasMaskable = manifest.icons.some(
      (icon: any) => icon.purpose && icon.purpose.includes('maskable')
    );
    assert.ok(hasMaskable, 'must include maskable icon definition for Android adaptiveness');

    // Verify each referenced icon file exists in the public directory
    for (const icon of manifest.icons) {
      const relativePath = icon.src.replace(/^\//, '');
      const fullPath = path.join(projectRoot, 'public', relativePath);
      assert.ok(fs.existsSync(fullPath), `Icon file ${icon.src} must exist at ${fullPath}`);
      const stats = fs.statSync(fullPath);
      assert.ok(stats.size > 0, `Icon file ${icon.src} must not be empty`);
    }
  });

  await t.test('manifest includes orientation, description, and categories', () => {
    assert.ok(typeof manifest.description === 'string' && manifest.description.length > 0);
    assert.ok(Array.isArray(manifest.categories) && manifest.categories.length > 0);
    assert.ok(['any', 'portrait-primary', 'portrait'].includes(manifest.orientation));
  });
});
