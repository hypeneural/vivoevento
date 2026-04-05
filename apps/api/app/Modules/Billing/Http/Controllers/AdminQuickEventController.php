<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\CreateAdminQuickEventAction;
use App\Modules\Billing\Http\Requests\StoreAdminQuickEventRequest;
use App\Modules\Billing\Http\Resources\AdminQuickEventResource;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class AdminQuickEventController extends BaseController
{
    public function store(
        StoreAdminQuickEventRequest $request,
        CreateAdminQuickEventAction $action,
    ): JsonResponse {
        return $this->created(
            new AdminQuickEventResource(
                $action->execute($request->validated(), $request->user())
            )
        );
    }
}
