import { test, expect } from '@playwright/test';

test.describe('Student Learning Journey', () => {
  test('Student can enroll in a course, view lessons, complete a quiz, and earn a certificate', async ({ page }) => {
    // 1. Login
    await page.goto('/login');
    await page.fill('input[type="email"]', 'volunteer@example.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');

    // 2. Navigate to Course Catalog
    await page.goto('/academy/courses');
    await expect(page.locator('h1')).toContainText('Course Catalog');

    // 3. Click Course details
    await page.click('text=Introduction to Dawah');
    await expect(page).toHaveURL(/\/academy\/courses\/\d+/);

    // 4. Enroll
    await page.click('button:has-text("Enroll")');
    await expect(page.locator('button:has-text("Start Lesson")')).toBeVisible();

    // 5. Complete Lesson
    await page.click('button:has-text("Start Lesson")');
    await expect(page.locator('h1')).toBeVisible(); // Lesson title
    await page.click('button:has-text("Mark Completed")');

    // 6. Complete Quiz
    await page.click('text=Take Quiz');
    await page.click('button:has-text("Start Quiz")');
    
    // Choose answers and submit
    await page.click('text=Option A');
    await page.click('button:has-text("Submit Answer")');
    
    await expect(page.locator('text=Quiz Passed')).toBeVisible();

    // 7. Verify Certificate Earned
    await page.click('text=View Certificate');
    await expect(page.locator('text=Certificate of Completion')).toBeVisible();
    await expect(page.locator('button:has-text("Download PDF")')).toBeVisible();
  });
});
