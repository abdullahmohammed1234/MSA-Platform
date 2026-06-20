import { test, expect } from '@playwright/test';

test.describe('Admin Operations', () => {
  test('Admin can manage courses, assign mentors, publish announcements, and view analytics', async ({ page }) => {
    // 1. Login
    await page.goto('/login');
    await page.fill('input[type="email"]', 'admin@example.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');

    // 2. Access Admin Panel
    await page.goto('/admin/courses');
    await expect(page.locator('h1')).toContainText('Course Management');

    // 3. Create Course
    await page.click('button:has-text("Create Course")');
    await page.fill('input[name="title"]', 'E2E New Course');
    await page.fill('textarea[name="description"]', 'E2E description of the new course.');
    await page.selectOption('select[name="difficulty"]', 'intermediate');
    await page.click('button:has-text("Save Draft")');
    await expect(page.locator('text=E2E New Course')).toBeVisible();

    // 4. Assign Mentor to User
    await page.goto('/admin/students');
    await page.click('text=Volunteer User');
    await page.click('button:has-text("Assign Mentor")');
    await page.selectOption('select[name="mentor_id"]', 'Mentor User');
    await page.click('button:has-text("Assign")');
    await expect(page.locator('text=Assigned Successfully')).toBeVisible();

    // 5. Publish Announcement
    await page.goto('/admin/cms');
    await page.click('button:has-text("New Announcement")');
    await page.fill('input[name="title"]', 'E2E Announcement');
    await page.fill('textarea[name="message"]', 'Attention all academy students!');
    await page.click('button:has-text("Publish")');
    await expect(page.locator('text=E2E Announcement')).toBeVisible();

    // 6. Review Analytics Dashboard
    await page.goto('/admin/analytics');
    await expect(page.locator('text=Aggregate Event Count')).toBeVisible();
    await expect(page.locator('text=Active Sessions')).toBeVisible();
  });
});
