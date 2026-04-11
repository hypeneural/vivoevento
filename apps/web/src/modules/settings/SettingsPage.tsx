import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { Link } from 'react-router-dom';
import {
  CheckCircle2,
  CircleHelp,
  Copy,
  Crown,
  FileImage,
  ImageUp,
  Loader2,
  LockKeyhole,
  MessageSquare,
  Palette,
  Plus,
  Save,
  Send,
  ShieldCheck,
  Trash2,
  Upload,
  UserMinus,
  UserPlus,
  Users,
} from 'lucide-react';

import { useAuth } from '@/app/providers/AuthProvider';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useToast } from '@/hooks/use-toast';
import { WHATSAPP_SETTINGS_PATH } from '@/modules/whatsapp/paths';
import { formatRoleLabel } from '@/shared/auth/labels';
import { SYSTEM_MODULES } from '@/shared/auth/modules';
import { ROLE_DEFAULT_PERMISSIONS, type Permission } from '@/shared/auth/permissions';
import { PageHeader } from '@/shared/components/PageHeader';
import { UserAvatar } from '@/shared/components/UserAvatar';
import type { UserRole } from '@/shared/types';

import { settingsService } from './api';
import type {
  CurrentOrganizationBrandingAssetKind,
  InviteCurrentOrganizationTeamMemberPayload,
  InviteCurrentOrganizationTeamMemberRoleKey,
  OrganizationTeamMember,
  OrganizationTeamInvitation,
} from './types';

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

type InviteFormState = Omit<InviteCurrentOrganizationTeamMemberPayload, 'role_key'> & {
  role_key: InviteCurrentOrganizationTeamMemberRoleKey | '';
};

const DEFAULT_INVITE_FORM: InviteFormState = {
  user: {
    name: '',
    email: '',
    phone: '',
  },
  role_key: '',
  send_via_whatsapp: true,
};

const TEAM_ROLE_OPTIONS: Array<{
  key: InviteCurrentOrganizationTeamMemberRoleKey;
  label: string;
  description: string;
}> = [
  {
    key: 'partner-manager',
    label: 'Gerente / Secretaria',
    description: 'Ajuda a organizar clientes, eventos e a operacao do dia a dia.',
  },
  {
    key: 'event-operator',
    label: 'Operar eventos',
    description: 'Acompanha o evento e atua na execucao com foco operacional.',
  },
  {
    key: 'financeiro',
    label: 'Financeiro',
    description: 'Acompanha cobrancas, faturamento e indicadores financeiros.',
  },
  {
    key: 'viewer',
    label: 'Acompanhar em leitura',
    description: 'Consulta informacoes sem alterar a configuracao da conta.',
  },
];

const PREMIUM_BRANDING_ASSETS: Array<{
  kind: CurrentOrganizationBrandingAssetKind;
  label: string;
  description: string;
}> = [
  {
    kind: 'cover',
    label: 'Capa padrao',
    description: 'Imagem de abertura herdada por eventos novos quando eles nao tiverem uma capa propria.',
  },
  {
    kind: 'logo_dark',
    label: 'Logo para fundo escuro',
    description: 'Versao alternativa do logo para telas, wall e materiais com fundo escuro.',
  },
  {
    kind: 'favicon',
    label: 'Icone do navegador',
    description: 'Icone pequeno usado em experiencias white-label e atalhos do navegador.',
  },
  {
    kind: 'watermark',
    label: 'Marca d agua',
    description: 'Ativo usado quando o plano permite aplicar marca d agua nas entregas.',
  },
];

function HelpTooltip({ text }: { text: string }) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <button type="button" className="inline-flex rounded-full text-muted-foreground hover:text-foreground" aria-label="Ajuda">
          <CircleHelp className="h-4 w-4" />
        </button>
      </TooltipTrigger>
      <TooltipContent side="top" align="start" className="max-w-xs rounded-xl px-4 py-3 text-sm">
        {text}
      </TooltipContent>
    </Tooltip>
  );
}

function formatInvitationDeliveryLabel(status?: string | null): string {
  switch (status) {
    case 'queued':
      return 'Enviado por WhatsApp';
    case 'unavailable':
      return 'Link pronto para copiar';
    case 'manual_link':
      return 'Link pronto para copiar';
    case 'pending_dispatch':
      return 'Preparando envio';
    case 'accepted':
      return 'Aceito';
    case 'revoked':
      return 'Revogado';
    case 'failed':
      return 'Falha no envio';
    default:
      return 'Pendente';
  }
}

