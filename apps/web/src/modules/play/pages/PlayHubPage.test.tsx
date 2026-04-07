import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PlayHubPage from './PlayHubPage';

const listEventsMock = vi.fn();

vi.mock('@/modules/events/api', () => ({
  listEvents: (...args: unknown[]) => listEventsMock(...args),
}));

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <PlayHubPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('PlayHubPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows the error state when the events payload is malformed', async () => {
    listEventsMock.mockResolvedValue({
      data: 'invalid',
    });

    renderPage();

    expect(await screen.findByText(/nao foi possivel carregar os eventos com jogos/i)).toBeInTheDocument();
  });
});
