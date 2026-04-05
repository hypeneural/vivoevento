<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Shared\Concerns\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory, HasOrganization;

    protected static function newFactory(): \Database\Factories\InvoiceFactory
    {
        return \Database\Factories\InvoiceFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'billing_order_id',
        'invoice_number',
        'status',
        'amount_cents',
        'currency',
        'issued_at',
        'due_at',
        'paid_at',
        'snapshot_json',
    ];

    protected $casts = [
        'status' => InvoiceStatus::class,
        'amount_cents' => 'integer',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'snapshot_json' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(BillingOrder::class, 'billing_order_id');
    }
}
