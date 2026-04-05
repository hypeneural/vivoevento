<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\WhatsApp\Enums\WhatsAppProviderKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppProvider extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\WhatsAppProviderFactory
    {
        return \Database\Factories\WhatsAppProviderFactory::new();
    }

    protected $table = 'whatsapp_providers';

    protected $fillable = [
        'key',
        'name',
        'is_active',
        'config_json',
    ];

    protected $casts = [
        'key' => WhatsAppProviderKey::class,
        'is_active' => 'boolean',
        'config_json' => 'array',
    ];

    // ─── Relationships ─────────────────────────────────────

    public function instances(): HasMany
    {
        return $this->hasMany(WhatsAppInstance::class, 'provider_id');
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
