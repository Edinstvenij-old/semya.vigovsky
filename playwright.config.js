import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests in real Chromium against a throw-away copy of the app:
 * own SQLite file, demo family seeded fresh, no realtime server.
 *   npm run build && npx playwright test
 */
const PORT = 8030;

export default defineConfig({
    testDir: 'tests/e2e',
    timeout: 60_000,
    fullyParallel: false,
    workers: 1, // one shared database
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: `http://localhost:${PORT}`,
        locale: 'ru-RU',
        timezoneId: 'Europe/Kyiv',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    ],
    webServer: {
        command: `node tests/e2e/prepare.js && php artisan serve --port=${PORT}`,
        url: `http://localhost:${PORT}/up`,
        reuseExistingServer: false,
        timeout: 120_000,
        env: {
            APP_ENV: 'testing',
            APP_URL: `http://localhost:${PORT}`,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: 'database/e2e.sqlite',
            BROADCAST_CONNECTION: 'log',
            QUEUE_CONNECTION: 'sync',
            CACHE_STORE: 'array',
            SESSION_DRIVER: 'file',
            HASH_DRIVER: 'bcrypt',
            BCRYPT_ROUNDS: '4',
            MAIL_MAILER: 'array',
            VAPID_PUBLIC_KEY: '',
            ALERTS_IN_UA_TOKEN: '',
            FLIGHT_STATUS_API_KEY: '',
            TELEGRAM_BOT_TOKEN: '',
        },
    },
});
