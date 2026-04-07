<?php

namespace App\Modules\WhatsApp\Models;

use App\Shared\Concerns\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppInboxSession extends Model
{
    use HasFactory, HasOrganization;

    protected static function newFactory(): \Database\Factories\WhatsAppInboxSessionFactory
    {
        return \Database\Factories\WhatsAppInboxSessionFactory::new();
    }

    protected $table = 'whatsapp_inbox_sessions';

    protected $fillable = [
        'organization_id',
        'event_id',
        'event_channel_id',
        'instance_id',
        'sender_external_id',
        'sender_phone',
        'sender_lid',
        'sender_name',
        'chat_external_id',
        'status',
        'activated_by_provider_message_id',
        'last_inbound_provider_message_id',
        'activated_at',
        'last_interaction_at',
        'expires_at',
        'closed_at',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'activated_at' => 'datetime',
        'last_interaction_at' => 'datetime',
        'expires_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInstance::class, 'instance_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Channels\Models\EventChannel::class, 'event_channel_id');
    }

    public function scopeActive($query)
    {
        return $query
            ->where('status', 'active')
            ->whereNull('closed_at')
            ->where(function ($builder) {
                $builder->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
