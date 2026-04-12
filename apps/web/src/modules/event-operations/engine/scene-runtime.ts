import type {
  EventOperationsHealthStatus,
  EventOperationsStationKey,
  EventOperationsVisualRole,
} from '@eventovivo/shared-types/event-operations';

import type {
  ControlRoomMotionMode,
  ControlRoomStationGesture,
} from '../hooks/useReducedControlRoomMotion';
import type { EventOperationsV0Room } from '../types';
import { OPERATIONS_CANVAS_LAYERS, OPERATIONS_CANVAS_SIZE, type OperationsCanvasLayerKey } from './assets';
import { EVENT_OPERATIONS_STATION_VISUAL_GUIDE, resolveVisualRoleAssignments } from './visual-roles';

interface OperationsSceneStationLayout {
  x: number;
  y: number;
  width: number;
  height: number;
}

export interface OperationsSceneStationNode extends OperationsSceneStationLayout {
  station_key: EventOperationsStationKey;
  label: string;
  health: EventOperationsHealthStatus;
  emphasis: 'quiet' | 'active' | 'dominant';
  queue_depth: number;
  recent_items_count: number;
  current_gesture: ControlRoomStationGesture;
  current_gesture_label: string;
  full_motion_label: string;
  reduced_motion_label: string;
}

export interface OperationsSceneAgentNode {
  role: EventOperationsVisualRole;
  label: string;
  anchor_station_key: EventOperationsStationKey;
  mood: 'calm' | 'busy' | 'warning' | 'critical';
  summary: string;
  x: number;
  y: number;
}

export interface OperationsSceneEffectNode {
  station_key: EventOperationsStationKey;
  severity: 'info' | 'warning' | 'critical';
  label: string;
}

export interface OperationsSceneRuntime {
  layers: readonly OperationsCanvasLayerKey[];
  size: typeof OPERATIONS_CANVAS_SIZE;
  calm_state: boolean;
  scene_mode_label: string;
  narrative_summary: string;
  macro_reading: {
    title: string;
    summary: string;
  };
  meso_reading: {
    title: string;
    summary: string;
  };
  stations: OperationsSceneStationNode[];
  agents: OperationsSceneAgentNode[];
  effects: OperationsSceneEffectNode[];
}

interface BuildOperationsSceneRuntimeInput {
  room: EventOperationsV0Room;
  motionMode: ControlRoomMotionMode;
  stationGestures: Record<EventOperationsStationKey, ControlRoomStationGesture>;
}

const STATION_LAYOUT: Record<EventOperationsStationKey, OperationsSceneStationLayout> = {
  intake: { x: 40, y: 92, width: 140, height: 92 },
  download: { x: 220, y: 92, width: 140, height: 92 },
  variants: { x: 400, y: 92, width: 140, height: 92 },
  safety: { x: 580, y: 92, width: 140, height: 92 },
  intelligence: { x: 760, y: 92, width: 140, height: 92 },
  human_review: { x: 40, y: 300, width: 140, height: 92 },
  gallery: { x: 220, y: 300, width: 140, height: 92 },
  wall: { x: 400, y: 300, width: 140, height: 92 },
  feedback: { x: 580, y: 300, width: 140, height: 92 },
  alerts: { x: 760, y: 300, width: 140, height: 92 },
};

const ROLE_OFFSETS: Record<EventOperationsVisualRole, { x: number; y: number }> = {
  coordinator: { x: 16, y: -34 },
  dispatcher: { x: -26, y: 18 },
  runner: { x: 52, y: 24 },
  reviewer: { x: -22, y: 26 },
  operator: { x: 44, y: 24 },
  triage: { x: 18, y: -28 },
};

function clamp(value: number, min: number, max: number): number {
  return Math.max(min, Math.min(max, value));
}

function resolveCalmState(room: EventOperationsV0Room): boolean {
  return room.health.status === 'healthy'
    && room.alerts.length === 0
    && room.counters.backlog_total <= 0
    && room.counters.human_review_pending <= 0;
}

