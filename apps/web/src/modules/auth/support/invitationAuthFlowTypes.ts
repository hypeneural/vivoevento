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
  | { type: 'RETURN_TO_METHOD' }
  | { type: 'RETURN_TO_LOGIN' }
  | { type: 'RETURN_TO_REGISTER' }
  | { type: 'RETURN_TO_FORGOT_REQUEST' }
  | { type: 'RETURN_TO_FORGOT_CODE' };

export function createInvitationAuthFlowState(initialStep: LoginInitialStep): InvitationAuthFlowState {
  return {
    step: initialStep === 'forgot' ? 'forgot' : 'method',
  };
}
