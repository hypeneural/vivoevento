import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { useCheckoutResumeDraft } from './useCheckoutResumeDraft';
import { initialCheckoutV2Values } from '../support/checkoutFormSchema';
import { PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY } from '../support/checkoutFormUtils';

describe('useCheckoutResumeDraft', () => {
  beforeEach(() => {
    window.localStorage.clear();
    window.sessionStorage.clear();
  });

  it('stores the safe draft in sessionStorage first and clears stale localStorage data', () => {
    window.localStorage.setItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY, JSON.stringify({
      version: 1,
      source: 'identity_conflict',
      saved_at: '2026-04-09T10:00:00Z',
      expires_at: '2099-04-09T10:00:00Z',
      responsible_name: 'Rascunho antigo',
    }));

    const { result } = renderHook(() => useCheckoutResumeDraft(initialCheckoutV2Values));

    act(() => {
      result.current.writeDraft({
        ...initialCheckoutV2Values,
        responsible_name: 'Camila Rocha',
        whatsapp: '(48) 99977-1111',
        package_id: '1',
        payment_method: 'credit_card',
        card_number: '4111 1111 1111 1111',
        card_holder_name: 'CAMILA ROCHA',
        card_exp_month: '12',
        card_exp_year: '29',
        card_cvv: '123',
      });
    });

    const draft = JSON.parse(
      window.sessionStorage.getItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY) ?? '{}',
    ) as Record<string, unknown>;

    expect(draft).toMatchObject({
      responsible_name: 'Camila Rocha',
      whatsapp: '(48) 99977-1111',
      payment_method: 'credit_card',
      source: 'identity_conflict',
      version: 1,
    });
    expect(draft.card_number).toBeUndefined();
    expect(draft.card_cvv).toBeUndefined();
    expect(window.localStorage.getItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY)).toBeNull();
  });

  it('stores only the safe fields from the checkout journey', () => {
    const { result } = renderHook(() => useCheckoutResumeDraft(initialCheckoutV2Values));

    act(() => {
      result.current.writeDraft({
        ...initialCheckoutV2Values,
        responsible_name: 'Camila Rocha',
        whatsapp: '(48) 99977-1111',
        package_id: '1',
        payment_method: 'credit_card',
        card_number: '4111 1111 1111 1111',
        card_holder_name: 'CAMILA ROCHA',
        card_exp_month: '12',
        card_exp_year: '29',
        card_cvv: '123',
      });
    });

    const draft = JSON.parse(
      window.sessionStorage.getItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY) ?? '{}',
    ) as Record<string, unknown>;

    expect(draft).toMatchObject({
      responsible_name: 'Camila Rocha',
      whatsapp: '(48) 99977-1111',
      payment_method: 'credit_card',
      source: 'identity_conflict',
      version: 1,
    });
    expect(draft.card_number).toBeUndefined();
    expect(draft.card_cvv).toBeUndefined();
  });

  it('ignores expired drafts on load', () => {
    window.sessionStorage.setItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY, JSON.stringify({
      version: 1,
      source: 'identity_conflict',
      saved_at: '2026-04-09T10:00:00Z',
      expires_at: '2000-04-09T10:00:00Z',
      responsible_name: 'Camila Rocha',
    }));

    const { result } = renderHook(() => useCheckoutResumeDraft(initialCheckoutV2Values));

    expect(result.current.resumeDraft).toBeNull();
    expect(window.sessionStorage.getItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY)).toBeNull();
  });

  it('falls back to a legacy localStorage draft when sessionStorage is empty', () => {
    window.localStorage.setItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY, JSON.stringify({
      version: 1,
      source: 'identity_conflict',
      saved_at: '2026-04-09T10:00:00Z',
      expires_at: '2099-04-09T10:00:00Z',
      responsible_name: 'Camila Rocha',
      whatsapp: '(48) 99977-1111',
      package_id: '1',
      payment_method: 'pix',
    }));

    const { result } = renderHook(() => useCheckoutResumeDraft(initialCheckoutV2Values));

    expect(result.current.resumeDraft).toMatchObject({
      responsible_name: 'Camila Rocha',
      payment_method: 'pix',
    });
  });
});
