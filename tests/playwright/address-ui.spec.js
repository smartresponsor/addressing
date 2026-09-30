const fs = require('node:fs');
const path = require('node:path');
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
  await page.getByRole('button', { name: 'Create address' }).click();

  await expect(page.getByText('Address created successfully:')).toBeVisible();
  await expect(page.getByText(line1)).toBeVisible();

  const date = new Date().toISOString().slice(0, 10);
  const runId = process.env.CMCP_VISUAL_RUN_ID || 'routing-rc';
  const artifactDir = path.join(__dirname, '..', '..', '..', 'var', 'Addressing', date, runId);
  fs.mkdirSync(artifactDir, { recursive: true });
  await page.screenshot({
    path: path.join(artifactDir, 'address-manage-created.png'),
    fullPage: true,
  });

  const coverageDir = path.join(__dirname, '..', '..', 'var', 'coverage');
  fs.mkdirSync(coverageDir, { recursive: true });
  fs.writeFileSync(
    path.join(coverageDir, 'address-manage-playwright.json'),
    JSON.stringify({
      schema: 'address-manage-playwright-v1',
      passedAt: new Date().toISOString(),
      covered: ['GET /address/manage', 'POST /address/manage'],
    }, null, 2),
  );
});
