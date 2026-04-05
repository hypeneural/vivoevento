<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class UploadCurrentUserAvatarAction
{
    public function execute(User $user, UploadedFile $file): array
    {
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $directory = "avatars/{$user->id}";
        $filename = Str::random(24) . '.webp';
        $storedPath = "{$directory}/{$filename}";

        $image = Image::decode($file)
            ->cover(512, 512);

        $encodedImage = $image->encodeUsingMediaType('image/webp', 85);

        Storage::disk('public')->put($storedPath, (string) $encodedImage);

        $user->update([
            'avatar_path' => $storedPath,
        ]);

        activity()
            ->performedOn($user)
            ->log('Avatar atualizado');

        return [
            'avatar_path' => $storedPath,
            'avatar_url' => Storage::disk('public')->url($storedPath),
        ];
    }
}
