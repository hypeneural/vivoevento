<?php
namespace App\Modules\Plans\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPrice extends Model
{
    protected $fillable = [
        'plan_id', 'billing_cycle', 'currency', 'amount_cents',
        'gateway_provider', 'gateway_price_id', 'is_default',
    ];
    protected $casts = ['amount_cents' => 'integer', 'is_default' => 'boolean'];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
}
