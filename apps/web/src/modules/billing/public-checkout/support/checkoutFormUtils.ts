import type { PublicEventCheckoutPayload } from '@/lib/api-types';

import type { CheckoutResumeDraft, CheckoutV2FormValues } from './checkoutFormSchema';

export const PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY = 'eventovivo.public-event-checkout.v2.resume-draft';
export const PUBLIC_CHECKOUT_V2_POLLING_INTERVAL_MS = 5000;
export const PUBLIC_CHECKOUT_V2_AUTH_RESUME_VALUE = 'auth';
export const PUBLIC_CHECKOUT_V2_RESUME_DRAFT_VERSION = 1;
export const PUBLIC_CHECKOUT_V2_RESUME_DRAFT_TTL_MS = 1000 * 60 * 60 * 24;

export function digitsOnly(value: string | null | undefined) {
  return (value ?? '').replace(/\D+/g, '');
}

export function normalizeStateCode(value: string) {
  return value.replace(/[^a-zA-Z]/g, '').slice(0, 2).toUpperCase();
}

export function hasTwoWords(value: string) {
  return value
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .length >= 2;
}

export function isValidCpf(value: string) {
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

export function isValidCardNumber(value: string) {
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

export function isValidCardExpiry(month: string, year: string) {
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

export function formatPhone(value: string) {
  const digits = digitsOnly(value).slice(0, 11);

  if (digits.length <= 2) return digits;
  if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
  if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;

  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

export function formatCpf(value: string) {
  const digits = digitsOnly(value).slice(0, 11);

  return digits
    .replace(/^(\d{3})(\d)/, '$1.$2')
    .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
}

export function formatZipCode(value: string) {
  const digits = digitsOnly(value).slice(0, 8);

  if (digits.length <= 5) return digits;

  return `${digits.slice(0, 5)}-${digits.slice(5)}`;
}

export function formatCardNumber(value: string) {
  return digitsOnly(value)
    .slice(0, 19)
    .replace(/(\d{4})(?=\d)/g, '$1 ')
    .trim();
}

export function formatCardExpiryPart(value: string, length: number) {
  return digitsOnly(value).slice(0, length);
}

export function normalizeCardHolderName(value: string) {
  return value.replace(/\s+/g, ' ').trimStart().toUpperCase();
}

export function formatCurrency(amountCents: number, currency = 'BRL') {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency,
  }).format(amountCents / 100);
}

export function formatRemainingTime(expiresAt: string | null | undefined, nowMs: number) {
  if (!expiresAt) return null;

  const remainingSeconds = Math.max(0, Math.ceil((new Date(expiresAt).getTime() - nowMs) / 1000));
  const minutes = Math.floor(remainingSeconds / 60);
  const seconds = remainingSeconds % 60;

  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

export function buildCheckoutPayload(values: CheckoutV2FormValues, cardToken?: string): PublicEventCheckoutPayload {
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
      state: normalizeStateCode(values.address_state),
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
    payment: {
      method: 'pix',
    },
    event,
  };
}

export function buildV2LoginResumePath() {
  return `/login?returnTo=${encodeURIComponent(`/checkout/evento?v2=1&resume=${PUBLIC_CHECKOUT_V2_AUTH_RESUME_VALUE}`)}`;
}

export function buildResumeDraft(values: CheckoutV2FormValues): CheckoutResumeDraft {
  return {
    version: PUBLIC_CHECKOUT_V2_RESUME_DRAFT_VERSION,
    source: 'identity_conflict',
    saved_at: new Date().toISOString(),
    expires_at: new Date(Date.now() + PUBLIC_CHECKOUT_V2_RESUME_DRAFT_TTL_MS).toISOString(),
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
  };
}

export function restoreFormValuesFromResumeDraft(
  draft: CheckoutResumeDraft,
  initialValues: CheckoutV2FormValues,
): CheckoutV2FormValues {
  return {
    ...initialValues,
    ...draft,
  };
}
