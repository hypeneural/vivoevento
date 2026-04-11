import type {
  EventJourneyBranch,
  EventJourneyBranchStatus,
  EventJourneyNode,
  EventJourneyNodeStatus,
  EventJourneyProjection,
  EventJourneyStage,
  EventJourneyStageId,
} from './types';

export interface JourneyGraphStageBand {
  id: EventJourneyStageId;
  label: string;
  description: string;
  y: number;
  height: number;
  className: string;
}

export interface JourneyGraphNode {
  id: string;
  stage: EventJourneyStageId;
  kind: EventJourneyNode['kind'];
  status: EventJourneyNodeStatus;
  position: {
    x: number;
    y: number;
  };
  width: number;
  height: number;
  className: string;
  targetHandle: 'inbound';
  sourceHandles: string[];
  data: {
    node: EventJourneyNode;
    stage: Pick<EventJourneyStage, 'id' | 'label' | 'description'>;
  };
}

export interface JourneyGraphEdge {
  id: string;
  source: string;
  target: string;
  sourceHandle: string;
  targetHandle: 'inbound';
  label: string;
  className: string;
  data: {
    branch: EventJourneyBranch;
    source_stage: EventJourneyStageId;
    target_stage: EventJourneyStageId | null;
  };
}

export interface JourneyGraph {
  stages: JourneyGraphStageBand[];
  nodes: JourneyGraphNode[];
  edges: JourneyGraphEdge[];
}

const STAGE_HEIGHT = 252;
const STAGE_VERTICAL_SPACING = 272;
const NODE_ROW_GAP = 136;
const NODE_WIDTH = 248;
const NODE_HEIGHT = 132;
const DEFAULT_STAGE_COLUMNS = [80, 360, 640, 920] as const;

const NODE_LAYOUT: Record<string, { x: number; row: 0 | 1 }> = {
  entry_whatsapp_direct: { x: 60, row: 0 },
  entry_whatsapp_groups: { x: 340, row: 0 },
  entry_telegram: { x: 620, row: 0 },
  entry_public_upload: { x: 900, row: 0 },
  entry_sender_blacklist: { x: 480, row: 1 },
  processing_receive_feedback: { x: 120, row: 0 },
  processing_download_media: { x: 400, row: 0 },
  processing_prepare_variants: { x: 680, row: 0 },
  processing_safety_ai: { x: 250, row: 1 },
  processing_media_intelligence: { x: 570, row: 1 },
  decision_event_moderation_mode: { x: 100, row: 0 },
  decision_safety_result: { x: 400, row: 0 },
  decision_context_gate: { x: 700, row: 0 },
  decision_media_type: { x: 250, row: 1 },
  decision_caption_presence: { x: 570, row: 1 },
  output_reaction_final: { x: 40, row: 0 },
  output_reply_text: { x: 320, row: 0 },
  output_gallery: { x: 600, row: 0 },
  output_wall: { x: 880, row: 0 },
  output_print: { x: 320, row: 1 },
  output_silence: { x: 600, row: 1 },
};

const STAGE_CLASSNAME_BY_ID: Record<EventJourneyStageId, string> = {
  entry: 'journey-stage journey-stage--entry',
  processing: 'journey-stage journey-stage--processing',
  decision: 'journey-stage journey-stage--decision',
  output: 'journey-stage journey-stage--output',
};

function nodeClassName(node: EventJourneyNode) {
  return [
    'journey-node',
    `journey-node--${node.kind}`,
    `journey-node--${node.stage}`,
    `status-${node.status}`,
    node.active ? 'is-active' : 'is-inactive',
    node.editable ? 'is-editable' : 'is-readonly',
  ].join(' ');
}

function edgeClassName(status: EventJourneyBranchStatus, active: boolean) {
  return [
    'journey-edge',
    `status-${status}`,
    active ? 'is-active' : 'is-inactive',
  ].join(' ');
}

function stageY(index: number) {
  return index * STAGE_VERTICAL_SPACING;
}

function fallbackNodePosition(stageIndex: number, index: number) {
  const column = DEFAULT_STAGE_COLUMNS[index % DEFAULT_STAGE_COLUMNS.length];
  const row = Math.floor(index / DEFAULT_STAGE_COLUMNS.length);

  return {
    x: column,
    y: stageY(stageIndex) + (row * NODE_ROW_GAP),
  };
}

function stageBand(stage: EventJourneyStage, stageIndex: number): JourneyGraphStageBand {
  return {
    id: stage.id,
    label: stage.label,
    description: stage.description,
    y: stageY(stageIndex),
    height: STAGE_HEIGHT,
    className: STAGE_CLASSNAME_BY_ID[stage.id],
  };
}

function graphNode(stage: EventJourneyStage, stageIndex: number, node: EventJourneyNode, index: number): JourneyGraphNode {
  const knownLayout = NODE_LAYOUT[node.id];
  const position = knownLayout
    ? { x: knownLayout.x, y: stageY(stageIndex) + (knownLayout.row * NODE_ROW_GAP) }
    : fallbackNodePosition(stageIndex, index);

  return {
    id: node.id,
    stage: node.stage,
    kind: node.kind,
    status: node.status,
    position,
    width: NODE_WIDTH,
    height: NODE_HEIGHT,
    className: nodeClassName(node),
    targetHandle: 'inbound',
    sourceHandles: node.branches.map((branch) => `branch:${branch.id}`),
    data: {
      node,
      stage: {
        id: stage.id,
        label: stage.label,
        description: stage.description,
      },
    },
  };
}

function graphEdge(
  sourceNode: EventJourneyNode,
  branch: EventJourneyBranch,
  targetStageId: EventJourneyStageId | null,
): JourneyGraphEdge | null {
  if (!branch.target_node_id) {
    return null;
  }

  return {
    id: `${sourceNode.id}:${branch.id}->${branch.target_node_id}`,
    source: sourceNode.id,
    target: branch.target_node_id,
    sourceHandle: `branch:${branch.id}`,
    targetHandle: 'inbound',
    label: branch.label,
    className: edgeClassName(branch.status, branch.active),
    data: {
      branch,
      source_stage: sourceNode.stage,
      target_stage: targetStageId,
    },
  };
}

export function buildJourneyGraph(projection: EventJourneyProjection): JourneyGraph {
  const stages = projection.stages.map((stage, stageIndex) => stageBand(stage, stageIndex));
  const nodes = projection.stages.flatMap((stage, stageIndex) =>
    stage.nodes.map((node, index) => graphNode(stage, stageIndex, node, index)),
  );
  const nodeStageById = new Map<string, EventJourneyStageId>(
    projection.stages.flatMap((stage) => stage.nodes.map((node) => [node.id, stage.id] as const)),
  );
  const edges = projection.stages.flatMap((stage) =>
    stage.nodes.flatMap((node) =>
      node.branches
        .map((branch) => graphEdge(node, branch, nodeStageById.get(branch.target_node_id ?? '') ?? null))
        .filter((edge): edge is JourneyGraphEdge => edge !== null),
    ),
  );

  return {
    stages,
    nodes,
    edges,
  };
}
