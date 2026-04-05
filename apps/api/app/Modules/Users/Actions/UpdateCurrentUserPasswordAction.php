<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdateCurrentUserPasswordAction
{
    /**
     * @throws ValidationException
     */
    public function execute(User $user, array $data): User
    {
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'A senha atual informada nao confere.',
            ]);
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        activity()
            ->performedOn($user)
            ->log('Senha atualizada');

        return $user->fresh();
    }
}
