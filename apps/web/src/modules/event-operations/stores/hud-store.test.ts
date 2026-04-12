import { beforeEach, describe, expect, it } from 'vitest';

import { eventOperationsDegradedSnapshotFixture, eventOperationsHealthySnapshotFixture } from '../__fixtures__/operations-room.fixture';
import { buildOperationsHudState, eventOperationsHudStore } from './hud-store';

describe('hud-store', () => {
  beforeEach(() => {
    eventOperationsHudStore.reset();
  });

  it('derives a compact hud summary from the room snapshot', () => {
    const hud = buildOperationsHudState(eventOperationsHealthySnapshotFixture);

    expect(hud.event_title).toBe('Casamento Ana e Bruno');
    expect(hud.global_status_label).toBe('Saudavel');
    expect(hud.connection_label).toBe('Conectado');
    expect(hud.wall_label).toContain('online');
    expect(hud.human_queue_label).toBe('0 pendente(s)');
  });

  it('stores the derived hud state separately from the room payload and resets cleanly', () => {
    eventOperationsHudStore.setRoom(eventOperationsDegradedSnapshotFixture);

    expect(eventOperationsHudStore.getSnapshot().room_snapshot_version).toBe(3);
    expect(eventOperationsHudStore.getSnapshot().hud?.connection_label).toBe('Sala degradada: dados ao vivo indisponiveis');
    expect(eventOperationsHudStore.getSnapshot().hud?.wall_tone).toBe('critical');

    eventOperationsHudStore.reset();

    expect(eventOperationsHudStore.getSnapshot().hud).toBeNull();
  });
});
