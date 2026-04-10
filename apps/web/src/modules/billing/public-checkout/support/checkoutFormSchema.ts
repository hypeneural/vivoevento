import { z } from 'zod';

import {
  digitsOnly,
  hasTwoWords,
  isValidCardExpiry,
  isValidCardNumber,
  isValidCpf,
  normalizeStateCode,
} from './checkoutFormUtils';

export const checkoutV2Schema = z.object({
  package_id: z.string().trim().min(1, 'Escolha um pacote.'),
  responsible_name: z.string().trim().min(3, 'Informe seu nome.'),
  whatsapp: z.string().trim().min(8, 'Informe um WhatsApp valido.'),
  email: z.string().trim().email('Informe um e-mail valido.').or(z.literal('')),
  event_title: z.string().trim().min(3, 'Informe o nome do evento.'),
  event_type: z.enum(['wedding', 'birthday', 'fifteen', 'corporate', 'fair', 'graduation', 'other']),
  organization_name: z.string().trim().optional().default(''),
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

  if (values.payment_method !== 'credit_card') {
    return;
  }

  if (!values.email) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Informe o e-mail principal para checkout com cartao.',
      path: ['email'],
    });
  }

  const requiredMessages = {
    payer_document: 'Informe o CPF do pagador.',
    payer_phone: 'Informe o telefone do pagador.',
    address_street: 'Informe a rua de cobranca.',
    address_number: 'Informe o numero do endereco.',
    address_district: 'Informe o bairro.',
    address_zip_code: 'Informe o CEP.',
    address_city: 'Informe a cidade de cobranca.',
    address_state: 'Informe a UF do endereco.',
    card_number: 'Informe o numero do cartao.',
    card_holder_name: 'Informe o nome do titular.',
    card_exp_month: 'Informe o mes da validade.',
    card_exp_year: 'Informe o ano da validade.',
    card_cvv: 'Informe o CVV.',
  } satisfies Record<string, string>;

  for (const [field, message] of Object.entries(requiredMessages)) {
    const fieldValue = values[field as keyof CheckoutV2FormValues];

    if (typeof fieldValue === 'string' && !fieldValue.trim()) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message,
        path: [field],
      });
    }
  }

  if (values.payer_document && !isValidCpf(values.payer_document)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Informe um CPF valido para o pagador.',
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
      message: 'O CEP deve ter 8 digitos.',
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
      message: 'Informe um numero de cartao valido.',
      path: ['card_number'],
    });
  }

  if ((values.card_exp_month || values.card_exp_year) && !isValidCardExpiry(values.card_exp_month, values.card_exp_year)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Informe uma validade vigente para o cartao.',
      path: ['card_exp_year'],
    });
  }

  if (values.card_cvv && ![3, 4].includes(digitsOnly(values.card_cvv).length)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'O CVV deve ter 3 ou 4 digitos.',
      path: ['card_cvv'],
    });
  }
});

export type CheckoutV2FormValues = z.infer<typeof checkoutV2Schema>;

export type CheckoutResumeDraft = Omit<
  CheckoutV2FormValues,
  'card_number' | 'card_holder_name' | 'card_exp_month' | 'card_exp_year' | 'card_cvv'
> & {
  version: number;
  source: 'identity_conflict' | 'manual_login';
  saved_at: string;
  expires_at: string | null;
};

export const initialCheckoutV2Values: CheckoutV2FormValues = {
  package_id: '',
  responsible_name: '',
  whatsapp: '',
  email: '',
  event_title: '',
  event_type: 'wedding',
  organization_name: '',
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
