import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { QrCode, RefreshCcw, Smartphone, Loader2, Unplug, ShieldCheck } from 'lucide-react';
import { QRCodeCanvas } from 'qrcode.react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useToast } from '@/hooks/use-toast';
import { queryKeys } from '@/lib/query-client';

import { whatsappService } from '../api';
import type { WhatsAppInstanceItem } from '../types';

function translateStatusMessage(message?: string | null) {
  if (!message) return null;

  const normalized = message.toLowerCase();

  if (normalized.includes('you are not connected')) {
    return 'WhatsApp desconectado no momento.';
  }

  if (normalized.includes('restore the session')) {
    return 'A sessao precisa ser restaurada antes de gerar uma nova conexao.';
  }

  if (normalized.includes('invalid') || normalized.includes('credential')) {
    return 'As credenciais informadas foram rejeitadas pelo provider.';
  }

  return message;
}

function formatDateTime(value?: string | null) {
  if (!value) return 'Sem registro';

  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

interface WhatsAppConnectionPanelProps {
  instance: WhatsAppInstanceItem;
}

export function WhatsAppConnectionPanel({ instance }: WhatsAppConnectionPanelProps) {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const [phoneForPairing, setPhoneForPairing] = useState(instance.phone_number ?? '');

  const connectionQuery = useQuery({
    queryKey: queryKeys.whatsapp.connection(instance.id),
    queryFn: () => whatsappService.getConnectionState(instance.id),
    refetchInterval: (query) => {
      return query.state.data?.connected ? false : 10000;
    },
    staleTime: 0,
  });

  const invalidateInstanceState = async () => {
    await queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.detail(instance.id) });
    await queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.connection(instance.id) });
    await queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.lists() });
  };

  const testMutation = useMutation({
    mutationFn: () => whatsappService.testConnection(instance.id),
    onSuccess: async (result) => {
      await invalidateInstanceState();
      toast({
        title: result.connected ? 'Instancia conectada' : 'Teste concluido',
        description: result.message || 'A verificacao da instancia terminou.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao testar conexao',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const disconnectMutation = useMutation({
    mutationFn: () => whatsappService.disconnect(instance.id),
    onSuccess: async (result) => {
      await invalidateInstanceState();
      toast({
        title: 'Instancia desconectada',
        description: result.message || 'A sessao foi encerrada no provider.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao desconectar',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const phoneCodeMutation = useMutation({
    mutationFn: () => whatsappService.requestPhoneCode(instance.id, phoneForPairing),
    onSuccess: (result) => {
      toast({
        title: 'Codigo de pareamento gerado',
        description: result.message || 'Use o codigo retornado no fluxo de conexao por telefone do WhatsApp.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao gerar codigo',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const state = connectionQuery.data;
  const busy = testMutation.isPending || disconnectMutation.isPending || phoneCodeMutation.isPending;

  return (
    <section className="space-y-4">
      <div className="flex flex-col gap-3 rounded-3xl border border-border/60 bg-background/70 p-5">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <div className="flex items-center gap-2">
              <h2 className="text-lg font-semibold">Conexao da instancia</h2>
              {state ? (
                <Badge variant="outline" className={state.connected ? 'bg-success/15 text-success border-success/20' : 'bg-destructive/10 text-destructive border-destructive/20'}>
                  {state.connected ? 'Conectada' : 'Desconectada'}
                </Badge>
              ) : null}
            </div>
            <p className="mt-1 text-sm text-muted-foreground">
              Polling automatico a cada 10 segundos quando a sessao estiver desconectada.
            </p>
          </div>

          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={() => connectionQuery.refetch()} disabled={connectionQuery.isFetching}>
              {connectionQuery.isFetching ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCcw className="h-4 w-4" />}
              Atualizar agora
            </Button>
            <Button variant="outline" onClick={() => testMutation.mutate()} disabled={busy}>
              <ShieldCheck className="h-4 w-4" />
              Testar conexao
            </Button>
            <Button variant="outline" onClick={() => disconnectMutation.mutate()} disabled={busy}>
              <Unplug className="h-4 w-4" />
              Desconectar
            </Button>
          </div>
        </div>

        {connectionQuery.isLoading ? (
          <div className="flex items-center gap-2 rounded-2xl border border-border/60 px-4 py-8 text-sm text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" />
            Consultando o provider...
          </div>
        ) : connectionQuery.isError ? (
          <Alert variant="destructive">
            <AlertTitle>Falha ao consultar a instancia</AlertTitle>
            <AlertDescription>
              Nao foi possivel buscar o estado atual da conexao.
            </AlertDescription>
          </Alert>
        ) : state ? (
          <div className="space-y-4">
            {translateStatusMessage(state.status_message) ? (
              <Alert variant={state.connected ? 'default' : 'destructive'}>
                <AlertTitle>{state.connected ? 'Status da sessao' : 'Sessao pendente de conexao'}</AlertTitle>
                <AlertDescription>{translateStatusMessage(state.status_message)}</AlertDescription>
              </Alert>
            ) : null}

            {state.connected ? (
              <div className="grid gap-4 lg:grid-cols-[1.4fr_0.8fr]">
                <div className="rounded-3xl border border-success/20 bg-success/5 p-5">
                  <div className="flex items-start gap-4">
                    {state.profile.img_url ? (
                      <img src={state.profile.img_url} alt={state.profile.name || 'Perfil'} className="h-16 w-16 rounded-2xl object-cover" />
                    ) : (
                      <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-success/10 text-success">
                        <Smartphone className="h-7 w-7" />
                      </div>
                    )}

                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-base font-semibold">{state.profile.name || instance.name}</h3>
                        <Badge variant="outline" className={state.smartphone_connected ? 'bg-success/15 text-success border-success/20' : 'bg-warning/10 text-warning border-warning/20'}>
                          {state.smartphone_connected ? 'Smartphone online' : 'Smartphone offline'}
                        </Badge>
                      </div>
                      <p className="mt-1 text-sm text-muted-foreground">{state.profile.about || 'Sem recado configurado.'}</p>

                      <div className="mt-4 grid gap-3 md:grid-cols-2">
                        <div className="rounded-2xl border border-border/60 bg-background/80 p-3">
                          <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Telefone</p>
                          <p className="mt-1 font-medium">{state.formatted_phone || state.phone || 'Nao informado'}</p>
                        </div>
                        <div className="rounded-2xl border border-border/60 bg-background/80 p-3">
                          <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">LID</p>
                          <p className="mt-1 font-medium">{state.profile.lid || 'Nao informado'}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="rounded-3xl border border-border/60 bg-background/70 p-5">
                  <h3 className="text-sm font-semibold">Dispositivo</h3>
                  <div className="mt-4 space-y-3 text-sm">
                    <div>
                      <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Sessao</p>
                      <p className="mt-1 font-medium">{state.device.session_name || 'Nao informado'}</p>
                    </div>
                    <div>
                      <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Modelo</p>
                      <p className="mt-1 font-medium">{state.device.device_model || 'Nao informado'}</p>
                    </div>
                    <div>
                      <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Origem</p>
                      <p className="mt-1 font-medium">{state.device.original_device || 'Nao informado'}</p>
                    </div>
                    <div>
                      <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Ultima checagem</p>
                      <p className="mt-1 font-medium">{formatDateTime(state.checked_at)}</p>
                    </div>
                  </div>
                </div>
              </div>
            ) : (
              <div className="grid gap-4 lg:grid-cols-[1fr_1.2fr]">
                <div className="rounded-3xl border border-border/60 bg-background/70 p-5">
                  <h3 className="text-sm font-semibold">Reconectar a instancia</h3>
                  <ol className="mt-4 space-y-3 text-sm text-muted-foreground">
                    <li>1. Abra o WhatsApp no aparelho que vai autenticar a sessao.</li>
                    <li>2. Leia o QR Code ou use o pareamento por telefone.</li>
                    <li>3. Aguarde a proxima checagem para o painel marcar a instancia como conectada.</li>
                  </ol>

                  <div className="mt-5 space-y-3 rounded-2xl border border-border/60 bg-background/90 p-4">
                    <label className="text-sm font-medium">Pareamento por telefone</label>
                    <Input
                      value={phoneForPairing}
                      onChange={(event) => setPhoneForPairing(event.target.value)}
                      placeholder="5511999999999"
                    />
                    <Button
                      variant="outline"
                      className="w-full"
                      onClick={() => phoneCodeMutation.mutate()}
                      disabled={!phoneForPairing || busy}
                    >
                      {phoneCodeMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Smartphone className="h-4 w-4" />}
                      Gerar codigo por telefone
                    </Button>
                  </div>
                </div>

                <div className="rounded-3xl border border-warning/30 bg-warning/5 p-5">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <h3 className="text-sm font-semibold">QR Code de conexao</h3>
                      <p className="mt-1 text-xs text-muted-foreground">
                        {state.qr_available
                          ? `Expira em cerca de ${state.qr_expires_in_sec ?? 20}s.`
                          : 'Quando o provider disponibilizar um novo QR, ele aparece aqui.'}
                      </p>
                    </div>
                    <Badge variant="outline" className="bg-warning/15 text-warning border-warning/20">
                      {instance.provider.label}
                    </Badge>
                  </div>

                  <div className="mt-5 flex min-h-[320px] items-center justify-center rounded-3xl border border-dashed border-border/70 bg-background/95 p-6">
                    {state.qr_available && state.qr_code ? (
                      state.qr_render_mode === 'image' ? (
                        <img src={state.qr_code} alt="QR Code da instancia" className="max-h-[280px] rounded-2xl border border-border/60 bg-white p-4" />
                      ) : (
                        <div className="rounded-2xl bg-white p-4">
                          <QRCodeCanvas value={state.qr_code} size={240} level="M" includeMargin />
                        </div>
                      )
                    ) : (
                      <div className="text-center text-sm text-muted-foreground">
                        <QrCode className="mx-auto mb-3 h-10 w-10 text-muted-foreground/60" />
                        <p>{state.qr_error || 'QR Code indisponivel no momento. Atualize ou teste a conexao novamente.'}</p>
                      </div>
                    )}
                  </div>

                  {state.qr_error ? (
                    <p className="mt-4 text-sm text-destructive">{state.qr_error}</p>
                  ) : null}
                </div>
              </div>
            )}
          </div>
        ) : null}
      </div>
    </section>
  );
}
