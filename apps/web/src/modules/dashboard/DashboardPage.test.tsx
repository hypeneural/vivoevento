import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import DashboardPage from './DashboardPage';

const useDashboardStatsMock = vi.fn();

vi.mock('./hooks/useDashboardStats', () => ({
  useDashboardStats: () => useDashboardStatsMock(),
}));

vi.mock('@/app/providers/ThemeProvider', () => ({
  useTheme: () => ({
    resolvedTheme: 'light',
  }),
}));

describe('DashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows the error state when the dashboard payload is malformed', () => {
    useDashboardStatsMock.mockReturnValue({
      data: {
        charts: {
          uploads_per_hour: [],
        },
      },
      isLoading: false,
      isError: false,
    });

    render(
      <MemoryRouter>
        <DashboardPage />
      </MemoryRouter>,
    );

    expect(screen.getByText(/erro ao carregar dashboard/i)).toBeInTheDocument();
  });
});
