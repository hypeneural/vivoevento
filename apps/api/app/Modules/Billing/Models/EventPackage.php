<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\EventPackageAudience;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventPackage extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\EventPackageFactory
    {
        return \Database\Factories\EventPackageFactory::new();
    }

    protected $fillable = [
        'code',
        'name',
        'description',
        'target_audience',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'target_audience' => EventPackageAudience::class,
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(EventPackagePrice::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(EventPackageFeature::class);
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(EventAccessGrant::class, 'package_id');
    }
}
