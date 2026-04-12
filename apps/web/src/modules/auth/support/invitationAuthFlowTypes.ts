import type { LoginInitialStep } from '@/modules/auth/login-navigation';

export type InvitationAuthFlowStep =
  | 'method'
  | 'login'
  | 'register'
  | 'register-otp'
  | 'register-welcome'
  | 'forgot'
  | 'forgot-code'
  | 'forgot-reset'
  | 'forgot-success';

export type InvitationAuthFlowState = {
  step: InvitationAuthFlowStep;
  history: InvitationAuthFlowStep[];
  canGoBack: boolean;
};

export type InvitationAuthFlowAction =
  | { type: 'RESTORE_INITIAL_STEP'; initialStep: LoginInitialStep }
  | { type: 'CHOOSE_LOGIN' }
  | { type: 'CHOOSE_REGISTER' }
  | { type: 'REGISTER_OTP_SENT' }
  | { type: 'REGISTER_COMPLETED' }
  | { type: 'START_PASSWORD_RECOVERY' }
  | { type: 'FORGOT_OTP_SENT' }
  | { type: 'FORGOT_OTP_CONFIRMED' }
  | { type: 'PASSWORD_RESET_SUCCEEDED' }
  | { type: 'GO_BACK' };

function buildState(history: InvitationAuthFlowStep[]): InvitationAuthFlowState {
  const nextHistory = history.length > 0 ? history : ['method'];

  return {
    step: nextHistory[nextHistory.length - 1],
    history: nextHistory,
    canGoBack: nextHistory.length > 1,
  };
}

export function createInvitationAuthFlowState(initialStep: LoginInitialStep): InvitationAuthFlowState {
  return buildState(initialStep === 'forgot' ? ['login', 'forgot'] : ['method']);
}

export function createInvitationAuthFlowStateFromHistory(
  history: InvitationAuthFlowStep[],
): InvitationAuthFlowState {
  return buildState(history);
}
