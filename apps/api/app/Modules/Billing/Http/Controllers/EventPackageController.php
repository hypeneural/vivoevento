<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Enums\EventPackageAudience;
use App\Modules\Billing\Http\Requests\ListEventPackagesRequest;
use App\Modules\Billing\Http\Resources\EventPackageResource;
use App\Modules\Billing\Models\EventPackage;
use App\Modules\Billing\Queries\ListEventPackagesQuery;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventPackageController extends BaseController
{
    public function index(ListEventPackagesRequest $request): JsonResponse
    {
        $packages = (new ListEventPackagesQuery(
            targetAudience: $request->targetAudience(),
            activeOnly: false,
        ))->query()->get();

        return $this->success(EventPackageResource::collection($packages));
    }

    public function show(EventPackage $eventPackage): JsonResponse
    {
        return $this->success(
            new EventPackageResource(
                $eventPackage->load(['prices', 'features'])
            )
        );
    }

    public function publicIndex(ListEventPackagesRequest $request): JsonResponse
    {
        $packages = (new ListEventPackagesQuery(
            targetAudience: $request->targetAudience() ?? EventPackageAudience::DirectCustomer,
            activeOnly: true,
        ))->query()->get();

        return $this->success(EventPackageResource::collection($packages));
    }
}
