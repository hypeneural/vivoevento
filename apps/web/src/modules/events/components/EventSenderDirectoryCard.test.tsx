import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';

import type { EventIntakeBlacklistSenderSummary } from '../intake';
import { EventSenderDirectoryCard } from './EventSenderDirectoryCard';

const sender: EventIntakeBlacklistSenderSummary = {
  sender_external_id: '11111111111111@lid',
  sender_phone: '554899999999',
  sender_lid: '11111111111111@lid',
  sender_name: 'Ana Martins',
  sender_avatar_url: 'https://cdn.eventovivo.test/ana.jpg',
  inbound_count: 3,
  media_count: 2,
  last_seen_at: '2026-04-06T18:00:00.000Z',
  recommended_identity_type: 'lid',
  recommended_identity_value: '11111111111111@lid',
  recommended_normalized_phone: null,
  blocked: true,
  blocking_entry_id: 9,
  blocking_expires_at: '2026-04-07T18:00:00.000Z',
  blocking_reason: 'Bloqueado',
};

describe('EventSenderDirectoryCard', () => {
  it('renders sender shortcuts to moderation and gallery', () => {
    render(
      <MemoryRouter>
        <EventSenderDirectoryCard eventId={42} senders={[sender]} canManageBlacklist={false} />
      </MemoryRouter>,
    );

    expect(screen.getByText('Ana Martins')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /moderacao/i })).toHaveAttribute(
      'href',
      '/moderation?event_id=42&search=11111111111111%40lid',
    );
    expect(screen.getByRole('link', { name: /galeria/i })).toHaveAttribute(
      'href',
      '/gallery?event_id=42&search=11111111111111%40lid',
    );
  });

  it('propagates quick block toggles with the selected duration', () => {
    const onToggleBlock = vi.fn();

    render(
      <MemoryRouter>
        <EventSenderDirectoryCard eventId={42} senders={[{ ...sender, blocked: false }]} canManageBlacklist onToggleBlock={onToggleBlock} />
      </MemoryRouter>,
    );

    fireEvent.click(screen.getByRole('switch'));

    expect(onToggleBlock).toHaveBeenCalledWith(
      expect.objectContaining({ sender_external_id: '11111111111111@lid' }),
      true,
      '7d',
    );
  });
});
