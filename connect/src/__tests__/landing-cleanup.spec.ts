import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

test('MSA Connect Landing Page Visuals, Spacing & Invariants', async (t) => {
  const landingFilePath = resolve(import.meta.dirname, '../views/LandingView.vue');
  assert.ok(existsSync(landingFilePath), 'LandingView.vue must exist');

  const content = readFileSync(landingFilePath, 'utf-8');

  await t.test('Asset files book.png and mosque.png exist in connect/public', () => {
    const bookAssetPath = resolve(import.meta.dirname, '../../public/book.png');
    const mosqueAssetPath = resolve(import.meta.dirname, '../../public/mosque.png');
    assert.ok(existsSync(bookAssetPath), 'connect/public/book.png must exist');
    assert.ok(existsSync(mosqueAssetPath), 'connect/public/mosque.png must exist');
  });

  await t.test('Decorative crescents replace stars across landing page and background pattern', () => {
    // Background uses authentic crescent pattern
    assert.ok(
      content.includes('pattern-islamic-crescent'),
      'LandingView root must use pattern-islamic-crescent'
    );
    // Lucide Moon is imported and used as decorative element
    assert.ok(content.includes('Moon,'), 'Moon icon must be imported');
    assert.ok(content.includes('<Moon'), 'Moon component must be used for crescent decorations');
    // Stars are not used as decorative elements in LandingView
    assert.doesNotMatch(content, /IslamicStar/, 'IslamicStar component must not be used in LandingView');
    assert.doesNotMatch(content, /pattern-islamic-lattice/, 'pattern-islamic-lattice must not be used in LandingView');
  });

  await t.test('Section 1 book illustration is visible on contrasting container and sized appropriately', () => {
    assert.ok(content.includes("getPublicAssetUrl('book.png')"), 'Section 1 must reference book.png');
    // Book is placed in a contrasting container with dark gradient
    const heroSection = content.slice(0, content.indexOf('<!-- 2. Special Welcome'));
    assert.ok(
      heroSection.includes('bg-gradient-to-br from-[#640c0e]'),
      'Book illustration must be placed in contrasting dark burgundy container'
    );
    // Proportional sizing - not oversized
    assert.ok(heroSection.includes('w-36 sm:w-48'), 'Book illustration must have balanced responsive sizing');
    assert.ok(heroSection.includes('max-h-28 sm:max-h-32'), 'Book illustration height must be bounded');
  });

  await t.test('Restored "Every journey begins with a first step." section with compact spacing and original content', () => {
    assert.ok(
      content.includes('Every journey begins with a first step.'),
      'Must restore heading: "Every journey begins with a first step."'
    );
    assert.ok(
      content.includes('A Warm Welcome to New Muslims & Faith Seekers'),
      'Must restore welcome badge'
    );
    assert.ok(
      content.includes('If you have recently embraced Islam, are asking questions about the faith'),
      'Must restore welcome text'
    );
    assert.ok(content.includes('Gentle Pace'), 'Must restore Gentle Pace card');
    assert.ok(content.includes('Brothers & Sisters'), 'Must restore Brothers & Sisters card');
    assert.ok(content.includes('Campus Spaces'), 'Must restore Campus Spaces card');
    // Compact spacing applied
    assert.ok(content.includes('py-8 sm:py-12'), 'Section must use compact vertical padding');
    assert.ok(content.includes('max-w-5xl'), 'Section must use balanced max width');
  });

  await t.test('Section 2 mosque illustration is visible in normal rendering inside contrasting container', () => {
    assert.ok(content.includes("getPublicAssetUrl('mosque.png')"), 'Section 2 must reference mosque.png');
    const announcementsSection = content.slice(
      content.indexOf('<!-- 3. Campus Updates'),
      content.indexOf('<!-- 4. Community Pillars')
    );
    assert.ok(
      announcementsSection.includes('bg-gradient-to-br from-[#640c0e]'),
      'Mosque illustration must be placed in contrasting dark container'
    );
    assert.ok(
      announcementsSection.includes('w-28 sm:w-36') || announcementsSection.includes('w-36 sm:w-48'),
      'Mosque illustration must have bounded responsive sizing'
    );
  });

  await t.test('Section 3 CTA displays exactly two distinct illustrations side by side (no overlap)', () => {
    const ctaSection = content.slice(
      content.indexOf('Section 3 Final Sign-in'),
      content.indexOf('Stay Connected With Your Campus Community') + 300
    );
    assert.ok(ctaSection.includes("getPublicAssetUrl('book.png')"), 'Section 3 CTA must include book.png');
    assert.ok(ctaSection.includes("getPublicAssetUrl('mosque.png')"), 'Section 3 CTA must include mosque.png');
    // Side by side layout
    assert.ok(
      ctaSection.includes('flex items-center justify-center gap-4 sm:gap-8'),
      'Illustrations must be arranged horizontally side by side'
    );
    // Not overlapping or absolute positioned over each other
    assert.ok(
      !ctaSection.includes('absolute w-36 sm:w-44'),
      'Illustrations must not use absolute positioning layering over each other'
    );
  });

  await t.test('Four Pillars section retains exact heading, subtitle, and 4 pillar titles with descriptions removed', () => {
    assert.ok(
      content.includes('Four Pillars of Our Campus Community'),
      'Must preserve heading: "Four Pillars of Our Campus Community"'
    );
    assert.ok(
      content.includes('Designed to make student life enriching, supportive, and spiritually grounding.'),
      'Must preserve subtitle'
    );
    assert.ok(content.includes('Campus Gatherings & Jummah'), 'Must preserve pillar 1 title');
    assert.ok(content.includes('Peer Support & Circles'), 'Must preserve pillar 2 title');
    assert.ok(content.includes('Instant Event Wallet'), 'Must preserve pillar 3 title');
    assert.ok(content.includes('Unified Student Identity'), 'Must preserve pillar 4 title');
    assert.ok(content.includes('<Calendar'), 'Must preserve Calendar icon');
    assert.ok(content.includes('<HeartHandshake'), 'Must preserve HeartHandshake icon');
    assert.ok(content.includes('<Ticket'), 'Must preserve Ticket icon');
    assert.ok(content.includes('<ShieldCheck'), 'Must preserve ShieldCheck icon');

    // Descriptions removed
    assert.ok(
      !content.includes('Weekly Friday prayer schedules, study halaqahs, guest speakers, and community dinners.'),
      'Paragraph 1 must be removed'
    );
    assert.ok(
      !content.includes('Academic guidance, campus advice, and community connections.'),
      'Paragraph 2 must be removed'
    );
    assert.ok(
      !content.includes('Admission passes and mobile QR-code check-in.'),
      'Paragraph 3 must be removed'
    );
    assert.ok(
      !content.includes('Single sign-on, profile synchronization, and event history.'),
      'Paragraph 4 must be removed'
    );
  });

  await t.test('Public asset resolution helper is base-aware for /connect/ deployments', () => {
    assert.ok(
      content.includes('function getPublicAssetUrl(path: string): string'),
      'Must define getPublicAssetUrl helper'
    );
    assert.ok(
      content.includes('import.meta.env.BASE_URL'),
      'getPublicAssetUrl must use import.meta.env.BASE_URL to handle /connect/ base path'
    );
  });
});
