import { useState, useCallback, useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '@/app/providers/AuthProvider';
import { AuthFlowFooter } from '@/modules/auth/components/invitation-flow/AuthFlowFooter';
import { AuthFlowSuccessState } from '@/modules/auth/components/invitation-flow/AuthFlowSuccessState';
import { AuthHelpHint } from '@/modules/auth/components/invitation-flow/AuthHelpHint';
import { InvitationContextCard } from '@/modules/auth/components/invitation-flow/InvitationContextCard';
import { OtpCodeStep } from '@/modules/auth/components/invitation-flow/OtpCodeStep';
import { PasswordSetupStep } from '@/modules/auth/components/invitation-flow/PasswordSetupStep';
import { RecoveryRequestStep } from '@/modules/auth/components/invitation-flow/RecoveryRequestStep';
import { useInvitationAuthFlow } from '@/modules/auth/hooks/useInvitationAuthFlow';
import { resolveInvitationReturnContext, resolveLoginInitialStep, resolveLoginReturnPath } from '@/modules/auth/login-navigation';
import { authService } from '@/modules/auth/services/auth.service';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ArrowRight, ArrowLeft, Loader2, ChevronDown, Phone, Lock, Eye, EyeOff, Sparkles, CheckCircle2 } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import { useToast } from '@/hooks/use-toast';

