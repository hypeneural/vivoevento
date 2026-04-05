<?php

namespace App\Modules\Wall\Models;

use App\Modules\Events\Models\Event;
use App\Modules\Wall\Enums\WallEventPhase;
use App\Modules\Wall\Enums\WallLayout;
use App\Modules\Wall\Enums\WallSelectionMode;
use App\Modules\Wall\Enums\WallStatus;
use App\Modules\Wall\Enums\WallTransition;
use App\Modules\Wall\Support\WallSelectionPreset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class EventWallSetting extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\EventWallSettingFactory
    {
        return \Database\Factories\EventWallSettingFactory::new();
    }

    protected $fillable = [
        'event_id',
        'wall_code',
        'is_enabled',
        'status',
        'layout',
        'transition_effect',
        'interval_ms',
        'queue_limit',
        'selection_mode',
        'event_phase',
        'selection_policy',
        'show_qr',
        'show_branding',
        'show_neon',
        'neon_text',
        'neon_color',
        'show_sender_credit',
        'background_image_path',
        'partner_logo_path',
        'instructions_text',
        'expires_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'status' => WallStatus::class,
        'layout' => WallLayout::class,
        'transition_effect' => WallTransition::class,
        'interval_ms' => 'integer',
        'queue_limit' => 'integer',
        'selection_mode' => WallSelectionMode::class,
        'event_phase' => WallEventPhase::class,
        'selection_policy' => 'array',
        'show_qr' => 'boolean',
        'show_branding' => 'boolean',
        'show_neon' => 'boolean',
        'show_sender_credit' => 'boolean',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $setting): void {
            if (empty($setting->wall_code)) {
                $setting->wall_code = static::generateUniqueCode();
            }

            if (empty($setting->status)) {
                $setting->status = WallStatus::Draft;
            }

            if (empty($setting->layout)) {
                $setting->layout = WallLayout::Auto;
            }

            if (empty($setting->transition_effect)) {
                $setting->transition_effect = WallTransition::Fade;
            }

            if (empty($setting->selection_mode)) {
                $setting->selection_mode = WallSelectionMode::Balanced;
            }

            if (empty($setting->event_phase)) {
                $setting->event_phase = WallEventPhase::Flow;
            }

            if (empty($setting->selection_policy)) {
                $setting->selection_policy = WallSelectionPreset::defaultsFor($setting->selection_mode);
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function playerRuntimeStatuses(): HasMany
    {
        return $this->hasMany(WallPlayerRuntimeStatus::class, 'event_wall_setting_id');
    }

    public function diagnosticSummary(): HasOne
    {
        return $this->hasOne(WallDiagnosticSummary::class, 'event_wall_setting_id');
    }

    public function isPlayable(): bool
    {
        return $this->is_enabled
            && $this->status === WallStatus::Live
            && $this->eventAllowsWall() === true;
    }

    public function isAvailable(): bool
    {
        return $this->is_enabled
            && $this->eventAllowsWall()
            && ! in_array($this->status, [WallStatus::Expired], true);
    }

    public function isLive(): bool
    {
        return $this->status === WallStatus::Live
            && $this->is_enabled
            && $this->eventAllowsWall();
    }

    public function isExpired(): bool
    {
        if ($this->status === WallStatus::Expired) {
            return true;
        }

        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function publicStatus(): string
    {
        if (! $this->eventAllowsWall() || ! $this->is_enabled) {
            return 'disabled';
        }

        if ($this->isExpired()) {
            return WallStatus::Expired->value;
        }

        return $this->status->value;
    }

    public function publicUrl(): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return "{$frontendUrl}/wall/player/{$this->wall_code}";
    }

    public static function generateUniqueCode(): string
    {
        do {
            $candidate = Str::upper(Str::random(8));
        } while (static::query()->where('wall_code', $candidate)->exists());

        return $candidate;
    }

    private function eventAllowsWall(): bool
    {
        $event = $this->relationLoaded('event')
            ? $this->getRelation('event')
            : $this->event()->first();

        return $event?->isActive() === true
            && $event->isModuleEnabled('wall');
    }
}
