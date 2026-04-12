<?php

namespace App\Modules\Gallery\Http\Requests;

use App\Modules\Gallery\Support\GalleryBuilderSchemaRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventGallerySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gallery.builder.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['sometimes', 'boolean'],
            'event_type_family' => ['sometimes', Rule::in(GalleryBuilderSchemaRegistry::EVENT_TYPE_FAMILIES)],
            'style_skin' => ['sometimes', Rule::in(GalleryBuilderSchemaRegistry::STYLE_SKINS)],
            'behavior_profile' => ['sometimes', Rule::in(GalleryBuilderSchemaRegistry::BEHAVIOR_PROFILES)],
            'theme_key' => ['sometimes', Rule::in(GalleryBuilderSchemaRegistry::THEME_KEYS)],
            'layout_key' => ['sometimes', Rule::in(GalleryBuilderSchemaRegistry::LAYOUT_KEYS)],
            'theme_tokens' => ['sometimes', 'array'],
            'theme_tokens.palette' => ['sometimes', 'array'],
            'theme_tokens.palette.page_background' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_tokens.palette.surface_background' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_tokens.palette.surface_border' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_tokens.palette.text_primary' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_tokens.palette.text_secondary' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_tokens.palette.accent' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_tokens.palette.button_fill' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_tokens.palette.button_text' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_tokens.motion' => ['sometimes', 'array'],
            'theme_tokens.motion.respect_user_preference' => ['sometimes', 'boolean'],
            'page_schema' => ['sometimes', 'array'],
            'page_schema.block_order' => ['sometimes', 'array', 'max:7'],
            'page_schema.block_order.*' => ['required', Rule::in(GalleryBuilderSchemaRegistry::BLOCK_KEYS)],
            'page_schema.blocks' => ['sometimes', 'array'],
            'page_schema.blocks.hero' => ['sometimes', 'array'],
            'page_schema.blocks.hero.enabled' => ['sometimes', 'boolean'],
            'page_schema.blocks.hero.variant' => ['sometimes', Rule::in(GalleryBuilderSchemaRegistry::EVENT_TYPE_FAMILIES)],
            'page_schema.blocks.hero.show_logo' => ['sometimes', 'boolean'],
            'page_schema.blocks.hero.show_face_search_cta' => ['sometimes', 'boolean'],
            'page_schema.blocks.banner_strip' => ['sometimes', 'array'],
            'page_schema.blocks.banner_strip.enabled' => ['sometimes', 'boolean'],
            'page_schema.blocks.banner_strip.positions' => ['sometimes', 'array', 'max:2'],
            'page_schema.blocks.banner_strip.positions.*' => ['required', 'regex:/^after_\\d+$/'],
            'page_schema.blocks.quote' => ['sometimes', 'array'],
            'page_schema.blocks.quote.enabled' => ['sometimes', 'boolean'],
            'page_schema.blocks.footer_brand' => ['sometimes', 'array'],
            'page_schema.blocks.footer_brand.enabled' => ['sometimes', 'boolean'],
            'media_behavior' => ['sometimes', 'array'],
            'media_behavior.grid' => ['sometimes', 'array'],
            'media_behavior.grid.layout' => ['sometimes', Rule::in(['masonry', 'rows', 'columns', 'justified'])],
            'media_behavior.grid.density' => ['sometimes', Rule::in(GalleryBuilderSchemaRegistry::DENSITIES)],
            'media_behavior.grid.breakpoints' => ['sometimes', 'array'],
            'media_behavior.grid.breakpoints.*' => ['required', 'integer'],
            'media_behavior.pagination' => ['sometimes', 'array'],
            'media_behavior.pagination.mode' => ['sometimes', Rule::in(['page', 'infinite-scroll'])],
            'media_behavior.pagination.page_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'media_behavior.pagination.chunk_strategy' => ['sometimes', Rule::in(['page', 'sectioned'])],
            'media_behavior.loading' => ['sometimes', 'array'],
            'media_behavior.loading.hero_and_first_band' => ['sometimes', Rule::in(['eager'])],
            'media_behavior.loading.below_fold' => ['sometimes', Rule::in(['lazy'])],
            'media_behavior.loading.content_visibility' => ['sometimes', Rule::in(['auto', 'visible'])],
            'media_behavior.lightbox' => ['sometimes', 'array'],
            'media_behavior.lightbox.photos' => ['sometimes', 'boolean'],
            'media_behavior.lightbox.videos' => ['sometimes', 'boolean'],
            'media_behavior.video' => ['sometimes', 'array'],
            'media_behavior.video.mode' => ['sometimes', Rule::in(GalleryBuilderSchemaRegistry::VIDEO_MODES)],
            'media_behavior.video.show_badge' => ['sometimes', 'boolean'],
            'media_behavior.video.allow_inline_preview' => ['sometimes', 'boolean'],
            'media_behavior.interstitials' => ['sometimes', 'array'],
            'media_behavior.interstitials.enabled' => ['sometimes', 'boolean'],
            'media_behavior.interstitials.policy' => ['sometimes', Rule::in(GalleryBuilderSchemaRegistry::INTERSTITIAL_POLICIES)],
            'media_behavior.interstitials.max_per_24_items' => ['sometimes', 'integer', 'min:0', 'max:2'],
        ];
    }
}
