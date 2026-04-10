import { describe, it } from 'vitest';

describe('workspace selector contracts', () => {
  it.todo('shows event cards grouped by partner when the same DJ has four event-scoped accesses');
  it.todo('opens an event-scoped dashboard with only the actions allowed by that event role');
  it.todo('does not render organization-wide navigation for a user with only event-scoped accesses');
  it.todo('uses plain-language role summaries such as Operar evento Moderar mídias and Ver mídias instead of raw permission names');
  it.todo('surfaces the partner name and event date prominently so a lay user can choose the correct event safely');
  it.todo('lets a multi-organization staff user switch active organization explicitly');
  it.todo('keeps cache keys separated between organization context and event context');
  it.todo('redirects an event-only user with one event directly to that event workspace');
  it.todo('redirects an event-only user with multiple events to /my-events');
  it.todo('renders organization workspaces and event workspaces in separated groups when both exist');
});
