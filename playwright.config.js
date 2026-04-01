const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/playwright',
  timeout: 30_000,
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    launchOptions: {
      executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH,
      args: ['--disable-dev-shm-usage', '--no-sandbox']
    }
  },
  webServer: process.env.PLAYWRIGHT_SKIP_WEBSERVER ? undefined : {
    command: 'php -S 127.0.0.1:8000 -t public public/router.php',
    url: 'http://127.0.0.1:8000/address/manage',
    reuseExistingServer: true,
    timeout: 30_000
  }
});
