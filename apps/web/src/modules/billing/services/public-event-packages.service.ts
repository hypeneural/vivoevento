import api from '@/lib/api';
import type { ApiEventPackage } from '@/lib/api-types';

export const publicEventPackagesService = {
  list() {
    return api.get<ApiEventPackage[]>('/public/event-packages', {
      params: {
        target_audience: 'direct_customer',
      },
    });
  },
};
