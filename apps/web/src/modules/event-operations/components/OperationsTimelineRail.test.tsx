import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import type { EventOperationsTimelineEntry } from '@eventovivo/shared-types/event-operations';

import { OperationsTimelineRail } from './OperationsTimelineRail';

describe('OperationsTimelineRail', () => {
  it('renders the history rail as an accessible append-only log ordered from oldest to newest', () => {
    const entries: EventOperationsTimelineEntry[] = [
      {
        id: 'evt_new',
        event_sequence: 102,
        station_key: 'wall',
        event_key: 'wall.health.changed',
        severity: 'warning',
        urgency: 'high',
        title: 'Wall em atencao',
        summary: 'Um player ficou degradado.',
        occurred_at: '2026-04-11T18:43:15Z',
        render_group: 'wall',
        animation_hint: 'wall_health',
      },
      {
        id: 'evt_old',
        event_sequence: 101,
        station_key: 'gallery',
        event_key: 'media.published.gallery',
        severity: 'info',
        urgency: 'normal',
        title: 'Midia publicada',
        summary: 'Uma nova foto entrou na galeria.',
        occurred_at: '2026-04-11T18:42:15Z',
        render_group: 'publishing',
        animation_hint: 'gallery_publish',
      },
    ];

    render(<OperationsTimelineRail entries={entries} />);

    const log = screen.getByRole('log', { name: 'Timeline da operacao' });
    const items = within(log).getAllByRole('listitem');

    expect(items[0]).toHaveTextContent('Midia publicada');
    expect(items[1]).toHaveTextContent('Wall em atencao');
  });
});
