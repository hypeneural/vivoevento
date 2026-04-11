import { describe, expect, it } from 'vitest';

import { invitationAuthFlowReducer } from './invitationAuthFlowReducer';
import { createInvitationAuthFlowState } from './invitationAuthFlowTypes';

describe('invitationAuthFlowReducer', () => {
  it('starts from the expected initial step', () => {
    expect(createInvitationAuthFlowState('method')).toEqual({ step: 'method' });
    expect(createInvitationAuthFlowState('forgot')).toEqual({ step: 'forgot' });
  });

  it('moves through the main invitation-auth states with semantic actions', () => {
    let state = createInvitationAuthFlowState('method');

    state = invitationAuthFlowReducer(state, { type: 'CHOOSE_LOGIN' });
    expect(state).toEqual({ step: 'login' });

    state = invitationAuthFlowReducer(state, { type: 'START_PASSWORD_RECOVERY' });
    expect(state).toEqual({ step: 'forgot' });

    state = invitationAuthFlowReducer(state, { type: 'FORGOT_OTP_SENT' });
    expect(state).toEqual({ step: 'forgot-code' });

    state = invitationAuthFlowReducer(state, { type: 'FORGOT_OTP_CONFIRMED' });
    expect(state).toEqual({ step: 'forgot-reset' });

    state = invitationAuthFlowReducer(state, { type: 'PASSWORD_RESET_SUCCEEDED' });
    expect(state).toEqual({ step: 'forgot-success' });
  });

  it('supports the register branch and explicit back navigation', () => {
    let state = createInvitationAuthFlowState('method');

    state = invitationAuthFlowReducer(state, { type: 'CHOOSE_REGISTER' });
    expect(state).toEqual({ step: 'register' });

    state = invitationAuthFlowReducer(state, { type: 'REGISTER_OTP_SENT' });
    expect(state).toEqual({ step: 'register-otp' });

    state = invitationAuthFlowReducer(state, { type: 'RETURN_TO_REGISTER' });
    expect(state).toEqual({ step: 'register' });

    state = invitationAuthFlowReducer(state, { type: 'REGISTER_COMPLETED' });
    expect(state).toEqual({ step: 'register-welcome' });

    state = invitationAuthFlowReducer(state, { type: 'RETURN_TO_METHOD' });
    expect(state).toEqual({ step: 'method' });
  });
});
