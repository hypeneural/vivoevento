import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import EventDetailPage from './EventDetailPage';

const getEventDetailMock = vi.fn();
const getEventCommercialStatusMock = vi.fn();
const listEventMediaMock = vi.fn();

vi.mock('./api', () => ({
  deleteEventIntakeBlacklistEntry: vi.fn(),
  getEventCommercialStatus: (...args: unknown[]) => getEventCommercialStatusMock(...args),
  getEventDetail: (...args: unknown[]) => getEventDetailMock(...args),
  listEventMedia: (...args: unknown[]) => listEventMediaMock(...args),
  regenerateEventPublicIdentifiers: vi.fn(),
  upsertEventIntakeBlacklistEntry: vi.fn(),
  updateEventPublicIdentifiers: vi.fn(),
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: vi.fn(),
  }),
}));

vi.mock('@/shared/hooks/usePermissions', () => ({
  usePermissions: () => ({
    can: () => true,
  }),
}));

function renderEventDetailPage(initialEntry = '/events/42') {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter
        initialEntries={[initialEntry]}
        future={{ v7_relativeSplatPath: true, v7_startTransition: true }}
      >
        <Routes>
          <Route path="/events/:id" element={<EventDetailPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('EventDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('keeps the loading screen stable before the event payload exists', async () => {
    getEventDetailMock.mockReturnValue(new Promise(() => {}));
    getEventCommercialStatusMock.mockReturnValue(new Promise(() => {}));

    const { container } = renderEventDetailPage();

    await waitFor(() => {
      expect(getEventDetailMock).toHaveBeenCalledWith('42');
    });

    expect(container.querySelector('.animate-spin')).toBeInTheDocument();
  });
});
