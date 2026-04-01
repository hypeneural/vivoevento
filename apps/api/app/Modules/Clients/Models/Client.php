<?php

namespace App\Modules\Clients\Models;

use App\Modules\Clients\Enums\ClientType;
use App\Shared\Concerns\HasAudit;
use App\Shared\Concerns\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes, HasOrganization, HasAudit;

    protected static function newFactory(): \Database\Factories\ClientFactory
    {
        return \Database\Factories\ClientFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'type',
        'name',
        'email',
        'phone',
        'document_number',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'type' => ClientType::class,
    ];

    // ─── Relationships ─────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Organizations\Models\Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(\App\Modules\Events\Models\Event::class);
    }
}
