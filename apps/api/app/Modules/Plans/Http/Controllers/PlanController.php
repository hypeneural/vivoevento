<?php
namespace App\Modules\Plans\Http\Controllers;
use App\Modules\Plans\Models\Plan;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class PlanController extends BaseController
{
    public function index(): JsonResponse
    {
        return $this->success(Plan::with(['prices', 'features'])->get());
    }

    public function show(Plan $plan): JsonResponse
    {
        return $this->success($plan->load(['prices', 'features']));
    }
}
