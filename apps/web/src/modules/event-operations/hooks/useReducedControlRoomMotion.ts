import { useEffect, useMemo, useState } from 'react';

import type { EventOperationsStationKey } from '@eventovivo/shared-types/event-operations';

export type ControlRoomMotionMode = 'full' | 'reduced';
export type ControlRoomStationGesture =
  | 'pulse_and_queue'
  | 'conveyor_motion'
  | 'thumbnail_birth'
  | 'scanner_sweep'
  | 'terminal_reading'
  | 'card_stack_motion'
  | 'live_wall_glow'
  | 'monitor_glow'
  | 'message_trails'
  | 'short_siren'
  | 'count_pulse'
  | 'static_box_count'
  | 'thumbnail_counter'
  | 'color_shift'
  | 'reading_badge'
  | 'stack_indicator'
  | 'recent_thumb_badge'
  | 'current_next_badge'
  | 'message_counter'
  | 'static_warning_badge';

export const FULL_CONTROL_ROOM_GESTURES: Record<EventOperationsStationKey, ControlRoomStationGesture> = {
  intake: 'pulse_and_queue',
  download: 'conveyor_motion',
  variants: 'thumbnail_birth',
  safety: 'scanner_sweep',
  intelligence: 'terminal_reading',
  human_review: 'card_stack_motion',
  gallery: 'live_wall_glow',
  wall: 'monitor_glow',
  feedback: 'message_trails',
  alerts: 'short_siren',
};

export const REDUCED_CONTROL_ROOM_GESTURES: Record<EventOperationsStationKey, ControlRoomStationGesture> = {
  intake: 'count_pulse',
  download: 'static_box_count',
  variants: 'thumbnail_counter',
  safety: 'color_shift',
  intelligence: 'reading_badge',
  human_review: 'stack_indicator',
  gallery: 'recent_thumb_badge',
  wall: 'current_next_badge',
  feedback: 'message_counter',
  alerts: 'static_warning_badge',
};

export function useReducedControlRoomMotion() {
  const [prefersReducedMotion, setPrefersReducedMotion] = useState(() =>
    typeof window.matchMedia === 'function'
      ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
      : false,
  );

  useEffect(() => {
    if (typeof window.matchMedia !== 'function') {
      return undefined;
    }

    const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    const update = () => setPrefersReducedMotion(mediaQuery.matches);

    update();
    mediaQuery.addEventListener?.('change', update);

    return () => {
      mediaQuery.removeEventListener?.('change', update);
    };
  }, []);

  const stationGestures = useMemo(
    () => (prefersReducedMotion ? REDUCED_CONTROL_ROOM_GESTURES : FULL_CONTROL_ROOM_GESTURES),
    [prefersReducedMotion],
  );

  return {
    prefersReducedMotion,
    motionMode: prefersReducedMotion ? 'reduced' : 'full' as ControlRoomMotionMode,
    stationGestures,
  };
}
