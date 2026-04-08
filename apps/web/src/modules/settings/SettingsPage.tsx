import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { Link } from 'react-router-dom';
import { ImageUp, Loader2, MessageSquare, Plus, Save, Send, Trash2, Upload } from 'lucide-react';

import { useAuth } from '@/app/providers/AuthProvider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import { WHATSAPP_SETTINGS_PATH } from '@/modules/whatsapp/paths';
import { formatRoleLabel } from '@/shared/auth/labels';
import { SYSTEM_MODULES } from '@/shared/auth/modules';
import { ROLE_DEFAULT_PERMISSIONS, type Permission } from '@/shared/auth/permissions';
import { PageHeader } from '@/shared/components/PageHeader';
import { UserAvatar } from '@/shared/components/UserAvatar';
import type { UserRole } from '@/shared/types';

import { settingsService } from './api';
import type { InviteCurrentOrganizationTeamMemberPayload } from './types';

const ROLE_KEYS: UserRole[] = [
  'super_admin',
  'platform_admin',
  'partner_owner',
  'partner_manager',
  'event_operator',
  'financial',
  'partner',
  'viewer',
];

const SETTINGS_PERMISSION_MODULES: Array<{ key: string; label: string; permission: Permission }> = Object.values(SYSTEM_MODULES).flatMap((module) => {
  if (!module.requiredPermission) {
    return [];
  }

  return [
    {
      key: module.key,
      label: module.label,
      permission: module.requiredPermission as Permission,
    },
  ];
});

const DEFAULT_INVITE_FORM: InviteCurrentOrganizationTeamMemberPayload = {
  user: {
    name: '',
    email: '',
    phone: '',
  },
  role_key: 'partner-manager',
  is_owner: false,
};

