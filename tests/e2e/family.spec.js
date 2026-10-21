import { expect, test } from '@playwright/test';
import { asAnother, login } from './helpers.js';

test('login shows the family dashboard and the crest', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByAltText('Семейный герб')).toBeVisible();
    await login(page, 'nikolay');
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Николай');
    await expect(page.getByText('Где все')).toBeVisible();
});

test('"Is everyone OK?": one asks, another answers from her own phone', async ({ page, browser }) => {
    await login(page, 'papa');
    await page.getByRole('button', { name: 'Спросить: все ли в порядке?' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Спросить всех' }).click();
    await expect(page.getByText(/Ответили 1 из 5/)).toBeVisible();

    const mama = await asAnother(browser, 'mama');
    await expect(mama.page.getByText('Ответьте, пожалуйста:')).toBeVisible();
    await mama.page.getByRole('button', { name: 'Я в укрытии' }).click();
    await expect(mama.page.getByText(/Ответили 2 из 5/)).toBeVisible();

    // Papa's card refreshes on its own (polling every 15 s without a realtime server).
    await expect(page.getByText(/Ответили 2 из 5/)).toBeVisible({ timeout: 20_000 });
    await page.getByRole('button', { name: 'Закрыть опрос' }).click();
    await mama.context.close();
});

test('shared shopping list: added on one phone, ticked off on another', async ({ page, browser }) => {
    await login(page, 'mama');
    await page.goto('/shopping');
    await page.getByPlaceholder('Что купить? Например: хлеб').fill('Свечи');
    await page.getByRole('button', { name: 'Добавить' }).click();
    await expect(page.getByText('Свечи')).toBeVisible();

    const son = await asAnother(browser, 'yuriy');
    await son.page.goto('/shopping');
    await son.page.getByRole('listitem').filter({ hasText: 'Свечи' }).getByRole('checkbox').check();

    await expect(page.getByRole('list', { name: 'Нужно купить' }).getByText('Свечи')).toHaveCount(0, { timeout: 15_000 });
    await son.context.close();
});

test('viewer is read-only but sees photos and statuses', async ({ browser }) => {
    // Turn Maria into a viewer through the admin screen first.
    const admin = await asAnother(browser, 'nikolay');
    await admin.page.goto('/family');
    await admin.page.getByRole('button', { name: 'Изменить Мария Виговская' }).click();
    const dialog = admin.page.getByRole('dialog');
    await dialog.getByLabel('Роль').selectOption('viewer');
    await dialog.getByRole('button', { name: 'Сохранить' }).click();
    await expect(dialog).toHaveCount(0);
    await admin.context.close();

    const maria = await asAnother(browser, 'maria');
    await expect(maria.page.getByRole('link', { name: 'Фото', exact: true })).toBeVisible(); // big tile of the simple mode
    await expect(maria.page.getByText('Быстрый статус')).toHaveCount(0);
    await maria.page.goto('/shopping');
    await expect(maria.page.getByPlaceholder('Что купить? Например: хлеб')).toHaveCount(0);
    await maria.context.close();
});
