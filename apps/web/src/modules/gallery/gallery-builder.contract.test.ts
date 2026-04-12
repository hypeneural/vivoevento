import { describe, expect, it } from 'vitest';

import {
  galleryContractCatalog,
  galleryModelMatrixOptions,
  createGalleryExperienceFixture,
} from './gallery-builder';

describe('gallery builder contract', () => {
  it('freezes the human-facing model matrix axes', () => {
    expect(galleryModelMatrixOptions.eventTypeFamilies).toEqual(['wedding', 'quince', 'corporate']);
    expect(galleryModelMatrixOptions.styleSkins).toEqual(['romantic', 'modern', 'classic', 'premium', 'clean']);
    expect(galleryModelMatrixOptions.behaviorProfiles).toEqual(['light', 'story', 'live', 'sponsors']);
  });

  it('keeps the builder contract split into theme, page and media layers', () => {
    const experience = createGalleryExperienceFixture({
      event_type_family: 'wedding',
      style_skin: 'romantic',
      behavior_profile: 'story',
    });

    expect(experience).toHaveProperty('theme_tokens');
    expect(experience).toHaveProperty('page_schema');
    expect(experience).toHaveProperty('media_behavior');
    expect(experience.theme_tokens.contrast_rules).toEqual({
      body_text_min_ratio: 4.5,
      large_text_min_ratio: 3,
      ui_min_ratio: 3,
    });
    expect(experience.page_schema.presence_rules.require_preview_before_publish).toBe(true);
    expect(experience.media_behavior.lightbox).toEqual({
      photos: true,
      videos: false,
    });
  });

  it('declares closed catalogs for layout, theme, blocks and video modes', () => {
    expect(galleryContractCatalog.layoutKeys).toContain('editorial-masonry');
    expect(galleryContractCatalog.layoutKeys).toContain('timeless-rows');
    expect(galleryContractCatalog.layoutKeys).toContain('clean-columns');
    expect(galleryContractCatalog.themeKeys).toContain('event-brand');
    expect(galleryContractCatalog.blockKeys).toContain('gallery_stream');
    expect(galleryContractCatalog.videoModes).toEqual(['poster_only', 'poster_to_modal', 'inline_preview']);
  });
});
