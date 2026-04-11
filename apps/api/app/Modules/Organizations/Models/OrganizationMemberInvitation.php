<?php

namespace App\Modules\Organizations\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMemberInvitation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    protected static function newFactory(): \Database\Factories\OrganizationMemberInvitationFactory
    {
        return \Database\Factories\OrganizationMemberInvitationFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'invited_by',
        'existing_user_id',
        'accepted_user_id',
        'invitee_name',
        'invitee_email',
        'invitee_phone',
        'role_key',
        'delivery_channel',
        'delivery_status',
        'delivery_error',
        'token',
        'token_expires_at',
        'invitation_url',
        'status',
        'accepted_at',
        'revoked_at',
        'last_sent_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'invited_by');
    }

    public function existingUser(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'existing_user_id');
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'accepted_user_id');
    }
}
