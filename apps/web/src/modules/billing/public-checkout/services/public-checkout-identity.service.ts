import api from '@/lib/api';
import type {
  PublicCheckoutIdentityCheckPayload,
  PublicCheckoutIdentityCheckResponse,
} from '@/lib/api-types';

export const publicCheckoutIdentityService = {
  check(payload: PublicCheckoutIdentityCheckPayload, signal?: AbortSignal) {
    return api.post<PublicCheckoutIdentityCheckResponse>('/public/checkout-identity/check', {
      body: payload,
      signal,
    });
  },
};
