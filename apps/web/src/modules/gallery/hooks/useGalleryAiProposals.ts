import { useMutation } from '@tanstack/react-query';

import { runEventGalleryAiProposals } from '../api';
import type { GalleryAiTargetLayer } from '../gallery-builder';

export function useGalleryAiProposals(eventId: string | null) {
  return useMutation({
    mutationFn: (payload: {
      prompt_text: string;
      persona_key?: string | null;
      target_layer?: GalleryAiTargetLayer;
      base_preset_key?: string | null;
    }) => runEventGalleryAiProposals(eventId as string, payload),
  });
}
