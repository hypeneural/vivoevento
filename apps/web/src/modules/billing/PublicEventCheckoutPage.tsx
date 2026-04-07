import { useDeferredValue, useEffect, useMemo, useState, startTransition } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AlertTriangle,
  BadgeCheck,
  CheckCircle2,
  CircleEllipsis,
  CreditCard,
  Loader2,
  LockKeyhole,
  MapPinned,
  QrCode,
  RefreshCcw,
  ShieldCheck,
  Sparkles,
  UserRound,
  WalletCards,
} from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useForm } from 'react-hook-form';
import { Link, useSearchParams } from 'react-router-dom';
import { z } from 'zod';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { useAuth } from '@/app/providers/AuthProvider';
import { ApiError, setToken } from '@/lib/api';
import type { PublicEventCheckoutPayload, PublicEventCheckoutResponse } from '@/lib/api-types';
import { createPagarmeCardToken, PagarmeTokenizationError } from '@/lib/pagarme-tokenization';
import { cn } from '@/lib/utils';

import { publicEventCheckoutService } from './services/public-event-checkout.service';
import { publicEventPackagesService } from './services/public-event-packages.service';

const STORAGE_KEY = 'eventovivo.public-event-checkout.last-uuid';
const RESUME_DRAFT_STORAGE_KEY = 'eventovivo.public-event-checkout.resume-draft';
const POLLING_INTERVAL_MS = 5000;
const AUTH_RESUME_SEARCH_VALUE = 'auth';

function digitsOnly(value: string | null | undefined) {
  return (value ?? '').replace(/\D+/g, '');
}

function normalizeStateCode(value: string) {
  return value.replace(/[^a-zA-Z]/g, '').slice(0, 2).toUpperCase();
}

function hasTwoWords(value: string) {
  return value
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .length >= 2;
}

function isValidCpf(value: string) {
  const digits = digitsOnly(value);

  if (digits.length !== 11 || /^(\d)\1+$/.test(digits)) {
    return false;
  }

  let sum = 0;

  for (let index = 0; index < 9; index += 1) {
    sum += Number(digits[index]) * (10 - index);
  }

  let remainder = (sum * 10) % 11;

  if (remainder === 10) {
    remainder = 0;
  }

  if (remainder !== Number(digits[9])) {
    return false;
  }

  sum = 0;

  for (let index = 0; index < 10; index += 1) {
    sum += Number(digits[index]) * (11 - index);
  }

  remainder = (sum * 10) % 11;

  if (remainder === 10) {
    remainder = 0;
  }

  return remainder === Number(digits[10]);
}

function isValidCardNumber(value: string) {
  const digits = digitsOnly(value);

  if (digits.length < 13 || digits.length > 19) {
    return false;
  }

  let sum = 0;
  let shouldDouble = false;

  for (let index = digits.length - 1; index >= 0; index -= 1) {
    let digit = Number(digits[index]);

    if (shouldDouble) {
      digit *= 2;

      if (digit > 9) {
        digit -= 9;
      }
    }

    sum += digit;
    shouldDouble = !shouldDouble;
  }

  return sum % 10 === 0;
}

function isValidCardExpiry(month: string, year: string) {
  const normalizedMonth = digitsOnly(month);
  const normalizedYear = digitsOnly(year);

  if (normalizedMonth.length !== 2 || (normalizedYear.length !== 2 && normalizedYear.length !== 4)) {
    return false;
  }

  const monthNumber = Number(normalizedMonth);

  if (monthNumber < 1 || monthNumber > 12) {
    return false;
  }

  const fullYear = normalizedYear.length === 2 ? 2000 + Number(normalizedYear) : Number(normalizedYear);

  if (!Number.isFinite(fullYear)) {
    return false;
  }

  const now = new Date();
  const expiry = new Date(fullYear, monthNumber, 0, 23, 59, 59, 999);

  return expiry.getTime() >= now.getTime();
}

function buildCardChecklist(values: CheckoutFormValues) {
  const items = [
    {
      key: 'payer_document',
      label: 'CPF valido do pagador',
      done: isValidCpf(values.payer_document),
    },
    {
      key: 'payer_phone',
      label: 'Telefone do pagador com DDD',
      done: digitsOnly(values.payer_phone).length >= 10,
    },
    {
      key: 'billing_address',
      label: 'Endereco de cobranca completo',
      done: Boolean(
        values.address_street.trim()
        && values.address_number.trim()
        && values.address_district.trim()
        && digitsOnly(values.address_zip_code).length === 8
        && values.address_city.trim()
        && normalizeStateCode(values.address_state).length === 2,
      ),
    },
    {
      key: 'card_number',
      label: 'Numero do cartao valido',
      done: isValidCardNumber(values.card_number),
    },
    {
      key: 'card_holder_name',
      label: 'Nome e sobrenome do titular',
      done: hasTwoWords(values.card_holder_name),
    },
    {
      key: 'card_expiry',
      label: 'Validade vigente',
      done: isValidCardExpiry(values.card_exp_month, values.card_exp_year),
    },
    {
      key: 'card_cvv',
      label: 'CVV com 3 ou 4 digitos',
      done: [3, 4].includes(digitsOnly(values.card_cvv).length),
    },
  ] as const;

  return {
    items,
    completed: items.filter((item) => item.done).length,
    total: items.length,
    ready: items.every((item) => item.done),
  };
}

const checkoutFormSchema = z.object({
  responsible_name: z.string().trim().min(3, 'Informe o nome do responsável.'),
  whatsapp: z.string().trim().min(8, 'Informe um WhatsApp válido.'),
  email: z.string().trim().email('Informe um e-mail válido.').or(z.literal('')),
  organization_name: z.string().trim().optional().default(''),
  package_id: z.string().trim().min(1, 'Escolha um pacote.'),
  event_title: z.string().trim().min(3, 'Informe o nome do evento.'),
  event_type: z.enum(['wedding', 'birthday', 'fifteen', 'corporate', 'fair', 'graduation', 'other']),
  event_date: z.string().trim().optional().default(''),
  event_city: z.string().trim().optional().default(''),
  event_description: z.string().trim().optional().default(''),
  payment_method: z.enum(['pix', 'credit_card']),
  payer_document: z.string().trim().optional().default(''),
  payer_phone: z.string().trim().optional().default(''),
  address_street: z.string().trim().optional().default(''),
  address_number: z.string().trim().optional().default(''),
  address_district: z.string().trim().optional().default(''),
  address_complement: z.string().trim().optional().default(''),
  address_zip_code: z.string().trim().optional().default(''),
  address_city: z.string().trim().optional().default(''),
  address_state: z.string().trim().optional().default(''),
  card_number: z.string().trim().optional().default(''),
  card_holder_name: z.string().trim().optional().default(''),
  card_exp_month: z.string().trim().optional().default(''),
  card_exp_year: z.string().trim().optional().default(''),
  card_cvv: z.string().trim().optional().default(''),
}).superRefine((values, ctx) => {
  if (digitsOnly(values.whatsapp).length < 10) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Informe um WhatsApp com DDD.',
      path: ['whatsapp'],
    });
  }

  if (values.payment_method !== 'credit_card') return;

  if (!values.email) {
    ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Informe o e-mail principal para checkout com cartão.', path: ['email'] });
  }

  const requiredMessages = {
    payer_document: 'Informe o CPF do pagador.',
    payer_phone: 'Informe o telefone do pagador.',
    address_street: 'Informe a rua de cobrança.',
    address_number: 'Informe o número do endereço.',
    address_district: 'Informe o bairro.',
    address_zip_code: 'Informe o CEP.',
    address_city: 'Informe a cidade de cobrança.',
    address_state: 'Informe a UF do endereço.',
    card_number: 'Informe o número do cartão.',
    card_holder_name: 'Informe o nome do titular.',
    card_exp_month: 'Informe o mês da validade.',
    card_exp_year: 'Informe o ano da validade.',
    card_cvv: 'Informe o CVV.',
  } satisfies Record<string, string>;

  for (const [field, message] of Object.entries(requiredMessages)) {
    const fieldValue = values[field as keyof CheckoutFormValues];

    if (typeof fieldValue === 'string' && !fieldValue.trim()) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, message, path: [field] });
    }
  }

  if (values.payer_document && !isValidCpf(values.payer_document)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Informe um CPF válido para o pagador.',
      path: ['payer_document'],
    });
  }

  if (values.payer_phone && digitsOnly(values.payer_phone).length < 10) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Informe um telefone do pagador com DDD.',
      path: ['payer_phone'],
    });
  }

  if (values.address_zip_code && digitsOnly(values.address_zip_code).length !== 8) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'O CEP deve ter 8 dígitos.',
      path: ['address_zip_code'],
    });
  }

  if (values.address_state && normalizeStateCode(values.address_state).length !== 2) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Use a UF com 2 letras.',
      path: ['address_state'],
    });
  }

  if (values.card_holder_name && !hasTwoWords(values.card_holder_name)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Informe nome e sobrenome do titular.',
      path: ['card_holder_name'],
    });
  }

  if (values.card_number && !isValidCardNumber(values.card_number)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Informe um número de cartão válido.',
      path: ['card_number'],
    });
  }

  if ((values.card_exp_month || values.card_exp_year) && !isValidCardExpiry(values.card_exp_month, values.card_exp_year)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Informe uma validade vigente para o cartão.',
      path: ['card_exp_year'],
    });
  }

  if (values.card_cvv && ![3, 4].includes(digitsOnly(values.card_cvv).length)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'O CVV deve ter 3 ou 4 dígitos.',
      path: ['card_cvv'],
    });
  }
});

type CheckoutFormValues = z.infer<typeof checkoutFormSchema>;

