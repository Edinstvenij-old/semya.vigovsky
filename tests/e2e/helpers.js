import { expect } from '@playwright/test';

export const PASSWORD = 'demo-password'; // demo seed (database/seeders/DatabaseSeeder.php)

export const PEOPLE = {
    papa: 'papa@vigovsky.test',
    mama: 'mama@vigovsky.test',
    nikolay: 'nikolay@vigovsky.test',
    yuriy: 'yuriy@vigovsky.test',
    maria: 'maria@vigovsky.test',
};

export async function login(page, who) {
    await page.goto('/login');
    await page.getByLabel('Email').fill(PEOPLE[who]);
    await page.getByLabel('Пароль', { exact: true }).fill(PASSWORD);
    await page.getByRole('button', { name: /^(Войти|Увійти)$/ }).click();
    await expect(page).toHaveURL('/');
}

/** Opens a second, independent browser session (another family member's phone). */
export async function asAnother(browser, who, options = {}) {
    const context = await browser.newContext({ locale: 'ru-RU', ...options });
    const page = await context.newPage();
    await login(page, who);
    return { context, page };
}
