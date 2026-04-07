<?php

namespace App\Modules\Partners\Models;

use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerStat extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $primaryKey = 'organization_id';

    protected $keyType = 'int';

    protected static function newFactory(): \Database\Factories\PartnerStatFactory
    {
        return \Database\Factories\PartnerStatFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'clients_count',
        'events_count',
        'active_events_count',
        'team_size',
        'active_bonus_grants_count',
        'subscription_plan_code',
        'subscription_plan_name',
        'subscription_status',
        'subscription_billing_cycle',
        'subscription_revenue_cents',
        'event_package_revenue_cents',
        'total_revenue_cents',
        'last_paid_invoice_at',
        'refreshed_at',
    ];

    protected $casts = [
        'clients_count' => 'integer',
        'events_count' => 'integer',
        'active_events_count' => 'integer',
        'team_size' => 'integer',
        'active_bonus_grants_count' => 'integer',
        'subscription_revenue_cents' => 'integer',
        'event_package_revenue_cents' => 'integer',
        'total_revenue_cents' => 'integer',
        'last_paid_invoice_at' => 'datetime',
        'refreshed_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
