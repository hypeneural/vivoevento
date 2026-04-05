const PAGARME_TOKENS_URL = 'https://api.pagar.me/core/v5/tokens';

export interface PagarmeCardTokenizationInput {
  number: string;
  holderName: string;
  expMonth: number | string;
  expYear: number | string;
  cvv: string;
}

export interface PagarmeCardToken {
  id: string;
  type: string;
  created_at?: string | null;
  expires_at?: string | null;
  card?: {
    first_six_digits?: string | null;
    last_four_digits?: string | null;
    brand?: string | null;
    holder_name?: string | null;
    exp_month?: number | null;
    exp_year?: number | null;
  } | null;
}

export class PagarmeTokenizationError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly body: unknown,
  ) {
    super(message);
    this.name = 'PagarmeTokenizationError';
  }
}

interface CreatePagarmeCardTokenOptions {
  publicKey?: string | null;
}

export async function createPagarmeCardToken(
  input: PagarmeCardTokenizationInput,
  options: CreatePagarmeCardTokenOptions = {},
): Promise<PagarmeCardToken> {
  const publicKey = options.publicKey ?? import.meta.env.VITE_PAGARME_PUBLIC_KEY;

  if (!publicKey) {
    throw new Error('VITE_PAGARME_PUBLIC_KEY is required to tokenize cards with Pagar.me.');
  }

  const url = new URL(PAGARME_TOKENS_URL);
  url.searchParams.set('appId', publicKey);

  const response = await fetch(url.toString(), {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      type: 'card',
      card: {
        number: input.number,
        holder_name: input.holderName,
        exp_month: Number(input.expMonth),
        exp_year: Number(input.expYear),
        cvv: input.cvv,
      },
    }),
  });

  const contentType = response.headers.get('content-type') || '';
  const isJson = contentType.includes('application/json');
  const body = isJson ? await response.json() : await response.text();

  if (!response.ok) {
    const message = typeof body === 'object' && body && 'message' in body
      ? String(body.message)
      : 'Pagar.me card tokenization failed.';

    throw new PagarmeTokenizationError(message, response.status, body);
  }

  return body as PagarmeCardToken;
}
