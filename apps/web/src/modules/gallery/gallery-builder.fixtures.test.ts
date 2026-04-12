import { describe, expect, it } from 'vitest';

import { galleryExperienceFixtures } from './gallery-builder';

describe('gallery builder fixtures', () => {
  it('provides the four sprint zero model fixtures', () => {
    expect(Object.keys(galleryExperienceFixtures)).toEqual([
      'weddingRomanticStory',
      'weddingPremiumLight',
      'quinceModernLive',
      'corporateCleanSponsors',
    ]);
  });

  it('derives wedding and quince into long-gallery friendly layouts', () => {
    expect(galleryExperienceFixtures.weddingPremiumLight.media_behavior.grid.layout).toBe('masonry');
    expect(galleryExperienceFixtures.quinceModernLive.media_behavior.grid.layout).toBe('masonry');
    expect(galleryExperienceFixtures.quinceModernLive.media_behavior.video.mode).toBe('inline_preview');
  });

  it('derives corporate sponsors into rows and sponsor interstitials', () => {
    const experience = galleryExperienceFixtures.corporateCleanSponsors;

    expect(experience.theme_key).toBe('corporate-clean');
    expect(experience.media_behavior.grid.layout).toBe('rows');
    expect(experience.media_behavior.interstitials).toMatchObject({
      enabled: true,
      policy: 'sponsors',
      max_per_24_items: 1,
    });
    expect(experience.page_schema.blocks.banner_strip).toMatchObject({
      enabled: true,
      positions: ['after_12'],
    });
  });
});
