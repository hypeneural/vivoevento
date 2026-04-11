import type { InvitationAuthFlowAction, InvitationAuthFlowState } from './invitationAuthFlowTypes';
import { createInvitationAuthFlowState } from './invitationAuthFlowTypes';

export function invitationAuthFlowReducer(
  state: InvitationAuthFlowState,
  action: InvitationAuthFlowAction,
): InvitationAuthFlowState {
  switch (action.type) {
    case 'RESTORE_INITIAL_STEP':
      return createInvitationAuthFlowState(action.initialStep);
    case 'CHOOSE_LOGIN':
      return { step: 'login' };
    case 'CHOOSE_REGISTER':
      return { step: 'register' };
    case 'REGISTER_OTP_SENT':
      return { step: 'register-otp' };
    case 'REGISTER_COMPLETED':
      return { step: 'register-welcome' };
    case 'START_PASSWORD_RECOVERY':
      return { step: 'forgot' };
    case 'FORGOT_OTP_SENT':
      return { step: 'forgot-code' };
    case 'FORGOT_OTP_CONFIRMED':
      return { step: 'forgot-reset' };
    case 'PASSWORD_RESET_SUCCEEDED':
      return { step: 'forgot-success' };
    case 'RETURN_TO_METHOD':
      return { step: 'method' };
    case 'RETURN_TO_LOGIN':
      return { step: 'login' };
    case 'RETURN_TO_REGISTER':
      return { step: 'register' };
    case 'RETURN_TO_FORGOT_REQUEST':
      return { step: 'forgot' };
    case 'RETURN_TO_FORGOT_CODE':
      return { step: 'forgot-code' };
    default:
      return state;
  }
}