export default function SettingsPage() {
  const { meUser: user, meOrganization: organization, refreshSession, can } = useAuth();
  const { toast } = useToast();
  const queryClient = useQueryClient();

  const isSuperAdmin = user?.role.key === 'super-admin';
  const canManageAiSettings = user?.role.key === 'super-admin' || user?.role.key === 'platform-admin';
  const currentRoleLabel = formatRoleLabel(user?.role.key, user?.role.name);
  const canManageSettings = can('settings.manage');
  const canManageBranding = can('branding.manage');
  const canManageTeam = can('team.manage');
  const canManageChannels = can('channels.manage');
  const canViewPermissionsMatrix = isSuperAdmin;
  const canViewIntegrations = isSuperAdmin;

  const teamQueryKey = useMemo(
    () => ['settings', 'current-organization-team', organization?.id ?? 'none'],
    [organization?.id],
  );

  const [organizationForm, setOrganizationForm] = useState({
    name: organization?.name ?? '',
    slug: organization?.slug ?? '',
    custom_domain: organization?.branding?.custom_domain ?? '',
  });
  const [brandingForm, setBrandingForm] = useState({
    primary_color: organization?.branding?.primary_color ?? '#7c3aed',
    secondary_color: organization?.branding?.secondary_color ?? '#3b82f6',
  });
  const [preferencesForm, setPreferencesForm] = useState({
    email_notifications: user?.preferences?.email_notifications ?? true,
    push_notifications: user?.preferences?.push_notifications ?? false,
    compact_mode: user?.preferences?.compact_mode ?? false,
  });
  const [replyTextPrompt, setReplyTextPrompt] = useState('');
  const [selectedLogoFile, setSelectedLogoFile] = useState<File | null>(null);
  const [inviteDialogOpen, setInviteDialogOpen] = useState(false);
  const [inviteForm, setInviteForm] = useState<InviteCurrentOrganizationTeamMemberPayload>(DEFAULT_INVITE_FORM);

  useEffect(() => {
    setOrganizationForm({
      name: organization?.name ?? '',
      slug: organization?.slug ?? '',
      custom_domain: organization?.branding?.custom_domain ?? '',
    });
  }, [organization?.branding?.custom_domain, organization?.name, organization?.slug]);

  useEffect(() => {
    setBrandingForm({
      primary_color: organization?.branding?.primary_color ?? '#7c3aed',
      secondary_color: organization?.branding?.secondary_color ?? '#3b82f6',
    });
  }, [organization?.branding?.primary_color, organization?.branding?.secondary_color]);

  useEffect(() => {
    setPreferencesForm({
      email_notifications: user?.preferences?.email_notifications ?? true,
      push_notifications: user?.preferences?.push_notifications ?? false,
      compact_mode: user?.preferences?.compact_mode ?? false,
    });
  }, [
    user?.preferences?.compact_mode,
    user?.preferences?.email_notifications,
    user?.preferences?.push_notifications,
  ]);

  const teamQuery = useQuery({
    queryKey: teamQueryKey,
    queryFn: () => settingsService.listCurrentOrganizationTeam(),
    enabled: canManageTeam && !!organization?.id,
  });

  const mediaIntelligenceGlobalSettingsQuery = useQuery({
    queryKey: ['settings', 'media-intelligence-global-settings'],
    queryFn: () => settingsService.getMediaIntelligenceGlobalSettings(),
    enabled: canManageAiSettings,
  });

  useEffect(() => {
    setReplyTextPrompt(mediaIntelligenceGlobalSettingsQuery.data?.reply_text_prompt ?? '');
  }, [mediaIntelligenceGlobalSettingsQuery.data?.reply_text_prompt]);

  const organizationMutation = useMutation({
    mutationFn: (payload: { name: string; slug: string; custom_domain: string }) => settingsService.updateCurrentOrganization(payload),
    onSuccess: async () => {
      await refreshSession();
      toast({
        title: 'Organizacao atualizada',
        description: 'Os dados principais da organizacao foram salvos.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao salvar organizacao',
        description: 'Nao foi possivel persistir os dados da organizacao.',
        variant: 'destructive',
      });
    },
  });

  const brandingMutation = useMutation({
    mutationFn: (payload: { primary_color: string; secondary_color: string }) => settingsService.updateCurrentOrganizationBranding(payload),
    onSuccess: async () => {
      await refreshSession();
      toast({
        title: 'Branding atualizado',
        description: 'As cores da organizacao foram salvas.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao salvar branding',
        description: 'Nao foi possivel persistir as configuracoes de branding.',
        variant: 'destructive',
      });
    },
  });

  const uploadLogoMutation = useMutation({
    mutationFn: (file: File) => settingsService.uploadCurrentOrganizationLogo(file),
    onSuccess: async () => {
      setSelectedLogoFile(null);
      await refreshSession();
      toast({
        title: 'Logo atualizado',
        description: 'O logo da organizacao foi salvo.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao enviar logo',
        description: 'Nao foi possivel salvar o logo da organizacao.',
        variant: 'destructive',
      });
    },
  });

  const inviteMemberMutation = useMutation({
    mutationFn: (payload: InviteCurrentOrganizationTeamMemberPayload) => settingsService.inviteCurrentOrganizationTeamMember(payload),
    onSuccess: async () => {
      setInviteDialogOpen(false);
      setInviteForm(DEFAULT_INVITE_FORM);
      await queryClient.invalidateQueries({ queryKey: teamQueryKey });
      toast({
        title: 'Membro adicionado',
        description: 'A equipe da organizacao foi atualizada.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao adicionar membro',
        description: 'Nao foi possivel convidar o membro para a organizacao.',
        variant: 'destructive',
      });
    },
  });

  const removeMemberMutation = useMutation({
    mutationFn: (memberId: number) => settingsService.removeCurrentOrganizationTeamMember(memberId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: teamQueryKey });
      toast({
        title: 'Membro removido',
        description: 'A equipe da organizacao foi atualizada.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao remover membro',
        description: 'Nao foi possivel remover o membro da organizacao.',
        variant: 'destructive',
      });
    },
  });

  const preferencesMutation = useMutation({
    mutationFn: (payload: { email_notifications: boolean; push_notifications: boolean; compact_mode: boolean }) =>
      settingsService.updateCurrentUserPreferences(payload),
    onSuccess: async () => {
      await refreshSession();
      toast({
        title: 'Preferencias salvas',
        description: 'Suas preferencias de uso foram atualizadas.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao salvar preferencias',
        description: 'Nao foi possivel persistir as preferencias da conta.',
        variant: 'destructive',
      });
    },
  });

  const mediaIntelligenceGlobalSettingsMutation = useMutation({
    mutationFn: (payload: { reply_text_prompt: string }) =>
      settingsService.updateMediaIntelligenceGlobalSettings(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['settings', 'media-intelligence-global-settings'] });
      toast({
        title: 'Instrucao padrao atualizada',
        description: 'A configuracao global de respostas automaticas foi salva.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao salvar instrucao padrao',
        description: 'Nao foi possivel persistir a configuracao global de respostas automaticas.',
        variant: 'destructive',
      });
    },
  });

  const teamMembers = teamQuery.data?.data ?? [];

  const handleSaveOrganization = () => {
    organizationMutation.mutate({
      name: organizationForm.name.trim(),
      slug: organizationForm.slug.trim(),
      custom_domain: organizationForm.custom_domain.trim(),
    });
  };

  const handleSaveBranding = () => {
    brandingMutation.mutate({
      primary_color: brandingForm.primary_color.trim(),
      secondary_color: brandingForm.secondary_color.trim(),
    });
  };

  const handleUploadLogo = () => {
    if (!selectedLogoFile) {
      return;
    }

    uploadLogoMutation.mutate(selectedLogoFile);
  };

  const handleInviteMember = () => {
    inviteMemberMutation.mutate({
      user: {
        name: inviteForm.user.name.trim(),
        email: inviteForm.user.email.trim(),
        phone: inviteForm.user.phone?.trim() || undefined,
      },
      role_key: inviteForm.role_key,
      is_owner: inviteForm.is_owner,
    });
  };

  const savePreferences = () => {
    preferencesMutation.mutate({
      email_notifications: preferencesForm.email_notifications,
      push_notifications: preferencesForm.push_notifications,
      compact_mode: preferencesForm.compact_mode,
    });
  };

  const saveMediaIntelligenceGlobalSettings = () => {
    mediaIntelligenceGlobalSettingsMutation.mutate({
      reply_text_prompt: replyTextPrompt.trim(),
    });
  };

  return (
    <motion.div initial={false} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader title="Configuracoes" description="Gerencie seu perfil, organizacao e recursos da conta" />

      <Tabs defaultValue="profile">
        <TabsList className="flex-wrap bg-muted/50">
          <TabsTrigger value="profile">Perfil</TabsTrigger>
          <TabsTrigger value="organization">Organizacao</TabsTrigger>
          {canManageBranding ? <TabsTrigger value="branding">Branding</TabsTrigger> : null}
          {canManageTeam ? <TabsTrigger value="team">Equipe</TabsTrigger> : null}
          {canViewPermissionsMatrix ? <TabsTrigger value="permissions">Permissoes</TabsTrigger> : null}
          {canViewIntegrations ? <TabsTrigger value="integrations">Integracoes</TabsTrigger> : null}
          <TabsTrigger value="preferences">Preferencias</TabsTrigger>
        </TabsList>

        <TabsContent value="profile" className="mt-6">
          <div className="glass max-w-xl space-y-4 rounded-xl p-6">
            <h3 className="font-semibold">Dados do Perfil</h3>

            <div className="mb-4 flex items-center gap-4">
              <UserAvatar name={user?.name || ''} avatarUrl={user?.avatar_url} size="lg" />

              <div className="space-y-1.5">
                <Button variant="outline" size="sm" asChild>
                  <Link to="/profile">
                    <Upload className="mr-1 h-4 w-4" />
                    Editar perfil
                  </Link>
                </Button>

                <p className="text-[10px] text-muted-foreground">
                  Abra a tela completa do perfil para editar avatar e dados pessoais.
                </p>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Nome</Label>
                <Input value={user?.name ?? ''} className="mt-1.5" disabled />
              </div>

              <div>
                <Label>E-mail</Label>
                <Input value={user?.email ?? ''} className="mt-1.5" disabled />
              </div>
            </div>

            <div>
              <Label>Perfil atual</Label>
              <Input value={currentRoleLabel} disabled className="mt-1.5" />
            </div>
          </div>
        </TabsContent>

        <TabsContent value="organization" className="mt-6">
          <div className="glass max-w-xl space-y-4 rounded-xl p-6">
            <h3 className="font-semibold">Dados da Organizacao</h3>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label htmlFor="organization-name">Nome</Label>
                <Input
                  id="organization-name"
                  value={organizationForm.name}
                  onChange={(event) => setOrganizationForm((current) => ({ ...current, name: event.target.value }))}
                  className="mt-1.5"
                  disabled={!canManageSettings}
                />
              </div>

              <div>
                <Label htmlFor="organization-slug">Slug</Label>
                <Input
                  id="organization-slug"
                  value={organizationForm.slug}
                  onChange={(event) => setOrganizationForm((current) => ({ ...current, slug: event.target.value }))}
                  className="mt-1.5"
                  disabled={!canManageSettings}
                />
              </div>
            </div>

            <div>
              <Label htmlFor="organization-custom-domain">Dominio customizado</Label>
              <Input
                id="organization-custom-domain"
                value={organizationForm.custom_domain}
                onChange={(event) => setOrganizationForm((current) => ({ ...current, custom_domain: event.target.value }))}
                placeholder="eventos.suaempresa.com"
                className="mt-1.5"
                disabled={!canManageSettings}
              />
            </div>

            <Button onClick={handleSaveOrganization} disabled={!canManageSettings || organizationMutation.isPending}>
              {organizationMutation.isPending ? <Loader2 className="mr-1 h-4 w-4 animate-spin" /> : <Save className="mr-1 h-4 w-4" />}
              Salvar
            </Button>
          </div>
        </TabsContent>

        {canManageBranding ? (
          <TabsContent value="branding" className="mt-6">
            <div className="glass max-w-xl space-y-4 rounded-xl p-6">
              <h3 className="font-semibold">Branding</h3>

              <div className="space-y-3">
                <Label htmlFor="branding-logo-upload">Logo da organizacao</Label>

                {organization?.logo_url ? (
                  <div className="flex items-center gap-3 rounded-lg border border-border/50 bg-muted/20 p-3">
                    <img
                      src={organization.logo_url}
                      alt="Logo atual da organizacao"
                      className="h-14 w-14 rounded-md border border-border/50 bg-background object-contain p-1"
                    />
                    <div className="text-sm text-muted-foreground">
                      Logo atual carregado da organizacao.
                    </div>
                  </div>
                ) : null}

                <Input
                  id="branding-logo-upload"
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  onChange={(event) => setSelectedLogoFile(event.target.files?.[0] ?? null)}
                />

                <div className="flex items-center justify-between gap-3 rounded-lg border border-dashed border-border/60 p-3 text-sm text-muted-foreground">
                  <div className="flex items-center gap-2">
                    <ImageUp className="h-4 w-4" />
                    <span>{selectedLogoFile?.name ?? 'Selecione um arquivo PNG, JPG ou WebP.'}</span>
                  </div>

                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={handleUploadLogo}
                    disabled={!selectedLogoFile || uploadLogoMutation.isPending}
                  >
                    {uploadLogoMutation.isPending ? <Loader2 className="mr-1 h-4 w-4 animate-spin" /> : <Upload className="mr-1 h-4 w-4" />}
                    Enviar logo
                  </Button>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="branding-primary-color">Cor primaria</Label>
                  <div className="mt-1.5 flex items-center gap-3">
                    <div className="h-9 w-9 rounded-md border" style={{ backgroundColor: brandingForm.primary_color }} />
                    <Input
                      id="branding-primary-color"
                      value={brandingForm.primary_color}
                      onChange={(event) => setBrandingForm((current) => ({ ...current, primary_color: event.target.value }))}
                    />
                  </div>
                </div>

                <div>
                  <Label htmlFor="branding-secondary-color">Cor secundaria</Label>
                  <div className="mt-1.5 flex items-center gap-3">
                    <div className="h-9 w-9 rounded-md border" style={{ backgroundColor: brandingForm.secondary_color }} />
                    <Input
                      id="branding-secondary-color"
                      value={brandingForm.secondary_color}
                      onChange={(event) => setBrandingForm((current) => ({ ...current, secondary_color: event.target.value }))}
                    />
                  </div>
                </div>
              </div>

              <Button onClick={handleSaveBranding} disabled={brandingMutation.isPending}>
                {brandingMutation.isPending ? <Loader2 className="mr-1 h-4 w-4 animate-spin" /> : <Save className="mr-1 h-4 w-4" />}
                Salvar
              </Button>
            </div>
          </TabsContent>
        ) : null}

        {canManageTeam ? (
          <TabsContent value="team" className="mt-6">
            <div className="glass space-y-4 rounded-xl p-6">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <h3 className="font-semibold">Membros da Equipe</h3>
                  <p className="text-sm text-muted-foreground">
                    Convide membros da sua organizacao e acompanhe o papel de cada um.
                  </p>
                </div>

                <Button size="sm" onClick={() => setInviteDialogOpen(true)}>
                  <Plus className="mr-1 h-4 w-4" />
                  Convidar
                </Button>
              </div>

              {teamMembers.length === 0 ? (
                teamQuery.isLoading ? (
                  <div className="rounded-lg border border-dashed border-border/50 p-6 text-sm text-muted-foreground">
                    Carregando equipe da organizacao...
                  </div>
                ) : teamQuery.isError ? (
                  <div className="rounded-lg border border-destructive/30 p-6 text-sm text-destructive">
                    Nao foi possivel carregar a equipe da organizacao.
                  </div>
                ) : (
                  <div className="rounded-lg border border-dashed border-border/50 p-6 text-sm text-muted-foreground">
                    Nenhum membro de equipe encontrado para esta organizacao.
                  </div>
                )
              ) : (
                <div className="space-y-2">
                  {teamMembers.map((teamMember) => (
                    <div key={teamMember.id} className="flex items-center gap-3 rounded-lg bg-muted/30 p-3">
                      <UserAvatar name={teamMember.user?.name || 'Usuario'} size="md" />

                      <div className="flex-1">
                        <p className="text-sm font-medium">{teamMember.user?.name || 'Usuario sem nome'}</p>
                        <p className="text-xs text-muted-foreground">{teamMember.user?.email || 'Sem e-mail'}</p>
                      </div>

                      <Badge variant="outline" className="text-xs">
                        {formatRoleLabel(teamMember.role_key, teamMember.role_key)}
                      </Badge>

                      {teamMember.is_owner ? (
                        <Badge>Proprietario</Badge>
                      ) : (
                        <Button
                          variant="ghost"
                          size="icon"
                          className="text-destructive"
                          aria-label={`Remover ${teamMember.user?.name || 'membro'}`}
                          onClick={() => removeMemberMutation.mutate(teamMember.id)}
                          disabled={removeMemberMutation.isPending}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          </TabsContent>
        ) : null}

        {canManageAiSettings ? (
          <TabsContent value="ai" className="mt-6">
            <div className="glass max-w-3xl space-y-4 rounded-xl p-6">
              <div className="space-y-1">
                <h3 className="font-semibold">Prompt global de reply_text</h3>
                <p className="text-sm text-muted-foreground">
                  Este prompt e usado como padrao para respostas curtas da midia aprovada quando o evento habilita
                  `reply_text` e nao define um override proprio.
                </p>
              </div>

              {mediaIntelligenceGlobalSettingsQuery.isLoading ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Carregando prompt global de IA...
                </div>
              ) : mediaIntelligenceGlobalSettingsQuery.isError ? (
                <div className="rounded-lg border border-destructive/30 p-4 text-sm text-destructive">
                  Nao foi possivel carregar o prompt global de reply_text.
                </div>
              ) : (
                <>
                  <div className="space-y-2">
                    <Label htmlFor="media-intelligence-reply-text-prompt">Prompt global</Label>
                    <Textarea
                      id="media-intelligence-reply-text-prompt"
                      value={replyTextPrompt}
                      onChange={(event) => setReplyTextPrompt(event.target.value)}
                      rows={8}
                      disabled={!canManageSettings || mediaIntelligenceGlobalSettingsMutation.isPending}
                    />
                    <p className="text-xs text-muted-foreground">
                      Regra recomendada: frase curta, emoji coerente com a cena, sem hashtags, sem inventar contexto e
                      com uso opcional do nome do evento.
                    </p>
                  </div>

                  <div className="rounded-lg border border-border/50 bg-muted/20 p-4 text-sm text-muted-foreground">
                    <p className="font-medium text-foreground">Exemplos esperados</p>
                    <p className="mt-2">Momento de risadas e lembrancas! 📱🎉</p>
                    <p className="mt-1">🎓✨ Momentos que ficam para a vida! Vamos celebrar! 🎉📸</p>
                    <p className="mt-1">Memorias que fazem o coracao sorrir! 🎉📸</p>
                    <p className="mt-1">Paz, amor e um sorriso! ✌️😊</p>
                    <p className="mt-1">Coelhinho charmoso para alegrar a festa! 🐰✨</p>
                  </div>

                  <Button
                    onClick={saveMediaIntelligenceGlobalSettings}
                    disabled={!canManageSettings || mediaIntelligenceGlobalSettingsMutation.isPending}
                  >
                    {mediaIntelligenceGlobalSettingsMutation.isPending
                      ? <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                      : <Save className="mr-1 h-4 w-4" />}
                    Salvar prompt de IA
                  </Button>
                </>
              )}
            </div>
          </TabsContent>
        ) : null}

        {canViewPermissionsMatrix ? (
          <TabsContent value="permissions" className="mt-6">
            <div className="glass space-y-4 rounded-xl p-6">
              <div className="space-y-1">
                <h3 className="font-semibold">Permissoes por Perfil</h3>
                <p className="text-sm text-muted-foreground">
                  Esta visao e exclusiva para super administradores da plataforma.
                </p>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-border/50">
                      <th className="py-2 pr-4 text-left">Modulo</th>
                      {ROLE_KEYS.map((roleKey) => (
                        <th key={roleKey} className="px-3 py-2 text-center text-xs text-muted-foreground">
                          {formatRoleLabel(roleKey.replace(/_/g, '-'), roleKey)}
                        </th>
                      ))}
                    </tr>
                  </thead>

                  <tbody>
                    {SETTINGS_PERMISSION_MODULES.map((moduleItem) => (
                      <tr key={moduleItem.key} className="border-b border-border/20">
                        <td className="py-2 pr-4">{moduleItem.label}</td>

                        {ROLE_KEYS.map((roleKey) => (
                          <td key={roleKey} className="px-3 py-2 text-center">
                            <Switch
                              checked={ROLE_DEFAULT_PERMISSIONS[roleKey].includes(moduleItem.permission)}
                              disabled
                              aria-label={`${moduleItem.label} para ${roleKey}`}
                              className="scale-75"
                            />
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </TabsContent>
        ) : null}

        {canViewIntegrations ? (
          <TabsContent value="integrations" className="mt-6">
            <div className="space-y-4">
              <div className="glass max-w-2xl rounded-xl p-6">
                <h3 className="font-semibold">Integracoes</h3>
                <p className="mt-2 text-sm text-muted-foreground">
                  Somente o super administrador enxerga o escopo global das integracoes da plataforma.
                </p>
              </div>

              <div className="grid max-w-2xl grid-cols-1 gap-4 md:grid-cols-2">
                {[
                  {
                    name: 'WhatsApp',
                    icon: MessageSquare,
                    connected: true,
                    desc: 'Instancias e conexao de WhatsApp da organizacao',
                    actionLabel: canManageChannels ? 'Gerenciar' : 'Visualizar',
                    actionPath: WHATSAPP_SETTINGS_PATH,
                  },
                  {
                    name: 'Telegram',
                    icon: Send,
                    connected: false,
                    desc: 'Recebimento de fotos via bot do Telegram',
                    actionLabel: 'Em breve',
                    actionPath: null,
                  },
                ].map((integration) => (
                  <div key={integration.name} className="glass card-hover rounded-xl p-5">
                    <div className="mb-3 flex items-center gap-3">
                      <div className="rounded-lg bg-primary/10 p-2">
                        <integration.icon className="h-5 w-5 text-primary" />
                      </div>

                      <div className="flex-1">
                        <p className="font-medium">{integration.name}</p>
                        <p className="text-xs text-muted-foreground">{integration.desc}</p>
                      </div>
                    </div>

                    <div className="mb-3">
                      <Badge variant="outline">Escopo global habilitado</Badge>
                    </div>

                    {integration.actionPath ? (
                      <Button asChild variant="outline" size="sm">
                        <Link to={integration.actionPath}>{integration.actionLabel}</Link>
                      </Button>
                    ) : (
                      <Button variant={integration.connected ? 'outline' : 'default'} size="sm" disabled>
                        {integration.actionLabel}
                      </Button>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </TabsContent>
        ) : null}

        <TabsContent value="preferences" className="mt-6">
          <div className="glass max-w-xl space-y-4 rounded-xl p-6">
            <h3 className="font-semibold">Preferencias</h3>

            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <div>
                  <Label>Notificacoes por e-mail</Label>
                  <p className="text-xs text-muted-foreground">
                    Receber alertas importantes por e-mail
                  </p>
                </div>
                <Switch
                  aria-label="Notificacoes por e-mail"
                  checked={preferencesForm.email_notifications}
                  onCheckedChange={(checked) => setPreferencesForm((current) => ({ ...current, email_notifications: checked }))}
                />
              </div>

              <div className="flex items-center justify-between">
                <div>
                  <Label>Notificacoes push</Label>
                  <p className="text-xs text-muted-foreground">
                    Exibir alertas no navegador
                  </p>
                </div>
                <Switch
                  aria-label="Notificacoes push"
                  checked={preferencesForm.push_notifications}
                  onCheckedChange={(checked) => setPreferencesForm((current) => ({ ...current, push_notifications: checked }))}
                />
              </div>

              <div className="flex items-center justify-between">
                <div>
                  <Label>Modo compacto</Label>
                  <p className="text-xs text-muted-foreground">
                    Reduzir espacamentos da interface
                  </p>
                </div>
                <Switch
                  aria-label="Modo compacto"
                  checked={preferencesForm.compact_mode}
                  onCheckedChange={(checked) => setPreferencesForm((current) => ({ ...current, compact_mode: checked }))}
                />
              </div>
            </div>

            <Button onClick={savePreferences} disabled={preferencesMutation.isPending}>
              {preferencesMutation.isPending ? <Loader2 className="mr-1 h-4 w-4 animate-spin" /> : <Save className="mr-1 h-4 w-4" />}
              Salvar
            </Button>
          </div>
        </TabsContent>
      </Tabs>

      <Dialog open={inviteDialogOpen} onOpenChange={setInviteDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Adicionar membro</DialogTitle>
            <DialogDescription>
              Convide um membro para a organizacao atual e defina o papel operacional.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div>
              <Label htmlFor="invite-member-name">Nome</Label>
              <Input
                id="invite-member-name"
                placeholder="Nome do membro"
                value={inviteForm.user.name}
                onChange={(event) => setInviteForm((current) => ({
                  ...current,
                  user: { ...current.user, name: event.target.value },
                }))}
              />
            </div>

            <div>
              <Label htmlFor="invite-member-email">E-mail</Label>
              <Input
                id="invite-member-email"
                placeholder="membro@organizacao.com"
                value={inviteForm.user.email}
                onChange={(event) => setInviteForm((current) => ({
                  ...current,
                  user: { ...current.user, email: event.target.value },
                }))}
              />
            </div>

            <div>
              <Label htmlFor="invite-member-phone">Telefone</Label>
              <Input
                id="invite-member-phone"
                placeholder="11999999999"
                value={inviteForm.user.phone ?? ''}
                onChange={(event) => setInviteForm((current) => ({
                  ...current,
                  user: { ...current.user, phone: event.target.value },
                }))}
              />
            </div>

            <div>
              <Label htmlFor="invite-member-role">Perfil</Label>
              <select
                id="invite-member-role"
                className="mt-1.5 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={inviteForm.role_key}
                onChange={(event) => setInviteForm((current) => ({
                  ...current,
                  role_key: event.target.value as InviteCurrentOrganizationTeamMemberPayload['role_key'],
                }))}
              >
                <option value="partner-manager">Gerente</option>
                <option value="event-operator">Operador de evento</option>
                <option value="financeiro">Financeiro</option>
                <option value="viewer">Visualizador</option>
                <option value="partner-owner">Proprietario</option>
              </select>
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setInviteDialogOpen(false)}>
              Cancelar
            </Button>
            <Button onClick={handleInviteMember} disabled={inviteMemberMutation.isPending}>
              {inviteMemberMutation.isPending ? <Loader2 className="mr-1 h-4 w-4 animate-spin" /> : <Plus className="mr-1 h-4 w-4" />}
              Adicionar membro
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </motion.div>
  );
}
