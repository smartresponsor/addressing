const path = require('node:path');
const {defineConfig} = require('@playwright/test');

const e2ePort = process.env.PLAYWRIGHT_PORT || '18131';
if (!/^\d{4,5}$/.test(e2ePort) || Number(e2ePort) < 1024 || Number(e2ePort) > 65535) {
    throw new Error('Invalid loopback Playwright port');
}
const e2eBaseUrl = `http://127.0.0.1:${e2ePort}`;
const e2eRunId = `${process.pid}-${Date.now()}-${require('node:crypto').randomBytes(8).toString('hex')}`;
const addressDbPath = path.join(__dirname, 'var', `addressing-playwright-${e2eRunId}.sqlite`);
const runtimeVarDir = path.join(__dirname, 'var', `playwright-runtime-${e2eRunId}`);

module.exports = defineConfig({
    testDir: './tests/playwright',
    timeout: 90_000,
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
    webServer: {
        command: `php tools/e2e/ensure-schema.php && php -S 127.0.0.1:${e2ePort} -t public public/router.php`,
        port: Number(e2ePort),
        reuseExistingServer: false,
        timeout: 60_000,
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
