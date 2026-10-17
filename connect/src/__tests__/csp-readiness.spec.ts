import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// Conditions that must hold before public/.htaccess switches from
// Content-Security-Policy-Report-Only to an enforced Content-Security-Policy.
// Set CSP_DIST_DIR to a build output directory to also scan the built files.

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const htaccess = fs.readFileSync(path.join(projectRoot, 'public/.htaccess'), 'utf-8');

function spaPolicy(): Map<string, string[]> {
  const match = htaccess.match(/Header set Content-Security-Policy(?:-Report-Only)? "([^"]+)"/);
  assert.ok(match, 'SPA CSP header missing from public/.htaccess');
  const directives = new Map<string, string[]>();
  for (const part of match[1].split(';')) {
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

test('Connect CSP enforcement readiness', async (t) => {
  await t.test('script policy allows only same-origin files', () => {
    const policy = spaPolicy();
    assert.deepEqual(policy.get('script-src'), ["'self'"]);
    assert.deepEqual(policy.get('object-src'), ["'none'"]);
    assert.deepEqual(policy.get('base-uri'), ["'self'"]);
    assert.deepEqual(policy.get('worker-src'), ["'self'"]);
    for (const sources of policy.values()) {
      assert.ok(!sources.includes("'unsafe-eval'"));
    }
  });

  await t.test('source index.html has no inline script, handler or javascript: URL', () => {
    const html = fs.readFileSync(path.join(projectRoot, 'index.html'), 'utf-8');
    assert.deepEqual(inlineScriptProblems(html), []);
  });

  const distDir = process.env.CSP_DIST_DIR;

  await t.test('built output has no inline script or eval', { skip: !distDir && 'CSP_DIST_DIR not set' }, () => {
    const htmlFiles = walk(distDir!, '.html');
    assert.ok(htmlFiles.length > 0);
    for (const file of htmlFiles) {
      assert.deepEqual(inlineScriptProblems(fs.readFileSync(file, 'utf-8')), [], file);
    }
    for (const file of walk(distDir!, '.js')) {
      assert.ok(!/\beval\(|new Function\(/.test(fs.readFileSync(file, 'utf-8')), file);
    }
  });
});
