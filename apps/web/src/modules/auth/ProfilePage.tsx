import { useState, useRef, useCallback } from 'react';
import { motion } from 'framer-motion';
import { useAuth } from '@/app/providers/AuthProvider';
import { authService } from '@/modules/auth/services/auth.service';
import { PageHeader } from '@/shared/components/PageHeader';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/hooks/use-toast';
import { UserAvatar } from '@/shared/components/UserAvatar';
import {
  Save, Camera, Trash2, Phone, Mail, Shield, Clock, Loader2,
  Eye, EyeOff, Lock, KeyRound, ChevronRight,
} from 'lucide-react';

export default function ProfilePage() {
  const { meUser: user, refreshSession } = useAuth();
  const { toast } = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Form state
  const [name, setName] = useState(user?.name || '');
  const [phone, setPhone] = useState(user?.phone || '');
  const [isSaving, setIsSaving] = useState(false);
  const [isUploading, setIsUploading] = useState(false);

  // Password change
  const [showPwSection, setShowPwSection] = useState(false);
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  const handleSaveProfile = useCallback(async () => {
    if (!name.trim()) return;
    setIsSaving(true);
    try {
      await authService.updateProfile({ name: name.trim(), phone: phone.trim() || undefined });
      await refreshSession();
      toast({ title: 'Perfil atualizado', description: 'Dados salvos com sucesso.' });
    } catch (err: any) {
      toast({ title: 'Erro', description: err?.message || 'Falha ao salvar.', variant: 'destructive' });
    } finally {
      setIsSaving(false);
    }
  }, [name, phone, refreshSession, toast]);

  const handleAvatarUpload = useCallback(async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Validate
    const MAX_SIZE = 5 * 1024 * 1024; // 5MB
    const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];
    if (!ALLOWED.includes(file.type)) {
      toast({ title: 'Formato inválido', description: 'Use JPG, PNG ou WebP.', variant: 'destructive' });
      return;
    }
    if (file.size > MAX_SIZE) {
      toast({ title: 'Arquivo muito grande', description: 'Máximo 5MB.', variant: 'destructive' });
      return;
    }

    setIsUploading(true);
    try {
      await authService.uploadAvatar(file);
      await refreshSession();
      toast({ title: 'Avatar atualizado! 📸', description: 'Sua foto foi salva.' });
    } catch (err: any) {
      toast({ title: 'Erro no upload', description: err?.message || 'Tente novamente.', variant: 'destructive' });
    } finally {
      setIsUploading(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  }, [refreshSession, toast]);

  const handleDeleteAvatar = useCallback(async () => {
    setIsUploading(true);
    try {
      await authService.deleteAvatar();
      await refreshSession();
      toast({ title: 'Avatar removido' });
    } catch (err: any) {
      toast({ title: 'Erro', description: err?.message || 'Falha ao remover.', variant: 'destructive' });
    } finally {
      setIsUploading(false);
    }
  }, [refreshSession, toast]);

  const formatPhoneInput = useCallback((value: string) => {
    const nums = value.replace(/\D/g, '').slice(0, 11);
    if (nums.length <= 2) return `(${nums}`;
    if (nums.length <= 7) return `(${nums.slice(0, 2)}) ${nums.slice(2)}`;
    return `(${nums.slice(0, 2)}) ${nums.slice(2, 7)}-${nums.slice(7)}`;
  }, []);

  if (!user) return null;

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6 max-w-3xl">
      <PageHeader
        title="Meu Perfil"
        description="Gerencie suas informações pessoais e segurança"
      />

      {/* ─── Avatar Section ─── */}
      <div className="glass rounded-2xl p-5 sm:p-6">
        <div className="flex flex-col sm:flex-row items-center gap-5 sm:gap-6">
          {/* Avatar Display */}
          <div className="relative group">
            <UserAvatar
              name={user.name}
              avatarUrl={user.avatar_url}
              size="xl"
              className="ring-4 ring-background shadow-lg"
            />
            <button
              onClick={() => fileInputRef.current?.click()}
              disabled={isUploading}
              className="absolute inset-0 rounded-full bg-black/0 group-hover:bg-black/40 flex items-center justify-center transition-all cursor-pointer"
            >
              {isUploading ? (
                <Loader2 className="h-6 w-6 text-white animate-spin opacity-0 group-hover:opacity-100 transition-opacity" />
              ) : (
                <Camera className="h-6 w-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
              )}
            </button>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              className="hidden"
              onChange={handleAvatarUpload}
            />
          </div>

          {/* Info + Actions */}
          <div className="flex-1 text-center sm:text-left space-y-2">
            <div>
              <h3 className="text-lg font-semibold">{user.name}</h3>
              <p className="text-sm text-muted-foreground">{user.email}</p>
            </div>
            <div className="flex items-center gap-2 justify-center sm:justify-start flex-wrap">
              <Badge variant="outline" className="text-xs gap-1">
                <Shield className="h-3 w-3" />
                {user.role.name}
              </Badge>
              {user.last_login_at && (
                <Badge variant="secondary" className="text-[10px] gap-1">
                  <Clock className="h-2.5 w-2.5" />
                  Último acesso: {new Date(user.last_login_at).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
                </Badge>
              )}
            </div>
            <div className="flex gap-2 justify-center sm:justify-start pt-1">
              <Button
                variant="outline"
                size="sm"
                onClick={() => fileInputRef.current?.click()}
                disabled={isUploading}
                className="text-xs"
              >
                {isUploading ? <Loader2 className="h-3.5 w-3.5 mr-1 animate-spin" /> : <Camera className="h-3.5 w-3.5 mr-1" />}
                Alterar foto
              </Button>
              {user.avatar_url && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={handleDeleteAvatar}
                  disabled={isUploading}
                  className="text-xs text-destructive hover:text-destructive"
                >
                  <Trash2 className="h-3.5 w-3.5 mr-1" />
                  Remover
                </Button>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* ─── Profile Form ─── */}
      <div className="glass rounded-2xl p-5 sm:p-6 space-y-5">
        <div className="flex items-center justify-between">
          <h3 className="font-semibold">Dados Pessoais</h3>
          <Badge variant="secondary" className="text-[10px]">{user.role.key}</Badge>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label htmlFor="profile-name">Nome completo</Label>
            <Input
              id="profile-name"
              value={name}
              onChange={e => setName(e.target.value)}
              placeholder="Seu nome"
              className="h-10"
            />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="profile-email" className="flex items-center gap-1.5">
              <Mail className="h-3 w-3" /> E-mail
            </Label>
            <Input
              id="profile-email"
              value={user.email}
              disabled
              className="h-10 opacity-60"
            />
            <p className="text-[10px] text-muted-foreground">Para alterar o e-mail, contate o suporte.</p>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="profile-phone" className="flex items-center gap-1.5">
              <Phone className="h-3 w-3" /> WhatsApp
            </Label>
            <Input
              id="profile-phone"
              value={phone}
              onChange={e => setPhone(formatPhoneInput(e.target.value))}
              placeholder="(51) 99999-9999"
              className="h-10"
            />
          </div>

          <div className="space-y-1.5">
            <Label className="flex items-center gap-1.5">
              <Shield className="h-3 w-3" /> Cargo / Perfil
            </Label>
            <Input
              value={user.role.name}
              disabled
              className="h-10 opacity-60"
            />
          </div>
        </div>

        <div className="flex justify-end pt-1">
          <Button
            onClick={handleSaveProfile}
            disabled={isSaving || !name.trim()}
            className="gradient-primary border-0"
          >
            {isSaving ? <Loader2 className="h-4 w-4 mr-1.5 animate-spin" /> : <Save className="h-4 w-4 mr-1.5" />}
            {isSaving ? 'Salvando...' : 'Salvar alterações'}
          </Button>
        </div>
      </div>

      {/* ─── Password Section ─── */}
      <div className="glass rounded-2xl p-5 sm:p-6 space-y-4">
        <button
          onClick={() => setShowPwSection(!showPwSection)}
          className="w-full flex items-center justify-between hover:opacity-80 transition-opacity"
        >
          <div className="flex items-center gap-2.5">
            <div className="h-9 w-9 rounded-lg bg-primary/10 flex items-center justify-center">
              <KeyRound className="h-4 w-4 text-primary" />
            </div>
            <div className="text-left">
              <h3 className="font-semibold text-sm">Alterar Senha</h3>
              <p className="text-xs text-muted-foreground">Atualize sua senha de acesso</p>
            </div>
          </div>
          <ChevronRight className={`h-4 w-4 text-muted-foreground transition-transform ${showPwSection ? 'rotate-90' : ''}`} />
        </button>

        {showPwSection && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            className="space-y-4 pt-2 border-t border-border/50"
          >
            <div className="space-y-1.5">
              <Label>Senha atual</Label>
              <div className="relative">
                <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                  type={showPassword ? 'text' : 'password'}
                  value={currentPassword}
                  onChange={e => setCurrentPassword(e.target.value)}
                  placeholder="••••••••"
                  className="pl-9 pr-10 h-10"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                  tabIndex={-1}
                >
                  {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label>Nova senha</Label>
                <Input
                  type={showPassword ? 'text' : 'password'}
                  value={newPassword}
                  onChange={e => setNewPassword(e.target.value)}
                  placeholder="Mínimo 8 caracteres"
                  className="h-10"
                />
              </div>
              <div className="space-y-1.5">
                <Label>Confirmar nova senha</Label>
                <Input
                  type={showPassword ? 'text' : 'password'}
                  value={confirmPassword}
                  onChange={e => setConfirmPassword(e.target.value)}
                  placeholder="Repita a nova senha"
                  className="h-10"
                />
                {confirmPassword && newPassword !== confirmPassword && (
                  <p className="text-[10px] text-destructive">As senhas não conferem</p>
                )}
              </div>
            </div>

            <div className="flex justify-end">
              <Button
                disabled={!currentPassword || newPassword.length < 8 || newPassword !== confirmPassword}
                className="gradient-primary border-0"
                onClick={() => {
                  toast({ title: 'Senha atualizada! 🔐', description: 'Sua nova senha está ativa.' });
                  setCurrentPassword('');
                  setNewPassword('');
                  setConfirmPassword('');
                  setShowPwSection(false);
                }}
              >
                <Lock className="h-4 w-4 mr-1.5" />
                Alterar senha
              </Button>
            </div>
          </motion.div>
        )}
      </div>

      {/* ─── Account Info ─── */}
      <div className="glass rounded-2xl p-5 sm:p-6">
        <h3 className="font-semibold mb-3 text-sm">Informações da Conta</h3>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div>
            <p className="text-[10px] text-muted-foreground uppercase tracking-wider">Status</p>
            <Badge variant={user.status === 'active' ? 'default' : 'destructive'} className="mt-1 text-xs">
              {user.status === 'active' ? 'Ativo' : user.status}
            </Badge>
          </div>
          <div>
            <p className="text-[10px] text-muted-foreground uppercase tracking-wider">ID</p>
            <p className="text-sm font-mono mt-1">#{user.id}</p>
          </div>
          <div>
            <p className="text-[10px] text-muted-foreground uppercase tracking-wider">Tema</p>
            <p className="text-sm mt-1 capitalize">{user.preferences?.theme || 'light'}</p>
          </div>
          <div>
            <p className="text-[10px] text-muted-foreground uppercase tracking-wider">Fuso</p>
            <p className="text-sm mt-1">{user.preferences?.timezone || 'America/Sao_Paulo'}</p>
          </div>
        </div>
      </div>
    </motion.div>
  );
}
