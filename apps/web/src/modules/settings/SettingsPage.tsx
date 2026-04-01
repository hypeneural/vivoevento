import { motion } from 'framer-motion';
import { PageHeader } from '@/shared/components/PageHeader';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { useAuth } from '@/app/providers/AuthProvider';
import { mockUsers } from '@/shared/mock/data';
import { useToast } from '@/hooks/use-toast';
import { UserAvatar } from '@/shared/components/UserAvatar';
import { Save, Upload, Trash2, Plus, MessageSquare, Send } from 'lucide-react';
import { Link } from 'react-router-dom';

// Role display map (kebab-case keys from backend)
const ROLE_DISPLAY: Record<string, string> = {
  'super-admin': 'Super Admin',
  'platform-admin': 'Admin Plataforma',
  'partner-owner': 'Dono Parceiro',
  'partner-manager': 'Gerente',
  'event-operator': 'Operador',
  'financial': 'Financeiro',
  'viewer': 'Visualizador',
  // Legacy snake_case fallback
  'super_admin': 'Super Admin',
  'platform_admin': 'Admin Plataforma',
  'partner_owner': 'Dono Parceiro',
  'partner_manager': 'Gerente',
  'event_operator': 'Operador',
};

const ALL_ROLES = ['Super Admin', 'Admin', 'Dono', 'Gerente', 'Operador', 'Viewer'];
const ALL_MODULES = ['Dashboard', 'Eventos', 'Mídias', 'Moderação', 'Galeria', 'Wall', 'Play', 'Hub', 'Parceiros', 'Planos', 'Analytics', 'Auditoria', 'Configurações'];

