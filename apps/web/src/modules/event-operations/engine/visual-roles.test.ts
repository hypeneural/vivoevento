import { describe, expect, it } from 'vitest';

import { eventOperationsHealthySnapshotFixture } from '../__fixtures__/operations-room.fixture';
import type { EventOperationsV0Room } from '../types';
import {
  EVENT_OPERATIONS_STATION_VISUAL_GUIDE,
  EVENT_OPERATIONS_VISUAL_ROLE_CONFIG,
  resolveVisualRoleAssignments,
} from './visual-roles';

function makeRoom(): EventOperationsV0Room {
  return {
    ...eventOperationsHealthySnapshotFixture,
    v0: {
      mode: 'read_only',
      journey_summary_text: 'Fluxo atual com recepcao, safety, moderacao e wall.',
      active_entry_channels: ['WhatsApp privado', 'Telegram', 'Link de envio'],
      destinations: {
        gallery: true,
        wall: true,
        print: false,
      },
      dominant_station_reason: null,
    },
  };
}

describe('visual-roles', () => {
  it('declares the operational archetypes that make the room look like a working team', () => {
    expect(EVENT_OPERATIONS_VISUAL_ROLE_CONFIG.coordinator.label).toBe('Coordinator');
    expect(EVENT_OPERATIONS_VISUAL_ROLE_CONFIG.dispatcher.label).toBe('Dispatcher');
    expect(EVENT_OPERATIONS_VISUAL_ROLE_CONFIG.runner.label).toBe('Runner');
    expect(EVENT_OPERATIONS_VISUAL_ROLE_CONFIG.reviewer.label).toBe('Reviewer');
    expect(EVENT_OPERATIONS_VISUAL_ROLE_CONFIG.operator.label).toBe('Operator');
    expect(EVENT_OPERATIONS_VISUAL_ROLE_CONFIG.triage.label).toBe('Triage');
  });

  it('maps each station to a full-motion and reduced-motion gesture guide', () => {
    expect(EVENT_OPERATIONS_STATION_VISUAL_GUIDE.intake.full_motion_label).toContain('fila simbolica');
    expect(EVENT_OPERATIONS_STATION_VISUAL_GUIDE.intake.reduced_motion_label).toContain('contagem');
    expect(EVENT_OPERATIONS_STATION_VISUAL_GUIDE.wall.full_motion_label).toContain('monitor central');
    expect(EVENT_OPERATIONS_STATION_VISUAL_GUIDE.wall.reduced_motion_label).toContain('current/next/health');
  });

  it('anchors the visual roles to the current operational reading of the room', () => {
    const assignments = resolveVisualRoleAssignments(makeRoom());

    expect(assignments.find((role) => role.role === 'dispatcher')?.anchor_station_key).toBe('intake');
    expect(assignments.find((role) => role.role === 'reviewer')?.anchor_station_key).toBe('human_review');
    expect(assignments.find((role) => role.role === 'operator')?.anchor_station_key).toBe('wall');
  });
});
