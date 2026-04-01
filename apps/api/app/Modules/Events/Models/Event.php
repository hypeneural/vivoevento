<?php

namespace App\Modules\Events\Models;

use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Enums\EventType;
use App\Shared\Concerns\HasAudit;
use App\Shared\Concerns\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, SoftDeletes, HasOrganization, HasAudit;

    protected static function newFactory(): \Database\Factories\EventFactory
    {
        return \Database\Factories\EventFactory::new();
    }

    protected $fillable = [
        'uuid',
        'organization_id',
        'client_id',
        'created_by',
        'title',
        'slug',
        'upload_slug',
        'event_type',
        'status',
        'visibility',
        'moderation_mode',
        'starts_at',
        'ends_at',
        'location_name',
        'description',
        'cover_image_path',
        'logo_path',
        'qr_code_path',
        'primary_color',
        'secondary_color',
        'public_url',
        'upload_url',
        'retention_days',
        'purchased_plan_snapshot_json',
    ];

    protected $casts = [
        'event_type' => EventType::class,
        'status' => EventStatus::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'retention_days' => 'integer',
        'purchased_plan_snapshot_json' => 'array',
    ];

    // ─── Boot ─────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
            if (empty($event->upload_slug)) {
                $event->upload_slug = Str::random(12);
            }
        });
    }

    // ─── Relationships ─────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'created_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Clients\Models\Client::class);
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(\App\Modules\EventTeam\Models\EventTeamMember::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(EventModule::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(\App\Modules\Channels\Models\EventChannel::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(\App\Modules\MediaProcessing\Models\EventMedia::class);
    }

    public function banners(): HasMany
    {
        return $this->hasMany(EventBanner::class);
    }

    public function wallSettings(): HasOne
    {
        return $this->hasOne(\App\Modules\Wall\Models\EventWallSetting::class);
    }

    public function playSettings(): HasOne
    {
        return $this->hasOne(\App\Modules\Play\Models\EventPlaySetting::class);
    }

    public function hubSettings(): HasOne
    {
        return $this->hasOne(\App\Modules\Hub\Models\EventHubSetting::class);
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', EventStatus::Active);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', EventStatus::Draft);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    // ─── Helpers ───────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === EventStatus::Active;
    }

    public function isDraft(): bool
    {
        return $this->status === EventStatus::Draft;
    }

    public function isAutoModeration(): bool
    {
        return $this->moderation_mode === 'auto';
    }

    public function isModuleEnabled(string $moduleKey): bool
    {
        $module = $this->relationLoaded('modules')
            ? $this->modules->firstWhere('module_key', $moduleKey)
            : $this->modules()->where('module_key', $moduleKey)->first();

        return (bool) $module?->is_enabled;
    }

    public function publicUploadUrl(): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return "{$frontendUrl}/upload/{$this->upload_slug}";
    }

    public function publicUploadApiUrl(): string
    {
        $backendUrl = rtrim((string) config('app.url'), '/');

        return "{$backendUrl}/api/v1/public/events/{$this->upload_slug}/upload";
    }
}
