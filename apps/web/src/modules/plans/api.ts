import { api } from '@/lib/api';
import type {
  ApiBillingCancelSubscriptionResponse,
  ApiBillingCheckoutResponse,
  ApiBillingInvoice,
  ApiBillingSubscription,
  ApiPaginationMeta,
  ApiPlan,
} from '@/lib/api-types';

export interface BillingInvoicesResponse {
  success: boolean;
  data: ApiBillingInvoice[];
  meta: ApiPaginationMeta;
}

export interface BillingCheckoutPayload {
  plan_id: number;
  billing_cycle?: 'monthly' | 'yearly';
}

export interface CancelSubscriptionPayload {
  effective?: 'period_end' | 'immediately';
  reason?: string;
}

export const plansService = {
  listCatalog() {
    return api.get<ApiPlan[]>('/plans');
  },

  getCurrentSubscription() {
    return api.get<ApiBillingSubscription | null>('/billing/subscription');
  },

  listInvoices(page = 1) {
    return api.getRaw<BillingInvoicesResponse>('/billing/invoices', {
      params: {
        page,
      },
    });
  },

  checkout(payload: BillingCheckoutPayload) {
    return api.post<ApiBillingCheckoutResponse>('/billing/checkout', {
      body: payload,
    });
  },

  cancelSubscription(payload: CancelSubscriptionPayload = {}) {
    return api.post<ApiBillingCancelSubscriptionResponse>('/billing/subscription/cancel', {
      body: payload,
    });
  },
};
