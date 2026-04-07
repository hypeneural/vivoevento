import { AlertTriangle, Bot } from 'lucide-react';

import { Badge } from '@/components/ui/badge';

import { describeTelegramOperationalSignal, maskTelegramExternalId } from '../telegram-operational';
import type { EventTelegramOperationalStatus } from '../types';

interface TelegramOperationalStatusCardProps {
  status: EventTelegramOperationalStatus | null;
  loading: boolean;
  isError: boolean;
}

function formatDateTime(value?: string | null) {
  if (!value) {
    return 'Agora';
  }

  return new Date(value).toLocaleString('pt-BR');
}

export function TelegramOperationalStatusCard({
  status,
  loading,
  isError,
}: TelegramOperationalStatusCardProps) {
  return (
    <div className="mt-4 rounded-2xl border border-border/60 bg-background/70 p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-sm font-semibold">Status operacional do bot</p>
          <p className="mt-1 text-xs text-muted-foreground">
            Snapshot ao vivo do `getMe`, `getWebhookInfo` e sinais `my_chat_member` associados a este canal.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Badge variant={status?.healthy ? 'default' : 'outline'}>
            {loading
              ? 'Verificando'
              : status?.healthy
                ? 'Saudavel'
                : status?.configured
                  ? 'Atencao'
                  : 'Nao configurado'}
          </Badge>
          <Badge variant={status?.webhook.ok ? 'outline' : 'secondary'}>
            {status?.webhook.ok ? 'Webhook ativo' : 'Webhook pendente'}
          </Badge>
        </div>
      </div>

      {isError ? (
        <div className="mt-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-700">
          Nao foi possivel consultar o status operacional do Telegram agora.
        </div>
      ) : null}

      {!isError && status ? (
        <div className="mt-4 space-y-4">
          <div className="grid gap-3 md:grid-cols-2">
            <div className="rounded-2xl border border-border/60 bg-muted/20 p-3">
              <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Bot</p>
              <p className="mt-2 flex items-center gap-2 text-sm font-medium text-foreground">
                <Bot className="h-4 w-4 text-primary" />
                {status.bot.username
                  ? `@${status.bot.username}`
                  : 'Username indisponivel'}
              </p>
              <p className="mt-1 text-xs text-muted-foreground">
                ID {status.bot.id ?? 'indisponivel'} · grupos {status.bot.can_join_groups ? 'permitidos' : 'desligados'}
              </p>
            </div>

            <div className="rounded-2xl border border-border/60 bg-muted/20 p-3">
              <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Webhook</p>
              <p className="mt-2 break-all text-sm font-medium text-foreground">
                {status.webhook.url ?? 'Nao registrado'}
              </p>
              <p className="mt-1 text-xs text-muted-foreground">
                Pendentes {status.webhook.pending_update_count} · atualizacoes {status.webhook.allowed_updates.join(', ') || 'nenhuma'}
              </p>
            </div>
          </div>

          <div className="grid gap-3 md:grid-cols-2">
            <div className="rounded-2xl border border-border/60 bg-muted/20 p-3 text-xs text-muted-foreground">
              <p>
                Esperado: {status.webhook.expected_allowed_updates.join(', ') || 'nenhum'}
              </p>
              <p className="mt-1">
                Status: {status.webhook.matches_expected_allowed_updates ? 'subscriptions alinhadas' : 'subscriptions divergentes'}
              </p>
            </div>

            <div className="rounded-2xl border border-border/60 bg-muted/20 p-3 text-xs text-muted-foreground">
              <p>
                Ultimo erro: {status.webhook.last_error_message ?? 'sem erro reportado'}
              </p>
              <p className="mt-1">
                Atualizado em: {formatDateTime(status.webhook.last_error_at)}
              </p>
            </div>
          </div>

          {status.error_message ? (
            <div className="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-700">
              <div className="flex items-start gap-2">
                <AlertTriangle className="mt-0.5 h-4 w-4" />
                <p>{status.error_message}</p>
              </div>
            </div>
          ) : null}

          <div className="rounded-2xl border border-border/60 bg-muted/20 p-3">
            <p className="text-sm font-medium text-foreground">Sinais operacionais recentes</p>
            {status.recent_operational_signals.length === 0 ? (
              <p className="mt-2 text-xs text-muted-foreground">
                Nenhum `my_chat_member` associado a este canal ainda.
              </p>
            ) : (
              <div className="mt-3 space-y-2">
                {status.recent_operational_signals.slice(0, 4).map((signal) => (
                  <div key={signal.id} className="rounded-2xl border border-border/60 bg-background/80 p-3 text-xs text-muted-foreground">
                    <p className="font-medium text-foreground">{describeTelegramOperationalSignal(signal.signal)}</p>
                    <p className="mt-1">
                      Chat {maskTelegramExternalId(signal.chat_external_id)} · {signal.sender_name ?? 'usuario sem nome'}
                    </p>
                    <p className="mt-1">
                      {signal.old_status ?? 'desconhecido'} → {signal.new_status ?? 'desconhecido'} · {formatDateTime(signal.occurred_at ?? signal.created_at)}
                    </p>
                  </div>
                ))}
              </div>
            )}
            <p className="mt-3 text-xs text-muted-foreground">
              Esses sinais indicam apenas que o usuario bloqueou ou desbloqueou o bot. Eles nao alteram a blacklist do evento.
            </p>
          </div>
        </div>
      ) : null}
    </div>
  );
}
