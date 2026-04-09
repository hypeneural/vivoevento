<?php

namespace App\Modules\Billing\Models;

use App\Shared\Concerns\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingProfile extends Model
{
    use HasFactory, HasOrganization;

    protected static function newFactory(): \Database\Factories\BillingProfileFactory
    {
        return \Database\Factories\BillingProfileFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'gateway_provider',
        'gateway_customer_id',
        'gateway_default_card_id',
        'payer_name',
        'payer_email',
        'payer_document',
        'payer_phone',
        'billing_address_json',
        'metadata_json',
    ];

    protected $casts = [
        'billing_address_json' => 'array',
        'metadata_json' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Organizations\Models\Organization::class);
    }
}
