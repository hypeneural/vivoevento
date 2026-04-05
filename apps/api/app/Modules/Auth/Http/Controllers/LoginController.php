<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Actions\LoginUserAction;
use App\Modules\Auth\Actions\LogoutUserAction;
use App\Modules\Auth\Actions\RegisterWithWhatsAppOtpAction;
use App\Modules\Auth\Actions\ResetPasswordWithOtpAction;
use App\Modules\Auth\Http\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RequestRegisterOtpRequest;
use App\Modules\Auth\Http\Requests\ResendForgotPasswordOtpRequest;
use App\Modules\Auth\Http\Requests\ResendRegisterOtpRequest;
use App\Modules\Auth\Http\Requests\ResetPasswordRequest;
use App\Modules\Auth\Http\Requests\VerifyForgotPasswordOtpRequest;
use App\Modules\Auth\Http\Requests\VerifyRegisterOtpRequest;
use App\Modules\Users\Http\Resources\UserResource;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginController extends BaseController
{
    /**
     * POST /api/v1/auth/login
     *
     * Accepts login via WhatsApp phone or email + password.
     */
    public function login(LoginRequest $request, LoginUserAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->getLoginIdentifier(),
            $request->validated('password'),
            $request->validated('device_name', 'web-panel'),
            $request->isPhoneLogin(),
            $request->ip(),
            $request->userAgent()
        );

        return $this->success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request, LogoutUserAction $action): JsonResponse
    {
        $action->execute($request);

        return $this->success(message: 'Logged out');
    }

    /**
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(
        ForgotPasswordRequest $request,
        ResetPasswordWithOtpAction $action
    ): JsonResponse {
        return $this->success(
            $action->requestOtp(
                $request->getLoginIdentifier(),
                $request->isPhoneLogin(),
                $request->ip()
            )
        );
    }

    /**
     * POST /api/v1/auth/forgot-password/resend-otp
     */
    public function resendForgotPasswordOtp(
        ResendForgotPasswordOtpRequest $request,
        ResetPasswordWithOtpAction $action
    ): JsonResponse {
        return $this->success(
            $action->resendOtp(
                $request->sessionToken(),
                $request->ip()
            )
        );
    }

    /**
     * POST /api/v1/auth/forgot-password/verify-otp
     */
    public function verifyForgotPasswordOtp(
        VerifyForgotPasswordOtpRequest $request,
        ResetPasswordWithOtpAction $action
    ): JsonResponse {
        return $this->success(
            $action->verifyOtp(
                $request->sessionToken(),
                $request->validated('code')
            )
        );
    }

    /**
     * POST /api/v1/auth/register/request-otp
     *
     * Starts the WhatsApp-first signup flow.
     */
    public function requestRegisterOtp(
        RequestRegisterOtpRequest $request,
        RegisterWithWhatsAppOtpAction $action
    ): JsonResponse {
        return $this->success(
            $action->requestOtp(
                $request->getName(),
                $request->getNormalizedPhone(),
                $request->ip(),
                $request->journey(),
            )
        );
    }

    /**
     * POST /api/v1/auth/register/resend-otp
     */
    public function resendRegisterOtp(
        ResendRegisterOtpRequest $request,
        RegisterWithWhatsAppOtpAction $action
    ): JsonResponse {
        return $this->success(
            $action->resendOtp(
                $request->sessionToken(),
                $request->ip()
            )
        );
    }

    /**
     * POST /api/v1/auth/register/verify-otp
     */
    public function verifyRegisterOtp(
        VerifyRegisterOtpRequest $request,
        RegisterWithWhatsAppOtpAction $action
    ): JsonResponse {
        $result = $action->verifyOtp(
            $request->sessionToken(),
            $request->validated('code'),
            $request->deviceName()
        );

        return $this->success([
            'message' => $result['message'],
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'onboarding' => $result['onboarding'],
        ]);
    }

    /**
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(
        ResetPasswordRequest $request,
        ResetPasswordWithOtpAction $action
    ): JsonResponse {
        $result = $action->resetPassword(
            $request->sessionToken(),
            $request->validated('password'),
            $request->deviceName()
        );

        return $this->success([
            'message' => $result['message'],
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }
}
