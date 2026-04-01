<?php

namespace App\Modules\Users\Http\Controllers;

use App\Modules\Users\Http\Resources\UserResource;
use App\Modules\Users\Models\User;
use App\Shared\Http\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends BaseController
{
    public function index(): AnonymousResourceCollection
    {
        $users = User::query()->latest()->paginate(20);

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }
}
