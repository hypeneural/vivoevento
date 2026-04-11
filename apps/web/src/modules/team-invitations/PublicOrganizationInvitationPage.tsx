import { useMemo, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Building2, CheckCircle2, KeyRound, Loader2, LogIn, ShieldCheck } from 'lucide-react';
import { Link, useParams } from 'react-router-dom';

import { useAuth } from '@/app/providers/AuthProvider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ApiError, setToken } from '@/lib/api';
import { persistSession } from '@/modules/auth/services/auth.service';
import { redirectToInvitationNextPath } from '@/modules/event-invitations/navigation';

import { organizationInvitationsApi } from './api';

function resolveErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error && typeof error === 'object' && 'message' in error && typeof error.message === 'string') {
    return error.message;
  }

  return fallback;
}

export default function PublicOrganizationInvitationPage() {
  const { token } = useParams<{ token: string }>();
  const { isAuthenticated, meUser } = useAuth();
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [submitError, setSubmitError] = useState<string | null>(null);

  const invitationQuery = useQuery({
    queryKey: ['public-organization-invitation', token],
    enabled: !!token,
    retry: false,
    queryFn: () => organizationInvitationsApi.getPublicInvitation(token as string),
  });

  const invitation = invitationQuery.data;
  const loginPath = useMemo(
    () => `/login?returnTo=${encodeURIComponent(`/convites/equipe/${token ?? ''}`)}`,
    [token],
  );

  const acceptMutation = useMutation({
    mutationFn: async () => {
      if (!token || !invitation) {
        throw new Error('Convite invalido.');
      }

      setSubmitError(null);

      if (invitation.requires_existing_login) {
        return organizationInvitationsApi.acceptAuthenticatedInvitation(token);
      }

      return organizationInvitationsApi.acceptPublicInvitation(token, {
        password,
        password_confirmation: passwordConfirmation,
        device_name: 'organization-invite-web',
      });
    },
    onSuccess: (result) => {
      if (result.token) {
        setToken(result.token);
      }

      persistSession(result.session);
      redirectToInvitationNextPath(result.next_path);
    },
    onError: (error) => {
      setSubmitError(resolveErrorMessage(error, 'Nao foi possivel aceitar este convite agora.'));
    },
  });

  if (!token) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-background px-6">
        <Card className="w-full max-w-lg">
          <CardContent className="space-y-3 p-6 text-center">
            <CardTitle>Convite invalido</CardTitle>
            <p className="text-sm text-muted-foreground">Confirme o link recebido e tente novamente.</p>
          </CardContent>
        </Card>
      </div>
    );
  }

  if (invitationQuery.isLoading) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-background">
        <div className="flex flex-col items-center gap-3">
          <Loader2 className="h-8 w-8 animate-spin text-primary" />
          <p className="text-sm text-muted-foreground">Abrindo seu convite...</p>
        </div>
      </div>
    );
  }

  if (invitationQuery.isError || !invitation) {
    const message = resolveErrorMessage(invitationQuery.error, 'Nao foi possivel abrir este convite.');

    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-background px-6">
        <Card className="w-full max-w-lg">
          <CardContent className="space-y-3 p-6 text-center">
            <CardTitle>Convite indisponivel</CardTitle>
            <p className="text-sm text-muted-foreground">{message}</p>
          </CardContent>
        </Card>
      </div>
    );
  }

  const canSubmit = invitation.requires_existing_login
    ? isAuthenticated
    : password.trim().length >= 8 && passwordConfirmation.trim().length >= 8;

  return (
    <div className="min-h-[100dvh] bg-gradient-to-b from-background via-background to-muted/30 px-4 py-10">
      <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <Card className="border-0 shadow-xl shadow-slate-200/70">
          <CardHeader className="space-y-4">
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="secondary">Convite da equipe</Badge>
              <Badge variant="outline">{invitation.access.role_label}</Badge>
            </div>
            <div className="space-y-2">
              <CardTitle className="text-3xl leading-tight">{invitation.organization.name}</CardTitle>
              <p className="text-sm text-muted-foreground">
                {invitation.invitee_name}, este convite libera seu acesso pessoal para a rotina desta organizacao.
              </p>
            </div>
          </CardHeader>
          <CardContent className="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
            <div className="space-y-4">
              <div className="rounded-2xl border bg-muted/20 p-4">
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Resumo</p>
                <div className="mt-3 space-y-2 text-sm">
                  <p><span className="font-medium">Organizacao:</span> {invitation.organization.name}</p>
                  <p><span className="font-medium">Contato:</span> {invitation.invitee_contact.phone_masked ?? invitation.invitee_contact.email ?? 'Nao informado'}</p>
                </div>
              </div>

              <div className="rounded-2xl border bg-background p-4">
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Seu papel</p>
                <div className="mt-3 flex items-start gap-3">
                  <div className="rounded-full bg-primary/10 p-2 text-primary">
                    <Building2 className="h-4 w-4" />
                  </div>
                  <div className="space-y-1">
                    <p className="text-sm font-medium">{invitation.access.role_label}</p>
                    <p className="text-sm text-muted-foreground">{invitation.access.description}</p>
                  </div>
                </div>
              </div>
            </div>

            <Card className="border bg-white/95 shadow-none">
              <CardHeader className="space-y-2">
                <CardTitle className="text-xl">Ativar meu acesso</CardTitle>
                <p className="text-sm text-muted-foreground">
                  {invitation.requires_existing_login
                    ? 'Este convite esta ligado a uma conta existente da plataforma.'
                    : 'Defina uma senha para acessar sua conta e entrar quando precisar.'}
                </p>
              </CardHeader>
              <CardContent className="space-y-4">
                {invitation.requires_existing_login ? (
                  isAuthenticated ? (
                    <div className="space-y-4">
                      <div className="rounded-2xl border bg-muted/20 p-4 text-sm">
                        <p className="font-medium">Voce esta logado como</p>
                        <p className="mt-1 text-muted-foreground">{meUser?.name ?? 'Usuario autenticado'}</p>
                      </div>
                      <Button className="w-full" onClick={() => acceptMutation.mutate()} disabled={acceptMutation.isPending}>
                        {acceptMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <ShieldCheck className="mr-2 h-4 w-4" />}
                        Aceitar convite
                      </Button>
                    </div>
                  ) : (
                    <div className="space-y-4">
                      <div className="rounded-2xl border bg-muted/20 p-4 text-sm text-muted-foreground">
                        Faca login com sua conta da plataforma para concluir este aceite com seguranca.
                      </div>
                      <Button asChild className="w-full">
                        <Link to={loginPath}>
                          <LogIn className="mr-2 h-4 w-4" />
                          Fazer login para continuar
                        </Link>
                      </Button>
                    </div>
                  )
                ) : (
                  <div className="space-y-4">
                    <div className="space-y-2">
                      <Label htmlFor="organization-invitation-password">Senha</Label>
                      <Input
                        id="organization-invitation-password"
                        type="password"
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                        placeholder="Crie uma senha segura"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="organization-invitation-password-confirmation">Confirmar senha</Label>
                      <Input
                        id="organization-invitation-password-confirmation"
                        type="password"
                        value={passwordConfirmation}
                        onChange={(event) => setPasswordConfirmation(event.target.value)}
                        placeholder="Repita a senha"
                      />
                    </div>
                    <Button className="w-full" onClick={() => acceptMutation.mutate()} disabled={acceptMutation.isPending || !canSubmit}>
                      {acceptMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <KeyRound className="mr-2 h-4 w-4" />}
                      Criar conta e entrar
                    </Button>
                  </div>
                )}

                {submitError ? (
                  <div className="rounded-2xl border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                    {submitError}
                  </div>
                ) : null}

                <div className="rounded-2xl border bg-emerald-50/80 px-4 py-3 text-sm text-emerald-900">
                  <div className="flex items-start gap-2">
                    <CheckCircle2 className="mt-0.5 h-4 w-4" />
                    <p>Depois do aceite, sua conta entra direto no ambiente desta organizacao com o papel liberado neste convite.</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
