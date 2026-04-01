<?php

namespace App\Modules\Users\Http\Controllers;

use App\Modules\Auth\Http\Resources\MeResource;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'preferences' => ['nullable', 'array'],
            'preferences.theme' => ['nullable', 'string', 'in:light,dark'],
            'preferences.locale' => ['nullable', 'string', 'in:pt-BR,en,es'],
        ]);

        $request->user()->update($validated);

        return $this->show($request);
    }

    /**
     * POST /api/v1/auth/me/avatar
     *
     * Upload user avatar to local storage.
     * Stores in: storage/app/public/avatars/{user_id}/{hash}.{ext}
     * Returns the public URL.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ], [
            'avatar.required' => 'Selecione uma imagem.',
            'avatar.image' => 'O arquivo precisa ser uma imagem.',
            'avatar.max' => 'A imagem não pode ter mais de 5MB.',
            'avatar.mimes' => 'Formato aceito: JPG, PNG ou WebP.',
        ]);

        $user = $request->user();
        $file = $request->file('avatar');

        // Delete old avatar if exists
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        // Generate a unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = Str::random(24) . '.' . $extension;
        $path = "avatars/{$user->id}";

        // Store on local public disk
        $storedPath = $file->storeAs($path, $filename, 'public');

        // Update user
        $user->update([
            'avatar_path' => $storedPath,
        ]);

        activity()
            ->performedOn($user)
            ->log('Avatar atualizado');

        return $this->success([
            'avatar_path' => $storedPath,
            'avatar_url' => '/storage/' . $storedPath,
        ]);
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
