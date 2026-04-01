<?php

namespace App\Shared\Concerns;

use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait for models that belong to an Organization.
 *
 * @property int $organization_id
 * @property-read Organization $organization
 */
trait HasOrganization
{
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scope query to a specific organization.
     */
    public function scopeForOrganization($query, int|Organization $organization)
    {
        $id = $organization instanceof Organization ? $organization->id : $organization;

        return $query->where('organization_id', $id);
    }
}
