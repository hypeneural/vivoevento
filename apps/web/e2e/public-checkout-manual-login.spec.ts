import { expect, test } from '@playwright/test';

import {
  fillBuyerDetails,
  mockAuthLogin,
  mockCommonPublicCheckoutRoutes,
  PUBLIC_CHECKOUT_V2_PATH,
} from './helpers/public-checkout';

test('manual "Ja tenho conta" keeps the package and resumes directly on payment after login', async ({ page }) => {
  test.setTimeout(45000);

  await mockCommonPublicCheckoutRoutes(page);
  await mockAuthLogin(page);

  await page.goto(`${PUBLIC_CHECKOUT_V2_PATH}?package=casamento-essencial`, { waitUntil: 'domcontentloaded' });

  await expect(page.getByLabel(/seu nome/i)).toBeVisible({ timeout: 15000 });
  await fillBuyerDetails(page);

  await page.getByRole('button', { name: /ja tenho conta/i }).click();

  await expect(page).toHaveURL(/\/login\?/, { timeout: 15000 });
  await expect(page).toHaveURL(/returnTo=.*package%3Dcasamento-essencial/, { timeout: 15000 });

  await page.getByRole('button', { name: /entrar com whatsapp/i }).click();
  await page.locator('input[autocomplete="username"]').fill('camila@example.com');
  await page.locator('input[autocomplete="current-password"]').fill('SenhaForte!2026');
  await page.getByRole('button', { name: /^entrar$/i }).click();

  await expect(page).toHaveURL(/resume=auth/, { timeout: 15000 });
  await expect(page).toHaveURL(/step=payment/, { timeout: 15000 });
  await expect(page.getByText(/sessao retomada com a sua conta/i)).toBeVisible({ timeout: 15000 });
  await expect(page.getByRole('button', { name: /gerar meu pix/i })).toBeVisible({ timeout: 15000 });

  await page.getByRole('button', { name: /voltar para seus dados/i }).click();

  await expect(page).toHaveURL(/step=details/, { timeout: 15000 });
  await expect(page.getByLabel(/seu nome/i)).toHaveValue('Camila Rocha');
  await expect(page.getByLabel(/^WhatsApp$/i)).toHaveValue('(48) 99977-1111');
  await expect(page.getByLabel(/nome do evento/i)).toHaveValue('Casamento Camila e Bruno');
});
