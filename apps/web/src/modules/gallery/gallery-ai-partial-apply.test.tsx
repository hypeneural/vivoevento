import { describe, expect, it } from 'vitest';

import {
  applyGalleryAiVariationToDraft,
  createGalleryAiVariationFixture,
  createGalleryBuilderSettingsFixture,
} from './gallery-builder';

describe('gallery ai partial apply', () => {
  it('applies only the requested ai layer without mutating the others', () => {
    const draft = createGalleryBuilderSettingsFixture();
    const variation = createGalleryAiVariationFixture({
      model_matrix: {
        event_type_family: 'wedding',
        style_skin: 'premium',
        behavior_profile: 'live',
        theme_key: 'black-tie',
        layout_key: 'live-stream',
      },
      patch: {
        theme_tokens: {
          palette: {
            accent: '#f8fafc',
          },
        },
        page_schema: {
          blocks: {
            quote: {
              enabled: false,
            },
          },
        },
        media_behavior: {
          grid: {
            layout: 'rows',
            density: 'immersive',
          },
        },
      },
    });

    const next = applyGalleryAiVariationToDraft(draft, variation, 'theme_tokens');

    expect(next.theme_key).toBe('black-tie');
    expect(next.style_skin).toBe('premium');
    expect(next.theme_tokens.palette.accent).toBe('#f8fafc');
    expect(next.page_schema).toEqual(draft.page_schema);
    expect(next.media_behavior).toEqual(draft.media_behavior);
    expect(next.layout_key).toBe(draft.layout_key);
    expect(next.behavior_profile).toBe(draft.behavior_profile);
  });
});