export default function SettingsPage() {
  const { meUser: user, meOrganization: organization, can } = useAuth();
  const { toast } = useToast();
  const save = () => toast({ title: 'Configurações salvas', description: 'Alterações salvas com sucesso (mock)' });

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader title="Configurações" description="Gerencie sua conta e organização" />

      <Tabs defaultValue="profile">
        <TabsList className="bg-muted/50 flex-wrap">
          <TabsTrigger value="profile">Perfil</TabsTrigger>
          <TabsTrigger value="organization">Organização</TabsTrigger>
          <TabsTrigger value="branding">Branding</TabsTrigger>
          <TabsTrigger value="team">Equipe</TabsTrigger>
          {can('settings.manage') && <TabsTrigger value="permissions">Permissões</TabsTrigger>}
          <TabsTrigger value="integrations">Integrações</TabsTrigger>
          <TabsTrigger value="preferences">Preferências</TabsTrigger>
        </TabsList>

        <TabsContent value="profile" className="mt-6">
          <div className="glass rounded-xl p-6 max-w-xl space-y-4">
            <h3 className="font-semibold">Dados do Perfil</h3>
            <div className="flex items-center gap-4 mb-4">
              <UserAvatar name={user?.name || ''} avatarUrl={user?.avatar_url} size="lg" />
              <div className="space-y-1.5">
                <Button variant="outline" size="sm" asChild>
                  <Link to="/profile">
                    <Upload className="h-4 w-4 mr-1" /> Editar Perfil
                  </Link>
                </Button>
                <p className="text-[10px] text-muted-foreground">Clique para acessar a página completa</p>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div><Label>Nome</Label><Input defaultValue={user?.name} className="mt-1.5" disabled /></div>
              <div><Label>E-mail</Label><Input defaultValue={user?.email} className="mt-1.5" disabled /></div>
            </div>
            <div><Label>Cargo / Perfil</Label><Input defaultValue={user?.role.name || 'Visualizador'} disabled className="mt-1.5" /></div>
          </div>
        </TabsContent>

        <TabsContent value="organization" className="mt-6">
          <div className="glass rounded-xl p-6 max-w-xl space-y-4">
            <h3 className="font-semibold">Dados da Organização</h3>
            <div className="grid grid-cols-2 gap-4">
              <div><Label>Nome</Label><Input defaultValue={organization?.name} className="mt-1.5" /></div>
              <div><Label>Slug</Label><Input defaultValue={organization?.slug} className="mt-1.5" /></div>
            </div>
            <div><Label>Domínio Customizado</Label><Input placeholder="eventos.suaempresa.com" className="mt-1.5" /></div>
            <Button onClick={save}><Save className="h-4 w-4 mr-1" /> Salvar</Button>
          </div>
        </TabsContent>

        <TabsContent value="branding" className="mt-6">
          <div className="glass rounded-xl p-6 max-w-xl space-y-4">
            <h3 className="font-semibold">Branding</h3>
            <div><Label>Logo</Label>
              <div className="mt-1.5 border-2 border-dashed border-border rounded-lg p-8 text-center text-sm text-muted-foreground">Arraste ou clique para enviar</div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div><Label>Cor Primária</Label><div className="flex gap-2 mt-1.5"><Input type="color" defaultValue={organization?.branding?.primary_color || '#7c3aed'} className="w-12 h-9 p-1" /><Input defaultValue={organization?.branding?.primary_color || '#7c3aed'} /></div></div>
              <div><Label>Cor Secundária</Label><div className="flex gap-2 mt-1.5"><Input type="color" defaultValue={organization?.branding?.secondary_color || '#3b82f6'} className="w-12 h-9 p-1" /><Input defaultValue={organization?.branding?.secondary_color || '#3b82f6'} /></div></div>
            </div>
            <Button onClick={save}><Save className="h-4 w-4 mr-1" /> Salvar</Button>
          </div>
        </TabsContent>

        <TabsContent value="team" className="mt-6">
          <div className="glass rounded-xl p-6 space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="font-semibold">Membros da Equipe</h3>
              <Button size="sm"><Plus className="h-4 w-4 mr-1" /> Convidar</Button>
            </div>
            <div className="space-y-2">
              {mockUsers.map(u => (
                <div key={u.id} className="flex items-center gap-3 p-3 rounded-lg bg-muted/30">
                  <UserAvatar name={u.name} size="md" />
                  <div className="flex-1">
                    <p className="text-sm font-medium">{u.name}</p>
                    <p className="text-xs text-muted-foreground">{u.email}</p>
                  </div>
                  <Badge variant="outline" className="text-xs">{ROLE_DISPLAY[u.role] || u.role}</Badge>
                  <Button variant="ghost" size="icon" className="text-destructive"><Trash2 className="h-4 w-4" /></Button>
                </div>
              ))}
            </div>
          </div>
        </TabsContent>

        {can('settings.manage') && (
          <TabsContent value="permissions" className="mt-6">
            <div className="glass rounded-xl p-6 space-y-4">
              <h3 className="font-semibold">Permissões por Perfil</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-border/50">
                      <th className="text-left py-2 pr-4">Módulo</th>
                      {ALL_ROLES.map(r => <th key={r} className="text-center py-2 px-3 text-xs text-muted-foreground">{r}</th>)}
                    </tr>
                  </thead>
                  <tbody>
                    {ALL_MODULES.map(mod => (
                      <tr key={mod} className="border-b border-border/20">
                        <td className="py-2 pr-4">{mod}</td>
                        {ALL_ROLES.map((r, i) => (
                          <td key={r} className="text-center py-2 px-3"><Switch defaultChecked={i < 4} className="scale-75" /></td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </TabsContent>
        )}

        <TabsContent value="integrations" className="mt-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-2xl">
            {[
              { name: 'WhatsApp', icon: MessageSquare, connected: true, desc: 'Receba fotos via WhatsApp Business' },
              { name: 'Telegram', icon: Send, connected: false, desc: 'Receba fotos via Bot do Telegram' },
            ].map(int => (
              <div key={int.name} className="glass rounded-xl p-5 card-hover">
                <div className="flex items-center gap-3 mb-3">
                  <div className="rounded-lg bg-primary/10 p-2"><int.icon className="h-5 w-5 text-primary" /></div>
                  <div className="flex-1">
                    <p className="font-medium">{int.name}</p>
                    <p className="text-xs text-muted-foreground">{int.desc}</p>
                  </div>
                </div>
                <Button variant={int.connected ? 'outline' : 'default'} size="sm" className={!int.connected ? 'gradient-primary border-0' : ''}>
                  {int.connected ? 'Configurar' : 'Conectar'}
                </Button>
              </div>
            ))}
          </div>
        </TabsContent>

        <TabsContent value="preferences" className="mt-6">
          <div className="glass rounded-xl p-6 max-w-xl space-y-4">
            <h3 className="font-semibold">Preferências</h3>
            <div className="space-y-3">
              <div className="flex items-center justify-between"><div><Label>Notificações por e-mail</Label><p className="text-xs text-muted-foreground">Receber alertas importantes por e-mail</p></div><Switch defaultChecked /></div>
              <div className="flex items-center justify-between"><div><Label>Notificações push</Label><p className="text-xs text-muted-foreground">Notificações no navegador</p></div><Switch /></div>
              <div className="flex items-center justify-between"><div><Label>Modo compacto</Label><p className="text-xs text-muted-foreground">Reduzir espaçamentos da interface</p></div><Switch /></div>
            </div>
            <Button onClick={save}><Save className="h-4 w-4 mr-1" /> Salvar</Button>
          </div>
        </TabsContent>
      </Tabs>
    </motion.div>
  );
}
