<?php
namespace App\Modules\Plans\Http\Controllers;
use App\Modules\Plans\Models\Plan;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureCanViewBilling($request);

        return $this->success(
            Plan::query()
                ->where('status', 'active')
                ->with(['prices', 'features'])
                ->orderBy('name')
                ->get()
        );
    }

    public function show(Request $request, Plan $plan): JsonResponse
    {
        $this->ensureCanViewBilling($request);

        return $this->success($plan->load(['prices', 'features']));
    }

    private function ensureCanViewBilling(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user
                && (
                    $user->can('billing.view')
                    || $user->can('billing.manage')
                    || $user->can('billing.purchase')
                    || $user->can('billing.manage_subscription')
                ),
            403
        );
    }
}
