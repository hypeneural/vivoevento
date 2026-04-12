import { describe, expect, it } from 'vitest';

import { invitationAuthFlowReducer } from './invitationAuthFlowReducer';
import { createInvitationAuthFlowState } from './invitationAuthFlowTypes';

describe('invitationAuthFlowReducer', () => {
  it('starts from the expected initial step', () => {
    expect(createInvitationAuthFlowState('method')).toEqual({
      step: 'method',
      history: ['method'],
      canGoBack: false,
    });
    expect(createInvitationAuthFlowState('forgot')).toEqual({
      step: 'forgot',
      history: ['login', 'forgot'],
      canGoBack: true,
    });
  });

  it('moves through the main invitation-auth states with semantic actions and generic backtracking', () => {
    let state = createInvitationAuthFlowState('method');

    state = invitationAuthFlowReducer(state, { type: 'CHOOSE_LOGIN' });
    expect(state).toEqual({
      step: 'login',
      history: ['method', 'login'],
      canGoBack: true,
    });

    state = invitationAuthFlowReducer(state, { type: 'START_PASSWORD_RECOVERY' });
    expect(state).toEqual({
      step: 'forgot',
      history: ['method', 'login', 'forgot'],
      canGoBack: true,
    });

    state = invitationAuthFlowReducer(state, { type: 'FORGOT_OTP_SENT' });
    expect(state).toEqual({
      step: 'forgot-code',
      history: ['method', 'login', 'forgot', 'forgot-code'],
      canGoBack: true,
    });

    state = invitationAuthFlowReducer(state, { type: 'FORGOT_OTP_CONFIRMED' });
    expect(state).toEqual({
      step: 'forgot-reset',
      history: ['method', 'login', 'forgot', 'forgot-code', 'forgot-reset'],
      canGoBack: true,
    });

    state = invitationAuthFlowReducer(state, { type: 'PASSWORD_RESET_SUCCEEDED' });
    expect(state).toEqual({
      step: 'forgot-success',
      history: ['method', 'login', 'forgot', 'forgot-code', 'forgot-reset', 'forgot-success'],
      canGoBack: true,
    });

    state = invitationAuthFlowReducer(state, { type: 'GO_BACK' });
    expect(state).toEqual({
      step: 'forgot-reset',
      history: ['method', 'login', 'forgot', 'forgot-code', 'forgot-reset'],
      canGoBack: true,
    });
  });

  it('supports the register branch, sibling replacement and generic back navigation', () => {
    let state = createInvitationAuthFlowState('method');

    state = invitationAuthFlowReducer(state, { type: 'CHOOSE_REGISTER' });
    expect(state).toEqual({
      step: 'register',
      history: ['method', 'register'],
      canGoBack: true,
    });

    state = invitationAuthFlowReducer(state, { type: 'REGISTER_OTP_SENT' });
    expect(state).toEqual({
      step: 'register-otp',
      history: ['method', 'register', 'register-otp'],
      canGoBack: true,
    });

    state = invitationAuthFlowReducer(state, { type: 'GO_BACK' });
    expect(state).toEqual({
      step: 'register',
      history: ['method', 'register'],
      canGoBack: true,
    });

    state = invitationAuthFlowReducer(state, { type: 'CHOOSE_LOGIN' });
    expect(state).toEqual({
      step: 'login',
      history: ['method', 'login'],
      canGoBack: true,
    });

    state = invitationAuthFlowReducer(createInvitationAuthFlowState('forgot'), { type: 'GO_BACK' });
    expect(state).toEqual({
      step: 'login',
      history: ['login'],
      canGoBack: false,
    });

    state = invitationAuthFlowReducer(state, { type: 'CHOOSE_REGISTER' });
    expect(state).toEqual({
      step: 'register',
      history: ['login', 'register'],
      canGoBack: true,
    });

    state = invitationAuthFlowReducer(state, { type: 'GO_BACK' });
    expect(state).toEqual({
      step: 'login',
      history: ['login'],
      canGoBack: false,
    });
  });
});
