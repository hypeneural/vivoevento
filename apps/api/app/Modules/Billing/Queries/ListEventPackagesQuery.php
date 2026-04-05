<?php

namespace App\Modules\Billing\Queries;

use App\Modules\Billing\Enums\EventPackageAudience;
use App\Modules\Billing\Models\EventPackage;
use Illuminate\Database\Eloquent\Builder;

class ListEventPackagesQuery
{
    public function __construct(
        private readonly ?EventPackageAudience $targetAudience = null,
        private readonly bool $activeOnly = true,
    ) {}

    public function query(): Builder
    {
        return EventPackage::query()
            ->with([
                'prices' => fn ($query) => $query->orderByDesc('is_default')->orderBy('amount_cents'),
                'features' => fn ($query) => $query->orderBy('feature_key'),
            ])
            ->when($this->activeOnly, fn (Builder $query) => $query->where('is_active', true))
            ->when($this->targetAudience, function (Builder $query, EventPackageAudience $targetAudience) {
                $query->whereIn('target_audience', [$targetAudience->value, EventPackageAudience::Both->value]);
            })
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
