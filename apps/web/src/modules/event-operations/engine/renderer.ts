import { OPERATIONS_RENDER_GROUP_COLORS, OPERATIONS_SCENE_PALETTE } from './assets';
import type { OperationsSceneRuntime } from './scene-runtime';
import { OPERATIONS_ROLE_SPRITES, OPERATIONS_STATION_SPRITES } from './sprites';

function resolveHealthColor(status: OperationsSceneRuntime['stations'][number]['health']) {
  switch (status) {
    case 'healthy':
      return OPERATIONS_SCENE_PALETTE.healthy;
    case 'attention':
      return OPERATIONS_SCENE_PALETTE.attention;
    case 'risk':
    case 'offline':
      return OPERATIONS_SCENE_PALETTE.critical;
    default:
      return OPERATIONS_SCENE_PALETTE.muted;
  }
}

export function renderOperationsScene(
  context: CanvasRenderingContext2D,
  runtime: OperationsSceneRuntime,
) {
  const { width, height } = runtime.size;

  context.save();
  context.imageSmoothingEnabled = false;
  context.clearRect(0, 0, width, height);

  drawBackgroundLayer(context, width, height);
  drawStationsLayer(context, runtime);
  drawAgentsLayer(context, runtime);
  drawEffectsLayer(context, runtime);

  context.restore();
}

function drawBackgroundLayer(
  context: CanvasRenderingContext2D,
  width: number,
  height: number,
) {
  context.fillStyle = OPERATIONS_SCENE_PALETTE.night;
  context.fillRect(0, 0, width, height);

  context.fillStyle = OPERATIONS_SCENE_PALETTE.floor;
  context.fillRect(20, 44, width - 40, height - 88);

  context.fillStyle = OPERATIONS_SCENE_PALETTE.corridor;
  context.fillRect(20, 242, width - 40, 56);

  context.strokeStyle = OPERATIONS_SCENE_PALETTE.tile;
  context.lineWidth = 1;

  for (let x = 20; x <= width - 20; x += 40) {
    context.beginPath();
    context.moveTo(x, 44);
    context.lineTo(x, height - 44);
    context.stroke();
  }

  for (let y = 44; y <= height - 44; y += 40) {
    context.beginPath();
    context.moveTo(20, y);
    context.lineTo(width - 20, y);
    context.stroke();
  }
}

function drawStationsLayer(
  context: CanvasRenderingContext2D,
  runtime: OperationsSceneRuntime,
) {
  context.textBaseline = 'top';
  context.font = '12px monospace';

  for (const station of runtime.stations) {
    const sprite = OPERATIONS_STATION_SPRITES[station.station_key];
    const outline = resolveHealthColor(station.health);
    const glow =
      station.emphasis === 'dominant'
        ? outline
        : station.emphasis === 'active'
          ? OPERATIONS_RENDER_GROUP_COLORS[station.station_key === 'alerts' ? 'system' : station.station_key === 'wall' ? 'wall' : station.station_key === 'gallery' ? 'publishing' : station.station_key === 'human_review' || station.station_key === 'safety' || station.station_key === 'intelligence' ? 'review' : station.station_key === 'download' || station.station_key === 'variants' ? 'processing' : 'intake']
          : OPERATIONS_SCENE_PALETTE.panelBorder;

    context.fillStyle = sprite.fill;
    context.fillRect(station.x, station.y, sprite.width, sprite.height);

    context.strokeStyle = glow;
    context.lineWidth = station.emphasis === 'dominant' ? 4 : 2;
    context.strokeRect(station.x, station.y, sprite.width, sprite.height);

    context.fillStyle = sprite.accent;
    context.fillRect(station.x + 10, station.y + 10, sprite.width - 20, 10);

    context.fillStyle = OPERATIONS_SCENE_PALETTE.ink;
    context.fillText(station.label, station.x + 10, station.y + 28);

    context.fillStyle = OPERATIONS_SCENE_PALETTE.muted;
    context.fillText(`fila ${station.queue_depth}`, station.x + 10, station.y + 46);

    const queueBarWidth = Math.max(8, Math.min(sprite.width - 24, station.queue_depth * 8));
    context.fillStyle = outline;
    context.fillRect(station.x + 10, station.y + sprite.height - 16, queueBarWidth, 6);
  }
}

function drawAgentsLayer(
  context: CanvasRenderingContext2D,
  runtime: OperationsSceneRuntime,
) {
  for (const agent of runtime.agents) {
    const sprite = OPERATIONS_ROLE_SPRITES[agent.role];
    const moodColor =
      agent.mood === 'critical'
        ? OPERATIONS_SCENE_PALETTE.critical
        : agent.mood === 'warning'
          ? OPERATIONS_SCENE_PALETTE.attention
          : agent.mood === 'busy'
            ? sprite.accent
            : OPERATIONS_SCENE_PALETTE.calm;

    context.fillStyle = OPERATIONS_SCENE_PALETTE.agentShadow;
    context.fillRect(agent.x - 8, agent.y + 10, sprite.width, 8);

    context.fillStyle = sprite.fill;
    context.fillRect(agent.x - 6, agent.y - 10, sprite.width - 4, sprite.height - 6);

    context.fillStyle = moodColor;
    context.fillRect(agent.x - 8, agent.y - 14, 10, 10);
    context.fillRect(agent.x + 6, agent.y - 14, 4, 4);
  }
}

function drawEffectsLayer(
  context: CanvasRenderingContext2D,
  runtime: OperationsSceneRuntime,
) {
  for (const effect of runtime.effects) {
    const station = runtime.stations.find((item) => item.station_key === effect.station_key);

    if (!station) {
      continue;
    }

    const color =
      effect.severity === 'critical'
        ? OPERATIONS_SCENE_PALETTE.critical
        : effect.severity === 'warning'
          ? OPERATIONS_SCENE_PALETTE.attention
          : OPERATIONS_SCENE_PALETTE.effects;

    context.strokeStyle = color;
    context.lineWidth = 3;
    context.strokeRect(station.x - 4, station.y - 4, station.width + 8, station.height + 8);

    context.fillStyle = color;
    context.fillRect(station.x + station.width - 22, station.y - 12, 16, 16);
  }
}
