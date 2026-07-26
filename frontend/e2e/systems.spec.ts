import { test, expect } from '@playwright/test';

test.describe('Systems Administration Console Validation', () => {
  test('can load Main Website System dashboard, view details, and update config', async ({ page }) => {
    // 1. Authenticate as Super Administrator
    await page.goto('/login');
    await page.fill('input[type="email"]', 'superadmin@example.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');

    // Wait for the dashboard to render, ensuring login has completed successfully
    await expect(page.locator('text=Admin Overview')).toBeVisible({ timeout: 15000 });

    // 2. Go to Main Website System Page
    await page.goto('/admin/systems/main-website');
    await expect(page.locator('h1')).toContainText('Main Website & CMS');

    // Verify Tab: Overview details are rendered
    await expect(page.locator('text=System Oversight Overview')).toBeVisible();
    await expect(page.locator('text=CMS Engine')).toBeVisible();

    // Verify Tab: System Health (give it extra timeout for disk/database checks to complete)
    await page.click('button:has-text("System Health")');
    await expect(page.locator('text=API Gateway')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('text=Database Connection')).toBeVisible();

    // Verify Tab: CMS & Public Metrics
    await page.click('button:has-text("CMS & Public Metrics")');
    await expect(page.locator('text=Total Announcements')).toBeVisible();
    await expect(page.locator('text=Team Directory')).toBeVisible();

    // Verify Tab: Integrations check
    await page.click('button:has-text("Integrations check")');
    await expect(page.locator('text=SMTP Outbound Mailer')).toBeVisible();

    // Verify Tab: Configuration settings
    await page.click('button:has-text("Configuration settings")');
    await expect(page.locator('text=Website configurations')).toBeVisible();

    // Change site name and save
    const inputSiteName = page.locator('label:has-text("Website name") + input');
    await inputSiteName.fill('SFU MSA Custom Automated Test Name');
    await page.click('button:has-text("Save configs")');

    // Verify success feedback
    await expect(page.locator('text=Configurations updated successfully')).toBeVisible();
  });

  test('can load Dawah Academy System dashboard, view details, and update config', async ({ page }) => {
    // 1. Authenticate as Super Administrator
    await page.goto('/login');
    await page.fill('input[type="email"]', 'superadmin@example.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');

    // Wait for the dashboard to render, ensuring login has completed successfully
    await expect(page.locator('text=Admin Overview')).toBeVisible({ timeout: 15000 });

    // 2. Go to Dawah Academy System Page
    await page.goto('/admin/systems/dawah-academy');
    await expect(page.locator('h1')).toContainText('Dawah Academy Portal');

    // Verify Tab: Overview details
    await expect(page.locator('text=System Oversight Overview')).toBeVisible();
    await expect(page.locator('text=LMS Core Engine')).toBeVisible();

    // Verify Tab: System Health (give it extra timeout for checks to complete)
    await page.click('button:has-text("System Health")');
    await expect(page.locator('text=Academy API Gateway')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('text=Discussion Forums')).toBeVisible();

    // Verify Tab: LMS & Academy Metrics
    await page.click('button:has-text("LMS & Academy Metrics")');
    await expect(page.locator('text=Total Courses')).toBeVisible();
    await expect(page.locator('text=Achievements & Badges')).toBeVisible();

    // Verify Tab: Integrations check
    await page.click('button:has-text("Integrations check")');
    await expect(page.locator('text=AI Mentor Service')).toBeVisible();

    // Verify Tab: Configuration settings
    await page.click('button:has-text("Configuration settings")');
    await expect(page.locator('text=Dawah Academy configurations')).toBeVisible();

    // Change max attempts and save
    const inputQuizAttempts = page.locator('label:has-text("Max Attempts per Quiz") + input');
    await inputQuizAttempts.fill('6');
    await page.click('button:has-text("Save configs")');

    // Verify success feedback
    await expect(page.locator('text=Configurations updated successfully')).toBeVisible();
  });
});
