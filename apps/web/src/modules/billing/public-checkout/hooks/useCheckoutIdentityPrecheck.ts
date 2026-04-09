import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';

import type { PublicCheckoutIdentityCheckResponse } from '@/lib/api-types';

import { publicCheckoutIdentityService } from '../services/public-checkout-identity.service';

type UseCheckoutIdentityPrecheckOptions = {
  whatsapp: string;
  email?: string | null;
  debounceMs?: number;
};

type DebouncedIdentity = {
  whatsapp: string;
  email: string | null;
};

function normalizeWhatsapp(value: string) {
  return value.replace(/\D+/g, '');
}

function normalizeEmail(value?: string | null) {
  const normalized = (value ?? '').trim().toLowerCase();

  return normalized === '' ? null : normalized;
}

export function useCheckoutIdentityPrecheck({
  whatsapp,
  email,
  debounceMs = 700,
}: UseCheckoutIdentityPrecheckOptions) {
  const normalizedWhatsapp = normalizeWhatsapp(whatsapp);
  const normalizedEmail = normalizeEmail(email);
  const isReady = normalizedWhatsapp.length >= 10;
  const [debouncedIdentity, setDebouncedIdentity] = useState<DebouncedIdentity>({
    whatsapp: '',
    email: null,
  });

  useEffect(() => {
    if (!isReady) {
      setDebouncedIdentity((current) => {
        if (current.whatsapp === '' && current.email === null) {
          return current;
        }

        return {
          whatsapp: '',
          email: null,
        };
      });

      return undefined;
    }

    const timeoutId = window.setTimeout(() => {
      setDebouncedIdentity({
        whatsapp: normalizedWhatsapp,
        email: normalizedEmail,
      });
    }, debounceMs);

    return () => window.clearTimeout(timeoutId);
  }, [debounceMs, isReady, normalizedEmail, normalizedWhatsapp]);

  const query = useQuery({
    queryKey: ['public-checkout-identity', debouncedIdentity.whatsapp, debouncedIdentity.email],
    enabled: debouncedIdentity.whatsapp.length >= 10,
    placeholderData: (previous) => previous,
    retry: false,
    staleTime: 15_000,
    queryFn: ({ signal }) => publicCheckoutIdentityService.check({
      whatsapp: debouncedIdentity.whatsapp,
      email: debouncedIdentity.email,
    }, signal),
  });

  const identityAssist: PublicCheckoutIdentityCheckResponse | null = isReady
    ? (query.data ?? null)
    : null;

  return {
    ...query,
    isReady,
    isChecking: debouncedIdentity.whatsapp.length >= 10 && (query.isPending || query.isFetching),
    identityAssist,
  };
}
