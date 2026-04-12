import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { EventPeopleCoveragePanel } from './EventPeopleCoveragePanel';
import { eventPeopleApi } from '../api';

vi.mock('../api', () => ({
  eventPeopleApi: {
    getCoverage: vi.fn(),
    refreshCoverage: vi.fn(),
  },
}));

function renderPanel() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <EventPeopleCoveragePanel eventId="42" />
    </QueryClientProvider>,
  );
}

describe('EventPeopleCoveragePanel', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders coverage summary and highlights missing targets', async () => {
    vi.mocked(eventPeopleApi.getCoverage).mockResolvedValue({
      summary: {
        missing: 1,
        weak: 0,
        ok: 0,
        strong: 0,
        active_alerts: 1,
        last_evaluated_at: null,
      },
      targets: [{
        id: 1,
        key: 'couple_portrait',
        label: 'Casal junto',
        target_type: 'group',
        status: 'active',
        importance_rank: 100,
        required_media_count: 4,
        required_published_media_count: 1,
        last_evaluated_at: null,
        group: { id: 10, display_name: 'Casal', slug: 'couple', importance_rank: 100 },
        person_a: null,
        person_b: null,
        stat: {
          coverage_state: 'missing',
          score: 0,
          resolved_entity_count: 2,
          media_count: 0,
          published_media_count: 0,
          joint_media_count: 0,
          people_with_primary_photo_count: 0,
          reason_codes: ['grupo_sem_fotos'],
          projected_at: null,
        },
      }],
      alerts: [{
        id: 99,
        alert_key: 'coverage:couple_portrait',
        severity: 'missing',
        title: 'Cobertura: Casal junto',
        summary: 'Cobertura missing (grupo sem fotos).',
        status: 'active',
        last_evaluated_at: null,
        target: {
          id: 1,
          key: 'couple_portrait',
          label: 'Casal junto',
          coverage_state: 'missing',
        },
      }],
    });

    renderPanel();

    expect(await screen.findByText('Cobertura importante')).toBeInTheDocument();
    expect(await screen.findByText('Casal junto')).toBeInTheDocument();
    expect(screen.getAllByText('Faltando')).toHaveLength(2);
  });

  it('refreshes coverage on demand', async () => {
    vi.mocked(eventPeopleApi.getCoverage).mockResolvedValue({
      summary: {
        missing: 0,
        weak: 0,
        ok: 1,
        strong: 0,
        active_alerts: 0,
        last_evaluated_at: null,
      },
      targets: [],
      alerts: [],
    });

    vi.mocked(eventPeopleApi.refreshCoverage).mockResolvedValue({
      summary: {
        missing: 0,
        weak: 0,
        ok: 1,
        strong: 0,
        active_alerts: 0,
        last_evaluated_at: null,
      },
      targets: [],
      alerts: [],
    });

    renderPanel();

    fireEvent.click(await screen.findByRole('button', { name: 'Recalcular' }));

    await waitFor(() => {
      expect(eventPeopleApi.refreshCoverage).toHaveBeenCalledWith('42');
    });
  });
});
