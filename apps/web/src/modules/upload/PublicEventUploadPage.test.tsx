import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { PublicEventUploadBootstrap, PublicEventUploadResult } from '@/lib/api-types';

import PublicEventUploadPage from './PublicEventUploadPage';

const getMock = vi.fn();
const uploadMock = vi.fn();
const toastMock = vi.fn();
const createObjectURLMock = vi.fn();
const revokeObjectURLMock = vi.fn();

vi.mock('@/lib/api', () => ({
  ApiError: class ApiError extends Error {},
  default: {
    get: (...args: unknown[]) => getMock(...args),
    upload: (...args: unknown[]) => uploadMock(...args),
  },
}));

vi.mock('@/hooks/use-toast', () => ({
  toast: (...args: unknown[]) => toastMock(...args),
}));

function makeBootstrap(overrides: Partial<PublicEventUploadBootstrap> = {}): PublicEventUploadBootstrap {
  return {
    event: {
      id: 11,
      title: 'Festival da Familia',
      slug: 'festival-da-familia',
      upload_slug: 'festival-da-familia-upload',
      cover_image_path: null,
      cover_image_url: null,
      logo_path: null,
      logo_url: null,
      primary_color: '#5b3df5',
      secondary_color: '#1f8fff',
      starts_at: '2026-04-09T18:00:00Z',
      location_name: 'Florianopolis',
    },
    upload: {
      enabled: true,
      status: 'available',
      reason: null,
      message: 'Envie fotos ou 1 video curto direto do seu celular.',
      accepts_multiple: true,
      accepts_video: true,
      video_single_only: true,
      video_max_duration_seconds: 30,
      max_files: 10,
      max_file_size_mb: 20,
      accept_hint: 'image/*,video/mp4,video/quicktime',
      moderation_mode: 'manual',
      instructions: 'As fotos e os videos curtos de ate 30s passam por moderacao manual antes de aparecer no evento.',
    },
    links: {
      upload_url: 'https://app.eventovivo.test/upload/festival-da-familia-upload',
      upload_api_url: 'https://api.eventovivo.test/api/v1/public/events/festival-da-familia-upload/upload',
      hub_url: null,
    },
    ...overrides,
  };
}

function makeUploadResult(overrides: Partial<PublicEventUploadResult> = {}): PublicEventUploadResult {
  return {
    message: 'Imagem recebida com sucesso!',
    uploaded_count: 1,
    media_ids: [1],
    moderation: 'pending',
    ...overrides,
  };
}

function renderPage(initialEntry = '/upload/festival-da-familia-upload') {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <Routes>
          <Route path="/upload/:code" element={<PublicEventUploadPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('PublicEventUploadPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    createObjectURLMock.mockImplementation((file: File) => `blob:${file.name}`);
    revokeObjectURLMock.mockImplementation(() => undefined);
    vi.stubGlobal('URL', {
      ...window.URL,
      createObjectURL: createObjectURLMock,
      revokeObjectURL: revokeObjectURLMock,
    });
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('renders the upload policy for photos and one short video', async () => {
    getMock.mockResolvedValue(makeBootstrap());

    const { container } = renderPage();

    expect(await screen.findByText('Festival da Familia')).toBeInTheDocument();
    expect(screen.getByText('Envie fotos ou 1 video curto direto do seu celular.')).toBeInTheDocument();
    expect(screen.getByText('Selecione fotos ou 1 video curto de ate 30s.')).toBeInTheDocument();

    const fileInputs = container.querySelectorAll('input[type="file"]');
    const galleryInput = fileInputs[1] as HTMLInputElement;

    expect(galleryInput.accept).toBe('image/*,video/mp4,video/quicktime');
    expect(galleryInput.multiple).toBe(true);
  });

  it('submits a single selected video as the file field', async () => {
    getMock.mockResolvedValue(makeBootstrap());
    uploadMock.mockResolvedValue(makeUploadResult({
      message: 'Video recebido com sucesso!',
      uploaded_count: 1,
    }));

    const { container } = renderPage();

    await screen.findByText('Festival da Familia');

    const fileInputs = container.querySelectorAll('input[type="file"]');
    const galleryInput = fileInputs[1] as HTMLInputElement;
    const videoFile = new File(['video-binary'], 'entrada.mp4', { type: 'video/mp4' });

    fireEvent.change(galleryInput, {
      target: {
        files: [videoFile],
      },
    });

    expect(await screen.findByText('entrada.mp4')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /enviar/i }));

    await waitFor(() => {
      expect(uploadMock).toHaveBeenCalledTimes(1);
    });

    const formData = uploadMock.mock.calls[0]?.[1] as FormData;

    expect(formData.get('file')).toBe(videoFile);
    expect(formData.getAll('files[]')).toHaveLength(0);
  });

  it('keeps multi-image uploads on files[]', async () => {
    getMock.mockResolvedValue(makeBootstrap());
    uploadMock.mockResolvedValue(makeUploadResult({
      message: 'Imagens recebidas com sucesso!',
      uploaded_count: 2,
      media_ids: [1, 2],
    }));

    const { container } = renderPage();

    await screen.findByText('Festival da Familia');

    const fileInputs = container.querySelectorAll('input[type="file"]');
    const galleryInput = fileInputs[1] as HTMLInputElement;
    const imageA = new File(['image-a'], 'entrada.jpg', { type: 'image/jpeg' });
    const imageB = new File(['image-b'], 'brinde.png', { type: 'image/png' });

    fireEvent.change(galleryInput, {
      target: {
        files: [imageA, imageB],
      },
    });

    expect(await screen.findByText('entrada.jpg')).toBeInTheDocument();
    expect(await screen.findByText('brinde.png')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /enviar/i }));

    await waitFor(() => {
      expect(uploadMock).toHaveBeenCalledTimes(1);
    });

    const formData = uploadMock.mock.calls[0]?.[1] as FormData;

    expect(formData.get('file')).toBeNull();
    expect(formData.getAll('files[]')).toEqual([imageA, imageB]);
  });

  it('rejects mixed video and image selections in the same batch', async () => {
    getMock.mockResolvedValue(makeBootstrap());

    const { container } = renderPage();

    await screen.findByText('Festival da Familia');

    const fileInputs = container.querySelectorAll('input[type="file"]');
    const galleryInput = fileInputs[1] as HTMLInputElement;
    const videoFile = new File(['video-binary'], 'entrada.mp4', { type: 'video/mp4' });
    const imageFile = new File(['image-binary'], 'brinde.jpg', { type: 'image/jpeg' });

    fireEvent.change(galleryInput, {
      target: {
        files: [videoFile, imageFile],
      },
    });

    expect(toastMock).toHaveBeenCalledWith(expect.objectContaining({
      title: 'Selecao nao permitida',
    }));
    expect(screen.queryByText('entrada.mp4')).not.toBeInTheDocument();
    expect(screen.queryByText('brinde.jpg')).not.toBeInTheDocument();
    expect(uploadMock).not.toHaveBeenCalled();
  });
});
