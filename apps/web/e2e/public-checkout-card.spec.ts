import { expect, test } from '@playwright/test';

import {
  PUBLIC_CHECKOUT_V2_PATH,
  createCardProcessingCheckoutResponse,
  mockAuthLogin,
  mockCommonPublicCheckoutRoutes,
  mockPagarmeCardToken,
} from './helpers/public-checkout';

test('card checkout restores only safe data after login and requires refilling sensitive fields', async ({ page }) => {
  let createAttempts = 0;

  await mockCommonPublicCheckoutRoutes(page);
  await mockPagarmeCardToken(page);
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
      body: JSON.stringify(createCardProcessingCheckoutResponse()),
    });
  });

  await page.goto(PUBLIC_CHECKOUT_V2_PATH, { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: /escolher este pacote/i }).click();
  await page.getByLabel(/seu nome/i).fill('Camila Rocha');
  await page.getByLabel(/^WhatsApp$/i).fill('(48) 99977-1111');
  await page.getByLabel(/e-mail/i).fill('camila@example.com');
  await page.getByLabel(/nome do evento/i).fill('Casamento Camila e Bruno');
  await page.getByRole('button', { name: /continuar para pagamento/i }).click();

  await page.getByRole('tab', { name: /cartao/i }).click();
  await page.getByLabel(/cpf do pagador/i).fill('529.982.247-25');
  await page.getByLabel(/telefone do pagador/i).fill('(48) 99977-1111');
  await page.getByLabel(/^Rua$/i).fill('Rua das Flores');
  await page.getByLabel(/^Numero$/i).fill('123');
  await page.getByLabel(/^Bairro$/i).fill('Centro');
  await page.getByLabel(/^CEP$/i).fill('88000-000');
  await page.getByLabel(/^Cidade$/i).fill('Florianopolis');
  await page.getByLabel(/^Estado$/i).fill('SC');
  await page.getByLabel(/numero do cartao/i).fill('4111 1111 1111 1111');
  await page.getByLabel(/nome impresso no cartao/i).fill('CAMILA ROCHA');
  await page.getByLabel(/^Mes$/i).fill('12');
  await page.getByLabel(/^Ano$/i).fill('29');
  await page.getByLabel(/^CVV$/i).fill('123');
  await page.getByRole('button', { name: /finalizar com cartao/i }).click();

  const loginLink = page.getByRole('link', { name: /entrar para continuar/i });
  await expect(loginLink).toBeVisible();
  await loginLink.click();

  await page.getByRole('button', { name: /entrar com whatsapp/i }).click();
  await page.locator('input[autocomplete="username"]').fill('camila@example.com');
  await page.locator('input[autocomplete="current-password"]').fill('SenhaForte!2026');
  await page.getByRole('button', { name: /^entrar$/i }).click();

  await expect(page).toHaveURL(/resume=auth/);
  await expect(page.getByText(/os campos do cartao precisam ser preenchidos novamente/i)).toBeVisible();
  await expect(page.getByLabel(/numero do cartao/i)).toHaveValue('');
  await expect(page.getByLabel(/^CVV$/i)).toHaveValue('');
  await expect(page.getByLabel(/cpf do pagador/i)).toHaveValue('529.982.247-25');
});
