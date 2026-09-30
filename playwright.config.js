const path = require('node:path');
const {defineConfig} = require('@playwright/test');

const e2ePort = process.env.PLAYWRIGHT_PORT || '8001';
const e2eBaseUrl = process.env.PLAYWRIGHT_BASE_URL || `http://127.0.0.1:${e2ePort}`;
const e2eRunId = `${process.pid}-${Date.now()}`;
const addressDbPath = process.env.ADDRESS_DB_PATH || path.join(__dirname, 'var', `addressing-playwright-${e2eRunId}.sqlite`);
const runtimeVarDir = path.join(__dirname, 'var', 'playwright-runtime');

module.exports = defineConfig({
    testDir: './tests/playwright',
    timeout: 30_000,
    use: {
        baseURL: e2eBaseUrl,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        launchOptions: {
            executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH,
            args: ['--disable-dev-shm-usage', '--no-sandbox']
        }
    },
    webServer: process.env.PLAYWRIGHT_SKIP_WEBSERVER ? undefined : {
        command: `php tools/e2e/ensure-schema.php && php -S 127.0.0.1:${e2ePort} -t public public/router.php`,
        url: `${e2eBaseUrl}/address/manage`,
        reuseExistingServer: false,
        timeout: 30_000,
        env: {
            ...process.env,
            APP_ENV: 'dev',
            APP_DEBUG: '0',
            ADDRESS_DB_DSN: `sqlite:${addressDbPath}`,
            ADDRESS_DB_PATH: addressDbPath,
            ADDRESS_PARITY_DATABASE_URL: 'postgresql://smartresponsor:smartresponsor@127.0.0.1:5432/smartresponsor?serverVersion=15&charset=utf8',
            APP_VAR_DIR: runtimeVarDir,
        },
    }
});
