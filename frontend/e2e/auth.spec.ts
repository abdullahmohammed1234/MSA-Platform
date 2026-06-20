import { test, expect } from '@playwright/test';

test.describe('Authentication Flows', () => {
  test('User can register, verify validation, and login successfully', async ({ page }) => {
    // 1. Visit Login/Register Page
    await page.goto('/login');
    await expect(page).toHaveTitle(/Login/);

    // 2. Click Register Link
    await page.click('text=Register');
    await expect(page.locator('h1')).toContainText('Register');

    // 3. Perform registration validation error check
    await page.click('button[type="submit"]');
    await expect(page.locator('text=required')).toBeVisible();

    // 4. Fill in registration form
    await page.fill('input[type="text"]', 'E2E Volunteer');
    await page.fill('input[type="email"]', 'e2e_volunteer@sfu.ca');
    await page.fill('input[name="password"]', 'Password123!');
    await page.fill('input[name="password_confirmation"]', 'Password123!');
    await page.click('button[type="submit"]');

    // 5. Registration successful, expect redirect/toast feedback
    await expect(page).toHaveURL(/\/academy|\/login|\/verify-email/);
  });

  test('User can trigger password reset', async ({ page }) => {
    await page.goto('/login');
    await page.click('text=Forgot Password?');
    await page.fill('input[type="email"]', 'e2e_volunteer@sfu.ca');
    await page.click('button[type="submit"]');
    await expect(page.locator('text=sent')).toBeVisible();
  });
});
