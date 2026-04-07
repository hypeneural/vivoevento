<?php

namespace App\Modules\Partners\Models;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerProfile extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\PartnerProfileFactory
    {
        return \Database\Factories\PartnerProfileFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'segment',
        'business_stage',
        'account_owner_user_id',
        'notes',
        'tags_json',
        'onboarded_at',
    ];

    protected $casts = [
        'tags_json' => 'array',
        'onboarded_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function accountOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_owner_user_id');
    }
}
