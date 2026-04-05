<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\EntitlementMergeStrategy;
use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Enums\EventAccessGrantStatus;
use App\Shared\Concerns\HasOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class EventAccessGrant extends Model
{
    use HasFactory, HasOrganization;

    protected static function newFactory(): \Database\Factories\EventAccessGrantFactory
    {
        return \Database\Factories\EventAccessGrantFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'event_id',
        'source_type',
        'source_id',
        'package_id',
        'status',
        'priority',
        'merge_strategy',
        'starts_at',
        'ends_at',
        'features_snapshot_json',
        'limits_snapshot_json',
        'granted_by_user_id',
        'notes',
        'metadata_json',
    ];

    protected $casts = [
        'source_type' => EventAccessGrantSourceType::class,
        'status' => EventAccessGrantStatus::class,
        'merge_strategy' => EntitlementMergeStrategy::class,
        'priority' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'features_snapshot_json' => 'array',
        'limits_snapshot_json' => 'array',
        'metadata_json' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'granted_by_user_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(EventPackage::class, 'package_id');
    }

    public function scopeActiveAt(Builder $query, \DateTimeInterface|string|null $at = null): Builder
    {
        $at = match (true) {
            $at instanceof \DateTimeInterface => Carbon::instance(\DateTimeImmutable::createFromInterface($at)),
            is_string($at) => Carbon::parse($at),
            default => now(),
        };

        return $query
            ->where('status', EventAccessGrantStatus::Active)
            ->where(function (Builder $builder) use ($at) {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $builder) use ($at) {
                $builder->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }
}
