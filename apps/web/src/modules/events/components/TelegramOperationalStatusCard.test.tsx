import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import type { EventTelegramOperationalStatus } from '../types';
import { TelegramOperationalStatusCard } from './TelegramOperationalStatusCard';

const operationalStatus: EventTelegramOperationalStatus = {
  enabled: true,
  configured: true,
  healthy: true,
  error_message: null,
  channel: {
    id: 12,
    status: 'active',
    bot_username: 'eventovivoBot',
    media_inbox_code: 'TGTEST406',
    session_ttl_minutes: 180,
    allow_private: true,
    v1_allowed_updates: ['message', 'my_chat_member'],
  },
  bot: {
    ok: true,
    id: '9007199254740991',
    username: 'eventovivoBot',
    is_bot: true,
    can_join_groups: false,
    can_read_all_group_messages: false,
  },
  webhook: {
    ok: true,
    url: 'https://webhooks-local.eventovivo.com.br/api/v1/webhooks/telegram',
    pending_update_count: 0,
    has_custom_certificate: false,
    ip_address: '149.154.167.220',
    last_error_at: '2026-04-06T20:00:00.000Z',
    last_error_message: null,
    max_connections: 40,
    allowed_updates: ['message', 'my_chat_member'],
    expected_allowed_updates: ['message', 'my_chat_member'],
    matches_expected_allowed_updates: true,
  },
  recent_operational_signals: [
    {
      id: 1,
      provider_update_id: '99001',
      chat_external_id: '9007199254740991',
      sender_external_id: '9007199254740991',
      sender_name: 'Ana Martins',
      signal: 'bot_blocked_by_user',
      old_status: 'member',
      new_status: 'kicked',
      occurred_at: '2026-04-06T20:01:00.000Z',
      created_at: '2026-04-06T20:01:01.000Z',
    },
  ],
};

describe('TelegramOperationalStatusCard', () => {
  it('renders webhook health and operational my_chat_member signals without mixing them with blacklist messaging', () => {
    render(
      <TelegramOperationalStatusCard
        status={operationalStatus}
        loading={false}
        isError={false}
      />,
    );

    expect(screen.getByText('Status operacional do bot')).toBeInTheDocument();
    expect(screen.getByText('@eventovivoBot')).toBeInTheDocument();
    expect(screen.getByText(/Webhook ativo/i)).toBeInTheDocument();
    expect(screen.getByText(/Bot bloqueado pelo usuario/i)).toBeInTheDocument();
    expect(screen.getByText(/Chat 9007...0991 · Ana Martins/i)).toBeInTheDocument();
    expect(screen.getByText(/Esses sinais indicam apenas que o usuario bloqueou ou desbloqueou o bot/i)).toBeInTheDocument();
  });

  it('renders error fallback when the operational query fails', () => {
    render(
      <TelegramOperationalStatusCard
        status={null}
        loading={false}
        isError
      />,
    );

    expect(screen.getByText(/Nao foi possivel consultar o status operacional do Telegram agora/i)).toBeInTheDocument();
    expect(screen.getByText(/Nao configurado/i)).toBeInTheDocument();
  });
});
