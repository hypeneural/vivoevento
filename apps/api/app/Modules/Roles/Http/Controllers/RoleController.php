<?php

namespace App\Modules\Roles\Http\Controllers;

use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends BaseController
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get();

        return $this->success($roles);
    }

    public function show(Role $role): JsonResponse
    {
        return $this->success($role->load('permissions'));
    }
}
