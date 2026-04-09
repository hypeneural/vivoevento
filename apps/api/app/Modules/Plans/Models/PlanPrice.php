<?php
namespace App\Modules\Plans\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPrice extends Model
{
    protected $fillable = [
        'plan_id', 'billing_cycle', 'currency', 'amount_cents',
        'gateway_provider', 'gateway_price_id', 'gateway_plan_id',
        'gateway_plan_payload_json', 'billing_type', 'billing_day',
        'trial_period_days', 'payment_methods_json', 'is_default',
    ];
    protected $casts = [
        'amount_cents' => 'integer',
        'billing_day' => 'integer',
        'trial_period_days' => 'integer',
        'gateway_plan_payload_json' => 'array',
        'payment_methods_json' => 'array',
        'is_default' => 'boolean',
    ];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
}
