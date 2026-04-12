import { describe, expect, it } from 'vitest';

import {
  eventOperationsHealthySnapshotFixture,
  eventOperationsHumanReviewBottleneckSnapshotFixture,
} from '../__fixtures__/operations-room.fixture';
import {
  FULL_CONTROL_ROOM_GESTURES,
  REDUCED_CONTROL_ROOM_GESTURES,
} from '../hooks/useReducedControlRoomMotion';
import type { EventOperationsV0Room } from '../types';
import { buildOperationsSceneRuntime } from './scene-runtime';

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
        ? 'Fila de revisao humana acima do normal.'
        : null,
    },
  };
}

describe('scene-runtime', () => {
  it('builds the fixed canvas layers and the calm operational scene for a healthy room', () => {
    const runtime = buildOperationsSceneRuntime({
      room: makeRoom(),
      motionMode: 'full',
      stationGestures: FULL_CONTROL_ROOM_GESTURES,
    });

    expect(runtime.layers).toEqual(['background', 'stations', 'agents', 'effects']);
    expect(runtime.calm_state).toBe(true);
    expect(runtime.macro_reading.title).toBe('Operacao saudavel');
    expect(runtime.meso_reading.title).toBe('Sem gargalo dominante agora');
    expect(runtime.agents.map((agent) => agent.role)).toEqual(
      expect.arrayContaining(['coordinator', 'dispatcher', 'runner', 'reviewer', 'operator']),
    );
    expect(runtime.stations.find((station) => station.station_key === 'intake')?.current_gesture_label)
      .toContain('fila simbolica');
  });

  it('promotes the bottleneck and switches to reduced-motion gestures when requested', () => {
    const runtime = buildOperationsSceneRuntime({
      room: makeRoom(eventOperationsHumanReviewBottleneckSnapshotFixture),
      motionMode: 'reduced',
      stationGestures: REDUCED_CONTROL_ROOM_GESTURES,
    });

    expect(runtime.calm_state).toBe(false);
    expect(runtime.meso_reading.title).toBe('Moderacao humana');
    expect(runtime.stations.find((station) => station.station_key === 'human_review')?.emphasis).toBe('dominant');
    expect(runtime.stations.find((station) => station.station_key === 'intake')?.current_gesture_label)
      .toContain('contagem');
    expect(runtime.stations.find((station) => station.station_key === 'wall')?.current_gesture_label)
      .toContain('current/next/health');
  });
});
