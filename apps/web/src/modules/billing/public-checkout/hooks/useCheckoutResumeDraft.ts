import { useCallback, useState } from 'react';

import type { CheckoutResumeDraft, CheckoutV2FormValues } from '../support/checkoutFormSchema';
import {
  buildResumeDraft,
  PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY,
  restoreFormValuesFromResumeDraft,
} from '../support/checkoutFormUtils';

function getStorageCandidates() {
  return [
    window.sessionStorage,
    window.localStorage,
  ].filter((storage): storage is Storage => {
    try {
      const probeKey = `${PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY}.probe`;
      storage.setItem(probeKey, '1');
      storage.removeItem(probeKey);
      return true;
    } catch {
      return false;
    }
  });
}

function parseStoredResumeDraft(storage: Storage): CheckoutResumeDraft | null {
  const stored = storage.getItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY);

  if (!stored) {
    return null;
  }

  try {
    const parsed = JSON.parse(stored) as CheckoutResumeDraft;

    if (!parsed || typeof parsed !== 'object') {
      storage.removeItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY);
      return null;
    }

    if (parsed.expires_at && new Date(parsed.expires_at).getTime() < Date.now()) {
      storage.removeItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY);
      return null;
    }

    return parsed;
  } catch {
    storage.removeItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY);
    return null;
  }
}

function readStoredResumeDraft(): CheckoutResumeDraft | null {
  for (const storage of getStorageCandidates()) {
    const draft = parseStoredResumeDraft(storage);

    if (draft) {
      return draft;
    }
  }

  return null;
}

function persistResumeDraft(draft: CheckoutResumeDraft) {
  const storages = getStorageCandidates();
  const sessionStorage = storages[0] ?? null;
  const localStorage = storages[1] ?? null;

  if (sessionStorage) {
    sessionStorage.setItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY, JSON.stringify(draft));
    localStorage?.removeItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY);
    return;
  }

  localStorage?.setItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY, JSON.stringify(draft));
}

function clearPersistedResumeDraft() {
  for (const storage of getStorageCandidates()) {
    storage.removeItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY);
  }
}

export function useCheckoutResumeDraft(initialValues: CheckoutV2FormValues) {
  const [resumeDraft, setResumeDraft] = useState<CheckoutResumeDraft | null>(() => readStoredResumeDraft());

  const writeDraft = useCallback((values: CheckoutV2FormValues) => {
    const draft = buildResumeDraft(values);

    persistResumeDraft(draft);
    setResumeDraft(draft);

    return draft;
  }, []);

  const clearDraft = useCallback(() => {
    clearPersistedResumeDraft();
    setResumeDraft(null);
  }, []);

  const restoreValues = useCallback((draft: CheckoutResumeDraft) => (
    restoreFormValuesFromResumeDraft(draft, initialValues)
  ), [initialValues]);

  return {
    resumeDraft,
    writeDraft,
    clearDraft,
    restoreValues,
    setResumeDraft,
  };
}
