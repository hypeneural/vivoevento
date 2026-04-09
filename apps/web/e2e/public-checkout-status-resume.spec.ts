import { expect, test } from '@playwright/test';

import {
  createPixPendingCheckoutResponse,
  mockCommonPublicCheckoutRoutes,
} from './helpers/public-checkout';

test('status url can be refreshed directly and rehydrates the payment tracking state', async ({ page }) => {
  const response = createPixPendingCheckoutResponse();

  await mockCommonPublicCheckoutRoutes(page);
  await page.route('**/api/v1/public/event-checkouts/checkout-uuid', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(response),
    });
  });

  await page.goto('/checkout/evento?v2=1&checkout=checkout-uuid&step=status', {
    waitUntil: 'domcontentloaded',
  });

  await expect(page.getByText(/pix gerado com sucesso/i)).toBeVisible();
  await expect(page.getByRole('button', { name: /copiar codigo pix/i })).toBeVisible();

  await page.reload();

  await expect(page).toHaveURL(/checkout=checkout-uuid/);
  await expect(page).toHaveURL(/step=status/);
  await expect(page.getByText(/pix gerado com sucesso/i)).toBeVisible();
});
