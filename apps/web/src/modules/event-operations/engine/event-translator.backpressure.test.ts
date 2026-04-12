import { describe, expect, it } from 'vitest';

import { eventOperationsHumanReviewBottleneckSnapshotFixture } from '../__fixtures__/operations-room.fixture';
import type { EventOperationsV0Room } from '../types';
import { translateEventOperationsRoom } from './event-translator';

function makeBurstRoom(): EventOperationsV0Room {
  return {
    ...eventOperationsHumanReviewBottleneckSnapshotFixture,
    stations: eventOperationsHumanReviewBottleneckSnapshotFixture.stations.map((station) => {
      if (station.station_key === 'human_review') {
        return {
          ...station,
          queue_depth: 24,
          backlog_count: 24,
          station_load: 0.92,
          recent_items: Array.from({ length: 8 }, (_, index) => ({
            id: `review_${index + 1}`,
            event_sequence: 140 + index,
            title: `Review ${index + 1}`,
            occurred_at: '2026-04-11T18:42:15Z',
          })),
        };
      }

      if (station.station_key === 'gallery') {
        return {
          ...station,
          recent_items: Array.from({ length: 6 }, (_, index) => ({
            id: `gallery_${index + 1}`,
            event_sequence: 200 + index,
            title: `Gallery ${index + 1}`,
            occurred_at: '2026-04-11T18:42:15Z',
          })),
          throughput_per_minute: 14,
        };
      }

      return station;
    }),
    counters: {
      ...eventOperationsHumanReviewBottleneckSnapshotFixture.counters,
      backlog_total: 36,
      human_review_pending: 24,
    },
    alerts: [
      ...eventOperationsHumanReviewBottleneckSnapshotFixture.alerts,
      {
        id: 'alert_2',
        severity: 'warning',
        urgency: 'high',
        station_key: 'human_review',
        title: 'Burst sintetizado',
        summary: 'A fila humana ultrapassou a janela visual.',
        occurred_at: '2026-04-11T18:42:15Z',
      },
    ],
    v0: {
      mode: 'read_only',
      journey_summary_text: 'Burst de revisao humana e publicacao simultanea.',
      active_entry_channels: ['WhatsApp privado', 'Telegram'],
      destinations: {
        gallery: true,
        wall: true,
        print: false,
      },
      dominant_station_reason: 'Burst humano acima do orcamento perceptivo.',
    },
  };
}

describe('event-translator backpressure', () => {
  it('coalesces micro-bursts into counters, heat and capped visual budgets', () => {
    const direction = translateEventOperationsRoom({
      room: makeBurstRoom(),
      motionMode: 'full',
    });

    expect(direction.backpressure_active).toBe(true);
    expect(direction.budgets.max_queue_cards_per_station).toBeLessThan(3);
    expect(direction.budgets.max_recent_thumbs_per_station).toBeLessThan(3);

    const reviewStation = direction.stations.find((station) => station.station_key === 'human_review');
    const galleryStation = direction.stations.find((station) => station.station_key === 'gallery');

    expect(reviewStation?.pressure_mode).toBe('heat');
    expect(reviewStation?.visual_queue_count).toBeLessThan(reviewStation?.queue_depth ?? 0);
    expect(reviewStation?.heat_level).toBe(3);
    expect(galleryStation?.visual_recent_count).toBeLessThan(galleryStation?.recent_items_count ?? 0);
    expect(galleryStation?.pressure_mode).toBe('counter');
  });
});
