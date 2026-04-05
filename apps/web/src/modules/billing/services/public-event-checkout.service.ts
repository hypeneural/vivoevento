import api from '@/lib/api';
import type {
  PublicEventCheckoutPayload,
  PublicEventCheckoutResponse,
} from '@/lib/api-types';

export const publicEventCheckoutService = {
  create(payload: PublicEventCheckoutPayload) {
    return api.post<PublicEventCheckoutResponse>('/public/event-checkouts', {
      body: payload,
    });
  },

  get(uuid: string) {
    return api.get<PublicEventCheckoutResponse>(`/public/event-checkouts/${uuid}`);
  },
};
