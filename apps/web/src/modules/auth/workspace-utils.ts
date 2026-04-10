import type { MeEventAccessWorkspace } from '@/lib/api-types';

export type MyEventsTab = 'active' | 'upcoming' | 'ended' | 'all';
export type MyEventsSort = 'event_date_asc' | 'event_date_desc' | 'partner_name' | 'event_title';

export interface MyEventsFilters {
  search: string;
  capability: string;
  partner: string;
  tab: MyEventsTab;
  sort: MyEventsSort;
}

export interface EventWorkspaceAction {
  key: 'media' | 'moderation' | 'wall' | 'play';
  label: string;
  description: string;
  to: string;
  primary?: boolean;
}

export function formatEventDate(value?: string | null): string {
  if (!value) {
    return 'Data a confirmar';
  }

  return new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

export function capabilityLabel(capability: string): string {
  return {
    overview: 'Resumo',
    media: 'Mídias',
    moderation: 'Moderação',
    wall: 'Telão',
    play: 'Jogos',
    team: 'Equipe',
  }[capability] ?? capability;
}

export function eventWorkspaceActions(workspace: MeEventAccessWorkspace): EventWorkspaceAction[] {
  const basePath = `/my-events/${workspace.event_id}`;
  const capabilities = new Set(workspace.capabilities);
  const actions: EventWorkspaceAction[] = [];

  if (capabilities.has('moderation')) {
    actions.push({
      key: 'moderation',
      label: 'Moderar mídias',
      description: 'Aprovar ou recusar mídias deste evento.',
      to: `${basePath}/moderation`,
      primary: true,
    });
  }

  if (capabilities.has('media')) {
    actions.push({
      key: 'media',
      label: 'Ver mídias',
      description: 'Acompanhar as mídias recebidas neste evento.',
      to: `${basePath}/media`,
      primary: actions.length === 0,
    });
  }

  if (capabilities.has('wall')) {
    actions.push({
      key: 'wall',
      label: 'Operar telão',
      description: 'Controlar o telão ao vivo deste evento.',
      to: `${basePath}/wall`,
    });
  }

  if (capabilities.has('play')) {
    actions.push({
      key: 'play',
      label: 'Operar jogos',
      description: 'Gerenciar os jogos liberados neste evento.',
      to: `${basePath}/play`,
    });
  }

  return actions;
}

export function eventWorkspaceBucket(workspace: MeEventAccessWorkspace, now = new Date()): Exclude<MyEventsTab, 'all'> {
  if (workspace.event_status === 'active') {
    return 'active';
  }

  if (!workspace.event_date) {
    return 'upcoming';
  }

  const eventDate = new Date(`${workspace.event_date}T00:00:00`);
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

  return eventDate < today ? 'ended' : 'upcoming';
}

export function filterEventWorkspaces(
  workspaces: MeEventAccessWorkspace[],
  filters: MyEventsFilters,
  now = new Date(),
): MeEventAccessWorkspace[] {
  const normalizedSearch = filters.search.trim().toLowerCase();

  const filtered = workspaces.filter((workspace) => {
    if (filters.tab !== 'all' && eventWorkspaceBucket(workspace, now) !== filters.tab) {
      return false;
    }

    if (filters.partner !== 'all' && workspace.organization_name !== filters.partner) {
      return false;
    }

    if (filters.capability !== 'all' && !workspace.capabilities.includes(filters.capability)) {
      return false;
    }

    if (!normalizedSearch) {
      return true;
    }

    const haystack = [
      workspace.event_title,
      workspace.organization_name,
      workspace.role_label,
    ].join(' ').toLowerCase();

    return haystack.includes(normalizedSearch);
  });

  return filtered.sort((left, right) => {
    switch (filters.sort) {
      case 'event_date_desc':
        return (parseEventDate(right.event_date) ?? 0) - (parseEventDate(left.event_date) ?? 0);
      case 'partner_name':
        return left.organization_name.localeCompare(right.organization_name, 'pt-BR');
      case 'event_title':
        return left.event_title.localeCompare(right.event_title, 'pt-BR');
      case 'event_date_asc':
      default:
        return (parseEventDate(left.event_date) ?? Number.MAX_SAFE_INTEGER) - (parseEventDate(right.event_date) ?? Number.MAX_SAFE_INTEGER);
    }
  });
}

export function groupEventWorkspacesByPartner(workspaces: MeEventAccessWorkspace[]) {
  const groups = new Map<string, MeEventAccessWorkspace[]>();

  for (const workspace of workspaces) {
    const current = groups.get(workspace.organization_name) ?? [];
    current.push(workspace);
    groups.set(workspace.organization_name, current);
  }

  return Array.from(groups.entries())
    .map(([organizationName, items]) => ({
      organizationName,
      items,
    }))
    .sort((left, right) => left.organizationName.localeCompare(right.organizationName, 'pt-BR'));
}

function parseEventDate(value?: string | null): number | null {
  if (!value) {
    return null;
  }

  return new Date(`${value}T00:00:00`).getTime();
}
