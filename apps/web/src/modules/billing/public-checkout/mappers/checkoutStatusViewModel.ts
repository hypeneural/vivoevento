import type { PublicEventCheckoutResponse } from '@/lib/api-types';

import { formatRemainingTime } from '../support/checkoutFormUtils';

export type PublicCheckoutStatusTone = 'idle' | 'info' | 'success' | 'warning' | 'error';
export type PublicCheckoutSemanticState = 'idle' | 'pending' | 'processing' | 'paid' | 'failed' | 'refunded';

export type PublicCheckoutStatusViewModel = {
  state: PublicCheckoutSemanticState;
  tone: PublicCheckoutStatusTone;
  title: string;
  description: string;
  paymentMethod: 'pix' | 'credit_card' | null;
  statusLabel: string;
  isTerminal: boolean;
  qrCode: string | null;
  qrCodeUrl: string | null;
  pixExpiresAt: string | null;
  pixExpiresLabel: string | null;
  whatsappPixNotice: PublicEventCheckoutResponse['checkout']['payment']['whatsapp']['pix_generated'] | null;
  onboardingPath: string | null;
};

function normalizeSummaryState(value: string | null | undefined): PublicCheckoutSemanticState {
  switch (value) {
    case 'pending':
    case 'processing':
    case 'paid':
    case 'failed':
    case 'refunded':
      return value;
    default:
      return 'idle';
  }
}

function normalizeSummaryTone(value: string | null | undefined): PublicCheckoutStatusTone {
  switch (value) {
    case 'info':
    case 'success':
    case 'warning':
    case 'error':
      return value;
    default:
      return 'idle';
  }
}

function resolveSemanticState(response: PublicEventCheckoutResponse | undefined): PublicCheckoutSemanticState {
  const summaryState = response?.checkout.summary?.state;

  if (summaryState) {
    return normalizeSummaryState(summaryState);
  }

  const checkout = response?.checkout;
  const payment = checkout?.payment;

  if (!checkout || !payment) {
    return 'idle';
  }

  const statuses = [
    checkout.status,
    payment.status,
    payment.gateway_status,
    payment.credit_card?.last_status,
  ].filter(Boolean);

  if (statuses.some((status) => status === 'paid')) {
    return 'paid';
  }

  if (statuses.some((status) => status === 'refunded' || status === 'chargedback')) {
    return 'refunded';
  }

  if (statuses.some((status) => status === 'failed' || status === 'canceled')) {
    return 'failed';
  }

  if (payment.method === 'credit_card') {
    return 'processing';
  }

  return 'pending';
}

export function shouldPollPublicCheckout(response: PublicEventCheckoutResponse | undefined) {
  const summary = response?.checkout.summary;

  if (summary) {
    return summary.is_waiting_payment;
  }

  const semanticState = resolveSemanticState(response);

  return semanticState === 'pending' || semanticState === 'processing';
}

