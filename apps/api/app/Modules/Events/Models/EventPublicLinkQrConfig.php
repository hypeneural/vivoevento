<?php

namespace App\Modules\Events\Models;

use App\Shared\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPublicLinkQrConfig extends Model
{
    use HasFactory, HasAudit;

    protected static function newFactory(): \Database\Factories\EventPublicLinkQrConfigFactory
    {
        return \Database\Factories\EventPublicLinkQrConfigFactory::new();
    }

    protected $fillable = [
        'event_id',
        'link_key',
        'config_version',
        'config_json',
        'svg_path',
        'png_path',
        'last_rendered_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'config_json' => 'array',
        'last_rendered_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'updated_by');
    }
}