const initialCheckoutFormValues: CheckoutFormValues = {
  responsible_name: '',
  whatsapp: '',
  email: '',
  organization_name: '',
  package_id: '',
  event_title: '',
  event_type: 'wedding',
  event_date: '',
  event_city: '',
  event_description: '',
  payment_method: 'pix',
  payer_document: '',
  payer_phone: '',
  address_street: '',
  address_number: '',
  address_district: '',
  address_complement: '',
  address_zip_code: '',
  address_city: '',
  address_state: '',
  card_number: '',
  card_holder_name: '',
  card_exp_month: '',
  card_exp_year: '',
  card_cvv: '',
};

type CheckoutResumeDraft = Omit<
  CheckoutFormValues,
  'card_number' | 'card_holder_name' | 'card_exp_month' | 'card_exp_year' | 'card_cvv'
> & {
  source: 'identity_conflict';
  saved_at: string;
};

type ResumeNotice = {
  title: string;
  description: string;
  mode: 'auto' | 'manual';
};

function formatCurrency(amountCents: number, currency = 'BRL') {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency }).format(amountCents / 100);
}

function formatRemainingTime(expiresAt: string | null | undefined, nowMs: number) {
  if (!expiresAt) return null;

  const remainingSeconds = Math.max(0, Math.ceil((new Date(expiresAt).getTime() - nowMs) / 1000));
  const minutes = Math.floor(remainingSeconds / 60);
  const seconds = remainingSeconds % 60;

  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

function formatCpf(value: string) {
  const digits = digitsOnly(value).slice(0, 11);

  return digits
    .replace(/^(\d{3})(\d)/, '$1.$2')
    .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
}

function formatPhone(value: string) {
  const digits = digitsOnly(value).slice(0, 11);

  if (digits.length <= 2) return digits;
  if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
  if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;

  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

function formatZipCode(value: string) {
  const digits = digitsOnly(value).slice(0, 8);

  if (digits.length <= 5) return digits;

  return `${digits.slice(0, 5)}-${digits.slice(5)}`;
}

function formatCardNumber(value: string) {
  return digitsOnly(value)
    .slice(0, 19)
    .replace(/(\d{4})(?=\d)/g, '$1 ')
    .trim();
}

function formatCardExpiryPart(value: string, length: number) {
  return digitsOnly(value).slice(0, length);
}

function normalizeCardHolderName(value: string) {
  return value.replace(/\s+/g, ' ').trimStart().toUpperCase();
}

type CardBrandMeta = {
  key: 'visa' | 'mastercard' | 'amex' | 'elo' | 'hipercard' | 'generic';
  label: string;
  badgeClassName: string;
  surfaceClassName: string;
};

function detectCardBrand(cardNumber: string): CardBrandMeta | null {
  const digits = digitsOnly(cardNumber);

  if (!digits) return null;

  if (/^4/.test(digits)) {
    return {
      key: 'visa',
      label: 'Visa',
      badgeClassName: 'border-sky-300/30 bg-sky-400/15 text-sky-50',
      surfaceClassName: 'from-sky-500/30 via-cyan-400/15 to-slate-950',
    };
  }

  if (/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/.test(digits)) {
    return {
      key: 'mastercard',
      label: 'Mastercard',
      badgeClassName: 'border-orange-300/30 bg-orange-400/15 text-orange-50',
      surfaceClassName: 'from-orange-500/30 via-rose-400/15 to-slate-950',
    };
  }

  if (/^3[47]/.test(digits)) {
    return {
      key: 'amex',
      label: 'American Express',
      badgeClassName: 'border-cyan-300/30 bg-cyan-400/15 text-cyan-50',
      surfaceClassName: 'from-cyan-500/30 via-sky-400/15 to-slate-950',
    };
  }

  if (/^(4011(78|79)|431274|438935|451416|457393|504175|506(699|7\d{2}|8\d{2}|9\d{2})|627780|636297|636368|650)/.test(digits)) {
    return {
      key: 'elo',
      label: 'Elo',
      badgeClassName: 'border-emerald-300/30 bg-emerald-400/15 text-emerald-50',
      surfaceClassName: 'from-emerald-500/30 via-lime-400/15 to-slate-950',
    };
  }

  if (/^(3841|60)/.test(digits)) {
    return {
      key: 'hipercard',
      label: 'Hipercard',
      badgeClassName: 'border-fuchsia-300/30 bg-fuchsia-400/15 text-fuchsia-50',
      surfaceClassName: 'from-fuchsia-500/30 via-pink-400/15 to-slate-950',
    };
  }

  return {
    key: 'generic',
    label: 'Cartao',
    badgeClassName: 'border-white/15 bg-white/10 text-white',
    surfaceClassName: 'from-white/15 via-white/5 to-slate-950',
  };
}

function buildPixTimeline(response: PublicEventCheckoutResponse | undefined) {
  const checkout = response?.checkout;
  const payment = checkout?.payment;

  if (!checkout || payment?.method !== 'pix') {
    return [];
  }

  const checkoutStatus = checkout.status;
  const paymentStatus = payment.status ?? payment.gateway_status ?? '';
  const isPaid = checkoutStatus === 'paid' || paymentStatus === 'paid' || payment.gateway_status === 'paid';
  const isFailed = ['failed', 'canceled', 'refunded'].includes(checkoutStatus)
    || ['failed', 'canceled', 'refunded'].includes(paymentStatus)
    || ['failed', 'canceled', 'refunded'].includes(payment.gateway_status ?? '');
  const isPending = !isPaid && !isFailed;

  return [
    {
      key: 'created',
      label: 'Pedido criado',
      description: 'O pedido ja foi registrado localmente no Billing.',
      state: 'done',
    },
    {
      key: 'pix',
      label: 'Aguardando pagamento Pix',
      description: 'O QR Code foi gerado e a conciliacao final depende do webhook.',
      state: isPaid ? 'done' : isFailed ? 'idle' : 'active',
    },
    {
      key: 'result',
      label: isFailed ? 'Pagamento nao aprovado' : 'Pagamento confirmado',
      description: isFailed
        ? 'O pedido foi conciliado localmente como falho, cancelado ou revertido.'
        : 'Assim que o webhook confirmar, o evento segue para ativacao do pacote.',
      state: isPaid || isFailed ? 'done' : 'idle',
    },
  ] as const;
}

function describeCardCheckout(response: PublicEventCheckoutResponse | undefined) {
  const checkout = response?.checkout;
  const payment = checkout?.payment;
  const card = payment?.credit_card;

  if (!checkout || payment?.method !== 'credit_card') {
    return null;
  }

  if (checkout.status === 'paid' || payment.status === 'paid' || payment.gateway_status === 'paid') {
    return {
      tone: 'success',
      title: 'Pagamento aprovado',
      description: card?.acquirer_message || 'A cobranca foi conciliada localmente como aprovada.',
    } as const;
  }

  if (checkout.status === 'failed' || payment.status === 'failed' || payment.gateway_status === 'failed') {
    return {
      tone: 'error',
      title: 'Pagamento recusado',
      description: card?.acquirer_message || 'O emissor ou o fluxo PSP retornou falha para esta tentativa.',
    } as const;
  }

  if (checkout.status === 'refunded' || payment.status === 'refunded' || payment.gateway_status === 'chargedback') {
    return {
      tone: 'warning',
      title: 'Cobranca revertida',
      description: 'O pedido foi atualizado localmente como estornado ou chargeback.',
    } as const;
  }

  return {
    tone: 'warning',
    title: 'Analise local em andamento',
    description: 'A tokenizacao ja foi concluida e agora o checkout aguarda a reconciliacao local da cobranca.',
  } as const;
}

function formatStatus(response: PublicEventCheckoutResponse | undefined) {
  const checkout = response?.checkout;
  const payment = checkout?.payment;
  const card = payment?.credit_card;

  if (!checkout || !payment) {
    return { tone: 'idle', title: 'Preencha os dados e escolha o meio de pagamento', description: 'O pedido será criado localmente e o status público continuará sempre lendo o banco local.' } as const;
  }

  if (checkout.status === 'paid' || payment.status === 'paid' || payment.gateway_status === 'paid') {
    return { tone: 'success', title: 'Pagamento confirmado', description: 'O pacote já foi conciliado localmente e o evento pode seguir para ativação.' } as const;
  }

  if (checkout.status === 'failed' || payment.status === 'failed' || payment.gateway_status === 'failed') {
    return { tone: 'error', title: 'Pagamento não aprovado', description: card?.acquirer_message || 'O gateway retornou falha para esta tentativa.' } as const;
  }

  if (checkout.status === 'refunded' || payment.status === 'refunded' || payment.gateway_status === 'chargedback') {
    return { tone: 'warning', title: 'Cobrança revertida', description: 'O pedido local foi conciliado como estornado ou chargeback.' } as const;
  }

  if (payment.method === 'credit_card') {
    return { tone: 'warning', title: 'Cartão em processamento', description: 'O pedido ainda está em transição e a página seguirá consultando apenas o status local.' } as const;
  }

  return { tone: 'info', title: 'Pix gerado com sucesso', description: 'Use o QR Code abaixo e acompanhe a confirmação por polling local.' } as const;
}

function shouldPollCheckout(response: PublicEventCheckoutResponse | undefined) {
  const checkout = response?.checkout;
  const payment = checkout?.payment;

  if (!checkout || !payment) return false;

  const terminalStates = new Set(['paid', 'failed', 'canceled', 'refunded']);

  return !terminalStates.has(checkout.status)
    && !terminalStates.has(payment.status ?? '')
    && !terminalStates.has(payment.gateway_status ?? '');
}

function buildCheckoutPayload(values: CheckoutFormValues, cardToken?: string): PublicEventCheckoutPayload {
  const normalizedEmail = values.email.trim() || null;
  const normalizedOrganizationName = values.organization_name.trim() || null;
  const normalizedEventDate = values.event_date.trim() || null;
  const normalizedEventCity = values.event_city.trim() || null;
  const normalizedDescription = values.event_description.trim() || null;
  const normalizedWhatsapp = digitsOnly(values.whatsapp) || values.whatsapp.trim();

  const event = {
    title: values.event_title.trim(),
    event_type: values.event_type,
    event_date: normalizedEventDate,
    city: normalizedEventCity,
    description: normalizedDescription,
  };

  if (values.payment_method === 'credit_card') {
    const address = {
      street: values.address_street.trim(),
      number: values.address_number.trim(),
      district: values.address_district.trim(),
      complement: values.address_complement.trim() || null,
      zip_code: digitsOnly(values.address_zip_code),
      city: values.address_city.trim(),
      state: values.address_state.trim().toUpperCase(),
      country: 'BR',
    };

    return {
      responsible_name: values.responsible_name.trim(),
      whatsapp: normalizedWhatsapp,
      email: normalizedEmail,
      organization_name: normalizedOrganizationName,
      package_id: Number(values.package_id),
      payer: {
        name: values.responsible_name.trim(),
        email: normalizedEmail,
        document: digitsOnly(values.payer_document),
        document_type: 'cpf',
        phone: digitsOnly(values.payer_phone),
        address,
      },
      payment: {
        method: 'credit_card',
        credit_card: {
          installments: 1,
          card_token: cardToken ?? null,
          billing_address: address,
        },
      },
      event,
    };
  }

  return {
    responsible_name: values.responsible_name.trim(),
    whatsapp: normalizedWhatsapp,
    email: normalizedEmail,
    organization_name: normalizedOrganizationName,
    package_id: Number(values.package_id),
    payment: { method: 'pix' },
    event,
  };
}

function summaryToneClass(tone: ReturnType<typeof formatStatus>['tone']) {
  switch (tone) {
    case 'success': return 'border-emerald-200 bg-emerald-50 text-emerald-950';
    case 'error': return 'border-rose-200 bg-rose-50 text-rose-950';
    case 'warning': return 'border-amber-200 bg-amber-50 text-amber-950';
    case 'info': return 'border-sky-200 bg-sky-50 text-sky-950';
    default: return 'border-slate-200 bg-white/85 text-slate-950';
  }
}

function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return <p className="text-xs text-rose-300">{message}</p>;
}

interface PublicEventCheckoutPageProps {
  pollingIntervalMs?: number;
}

interface IdentityConflictState {
  message: string;
  loginPath: string;
}

function findIdentityConflictMessage(error: ApiError): string | null {
  const candidates = [
    error.fieldError('whatsapp'),
    error.fieldError('email'),
    typeof error.body?.message === 'string' ? error.body.message : null,
  ].filter((value): value is string => !!value);

  return candidates.find((message) => message.toLowerCase().includes('faca login para continuar')) ?? null;
}

function buildLoginResumePath() {
  return `/login?returnTo=${encodeURIComponent(`/checkout/evento?resume=${AUTH_RESUME_SEARCH_VALUE}`)}`;
}

function buildResumeDraft(values: CheckoutFormValues): CheckoutResumeDraft {
  return {
    responsible_name: values.responsible_name,
    whatsapp: values.whatsapp,
    email: values.email,
    organization_name: values.organization_name,
    package_id: values.package_id,
    event_title: values.event_title,
    event_type: values.event_type,
    event_date: values.event_date,
    event_city: values.event_city,
    event_description: values.event_description,
    payment_method: values.payment_method,
    payer_document: values.payer_document,
    payer_phone: values.payer_phone,
    address_street: values.address_street,
    address_number: values.address_number,
    address_district: values.address_district,
    address_complement: values.address_complement,
    address_zip_code: values.address_zip_code,
    address_city: values.address_city,
    address_state: values.address_state,
    source: 'identity_conflict',
    saved_at: new Date().toISOString(),
  };
}

function readResumeDraft(): CheckoutResumeDraft | null {
  const stored = window.localStorage.getItem(RESUME_DRAFT_STORAGE_KEY);

  if (!stored) return null;

  try {
    const parsed = JSON.parse(stored) as CheckoutResumeDraft;

    if (!parsed || typeof parsed !== 'object') {
      return null;
    }

    return parsed;
  } catch {
    return null;
  }
}

function writeResumeDraft(values: CheckoutFormValues) {
  window.localStorage.setItem(RESUME_DRAFT_STORAGE_KEY, JSON.stringify(buildResumeDraft(values)));
}

function clearResumeDraft() {
  window.localStorage.removeItem(RESUME_DRAFT_STORAGE_KEY);
}

function restoreFormValuesFromResumeDraft(draft: CheckoutResumeDraft): CheckoutFormValues {
  return {
    ...initialCheckoutFormValues,
    ...draft,
  };
}

export default function PublicEventCheckoutPage({
  pollingIntervalMs = POLLING_INTERVAL_MS,
}: PublicEventCheckoutPageProps = {}) {
  const { isAuthenticated } = useAuth();
  const queryClient = useQueryClient();
  const [searchParams, setSearchParams] = useSearchParams();
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [identityConflict, setIdentityConflict] = useState<IdentityConflictState | null>(null);
  const [copiedPix, setCopiedPix] = useState(false);
  const [nowMs, setNowMs] = useState(() => Date.now());
  const [resumeDraft, setResumeDraft] = useState<CheckoutResumeDraft | null>(() => readResumeDraft());
  const [resumeNotice, setResumeNotice] = useState<ResumeNotice | null>(null);
  const [resumeInitialized, setResumeInitialized] = useState(false);
  const [resumeAutoSubmitted, setResumeAutoSubmitted] = useState(false);
  const checkoutUuid = searchParams.get('checkout');
  const resumeMode = searchParams.get('resume');

  const form = useForm<CheckoutFormValues>({
    resolver: zodResolver(checkoutFormSchema),
    defaultValues: initialCheckoutFormValues,
    mode: 'onBlur',
    reValidateMode: 'onChange',
  });

  const paymentMethod = form.watch('payment_method');
  const selectedPackageId = form.watch('package_id');
  const deferredPackageId = useDeferredValue(selectedPackageId);
  const responsibleName = form.watch('responsible_name');
  const whatsapp = form.watch('whatsapp');
  const email = form.watch('email');
  const eventTitle = form.watch('event_title');
  const payerDocument = form.watch('payer_document');
  const payerPhone = form.watch('payer_phone');
  const addressStreet = form.watch('address_street');
  const addressNumber = form.watch('address_number');
  const addressDistrict = form.watch('address_district');
  const addressZipCode = form.watch('address_zip_code');
  const addressCity = form.watch('address_city');
  const cardNumber = form.watch('card_number');
  const cardHolderName = form.watch('card_holder_name');
  const cardExpMonth = form.watch('card_exp_month');
  const cardExpYear = form.watch('card_exp_year');
  const cardCvv = form.watch('card_cvv');
  const addressState = form.watch('address_state');
  const cardBrand = detectCardBrand(cardNumber);
  const cardPreviewNumber = cardNumber || '0000 0000 0000 0000';
  const cardPreviewHolder = cardHolderName || 'NOME NO CARTAO';
  const cardPreviewExpiry = cardExpMonth && cardExpYear ? `${cardExpMonth}/${cardExpYear}` : 'MM/AA';
  const cardChecklist = useMemo(
    () => buildCardChecklist({
      ...initialCheckoutFormValues,
      responsible_name: responsibleName,
      whatsapp,
      email,
      event_title: eventTitle,
      payment_method: paymentMethod,
      payer_document: payerDocument,
      payer_phone: payerPhone,
      address_street: addressStreet,
      address_number: addressNumber,
      address_district: addressDistrict,
      address_zip_code: addressZipCode,
      address_city: addressCity,
      address_state: addressState,
      card_number: cardNumber,
      card_holder_name: cardHolderName,
      card_exp_month: cardExpMonth,
      card_exp_year: cardExpYear,
      card_cvv: cardCvv,
    }),
    [
      addressCity,
      addressDistrict,
      addressNumber,
      addressState,
      addressStreet,
      addressZipCode,
      cardCvv,
      cardExpMonth,
      cardExpYear,
      cardHolderName,
      cardNumber,
      email,
      eventTitle,
      payerDocument,
      payerPhone,
      paymentMethod,
      responsibleName,
      whatsapp,
    ],
  );
  const cardChecklistProgress = Math.round((cardChecklist.completed / cardChecklist.total) * 100);

  const packagesQuery = useQuery({
    queryKey: ['public-event-packages'],
    queryFn: () => publicEventPackagesService.list(),
  });

  useEffect(() => {
    if (!checkoutUuid) {
      const storedUuid = window.localStorage.getItem(STORAGE_KEY);
      if (storedUuid) {
        startTransition(() => setSearchParams({ checkout: storedUuid }, { replace: true }));
      }
    }
  }, [checkoutUuid, setSearchParams]);

  useEffect(() => {
    const packages = packagesQuery.data ?? [];
    if (packages.length > 0 && !form.getValues('package_id')) {
      form.setValue('package_id', String(packages[0].id), { shouldDirty: false, shouldTouch: false });
    }
  }, [form, packagesQuery.data]);

  useEffect(() => {
    if (paymentMethod !== 'credit_card') {
      return;
    }

    if (digitsOnly(payerPhone).length > 0) {
      return;
    }

    if (digitsOnly(whatsapp).length < 10) {
      return;
    }

    form.setValue('payer_phone', formatPhone(whatsapp), {
      shouldDirty: false,
      shouldTouch: false,
      shouldValidate: false,
    });
  }, [form, payerPhone, paymentMethod, whatsapp]);

  const checkoutQuery = useQuery({
    queryKey: ['public-event-checkout', checkoutUuid],
    enabled: !!checkoutUuid,
    queryFn: () => publicEventCheckoutService.get(String(checkoutUuid)),
    staleTime: pollingIntervalMs,
    refetchInterval: (query) => shouldPollCheckout(query.state.data) ? pollingIntervalMs : false,
  });

  const selectedPackage = useMemo(
    () => (packagesQuery.data ?? []).find((item) => String(item.id) === deferredPackageId) ?? null,
    [deferredPackageId, packagesQuery.data],
  );

  const createCheckoutMutation = useMutation({
    mutationFn: async (values: CheckoutFormValues) => {
      let cardToken: string | undefined;

      if (values.payment_method === 'credit_card') {
        const token = await createPagarmeCardToken({
          number: digitsOnly(values.card_number),
          holderName: normalizeCardHolderName(values.card_holder_name),
          expMonth: digitsOnly(values.card_exp_month),
          expYear: digitsOnly(values.card_exp_year),
          cvv: digitsOnly(values.card_cvv),
        });

        cardToken = token.id;
      }

      return publicEventCheckoutService.create(buildCheckoutPayload(values, cardToken));
    },
    onSuccess: (response) => {
      setSubmitError(null);
      setIdentityConflict(null);
      setResumeNotice(null);
      form.clearErrors();
      if (response.token) setToken(response.token);
      clearResumeDraft();
      setResumeDraft(null);
      setResumeInitialized(false);
      setResumeAutoSubmitted(false);
      queryClient.setQueryData(['public-event-checkout', response.checkout.uuid], response);
      window.localStorage.setItem(STORAGE_KEY, response.checkout.uuid);
      startTransition(() => setSearchParams({ checkout: response.checkout.uuid }, { replace: true }));
    },
    onError: (error, values) => {
      if (error instanceof ApiError) {
        const validationEntries = Object.entries(error.validationErrors ?? {});
        const conflictMessage = findIdentityConflictMessage(error);

        if (conflictMessage) {
          writeResumeDraft(values);
          setResumeDraft(buildResumeDraft(values));
          setIdentityConflict({
            message: conflictMessage,
            loginPath: buildLoginResumePath(),
          });
          setSubmitError(null);
          return;
        }

        for (const [field, messages] of validationEntries) {
          if (messages[0]) {
            form.setError(field as never, {
              type: 'server',
              message: messages[0],
            });
          }
        }

        setIdentityConflict(null);
        setSubmitError(validationEntries[0]?.[1]?.[0] ?? error.message);
        return;
      }

      setIdentityConflict(null);
      if (error instanceof ApiError || error instanceof PagarmeTokenizationError) {
        setSubmitError(error.message);
      } else {
        setSubmitError('Não foi possível iniciar o checkout agora.');
      }
    },
  });

  useEffect(() => {
    if (resumeInitialized || resumeMode !== AUTH_RESUME_SEARCH_VALUE || !resumeDraft || checkoutUuid) {
      return;
    }

    const restoredValues = restoreFormValuesFromResumeDraft(resumeDraft);

    form.reset(restoredValues);
    setIdentityConflict(null);
    setSubmitError(null);
    setResumeNotice(
      restoredValues.payment_method === 'credit_card'
        ? {
            title: 'Sessao retomada com a sua conta',
            description: 'Retomamos os dados seguros da sua jornada. Para sua seguranca, os campos do cartao precisam ser preenchidos novamente.',
            mode: 'manual',
          }
        : {
            title: 'Sessao retomada com a sua conta',
            description: 'Seu rascunho foi restaurado e o checkout Pix sera retomado automaticamente na conta autenticada.',
            mode: 'auto',
          },
    );
    setResumeInitialized(true);
  }, [checkoutUuid, form, resumeDraft, resumeInitialized, resumeMode]);

  useEffect(() => {
    if (
      !resumeInitialized
      || !isAuthenticated
      || !resumeDraft
      || resumeMode !== AUTH_RESUME_SEARCH_VALUE
      || checkoutUuid
      || resumeAutoSubmitted
      || createCheckoutMutation.isPending
      || resumeDraft.payment_method !== 'pix'
    ) {
      return;
    }

    setResumeAutoSubmitted(true);
    void createCheckoutMutation.mutateAsync(restoreFormValuesFromResumeDraft(resumeDraft)).catch(() => undefined);
  }, [
    checkoutUuid,
    createCheckoutMutation,
    isAuthenticated,
    resumeAutoSubmitted,
    resumeDraft,
    resumeInitialized,
    resumeMode,
  ]);

  const checkoutResponse = checkoutQuery.data ?? undefined;
  const status = formatStatus(checkoutResponse);
  const isPolling = shouldPollCheckout(checkoutResponse) && checkoutQuery.isFetching;
  const pixPayment = checkoutResponse?.checkout.payment.method === 'pix'
    ? checkoutResponse.checkout.payment.pix
    : null;
  const pixWhatsappNotification = checkoutResponse?.checkout.payment.whatsapp.pix_generated ?? null;
  const pixExpiresAt = pixPayment?.expires_at ?? checkoutResponse?.checkout.payment.expires_at ?? null;
  const pixExpiresLabel = formatRemainingTime(pixExpiresAt, nowMs);
  const pixTimeline = buildPixTimeline(checkoutResponse);
  const cardRuntime = describeCardCheckout(checkoutResponse);

  function updateMaskedField<K extends keyof CheckoutFormValues>(field: K, value: CheckoutFormValues[K]) {
    form.setValue(field, value, {
      shouldDirty: true,
      shouldTouch: true,
      shouldValidate: form.formState.isSubmitted || Boolean(form.formState.errors[field]),
    });
  }

  useEffect(() => {
    if (!copiedPix) return;

    const timer = window.setTimeout(() => setCopiedPix(false), 2500);

    return () => window.clearTimeout(timer);
  }, [copiedPix]);

  useEffect(() => {
    if (!pixExpiresAt || checkoutResponse?.checkout.payment.method !== 'pix' || !shouldPollCheckout(checkoutResponse)) {
      return undefined;
    }

    const timer = window.setInterval(() => setNowMs(Date.now()), 1000);

    return () => window.clearInterval(timer);
  }, [checkoutResponse, pixExpiresAt]);

  async function handleSubmit(values: CheckoutFormValues) {
    setSubmitError(null);
    setIdentityConflict(null);
    form.clearErrors();

    try {
      await createCheckoutMutation.mutateAsync(values);
    } catch {
      // handled by mutation callbacks
    }
  }

  function restoreAnotherCheckout() {
    window.localStorage.removeItem(STORAGE_KEY);
    clearResumeDraft();
    setSubmitError(null);
    setIdentityConflict(null);
    setResumeNotice(null);
    setResumeDraft(null);
    setResumeInitialized(false);
    setResumeAutoSubmitted(false);
    form.clearErrors();
    form.reset(initialCheckoutFormValues);
    queryClient.removeQueries({ queryKey: ['public-event-checkout'] });
    startTransition(() => setSearchParams({}, { replace: true }));
  }

  async function handleCopyPixCode() {
    const qrCode = pixPayment?.qr_code;

    if (!qrCode || !navigator.clipboard?.writeText) return;

    await navigator.clipboard.writeText(qrCode);
    setCopiedPix(true);
  }

  return (
    <div className="min-h-[100dvh] bg-slate-950 text-slate-50">
      <div className="mx-auto max-w-7xl px-4 py-6 md:px-6 lg:px-8">
        <div className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr] lg:items-start">
          <div className="space-y-6">
            <Card className="overflow-hidden border-white/10 bg-white/[0.06] text-white shadow-2xl shadow-emerald-950/20 backdrop-blur">
              <div className="relative overflow-hidden px-5 py-6 md:px-7 md:py-8">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(16,185,129,0.22),transparent_35%),radial-gradient(circle_at_bottom_right,rgba(56,189,248,0.18),transparent_35%)]" />
                <div className="relative space-y-4">
                  <div className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-white/78">
                    <Sparkles className="h-3.5 w-3.5" />
                    Compra única com Pix ou cartão
                  </div>
                  <div className="space-y-3">
                    <h1 className="max-w-3xl text-3xl font-semibold tracking-tight md:text-4xl">
                      Checkout público com status local, webhook assíncrono e tokenização oficial.
                    </h1>
                    <p className="max-w-2xl text-sm text-white/76 md:text-base">
                      Esta tela usa o contrato do módulo Billing, envia apenas `card_token` ao backend e mantém o polling sempre no estado local do pedido.
                    </p>
                  </div>
                </div>
              </div>
            </Card>

            {resumeNotice ? (
              <Card className="border-sky-300/15 bg-sky-400/10 text-white shadow-xl shadow-sky-950/20 backdrop-blur">
                <CardContent className="space-y-4 p-5 md:p-6">
                  <div className="flex items-start gap-3">
                    {resumeNotice.mode === 'auto' ? (
                      <RefreshCcw className={cn('mt-1 h-5 w-5 text-sky-200', createCheckoutMutation.isPending ? 'animate-spin' : '')} />
                    ) : (
                      <LockKeyhole className="mt-1 h-5 w-5 text-sky-200" />
                    )}
                    <div className="space-y-2">
                      <p className="text-xs font-semibold uppercase tracking-[0.22em] text-sky-100/75">Sessao retomada</p>
                      <h2 className="text-xl font-semibold text-white">{resumeNotice.title}</h2>
                      <p className="max-w-2xl text-sm text-sky-50/85">{resumeNotice.description}</p>
                    </div>
                  </div>
                  <div className="grid gap-3 md:grid-cols-2">
                    <div className="rounded-[24px] border border-white/10 bg-black/10 p-4">
                      <p className="text-[11px] uppercase tracking-[0.16em] text-sky-100/70">Conta existente</p>
                      <p className="mt-2 text-sm font-medium text-white">A autenticacao ja ocorreu e a jornada voltou exatamente para este checkout.</p>
                    </div>
                    <div className="rounded-[24px] border border-white/10 bg-black/10 p-4">
                      <p className="text-[11px] uppercase tracking-[0.16em] text-sky-100/70">Seguranca</p>
                      <p className="mt-2 text-sm font-medium text-white">Retomamos os dados seguros da sua jornada sem persistir PAN, CVV ou validade do cartao.</p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ) : null}

            {checkoutResponse?.onboarding ? (
              <Card className="border-emerald-300/15 bg-emerald-400/10 text-white shadow-xl shadow-emerald-950/20 backdrop-blur">
                <CardContent className="space-y-5 p-5 md:p-6">
                  <div className="space-y-2">
                    <p className="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-200/80">Onboarding</p>
                    <h2 className="text-2xl font-semibold text-white">{checkoutResponse.onboarding.title}</h2>
                    <p className="max-w-2xl text-sm text-emerald-50/85">{checkoutResponse.onboarding.description}</p>
                  </div>

                  <div className="grid gap-3 md:grid-cols-3">
                    <div className="rounded-[24px] border border-white/10 bg-black/10 p-4">
                      <p className="text-[11px] uppercase tracking-[0.16em] text-emerald-100/70">Conta</p>
                      <p className="mt-2 text-sm font-medium text-white">Usuario autenticado e pronto para seguir para o painel.</p>
                    </div>
                    <div className="rounded-[24px] border border-white/10 bg-black/10 p-4">
                      <p className="text-[11px] uppercase tracking-[0.16em] text-emerald-100/70">Evento</p>
                      <p className="mt-2 text-sm font-medium text-white">Evento criado na jornada publica e aguardando a liberacao do pacote.</p>
                    </div>
                    <div className="rounded-[24px] border border-white/10 bg-black/10 p-4">
                      <p className="text-[11px] uppercase tracking-[0.16em] text-emerald-100/70">Pedido</p>
                      <p className="mt-2 text-sm font-medium text-white">Acompanhe o pagamento aqui e continue a configuracao no painel.</p>
                    </div>
                  </div>

                  <div className="flex flex-col gap-3 md:flex-row">
                    <Button asChild size="lg" className="h-12 rounded-2xl bg-white text-emerald-950 hover:bg-emerald-50">
                      <Link to={checkoutResponse.onboarding.next_path}>Abrir painel do evento</Link>
                    </Button>
                    <Button asChild variant="outline" size="lg" className="h-12 rounded-2xl border-white/20 bg-transparent text-white hover:bg-white/10">
                      <a href="#checkout-status">Acompanhar status do pagamento</a>
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ) : null}

            <Card className="border-white/10 bg-white/[0.05] shadow-xl shadow-slate-950/30 backdrop-blur">
              <CardContent className="space-y-6 p-5 md:p-6">
                <div className="space-y-2">
                  <p className="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-300/80">Pacote</p>
                  <h2 className="text-xl font-semibold text-white">Escolha o pacote do evento</h2>
                </div>

                {packagesQuery.isLoading ? (
                  <div className="flex items-center gap-3 rounded-3xl border border-white/10 bg-white/[0.04] px-4 py-6 text-sm text-slate-300">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Carregando catálogo público de pacotes...
                  </div>
                ) : null}

                {packagesQuery.isError ? (
                  <div className="rounded-3xl border border-rose-500/20 bg-rose-500/10 px-4 py-4 text-sm text-rose-100">
                    Não foi possível carregar o catálogo público agora.
                  </div>
                ) : null}

                <div className="grid gap-3 md:grid-cols-2">
                  {(packagesQuery.data ?? []).map((pkg) => {
                    const isSelected = String(pkg.id) === selectedPackageId;

                    return (
                      <button
                        key={pkg.id}
                        type="button"
                        onClick={() => form.setValue('package_id', String(pkg.id), { shouldDirty: true })}
                        className={cn(
                          'rounded-[28px] border p-4 text-left transition',
                          isSelected
                            ? 'border-emerald-300 bg-emerald-300/10 shadow-lg shadow-emerald-500/10'
                            : 'border-white/10 bg-white/[0.04] hover:border-white/20 hover:bg-white/[0.07]',
                        )}
                      >
                        <div className="space-y-2">
                          <p className="text-lg font-semibold text-white">{pkg.name}</p>
                          <p className="text-sm text-slate-300">{pkg.description || 'Pacote avulso para evento único.'}</p>
                        </div>
                        <div className="mt-4 flex items-center justify-between text-sm">
                          <span className="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs text-slate-200">
                            {pkg.modules.wall ? 'Wall ativo' : 'Wall off'}
                          </span>
                          <span className="font-semibold text-white">
                            {pkg.default_price ? formatCurrency(pkg.default_price.amount_cents, pkg.default_price.currency) : 'Sob consulta'}
                          </span>
                        </div>
                      </button>
                    );
                  })}
                </div>
              </CardContent>
            </Card>

            <Card className="border-white/10 bg-white/[0.05] shadow-xl shadow-slate-950/30 backdrop-blur">
              <CardContent className="p-5 md:p-6">
                <form className="space-y-6" onSubmit={form.handleSubmit(handleSubmit)}>
                  <section className="grid gap-3 xl:grid-cols-3">
                    <div className="rounded-[26px] border border-white/10 bg-white/[0.04] p-4">
                      <p className="text-[11px] uppercase tracking-[0.18em] text-emerald-200/75">Etapa 1</p>
                      <p className="mt-2 text-base font-semibold text-white">Conta e evento</p>
                      <p className="mt-2 text-sm text-slate-300">
                        {responsibleName.trim() && eventTitle.trim()
                          ? `${responsibleName.trim()} ja iniciou ${eventTitle.trim()}.`
                          : 'Preencha quem esta comprando e qual evento sera ativado.'}
                      </p>
                    </div>
                    <div className="rounded-[26px] border border-white/10 bg-white/[0.04] p-4">
                      <p className="text-[11px] uppercase tracking-[0.18em] text-sky-200/75">Etapa 2</p>
                      <p className="mt-2 text-base font-semibold text-white">Pagamento guiado</p>
                      <p className="mt-2 text-sm text-slate-300">
                        {paymentMethod === 'credit_card'
                          ? cardChecklist.ready
                            ? 'Os dados criticos do cartao ja estao prontos para tokenizacao segura.'
                            : `Faltam ${cardChecklist.total - cardChecklist.completed} ajustes para tokenizar o cartao com seguranca.`
                          : 'Pix gera QR Code imediatamente e continua conciliando pelo status local.'}
                      </p>
                    </div>
                    <div className="rounded-[26px] border border-white/10 bg-white/[0.04] p-4">
                      <p className="text-[11px] uppercase tracking-[0.18em] text-amber-200/75">Etapa 3</p>
                      <p className="mt-2 text-base font-semibold text-white">Acompanhamento local</p>
                      <p className="mt-2 text-sm text-slate-300">
                        Depois da criacao do pedido, esta tela acompanha apenas o estado local reconciliado por webhook.
                      </p>
                    </div>
                  </section>

                  <section className="space-y-4">
                    <div className="space-y-2">
                      <p className="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-300/80">Responsável</p>
                      <h2 className="text-xl font-semibold text-white">Quem está comprando o pacote</h2>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                      <div className="space-y-2">
                        <label htmlFor="responsible_name" className="text-sm font-medium text-slate-200">Nome do responsável</label>
                        <Input id="responsible_name" className="h-12 border-white/10 bg-white/5 text-white" {...form.register('responsible_name')} />
                        <FieldError message={form.formState.errors.responsible_name?.message} />
                      </div>
                      <div className="space-y-2">
                        <label htmlFor="whatsapp" className="text-sm font-medium text-slate-200">WhatsApp</label>
                        <Input id="whatsapp" className="h-12 border-white/10 bg-white/5 text-white" {...form.register('whatsapp')} />
                        <FieldError message={form.formState.errors.whatsapp?.message} />
                      </div>
                      <div className="space-y-2">
                        <label htmlFor="email" className="text-sm font-medium text-slate-200">Email principal</label>
                        <Input id="email" type="email" className="h-12 border-white/10 bg-white/5 text-white" {...form.register('email')} />
                        <FieldError message={form.formState.errors.email?.message} />
                      </div>
                      <div className="space-y-2">
                        <label htmlFor="organization_name" className="text-sm font-medium text-slate-200">Nome da organização</label>
                        <Input id="organization_name" className="h-12 border-white/10 bg-white/5 text-white" placeholder="Opcional" {...form.register('organization_name')} />
                      </div>
                    </div>
                  </section>

                  <section className="space-y-4">
                    <div className="space-y-2">
                      <p className="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-300/80">Evento</p>
                      <h2 className="text-xl font-semibold text-white">Dados do evento</h2>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                      <div className="space-y-2 md:col-span-2">
                        <label htmlFor="event_title" className="text-sm font-medium text-slate-200">Nome do evento</label>
                        <Input id="event_title" className="h-12 border-white/10 bg-white/5 text-white" {...form.register('event_title')} />
                        <FieldError message={form.formState.errors.event_title?.message} />
                      </div>
                      <div className="space-y-2">
                        <label htmlFor="event_type" className="text-sm font-medium text-slate-200">Tipo do evento</label>
                        <select id="event_type" className="h-12 w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 text-sm text-white" {...form.register('event_type')}>
                          <option value="wedding">Casamento</option>
                          <option value="birthday">Aniversário</option>
                          <option value="fifteen">15 anos</option>
                          <option value="corporate">Corporativo</option>
                          <option value="fair">Feira</option>
                          <option value="graduation">Formatura</option>
                          <option value="other">Outro</option>
                        </select>
                      </div>
                      <div className="space-y-2">
                        <label htmlFor="event_date" className="text-sm font-medium text-slate-200">Data do evento</label>
                        <Input id="event_date" type="date" className="h-12 border-white/10 bg-white/5 text-white" {...form.register('event_date')} />
                      </div>
                      <div className="space-y-2">
                        <label htmlFor="event_city" className="text-sm font-medium text-slate-200">Cidade do evento</label>
                        <Input id="event_city" className="h-12 border-white/10 bg-white/5 text-white" {...form.register('event_city')} />
                      </div>
                      <div className="space-y-2 md:col-span-2">
                        <label htmlFor="event_description" className="text-sm font-medium text-slate-200">Descrição</label>
                        <Textarea id="event_description" className="min-h-[120px] border-white/10 bg-white/5 text-white" placeholder="Opcional" {...form.register('event_description')} />
                      </div>
                    </div>
                  </section>

                  <section className="space-y-4">
                    <div className="space-y-2">
                      <p className="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-300/80">Pagamento</p>
                      <h2 className="text-xl font-semibold text-white">Pix ou cartão tokenizado</h2>
                    </div>

                    <div className="grid gap-3 md:grid-cols-2">
                      <button type="button" onClick={() => form.setValue('payment_method', 'pix', { shouldDirty: true })} className={cn('rounded-[28px] border p-4 text-left transition', paymentMethod === 'pix' ? 'border-sky-300 bg-sky-300/10' : 'border-white/10 bg-white/[0.04] hover:border-white/20')}>
                        <div className="flex items-center gap-3">
                          <QrCode className="h-5 w-5 text-sky-300" />
                          <div>
                            <p className="text-base font-semibold text-white">Pix</p>
                            <p className="text-sm text-slate-300">QR Code síncrono e conciliação por webhook.</p>
                          </div>
                        </div>
                      </button>
                      <button type="button" onClick={() => form.setValue('payment_method', 'credit_card', { shouldDirty: true })} className={cn('rounded-[28px] border p-4 text-left transition', paymentMethod === 'credit_card' ? 'border-emerald-300 bg-emerald-300/10' : 'border-white/10 bg-white/[0.04] hover:border-white/20')}>
                        <div className="flex items-center gap-3">
                          <CreditCard className="h-5 w-5 text-emerald-300" />
                          <div>
                            <p className="text-base font-semibold text-white">Cartão de crédito</p>
                            <p className="text-sm text-slate-300">Tokenização direta na Pagar.me e backend recebendo apenas `card_token`.</p>
                          </div>
                        </div>
                      </button>
                    </div>

                    {paymentMethod === 'credit_card' ? (
                      <div className="space-y-5 rounded-[28px] border border-emerald-300/12 bg-[linear-gradient(180deg,rgba(16,185,129,0.08),rgba(15,23,42,0.45))] p-4 md:p-5">
                        <div className="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(300px,0.85fr)]">
                          <div className="space-y-4">
                            <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-4">
                              <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div className="space-y-1">
                                  <div className="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-200/80">
                                    <UserRound className="h-3.5 w-3.5" />
                                    Pagador
                                  </div>
                                  <h3 className="text-lg font-semibold text-white">Identificacao e contato do titular</h3>
                                </div>
                                <Badge variant="outline" className="border-emerald-300/20 bg-emerald-400/10 text-emerald-50">
                                  Obrigatorio para tokenizacao
                                </Badge>
                              </div>
                              <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                  <label htmlFor="payer_document" className="text-sm font-medium text-slate-200">CPF do pagador</label>
                                  <Input
                                    id="payer_document"
                                    inputMode="numeric"
                                    maxLength={14}
                                    className="h-12 border-white/10 bg-white/5 text-white"
                                    {...form.register('payer_document')}
                                    value={payerDocument}
                                    onChange={(event) => updateMaskedField('payer_document', formatCpf(event.target.value))}
                                  />
                                  <FieldError message={form.formState.errors.payer_document?.message} />
                                </div>
                                <div className="space-y-2">
                                  <label htmlFor="payer_phone" className="text-sm font-medium text-slate-200">Telefone do pagador</label>
                                  <Input
                                    id="payer_phone"
                                    inputMode="tel"
                                    maxLength={15}
                                    className="h-12 border-white/10 bg-white/5 text-white"
                                    placeholder="Usamos seu WhatsApp como base"
                                    {...form.register('payer_phone')}
                                    value={payerPhone}
                                    onChange={(event) => updateMaskedField('payer_phone', formatPhone(event.target.value))}
                                  />
                                  <p className="text-xs text-slate-400">Se voce preferir, pode manter o mesmo numero do WhatsApp acima.</p>
                                  <FieldError message={form.formState.errors.payer_phone?.message} />
                                </div>
                              </div>
                            </div>

                            <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-4">
                              <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div className="space-y-1">
                                  <div className="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-sky-200/80">
                                    <MapPinned className="h-3.5 w-3.5" />
                                    Endereco de cobranca
                                  </div>
                                  <h3 className="text-lg font-semibold text-white">Mesmo endereco enviado no pedido da Pagar.me</h3>
                                </div>
                                <Badge variant="outline" className="border-white/15 bg-white/[0.06] text-slate-100">
                                  Billing address obrigatorio
                                </Badge>
                              </div>
                              <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <div className="space-y-2 md:col-span-2">
                                  <label htmlFor="address_street" className="text-sm font-medium text-slate-200">Rua</label>
                                  <Input id="address_street" className="h-12 border-white/10 bg-white/5 text-white" {...form.register('address_street')} />
                                  <FieldError message={form.formState.errors.address_street?.message} />
                                </div>
                                <div className="space-y-2">
                                  <label htmlFor="address_number" className="text-sm font-medium text-slate-200">Numero</label>
                                  <Input id="address_number" className="h-12 border-white/10 bg-white/5 text-white" {...form.register('address_number')} />
                                  <FieldError message={form.formState.errors.address_number?.message} />
                                </div>
                                <div className="space-y-2">
                                  <label htmlFor="address_district" className="text-sm font-medium text-slate-200">Bairro</label>
                                  <Input id="address_district" className="h-12 border-white/10 bg-white/5 text-white" {...form.register('address_district')} />
                                  <FieldError message={form.formState.errors.address_district?.message} />
                                </div>
                                <div className="space-y-2">
                                  <label htmlFor="address_complement" className="text-sm font-medium text-slate-200">Complemento</label>
                                  <Input id="address_complement" className="h-12 border-white/10 bg-white/5 text-white" placeholder="Opcional" {...form.register('address_complement')} />
                                </div>
                                <div className="space-y-2">
                                  <label htmlFor="address_zip_code" className="text-sm font-medium text-slate-200">CEP</label>
                                  <Input
                                    id="address_zip_code"
                                    inputMode="numeric"
                                    maxLength={9}
                                    className="h-12 border-white/10 bg-white/5 text-white"
                                    {...form.register('address_zip_code')}
                                    value={addressZipCode}
                                    onChange={(event) => updateMaskedField('address_zip_code', formatZipCode(event.target.value))}
                                  />
                                  <FieldError message={form.formState.errors.address_zip_code?.message} />
                                </div>
                                <div className="space-y-2">
                                  <label htmlFor="address_city" className="text-sm font-medium text-slate-200">Cidade</label>
                                  <Input id="address_city" className="h-12 border-white/10 bg-white/5 text-white" {...form.register('address_city')} />
                                  <FieldError message={form.formState.errors.address_city?.message} />
                                </div>
                                <div className="space-y-2">
                                  <label htmlFor="address_state" className="text-sm font-medium text-slate-200">Estado</label>
                                  <Input
                                    id="address_state"
                                    className="h-12 border-white/10 bg-white/5 text-white uppercase"
                                    maxLength={2}
                                    {...form.register('address_state')}
                                    value={addressState}
                                    onChange={(event) => updateMaskedField('address_state', event.target.value.replace(/[^a-z]/gi, '').slice(0, 2).toUpperCase())}
                                  />
                                  <FieldError message={form.formState.errors.address_state?.message} />
                                </div>
                              </div>
                            </div>

                            <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-4">
                              <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div className="space-y-1">
                                  <div className="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-amber-200/80">
                                    <WalletCards className="h-3.5 w-3.5" />
                                    Cartao
                                  </div>
                                  <h3 className="text-lg font-semibold text-white">Dados protegidos e tokenizados fora do backend</h3>
                                </div>
                                <Badge variant="outline" className={cn('border text-white', cardBrand ? cardBrand.badgeClassName : 'border-white/15 bg-white/10')}>
                                  {cardBrand ? `Bandeira ${cardBrand.label}` : 'Bandeira automatica'}
                                </Badge>
                              </div>
                              <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <div className="space-y-2 md:col-span-2">
                                  <label htmlFor="card_number" className="text-sm font-medium text-slate-200">Numero do cartao</label>
                                  <Input
                                    id="card_number"
                                    inputMode="numeric"
                                    autoComplete="cc-number"
                                    maxLength={23}
                                    className="h-12 border-white/10 bg-white/5 text-white"
                                    {...form.register('card_number')}
                                    value={cardNumber}
                                    onChange={(event) => updateMaskedField('card_number', formatCardNumber(event.target.value))}
                                  />
                                  <FieldError message={form.formState.errors.card_number?.message} />
                                </div>
                                <div className="space-y-2 md:col-span-2">
                                  <label htmlFor="card_holder_name" className="text-sm font-medium text-slate-200">Nome impresso no cartao</label>
                                  <Input
                                    id="card_holder_name"
                                    autoComplete="cc-name"
                                    className="h-12 border-white/10 bg-white/5 text-white uppercase"
                                    {...form.register('card_holder_name')}
                                    value={cardHolderName}
                                    onChange={(event) => updateMaskedField('card_holder_name', normalizeCardHolderName(event.target.value))}
                                  />
                                  <FieldError message={form.formState.errors.card_holder_name?.message} />
                                </div>
                                <div className="space-y-2">
                                  <label htmlFor="card_exp_month" className="text-sm font-medium text-slate-200">Mes</label>
                                  <Input
                                    id="card_exp_month"
                                    inputMode="numeric"
                                    autoComplete="cc-exp-month"
                                    maxLength={2}
                                    placeholder="MM"
                                    className="h-12 border-white/10 bg-white/5 text-white"
                                    {...form.register('card_exp_month')}
                                    value={cardExpMonth}
                                    onChange={(event) => updateMaskedField('card_exp_month', formatCardExpiryPart(event.target.value, 2))}
                                  />
                                  <FieldError message={form.formState.errors.card_exp_month?.message} />
                                </div>
                                <div className="space-y-2">
                                  <label htmlFor="card_exp_year" className="text-sm font-medium text-slate-200">Ano</label>
                                  <Input
                                    id="card_exp_year"
                                    inputMode="numeric"
                                    autoComplete="cc-exp-year"
                                    maxLength={2}
                                    placeholder="AA"
                                    className="h-12 border-white/10 bg-white/5 text-white"
                                    {...form.register('card_exp_year')}
                                    value={cardExpYear}
                                    onChange={(event) => updateMaskedField('card_exp_year', formatCardExpiryPart(event.target.value, 2))}
                                  />
                                  <FieldError message={form.formState.errors.card_exp_year?.message} />
                                </div>
                                <div className="space-y-2">
                                  <label htmlFor="card_cvv" className="text-sm font-medium text-slate-200">CVV</label>
                                  <Input
                                    id="card_cvv"
                                    inputMode="numeric"
                                    autoComplete="cc-csc"
                                    maxLength={4}
                                    className="h-12 border-white/10 bg-white/5 text-white"
                                    {...form.register('card_cvv')}
                                    value={cardCvv}
                                    onChange={(event) => updateMaskedField('card_cvv', digitsOnly(event.target.value).slice(0, 4))}
                                  />
                                  <FieldError message={form.formState.errors.card_cvv?.message} />
                                </div>
                              </div>
                            </div>
                          </div>

                          <div className="space-y-4">
                            <div className="rounded-[28px] border border-emerald-300/12 bg-emerald-400/10 p-4">
                              <div className="flex items-start justify-between gap-3">
                                <div className="space-y-1">
                                  <p className="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-100/80">Checklist do pagamento</p>
                                  <h3 className="text-lg font-semibold text-white">Tudo o que precisa ficar pronto antes da tokenizacao</h3>
                                </div>
                                <Badge variant="outline" className="border-emerald-200/20 bg-white/10 text-emerald-50">
                                  {cardChecklist.completed}/{cardChecklist.total}
                                </Badge>
                              </div>

                              <div className="mt-4 h-2 overflow-hidden rounded-full bg-black/20">
                                <div
                                  className={cn(
                                    'h-full rounded-full transition-all',
                                    cardChecklist.ready ? 'bg-emerald-300' : 'bg-gradient-to-r from-emerald-300 via-sky-300 to-amber-300',
                                  )}
                                  style={{ width: `${cardChecklistProgress}%` }}
                                />
                              </div>

                              <div className="mt-4 space-y-2">
                                {cardChecklist.items.map((item) => (
                                  <div
                                    key={item.key}
                                    className={cn(
                                      'flex items-center justify-between gap-3 rounded-2xl border px-3 py-2 text-sm',
                                      item.done
                                        ? 'border-emerald-200/20 bg-emerald-300/10 text-emerald-50'
                                        : 'border-white/10 bg-black/10 text-slate-200',
                                    )}
                                  >
                                    <div className="flex items-center gap-2">
                                      {item.done ? (
                                        <CheckCircle2 className="h-4 w-4 text-emerald-200" />
                                      ) : (
                                        <CircleEllipsis className="h-4 w-4 text-amber-200" />
                                      )}
                                      <span>{item.label}</span>
                                    </div>
                                    <span className="text-[11px] uppercase tracking-[0.16em] opacity-70">
                                      {item.done ? 'ok' : 'ajustar'}
                                    </span>
                                  </div>
                                ))}
                              </div>

                              <p className="mt-4 text-sm text-emerald-50/85">
                                Quando tudo ficar pronto, a Pagar.me recebe os dados do cartao no navegador e o backend segue apenas com <code className="rounded bg-black/20 px-1.5 py-0.5 text-[12px]">card_token</code>.
                              </p>
                            </div>

                            <div className={cn('relative overflow-hidden rounded-[30px] border border-white/10 bg-gradient-to-br p-5 text-white shadow-2xl shadow-slate-950/30', cardBrand?.surfaceClassName ?? 'from-white/15 via-white/5 to-slate-950')}>
                              <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.18),transparent_35%),radial-gradient(circle_at_bottom_left,rgba(16,185,129,0.18),transparent_40%)]" />
                              <div className="relative space-y-6">
                                <div className="flex items-start justify-between gap-3">
                                  <div className="space-y-2">
                                    <p className="text-xs font-semibold uppercase tracking-[0.22em] text-white/70">Preview seguro</p>
                                    <h3 className="text-lg font-semibold">Cartao tokenizado no navegador</h3>
                                  </div>
                                  <Badge variant="outline" className={cn('border text-white', cardBrand ? cardBrand.badgeClassName : 'border-white/15 bg-white/10')}>
                                    {cardBrand ? `Bandeira ${cardBrand.label}` : 'Bandeira automatica'}
                                  </Badge>
                                </div>

                                <div className="flex items-center justify-between">
                                  <Badge variant="outline" className="border-white/15 bg-white/10 text-white">
                                    1x sem juros
                                  </Badge>
                                  <CreditCard className="h-5 w-5 text-white/80" />
                                </div>

                                <div className="space-y-3">
                                  <p className="text-lg font-semibold tracking-[0.32em] text-white/95">{cardPreviewNumber}</p>
                                  <div className="grid gap-3 sm:grid-cols-2">
                                    <div>
                                      <p className="text-[11px] uppercase tracking-[0.18em] text-white/60">Titular</p>
                                      <p className="mt-1 text-sm font-medium text-white/95">{cardPreviewHolder}</p>
                                    </div>
                                    <div>
                                      <p className="text-[11px] uppercase tracking-[0.18em] text-white/60">Validade</p>
                                      <p className="mt-1 text-sm font-medium text-white/95">{cardPreviewExpiry}</p>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-4">
                              <div className="flex items-start gap-3">
                                <LockKeyhole className="mt-0.5 h-4 w-4 text-emerald-300" />
                                <div className="space-y-1">
                                  <p className="text-sm font-semibold text-white">Tokenizacao segura</p>
                                  <p className="text-sm text-slate-300">
                                    PAN e CVV ficam no navegador e o backend recebe apenas <code className="rounded bg-white/10 px-1.5 py-0.5 text-[12px]">card_token</code>.
                                  </p>
                                </div>
                              </div>
                              <Separator className="my-4 bg-white/10" />
                              <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-2xl border border-white/10 bg-black/10 p-3">
                                  <p className="text-[11px] uppercase tracking-[0.16em] text-slate-400">Backend</p>
                                  <p className="mt-2 text-sm text-white">Somente card_token, endereco e metadados do pedido.</p>
                                </div>
                                <div className="rounded-2xl border border-white/10 bg-black/10 p-3">
                                  <p className="text-[11px] uppercase tracking-[0.16em] text-slate-400">PSP v5</p>
                                  <p className="mt-2 text-sm text-white">Customer completo, billing address e conciliacao por webhook/local status.</p>
                                </div>
                              </div>
                            </div>

                            {cardRuntime ? (
                              <div
                                className={cn(
                                  'rounded-[28px] border p-4',
                                  cardRuntime.tone === 'success' && 'border-emerald-300/20 bg-emerald-400/10 text-emerald-50',
                                  cardRuntime.tone === 'error' && 'border-rose-300/20 bg-rose-500/10 text-rose-50',
                                  cardRuntime.tone === 'warning' && 'border-amber-300/20 bg-amber-400/10 text-amber-50',
                                )}
                              >
                                <div className="flex items-start gap-3">
                                  {cardRuntime.tone === 'success' ? <BadgeCheck className="mt-0.5 h-4 w-4" /> : null}
                                  {cardRuntime.tone === 'error' ? <AlertTriangle className="mt-0.5 h-4 w-4" /> : null}
                                  {cardRuntime.tone === 'warning' ? <CircleEllipsis className="mt-0.5 h-4 w-4" /> : null}
                                  <div className="space-y-1">
                                    <p className="text-xs font-semibold uppercase tracking-[0.16em] opacity-80">Status do cartao</p>
                                    <p className="text-base font-semibold text-white">{cardRuntime.title}</p>
                                    <p className="text-sm opacity-90">{cardRuntime.description}</p>
                                  </div>
                                </div>
                                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                  <div className="rounded-2xl border border-current/15 bg-black/10 p-3">
                                    <p className="text-[11px] uppercase tracking-[0.16em] opacity-70">Gateway status</p>
                                    <p className="mt-2 text-sm font-medium text-white">{checkoutResponse?.checkout.payment.gateway_status || 'aguardando'}</p>
                                  </div>
                                  <div className="rounded-2xl border border-current/15 bg-black/10 p-3">
                                    <p className="text-[11px] uppercase tracking-[0.16em] opacity-70">Ultima transicao</p>
                                    <p className="mt-2 text-sm font-medium text-white">{checkoutResponse?.checkout.payment.credit_card?.last_status || 'pendente'}</p>
                                  </div>
                                </div>
                              </div>
                            ) : null}
                          </div>
                        </div>
                      </div>
                    ) : (
                      <div className="rounded-[28px] border border-sky-300/15 bg-sky-400/10 px-4 py-4 text-sm text-sky-50">
                        O Pix devolve QR Code imediatamente e a confirmação final continua vindo por webhook, com a página consultando apenas o status local.
                      </div>
                    )}
                  </section>

                  {identityConflict ? (
                    <div className="space-y-4 rounded-[28px] border border-amber-300/20 bg-amber-400/10 px-4 py-4 text-sm text-amber-50">
                      <div className="space-y-2">
                        <p className="text-xs font-semibold uppercase tracking-[0.16em] text-amber-100/75">Conta existente</p>
                        <p className="text-base font-semibold text-white">Este responsavel ja possui cadastro.</p>
                        <p>{identityConflict.message}</p>
                      </div>
                      <div className="flex flex-col gap-3 md:flex-row">
                        <Button asChild className="h-11 rounded-2xl bg-white text-slate-950 hover:bg-slate-100">
                          <Link to={identityConflict.loginPath}>Fazer login para continuar</Link>
                        </Button>
                        <Button
                          type="button"
                          variant="outline"
                          className="h-11 rounded-2xl border-white/20 bg-transparent text-white hover:bg-white/10"
                          onClick={() => {
                            clearResumeDraft();
                            setResumeDraft(null);
                            setResumeNotice(null);
                            setResumeInitialized(false);
                            setResumeAutoSubmitted(false);
                            setIdentityConflict(null);
                            setSubmitError(null);
                          }}
                        >
                          Usar outro contato
                        </Button>
                      </div>
                    </div>
                  ) : null}

                  {submitError ? <div className="rounded-[28px] border border-rose-400/20 bg-rose-500/10 px-4 py-4 text-sm text-rose-100">{submitError}</div> : null}

                  <div className="flex flex-col gap-3 border-t border-white/10 pt-5 md:flex-row">
                    <Button type="submit" size="lg" className="h-12 rounded-2xl bg-emerald-400 text-slate-950 hover:bg-emerald-300" disabled={createCheckoutMutation.isPending || packagesQuery.isLoading}>
                      {createCheckoutMutation.isPending ? (
                        <>
                          <Loader2 className="h-4 w-4 animate-spin" />
                          Criando pedido...
                        </>
                      ) : paymentMethod === 'credit_card' ? (
                        <>
                          <ShieldCheck className="h-4 w-4" />
                          Pagar com cartão
                        </>
                      ) : (
                        <>
                          <QrCode className="h-4 w-4" />
                          Gerar Pix
                        </>
                      )}
                    </Button>
                    {checkoutUuid ? (
                      <Button type="button" variant="outline" size="lg" className="h-12 rounded-2xl border-white/15 bg-transparent text-white hover:bg-white/10" onClick={restoreAnotherCheckout}>
                        Iniciar outro checkout
                      </Button>
                    ) : null}
                  </div>

                  <p className="text-sm text-slate-400">
                    {paymentMethod === 'credit_card'
                      ? cardChecklist.ready
                        ? 'Os dados do cartao estao consistentes. Na proxima etapa a tokenizacao acontece no navegador e o pedido segue para conciliacao local.'
                        : `Antes de pagar com cartao, faltam ${cardChecklist.total - cardChecklist.completed} ajustes no checklist acima.`
                      : 'No Pix, o QR Code e exibido na resposta sincronamente e a confirmacao final continua chegando por webhook.'}
                  </p>
                </form>
              </CardContent>
            </Card>
          </div>

          <aside className="space-y-6">
            <Card id="checkout-status" className={cn('border shadow-xl backdrop-blur', summaryToneClass(status.tone))}>
              <CardContent className="space-y-4 p-5 md:p-6">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.22em] opacity-70">Status do checkout</p>
                    <h2 className="mt-2 text-2xl font-semibold">{status.title}</h2>
                  </div>
                  {status.tone === 'success' ? <CheckCircle2 className="h-6 w-6" /> : null}
                  {status.tone === 'error' ? <AlertTriangle className="h-6 w-6" /> : null}
                  {status.tone === 'warning' ? <RefreshCcw className={cn('h-6 w-6', isPolling ? 'animate-spin' : '')} /> : null}
                  {status.tone === 'info' ? <QrCode className="h-6 w-6" /> : null}
                </div>
                <p className="text-sm opacity-85">{status.description}</p>
                {checkoutResponse?.checkout ? (
                  <div className="grid gap-3 text-sm sm:grid-cols-2">
                    <div className="rounded-3xl border border-black/5 bg-black/5 p-3">
                      <p className="text-[11px] uppercase tracking-[0.16em] opacity-65">UUID local</p>
                      <p className="mt-1 break-all font-medium">{checkoutResponse.checkout.uuid}</p>
                    </div>
                    <div className="rounded-3xl border border-black/5 bg-black/5 p-3">
                      <p className="text-[11px] uppercase tracking-[0.16em] opacity-65">Gateway status</p>
                      <p className="mt-1 font-medium">{checkoutResponse.checkout.payment.gateway_status || 'aguardando'}</p>
                    </div>
                  </div>
                ) : null}
                {checkoutUuid ? (
                  <Button type="button" variant="outline" className="w-full rounded-2xl border-current/20 bg-transparent" onClick={() => checkoutQuery.refetch()} disabled={checkoutQuery.isFetching}>
                    {checkoutQuery.isFetching ? (
                      <>
                        <Loader2 className="h-4 w-4 animate-spin" />
                        Atualizando status local...
                      </>
                    ) : (
                      <>
                        <RefreshCcw className="h-4 w-4" />
                        Atualizar status local
                      </>
                    )}
                  </Button>
                ) : null}
              </CardContent>
            </Card>

            {checkoutResponse?.checkout.payment.method === 'pix' && checkoutResponse.checkout.payment.pix ? (
              <Card className="border-white/10 bg-white/[0.05] text-white shadow-xl shadow-slate-950/30 backdrop-blur">
                <CardContent className="space-y-4 p-5 md:p-6">
                  <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <p className="text-xs font-semibold uppercase tracking-[0.22em] text-sky-300/85">Pix</p>
                    <h3 className="text-xl font-semibold">QR Code e codigo copia e cola</h3>
                  </div>
                  {pixExpiresLabel ? (
                    <div className="rounded-full border border-sky-300/15 bg-sky-400/10 px-3 py-2 text-sm font-medium text-sky-50">
                      Expira em {pixExpiresLabel}
                    </div>
                  ) : null}
                  {pixWhatsappNotification && (
                    <div className="rounded-[28px] border border-emerald-300/15 bg-emerald-400/10 p-4 text-sm text-emerald-50">
                      <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-200/80">WhatsApp</p>
                      <p className="mt-2 font-medium">
                        {pixWhatsappNotification.status === 'queued' || pixWhatsappNotification.status === 'pending'
                          ? 'Tambem enviamos este Pix para o seu WhatsApp.'
                          : 'Tentamos enviar este Pix para o seu WhatsApp.'}
                      </p>
                      <p className="mt-1 text-emerald-100/85">
                        {pixWhatsappNotification.recipient_phone
                          ? `Numero usado: ${pixWhatsappNotification.recipient_phone}.`
                          : 'O envio usa o mesmo numero informado no checkout.'}{' '}
                        {pixWhatsappNotification.pix_button_message_id
                          ? 'O envio incluiu o botao de copiar o Pix.'
                          : 'O envio principal foi por texto.'}
                      </p>
                    </div>
                  )}
                  <div className="rounded-[28px] border border-white/10 bg-white p-4 text-slate-950">
                    <div className="flex justify-center">
                      <QRCodeSVG value={checkoutResponse.checkout.payment.pix.qr_code || ''} size={192} />
                    </div>
                  </div>
                  <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-4">
                    <p className="text-[11px] uppercase tracking-[0.16em] text-slate-400">Copia e cola</p>
                    <p className="mt-2 break-all text-sm text-slate-100">{checkoutResponse.checkout.payment.pix.qr_code}</p>
                  </div>
                  <div className="flex flex-col gap-3 md:flex-row">
                    <Button
                      type="button"
                      className="h-11 rounded-2xl bg-sky-300 text-slate-950 hover:bg-sky-200"
                      onClick={() => void handleCopyPixCode()}
                    >
                      {copiedPix ? 'Codigo Pix copiado' : 'Copiar codigo Pix'}
                    </Button>
                    {checkoutResponse.checkout.payment.pix.qr_code_url ? (
                      <Button asChild type="button" variant="outline" className="h-11 rounded-2xl border-white/20 bg-transparent text-white hover:bg-white/10">
                        <a href={checkoutResponse.checkout.payment.pix.qr_code_url} target="_blank" rel="noreferrer">
                          Abrir QR em nova aba
                        </a>
                      </Button>
                    ) : null}
                  </div>
                  {pixTimeline.length > 0 ? (
                    <div className="rounded-[28px] border border-white/10 bg-white/[0.03] p-4">
                      <p className="text-[11px] uppercase tracking-[0.16em] text-slate-400">Timeline local</p>
                      <div className="mt-4 space-y-3">
                        {pixTimeline.map((step) => (
                          <div key={step.key} className="flex gap-3">
                            <div
                              className={cn(
                                'mt-0.5 h-2.5 w-2.5 rounded-full',
                                step.state === 'done' ? 'bg-emerald-300' : step.state === 'active' ? 'bg-sky-300' : 'bg-white/20',
                              )}
                            />
                            <div className="space-y-1">
                              <p className="text-sm font-medium text-white">{step.label}</p>
                              <p className="text-xs text-slate-300">{step.description}</p>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  ) : null}
                </CardContent>
              </Card>
            ) : null}

            <Card className="border-white/10 bg-white/[0.05] text-white shadow-xl shadow-slate-950/30 backdrop-blur">
              <CardContent className="space-y-4 p-5 md:p-6">
                <div className="space-y-1">
                  <p className="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-300/80">Resumo</p>
                  <h3 className="text-xl font-semibold">Pacote selecionado</h3>
                </div>
                {selectedPackage ? (
                  <div className="space-y-4">
                    <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-4">
                      <p className="text-lg font-semibold">{selectedPackage.name}</p>
                      <p className="mt-2 text-sm text-slate-300">{selectedPackage.description}</p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                      <div className="rounded-3xl border border-white/10 bg-white/[0.04] p-3">
                        <p className="text-[11px] uppercase tracking-[0.16em] text-slate-400">Preço</p>
                        <p className="mt-1 text-sm font-semibold text-white">{selectedPackage.default_price ? formatCurrency(selectedPackage.default_price.amount_cents, selectedPackage.default_price.currency) : 'Sob consulta'}</p>
                      </div>
                      <div className="rounded-3xl border border-white/10 bg-white/[0.04] p-3">
                        <p className="text-[11px] uppercase tracking-[0.16em] text-slate-400">Retenção</p>
                        <p className="mt-1 text-sm font-semibold text-white">{selectedPackage.limits.retention_days ?? '—'} dias</p>
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="rounded-[28px] border border-dashed border-white/15 bg-white/[0.03] px-4 py-6 text-sm text-slate-300">
                    O catálogo será carregado acima. Escolha um pacote para ver o resumo operacional.
                  </div>
                )}
              </CardContent>
            </Card>
          </aside>
        </div>
      </div>
    </div>
  );
}
