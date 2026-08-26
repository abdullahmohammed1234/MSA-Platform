import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import publicRoutes from '../router/public';
import cmsRoutes from '../router/cms';
import damsRoutes from '../router/dams';
import emsRoutes from '../router/ems';
import adminRoutes from '../router/admin';
import { websiteService } from '../services/website/websiteService';
import { isAcademyEnabled } from '../config/features';

describe('Phase 10 — frontend architecture boundaries', () => {
  it('keeps application shells on dedicated prefixes', () => {
    expect(cmsRoutes.some((r) => r.path === '/cms')).toBe(true);
    expect(damsRoutes.some((r) => r.path === '/dams')).toBe(true);
    expect(emsRoutes.some((r) => r.path === '/ems' || String(r.path).startsWith('/ems'))).toBe(true);
    expect(adminRoutes.some((r) => r.path === '/admin')).toBe(true);
  });

  it('routes public events to EMS pages, not legacy CMS event pages', () => {
    const parent = publicRoutes.find((r) => r.path === '/');
    const events = parent?.children?.find((c) => c.path === 'events');
    expect(events).toBeDefined();
    expect(String(events?.component)).toMatch(/EmsPublicEventsPage|import\(/);
  });

  it('does not expose legacy CMS website event client methods', () => {
    expect(websiteService).not.toHaveProperty('getEvents');
    expect(websiteService).not.toHaveProperty('submitEventRsvp');
  });

  it('does not launch Academy by default', () => {
    expect(isAcademyEnabled).toBe(false);
  });

  it('AdminLayout opens applications rather than embedding them', () => {
    const layout = readFileSync(
      resolve(__dirname, '../layouts/AdminLayout.vue'),
      'utf8'
    );
    expect(layout).toContain("path: '/cms'");
    expect(layout).toContain("path: '/dams'");
    expect(layout).toContain("path: '/ems'");
    expect(layout).toContain('Open CMS');
    expect(layout).toContain('Open DAMS');
    expect(layout).toContain('Open EMS');
  });
});
