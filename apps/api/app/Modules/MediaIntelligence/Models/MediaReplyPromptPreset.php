<?php

namespace App\Modules\MediaIntelligence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MediaReplyPromptPreset extends Model
{
    use HasFactory;

    protected $table = 'ai_media_reply_prompt_presets';

    protected $fillable = [
        'slug',
        'name',
        'category',
        'description',
        'prompt_template',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categoryEntry(): HasOne
    {
        return $this->hasOne(MediaReplyPromptCategory::class, 'slug', 'category');
    }

    protected static function newFactory(): \Database\Factories\MediaReplyPromptPresetFactory
    {
        return \Database\Factories\MediaReplyPromptPresetFactory::new();
    }
}
