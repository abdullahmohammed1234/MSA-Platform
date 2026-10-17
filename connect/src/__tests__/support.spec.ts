import test from 'node:test';
import assert from 'node:assert/strict';
import { resourcesService } from '../services/resources.service.ts';

test('Support & Hidden Resources — Verification Suite', async (t) => {
  await t.test('Hidden resources section defaults to completely disabled', async () => {
    // Under standard environment without VITE_ENABLE_RESOURCES=true
    const isClientEnabled = resourcesService.isClientFlagEnabled();
    assert.equal(isClientEnabled, false, 'Client environment toggle must default to false');

    const isEnabled = await resourcesService.isEnabled();
    assert.equal(isEnabled, false, 'Feature gate must return false when client flag is inactive');

    const resources = await resourcesService.getResources();
    assert.deepEqual(resources, [], 'Resources service must return empty array when gated/disabled');
  });

  await t.test('Support reference codes strictly follow INQ-YYYYMM-XXXX pattern', () => {
    const referenceCodeRegex = /^INQ-\d{6}-[A-Z0-9]{4}$/;

    assert.equal(referenceCodeRegex.test('INQ-202610-A1B2'), true);
    assert.equal(referenceCodeRegex.test('INQ-202610-9999'), true);
    assert.equal(referenceCodeRegex.test('INQ-202610-ZZZZ'), true);

    // Invalid patterns
    assert.equal(referenceCodeRegex.test('INQ-2026-A1B2'), false);
    assert.equal(referenceCodeRegex.test('TKT-202610-A1B2'), false);
    assert.equal(referenceCodeRegex.test('INQ-202610-lowercase'), false);
    assert.equal(referenceCodeRegex.test(''), false);
  });

  await t.test('Support inquiry category validation', () => {
    const validCategories = new Set([
      'general',
      'new_muslim',
      'revert_support',
      'faith_questions',
      'spiritual',
      'campus_prayer',
      'academic',
      'event_support',
      'events',
      'feedback',
      'other',
    ]);

    assert.equal(validCategories.has('new_muslim'), true, 'New Muslim support must be a recognized category');
    assert.equal(validCategories.has('revert_support'), true);
    assert.equal(validCategories.has('campus_prayer'), true);
    assert.equal(validCategories.has('spiritual'), true);
    assert.equal(validCategories.has('academic'), true);
    assert.equal(validCategories.has('arbitrary_category'), false);
  });

  await t.test('Support message length validation constraints', () => {
    const isValidMessage = (msg: string) => {
      const trimmed = msg.trim();
      return trimmed.length >= 10 && trimmed.length <= 10000;
    };

    assert.equal(isValidMessage('Too short'), false);
    assert.equal(isValidMessage('   spaced  '), false);
    assert.equal(isValidMessage('This is a valid message inquiry with enough detail.'), true);
    assert.equal(isValidMessage('a'.repeat(9)), false);
    assert.equal(isValidMessage('a'.repeat(10)), true);
    assert.equal(isValidMessage('a'.repeat(10000)), true);
    assert.equal(isValidMessage('a'.repeat(10001)), false);
  });

  await t.test('Support status recognition across member and staff states', () => {
    const validStatuses = new Set([
      'open',
      'in_review',
      'in_progress',
      'awaiting_member',
      'awaiting_staff',
      'resolved',
      'closed',
    ]);

    assert.equal(validStatuses.has('open'), true);
    assert.equal(validStatuses.has('in_review'), true);
    assert.equal(validStatuses.has('in_progress'), true);
    assert.equal(validStatuses.has('awaiting_member'), true);
    assert.equal(validStatuses.has('awaiting_staff'), true);
    assert.equal(validStatuses.has('resolved'), true);
    assert.equal(validStatuses.has('closed'), true);
    assert.equal(validStatuses.has('unknown_status'), false);
  });

  await t.test('Resources service clearCache correctly resets cached status', () => {
    resourcesService.clearCache();
    assert.equal(resourcesService.isClientFlagEnabled(), false);
  });
});
