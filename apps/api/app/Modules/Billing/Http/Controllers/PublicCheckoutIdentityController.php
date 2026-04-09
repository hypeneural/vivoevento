<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\CheckPublicCheckoutIdentityAction;
use App\Modules\Billing\Http\Requests\CheckPublicCheckoutIdentityRequest;
use App\Modules\Billing\Http\Resources\PublicCheckoutIdentityResource;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCheckoutIdentityController extends BaseController
{
    public function check(
        Request $httpRequest,
        CheckPublicCheckoutIdentityRequest $request,
        CheckPublicCheckoutIdentityAction $action,
    ): JsonResponse {
        return $this->success(
            new PublicCheckoutIdentityResource(
                $action->execute(
                    $request->validated(),
                    $httpRequest->user('sanctum'),
                )
            )
        );
    }
}
