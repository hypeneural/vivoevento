import { describe, it } from 'vitest';

describe('moderation event scope contracts', () => {
  it.todo('subscribes to event-scoped moderation channels for event-limited users instead of organization-wide channels');
  it.todo('hides unrelated events from event-scoped users in moderation filters');
  it.todo('prevents bulk moderation actions from spanning media outside the allowed event scope');
});
