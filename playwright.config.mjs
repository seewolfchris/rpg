import { defineConfig } from '@playwright/test';

const e2eEnv = {
    APP_ENV: 'testing',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: 'database/database.sqlite',
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'sync',
};

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 90_000,
    workers: 1,
    fullyParallel: false,
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI
        ? [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]]
        : [['list']],
    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8000',
        headless: true,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8000',
        url: process.env.E2E_BASE_URL || 'http://127.0.0.1:8000/login',
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
        env: {
            ...process.env,
            ...e2eEnv,
        },
    },
});
