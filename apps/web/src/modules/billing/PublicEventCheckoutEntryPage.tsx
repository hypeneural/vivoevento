import { useSearchParams } from 'react-router-dom';

import PublicEventCheckoutPage from './PublicEventCheckoutPage';
import { PublicCheckoutPageV2 } from './public-checkout/PublicCheckoutPageV2';

export default function PublicEventCheckoutEntryPage() {
  const [searchParams] = useSearchParams();
  const useV2 = searchParams.get('v2') === '1';

  if (useV2) {
    return <PublicCheckoutPageV2 />;
  }

  return <PublicEventCheckoutPage />;
}
