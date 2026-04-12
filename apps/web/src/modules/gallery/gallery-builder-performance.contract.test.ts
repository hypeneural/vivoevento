import { describe, expect, it } from 'vitest';

import {
  buildGalleryBuilderVitalsTelemetryPayload,
  createGalleryBuilderSettingsFixture,
  createGalleryOptimizedRendererTriggerFixture,
  estimateGalleryRenderedHeightPx,
  resolveGalleryRenderModeForBuilder,
} from './gallery-builder';

describe('gallery builder performance contract', () => {
  it('switches to optimized render mode when the trigger threshold is exceeded', () => {
    const draft = createGalleryBuilderSettingsFixture();
    const trigger = createGalleryOptimizedRendererTriggerFixture({
      item_count: 24,
      estimated_rendered_height_px: 9000,
    });

    const renderMode = resolveGalleryRenderModeForBuilder({
      draft,
      itemCount: 30,
      viewport: 'desktop',
      trigger,
    });

    expect(renderMode).toBe('optimized');
  });

  it('estimates rendered height using layout, density and viewport', () => {
    const estimatedHeight = estimateGalleryRenderedHeightPx({
      itemCount: 18,
      layout: 'masonry',
      density: 'comfortable',
      viewport: 'mobile',
    });

    expect(estimatedHeight).toBeGreaterThan(0);
    expect(estimatedHeight).toBe(2700);
  });

  it('builds the vitals payload with viewport, media stats and latency fields', () => {
    const draft = createGalleryBuilderSettingsFixture();
    const payload = buildGalleryBuilderVitalsTelemetryPayload({
      draft,
      itemCount: 42,
      viewport: 'mobile',
      renderMode: 'optimized',
      vitals: {
        lcp_ms: 1800,
        inp_ms: 110,
        cls: 0.03,
      },
      previewLatencyMs: 840,
      publishLatencyMs: 1120,
    });

    expect(payload).toEqual({
      event: 'vitals_sample',
      viewport: 'mobile',
      item_count: 42,
      layout: draft.media_behavior.grid.layout,
      density: draft.media_behavior.grid.density,
      render_mode: 'optimized',
      lcp_ms: 1800,
      inp_ms: 110,
      cls: 0.03,
      preview_latency_ms: 840,
      publish_latency_ms: 1120,
    });
  });
});
