const { test, expect } = require('@playwright/test');

test('manage page renders and can create an address record', async ({ page }) => {
  const suffix = Date.now().toString();
  const line1 = `500 Test Ave ${suffix}`;

  await page.goto('/address/manage');

  await expect(page.getByRole('heading', { name: 'Address manager' })).toBeVisible();

  await page.getByLabel('Address line 1').fill(line1);
  await page.getByLabel('City').fill('Austin');
  await page.getByLabel('Country').selectOption('US');
  await page.getByLabel('Owner ID').fill(`playwright-owner-${suffix}`);
  await page.getByLabel('Vendor ID').fill(`playwright-vendor-${suffix}`);
  await page.getByRole('button', { name: 'Create address' }).click();

  await expect(page.getByText('Address created successfully:')).toBeVisible();
  await expect(page.getByText(line1)).toBeVisible();
});
