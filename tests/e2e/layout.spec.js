import { expect, test } from '@playwright/test';
import { login } from './helpers.js';

const PAGES = ['/', '/tasks', '/tasks/1', '/home', '/travel', '/documents', '/threads', '/threads/2', '/notifications', '/family', '/settings', '/shopping', '/photos'];

for (const locale of ['ru', 'uk']) {
    test(`small phone, largest text (${locale}): nothing overflows the screen`, async ({ browser }) => {
        const context = await browser.newContext({ viewport: { width: 320, height: 640 }, isMobile: true, hasTouch: true, locale: 'ru-RU' });
        const page = await context.newPage();
        await page.addInitScript((l) => localStorage.setItem('fh-locale', l), locale);
        await login(page, 'nikolay');

        for (const path of PAGES) {
            await page.goto(path);
            await page.waitForLoadState('networkidle');
            const problems = await page.evaluate(() => {
                document.documentElement.style.fontSize = '28px'; // 1.75× — the largest setting
                const width = document.documentElement.clientWidth;
                const issues = [];
                if (document.documentElement.scrollWidth > width) issues.push(`page is ${document.documentElement.scrollWidth}px wide`);
                document.querySelectorAll('nav.fixed span.truncate').forEach((s) => {
                    if (s.scrollWidth > s.clientWidth) issues.push(`bottom bar label cut: ${s.textContent}`);
                });
                const header = document.querySelector('header');
                if (header && header.scrollWidth > header.clientWidth) issues.push('top bar overflows');
                return issues;
            });
            expect(problems, path).toEqual([]);
        }
        await context.close();
    });
}

test('switching to Ukrainian translates the interface', async ({ page }) => {
    await login(page, 'yuriy');
    await page.goto('/settings');
    await page.getByRole('button', { name: 'Українська' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Налаштування');
    await page.goto('/');
    await expect(page.getByText('Де всі')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Кордон пройдено' })).toBeVisible();
});
