import type { ApiEventEffectiveBranding, ApiEventPublicLink } from '@/lib/api-types';

import { prefetchEventPublicLinkQrEditorState } from './api';
import { loadEventPublicLinkQrEditorModule } from './loader';

type WarmParams = {
  eventId: string | number;
  link: ApiEventPublicLink;
  effectiveBranding?: ApiEventEffectiveBranding | null;
};

const warmedKeys = new Set<string>();

function buildWarmKey(params: WarmParams) {
  return `${String(params.eventId)}:${params.link.key}`;
}

export async function warmEventPublicLinkQrEditor(params: WarmParams) {
  if (!params.link.qr_value) {
    return;
  }

  const warmKey = buildWarmKey(params);

  if (warmedKeys.has(warmKey)) {
    return;
  }

  warmedKeys.add(warmKey);

  const [moduleWarm, queryWarm] = await Promise.allSettled([
    loadEventPublicLinkQrEditorModule(),
    prefetchEventPublicLinkQrEditorState(params),
  ]);

  if (moduleWarm.status === 'rejected' || queryWarm.status === 'rejected') {
    warmedKeys.delete(warmKey);
  }
}

export function __resetEventPublicLinkQrWarmState() {
  warmedKeys.clear();
}
