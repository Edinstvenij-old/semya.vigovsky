import { expect, test } from '@playwright/test';
import { login } from './helpers.js';

test('works offline: cached pages and a queued status sent once back online', async ({ page, context }) => {
    await login(page, 'nikolay');

    // Let the service worker take control and cache what we look at.
    await page.evaluate(() => navigator.serviceWorker.ready);
    await page.reload();
    await expect.poll(() => page.evaluate(() => !!navigator.serviceWorker.controller)).toBe(true);
    await page.goto('/travel');
    await expect(page.getByText('Последние статусы')).toBeVisible();
    await page.goto('/');
    await expect(page.getByText('Где все')).toBeVisible();

    await context.setOffline(true);

    // The app still opens from cache and says the data may be old.
    await page.reload();
    await expect(page.getByText('Где все')).toBeVisible();
    await expect(page.getByRole('status').filter({ hasText: 'Нет связи' })).toBeVisible();

    // A status sent without a connection is queued…
    await page.getByRole('button', { name: 'Уже дома' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Отправить семье' }).click();
    await expect(page.getByText('Нет связи — статус отправится, когда появится интернет')).toBeVisible();
    await expect(page.getByRole('status').filter({ hasText: 'Ждут отправки: 1' })).toBeVisible();

    // …and delivered automatically when the connection returns.
    await context.setOffline(false);
    await expect(page.getByRole('status').filter({ hasText: 'Ждут отправки' })).toHaveCount(0, { timeout: 15_000 });
    const latest = await page.evaluate(() => fetch('/api/checkins').then((r) => r.json()));
    expect(latest.feed[0].user.name).toBe('Николай Виговский');
    expect(latest.feed[0].type).toBe('back_home');
});
