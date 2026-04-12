import type { InvitationAuthFlowAction, InvitationAuthFlowState } from './invitationAuthFlowTypes';
import {
  createInvitationAuthFlowState,
  createInvitationAuthFlowStateFromHistory,
} from './invitationAuthFlowTypes';

function replaceAuthBranch(
  state: InvitationAuthFlowState,
  target: 'login' | 'register',
): InvitationAuthFlowState {
  const [root] = state.history;

  if (root === 'method') {
    return createInvitationAuthFlowStateFromHistory(['method', target]);
  }

  if (root === 'login' || root === 'register') {
    return createInvitationAuthFlowStateFromHistory(
      root === target ? [root] : [root, target],
    );
  }

  return createInvitationAuthFlowStateFromHistory([target]);
}

function pushStep(state: InvitationAuthFlowState, step: InvitationAuthFlowState['step']) {
  return createInvitationAuthFlowStateFromHistory([...state.history, step]);
}

export function invitationAuthFlowReducer(
  state: InvitationAuthFlowState,
  action: InvitationAuthFlowAction,
): InvitationAuthFlowState {
  switch (action.type) {
    case 'RESTORE_INITIAL_STEP':
      return createInvitationAuthFlowState(action.initialStep);
    case 'CHOOSE_LOGIN':
      return replaceAuthBranch(state, 'login');
    case 'CHOOSE_REGISTER':
      return replaceAuthBranch(state, 'register');
    case 'REGISTER_OTP_SENT':
      return pushStep(state, 'register-otp');
    case 'REGISTER_COMPLETED':
      return pushStep(state, 'register-welcome');
    case 'START_PASSWORD_RECOVERY':
      return pushStep(state, 'forgot');
    case 'FORGOT_OTP_SENT':
      return pushStep(state, 'forgot-code');
    case 'FORGOT_OTP_CONFIRMED':
      return pushStep(state, 'forgot-reset');
    case 'PASSWORD_RESET_SUCCEEDED':
      return pushStep(state, 'forgot-success');
    case 'GO_BACK':
      return createInvitationAuthFlowStateFromHistory(state.history.slice(0, -1));
    default:
      return state;
  }
}
