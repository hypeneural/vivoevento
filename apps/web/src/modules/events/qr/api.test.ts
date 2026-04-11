import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import api from '@/lib/api';

import {
  buildPlaceholderEventPublicLinkQrEditorState,
  getEventPublicLinkQrEditorState,
  resetEventPublicLinkQrEditorState,
  uploadEventPublicLinkQrLogoAsset,
  updateEventPublicLinkQrEditorState,
} from './api';

vi.mock('@/lib/api', () => ({
  default: {
    get: vi.fn(),
    put: vi.fn(),
    post: vi.fn(),
    upload: vi.fn(),
  },
}));

function makeResponse() {
  return {
    event_id: 42,
    link_key: 'upload' as const,
    link: {
      key: 'upload' as const,
      label: 'Upload',
      enabled: true,
      identifier_type: 'upload_slug' as const,
      identifier: 'envio-casamento',
      url: 'https://example.com/upload/envio-casamento',
      api_url: null,
      qr_value: 'https://example.com/upload/envio-casamento',
    },
    effective_branding: {
      logo_path: null,
      logo_url: 'https://cdn.example.com/logo.png',
      cover_image_path: null,
      cover_image_url: null,
      primary_color: '#112233',
      secondary_color: '#445566',
      source: 'organization' as const,
      inherits_from_organization: true,
    },
    config: {
      config_version: 'event-public-link-qr.v1' as const,
      usage_preset: 'upload_rapido' as const,
      skin_preset: 'premium' as const,
      render: {
        preview_type: 'svg' as const,
        preview_size: 320,
        margin_modules: 4,
        background_mode: 'solid' as const,
      },
      style: {
        dots: {
          type: 'rounded' as const,
          color: '#112233',
          gradient: null,
        },
        corners_square: {
          type: 'extra-rounded' as const,
          color: '#112233',
          gradient: null,
        },
        corners_dot: {
          type: 'dot' as const,
          color: '#445566',
          gradient: null,
        },
        background: {
          color: '#ffffff',
          gradient: null,
          transparent: false,
        },
      },
      logo: {
        mode: 'event_logo' as const,
        asset_path: null,
        asset_url: 'https://cdn.example.com/logo.png',
        image_size: 0.22,
        margin_px: 8,
        hide_background_dots: true,
        save_as_blob: true,
      },
      advanced: {
        error_correction_level: 'H' as const,
        shape: 'square' as const,
        round_size: true,
        type_number: 0 as const,
        mode: 'Byte' as const,
      },
      export_defaults: {
        extension: 'svg' as const,
        size: 1024,
        download_name_pattern: 'evento-{event_id}-{link_key}',
      },
    },
    config_source: 'saved' as const,
    has_saved_config: true,
    updated_at: '2026-04-11T03:00:00.000Z',
    assets: {
      svg_path: null,
      png_path: null,
    },
  };
}

describe('event public link qr api', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('loads the persisted QR state from the Events endpoint and maps snake_case payloads', async () => {
    vi.mocked(api.get).mockResolvedValue(makeResponse());

    const payload = await getEventPublicLinkQrEditorState(42, 'upload');

    expect(api.get).toHaveBeenCalledWith('/events/42/qr-codes/upload');
    expect(payload).toEqual(expect.objectContaining({
      eventId: '42',
      linkKey: 'upload',
      configSource: 'saved',
      hasSavedConfig: true,
      updatedAt: '2026-04-11T03:00:00.000Z',
      assets: {
        svgPath: null,
        pngPath: null,
      },
    }));
  });

  it('sends semantic config through PUT and reset through POST', async () => {
    const response = makeResponse();

    vi.mocked(api.put).mockResolvedValue(response);
    vi.mocked(api.post).mockResolvedValue({
      ...response,
      config_source: 'default',
      has_saved_config: false,
      updated_at: null,
    });

    const placeholder = buildPlaceholderEventPublicLinkQrEditorState({
      eventId: 42,
      link: response.link,
      effectiveBranding: response.effective_branding,
    });

    await updateEventPublicLinkQrEditorState(42, 'upload', placeholder.config);
    await resetEventPublicLinkQrEditorState(42, 'upload');

    expect(api.put).toHaveBeenCalledWith('/events/42/qr-codes/upload', {
      body: {
        config: placeholder.config,
      },
    });
    expect(api.post).toHaveBeenCalledWith('/events/42/qr-codes/upload/reset', {
      body: {},
    });
  });

  it('uploads a custom QR logo through the existing branding asset endpoint', async () => {
    const file = new File(['logo'], 'logo.png', { type: 'image/png' });
    const uploadMock = vi.spyOn(api, 'upload').mockResolvedValue({
      kind: 'logo',
      path: 'events/branding/9/logo/qr-logo.webp',
      url: 'https://cdn.example.com/events/branding/9/logo/qr-logo.webp',
    });

    const payload = await uploadEventPublicLinkQrLogoAsset(file, 'events/branding/9/logo/old.webp');

    expect(payload.kind).toBe('logo');
    expect(uploadMock).toHaveBeenCalledWith('/events/branding-assets', expect.any(FormData));

    const formData = uploadMock.mock.calls[0]?.[1];
    expect(formData).toBeInstanceOf(FormData);
    expect((formData as FormData).get('kind')).toBe('logo');
    expect((formData as FormData).get('file')).toBe(file);
    expect((formData as FormData).get('previous_path')).toBe('events/branding/9/logo/old.webp');
  });
});
