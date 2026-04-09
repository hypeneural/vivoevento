import { expect, test } from '@playwright/test';

import {
  createPixPendingCheckoutResponse,
  goToPaymentStep,
  mockCommonPublicCheckoutRoutes,
} from './helpers/public-checkout';

test('pix happy path goes from package selection to the QR code status screen', async ({ page }) => {
  test.setTimeout(45000);

  const response = createPixPendingCheckoutResponse();

  await mockCommonPublicCheckoutRoutes(page);

  await page.route('**/api/v1/public/event-checkouts', async (route) => {
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

  await expect(page.getByText(/pix gerado com sucesso/i)).toBeVisible({
    timeout: 15000,
  });
  await expect.poll(() => page.url(), { timeout: 10000 }).toContain('checkout=checkout-uuid');
  await expect.poll(() => page.url(), { timeout: 10000 }).toContain('step=status');
  await expect(page.getByRole('button', { name: /copiar codigo pix/i })).toBeVisible({
    timeout: 15000,
  });
  await expect(page.getByText(/000201010212/i)).toBeVisible();
});

test('package deep link opens the default V2 checkout with the package preselected', async ({ page }) => {
  test.setTimeout(45000);

  await mockCommonPublicCheckoutRoutes(page);

  await page.goto('/checkout/evento?package=casamento-essencial', { waitUntil: 'domcontentloaded' });

  await expect(page.getByLabel(/seu nome/i)).toBeVisible({ timeout: 15000 });
  await expect(page.getByRole('heading', { name: /pacote casamento essencial selecionado/i })).toBeVisible();
  await expect(page.getByRole('complementary').getByText('Casamento Essencial', { exact: true })).toBeVisible();
  await expect.poll(() => page.url(), { timeout: 10000 }).toContain('step=details');
});
