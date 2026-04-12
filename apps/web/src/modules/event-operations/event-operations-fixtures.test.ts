import { describe, expect, it } from 'vitest';

import {
  eventOperationsAlertDeltaFixture,
  eventOperationsDegradedSnapshotFixture,
  eventOperationsGapDeltaFixture,
  eventOperationsHealthySnapshotFixture,
  eventOperationsHumanReviewBottleneckSnapshotFixture,
} from './__fixtures__/operations-room.fixture';

describe('event operations fixtures', () => {
  it('provides a healthy baseline snapshot for frontend parallel work', () => {
    expect(eventOperationsHealthySnapshotFixture.schema_version).toBe(1);
    expect(eventOperationsHealthySnapshotFixture.event_sequence).toBe(100);
    expect(eventOperationsHealthySnapshotFixture.health.status).toBe('healthy');
    expect(eventOperationsHealthySnapshotFixture.stations.some((station) => station.station_key === 'wall')).toBe(true);
  });

  it('provides a dominant bottleneck snapshot for visual hierarchy tests', () => {
    const bottleneck = eventOperationsHumanReviewBottleneckSnapshotFixture.stations.find(
      (station) => station.station_key === 'human_review',
    );

    expect(eventOperationsHumanReviewBottleneckSnapshotFixture.health.status).toBe('attention');
    expect(bottleneck?.health).toBe('attention');
    expect(bottleneck?.queue_depth).toBeGreaterThan(0);
    expect(bottleneck?.animation_hint).toBe('review_backlog');
  });

  it('provides critical alert, sequence gap and degraded scenarios', () => {
    expect(eventOperationsAlertDeltaFixture.kind).toBe('alert.created');
    expect(eventOperationsAlertDeltaFixture.alert?.severity).toBe('critical');

    expect(eventOperationsGapDeltaFixture.event_sequence).toBeGreaterThan(
      eventOperationsHealthySnapshotFixture.event_sequence + 1,
    );
    expect(eventOperationsGapDeltaFixture.resync_required).toBe(true);

    expect(eventOperationsDegradedSnapshotFixture.connection.status).toBe('degraded');
    expect(eventOperationsDegradedSnapshotFixture.health.status).toBe('risk');
  });
});
