import { expect, test } from '@playwright/test';

import { PUBLIC_CHECKOUT_V2_PATH, mockCommonPublicCheckoutRoutes } from './helpers/public-checkout';

test('mobile keeps data when moving forward to payment and back to details', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await mockCommonPublicCheckoutRoutes(page);

  await page.goto(PUBLIC_CHECKOUT_V2_PATH, { waitUntil: 'domcontentloaded' });

  await expect(page.getByRole('heading', { name: /contrate seu evento em poucos minutos/i })).toBeVisible({
    timeout: 15000,
  });
  await expect(page.getByRole('button', { name: /escolher este pacote/i })).toBeVisible({
    timeout: 15000,
  });
  await expect(page.getByTestId('public-checkout-mobile-footer')).toBeVisible({
    timeout: 15000,
  });
  await page.getByRole('button', { name: /ver resumo/i }).click();
  await expect(page.getByTestId('public-checkout-mobile-drawer')).toBeVisible();
  await page.keyboard.press('Escape');

  await page.getByRole('button', { name: /escolher este pacote/i }).click();
  await page.getByLabel(/seu nome/i).fill('Camila Rocha');
  await page.getByLabel(/whatsapp com ddd/i).fill('(48) 99977-1111');
  await page.getByLabel(/nome do evento/i).fill('Casamento Camila e Bruno');
  await expect(page.getByTestId('public-checkout-mobile-primary-cta')).toHaveText(/continuar para pagamento/i);
  await page.getByTestId('public-checkout-mobile-primary-cta').click();

  await expect(page).toHaveURL(/step=payment/);
  await expect(page.getByTestId('public-checkout-mobile-primary-cta')).toHaveText(/gerar meu pix/i);

  await page.getByRole('button', { name: /voltar para seus dados/i }).click();

  await expect(page).toHaveURL(/step=details/);
  await expect(page.getByLabel(/seu nome/i)).toHaveValue('Camila Rocha');
  await expect(page.getByLabel(/whatsapp com ddd/i)).toHaveValue('(48) 99977-1111');
  await expect(page.getByLabel(/nome do evento/i)).toHaveValue('Casamento Camila e Bruno');
});
