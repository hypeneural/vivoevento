<?php
namespace App\Modules\Hub\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventHubSetting extends Model
{
    protected $fillable = [
        'event_id', 'is_enabled', 'headline', 'subheadline',
        'show_gallery_button', 'show_upload_button', 'show_wall_button',
        'show_play_button', 'sponsor_json', 'extra_links_json',
    ];

    protected $casts = [
        'is_enabled' => 'boolean', 'show_gallery_button' => 'boolean',
        'show_upload_button' => 'boolean', 'show_wall_button' => 'boolean',
        'show_play_button' => 'boolean', 'sponsor_json' => 'array',
        'extra_links_json' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }
}
