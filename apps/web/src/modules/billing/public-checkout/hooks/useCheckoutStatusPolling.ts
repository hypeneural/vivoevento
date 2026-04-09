import { useQuery } from '@tanstack/react-query';

import { publicEventCheckoutService } from '../../services/public-event-checkout.service';
import { shouldPollPublicCheckout } from '../mappers/checkoutStatusViewModel';

type UseCheckoutStatusPollingOptions = {
  checkoutUuid?: string | null;
  pollingIntervalMs?: number;
};

export function useCheckoutStatusPolling({
  checkoutUuid,
  pollingIntervalMs = 5000,
}: UseCheckoutStatusPollingOptions) {
  return useQuery({
    queryKey: ['public-event-checkout-v2', checkoutUuid],
    enabled: !!checkoutUuid,
    queryFn: () => publicEventCheckoutService.get(String(checkoutUuid)),
    staleTime: pollingIntervalMs,
    refetchInterval: (query) => shouldPollPublicCheckout(query.state.data) ? pollingIntervalMs : false,
    retry: false,
  });
}
