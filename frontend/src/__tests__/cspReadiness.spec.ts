import { describe, it, expect } from 'vitest';
import fs from 'fs';
import path from 'path';

// Conditions that must hold before public/.htaccess switches from
// Content-Security-Policy-Report-Only to an enforced Content-Security-Policy.
// Set CSP_DIST_DIR to a build output directory to also scan the built files.

const projectRoot = path.resolve(__dirname, '../..');
const htaccess = fs.readFileSync(path.join(projectRoot, 'public/.htaccess'), 'utf8');

function spaPolicy(): Map<string, string[]> {
  const match = htaccess.match(/Header set Content-Security-Policy(?:-Report-Only)? "([^"]+)"/);
  expect(match, 'SPA CSP header missing from public/.htaccess').not.toBeNull();
  const directives = new Map<string, string[]>();
  for (const part of match![1].split(';')) {
    const [name, ...sources] = part.trim().split(/\s+/);
    if (name) directives.set(name, sources);
  }
  return directives;
}

function inlineScriptProblems(html: string): string[] {
  const problems: string[] = [];
  for (const tag of html.match(/<script\b[^>]*>/gi) ?? []) {
    if (!/\bsrc\s*=/.test(tag) && !/type\s*=\s*["']?application\/(ld\+)?json/i.test(tag)) problems.push(tag);
  }
  problems.push(...(html.match(/\son[a-z]+\s*=/gi) ?? []));
  problems.push(...(html.match(/javascript:/gi) ?? []));
  return problems;
}

function walk(dir: string, ext: string): string[] {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) return walk(full, ext);
    return entry.name.endsWith(ext) ? [full] : [];
  });
}

describe('SPA CSP enforcement readiness', () => {
  it('script policy allows only same-origin files', () => {
    const policy = spaPolicy();
    expect(policy.get('script-src')).toEqual(["'self'"]);
    expect(policy.get('object-src')).toEqual(["'none'"]);
    expect(policy.get('base-uri')).toEqual(["'self'"]);
    for (const sources of policy.values()) {
      expect(sources).not.toContain("'unsafe-eval'");
    }
  });

  it('framing is blocked by an enforced header, not only the report-only policy', () => {
    expect(htaccess).toMatch(/^\s*Header set X-Frame-Options "SAMEORIGIN"\s*$/m);
  });

  it('refuses dotfiles and build leftovers before the SPA fallback, but keeps ACME reachable', () => {
    const rules = [...htaccess.matchAll(/^\s*RewriteRule (\S+) (\S+) \[([^\]]+)\]/gm)];
    const forbidden = rules.filter((rule) => rule[2] === '-' && rule[3].split(',').includes('F'));
    const fallback = rules.findIndex((rule) => rule[2] === '/index.html');
    expect(forbidden.length).toBeGreaterThan(0);
    expect(forbidden.every((rule) => rules.indexOf(rule) < fallback)).toBe(true);

    // Per-directory RewriteRule patterns see the path without its leading slash.
    const denied = (urlPath: string) =>
      forbidden.some((rule) => new RegExp(rule[1], rule[3].includes('NC') ? 'i' : '').test(urlPath));
    for (const blocked of ['.env', '.git/config', 'assets/.env.production', 'assets/index-abc.js.map', 'backup.SQL', 'error.log', 'site.zip']) {
      expect(denied(blocked), blocked).toBe(true);
    }
    for (const allowed of ['.well-known/acme-challenge/token', 'index.html', 'assets/index-abc.js', 'events/eid-al-adha']) {
      expect(denied(allowed), allowed).toBe(false);
    }
  });

  it('source index.html has no inline script, handler or javascript: URL', () => {
    const html = fs.readFileSync(path.join(projectRoot, 'index.html'), 'utf8');
    expect(inlineScriptProblems(html)).toEqual([]);
  });

  it('frame-src covers the embed host LessonVideoPlayer generates', () => {
    const player = fs.readFileSync(path.join(projectRoot, 'src/components/lms/LessonVideoPlayer.vue'), 'utf8');
    expect(player).toContain('https://www.youtube.com/embed/');
    expect(spaPolicy().get('frame-src')).toContain('https://www.youtube.com');
  });

  const distDir = process.env.CSP_DIST_DIR;

  it.runIf(Boolean(distDir))('built output has no inline script or eval', () => {
    const htmlFiles = walk(distDir!, '.html');
    expect(htmlFiles.length).toBeGreaterThan(0);
    for (const file of htmlFiles) {
      expect(inlineScriptProblems(fs.readFileSync(file, 'utf8')), file).toEqual([]);
    }
    for (const file of walk(distDir!, '.js')) {
      const js = fs.readFileSync(file, 'utf8');
      expect(/\beval\(|new Function\(/.test(js), file).toBe(false);
    }
  });
});
