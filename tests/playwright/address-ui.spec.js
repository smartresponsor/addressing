const fs = require('node:fs');
const path = require('node:path');
const { test, expect } = require('@playwright/test');

test('manage page renders and can create an address record', async ({ page }, testInfo) => {
  const suffix = Date.now().toString();
  const line1 = `500 Test Ave ${suffix}`;
  const ownerId = `playwright-owner-${suffix}`;

  await page.goto('/address/manage');

  await expect(page.getByRole('heading', { name: 'Address manager' })).toBeVisible();

  await page.getByLabel('Address line 1').fill(line1);
  await page.getByLabel('City').fill('Austin');
  await page.getByLabel('Country').selectOption('US');
  await page.getByLabel('Owner ID').fill(ownerId);
  await page.getByRole('button', { name: 'Create address' }).click();

  await expect(page.getByText('Address created successfully:')).toBeVisible();
  await expect(page.getByText(line1)).toBeVisible();

  const ownPage = await page.request.get('/api/address/page', { params: { ownerId } });
  expect(ownPage.status()).toBe(200);
  const ownPayload = await ownPage.json();
  expect(ownPayload.items).toHaveLength(1);
  expect(ownPayload.items[0]).toMatchObject({ line1, ownerId, countryCode: 'US' });
  const id = ownPayload.items[0].id;
  expect(typeof id).toBe('string');
  const foreignPage = await page.request.get('/api/address/page', { params: { ownerId: `${ownerId}-foreign` } });
  expect(foreignPage.status()).toBe(200);
  expect((await foreignPage.json()).items).toEqual([]);
  const foreignRecord = await page.request.get(`/api/address/${encodeURIComponent(id)}`, { params: { ownerId: `${ownerId}-foreign` } });
  expect(foreignRecord.status()).toBe(404);

  await page.screenshot({
    path: testInfo.outputPath('address-manage-created.png'),
    fullPage: true,
  });

  const coverageDir = path.join(__dirname, '..', '..', 'var', 'coverage');
  fs.mkdirSync(coverageDir, { recursive: true });
  fs.writeFileSync(
    path.join(coverageDir, 'address-manage-playwright.json'),
    JSON.stringify({
      schema: 'address-manage-playwright-v2',
      passedAt: new Date().toISOString(),
      covered: ['GET /address/manage', 'POST /address/manage'],
      assertions: ['manage.page.render', 'manage.address.create.owner-scope'],
    }, null, 2),
  );
});