// WhatsApp palette
const WA_GREEN = '#25D366';
export default function LoginPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { login, loginMock, availableUsers, isLoading, refreshSession } = useAuth();
  const { toast } = useToast();
  const loginReturnPath = resolveLoginReturnPath(location.search, '/');
  const invitationReturnContext = resolveInvitationReturnContext(loginReturnPath);
  const initialStep = resolveLoginInitialStep(location.search);
  const {
    step,
    chooseLogin,
    chooseRegister,
    registerOtpSent,
    registerCompleted,
    startPasswordRecovery,
    forgotOtpSent,
    forgotOtpConfirmed,
    passwordResetSucceeded,
    goBack,
  } = useInvitationAuthFlow(initialStep);

  const [loginValue, setLoginValue] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [name, setName] = useState('');
  const [registerCode, setRegisterCode] = useState('');
  const [registerSessionToken, setRegisterSessionToken] = useState('');
  const [registerMaskedPhone, setRegisterMaskedPhone] = useState('');
  const [registerResendCountdown, setRegisterResendCountdown] = useState(0);
  const [registerWelcomeTitle, setRegisterWelcomeTitle] = useState('');
  const [registerWelcomeDescription, setRegisterWelcomeDescription] = useState('');
  const [registerNextPath, setRegisterNextPath] = useState('/plans');
  const [resetCode, setResetCode] = useState('');
  const [forgotSessionToken, setForgotSessionToken] = useState('');
  const [forgotDestinationMasked, setForgotDestinationMasked] = useState('');
  const [forgotResendCountdown, setForgotResendCountdown] = useState(0);
  const [showPassword, setShowPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isContinuing, setIsContinuing] = useState(false);
  const [resetMethod, setResetMethod] = useState<'whatsapp' | 'email'>('whatsapp');
  const hasDevQuickAccess = availableUsers.length > 0;

  useEffect(() => {
    if (registerResendCountdown <= 0) return;

    const timer = window.setTimeout(() => {
      setRegisterResendCountdown((current) => Math.max(current - 1, 0));
    }, 1000);

    return () => window.clearTimeout(timer);
  }, [registerResendCountdown]);

  useEffect(() => {
    if (forgotResendCountdown <= 0) return;

    const timer = window.setTimeout(() => {
      setForgotResendCountdown((current) => Math.max(current - 1, 0));
    }, 1000);

    return () => window.clearTimeout(timer);
  }, [forgotResendCountdown]);

  // â”€â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

  const isPhoneInput = useCallback((value: string) => {
    const digits = value.replace(/\D/g, '');
    return digits.length >= 2 && !/^[a-zA-Z]/.test(value);
  }, []);

  const formatPhone = useCallback((value: string) => {
    const nums = value.replace(/\D/g, '').slice(0, 11);
    if (nums.length <= 2) return `(${nums}`;
    if (nums.length <= 7) return `(${nums.slice(0, 2)}) ${nums.slice(2)}`;
    return `(${nums.slice(0, 2)}) ${nums.slice(2, 7)}-${nums.slice(7)}`;
  }, []);

  const formatCountdown = useCallback((seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;

    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
  }, []);

  const handleLoginInputChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const raw = e.target.value;
    // If starts looking like a phone, format it
    if (isPhoneInput(raw) && !raw.includes('@')) {
      const digits = raw.replace(/\D/g, '');
      if (digits.length <= 11) {
        setLoginValue(digits.length > 0 ? formatPhone(digits) : '');
      }
    } else {
      setLoginValue(raw);
    }
  }, [isPhoneInput, formatPhone]);

  const getLoginRaw = useCallback(() => {
    // If it looks like a formatted phone, return digits only
    const digits = loginValue.replace(/\D/g, '');
    if (digits.length >= 10 && !loginValue.includes('@')) {
      return digits;
    }
    return loginValue;
  }, [loginValue]);

  const isValidLogin = useCallback(() => {
    const raw = getLoginRaw();
    if (raw.includes('@')) return raw.includes('.') && raw.length >= 5;
    return raw.replace(/\D/g, '').length >= 10;
  }, [getLoginRaw]);

  const resetRegisterState = useCallback(() => {
    setRegisterCode('');
    setRegisterSessionToken('');
    setRegisterMaskedPhone('');
    setRegisterResendCountdown(0);
    setRegisterWelcomeTitle('');
    setRegisterWelcomeDescription('');
    setRegisterNextPath('/plans');
    setIsContinuing(false);
  }, []);

  const resetForgotState = useCallback(() => {
    setResetCode('');
    setForgotSessionToken('');
    setForgotDestinationMasked('');
    setForgotResendCountdown(0);
    setResetMethod('whatsapp');
  }, []);

  const getPasswordStrength = useCallback((pwd: string) => {
    if (!pwd) return { level: 0, label: '' };
    let score = 0;
    if (pwd.length >= 8) score++;
    if (pwd.length >= 12) score++;
    if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) score++;
    if (/\d/.test(pwd)) score++;
    if (/[!@#$%^&*(),.?":{}|<>]/.test(pwd)) score++;
    
    if (score <= 1) return { level: 1, label: 'Fraca', color: '#ef4444' };
    if (score <= 2) return { level: 2, label: 'RazoÃ¡vel', color: '#f59e0b' };
    if (score <= 3) return { level: 3, label: 'Boa', color: WA_GREEN };
    return { level: 4, label: 'Forte âœ“', color: WA_GREEN };
  }, []);

  // â”€â”€â”€ Actions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

  const handleLogin = useCallback(async () => {
    if (!isValidLogin() || !password) return;
    setIsSubmitting(true);
    try {
      await login({ login: getLoginRaw(), password });
      navigate(loginReturnPath, { replace: true });
      toast({ title: 'Bem-vindo! ðŸŽ‰', description: 'Login realizado com sucesso.' });
    } catch (err: any) {
      toast({
        title: 'Erro ao entrar',
        description: err?.message || 'Verifique suas credenciais.',
        variant: 'destructive',
      });
    } finally {
      setIsSubmitting(false);
    }
  }, [password, login, toast, getLoginRaw, isValidLogin, navigate, loginReturnPath]);

  const handleRegister = useCallback(async () => {
    const phoneDigits = getLoginRaw().replace(/\D/g, '');
    if (!name.trim() || phoneDigits.length < 10) return;

    setIsSubmitting(true);
    try {
      const result = await authService.requestRegisterOtp({
        name: name.trim(),
        phone: phoneDigits,
      });

      setRegisterSessionToken(result.session_token);
      setRegisterMaskedPhone(result.phone_masked);
      setRegisterCode('');
      setRegisterResendCountdown(result.resend_in);
      registerOtpSent();

      toast({
        title: 'Codigo enviado',
        description: 'Confira seu WhatsApp e digite o codigo de 6 digitos.',
      });
    } catch (err: any) {
      toast({
        title: 'Nao foi possivel iniciar o cadastro',
        description: err?.message || 'Tente novamente.',
        variant: 'destructive',
      });
    } finally {
      setIsSubmitting(false);
    }
  }, [getLoginRaw, name, registerOtpSent, toast]);

  const handleFinishRegister = useCallback(async () => {
    if (!password || password.length < 8) return;
    setIsSubmitting(true);
    try {
      // Register + login (backend will handle this)
      await login({ login: getLoginRaw(), password });
      toast({ title: 'Conta criada! ðŸŽ‰', description: 'Bem-vindo ao Evento Vivo.' });
    } catch (err: any) {
      toast({
        title: 'Erro ao criar conta',
        description: err?.message || 'Tente novamente.',
        variant: 'destructive',
      });
    } finally {
      setIsSubmitting(false);
    }
  }, [password, login, toast, getLoginRaw]);

  const handleResendRegisterCode = useCallback(async () => {
    if (!registerSessionToken || registerResendCountdown > 0) return;

    setIsSubmitting(true);
    try {
      const result = await authService.resendRegisterOtp({
        session_token: registerSessionToken,
      });

      setRegisterCode('');
      setRegisterResendCountdown(result.resend_in);
      toast({
        title: 'Codigo reenviado',
        description: 'Enviamos um novo codigo para o seu WhatsApp.',
      });
    } catch (err: any) {
      toast({
        title: 'Nao foi possivel reenviar',
        description: err?.message || 'Aguarde alguns instantes e tente de novo.',
        variant: 'destructive',
      });
    } finally {
      setIsSubmitting(false);
    }
  }, [registerResendCountdown, registerSessionToken, toast]);

  const handleVerifyRegisterCode = useCallback(async () => {
    if (registerCode.length !== 6 || !registerSessionToken) return;

    setIsSubmitting(true);
    try {
      const result = await authService.verifyRegisterOtp({
        session_token: registerSessionToken,
        code: registerCode,
        device_name: 'web-panel',
      });

      setRegisterWelcomeTitle(result.onboarding.title);
      setRegisterWelcomeDescription(result.onboarding.description);
      setRegisterNextPath(result.onboarding.next_path || '/plans');
      registerCompleted();

      toast({
        title: 'WhatsApp validado',
        description: 'Sua conta foi criada com sucesso.',
      });
    } catch (err: any) {
      toast({
        title: 'Codigo invalido',
        description: err?.message || 'Revise o codigo e tente novamente.',
        variant: 'destructive',
      });
    } finally {
      setIsSubmitting(false);
    }
  }, [registerCompleted, registerCode, registerSessionToken, toast]);

  const handleContinueAfterRegister = useCallback(async () => {
    setIsContinuing(true);
    try {
      await refreshSession();
      navigate(registerNextPath || '/plans', { replace: true });
    } finally {
      setIsContinuing(false);
    }
  }, [navigate, refreshSession, registerNextPath]);

  const handleForgotPassword = useCallback(async () => {
    if (!isValidLogin()) return;
    setIsSubmitting(true);
    try {
      const result = await authService.requestForgotPasswordOtp({ login: getLoginRaw() });
      setForgotSessionToken(result.session_token);
      setResetMethod(result.method);
      setForgotDestinationMasked(result.destination_masked);
      setForgotResendCountdown(result.resend_in);
      setResetCode('');
      forgotOtpSent();
      toast({
        title: 'Solicitacao recebida',
        description: result.method === 'whatsapp'
          ? 'Se encontrarmos uma conta para este WhatsApp, enviaremos um codigo de recuperacao.'
          : 'Se encontrarmos uma conta para este e-mail, enviaremos um codigo de recuperacao.',
      });
    } catch (err: any) {
      toast({
        title: 'Erro',
        description: err?.message || 'Tente novamente.',
        variant: 'destructive',
      });
    } finally {
      setIsSubmitting(false);
    }
  }, [forgotOtpSent, getLoginRaw, isValidLogin, toast]);

  const handleResendForgotCode = useCallback(async () => {
    if (!forgotSessionToken || forgotResendCountdown > 0) return;

    setIsSubmitting(true);
    try {
      const result = await authService.resendForgotPasswordOtp({
        session_token: forgotSessionToken,
      });

      setResetMethod(result.method);
      setForgotDestinationMasked(result.destination_masked);
      setForgotResendCountdown(result.resend_in);
      setResetCode('');

      toast({
        title: 'Solicitacao reenviada',
        description: result.method === 'whatsapp'
          ? 'Se existir uma conta para este WhatsApp, um novo codigo sera enviado.'
          : 'Se existir uma conta para este e-mail, um novo codigo sera enviado.',
      });
    } catch (err: any) {
      toast({
        title: 'Nao foi possivel reenviar',
        description: err?.message || 'Aguarde alguns instantes e tente novamente.',
        variant: 'destructive',
      });
    } finally {
      setIsSubmitting(false);
    }
  }, [forgotResendCountdown, forgotSessionToken, toast]);

  const handleVerifyForgotCode = useCallback(async () => {
    if (resetCode.length !== 6 || !forgotSessionToken) return;

    setIsSubmitting(true);
    try {
      await authService.verifyForgotPasswordOtp({
        session_token: forgotSessionToken,
        code: resetCode,
      });

      forgotOtpConfirmed();
      toast({
        title: 'Codigo validado',
        description: 'Agora voce ja pode criar uma nova senha.',
      });
    } catch (err: any) {
      toast({
        title: 'Codigo invalido',
        description: err?.message || 'Revise o codigo e tente novamente.',
        variant: 'destructive',
      });
    } finally {
      setIsSubmitting(false);
    }
  }, [forgotOtpConfirmed, forgotSessionToken, resetCode, toast]);

  const handleResetPassword = useCallback(async () => {
    if (!forgotSessionToken || password.length < 8 || password !== passwordConfirm) return;
    setIsSubmitting(true);
    try {
      await authService.resetPasswordWithOtp({
        session_token: forgotSessionToken,
        password,
        password_confirmation: passwordConfirm,
        device_name: 'web-panel',
      });
      passwordResetSucceeded();
      toast({ title: 'Senha redefinida! ðŸ”', description: 'Entrando na sua conta...' });
      window.setTimeout(async () => {
        await refreshSession();
        navigate(loginReturnPath, { replace: true });
      }, 1200);
    } catch (err: any) {
      toast({
        title: 'Erro',
        description: err?.message || 'CÃ³digo invÃ¡lido ou expirado.',
        variant: 'destructive',
      });
    } finally {
      setIsSubmitting(false);
    }
  }, [forgotSessionToken, loginReturnPath, navigate, password, passwordConfirm, passwordResetSucceeded, refreshSession, toast]);

  const handleKeyDown = useCallback((e: React.KeyboardEvent, action: () => void) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      action();
    }
  }, []);

  const slideVariants = {
    enter: (direction: number) => ({ x: direction > 0 ? 60 : -60, opacity: 0 }),
    center: { x: 0, opacity: 1 },
    exit: (direction: number) => ({ x: direction > 0 ? -60 : 60, opacity: 0 }),
  };

  const strength = getPasswordStrength(password);
  const registerNextLabel = registerNextPath === '/plans'
    ? 'Ir para planos'
    : registerNextPath === '/events/create'
      ? 'Criar meu evento'
      : 'Continuar';
  const registerNextHint = registerNextPath === '/plans'
    ? 'Proximo passo: selecionar o plano ideal para liberar a ativacao do seu primeiro evento.'
    : registerNextPath === '/events/create'
      ? 'Proximo passo: abrir a criacao do evento para continuar a jornada comercial.'
      : 'Proximo passo: continuar o fluxo iniciado no cadastro.';
  const loginIdentityInputId = 'login-identity';
  const loginPasswordInputId = 'login-password';
  const registerNameInputId = 'register-name';
  const registerPhoneInputId = 'register-phone';
  const forgotIdentityInputId = 'forgot-identity';
  const forgotCodeInputId = 'forgot-code';
  const forgotPasswordInputId = 'forgot-new-password';
  const forgotPasswordConfirmationInputId = 'forgot-new-password-confirmation';

  // â”€â”€â”€ WhatsApp SVG Icon â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

  const WhatsAppIcon = ({ className = '', size = 16 }: { className?: string; size?: number }) => (
    <svg viewBox="0 0 24 24" width={size} height={size} className={className} fill="currentColor">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>
  );

  return (
    <div className="min-h-[100dvh] flex flex-col bg-background relative overflow-hidden">
      {/* Background Effects */}
      <div className="absolute inset-0 pointer-events-none">
        <div className="absolute top-[-20%] left-[-10%] w-[60vw] h-[60vw] max-w-[600px] max-h-[600px] rounded-full opacity-[0.04]"
          style={{ background: `radial-gradient(circle, ${WA_GREEN} 0%, transparent 70%)` }} />
        <div className="absolute bottom-[-15%] right-[-10%] w-[50vw] h-[50vw] max-w-[500px] max-h-[500px] rounded-full opacity-[0.06]"
          style={{ background: 'radial-gradient(circle, hsl(258, 70%, 58%) 0%, transparent 70%)' }} />
      </div>

      {/* Main Content */}
      <div className="flex-1 flex items-center justify-center p-4 sm:p-6 relative z-10">
        <div className="w-full max-w-[420px]">

          {/* Logo Header */}
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4 }}
            className="text-center mb-6 sm:mb-8"
          >
            <div className="inline-flex items-center gap-2.5 mb-2">
              <Sparkles className="h-7 w-7 sm:h-8 sm:w-8 text-primary" />
              <h1 className="text-2xl sm:text-3xl font-bold gradient-text">Evento Vivo</h1>
            </div>
            <p className="text-muted-foreground text-sm sm:text-base">
              Plataforma de experiÃªncias vivas
            </p>
          </motion.div>

          {invitationReturnContext ? (
            <motion.div
              initial={{ opacity: 0, y: 12 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.35, delay: 0.05 }}
              className="mb-4"
            >
              <InvitationContextCard
                className="rounded-2xl border border-primary/20 bg-primary/5 px-4 py-3"
                badge={invitationReturnContext.badge}
                title={invitationReturnContext.title}
                description={invitationReturnContext.description}
              />
            </motion.div>
          ) : null}

          {/* Card */}
          <motion.div
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4, delay: 0.1 }}
            className="glass-strong rounded-2xl sm:rounded-3xl overflow-hidden"
          >
            <AnimatePresence mode="wait" custom={1}>
              {/* â”€â”€â”€ Step: Choose Method â”€â”€â”€ */}
              {step === 'method' && (
                <motion.div
                  key="method"
                  variants={slideVariants}
                  initial="enter"
                  animate="center"
                  exit="exit"
                  custom={1}
                  transition={{ duration: 0.25 }}
                  className="p-5 sm:p-8 space-y-5 sm:space-y-6"
                >
                  <div className="text-center space-y-1.5">
                    <h2 className="text-lg sm:text-xl font-semibold">Acesse sua conta</h2>
                    <p className="text-sm text-muted-foreground">
                      Entre com seu WhatsApp ou e-mail
                    </p>
                  </div>

                  <div className="space-y-3">
                    <button
                      onClick={chooseLogin}
                      className="group w-full flex items-center gap-3.5 p-3.5 sm:p-4 rounded-xl bg-muted/40 hover:bg-muted/70 border border-border/40 hover:border-border transition-all duration-200"
                    >
                      <div className="h-10 w-10 sm:h-11 sm:w-11 rounded-xl flex items-center justify-center shrink-0"
                        style={{ background: `linear-gradient(135deg, ${WA_GREEN}22, ${WA_GREEN}11)`, border: `1px solid ${WA_GREEN}33` }}>
                        <WhatsAppIcon size={18} className="text-[#25D366]" />
                      </div>
                      <div className="flex-1 text-left min-w-0">
                        <p className="text-sm sm:text-base font-medium">Entrar com WhatsApp</p>
                        <p className="text-xs text-muted-foreground truncate">Use seu nÃºmero de WhatsApp e senha</p>
                      </div>
                      <ArrowRight className="h-4 w-4 text-muted-foreground group-hover:text-foreground group-hover:translate-x-0.5 transition-all shrink-0" />
                    </button>

                    <button
                      onClick={() => {
                        resetRegisterState();
                        chooseRegister();
                      }}
                      className="group w-full flex items-center gap-3.5 p-3.5 sm:p-4 rounded-xl bg-muted/40 hover:bg-muted/70 border border-border/40 hover:border-border transition-all duration-200"
                    >
                      <div className="h-10 w-10 sm:h-11 sm:w-11 rounded-xl gradient-primary flex items-center justify-center shrink-0 opacity-90">
                        <Sparkles className="h-4.5 w-4.5 sm:h-5 sm:w-5 text-white" />
                      </div>
                      <div className="flex-1 text-left min-w-0">
                        <p className="text-sm sm:text-base font-medium">Criar conta</p>
                        <p className="text-xs text-muted-foreground truncate">Comece sua experiÃªncia no Evento Vivo</p>
                      </div>
                      <ArrowRight className="h-4 w-4 text-muted-foreground group-hover:text-foreground group-hover:translate-x-0.5 transition-all shrink-0" />
                    </button>
                  </div>

                  {hasDevQuickAccess ? (
                    <div className="border-t border-border/50 pt-4 sm:pt-5">
                      <button
                        onClick={() => {
                          const el = document.getElementById('dev-users');
                          el?.classList.toggle('hidden');
                        }}
                        className="flex items-center justify-center gap-1.5 w-full text-xs text-muted-foreground/60 hover:text-muted-foreground transition-colors"
                      >
                        <span>Acesso rÃ¡pido (dev)</span>
                        <ChevronDown className="h-3 w-3" />
                      </button>
                      <div id="dev-users" className="hidden mt-3 space-y-1.5">
                        {availableUsers.map(u => (
                          <button
                            key={u.id}
                            onClick={() => loginMock(u.id)}
                            className="flex items-center gap-2.5 w-full p-2 sm:p-2.5 rounded-lg bg-muted/30 hover:bg-muted/60 transition-colors text-left"
                          >
                            <div className="h-7 w-7 sm:h-8 sm:w-8 rounded-full gradient-primary flex items-center justify-center text-[10px] sm:text-xs font-bold text-primary-foreground shrink-0">
                              {u.name.split(' ').map(n => n[0]).join('').slice(0, 2)}
                            </div>
                            <div className="min-w-0 flex-1">
                              <p className="text-xs sm:text-sm font-medium truncate">{u.name}</p>
                              <p className="text-[10px] text-muted-foreground truncate">{u.role}</p>
                            </div>
                          </button>
                        ))}
                      </div>
                    </div>
                  ) : null}
                </motion.div>
              )}

              {/* â”€â”€â”€ Step: Login (WhatsApp/Email + Password) â”€â”€â”€ */}
              {step === 'login' && (
                <motion.div
                  key="login"
                  variants={slideVariants}
                  initial="enter"
                  animate="center"
                  exit="exit"
                  custom={1}
                  transition={{ duration: 0.25 }}
                  className="p-5 sm:p-8 space-y-4 sm:space-y-5"
                >
                  <div className="space-y-1.5">
                    <button
                      onClick={goBack}
                      className="text-xs text-muted-foreground hover:text-foreground transition-colors flex items-center gap-1 -ml-0.5 mb-2"
                    >
                      <ArrowLeft className="h-3 w-3" />
                      Alterar dados
                    </button>
                    <h2 className="text-lg sm:text-xl font-semibold">Entrar</h2>
                    <p className="text-sm text-muted-foreground">Use seu WhatsApp ou e-mail + senha</p>
                  </div>

                  <div className="space-y-3 sm:space-y-4">
                    <div>
                      <label htmlFor={loginIdentityInputId} className="text-xs sm:text-sm font-medium mb-1 sm:mb-1.5 block text-muted-foreground">WhatsApp ou E-mail</label>
                      <div className="relative">
                        <Phone className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                          id={loginIdentityInputId}
                          aria-label="WhatsApp ou E-mail"
                          type="text"
                          value={loginValue}
                          onChange={handleLoginInputChange}
                          onKeyDown={e => handleKeyDown(e, handleLogin)}
                          placeholder="(51) 99999-9999 ou seu@email.com"
                          className="pl-9 h-10 sm:h-11 text-sm sm:text-base"
                          autoComplete="username"
                          autoFocus
                        />
                      </div>
                    </div>

                    <div>
                      <div className="flex justify-between items-center mb-1 sm:mb-1.5">
                        <label htmlFor={loginPasswordInputId} className="text-xs sm:text-sm font-medium text-muted-foreground">Senha</label>
                        <button
                          type="button"
                          onClick={() => {
                            resetForgotState();
                            setPassword('');
                            setPasswordConfirm('');
                            startPasswordRecovery();
                          }}
                          className="text-[10px] sm:text-xs text-primary hover:underline font-medium"
                        >
                          Esqueci a senha
                        </button>
                      </div>
                      <div className="relative">
                        <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                          id={loginPasswordInputId}
                          aria-label="Senha"
                          type={showPassword ? 'text' : 'password'}
                          value={password}
                          onChange={e => setPassword(e.target.value)}
                          onKeyDown={e => handleKeyDown(e, handleLogin)}
                          placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢"
                          className="pl-9 pr-10 h-10 sm:h-11 text-sm sm:text-base"
                          autoComplete="current-password"
                        />
                        <button
                          type="button"
                          onClick={() => setShowPassword(!showPassword)}
                          className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors p-0.5"
                          tabIndex={-1}
                        >
                          {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                      </div>
                    </div>

                    <Button
                      className="w-full h-10 sm:h-11 gradient-primary border-0 text-sm sm:text-base font-medium"
                      onClick={handleLogin}
                      disabled={isSubmitting || isLoading || !isValidLogin() || !password}
                    >
                      {isSubmitting ? (
                        <Loader2 className="h-4 w-4 animate-spin mr-2" />
                      ) : null}
                      {isSubmitting ? 'Entrando...' : 'Entrar'}
                    </Button>
                  </div>

                  <AuthFlowFooter
                    prefix="Nao tem conta?"
                    actionLabel="Criar conta"
                    onAction={() => {
                      resetRegisterState();
                      chooseRegister();
                    }}
                  />
                </motion.div>
              )}

              {/* â”€â”€â”€ Step: Register (WhatsApp + Name) â”€â”€â”€ */}
              {step === 'register' && (
                <motion.div
                  key="register"
                  variants={slideVariants}
                  initial="enter"
                  animate="center"
                  exit="exit"
                  custom={1}
                  transition={{ duration: 0.25 }}
                  className="p-5 sm:p-8 space-y-4 sm:space-y-5"
                >
                  <div className="space-y-1.5">
                    <button
                      onClick={() => {
                        resetRegisterState();
                        goBack();
                      }}
                      className="text-xs text-muted-foreground hover:text-foreground transition-colors flex items-center gap-1 -ml-0.5 mb-2"
                    >
                      <ArrowLeft className="h-3 w-3" />
                      Voltar
                    </button>
                    <h2 className="text-lg sm:text-xl font-semibold">Criar conta</h2>
                    <p className="text-sm text-muted-foreground">Comece sua experiÃªncia no Evento Vivo</p>
                  </div>

                  {/* WhatsApp highlight */}
                  <div className="flex items-start gap-3 p-3 sm:p-3.5 rounded-xl border border-border/30"
                    style={{ background: `${WA_GREEN}08` }}>
                    <div className="h-8 w-8 sm:h-9 sm:w-9 rounded-lg flex items-center justify-center shrink-0"
                      style={{ background: `${WA_GREEN}20` }}>
                      <WhatsAppIcon size={16} className="text-[#25D366]" />
                    </div>
                    <div>
                      <p className="text-xs sm:text-sm font-medium" style={{ color: WA_GREEN }}>WhatsApp Ã© o canal principal</p>
                      <p className="text-[10px] sm:text-xs text-muted-foreground mt-0.5">
                        Seu nÃºmero serÃ¡ usado para suporte, notificaÃ§Ãµes e recebimento de mÃ­dias
                      </p>
                    </div>
                  </div>

                  <div className="space-y-3 sm:space-y-4">
                    <div>
                      <label htmlFor={registerNameInputId} className="text-xs sm:text-sm font-medium mb-1 sm:mb-1.5 block text-muted-foreground">Seu nome</label>
                      <Input
                        id={registerNameInputId}
                        aria-label="Seu nome"
                        value={name}
                        onChange={e => setName(e.target.value)}
                        onKeyDown={e => handleKeyDown(e, handleRegister)}
                        placeholder="Como quer ser chamado"
                        className="h-10 sm:h-11 text-sm sm:text-base"
                        autoFocus
                      />
                    </div>

                    <div>
                      <label htmlFor={registerPhoneInputId} className="text-xs sm:text-sm font-medium mb-1 sm:mb-1.5 block text-muted-foreground">WhatsApp</label>
                      <div className="relative">
                        <Phone className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                          id={registerPhoneInputId}
                          aria-label="WhatsApp"
                          type="tel"
                          value={loginValue}
                          onChange={handleLoginInputChange}
                          onKeyDown={e => handleKeyDown(e, handleRegister)}
                          placeholder="(51) 99999-9999"
                          className="pl-9 h-10 sm:h-11 text-sm sm:text-base"
                          autoComplete="tel"
                        />
                      </div>
                    </div>

                    <Button
                      className="w-full h-10 sm:h-11 border-0 text-sm sm:text-base font-medium text-white"
                      style={{ background: WA_GREEN }}
                      onClick={handleRegister}
                      disabled={!name.trim() || getLoginRaw().replace(/\D/g, '').length < 10}
                    >
                      Continuar
                      <ArrowRight className="h-4 w-4 ml-1.5" />
                    </Button>
                  </div>

                  <AuthFlowFooter
                    prefix="Ja tem conta?"
                    actionLabel="Entrar"
                    onAction={chooseLogin}
                  />
                </motion.div>
              )}

              {/* â”€â”€â”€ Step: Register Password â”€â”€â”€ */}
              {step === 'register-otp' && (
                <motion.div
                  key="register-otp"
                  variants={slideVariants}
                  initial="enter"
                  animate="center"
                  exit="exit"
                  custom={1}
                  transition={{ duration: 0.25 }}
                  className="p-5 sm:p-8 space-y-4 sm:space-y-5"
                >
                  <OtpCodeStep
                    title="Valide seu WhatsApp"
                    description={`Enviamos um codigo de 6 digitos para ${registerMaskedPhone || 'seu numero'}.`}
                    onBack={goBack}
                    helper={
                      <AuthHelpHint>
                        O cadastro so continua quando esse numero for confirmado e ainda nao existir na base.
                      </AuthHelpHint>
                    }
                    inputVariant="slots"
                    value={registerCode}
                    onChange={setRegisterCode}
                    submitLabel="Confirmar codigo"
                    submitLoadingLabel="Validando..."
                    onSubmit={handleVerifyRegisterCode}
                    submitDisabled={registerCode.length !== 6}
                    isSubmitting={isSubmitting}
                    buttonClassName="w-full h-10 sm:h-11 border-0 text-sm sm:text-base font-medium text-white"
                    buttonStyle={{ background: WA_GREEN }}
                    resendLayout="inline"
                    resendPrompt="Nao recebeu?"
                    resendCountdown={registerResendCountdown}
                    formatCountdown={formatCountdown}
                    onResend={handleResendRegisterCode}
                    resendButtonLabel="Reenviar codigo"
                  />
                </motion.div>
              )}

              {step === 'register-welcome' && (
                <motion.div
                  key="register-welcome"
                  variants={slideVariants}
                  initial="enter"
                  animate="center"
                  exit="exit"
                  custom={1}
                  transition={{ duration: 0.25 }}
                  className="p-5 sm:p-8 space-y-5 sm:space-y-6"
                >
                  <div className="text-center space-y-3">
                    <motion.div
                      initial={{ scale: 0 }}
                      animate={{ scale: 1 }}
                      transition={{ type: 'spring', duration: 0.6 }}
                      className="inline-flex"
                    >
                      <div className="h-16 w-16 rounded-2xl bg-green-500/10 flex items-center justify-center">
                        <CheckCircle2 className="h-8 w-8 text-green-500" />
                      </div>
                    </motion.div>
                    <h2 className="text-lg sm:text-xl font-semibold">{registerWelcomeTitle || `Bem-vindo, ${name.split(' ')[0]}!`}</h2>
                    <p className="text-sm text-muted-foreground">
                      {registerWelcomeDescription || 'Sua conta esta pronta. Agora escolha o plano para ativar seu evento.'}
                    </p>
                  </div>

                  <div className="rounded-xl border border-border/40 bg-muted/20 p-3">
                    <p className="text-xs text-muted-foreground leading-relaxed">
                      {registerNextHint}
                    </p>
                  </div>

                  <Button
                    className="w-full h-10 sm:h-11 border-0 text-sm sm:text-base font-medium text-white"
                    style={{ background: WA_GREEN }}
                    onClick={handleContinueAfterRegister}
                    disabled={isContinuing}
                  >
                    {isContinuing ? (
                      <Loader2 className="h-4 w-4 animate-spin mr-2" />
                    ) : null}
                    {isContinuing ? 'Abrindo...' : registerNextLabel}
                  </Button>
                </motion.div>
              )}

              {/* â”€â”€â”€ Step: Forgot Password â”€â”€â”€ */}
              {step === 'forgot' && (
                <motion.div
                  key="forgot"
                  variants={slideVariants}
                  initial="enter"
                  animate="center"
                  exit="exit"
                  custom={1}
                  transition={{ duration: 0.25 }}
                  className="p-5 sm:p-8 space-y-4 sm:space-y-5"
                >
                  <RecoveryRequestStep
                    description="Informe seu WhatsApp ou e-mail para receber um codigo de recuperacao"
                    onBack={goBack}
                    identityInputId={forgotIdentityInputId}
                    identityAriaLabel="WhatsApp ou E-mail"
                    identityValue={loginValue}
                    onIdentityChange={handleLoginInputChange}
                    onIdentityKeyDown={(event) => handleKeyDown(event, handleForgotPassword)}
                    helper={
                      <AuthHelpHint>
                        Enviaremos um codigo de 6 digitos via <strong>WhatsApp</strong> ou <strong>e-mail</strong> para confirmar sua identidade.
                      </AuthHelpHint>
                    }
                    submitLabel="Enviar codigo"
                    submitLoadingLabel="Enviando..."
                    onSubmit={handleForgotPassword}
                    submitDisabled={!isValidLogin()}
                    isSubmitting={isSubmitting}
                  />
                </motion.div>
              )}

              {/* â”€â”€â”€ Step: Enter Code â”€â”€â”€ */}
              {step === 'forgot-code' && (
                <motion.div
                  key="forgot-code"
                  variants={slideVariants}
                  initial="enter"
                  animate="center"
                  exit="exit"
                  custom={1}
                  transition={{ duration: 0.25 }}
                  className="p-5 sm:p-8 space-y-4 sm:space-y-5"
                >
                  <OtpCodeStep
                    title="Digite o codigo"
                    description={
                      resetMethod === 'whatsapp'
                        ? `Se encontrarmos uma conta vinculada a ${forgotDestinationMasked || 'este WhatsApp'}, enviaremos um codigo de 6 digitos por WhatsApp.`
                        : `Se encontrarmos uma conta vinculada a ${forgotDestinationMasked || 'este e-mail'}, enviaremos um codigo de 6 digitos por e-mail.`
                    }
                    secondaryDescription="Por seguranca, nao confirmamos se o contato informado esta cadastrado."
                    onBack={goBack}
                    value={resetCode}
                    onChange={setResetCode}
                    inputId={forgotCodeInputId}
                    inputAriaLabel="Codigo de verificacao"
                    submitLabel="Confirmar codigo"
                    onSubmit={handleVerifyForgotCode}
                    submitDisabled={resetCode.length !== 6}
                    isSubmitting={isSubmitting}
                    resendLayout="stacked"
                    resendCountdownPrefix="Novo envio em"
                    resendCountdown={forgotResendCountdown}
                    formatCountdown={formatCountdown}
                    onResend={handleResendForgotCode}
                    resendButtonLabel="Reenviar codigo"
                  />
                </motion.div>
              )}

              {/* â”€â”€â”€ Step: New Password â”€â”€â”€ */}
              {step === 'forgot-reset' && (
                <motion.div
                  key="forgot-reset"
                  variants={slideVariants}
                  initial="enter"
                  animate="center"
                  exit="exit"
                  custom={1}
                  transition={{ duration: 0.25 }}
                  className="p-5 sm:p-8 space-y-4 sm:space-y-5"
                >
                  <PasswordSetupStep
                    title="Nova senha"
                    description="Crie uma nova senha segura"
                    onBack={goBack}
                    passwordInputId={forgotPasswordInputId}
                    passwordAriaLabel="Nova senha"
                    password={password}
                    onPasswordChange={setPassword}
                    confirmationInputId={forgotPasswordConfirmationInputId}
                    confirmationAriaLabel="Confirmar nova senha"
                    confirmation={passwordConfirm}
                    onConfirmationChange={setPasswordConfirm}
                    onConfirmationKeyDown={(event) => handleKeyDown(event, handleResetPassword)}
                    showPassword={showPassword}
                    onToggleShowPassword={() => setShowPassword(!showPassword)}
                    strength={password ? strength : undefined}
                    submitLabel="Redefinir senha"
                    submitLoadingLabel="Redefinindo..."
                    onSubmit={handleResetPassword}
                    submitDisabled={password.length < 8 || password !== passwordConfirm}
                    isSubmitting={isSubmitting}
                  />
                </motion.div>
              )}

              {/* â”€â”€â”€ Step: Success â”€â”€â”€ */}
              {step === 'forgot-success' && (
                <motion.div
                  key="forgot-success"
                  variants={slideVariants}
                  initial="enter"
                  animate="center"
                  exit="exit"
                  custom={1}
                  transition={{ duration: 0.25 }}
                  className="p-5 sm:p-8 space-y-5 sm:space-y-6"
                >
                  <AuthFlowSuccessState
                    title="Senha redefinida!"
                    description="Sua senha foi alterada com sucesso. Entrando na sua conta..."
                    statusLabel="Redirecionando..."
                  />
                </motion.div>
              )}
            </AnimatePresence>
          </motion.div>

          {/* Footer */}
          <motion.p
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 0.5 }}
            className="text-center text-[10px] sm:text-xs text-muted-foreground/40 mt-6 sm:mt-8"
          >
            Â© {new Date().getFullYear()} Evento Vivo Â· Plataforma de experiÃªncias vivas
          </motion.p>
        </div>
      </div>
    </div>
  );
}