function resolveMacroSummary(room: EventOperationsV0Room, calmState: boolean): string {
  if (calmState) {
    return 'Estado calmo ativo';
  }

  if (room.health.status === 'risk' || room.health.status === 'offline') {
    return 'Alerta prioritario na sala';
  }

  return 'Atencao operacional ativa';
}

function resolveMesoSummary(room: EventOperationsV0Room): { title: string; summary: string } {
  const dominantStation = room.stations.find((station) => station.station_key === room.health.dominant_station_key);

  if (!dominantStation) {
    return {
      title: 'Sem gargalo dominante agora',
      summary: 'A operacao segue distribuida sem ponto unico de pressao.',
    };
  }

  return {
    title: dominantStation.label,
    summary: dominantStation.dominant_reason
      ?? room.v0?.dominant_station_reason
      ?? 'A sala promove essa estacao como foco principal agora.',
  };
}

function resolveStationEmphasis(
  room: EventOperationsV0Room,
  stationKey: EventOperationsStationKey,
  queueDepth: number,
  recentItemsCount: number,
): OperationsSceneStationNode['emphasis'] {
  if (room.health.dominant_station_key === stationKey) {
    return 'dominant';
  }

  if (queueDepth > 0 || recentItemsCount > 0) {
    return 'active';
  }

  return 'quiet';
}

export function buildOperationsSceneRuntime({
  room,
  motionMode,
  stationGestures,
}: BuildOperationsSceneRuntimeInput): OperationsSceneRuntime {
  const calmState = resolveCalmState(room);
  const visualRoles = resolveVisualRoleAssignments(room);
  const mesoReading = resolveMesoSummary(room);

  const stations = room.stations.map<OperationsSceneStationNode>((station) => {
    const guide = EVENT_OPERATIONS_STATION_VISUAL_GUIDE[station.station_key];

    return {
      ...STATION_LAYOUT[station.station_key],
      station_key: station.station_key,
      label: station.label,
      health: station.health,
      emphasis: resolveStationEmphasis(
        room,
        station.station_key,
        station.queue_depth,
        station.recent_items.length,
      ),
      queue_depth: station.queue_depth,
      recent_items_count: station.recent_items.length,
      current_gesture: stationGestures[station.station_key],
      current_gesture_label: motionMode === 'reduced'
        ? guide.reduced_motion_label
        : guide.full_motion_label,
      full_motion_label: guide.full_motion_label,
      reduced_motion_label: guide.reduced_motion_label,
    };
  });

  const agents = visualRoles.map<OperationsSceneAgentNode>((role) => {
    const anchor = STATION_LAYOUT[role.anchor_station_key];
    const offset = ROLE_OFFSETS[role.role];

    return {
      role: role.role,
      label: role.label,
      anchor_station_key: role.anchor_station_key,
      mood: role.mood,
      summary: role.summary,
      x: clamp(anchor.x + anchor.width / 2 + offset.x, 24, OPERATIONS_CANVAS_SIZE.width - 24),
      y: clamp(anchor.y + anchor.height / 2 + offset.y, 24, OPERATIONS_CANVAS_SIZE.height - 24),
    };
  });

  const effects = [
    ...room.alerts.map<OperationsSceneEffectNode>((alert) => ({
      station_key: alert.station_key,
      severity: alert.severity,
      label: alert.title,
    })),
    ...room.health.dominant_station_key
      ? [{
          station_key: room.health.dominant_station_key,
          severity: room.health.status === 'risk' ? 'critical' : 'warning',
          label: mesoReading.title,
        } satisfies OperationsSceneEffectNode]
      : [],
  ];

  return {
    layers: OPERATIONS_CANVAS_LAYERS,
    size: OPERATIONS_CANVAS_SIZE,
    calm_state: calmState,
    scene_mode_label: resolveMacroSummary(room, calmState),
    narrative_summary: room.v0?.journey_summary_text
      ?? 'Boot dedicado da sala com snapshot coarse-grained e rail historico separado.',
    macro_reading: {
      title: room.health.summary,
      summary: resolveMacroSummary(room, calmState),
    },
    meso_reading: mesoReading,
    stations,
    agents,
    effects,
  };
}
