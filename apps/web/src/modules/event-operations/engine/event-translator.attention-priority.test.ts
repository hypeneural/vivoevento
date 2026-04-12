import { describe, expect, it } from 'vitest';

import {
  eventOperationsDegradedSnapshotFixture,
  eventOperationsHealthySnapshotFixture,
  eventOperationsHumanReviewBottleneckSnapshotFixture,
} from '../__fixtures__/operations-room.fixture';
import type { EventOperationsV0Room } from '../types';
import { resolveOperationsAttentionPriority } from './attention-priority';

function makeRoom(snapshot = eventOperationsHealthySnapshotFixture): EventOperationsV0Room {
  return {
    ...snapshot,
    v0: {
      mode: 'read_only',
      journey_summary_text: 'Fluxo atual com recepcao, safety, moderacao e wall.',
      active_entry_channels: ['WhatsApp privado', 'Telegram', 'Link de envio'],
      destinations: {
        gallery: true,
        wall: true,
        print: false,
      },
      dominant_station_reason: snapshot.health.dominant_station_key
        ? 'A sala esta sob pressao nessa estacao.'
        : null,
    },
  };
}

describe('event-translator attention priority', () => {
  it('promotes urgent operational failures before any other visual reading', () => {
    const attention = resolveOperationsAttentionPriority(makeRoom(eventOperationsDegradedSnapshotFixture));

    expect(attention.kind).toBe('urgent_failure');
    expect(attention.station_key).toBe('wall');
    expect(attention.severity).toBe('critical');
    expect(attention.title).toContain('Player do telao offline');
  });

  it('promotes the dominant bottleneck when there is no urgent incident', () => {
    const attention = resolveOperationsAttentionPriority(makeRoom(eventOperationsHumanReviewBottleneckSnapshotFixture));

    expect(attention.kind).toBe('dominant_bottleneck');
    expect(attention.station_key).toBe('human_review');
    expect(attention.title).toContain('Moderacao humana');
  });

  it('falls back to visible progress before decorative breathing when the room is healthy', () => {
    const attention = resolveOperationsAttentionPriority(makeRoom(eventOperationsHealthySnapshotFixture));

    expect(attention.kind).toBe('visible_progress');
    expect(attention.station_key).toBe('gallery');
    expect(attention.severity).toBe('info');
  });
});
