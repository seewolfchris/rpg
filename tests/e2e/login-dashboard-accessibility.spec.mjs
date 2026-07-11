import { execFileSync } from 'node:child_process';

import { expect, test } from '@playwright/test';

const PASSWORD = 'PlaywrightDashboard123!';
const GM_EMAIL = 'testflight.gm+chroniken-der-asche@example.test';

test.beforeAll(() => {
    execFileSync(
        'php',
        [
            'artisan',
            'dev:testflight:seed',
            '--world=chroniken-der-asche',
            '--campaign-slug=testflight-playwright-dashboard',
            `--password=${PASSWORD}`,
        ],
        {
            cwd: process.cwd(),
            env: {
                ...process.env,
                APP_ENV: 'testing',
                DB_CONNECTION: 'sqlite',
                DB_DATABASE: 'database/database.sqlite',
                SESSION_DRIVER: 'file',
                CACHE_STORE: 'file',
                QUEUE_CONNECTION: 'sync',
            },
            stdio: 'pipe',
        },
    );
});

test('login form submits from the password field with Enter', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('E-Mail').fill('invalid-enter@example.test');
    await page.getByLabel('Passwort').fill('invalid-password');
    await page.getByLabel('Passwort').press('Enter');

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByText('Diese Zugangsdaten stimmen nicht mit unseren Aufzeichnungen überein.')).toBeVisible();
});

test('dashboard disclosures are keyboard operable and keep priority defaults', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('E-Mail').fill(GM_EMAIL);
    await page.getByLabel('Passwort').fill(PASSWORD);
    await page.getByLabel('Passwort').press('Enter');

    await expect(page).toHaveURL(/\/dashboard$/);

    const tutorial = page.locator('details[data-dashboard-section="tutorial"]');
    const quickAccess = page.locator('details[data-dashboard-section="quick-access"]');
    const leaderboard = page.locator('details[data-dashboard-section="leaderboard"]');

    await expect(tutorial).toHaveAttribute('open', '');
    await expect(quickAccess).not.toHaveAttribute('open', '');
    await expect(leaderboard).not.toHaveAttribute('open', '');

    const quickAccessSummary = quickAccess.locator('summary');
    await quickAccessSummary.focus();
    await page.keyboard.press('Enter');
    await expect(quickAccess).toHaveAttribute('open', '');

    const leaderboardSummary = leaderboard.locator('summary');
    await leaderboardSummary.focus();
    await page.keyboard.press('Space');
    await expect(leaderboard).toHaveAttribute('open', '');
});

test('scene post form initializes with the Alpine CSP build', async ({ page }) => {
    const cspParserErrors = [];
    const captureCspParserError = (message) => {
        if (/CSP Parser Error/i.test(String(message))) {
            cspParserErrors.push(String(message));
        }
    };

    page.on('console', (message) => captureCspParserError(message.text()));
    page.on('pageerror', (error) => captureCspParserError(error.message));

    await page.goto('/login');
    await page.getByLabel('E-Mail').fill(GM_EMAIL);
    await page.getByLabel('Passwort').fill(PASSWORD);
    await page.getByLabel('Passwort').press('Enter');
    await expect(page).toHaveURL(/\/dashboard$/);

    await page.goto('/w/chroniken-der-asche/campaigns/1/scenes/1');

    const postFormStateRoot = page.locator('[x-data^="postFormState"]');
    const postType = page.getByLabel('Beitragstyp');
    const postMode = page.getByLabel('IC-Modus');
    const character = page.getByLabel(/Charakter/);

    await expect(postFormStateRoot).toBeVisible();
    await expect(postFormStateRoot.locator('[x-cloak]')).toHaveCount(0);
    await expect(postMode).toBeVisible();

    await postMode.selectOption('gm');
    await expect(character).toBeDisabled();
    await expect(page.getByText('Im Spielleitungsmodus wird kein Charakter')).toBeVisible();

    await postType.selectOption('ooc');
    await expect(postMode).toBeHidden();
    await expect(character).toBeDisabled();
    expect(cspParserErrors).toEqual([]);
});
