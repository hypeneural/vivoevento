import { useCallback, useEffect, useReducer } from 'react';

import type { LoginInitialStep } from '@/modules/auth/login-navigation';
import { invitationAuthFlowReducer } from '@/modules/auth/support/invitationAuthFlowReducer';
import { createInvitationAuthFlowState } from '@/modules/auth/support/invitationAuthFlowTypes';

export function useInvitationAuthFlow(initialStep: LoginInitialStep) {
  const [state, dispatch] = useReducer(
    invitationAuthFlowReducer,
    initialStep,
    createInvitationAuthFlowState,
  );

  useEffect(() => {
    dispatch({ type: 'RESTORE_INITIAL_STEP', initialStep });
  }, [initialStep]);

  const chooseLogin = useCallback(() => dispatch({ type: 'CHOOSE_LOGIN' }), []);
  const chooseRegister = useCallback(() => dispatch({ type: 'CHOOSE_REGISTER' }), []);
  const registerOtpSent = useCallback(() => dispatch({ type: 'REGISTER_OTP_SENT' }), []);
  const registerCompleted = useCallback(() => dispatch({ type: 'REGISTER_COMPLETED' }), []);
  const startPasswordRecovery = useCallback(
    () => dispatch({ type: 'START_PASSWORD_RECOVERY' }),
    [],
  );
  const forgotOtpSent = useCallback(() => dispatch({ type: 'FORGOT_OTP_SENT' }), []);
  const forgotOtpConfirmed = useCallback(
    () => dispatch({ type: 'FORGOT_OTP_CONFIRMED' }),
    [],
  );
  const passwordResetSucceeded = useCallback(
    () => dispatch({ type: 'PASSWORD_RESET_SUCCEEDED' }),
    [],
  );
  const returnToMethod = useCallback(() => dispatch({ type: 'RETURN_TO_METHOD' }), []);
  const returnToLogin = useCallback(() => dispatch({ type: 'RETURN_TO_LOGIN' }), []);
  const returnToRegister = useCallback(() => dispatch({ type: 'RETURN_TO_REGISTER' }), []);
  const returnToForgotRequest = useCallback(
    () => dispatch({ type: 'RETURN_TO_FORGOT_REQUEST' }),
    [],
  );
  const returnToForgotCode = useCallback(
    () => dispatch({ type: 'RETURN_TO_FORGOT_CODE' }),
    [],
  );

  return {
    step: state.step,
    chooseLogin,
    chooseRegister,
    registerOtpSent,
    registerCompleted,
    startPasswordRecovery,
    forgotOtpSent,
    forgotOtpConfirmed,
    passwordResetSucceeded,
    returnToMethod,
    returnToLogin,
    returnToRegister,
    returnToForgotRequest,
    returnToForgotCode,
  };
}
