<?php
namespace App\Modules\Hub\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventHubSetting extends Model
{
    protected $fillable = [
        'event_id',
        'is_enabled',
        'headline',
        'subheadline',
        'welcome_text',
        'hero_image_path',
        'show_gallery_button',
        'show_upload_button',
        'show_wall_button',
        'show_play_button',
        'button_style_json',
        'buttons_json',
        'builder_config_json',
        'sponsor_json',
        'extra_links_json',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'show_gallery_button' => 'boolean',
        'show_upload_button' => 'boolean',
        'show_wall_button' => 'boolean',
        'show_play_button' => 'boolean',
        'button_style_json' => 'array',
        'buttons_json' => 'array',
        'builder_config_json' => 'array',
        'sponsor_json' => 'array',
        'extra_links_json' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }
}
