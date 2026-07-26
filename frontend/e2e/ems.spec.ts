import { test, expect } from '@playwright/test';

test.describe('EMS Event Lifecycle and Attendee Workflow', () => {
  test('Event Admin can carry out the full lifecycle from event creation to attendee check-in', async ({ page }) => {
    // 1. Authenticate as EMS Event Administrator
    await page.goto('/login');
    await page.fill('input[type="email"]', 'ems-admin@example.test');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');

    // Confirm navigation to dashboard and look for Event Management Link / direct load
    await expect(page).toHaveURL(/\/academy|\/login|\/ems/);

    // 2. Go to EMS Dashboard
    await page.goto('/ems');
    await expect(page.locator('h1')).toContainText('Event Management');

    // 3. Navigate to Event Creation Form
    await page.goto('/ems/events/create');
    await expect(page.locator('h1')).toContainText('Create Event');

    // 4. Fill out Event Form
    const eventName = `E2E Manual Workflow Gala ${Date.now()}`;
    await page.getByLabel('Event name').fill(eventName);
    await page.getByLabel('Short description').fill('E2E short description of the manual workflow event.');
    await page.getByLabel('Description').fill('E2E full description of the manual workflow event where we run verification.');
    await page.getByLabel('Location').fill('SFU Burnaby Student Union Building');
    await page.getByLabel('Capacity').fill('50');

    // Set starts_at and ends_at
    // In Vue, standard input date-local format is YYYY-MM-DDTHH:mm
    const tomorrowStr = new Date();
    tomorrowStr.setDate(tomorrowStr.getDate() + 1);
    const tomorrowDate = tomorrowStr.toISOString().split('T')[0];
    await page.getByLabel('Starts at').fill(`${tomorrowDate}T10:00`);
    await page.getByLabel('Ends at').fill(`${tomorrowDate}T12:00`);

    // Toggle Public listing (it's a switch)
    // The switch input usually has a specific role or class. Let's find it.
    await page.locator('button[role="switch"]').click();

    // Select category (Lectures & Seminars or whatever is seeded)
    // Let's see category selector: it has options populated from database.
    // EmsEventCategorySeeder seeds: 'Lectures', 'Socials', 'Outreach', 'Workshops'.
    // Let's choose the first one that is active or select option.
    // The Select component renders a standard <select> inside.
    await page.locator('select').selectOption({ label: 'Lectures' });

    // Submit form to Create Event
    await page.click('button[type="submit"]');

    // 5. Verify Redirected to Event Detail Page and showing draft status
    await expect(page).toHaveURL(/\/ems\/events\/[a-f0-9-]+/);
    await expect(page.locator('h1')).toContainText(eventName);
    await expect(page.locator('text=Draft')).toBeVisible();

    // 6. Configure Tickets
    // Click 'Add ticket type' to show the form
    await page.click('button:has-text("Add ticket type")');
    await page.locator('input[v-model="form.name"]').fill('General Admission');
    // For price, since it's a free event
    await page.locator('input[v-model.number="form.price"]').fill('0');
    // Quantity limit
    await page.locator('input[v-model="form.quantity"]').fill('50');
    // Max per order
    await page.locator('input[v-model="form.max_per_order"]').fill('2');
    // Save Ticket Type
    await page.click('form button[type="submit"]');

    // Verify Ticket type appears in table
    await expect(page.locator('text=General Admission')).toBeVisible();
    await expect(page.locator('text=Active')).toBeVisible();

    // 7. Transition Lifecycle
    // Step A: Publish
    await page.click('button:has-text("Publish")');
    await expect(page.locator('text=Published')).toBeVisible();

    // Step B: Open Registration
    await page.click('button:has-text("Open Registration")');
    await expect(page.locator('text=Registration Open')).toBeVisible();

    // 8. Public Discovery and Registration
    // The event slug is typically computed from name (lowercase, hyphens).
    // Let's find the slug of the event or navigate to public page.
    const eventUrl = page.url();
    const eventUuid = eventUrl.split('/').pop();

    // Go to public events directory to verify it is listed
    await page.goto('/events');
    await expect(page.locator('h1')).toContainText('Events');
    await expect(page.locator(`text=${eventName}`)).toBeVisible();

    // Click on event card to view public detail page
    await page.click(`text=${eventName}`);
    await expect(page.locator('h1')).toContainText(eventName);

    // Scroll and register
    await page.fill('input[name="first_name"]', 'Test');
    await page.fill('input[name="last_name"]', 'Attendee');
    await page.fill('input[name="email"]', 'test.attendee@example.test');
    // Select Ticket Type dropdown
    await page.locator('select').selectOption({ label: 'General Admission (Free)' });
    // Click register
    await page.click('button[type="submit"]');

    // Verify registration success (EmsCheckoutSuccessPage or successful toast)
    // For free ticket, it registers instantly and shows ticket page/info.
    await expect(page.locator('text=Ticket')).toBeVisible();
    await expect(page.locator('text=test.attendee@example.test')).toBeVisible();
    
    // Note ticket code from the ticket page
    const ticketUrl = page.url();
    const ticketCode = ticketUrl.split('/').pop() || '';
    expect(ticketCode).not.toBe('');

    // 9. Staff Portal & Check-in validation
    // Log back in as admin and go to operations
    await page.goto(`/ems/events/${eventUuid}/operations`);
    await expect(page.locator('text=1')).toBeVisible(); // 1 Registered attendee

    // Navigate to check-in page
    await page.goto(`/ems/events/${eventUuid}/check-in`);
    // Locate standard manual code field or check-in button
    await page.fill('input[placeholder*="ticket code"]', ticketCode);
    await page.click('button:has-text("Check In")');

    // Verify check-in confirmation
    await expect(page.locator('text=Checked In Successfully')).toBeVisible();

    // Go back to operations to see 1 checked-in
    await page.goto(`/ems/events/${eventUuid}/operations`);
    await expect(page.locator('text=Checked In')).toBeVisible();
  });
});
