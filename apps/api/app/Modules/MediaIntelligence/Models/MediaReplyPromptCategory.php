<?php

namespace App\Modules\MediaIntelligence\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaReplyPromptCategory extends Model
{
    use HasFactory;

    protected $table = 'ai_media_reply_prompt_categories';

    protected $fillable = [
        'slug',
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function presets(): HasMany
    {
        return $this->hasMany(MediaReplyPromptPreset::class, 'category', 'slug');
    }

    protected static function newFactory(): \Database\Factories\MediaReplyPromptCategoryFactory
    {
        return \Database\Factories\MediaReplyPromptCategoryFactory::new();
    }
}
