export const OPERATIONS_CANVAS_LAYERS = [
  'background',
  'stations',
  'agents',
  'effects',
] as const;

export type OperationsCanvasLayerKey = (typeof OPERATIONS_CANVAS_LAYERS)[number];

export const OPERATIONS_CANVAS_SIZE = {
  width: 960,
  height: 540,
} as const;

export const OPERATIONS_SCENE_PALETTE = {
  night: '#071511',
  floor: '#10221d',
  tile: '#173229',
  ink: '#ecfeff',
  muted: '#94a3b8',
  calm: '#5eead4',
  healthy: '#34d399',
  attention: '#fbbf24',
  critical: '#fb7185',
  corridor: '#0b1d18',
  panel: '#0f1f1a',
  panelBorder: '#21443d',
  agent: '#f8fafc',
  agentShadow: '#081411',
  effects: '#99f6e4',
} as const;

export const OPERATIONS_RENDER_GROUP_COLORS = {
  intake: '#38bdf8',
  processing: '#f59e0b',
  review: '#fb7185',
  publishing: '#4ade80',
  wall: '#22d3ee',
  system: '#f97316',
} as const;
