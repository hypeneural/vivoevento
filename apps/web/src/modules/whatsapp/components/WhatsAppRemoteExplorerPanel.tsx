import { useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  FolderSearch,
  Loader2,
  MessageSquareMore,
  RefreshCcw,
  Search,
  Users,
} from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { queryKeys } from '@/lib/query-client';

import { whatsappService } from '../api';
import type {
  WhatsAppInstanceItem,
  WhatsAppRemoteChat,
  WhatsAppRemoteGroup,
  WhatsAppRemoteMessage,
} from '../types';

function formatRemoteDate(value: number | string | null | undefined) {
  if (value === null || value === undefined || value === '') {
    return 'Sem registro';
  }

  const numeric = typeof value === 'string' ? Number(value) : value;
  const timestamp = Number.isFinite(numeric) ? Number(numeric) : Date.parse(String(value));

  if (!Number.isFinite(timestamp)) {
    return String(value);
  }

  const normalized = timestamp < 1_000_000_000_000 ? timestamp * 1000 : timestamp;

  return new Date(normalized).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function getGroupLabel(group: WhatsAppRemoteGroup) {
  return group.subject || group.id || 'Grupo sem nome';
}

function getChatRemoteJid(chat: WhatsAppRemoteChat) {
  const candidate = chat.remoteJid || chat.jid || chat.id;

  return typeof candidate === 'string' ? candidate : null;
}

function getChatLabel(chat: WhatsAppRemoteChat) {
  return chat.name || chat.formattedName || chat.pushName || getChatRemoteJid(chat) || 'Chat sem nome';
}

function extractMessagePreview(message: WhatsAppRemoteMessage) {
  const payload = message.message ?? {};
  const map = payload as Record<string, any>;

  if (typeof map.conversation === 'string' && map.conversation.trim() !== '') {
    return map.conversation;
  }

  if (typeof map.extendedTextMessage?.text === 'string') {
    return map.extendedTextMessage.text;
  }

  if (typeof map.imageMessage?.caption === 'string' && map.imageMessage.caption.trim() !== '') {
    return map.imageMessage.caption;
  }

  if (typeof map.videoMessage?.caption === 'string' && map.videoMessage.caption.trim() !== '') {
    return map.videoMessage.caption;
  }

  if (typeof map.documentMessage?.caption === 'string' && map.documentMessage.caption.trim() !== '') {
    return map.documentMessage.caption;
  }

  if (typeof map.reactionMessage?.text === 'string' && map.reactionMessage.text.trim() !== '') {
    return `Reacao: ${map.reactionMessage.text}`;
  }

  if (map.audioMessage) {
    return 'Audio';
  }

  if (map.stickerMessage) {
    return 'Sticker';
  }

  if (map.imageMessage) {
    return 'Imagem';
  }

  if (map.videoMessage) {
    return 'Video';
  }

  if (map.documentMessage) {
    return 'Documento';
  }

  return 'Mensagem sem preview textual';
}

interface WhatsAppRemoteExplorerPanelProps {
  instance: WhatsAppInstanceItem;
}

export function WhatsAppRemoteExplorerPanel({ instance }: WhatsAppRemoteExplorerPanelProps) {
  const [groupSearch, setGroupSearch] = useState('');
  const [selectedGroupId, setSelectedGroupId] = useState<string | null>(null);
  const [chatSearch, setChatSearch] = useState('');
  const [remoteJidInput, setRemoteJidInput] = useState('');
  const [activeRemoteJid, setActiveRemoteJid] = useState<string | null>(null);

  const evolutionEnabled = instance.provider_key === 'evolution';

  const groupsQuery = useQuery({
    queryKey: queryKeys.whatsapp.remoteGroups(instance.id, false),
    queryFn: () => whatsappService.getRemoteGroups(instance.id, false),
    enabled: evolutionEnabled,
  });

  const participantsQuery = useQuery({
    queryKey: queryKeys.whatsapp.remoteParticipants(instance.id, selectedGroupId ?? 'none'),
    queryFn: () => whatsappService.getRemoteGroupParticipants(instance.id, selectedGroupId as string),
    enabled: evolutionEnabled && !!selectedGroupId,
  });

  const invitationQuery = useQuery({
    queryKey: queryKeys.whatsapp.invitation(instance.id, selectedGroupId ?? 'none'),
    queryFn: () => whatsappService.getInvitationLink(instance.id, selectedGroupId as string),
    enabled: evolutionEnabled && !!selectedGroupId,
  });

  const chatsQuery = useQuery({
    queryKey: queryKeys.whatsapp.remoteChats(instance.id, 80),
    queryFn: () => whatsappService.getRemoteChats(instance.id, 80),
    enabled: evolutionEnabled,
  });

  const messagesQuery = useQuery({
    queryKey: queryKeys.whatsapp.remoteMessages(instance.id, activeRemoteJid ?? 'none', 30),
    queryFn: () => whatsappService.findRemoteMessages(instance.id, activeRemoteJid as string, { limit: 30 }),
    enabled: evolutionEnabled && !!activeRemoteJid,
  });

  const groups = groupsQuery.data?.groups ?? [];
  const chats = chatsQuery.data?.chats ?? [];
  const selectedGroup = useMemo(
    () => groups.find((group) => group.id === selectedGroupId) ?? null,
    [groups, selectedGroupId],
  );

  const filteredGroups = useMemo(() => {
    const search = groupSearch.trim().toLowerCase();

    if (!search) {
      return groups;
    }

    return groups.filter((group) => {
      const haystack = `${group.subject ?? ''} ${group.id ?? ''} ${group.owner ?? ''}`.toLowerCase();
      return haystack.includes(search);
    });
  }, [groupSearch, groups]);

  const filteredChats = useMemo(() => {
    const search = chatSearch.trim().toLowerCase();

    if (!search) {
      return chats;
    }

    return chats.filter((chat) => {
      const haystack = `${getChatLabel(chat)} ${getChatRemoteJid(chat) ?? ''}`.toLowerCase();
      return haystack.includes(search);
    });
  }, [chatSearch, chats]);

  useEffect(() => {
    if (!selectedGroupId && groups.length > 0) {
      setSelectedGroupId(groups[0].id);
    }
  }, [groups, selectedGroupId]);

  useEffect(() => {
    if (!remoteJidInput && chats.length > 0) {
      const firstJid = getChatRemoteJid(chats[0]);
      if (firstJid) {
        setRemoteJidInput(firstJid);
        setActiveRemoteJid(firstJid);
      }
    }
  }, [chats, remoteJidInput]);

  if (!evolutionEnabled) {
    return (
      <section className="glass rounded-3xl border border-border/60 p-5">
        <Alert>
          <AlertTitle>Explorer remoto indisponivel para este provider</AlertTitle>
          <AlertDescription>
            O painel de grupos, participantes e mensagens remotas foi ligado ao contrato atual da Evolution API.
            Para {instance.provider.label}, a tela continua focada em conexao, health check e configuracao da instancia.
          </AlertDescription>
        </Alert>
      </section>
    );
  }

  return (
    <section className="glass rounded-3xl border border-border/60 p-5">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-lg font-semibold">Explorer remoto</h2>
          <p className="text-sm text-muted-foreground">
            Inspecione grupos, participantes, chats e mensagens expostos pela Evolution API desta instancia.
          </p>
        </div>

        <Badge variant="outline" className="w-fit bg-primary/10 text-primary border-primary/20">
          Evolution API
        </Badge>
      </div>

      <Tabs defaultValue="groups" className="mt-5">
        <TabsList className="grid w-full grid-cols-2">
          <TabsTrigger value="groups">Grupos</TabsTrigger>
          <TabsTrigger value="messages">Mensagens</TabsTrigger>
        </TabsList>

        <TabsContent value="groups" className="space-y-4">
          <div className="grid gap-4 xl:grid-cols-[0.95fr_1.05fr]">
            <div className="rounded-3xl border border-border/60 bg-background/70 p-4">
              <div className="flex items-center gap-2">
                <FolderSearch className="h-4 w-4 text-primary" />
                <h3 className="text-sm font-semibold">Catalogo remoto</h3>
              </div>

              <div className="mt-4 flex gap-2">
                <div className="relative flex-1">
                  <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    value={groupSearch}
                    onChange={(event) => setGroupSearch(event.target.value)}
                    placeholder="Buscar por nome, owner ou id"
                    className="pl-9"
                  />
                </div>
                <Button variant="outline" onClick={() => groupsQuery.refetch()} disabled={groupsQuery.isFetching}>
                  {groupsQuery.isFetching ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCcw className="h-4 w-4" />}
                </Button>
              </div>

              <ScrollArea className="mt-4 h-[360px]">
                <div className="space-y-3 pr-4">
                  {groupsQuery.isLoading ? (
                    <div className="flex items-center gap-2 rounded-2xl border border-border/60 p-4 text-sm text-muted-foreground">
                      <Loader2 className="h-4 w-4 animate-spin" />
                      Carregando grupos remotos...
                    </div>
                  ) : filteredGroups.length > 0 ? (
                    filteredGroups.map((group) => {
                      const active = group.id === selectedGroupId;

                      return (
                        <button
                          key={group.id ?? group.subject ?? Math.random()}
                          type="button"
                          onClick={() => setSelectedGroupId(group.id)}
                          className={`w-full rounded-2xl border p-4 text-left transition ${
                            active
                              ? 'border-primary/40 bg-primary/5'
                              : 'border-border/60 bg-background/80 hover:border-primary/20'
                          }`}
                        >
                          <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                              <p className="truncate font-medium">{getGroupLabel(group)}</p>
                              <p className="mt-1 truncate text-xs text-muted-foreground">{group.id || 'Sem ID remoto'}</p>
                            </div>
                            <Badge variant="outline">{group.participants_count || group.size || 0} membros</Badge>
                          </div>
                        </button>
                      );
                    })
                  ) : (
                    <div className="rounded-2xl border border-dashed border-border/60 p-6 text-sm text-muted-foreground">
                      Nenhum grupo remoto encontrado para esta instancia.
                    </div>
                  )}
                </div>
              </ScrollArea>
            </div>

            <div className="rounded-3xl border border-border/60 bg-background/70 p-4">
              {selectedGroup ? (
                <>
                  <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                      <h3 className="text-base font-semibold">{getGroupLabel(selectedGroup)}</h3>
                      <p className="text-sm text-muted-foreground">{selectedGroup.id || 'Sem ID remoto'}</p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                      {selectedGroup.announce !== null ? (
                        <Badge variant="outline">{selectedGroup.announce ? 'Somente admins falam' : 'Todos falam'}</Badge>
                      ) : null}
                      {selectedGroup.restrict !== null ? (
                        <Badge variant="outline">{selectedGroup.restrict ? 'Editavel por admins' : 'Editavel por todos'}</Badge>
                      ) : null}
                    </div>
                  </div>

                  <div className="mt-4 grid gap-3 md:grid-cols-2">
                    <div className="rounded-2xl border border-border/60 bg-background/80 p-3">
                      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Owner</p>
                      <p className="mt-1 break-all font-medium">{selectedGroup.owner || 'Nao informado'}</p>
                    </div>
                    <div className="rounded-2xl border border-border/60 bg-background/80 p-3">
                      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Criacao</p>
                      <p className="mt-1 font-medium">{formatRemoteDate(selectedGroup.creation)}</p>
                    </div>
                    <div className="rounded-2xl border border-border/60 bg-background/80 p-3 md:col-span-2">
                      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Convite</p>
                      {invitationQuery.isLoading ? (
                        <p className="mt-1 text-sm text-muted-foreground">Consultando link de convite...</p>
                      ) : invitationQuery.data?.invitation_link ? (
                        <a
                          href={invitationQuery.data.invitation_link}
                          target="_blank"
                          rel="noreferrer"
                          className="mt-1 block break-all text-sm font-medium text-primary underline-offset-4 hover:underline"
                        >
                          {invitationQuery.data.invitation_link}
                        </a>
                      ) : (
                        <p className="mt-1 text-sm text-muted-foreground">Sem link retornado para este grupo.</p>
                      )}
                    </div>
                  </div>

                  <div className="mt-4">
                    <div className="flex items-center gap-2">
                      <Users className="h-4 w-4 text-primary" />
                      <h4 className="text-sm font-semibold">Participantes remotos</h4>
                    </div>

                    <ScrollArea className="mt-3 h-[220px] rounded-2xl border border-border/60 bg-background/80">
                      <div className="space-y-3 p-4">
                        {participantsQuery.isLoading ? (
                          <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            Carregando participantes...
                          </div>
                        ) : (participantsQuery.data?.participants ?? []).length > 0 ? (
                          participantsQuery.data?.participants.map((participant) => (
                            <div key={participant.id ?? Math.random()} className="rounded-2xl border border-border/60 p-3">
                              <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                  <p className="truncate font-medium">{participant.name || participant.notify || participant.id || 'Participante'}</p>
                                  <p className="mt-1 truncate text-xs text-muted-foreground">{participant.id || 'Sem JID'}</p>
                                </div>
                                {participant.admin ? <Badge variant="outline">{participant.admin}</Badge> : null}
                              </div>
                            </div>
                          ))
                        ) : (
                          <p className="text-sm text-muted-foreground">Nenhum participante retornado para este grupo.</p>
                        )}
                      </div>
                    </ScrollArea>
                  </div>
                </>
              ) : (
                <div className="rounded-2xl border border-dashed border-border/60 p-6 text-sm text-muted-foreground">
                  Selecione um grupo remoto para inspecionar participantes e convite.
                </div>
              )}
            </div>
          </div>
        </TabsContent>

        <TabsContent value="messages" className="space-y-4">
          <div className="grid gap-4 xl:grid-cols-[0.85fr_1.15fr]">
            <div className="rounded-3xl border border-border/60 bg-background/70 p-4">
              <div className="flex items-center gap-2">
                <MessageSquareMore className="h-4 w-4 text-primary" />
                <h3 className="text-sm font-semibold">Chats remotos</h3>
              </div>

              <div className="mt-4 flex gap-2">
                <div className="relative flex-1">
                  <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    value={chatSearch}
                    onChange={(event) => setChatSearch(event.target.value)}
                    placeholder="Buscar por nome ou JID"
                    className="pl-9"
                  />
                </div>
                <Button variant="outline" onClick={() => chatsQuery.refetch()} disabled={chatsQuery.isFetching}>
                  {chatsQuery.isFetching ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCcw className="h-4 w-4" />}
                </Button>
              </div>

              <ScrollArea className="mt-4 h-[360px]">
                <div className="space-y-3 pr-4">
                  {chatsQuery.isLoading ? (
                    <div className="flex items-center gap-2 rounded-2xl border border-border/60 p-4 text-sm text-muted-foreground">
                      <Loader2 className="h-4 w-4 animate-spin" />
                      Carregando chats remotos...
                    </div>
                  ) : filteredChats.length > 0 ? (
                    filteredChats.map((chat) => {
                      const remoteJid = getChatRemoteJid(chat);
                      const active = remoteJid && remoteJid === activeRemoteJid;

                      return (
                        <button
                          key={remoteJid ?? getChatLabel(chat)}
                          type="button"
                          onClick={() => {
                            if (!remoteJid) return;
                            setRemoteJidInput(remoteJid);
                            setActiveRemoteJid(remoteJid);
                          }}
                          className={`w-full rounded-2xl border p-4 text-left transition ${
                            active
                              ? 'border-primary/40 bg-primary/5'
                              : 'border-border/60 bg-background/80 hover:border-primary/20'
                          }`}
                        >
                          <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                              <p className="truncate font-medium">{getChatLabel(chat)}</p>
                              <p className="mt-1 truncate text-xs text-muted-foreground">{remoteJid || 'Sem JID remoto'}</p>
                            </div>
                            {chat.isGroup ? <Badge variant="outline">Grupo</Badge> : null}
                          </div>
                          <p className="mt-2 text-xs text-muted-foreground">
                            Ultima atividade: {formatRemoteDate(chat.conversationTimestamp || chat.lastMessageTime)}
                          </p>
                        </button>
                      );
                    })
                  ) : (
                    <div className="rounded-2xl border border-dashed border-border/60 p-6 text-sm text-muted-foreground">
                      Nenhum chat remoto retornado para esta instancia.
                    </div>
                  )}
                </div>
              </ScrollArea>
            </div>

            <div className="rounded-3xl border border-border/60 bg-background/70 p-4">
              <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
                <div className="flex-1">
                  <label className="text-sm font-medium">Remote JID</label>
                  <Input
                    value={remoteJidInput}
                    onChange={(event) => setRemoteJidInput(event.target.value)}
                    placeholder="5511999999999@s.whatsapp.net"
                    className="mt-2"
                  />
                </div>
                <Button
                  variant="outline"
                  onClick={() => setActiveRemoteJid(remoteJidInput.trim() || null)}
                  disabled={!remoteJidInput.trim()}
                >
                  Buscar mensagens
                </Button>
              </div>

              <div className="mt-4 rounded-2xl border border-border/60 bg-background/80 p-3">
                <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Chat ativo</p>
                <p className="mt-1 break-all font-medium">{activeRemoteJid || 'Selecione um chat remoto ou informe um JID.'}</p>
              </div>

              <ScrollArea className="mt-4 h-[360px] rounded-2xl border border-border/60 bg-background/80">
                <div className="space-y-3 p-4">
                  {!activeRemoteJid ? (
                    <p className="text-sm text-muted-foreground">
                      Escolha um chat remoto para carregar as ultimas mensagens do provider.
                    </p>
                  ) : messagesQuery.isLoading ? (
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                      <Loader2 className="h-4 w-4 animate-spin" />
                      Buscando mensagens remotas...
                    </div>
                  ) : (messagesQuery.data?.messages ?? []).length > 0 ? (
                    messagesQuery.data?.messages.map((message) => (
                      <article key={message.id ?? Math.random()} className="rounded-2xl border border-border/60 p-4">
                        <div className="flex flex-wrap items-center gap-2">
                          <Badge variant={message.from_me ? 'default' : 'secondary'}>
                            {message.from_me ? 'Saida' : 'Entrada'}
                          </Badge>
                          {message.push_name ? <Badge variant="outline">{message.push_name}</Badge> : null}
                        </div>
                        <p className="mt-3 text-sm font-medium">{extractMessagePreview(message)}</p>
                        <div className="mt-3 flex flex-col gap-1 text-xs text-muted-foreground">
                          <span>ID: {message.id || 'Sem ID'}</span>
                          <span>Quando: {formatRemoteDate(message.timestamp)}</span>
                        </div>
                      </article>
                    ))
                  ) : (
                    <p className="text-sm text-muted-foreground">
                      Nenhuma mensagem retornada para este chat remoto.
                    </p>
                  )}
                </div>
              </ScrollArea>
            </div>
          </div>
        </TabsContent>
      </Tabs>
    </section>
  );
}
