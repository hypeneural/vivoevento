<?php

namespace App\Modules\Users\Http\Controllers;

use App\Modules\Auth\Http\Resources\MeResource;
use App\Modules\Users\Actions\UpdateCurrentUserPasswordAction;
use App\Modules\Users\Actions\UploadCurrentUserAvatarAction;
use App\Modules\Users\Http\Requests\UpdateCurrentUserPasswordRequest;
use App\Modules\Users\Http\Requests\UploadCurrentUserAvatarRequest;
use App\Shared\Http\BaseController;
use App\Shared\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MeController extends BaseController
{
    /**
     * GET /api/v1/auth/me
     *
     * Most important endpoint of the system.
     * Returns everything the frontend needs to bootstrap the application.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->load([
            'roles',
            'permissions',
            'organizations',
            'organizationMembers',
        ]);

        return $this->success(new MeResource($user));
    }

    /**
     * PATCH /api/v1/auth/me
     *
     * Update profile: name, phone, preferences.
     */
    public function update(Request $request): JsonResponse
    {
        if ($request->has('phone')) {
            $request->merge([
                'phone' => PhoneNumber::normalizeBrazilianWhatsAppOrNull($request->input('phone')),
            ]);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'phone' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('users', 'phone')->ignore($request->user()->id),
            ],
            'preferences' => ['nullable', 'array'],
            'preferences.theme' => ['nullable', 'string', 'in:light,dark'],
            'preferences.locale' => ['nullable', 'string', 'in:pt-BR,en,es'],
        ]);

        $request->user()->update($validated);

        return $this->show($request);
    }

    /**
     * PATCH /api/v1/auth/me/password
     *
     * Update the password for the authenticated user.
     */
    public function updatePassword(
        UpdateCurrentUserPasswordRequest $request,
        UpdateCurrentUserPasswordAction $action
    ): JsonResponse
    {
        $action->execute($request->user(), $request->validated());

        return $this->success([
            'message' => 'Senha atualizada com sucesso.',
        ]);
    }

    /**
     * POST /api/v1/auth/me/avatar
     *
     * Upload user avatar to local storage.
     * Stores the final version as a normalized square WebP.
     */
    public function uploadAvatar(
        UploadCurrentUserAvatarRequest $request,
        UploadCurrentUserAvatarAction $action
    ): JsonResponse
    {
        return $this->success(
            $action->execute($request->user(), $request->file('avatar'))
        );
    }

    /**
     * DELETE /api/v1/auth/me/avatar
     *
     * Remove user avatar.
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        activity()
            ->performedOn($user)
            ->log('Avatar removido');

        return $this->success(null);
    }
}
