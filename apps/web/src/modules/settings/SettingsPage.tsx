import { useMemo } from 'react';
import { motion } from 'framer-motion';
import { Link } from 'react-router-dom';
import { MessageSquare, Plus, Save, Send, Trash2, Upload } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAuth } from '@/app/providers/AuthProvider';
import { useToast } from '@/hooks/use-toast';
import { WHATSAPP_SETTINGS_PATH } from '@/modules/whatsapp/paths';
import { formatRoleLabel } from '@/shared/auth/labels';
import { SYSTEM_MODULES } from '@/shared/auth/modules';
import { ROLE_DEFAULT_PERMISSIONS, type Permission } from '@/shared/auth/permissions';
import { UserAvatar } from '@/shared/components/UserAvatar';
import { mockUsers } from '@/shared/mock/data';
import { PageHeader } from '@/shared/components/PageHeader';
import type { UserRole } from '@/shared/types';

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

export default function SettingsPage() {
  const { meUser: user, meOrganization: organization, can } = useAuth();
  const { toast } = useToast();

  const currentRoleLabel = formatRoleLabel(user?.role.key, user?.role.name);

  const teamMembers = useMemo(() => {
    if (!organization?.uuid) {
      return [];
    }

    return mockUsers.filter((teamMember) => teamMember.organizationId === organization.uuid);
  }, [organization?.uuid]);

  const save = () => {
    toast({
      title: 'Configurações salvas',
      description: 'Alterações salvas com sucesso (mock)',
    });
  };

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader title="Configurações" description="Gerencie sua conta e organização" />

      <Tabs defaultValue="profile">
        <TabsList className="flex-wrap bg-muted/50">
          <TabsTrigger value="profile">Perfil</TabsTrigger>
          <TabsTrigger value="organization">Organização</TabsTrigger>
          {can('branding.manage') && <TabsTrigger value="branding">Branding</TabsTrigger>}
          {can('team.manage') && <TabsTrigger value="team">Equipe</TabsTrigger>}
          {can('settings.manage') && <TabsTrigger value="permissions">Permissões</TabsTrigger>}
          {can('integrations.manage') && <TabsTrigger value="integrations">Integrações</TabsTrigger>}
          <TabsTrigger value="preferences">Preferências</TabsTrigger>
        </TabsList>

        <TabsContent value="profile" className="mt-6">
          <div className="glass max-w-xl rounded-xl p-6 space-y-4">
            <h3 className="font-semibold">Dados do Perfil</h3>

            <div className="mb-4 flex items-center gap-4">
              <UserAvatar name={user?.name || ''} avatarUrl={user?.avatar_url} size="lg" />

              <div className="space-y-1.5">
                <Button variant="outline" size="sm" asChild>
                  <Link to="/profile">
                    <Upload className="mr-1 h-4 w-4" />
                    Editar Perfil
                  </Link>
                </Button>

                <p className="text-[10px] text-muted-foreground">
                  Clique para acessar a página completa
                </p>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Nome</Label>
                <Input defaultValue={user?.name} className="mt-1.5" disabled />
              </div>

              <div>
                <Label>E-mail</Label>
                <Input defaultValue={user?.email} className="mt-1.5" disabled />
              </div>
            </div>

            <div>
              <Label>Cargo / Perfil</Label>
              <Input defaultValue={currentRoleLabel} disabled className="mt-1.5" />
            </div>
          </div>
        </TabsContent>

        <TabsContent value="organization" className="mt-6">
          <div className="glass max-w-xl rounded-xl p-6 space-y-4">
            <h3 className="font-semibold">Dados da Organização</h3>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Nome</Label>
                <Input defaultValue={organization?.name} className="mt-1.5" />
              </div>

              <div>
                <Label>Slug</Label>
                <Input defaultValue={organization?.slug} className="mt-1.5" />
              </div>
            </div>

            <div>
              <Label>Domínio Customizado</Label>
              <Input placeholder="eventos.suaempresa.com" className="mt-1.5" />
            </div>

            <Button onClick={save}>
              <Save className="mr-1 h-4 w-4" />
              Salvar
            </Button>
          </div>
        </TabsContent>

        {can('branding.manage') && (
          <TabsContent value="branding" className="mt-6">
            <div className="glass max-w-xl rounded-xl p-6 space-y-4">
              <h3 className="font-semibold">Branding</h3>

              <div>
                <Label>Logo</Label>
                <div className="mt-1.5 rounded-lg border-2 border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                  Arraste ou clique para enviar
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label>Cor Primária</Label>
                  <div className="mt-1.5 flex gap-2">
                    <Input
                      type="color"
                      defaultValue={organization?.branding?.primary_color || '#7c3aed'}
                      className="h-9 w-12 p-1"
                    />
                    <Input defaultValue={organization?.branding?.primary_color || '#7c3aed'} />
                  </div>
                </div>

                <div>
                  <Label>Cor Secundária</Label>
                  <div className="mt-1.5 flex gap-2">
                    <Input
                      type="color"
                      defaultValue={organization?.branding?.secondary_color || '#3b82f6'}
                      className="h-9 w-12 p-1"
                    />
                    <Input defaultValue={organization?.branding?.secondary_color || '#3b82f6'} />
                  </div>
                </div>
              </div>

              <Button onClick={save}>
                <Save className="mr-1 h-4 w-4" />
                Salvar
              </Button>
            </div>
          </TabsContent>
        )}

        {can('team.manage') && (
          <TabsContent value="team" className="mt-6">
            <div className="glass rounded-xl p-6 space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="font-semibold">Membros da Equipe</h3>
                <Button size="sm">
                  <Plus className="mr-1 h-4 w-4" />
                  Convidar
                </Button>
              </div>

              {teamMembers.length === 0 ? (
                <div className="rounded-lg border border-dashed border-border/50 p-6 text-sm text-muted-foreground">
                  Nenhum membro de equipe disponível para esta organização no modo atual.
                </div>
              ) : (
                <div className="space-y-2">
                  {teamMembers.map((teamMember) => (
                    <div key={teamMember.id} className="flex items-center gap-3 rounded-lg bg-muted/30 p-3">
                      <UserAvatar name={teamMember.name} size="md" />

                      <div className="flex-1">
                        <p className="text-sm font-medium">{teamMember.name}</p>
                        <p className="text-xs text-muted-foreground">{teamMember.email}</p>
                      </div>

                      <Badge variant="outline" className="text-xs">
                        {formatRoleLabel(teamMember.role.replace(/_/g, '-'), teamMember.role)}
                      </Badge>

                      <Button variant="ghost" size="icon" className="text-destructive">
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </TabsContent>
        )}

        {can('settings.manage') && (
          <TabsContent value="permissions" className="mt-6">
            <div className="glass rounded-xl p-6 space-y-4">
              <h3 className="font-semibold">Permissões por Perfil</h3>

              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-border/50">
                      <th className="py-2 pr-4 text-left">Módulo</th>
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
        )}

        {can('integrations.manage') && (
          <TabsContent value="integrations" className="mt-6">
            <div className="grid max-w-2xl grid-cols-1 gap-4 md:grid-cols-2">
              {[
                {
                  name: 'WhatsApp',
                  icon: MessageSquare,
                  connected: true,
                  desc: 'Receba fotos via WhatsApp Business',
                  actionLabel: 'Gerenciar',
                  actionPath: WHATSAPP_SETTINGS_PATH,
                },
                {
                  name: 'Telegram',
                  icon: Send,
                  connected: false,
                  desc: 'Receba fotos via Bot do Telegram',
                  actionLabel: 'Conectar',
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

                  {integration.actionPath ? (
                    <Button asChild variant="outline" size="sm">
                      <Link to={integration.actionPath}>{integration.actionLabel}</Link>
                    </Button>
                  ) : (
                    <Button
                      variant={integration.connected ? 'outline' : 'default'}
                      size="sm"
                      className={!integration.connected ? 'gradient-primary border-0' : ''}
                    >
                      {integration.actionLabel}
                    </Button>
                  )}
                </div>
              ))}
            </div>
          </TabsContent>
        )}

        <TabsContent value="preferences" className="mt-6">
          <div className="glass max-w-xl rounded-xl p-6 space-y-4">
            <h3 className="font-semibold">Preferências</h3>

            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <div>
                  <Label>Notificações por e-mail</Label>
                  <p className="text-xs text-muted-foreground">
                    Receber alertas importantes por e-mail
                  </p>
                </div>
                <Switch defaultChecked />
              </div>

              <div className="flex items-center justify-between">
                <div>
                  <Label>Notificações push</Label>
                  <p className="text-xs text-muted-foreground">
                    Notificações no navegador
                  </p>
                </div>
                <Switch />
              </div>

              <div className="flex items-center justify-between">
                <div>
                  <Label>Modo compacto</Label>
                  <p className="text-xs text-muted-foreground">
                    Reduzir espaçamentos da interface
                  </p>
                </div>
                <Switch />
              </div>
            </div>

            <Button onClick={save}>
              <Save className="mr-1 h-4 w-4" />
              Salvar
            </Button>
          </div>
        </TabsContent>
      </Tabs>
    </motion.div>
  );
}
