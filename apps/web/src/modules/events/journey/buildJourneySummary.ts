import type { EventJourneyBuiltScenario, EventJourneyProjection } from './types';

export function buildJourneySummary(
  projection: EventJourneyProjection,
  simulation?: EventJourneyBuiltScenario | null,
) {
  if (!simulation) {
    return projection.summary.human_text;
  }

  return simulation.humanText;
}
