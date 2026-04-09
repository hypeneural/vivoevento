import { expect, test } from '@playwright/test';

import {
  createPixPendingCheckoutResponse,
  goToPaymentStep,
  mockAuthLogin,
  mockCommonPublicCheckoutRoutes,
} from './helpers/public-checkout';

test('identity conflict can authenticate and resume the Pix journey safely', async ({ page }) => {
  const response = createPixPendingCheckoutResponse();
  let createAttempts = 0;

  await mockCommonPublicCheckoutRoutes(page);
  await mockAuthLogin(page);

  await page.route('**/api/v1/public/event-checkouts', async (route) => {
    createAttempts += 1;

    if (createAttempts === 1) {
      await route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Ja existe uma conta com este contato. Faca login para continuar.',
          errors: {
            whatsapp: ['Ja existe uma conta com este WhatsApp. Faca login para continuar.'],
          },
        }),
      });
      return;
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(response),
    });
  });

  await page.route('**/api/v1/public/event-checkouts/checkout-uuid', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(response),
    });
  });

  await goToPaymentStep(page);
  await page.getByRole('button', { name: /gerar meu pix/i }).click();

  const loginLink = page.getByRole('link', { name: /entrar para continuar/i });
  await expect(loginLink).toBeVisible();
  await loginLink.click();

  await page.getByRole('button', { name: /entrar com whatsapp/i }).click();
  await page.locator('input[autocomplete="username"]').fill('camila@example.com');
  await page.locator('input[autocomplete="current-password"]').fill('SenhaForte!2026');
  await page.getByRole('button', { name: /^entrar$/i }).click();

  await expect(page).toHaveURL(/resume=auth/);
  await expect(page).toHaveURL(/checkout=checkout-uuid/);
  await expect(page.getByText(/pix gerado com sucesso/i)).toBeVisible();
  await expect(page.getByRole('button', { name: /copiar codigo pix/i })).toBeVisible();
});