export function buildCheckoutStatusViewModel(
  response: PublicEventCheckoutResponse | undefined,
  nowMs = Date.now(),
): PublicCheckoutStatusViewModel {
  const checkout = response?.checkout;
  const payment = checkout?.payment;
  const summary = checkout?.summary;
  const semanticState = resolveSemanticState(response);
  const paymentMethod = payment?.method === 'pix' || payment?.method === 'credit_card'
    ? payment.method
    : null;

  if (!checkout || !payment) {
    return {
      state: 'idle',
      tone: 'idle',
      title: 'Finalize seu pagamento',
      description: 'Escolha Pix ou cartao e conclua a compra para ativar o seu pacote.',
      paymentMethod: null,
      statusLabel: 'Aguardando seus dados',
      isTerminal: false,
      qrCode: null,
      qrCodeUrl: null,
      pixExpiresAt: null,
      pixExpiresLabel: null,
      whatsappPixNotice: null,
      onboardingPath: null,
    };
  }

  const pixExpiresAt = payment.pix?.expires_at ?? payment.expires_at ?? null;
  const pixExpiresLabel = formatRemainingTime(pixExpiresAt, nowMs);

  if (summary) {
    return {
      state: normalizeSummaryState(summary.state),
      tone: normalizeSummaryTone(summary.tone),
      title: summary.payment_status_title || 'Acompanhe seu pagamento',
      description: summary.payment_status_description || 'Acompanhe a confirmacao do pagamento nesta mesma pagina.',
      paymentMethod,
      statusLabel: summary.payment_status_label || summary.order_status_label || 'Atualizando pagamento',
      isTerminal: !summary.is_waiting_payment,
      qrCode: payment.pix?.qr_code ?? null,
      qrCodeUrl: payment.pix?.qr_code_url ?? null,
      pixExpiresAt,
      pixExpiresLabel,
      whatsappPixNotice: payment.whatsapp.pix_generated,
      onboardingPath: response?.onboarding?.next_path ?? null,
    };
  }

  if (semanticState === 'paid') {
    return {
      state: 'paid',
      tone: 'success',
      title: 'Pagamento confirmado',
      description: 'Seu pacote ja foi confirmado e o evento pode seguir para a ativacao.',
      paymentMethod,
      statusLabel: 'Confirmado',
      isTerminal: true,
      qrCode: payment.pix?.qr_code ?? null,
      qrCodeUrl: payment.pix?.qr_code_url ?? null,
      pixExpiresAt,
      pixExpiresLabel,
      whatsappPixNotice: payment.whatsapp.pix_generated,
      onboardingPath: response?.onboarding?.next_path ?? null,
    };
  }

  if (semanticState === 'failed') {
    return {
      state: 'failed',
      tone: 'error',
      title: 'Pagamento nao confirmado',
      description: payment.credit_card?.acquirer_message
        || 'Nao foi possivel confirmar esta tentativa de pagamento. Voce pode revisar os dados e tentar novamente.',
      paymentMethod,
      statusLabel: 'Nao confirmado',
      isTerminal: true,
      qrCode: payment.pix?.qr_code ?? null,
      qrCodeUrl: payment.pix?.qr_code_url ?? null,
      pixExpiresAt,
      pixExpiresLabel,
      whatsappPixNotice: payment.whatsapp.pix_generated,
      onboardingPath: response?.onboarding?.next_path ?? null,
    };
  }

  if (semanticState === 'refunded') {
    return {
      state: 'refunded',
      tone: 'warning',
      title: 'Cobranca revertida',
      description: 'O pedido foi atualizado como estornado ou revertido. Se precisar, faca uma nova tentativa de compra.',
      paymentMethod,
      statusLabel: 'Revertido',
      isTerminal: true,
      qrCode: payment.pix?.qr_code ?? null,
      qrCodeUrl: payment.pix?.qr_code_url ?? null,
      pixExpiresAt,
      pixExpiresLabel,
      whatsappPixNotice: payment.whatsapp.pix_generated,
      onboardingPath: response?.onboarding?.next_path ?? null,
    };
  }

  if (paymentMethod === 'credit_card') {
    return {
      state: 'processing',
      tone: 'warning',
      title: 'Pagamento em analise',
      description: payment.credit_card?.acquirer_message
        || 'Seu cartao foi enviado com seguranca e agora estamos aguardando a confirmacao do pagamento.',
      paymentMethod,
      statusLabel: 'Em analise',
      isTerminal: false,
      qrCode: null,
      qrCodeUrl: null,
      pixExpiresAt: null,
      pixExpiresLabel: null,
      whatsappPixNotice: null,
      onboardingPath: response?.onboarding?.next_path ?? null,
    };
  }

  return {
    state: 'pending',
    tone: 'info',
    title: 'Pix gerado com sucesso',
    description: 'Use o QR Code ou o codigo copia e cola abaixo. A confirmacao aparece aqui automaticamente.',
    paymentMethod: 'pix',
    statusLabel: 'Aguardando pagamento',
    isTerminal: false,
    qrCode: payment.pix?.qr_code ?? null,
    qrCodeUrl: payment.pix?.qr_code_url ?? null,
    pixExpiresAt,
    pixExpiresLabel,
    whatsappPixNotice: payment.whatsapp.pix_generated,
    onboardingPath: response?.onboarding?.next_path ?? null,
  };
}
