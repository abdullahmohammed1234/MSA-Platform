import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { isAcademyEnabled } from '../config/features';
import publicRoutes from '../router/public';
import { websiteService } from '../services/website/websiteService';

describe('Phase 12 — production readiness frontend invariants', () => {
  it('keeps Dawah Academy learner disabled by default', () => {
    expect(isAcademyEnabled).toBe(false);
    const example = readFileSync(resolve(__dirname, '../../.env.example'), 'utf8');
    expect(example).toMatch(/VITE_ACADEMY_ENABLED\s*=\s*false/);
  });

  it('routes public events to EMS and has no legacy CMS event client', () => {
    const parent = publicRoutes.find((r) => r.path === '/');
    const events = parent?.children?.find((c) => c.path === 'events');
    expect(events).toBeDefined();
    expect(websiteService).not.toHaveProperty('getEvents');
  });

  it('documents cPanel as sync-only with ops checklist reference', () => {
    const cpanel = readFileSync(resolve(__dirname, '../../../.cpanel.yml'), 'utf8');
    expect(cpanel).toContain('rsync');
    expect(cpanel).toContain('PRODUCTION_OPERATIONS_CHECKLIST');
    expect(cpanel).not.toMatch(/migrate --force/);
  });
});
