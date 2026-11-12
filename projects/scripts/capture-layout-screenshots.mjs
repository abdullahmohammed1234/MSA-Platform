import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(__dirname, '../docs/screenshots');
fs.mkdirSync(outDir, { recursive: true });

const base = process.env.PMS_PREVIEW_URL || 'http://127.0.0.1:5175';

const viewports = [
  { name: 'dashboard-1920x1080', width: 1920, height: 1080, url: `${base}/?preview=layout` },
  { name: 'dashboard-1440x900', width: 1440, height: 900, url: `${base}/?preview=layout` },
  { name: 'dashboard-1280x800', width: 1280, height: 800, url: `${base}/?preview=layout` },
  { name: 'dashboard-1024x768', width: 1024, height: 768, url: `${base}/?preview=layout` },
  { name: 'dashboard-768x1024', width: 768, height: 1024, url: `${base}/?preview=layout` },
  { name: 'dashboard-430x932', width: 430, height: 932, url: `${base}/?preview=layout` },
  { name: 'dashboard-414x896', width: 414, height: 896, url: `${base}/?preview=layout` },
  { name: 'dashboard-390x844', width: 390, height: 844, url: `${base}/?preview=layout` },
  { name: 'dashboard-375x812', width: 375, height: 812, url: `${base}/?preview=layout` },
  { name: 'dashboard-360x800', width: 360, height: 800, url: `${base}/?preview=layout` },
  { name: 'dashboard-447x818', width: 447, height: 818, url: `${base}/?preview=layout` },
  { name: 'dashboard-320x720', width: 320, height: 720, url: `${base}/?preview=layout` },
  { name: 'dashboard-empty-1440x900', width: 1440, height: 900, url: `${base}/?preview=layout&empty=1` },
  { name: 'login-390x844', width: 390, height: 844, url: `${base}/login` },
  { name: 'login-1440x900', width: 1440, height: 900, url: `${base}/login` },
];

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext();
const page = await context.newPage();

for (const vp of viewports) {
  await page.setViewportSize({ width: vp.width, height: vp.height });
  await page.goto(vp.url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(400);
  const dest = path.join(outDir, `${vp.name}.png`);
  await page.screenshot({ path: dest, fullPage: false });
  console.log(`wrote ${dest}`);
}

await browser.close();
console.log('done');
