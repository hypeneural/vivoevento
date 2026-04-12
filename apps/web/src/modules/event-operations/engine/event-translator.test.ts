import { describe, expect, it } from 'vitest';

import { eventOperationsHealthySnapshotFixture } from '../__fixtures__/operations-room.fixture';
import type { EventOperationsV0Room } from '../types';
import { translateEventOperationsRoom } from './event-translator';

function makeQuietRoom(): EventOperationsV0Room {
  return {
    ...eventOperationsHealthySnapshotFixture,
    counters: {
      ...eventOperationsHealthySnapshotFixture.counters,
      intake_per_minute: 0,
      backlog_total: 0,
    },
    stations: eventOperationsHealthySnapshotFixture.stations.map((station) => ({
      ...station,
      queue_depth: 0,
      backlog_count: 0,
      throughput_per_minute: 0,
      recent_items: [],
      animation_hint: 'none',
    })),
    timeline: [],
    v0: {
      mode: 'read_only',
      journey_summary_text: 'Sala calma e legivel mesmo sem throughput alto.',
      active_entry_channels: ['WhatsApp privado'],
      destinations: {
        gallery: true,
        wall: true,
        print: false,
      },
      dominant_station_reason: null,
    },
  };
}

describe('event-translator', () => {
  it('keeps a semantic calm mode instead of faking activity when the room is healthy and quiet', () => {
    const direction = translateEventOperationsRoom({
      room: makeQuietRoom(),
      motionMode: 'full',
    });

    expect(direction.scene_phase).toBe('calm');
    expect(direction.attention.kind).toBe('decorative_breathing');
    expect(direction.decorative_breathing_enabled).toBe(true);
    expect(direction.narrative_summary).toContain('calma operacional');
  });

  it('keeps reduced-motion semantic by changing the scene budget instead of just turning animation off', () => {
    const direction = translateEventOperationsRoom({
      room: makeQuietRoom(),
      motionMode: 'reduced',
    });

    expect(direction.reduced_motion_semantic).toBe(true);
    expect(direction.budgets.max_moving_agents).toBe(1);
    expect(direction.budgets.max_alert_effects).toBe(1);
    expect(direction.narrative_summary).toContain('reduced-motion');
  });
});