function organizationBrandingAssetUrl(
  organization: ReturnType<typeof useAuth>['meOrganization'],
  kind: CurrentOrganizationBrandingAssetKind,
): string | null {
  switch (kind) {
    case 'logo':
      return organization?.logo_url ?? organization?.branding?.logo_url ?? null;
    case 'logo_dark':
      return organization?.branding?.logo_dark_url ?? null;
    case 'favicon':
      return organization?.branding?.favicon_url ?? null;
    case 'watermark':
      return organization?.branding?.watermark_url ?? null;
    case 'cover':
      return organization?.branding?.cover_url ?? null;
    default:
      return null;
  }
}

export default function SettingsPage() {
  const { meUser: user, meOrganization: organization, meEntitlements, refreshSession, can } = useAuth();
  const { toast } = useToast();
  const queryClient = useQueryClient();

  const currentRoleKey = user?.role.key ?? null;
  const isGlobalAdmin = currentRoleKey === 'super-admin' || currentRoleKey === 'platform-admin';
  const isSuperAdmin = currentRoleKey === 'super-admin';
  const canManageAiSettings = isGlobalAdmin;
  const currentRoleLabel = formatRoleLabel(user?.role.key, user?.role.name);
  const canManageSettings = isGlobalAdmin || ['partner-owner', 'partner-manager'].includes(currentRoleKey ?? '');
  const canManageBranding = isGlobalAdmin || currentRoleKey === 'partner-owner';
  const canManageTeam = isGlobalAdmin || ['partner-owner', 'partner-manager'].includes(currentRoleKey ?? '');
  const canManageChannels = isGlobalAdmin || can('channels.manage');
  const canViewPermissionsMatrix = isSuperAdmin;
  const canViewIntegrations = isSuperAdmin;
  const brandingEntitlements = meEntitlements?.branding;
  const canUseCustomDomain = Boolean(brandingEntitlements?.custom_domain);
  const canUploadExpandedBranding = Boolean(brandingEntitlements?.expanded_assets);
  const canUploadWatermark = Boolean(brandingEntitlements?.watermark);
  const canTransferOwnership = isGlobalAdmin || currentRoleKey === 'partner-owner';

  const teamQueryKey = useMemo(
    () => ['settings', 'current-organization-team', organization?.id ?? 'none'],
    [organization?.id],
  );
  const teamInvitationsQueryKey = useMemo(
    () => ['settings', 'current-organization-team-invitations', organization?.id ?? 'none'],
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
  const [selectedBrandingAssetFiles, setSelectedBrandingAssetFiles] = useState<
    Partial<Record<CurrentOrganizationBrandingAssetKind, File>>
  >({});
  const [inviteDialogOpen, setInviteDialogOpen] = useState(false);
  const [inviteForm, setInviteForm] = useState<InviteFormState>(DEFAULT_INVITE_FORM);
  const [memberPendingRemoval, setMemberPendingRemoval] = useState<OrganizationTeamMember | null>(null);
  const [memberPendingOwnershipTransfer, setMemberPendingOwnershipTransfer] = useState<OrganizationTeamMember | null>(null);
  const [invitationPendingRevoke, setInvitationPendingRevoke] = useState<OrganizationTeamInvitation | null>(null);

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
  const teamInvitationsQuery = useQuery({
    queryKey: teamInvitationsQueryKey,
    queryFn: () => settingsService.listCurrentOrganizationTeamInvitations(),
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

  const uploadBrandingAssetMutation = useMutation({
    mutationFn: ({ kind, file }: { kind: CurrentOrganizationBrandingAssetKind; file: File }) =>
      settingsService.uploadCurrentOrganizationBrandingAsset(kind, file),
    onSuccess: async () => {
      const uploadedKind = uploadBrandingAssetMutation.variables?.kind;

      if (uploadedKind) {
        setSelectedBrandingAssetFiles((current) => ({
          ...current,
          [uploadedKind]: undefined,
        }));
      }

      await refreshSession();
      toast({
        title: 'Ativo de marca atualizado',
        description: 'O arquivo foi salvo e podera ser herdado pelos eventos.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao enviar ativo',
        description: 'Verifique o plano da organizacao e tente novamente.',
        variant: 'destructive',
      });
    },
  });

  const inviteMemberMutation = useMutation({
    mutationFn: (payload: InviteCurrentOrganizationTeamMemberPayload) => settingsService.inviteCurrentOrganizationTeamMember(payload),
    onSuccess: async (invitation: any) => {
      setInviteDialogOpen(false);
      setInviteForm(DEFAULT_INVITE_FORM);
      await queryClient.invalidateQueries({ queryKey: teamQueryKey });
      await queryClient.invalidateQueries({ queryKey: teamInvitationsQueryKey });
      toast({
        title: 'Convite criado',
        description: invitation?.delivery_status === 'queued'
          ? 'O convite ja foi encaminhado pelo WhatsApp.'
          : 'O link do convite ficou pronto para voce compartilhar.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao criar convite',
        description: 'Nao foi possivel preparar o acesso desta pessoa agora.',
        variant: 'destructive',
      });
    },
  });

  const resendInvitationMutation = useMutation({
    mutationFn: ({ invitationId, sendViaWhatsApp }: { invitationId: number; sendViaWhatsApp: boolean }) =>
      settingsService.resendCurrentOrganizationTeamInvitation(invitationId, sendViaWhatsApp),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: teamInvitationsQueryKey });
      toast({
        title: 'Convite reenviado',
        description: 'O convite foi atualizado e esta pronto para a pessoa acessar.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao reenviar convite',
        description: 'Nao foi possivel reenviar este convite agora.',
        variant: 'destructive',
      });
    },
  });

  const revokeInvitationMutation = useMutation({
    mutationFn: (invitationId: number) => settingsService.revokeCurrentOrganizationTeamInvitation(invitationId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: teamInvitationsQueryKey });
      toast({
        title: 'Convite revogado',
        description: 'O link anterior deixou de funcionar imediatamente.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao revogar convite',
        description: 'Nao foi possivel revogar este convite agora.',
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

  const transferOwnershipMutation = useMutation({
    mutationFn: (memberId: number) => settingsService.transferCurrentOrganizationOwnership({ member_id: memberId }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: teamQueryKey });
      await refreshSession();
      toast({
        title: 'Titularidade transferida',
        description: 'A nova conta principal da organizacao foi atualizada.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao transferir titularidade',
        description: 'Nao foi possivel concluir a transferencia agora.',
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
  const pendingInvitations = teamInvitationsQuery.data?.data ?? [];
  const memberBeingRemovedId = removeMemberMutation.isPending ? memberPendingRemoval?.id ?? null : null;
  const memberBeingTransferredId = transferOwnershipMutation.isPending ? transferOwnershipMutation.variables ?? null : null;
  const brandingAssetBeingUploadedKind = uploadBrandingAssetMutation.isPending
    ? uploadBrandingAssetMutation.variables?.kind ?? null
    : null;
  const invitationBeingResentId = resendInvitationMutation.isPending ? resendInvitationMutation.variables?.invitationId ?? null : null;
  const invitationBeingRevokedId = revokeInvitationMutation.isPending ? invitationPendingRevoke?.id ?? null : null;
  const inviteFormIsValid = inviteForm.user.name.trim() !== ''
    && (inviteForm.user.phone?.trim() ?? '') !== ''
    && inviteForm.role_key !== '';

  const handleSaveOrganization = () => {
    const payload: { name: string; slug: string; custom_domain?: string } = {
      name: organizationForm.name.trim(),
      slug: organizationForm.slug.trim(),
    };

    if (canUseCustomDomain) {
      payload.custom_domain = organizationForm.custom_domain.trim();
    }

    organizationMutation.mutate(payload);
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

  const handleBrandingAssetFileChange = (kind: CurrentOrganizationBrandingAssetKind, file?: File) => {
    setSelectedBrandingAssetFiles((current) => ({
      ...current,
      [kind]: file,
    }));
  };

  const handleUploadBrandingAsset = (kind: CurrentOrganizationBrandingAssetKind) => {
    const file = selectedBrandingAssetFiles[kind];

    if (!file) {
      return;
    }

    uploadBrandingAssetMutation.mutate({ kind, file });
  };

  const handleInviteMember = () => {
    if (!inviteFormIsValid) {
      return;
    }

    inviteMemberMutation.mutate({
      user: {
        name: inviteForm.user.name.trim(),
        email: inviteForm.user.email?.trim() || undefined,
        phone: inviteForm.user.phone.trim(),
      },
      role_key: inviteForm.role_key,
      send_via_whatsapp: inviteForm.send_via_whatsapp,
    });
  };

  const copyInvitationLink = async (invitation: OrganizationTeamInvitation) => {
    if (!invitation.invitation_url) {
      return;
    }

    try {
      if (!navigator.clipboard?.writeText) {
        throw new Error('clipboard_unavailable');
      }

      await navigator.clipboard.writeText(invitation.invitation_url);

      toast({
        title: 'Link copiado',
        description: 'Agora voce pode compartilhar o convite manualmente.',
      });
    } catch {
      toast({
        title: 'Nao foi possivel copiar o link',
        description: 'Copie o link manualmente no quadro do convite.',
        variant: 'destructive',
      });
    }
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
      reply_text_fixed_templates: mediaIntelligenceGlobalSettingsQuery.data?.reply_text_fixed_templates ?? [],
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
              <div className="flex items-center gap-2">
                <Label htmlFor="organization-custom-domain">Dominio proprio</Label>
                <HelpTooltip text="Use quando o plano permite publicar experiencias no dominio da sua marca, como eventos.suaempresa.com." />
                {!canUseCustomDomain ? <Badge variant="outline">Plano premium</Badge> : null}
              </div>
              <Input
                id="organization-custom-domain"
                value={organizationForm.custom_domain}
                onChange={(event) => setOrganizationForm((current) => ({ ...current, custom_domain: event.target.value }))}
                placeholder="eventos.suaempresa.com"
                className="mt-1.5"
                disabled={!canManageSettings || !canUseCustomDomain}
              />
              {!canUseCustomDomain ? (
                <p className="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground">
                  <LockKeyhole className="h-3.5 w-3.5" />
                  Disponivel em planos com dominio proprio ou white-label.
                </p>
              ) : null}
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

              <div className="rounded-xl border border-border/50 bg-background/70 p-4">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <Palette className="h-4 w-4 text-primary" />
                      <h4 className="text-sm font-semibold">Ativos premium da marca</h4>
                      <HelpTooltip text="Estes arquivos ficam no nivel da organizacao. Eventos novos herdam capa, logos e cores quando nao tiverem identidade propria." />
                    </div>
                    <p className="text-xs text-muted-foreground">
                      Use para deixar as paginas publicas consistentes sem configurar cada evento manualmente.
                    </p>
                  </div>

                  <Badge variant={canUploadExpandedBranding ? 'default' : 'outline'}>
                    {canUploadExpandedBranding ? 'White-label ativo' : 'Depende do plano'}
                  </Badge>
                </div>

                <div className="mt-4 grid gap-3">
                  {PREMIUM_BRANDING_ASSETS.map((asset) => {
                    const allowed = asset.kind === 'watermark' ? canUploadWatermark : canUploadExpandedBranding;
                    const selectedFile = selectedBrandingAssetFiles[asset.kind];
                    const currentAssetUrl = organizationBrandingAssetUrl(organization, asset.kind);

                    return (
                      <div key={asset.kind} className="rounded-lg border border-border/50 bg-muted/20 p-3">
                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                          <div className="flex items-start gap-3">
                            <div className="rounded-lg bg-background p-2 text-muted-foreground">
                              {allowed ? <FileImage className="h-4 w-4" /> : <LockKeyhole className="h-4 w-4" />}
                            </div>
                            <div className="space-y-1">
                              <div className="flex flex-wrap items-center gap-2">
                                <p className="text-sm font-medium">{asset.label}</p>
                                {currentAssetUrl ? <Badge variant="secondary">Configurado</Badge> : null}
                              </div>
                              <p className="text-xs text-muted-foreground">{asset.description}</p>
                              {selectedFile ? (
                                <p className="text-xs text-muted-foreground">Selecionado: {selectedFile.name}</p>
                              ) : null}
                            </div>
                          </div>

                          <div className="flex flex-col gap-2 md:w-56">
                            <Input
                              aria-label={`Enviar ${asset.label}`}
                              type="file"
                              accept="image/png,image/jpeg,image/webp"
                              disabled={!allowed}
                              onChange={(event) => handleBrandingAssetFileChange(asset.kind, event.target.files?.[0])}
                            />
                            <Button
                              type="button"
                              variant="outline"
                              size="sm"
                              onClick={() => handleUploadBrandingAsset(asset.kind)}
                              disabled={!allowed || !selectedFile || brandingAssetBeingUploadedKind === asset.kind}
                            >
                              {brandingAssetBeingUploadedKind === asset.kind
                                ? <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                                : <Upload className="mr-1 h-4 w-4" />}
                              Enviar ativo
                            </Button>
                          </div>
                        </div>
                      </div>
                    );
                  })}
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
            <div className="space-y-4">
              <div className="glass rounded-xl p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                  <div className="space-y-2">
                    <div className="flex items-center gap-2">
                      <div className="rounded-full bg-primary/10 p-2 text-primary">
                        <Users className="h-5 w-5" />
                      </div>
                      <h3 className="font-semibold">Equipe e convites</h3>
                      <HelpTooltip text="A pessoa so entra na equipe depois de aceitar o convite. Enquanto isso, o link fica pendente para voce acompanhar." />
                    </div>
                    <p className="text-sm text-muted-foreground">
                      Convide secretaria, financeiro ou operadores sem compartilhar a sua conta principal.
                    </p>
                  </div>

                  <Button size="sm" onClick={() => setInviteDialogOpen(true)}>
                    <UserPlus className="mr-1 h-4 w-4" />
                    Convidar pessoa
                  </Button>
                </div>
              </div>

              <div className="glass space-y-4 rounded-xl p-6">
                <div className="flex items-center gap-2">
                  <ShieldCheck className="h-4 w-4 text-primary" />
                  <h4 className="font-medium">Membros ativos</h4>
                  <Badge variant="secondary">{teamMembers.length}</Badge>
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
                      Ainda nao ha ninguem ativo alem da conta principal.
                    </div>
                  )
                ) : (
                  <div className="space-y-2">
                    {teamMembers.map((teamMember) => (
                      <div key={teamMember.id} className="flex flex-col gap-3 rounded-lg bg-muted/30 p-4 md:flex-row md:items-center">
                        <div className="flex items-center gap-3">
                          <UserAvatar name={teamMember.user?.name || 'Usuario'} size="md" />
                          <div className="space-y-1">
                            <p className="text-sm font-medium">{teamMember.user?.name || 'Usuario sem nome'}</p>
                            <p className="text-xs text-muted-foreground">
                              {teamMember.user?.email || teamMember.user?.phone || 'Contato nao informado'}
                            </p>
                          </div>
                        </div>

                        <div className="flex flex-1 flex-wrap items-center gap-2 md:justify-end">
                          <Badge variant="outline" className="text-xs">
                            {formatRoleLabel(teamMember.role_key, teamMember.role_key)}
                          </Badge>

                          {teamMember.is_owner ? (
                            <Badge>Conta principal</Badge>
                          ) : (
                            <>
                              {canTransferOwnership ? (
                                <Button
                                  variant="outline"
                                  size="sm"
                                  aria-label={`Transferir titularidade para ${teamMember.user?.name || 'membro'}`}
                                  onClick={() => setMemberPendingOwnershipTransfer(teamMember)}
                                  disabled={memberBeingTransferredId === teamMember.id}
                                >
                                  {memberBeingTransferredId === teamMember.id ? <Loader2 className="mr-1 h-4 w-4 animate-spin" /> : <Crown className="mr-1 h-4 w-4" />}
                                  Tornar titular
                                </Button>
                              ) : null}

                              <Button
                                variant="ghost"
                                size="sm"
                                className="text-destructive"
                                aria-label={`Remover ${teamMember.user?.name || 'membro'}`}
                                onClick={() => setMemberPendingRemoval(teamMember)}
                                disabled={memberBeingRemovedId === teamMember.id}
                              >
                                {memberBeingRemovedId === teamMember.id ? <Loader2 className="mr-1 h-4 w-4 animate-spin" /> : <UserMinus className="mr-1 h-4 w-4" />}
                                Remover acesso
                              </Button>
                            </>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              <div className="glass space-y-4 rounded-xl p-6">
                <div className="flex items-center gap-2">
                  <CheckCircle2 className="h-4 w-4 text-primary" />
                  <h4 className="font-medium">Convites pendentes</h4>
                  <Badge variant="secondary">{pendingInvitations.length}</Badge>
                  <HelpTooltip text="Use esta area para reenviar o WhatsApp, copiar o link manual ou cancelar um convite que ainda nao foi aceito." />
                </div>

                {pendingInvitations.length === 0 ? (
                  teamInvitationsQuery.isLoading ? (
                    <div className="rounded-lg border border-dashed border-border/50 p-6 text-sm text-muted-foreground">
                      Carregando convites pendentes...
                    </div>
                  ) : teamInvitationsQuery.isError ? (
                    <div className="rounded-lg border border-destructive/30 p-6 text-sm text-destructive">
                      Nao foi possivel carregar os convites pendentes.
                    </div>
                  ) : (
                    <div className="rounded-lg border border-dashed border-border/50 p-6 text-sm text-muted-foreground">
                      Nenhum convite pendente por enquanto.
                    </div>
                  )
                ) : (
                  <div className="space-y-3">
                    {pendingInvitations.map((invitation) => (
                      <div key={invitation.id} className="space-y-3 rounded-lg border border-border/50 bg-background/80 p-4">
                        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                          <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                              <p className="text-sm font-medium">{invitation.invitee.name}</p>
                              <Badge variant="outline">{invitation.role_label}</Badge>
                              <Badge variant="secondary">{formatInvitationDeliveryLabel(invitation.delivery_status)}</Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                              {invitation.invitee.email || invitation.invitee.phone || 'Contato nao informado'}
                            </p>
                            <p className="text-xs text-muted-foreground">{invitation.role_description}</p>
                          </div>

                          <div className="flex flex-wrap gap-2">
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => copyInvitationLink(invitation)}
                              disabled={!invitation.invitation_url}
                            >
                              <Copy className="mr-1 h-4 w-4" />
                              Copiar link
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => resendInvitationMutation.mutate({ invitationId: invitation.id, sendViaWhatsApp: true })}
                              disabled={invitationBeingResentId === invitation.id}
                            >
                              {invitationBeingResentId === invitation.id ? <Loader2 className="mr-1 h-4 w-4 animate-spin" /> : <MessageSquare className="mr-1 h-4 w-4" />}
                              Reenviar WhatsApp
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              className="text-destructive"
                              onClick={() => setInvitationPendingRevoke(invitation)}
                              disabled={invitationBeingRevokedId === invitation.id}
                            >
                              {invitationBeingRevokedId === invitation.id ? <Loader2 className="mr-1 h-4 w-4 animate-spin" /> : <Trash2 className="mr-1 h-4 w-4" />}
                              Revogar convite
                            </Button>
                          </div>
                        </div>

                        <div className="flex flex-col gap-2 rounded-lg border border-dashed border-border/50 bg-muted/20 p-3 md:flex-row md:items-center md:justify-between">
                          <div className="space-y-1">
                            <p className="text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">Link do convite</p>
                            <p className="break-all text-xs text-muted-foreground">
                              {invitation.invitation_url || 'Link indisponivel'}
                            </p>
                          </div>
                          <p className="text-xs text-muted-foreground">
                            {invitation.delivery_status === 'queued'
                              ? 'Ja tentamos entregar pelo WhatsApp.'
                              : 'Voce pode copiar este link e compartilhar manualmente.'}
                          </p>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
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
            <DialogTitle>Convidar pessoa para sua equipe</DialogTitle>
            <DialogDescription>
              O acesso so sera liberado quando a pessoa aceitar o convite.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div>
              <Label htmlFor="invite-member-name">Nome *</Label>
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
              <Label htmlFor="invite-member-email">E-mail (opcional)</Label>
              <Input
                id="invite-member-email"
                placeholder="membro@organizacao.com"
                value={inviteForm.user.email ?? ''}
                onChange={(event) => setInviteForm((current) => ({
                  ...current,
                  user: { ...current.user, email: event.target.value },
                }))}
              />
            </div>

            <div>
              <Label htmlFor="invite-member-phone">WhatsApp *</Label>
              <Input
                id="invite-member-phone"
                placeholder="5511999999999"
                value={inviteForm.user.phone ?? ''}
                onChange={(event) => setInviteForm((current) => ({
                  ...current,
                  user: { ...current.user, phone: event.target.value },
                }))}
              />
            </div>

            <div>
              <Label htmlFor="invite-member-role">Perfil *</Label>
              <select
                id="invite-member-role"
                className="mt-1.5 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={inviteForm.role_key}
                onChange={(event) => setInviteForm((current) => ({
                  ...current,
                  role_key: event.target.value as InviteCurrentOrganizationTeamMemberRoleKey | '',
                }))}
              >
                <option value="" disabled>Selecione um perfil</option>
                {TEAM_ROLE_OPTIONS.map((option) => (
                  <option key={option.key} value={option.key}>{option.label}</option>
                ))}
              </select>
            </div>

            {inviteForm.role_key ? (
              <div className="rounded-lg border border-border/50 bg-muted/20 p-3 text-sm text-muted-foreground">
                {TEAM_ROLE_OPTIONS.find((option) => option.key === inviteForm.role_key)?.description}
              </div>
            ) : null}

            <div className="flex items-start gap-3 rounded-lg border border-border/50 bg-background/70 p-3">
              <Checkbox
                id="invite-member-send-whatsapp"
                checked={inviteForm.send_via_whatsapp ?? true}
                onCheckedChange={(checked) => setInviteForm((current) => ({
                  ...current,
                  send_via_whatsapp: Boolean(checked),
                }))}
              />
              <div className="space-y-1">
                <div className="flex items-center gap-2">
                  <Label htmlFor="invite-member-send-whatsapp">Enviar convite pelo WhatsApp</Label>
                  <HelpTooltip text="Se a organizacao tiver uma instancia conectada, o sistema envia o link automaticamente. Sem isso, o link fica pronto para copia manual." />
                </div>
                <p className="text-xs text-muted-foreground">
                  Quando nao houver instancia disponivel, o convite continua criado com link para compartilhamento manual.
                </p>
              </div>
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setInviteDialogOpen(false)}>
              Cancelar
            </Button>
            <Button onClick={handleInviteMember} disabled={inviteMemberMutation.isPending || !inviteFormIsValid}>
              {inviteMemberMutation.isPending ? <Loader2 className="mr-1 h-4 w-4 animate-spin" /> : <Plus className="mr-1 h-4 w-4" />}
              Criar convite
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={memberPendingRemoval !== null} onOpenChange={(open) => {
        if (!open) {
          setMemberPendingRemoval(null);
        }
      }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remover membro da equipe?</AlertDialogTitle>
            <AlertDialogDescription>
              Esta acao remove o acesso de {memberPendingRemoval?.user?.name || 'este membro'} da organizacao atual.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => {
                if (!memberPendingRemoval) {
                  return;
                }

                removeMemberMutation.mutate(memberPendingRemoval.id);
                setMemberPendingRemoval(null);
              }}
            >
              Confirmar remocao
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={memberPendingOwnershipTransfer !== null} onOpenChange={(open) => {
        if (!open) {
          setMemberPendingOwnershipTransfer(null);
        }
      }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Transferir titularidade da organizacao?</AlertDialogTitle>
            <AlertDialogDescription>
              {memberPendingOwnershipTransfer?.user?.name || 'Esta pessoa'} passara a ser a conta principal.
              Seu acesso continua ativo, mas como gerente da organizacao.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                if (!memberPendingOwnershipTransfer) {
                  return;
                }

                transferOwnershipMutation.mutate(memberPendingOwnershipTransfer.id);
                setMemberPendingOwnershipTransfer(null);
              }}
            >
              Confirmar transferencia
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={invitationPendingRevoke !== null} onOpenChange={(open) => {
        if (!open) {
          setInvitationPendingRevoke(null);
        }
      }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Revogar este convite?</AlertDialogTitle>
            <AlertDialogDescription>
              O link atual deixara de funcionar para {invitationPendingRevoke?.invitee.name || 'esta pessoa'} imediatamente.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => {
                if (!invitationPendingRevoke) {
                  return;
                }

                revokeInvitationMutation.mutate(invitationPendingRevoke.id);
                setInvitationPendingRevoke(null);
              }}
            >
              Revogar convite
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </motion.div>
  );
}
