<?php

namespace App\Modules\Channels\Models;

use App\Modules\Channels\Enums\ChannelType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'channel_type', 'provider', 'external_id',
        'label', 'status', 'config_json', 'secret_hash',
    ];

    protected $casts = [
        'channel_type' => ChannelType::class,
        'config_json' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function whatsappInboxSessions(): HasMany
    {
        return $this->hasMany(\App\Modules\WhatsApp\Models\WhatsAppInboxSession::class, 'event_channel_id');
    }

    public function telegramInboxSessions(): HasMany
    {
        return $this->hasMany(\App\Modules\Telegram\Models\TelegramInboxSession::class, 'event_channel_id');
    }
}
