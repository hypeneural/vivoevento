import type {
  EventOperationsStationKey,
  EventOperationsVisualRole,
} from '@eventovivo/shared-types/event-operations';

export interface OperationsPixelSpriteSpec {
  width: number;
  height: number;
  fill: string;
  accent: string;
  silhouette: 'desk' | 'monitor' | 'scanner' | 'stack' | 'wall' | 'alert' | 'agent';
}

export const OPERATIONS_ROLE_SPRITES: Record<EventOperationsVisualRole, OperationsPixelSpriteSpec> = {
  coordinator: {
    width: 18,
    height: 24,
    fill: '#f8fafc',
    accent: '#5eead4',
    silhouette: 'agent',
  },
  dispatcher: {
    width: 18,
    height: 24,
    fill: '#fef3c7',
    accent: '#38bdf8',
    silhouette: 'agent',
  },
  runner: {
    width: 18,
    height: 24,
    fill: '#fde68a',
    accent: '#f59e0b',
    silhouette: 'agent',
  },
  reviewer: {
    width: 18,
    height: 24,
    fill: '#ffe4e6',
    accent: '#fb7185',
    silhouette: 'agent',
  },
  operator: {
    width: 18,
    height: 24,
    fill: '#cffafe',
    accent: '#22d3ee',
    silhouette: 'agent',
  },
  triage: {
    width: 18,
    height: 24,
    fill: '#dcfce7',
    accent: '#4ade80',
    silhouette: 'agent',
  },
};

export const OPERATIONS_STATION_SPRITES: Record<EventOperationsStationKey, OperationsPixelSpriteSpec> = {
  intake: {
    width: 132,
    height: 90,
    fill: '#0f2c38',
    accent: '#38bdf8',
    silhouette: 'desk',
  },
  download: {
    width: 132,
    height: 90,
    fill: '#33250f',
    accent: '#f59e0b',
    silhouette: 'desk',
  },
  variants: {
    width: 132,
    height: 90,
    fill: '#3a2810',
    accent: '#fbbf24',
    silhouette: 'monitor',
  },
  safety: {
    width: 132,
    height: 90,
    fill: '#3d181f',
    accent: '#fb7185',
    silhouette: 'scanner',
  },
  intelligence: {
    width: 132,
    height: 90,
    fill: '#20311a',
    accent: '#4ade80',
    silhouette: 'monitor',
  },
  human_review: {
    width: 132,
    height: 90,
    fill: '#381b22',
    accent: '#fb7185',
    silhouette: 'stack',
  },
  gallery: {
    width: 132,
    height: 90,
    fill: '#193221',
    accent: '#4ade80',
    silhouette: 'monitor',
  },
  wall: {
    width: 132,
    height: 90,
    fill: '#102d36',
    accent: '#22d3ee',
    silhouette: 'wall',
  },
  feedback: {
    width: 132,
    height: 90,
    fill: '#1f3017',
    accent: '#5eead4',
    silhouette: 'desk',
  },
  alerts: {
    width: 132,
    height: 90,
    fill: '#33170d',
    accent: '#f97316',
    silhouette: 'alert',
  },
};
