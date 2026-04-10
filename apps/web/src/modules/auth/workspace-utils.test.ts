import { describe, expect, it } from 'vitest';

import type { MeEventAccessWorkspace } from '@/lib/api-types';

import {
  eventWorkspaceActions,
  filterEventWorkspaces,
  groupEventWorkspacesByPartner,
} from './workspace-utils';

const baseWorkspace = (overrides: Partial<MeEventAccessWorkspace>): MeEventAccessWorkspace => ({
  event_id: 1,
  event_uuid: 'evt-1',
  event_title: 'Casamento Ana e Joao',
  event_slug: 'casamento-ana-joao',
  event_date: '2026-06-10',
  event_status: 'active',
  organization_id: 10,
  organization_name: 'Cerimonial Aurora',
  organization_slug: 'cerimonial-aurora',
  role_key: 'event.operator',
  role_label: 'Operar evento',
  persisted_role: 'operator',
  capabilities: ['overview', 'media', 'moderation', 'wall', 'play'],
  entry_path: '/my-events/1',
  ...overrides,
});

describe('workspace-utils', () => {
  it('groups event accesses by partner organization', () => {
    const grouped = groupEventWorkspacesByPartner([
      baseWorkspace({ event_id: 1, organization_name: 'Cerimonial Aurora' }),
      baseWorkspace({ event_id: 2, organization_name: 'Bella Assessoria' }),
      baseWorkspace({ event_id: 3, organization_name: 'Cerimonial Aurora' }),
    ]);

    expect(grouped).toHaveLength(2);
    expect(grouped[0]?.organizationName).toBe('Bella Assessoria');
    expect(grouped[1]?.organizationName).toBe('Cerimonial Aurora');
    expect(grouped[1]?.items).toHaveLength(2);
  });

  it('filters workspaces by search capability and tab', () => {
    const filtered = filterEventWorkspaces(
      [
        baseWorkspace({ event_id: 1, event_title: 'Casamento Ana e Joao', event_status: 'active' }),
        baseWorkspace({
          event_id: 2,
          event_title: 'Festa Sofia',
          organization_name: 'Luz Cerimonial',
          event_status: 'draft',
          capabilities: ['overview', 'media'],
        }),
        baseWorkspace({ event_id: 3, event_title: 'Corporativo Atlas', event_date: '2025-01-10', event_status: 'finished' }),
      ],
      {
        search: 'sofia',
        capability: 'media',
        partner: 'all',
        tab: 'upcoming',
        sort: 'event_title',
      },
      new Date('2026-04-10T10:00:00'),
    );

    expect(filtered).toHaveLength(1);
    expect(filtered[0]?.event_title).toBe('Festa Sofia');
  });

  it('builds safe primary actions from event capabilities', () => {
    const operatorActions = eventWorkspaceActions(
      baseWorkspace({ capabilities: ['overview', 'media', 'moderation', 'wall', 'play'] }),
    );
    const viewerActions = eventWorkspaceActions(
      baseWorkspace({
        role_key: 'event.media-viewer',
        role_label: 'Ver mídias',
        persisted_role: 'viewer',
        capabilities: ['overview', 'media'],
      }),
    );

    expect(operatorActions.map((action) => action.key)).toEqual(['moderation', 'media', 'wall', 'play']);
    expect(operatorActions[0]?.primary).toBe(true);
    expect(viewerActions.map((action) => action.key)).toEqual(['media']);
    expect(viewerActions[0]?.primary).toBe(true);
  });
});
