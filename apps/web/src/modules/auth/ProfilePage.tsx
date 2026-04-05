import { type ChangeEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { motion } from 'framer-motion';
import {
  Camera,
  ChevronRight,
  Clock,
  Eye,
  EyeOff,
  KeyRound,
  Loader2,
  Lock,
  Mail,
  Phone,
  Save,
  Shield,
  Trash2,
} from 'lucide-react';
import { useAuth } from '@/app/providers/AuthProvider';
import { authService } from '@/modules/auth/services/auth.service';
import { AvatarCropDialog } from '@/modules/auth/components/AvatarCropDialog';
import { PageHeader } from '@/shared/components/PageHeader';
import { formatRoleLabel, formatThemeLabel, formatUserStatusLabel } from '@/shared/auth/labels';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/hooks/use-toast';
import { UserAvatar } from '@/shared/components/UserAvatar';

type PasswordErrors = {
  current_password?: string;
  password?: string;
};

function formatPhoneInput(value: string): string {
  const numbers = value.replace(/\D/g, '').slice(0, 11);

  if (numbers.length <= 2) return `(${numbers}`;
  if (numbers.length <= 7) return `(${numbers.slice(0, 2)}) ${numbers.slice(2)}`;

  return `(${numbers.slice(0, 2)}) ${numbers.slice(2, 7)}-${numbers.slice(7)}`;
}

function formatLastAccess(value: string | null): string {
  if (!value) return 'Nao informado';

  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function buildAvatarFileName(originalName: string | null): string {
  const baseName = (originalName || 'avatar')
    .replace(/\.[^.]+$/, '')
    .trim()
    .toLowerCase()
    .replace(/\s+/g, '-');

  return `${baseName || 'avatar'}.webp`;
}

export default function ProfilePage() {
  const { meUser: user, refreshSession } = useAuth();
  const { toast } = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [isSaving, setIsSaving] = useState(false);
  const [isUploading, setIsUploading] = useState(false);

  const [isCropOpen, setIsCropOpen] = useState(false);
  const [selectedAvatarUrl, setSelectedAvatarUrl] = useState<string | null>(null);
  const [selectedAvatarName, setSelectedAvatarName] = useState<string | null>(null);

  const [showPasswordSection, setShowPasswordSection] = useState(false);
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showPasswords, setShowPasswords] = useState(false);
  const [isChangingPassword, setIsChangingPassword] = useState(false);
  const [passwordErrors, setPasswordErrors] = useState<PasswordErrors>({});

  useEffect(() => {
    if (!user) return;

    setName(user.name);
    setPhone(user.phone || '');
  }, [user]);

  useEffect(() => {
    return () => {
      if (selectedAvatarUrl) {
        URL.revokeObjectURL(selectedAvatarUrl);
      }
    };
  }, [selectedAvatarUrl]);

  const roleLabel = useMemo(
    () => formatRoleLabel(user?.role.key, user?.role.name),
    [user?.role.key, user?.role.name],
  );
  const statusLabel = useMemo(
    () => formatUserStatusLabel(user?.status),
    [user?.status],
  );
  const themeLabel = useMemo(
    () => formatThemeLabel(user?.preferences?.theme),
    [user?.preferences?.theme],
  );
  const passwordMismatch = confirmPassword.length > 0 && newPassword !== confirmPassword;
  const canSubmitPassword = Boolean(currentPassword)
    && newPassword.length >= 8
    && !passwordMismatch
    && !isChangingPassword;

  const clearSelectedAvatar = useCallback(() => {
    setSelectedAvatarUrl((currentUrl) => {
      if (currentUrl) {
        URL.revokeObjectURL(currentUrl);
      }

      return null;
    });
    setSelectedAvatarName(null);
  }, []);

  const handleSaveProfile = useCallback(async () => {
    if (!name.trim()) return;

    setIsSaving(true);

    try {
      await authService.updateProfile({
        name: name.trim(),
        phone: phone.trim() || undefined,
      });

      await refreshSession();

      toast({
        title: 'Perfil atualizado',
        description: 'Seus dados foram salvos com sucesso.',
      });
    } catch (error: any) {
      toast({
        title: 'Erro ao salvar',
        description: error?.message || 'Nao foi possivel atualizar o perfil.',
        variant: 'destructive',
      });
    } finally {
      setIsSaving(false);
    }
  }, [name, phone, refreshSession, toast]);

  const handleAvatarFileChange = useCallback((event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) return;

    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    const maxSize = 5 * 1024 * 1024;

    if (!allowedTypes.includes(file.type)) {
      toast({
        title: 'Formato invalido',
        description: 'Use um arquivo JPG, PNG ou WebP.',
        variant: 'destructive',
      });
      return;
    }

    if (file.size > maxSize) {
      toast({
        title: 'Arquivo muito grande',
        description: 'A imagem deve ter no maximo 5 MB.',
        variant: 'destructive',
      });
      return;
    }

    clearSelectedAvatar();

    const objectUrl = URL.createObjectURL(file);
    setSelectedAvatarUrl(objectUrl);
    setSelectedAvatarName(file.name);
    setIsCropOpen(true);
  }, [clearSelectedAvatar, toast]);

  const handleAvatarCropConfirm = useCallback(async (blob: Blob) => {
    setIsUploading(true);

    try {
      const file = new File([blob], buildAvatarFileName(selectedAvatarName), {
        type: 'image/webp',
      });

      await authService.uploadAvatar(file);
      await refreshSession();

      toast({
        title: 'Foto atualizada',
        description: 'Seu avatar foi salvo com sucesso.',
      });

      setIsCropOpen(false);
      clearSelectedAvatar();
    } catch (error: any) {
      toast({
        title: 'Erro no upload',
        description: error?.message || 'Nao foi possivel salvar a foto.',
        variant: 'destructive',
      });
    } finally {
      setIsUploading(false);
    }
  }, [clearSelectedAvatar, refreshSession, selectedAvatarName, toast]);

  const handleDeleteAvatar = useCallback(async () => {
    setIsUploading(true);

    try {
      await authService.deleteAvatar();
      await refreshSession();

      toast({
        title: 'Foto removida',
        description: 'O avatar foi removido da sua conta.',
      });
    } catch (error: any) {
      toast({
        title: 'Erro ao remover',
        description: error?.message || 'Nao foi possivel remover a foto.',
        variant: 'destructive',
      });
    } finally {
      setIsUploading(false);
    }
  }, [refreshSession, toast]);

  const handleChangePassword = useCallback(async () => {
    setPasswordErrors({});

    if (!canSubmitPassword) return;

    setIsChangingPassword(true);

    try {
      const response = await authService.updatePassword({
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: confirmPassword,
      });

      toast({
        title: 'Senha atualizada',
        description: response.message,
      });

      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
      setShowPasswordSection(false);
      setShowPasswords(false);
    } catch (error: any) {
      const validationErrors = error?.validationErrors as Record<string, string[]> | undefined;

      setPasswordErrors({
        current_password: validationErrors?.current_password?.[0],
        password: validationErrors?.password?.[0],
      });

      toast({
        title: 'Erro ao atualizar a senha',
        description: error?.message || 'Nao foi possivel alterar a senha.',
        variant: 'destructive',
      });
    } finally {
      setIsChangingPassword(false);
    }
  }, [canSubmitPassword, confirmPassword, currentPassword, newPassword, toast]);

  if (!user) return null;

  return (
    <>
      <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="max-w-3xl space-y-6">
        <PageHeader
          title="Meu perfil"
          description="Gerencie seus dados pessoais, foto e configuracoes de seguranca."
        />

        <div className="glass rounded-2xl p-5 sm:p-6">
          <div className="flex flex-col items-center gap-5 sm:flex-row sm:gap-6">
            <div className="relative group">
              <UserAvatar
                name={user.name}
                avatarUrl={user.avatar_url}
                size="xl"
                className="ring-4 ring-background shadow-lg"
              />

              <button
                type="button"
                onClick={() => fileInputRef.current?.click()}
                disabled={isUploading}
                className="absolute inset-0 flex items-center justify-center rounded-full bg-black/0 transition-all group-hover:bg-black/40"
              >
                {isUploading ? (
                  <Loader2 className="h-6 w-6 animate-spin text-white opacity-0 transition-opacity group-hover:opacity-100" />
                ) : (
                  <Camera className="h-6 w-6 text-white opacity-0 transition-opacity group-hover:opacity-100" />
                )}
              </button>

              <input
                ref={fileInputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={handleAvatarFileChange}
              />
            </div>

            <div className="flex-1 space-y-2 text-center sm:text-left">
              <div>
                <h3 className="text-lg font-semibold">{user.name}</h3>
                <p className="text-sm text-muted-foreground">{user.email}</p>
              </div>

              <div className="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                <Badge variant="outline" className="gap-1 text-xs">
                  <Shield className="h-3 w-3" />
                  {roleLabel}
                </Badge>

                <Badge variant="secondary" className="gap-1 text-[10px]">
                  <Clock className="h-2.5 w-2.5" />
                  Ultimo acesso: {formatLastAccess(user.last_login_at)}
                </Badge>
              </div>

              <div className="flex justify-center gap-2 pt-1 sm:justify-start">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => fileInputRef.current?.click()}
                  disabled={isUploading}
                  className="text-xs"
                >
                  {isUploading ? (
                    <Loader2 className="mr-1 h-3.5 w-3.5 animate-spin" />
                  ) : (
                    <Camera className="mr-1 h-3.5 w-3.5" />
                  )}
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
                    <Trash2 className="mr-1 h-3.5 w-3.5" />
                    Remover
                  </Button>
                )}
              </div>
            </div>
          </div>
        </div>

        <div className="glass rounded-2xl p-5 sm:p-6 space-y-5">
          <div className="flex items-center justify-between">
            <h3 className="font-semibold">Dados pessoais</h3>
            <Badge variant="secondary" className="text-[10px]">{roleLabel}</Badge>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="profile-name">Nome completo</Label>
              <Input
                id="profile-name"
                value={name}
                onChange={(event) => setName(event.target.value)}
                placeholder="Seu nome"
                className="h-10"
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="profile-email" className="flex items-center gap-1.5">
                <Mail className="h-3 w-3" />
                E-mail
              </Label>
              <Input
                id="profile-email"
                value={user.email}
                disabled
                className="h-10 opacity-60"
              />
              <p className="text-[10px] text-muted-foreground">
                Para alterar o e-mail, contate o suporte.
              </p>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="profile-phone" className="flex items-center gap-1.5">
                <Phone className="h-3 w-3" />
                WhatsApp
              </Label>
              <Input
                id="profile-phone"
                value={phone}
                onChange={(event) => setPhone(formatPhoneInput(event.target.value))}
                placeholder="(51) 99999-9999"
                className="h-10"
              />
            </div>

            <div className="space-y-1.5">
              <Label className="flex items-center gap-1.5">
                <Shield className="h-3 w-3" />
                Cargo / perfil
              </Label>
              <Input value={roleLabel} disabled className="h-10 opacity-60" />
            </div>
          </div>

          <div className="flex justify-end pt-1">
            <Button
              onClick={handleSaveProfile}
              disabled={isSaving || !name.trim()}
              className="gradient-primary border-0"
            >
              {isSaving ? (
                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
              ) : (
                <Save className="mr-1.5 h-4 w-4" />
              )}
              {isSaving ? 'Salvando...' : 'Salvar alteracoes'}
            </Button>
          </div>
        </div>

        <div className="glass rounded-2xl p-5 sm:p-6 space-y-4">
          <button
            type="button"
            onClick={() => setShowPasswordSection((current) => !current)}
            className="flex w-full items-center justify-between transition-opacity hover:opacity-80"
          >
            <div className="flex items-center gap-2.5">
              <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
                <KeyRound className="h-4 w-4 text-primary" />
              </div>
              <div className="text-left">
                <h3 className="text-sm font-semibold">Alterar senha</h3>
                <p className="text-xs text-muted-foreground">Atualize a senha usada para acessar o painel.</p>
              </div>
            </div>
            <ChevronRight className={`h-4 w-4 text-muted-foreground transition-transform ${showPasswordSection ? 'rotate-90' : ''}`} />
          </button>

          {showPasswordSection && (
            <motion.div
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: 'auto' }}
              className="space-y-4 border-t border-border/50 pt-2"
            >
              <div className="space-y-1.5">
                <Label>Senha atual</Label>
                <div className="relative">
                  <Lock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    type={showPasswords ? 'text' : 'password'}
                    value={currentPassword}
                    onChange={(event) => {
                      setCurrentPassword(event.target.value);
                      setPasswordErrors((current) => ({ ...current, current_password: undefined }));
                    }}
                    placeholder="Digite sua senha atual"
                    className="h-10 pl-9 pr-10"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPasswords((current) => !current)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    tabIndex={-1}
                  >
                    {showPasswords ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
                {passwordErrors.current_password && (
                  <p className="text-[10px] text-destructive">{passwordErrors.current_password}</p>
                )}
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label>Nova senha</Label>
                  <Input
                    type={showPasswords ? 'text' : 'password'}
                    value={newPassword}
                    onChange={(event) => {
                      setNewPassword(event.target.value);
                      setPasswordErrors((current) => ({ ...current, password: undefined }));
                    }}
                    placeholder="Minimo de 8 caracteres"
                    className="h-10"
                  />
                </div>

                <div className="space-y-1.5">
                  <Label>Confirmar nova senha</Label>
                  <Input
                    type={showPasswords ? 'text' : 'password'}
                    value={confirmPassword}
                    onChange={(event) => setConfirmPassword(event.target.value)}
                    placeholder="Repita a nova senha"
                    className="h-10"
                  />
                  {passwordMismatch && (
                    <p className="text-[10px] text-destructive">As senhas nao conferem.</p>
                  )}
                  {!passwordMismatch && passwordErrors.password && (
                    <p className="text-[10px] text-destructive">{passwordErrors.password}</p>
                  )}
                </div>
              </div>

              <div className="flex justify-end">
                <Button
                  disabled={!canSubmitPassword}
                  className="gradient-primary border-0"
                  onClick={handleChangePassword}
                >
                  {isChangingPassword ? (
                    <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                  ) : (
                    <Lock className="mr-1.5 h-4 w-4" />
                  )}
                  {isChangingPassword ? 'Salvando...' : 'Alterar senha'}
                </Button>
              </div>
            </motion.div>
          )}
        </div>

        <div className="glass rounded-2xl p-5 sm:p-6">
          <h3 className="mb-3 text-sm font-semibold">Informacoes da conta</h3>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
              <p className="text-[10px] uppercase tracking-wider text-muted-foreground">Status</p>
              <Badge variant={user.status === 'active' ? 'default' : 'secondary'} className="mt-1 text-xs">
                {statusLabel}
              </Badge>
            </div>

            <div>
              <p className="text-[10px] uppercase tracking-wider text-muted-foreground">ID</p>
              <p className="mt-1 text-sm font-mono">#{user.id}</p>
            </div>

            <div>
              <p className="text-[10px] uppercase tracking-wider text-muted-foreground">Tema</p>
              <p className="mt-1 text-sm">{themeLabel}</p>
            </div>

            <div>
              <p className="text-[10px] uppercase tracking-wider text-muted-foreground">Fuso</p>
              <p className="mt-1 text-sm">{user.preferences?.timezone || 'America/Sao_Paulo'}</p>
            </div>
          </div>
        </div>
      </motion.div>

      <AvatarCropDialog
        open={isCropOpen}
        imageSrc={selectedAvatarUrl}
        isSubmitting={isUploading}
        onOpenChange={(open) => {
          setIsCropOpen(open);

          if (!open) {
            clearSelectedAvatar();
          }
        }}
        onConfirm={handleAvatarCropConfirm}
      />
    </>
  );
}
