<?php

namespace App\Modules\Hub\Http\Requests;

use App\Modules\Hub\Support\HubBuilderPresetRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHubPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hub.manage') ?? false;
    }

    public function rules(): array
    {
        $builder = app(HubBuilderPresetRegistry::class);
        $iconOptions = ['camera', 'image', 'monitor', 'gamepad', 'link', 'calendar', 'map-pin', 'ticket', 'music', 'gift', 'sparkles', 'message-circle', 'instagram'];

        return [
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:180'],
            'button_style' => ['required', 'array'],
            'button_style.background_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'button_style.text_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'button_style.outline_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'builder_config' => ['required', 'array'],
            'builder_config.version' => ['required', 'integer', Rule::in([1])],
            'builder_config.layout_key' => ['required', Rule::in($builder::LAYOUT_KEYS)],
            'builder_config.theme_key' => ['required', Rule::in($builder::THEME_KEYS)],
            'builder_config.theme_tokens' => ['required', 'array'],
            'builder_config.theme_tokens.page_background' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'builder_config.theme_tokens.page_accent' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'builder_config.theme_tokens.surface_background' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'builder_config.theme_tokens.surface_border' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'builder_config.theme_tokens.text_primary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'builder_config.theme_tokens.text_secondary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'builder_config.theme_tokens.hero_overlay_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'builder_config.block_order' => ['required', 'array', 'max:8'],
            'builder_config.block_order.*' => ['required', Rule::in($builder::BLOCK_KEYS)],
            'builder_config.blocks' => ['required', 'array'],
            'builder_config.blocks.hero' => ['required', 'array'],
            'builder_config.blocks.hero.enabled' => ['required', 'boolean'],
            'builder_config.blocks.hero.show_logo' => ['required', 'boolean'],
            'builder_config.blocks.hero.show_badge' => ['required', 'boolean'],
            'builder_config.blocks.hero.show_meta_cards' => ['required', 'boolean'],
            'builder_config.blocks.hero.height' => ['required', Rule::in(['sm', 'md', 'lg'])],
            'builder_config.blocks.hero.overlay_opacity' => ['required', 'integer', 'min:0', 'max:90'],
            'builder_config.blocks.meta_cards' => ['required', 'array'],
            'builder_config.blocks.meta_cards.enabled' => ['required', 'boolean'],
            'builder_config.blocks.meta_cards.show_date' => ['required', 'boolean'],
            'builder_config.blocks.meta_cards.show_location' => ['required', 'boolean'],
            'builder_config.blocks.meta_cards.style' => ['required', Rule::in(['glass', 'solid', 'minimal'])],
            'builder_config.blocks.welcome' => ['required', 'array'],
            'builder_config.blocks.welcome.enabled' => ['required', 'boolean'],
            'builder_config.blocks.welcome.style' => ['required', Rule::in(['card', 'inline', 'bubble'])],
            'builder_config.blocks.countdown' => ['required', 'array'],
            'builder_config.blocks.countdown.enabled' => ['required', 'boolean'],
            'builder_config.blocks.countdown.style' => ['required', Rule::in(['cards', 'inline', 'minimal'])],
            'builder_config.blocks.countdown.target_mode' => ['required', Rule::in(['event_start', 'custom'])],
            'builder_config.blocks.countdown.target_at' => ['nullable', 'date'],
            'builder_config.blocks.countdown.title' => ['required', 'string', 'max:120'],
            'builder_config.blocks.countdown.completed_message' => ['required', 'string', 'max:180'],
            'builder_config.blocks.countdown.hide_after_start' => ['required', 'boolean'],
            'builder_config.blocks.info_grid' => ['required', 'array'],
            'builder_config.blocks.info_grid.enabled' => ['required', 'boolean'],
            'builder_config.blocks.info_grid.title' => ['required', 'string', 'max:120'],
            'builder_config.blocks.info_grid.style' => ['required', Rule::in(['cards', 'minimal', 'highlight'])],
            'builder_config.blocks.info_grid.columns' => ['required', 'integer', Rule::in([2, 3])],
            'builder_config.blocks.info_grid.items' => ['nullable', 'array', 'max:8'],
            'builder_config.blocks.info_grid.items.*.id' => ['required_with:builder_config.blocks.info_grid.items', 'string', 'max:80'],
            'builder_config.blocks.info_grid.items.*.title' => ['required_with:builder_config.blocks.info_grid.items', 'string', 'max:80'],
            'builder_config.blocks.info_grid.items.*.value' => ['required_with:builder_config.blocks.info_grid.items', 'string', 'max:140'],
            'builder_config.blocks.info_grid.items.*.description' => ['nullable', 'string', 'max:180'],
            'builder_config.blocks.info_grid.items.*.icon' => ['required_with:builder_config.blocks.info_grid.items', Rule::in($iconOptions)],
            'builder_config.blocks.info_grid.items.*.is_visible' => ['required_with:builder_config.blocks.info_grid.items', 'boolean'],
            'builder_config.blocks.cta_list' => ['required', 'array'],
            'builder_config.blocks.cta_list.enabled' => ['required', 'boolean'],
            'builder_config.blocks.cta_list.style' => ['required', Rule::in(['solid', 'outline', 'soft'])],
            'builder_config.blocks.cta_list.size' => ['required', Rule::in(['sm', 'md', 'lg'])],
            'builder_config.blocks.cta_list.icon_position' => ['required', Rule::in(['left', 'top'])],
            'builder_config.blocks.social_strip' => ['required', 'array'],
            'builder_config.blocks.social_strip.enabled' => ['required', 'boolean'],
            'builder_config.blocks.social_strip.style' => ['required', Rule::in(['icons', 'chips', 'cards'])],
            'builder_config.blocks.social_strip.size' => ['required', Rule::in(['sm', 'md', 'lg'])],
            'builder_config.blocks.social_strip.items' => ['nullable', 'array', 'max:12'],
            'builder_config.blocks.social_strip.items.*.id' => ['required_with:builder_config.blocks.social_strip.items', 'string', 'max:80'],
            'builder_config.blocks.social_strip.items.*.provider' => ['required_with:builder_config.blocks.social_strip.items', Rule::in(['instagram', 'whatsapp', 'tiktok', 'youtube', 'spotify', 'website', 'map', 'tickets'])],
            'builder_config.blocks.social_strip.items.*.label' => ['required_with:builder_config.blocks.social_strip.items', 'string', 'max:120'],
            'builder_config.blocks.social_strip.items.*.href' => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/)/i'],
            'builder_config.blocks.social_strip.items.*.icon' => ['required_with:builder_config.blocks.social_strip.items', Rule::in($iconOptions)],
            'builder_config.blocks.social_strip.items.*.is_visible' => ['required_with:builder_config.blocks.social_strip.items', 'boolean'],
            'builder_config.blocks.social_strip.items.*.opens_in_new_tab' => ['required_with:builder_config.blocks.social_strip.items', 'boolean'],
            'builder_config.blocks.sponsor_strip' => ['required', 'array'],
            'builder_config.blocks.sponsor_strip.enabled' => ['required', 'boolean'],
            'builder_config.blocks.sponsor_strip.title' => ['required', 'string', 'max:120'],
            'builder_config.blocks.sponsor_strip.style' => ['required', Rule::in(['logos', 'cards', 'compact'])],
            'builder_config.blocks.sponsor_strip.items' => ['nullable', 'array', 'max:20'],
            'builder_config.blocks.sponsor_strip.items.*.id' => ['required_with:builder_config.blocks.sponsor_strip.items', 'string', 'max:80'],
            'builder_config.blocks.sponsor_strip.items.*.name' => ['required_with:builder_config.blocks.sponsor_strip.items', 'string', 'max:120'],
            'builder_config.blocks.sponsor_strip.items.*.subtitle' => ['nullable', 'string', 'max:160'],
            'builder_config.blocks.sponsor_strip.items.*.logo_path' => ['nullable', 'string', 'max:2048'],
            'builder_config.blocks.sponsor_strip.items.*.href' => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/)/i'],
            'builder_config.blocks.sponsor_strip.items.*.is_visible' => ['required_with:builder_config.blocks.sponsor_strip.items', 'boolean'],
            'builder_config.blocks.sponsor_strip.items.*.opens_in_new_tab' => ['required_with:builder_config.blocks.sponsor_strip.items', 'boolean'],
            'buttons' => ['nullable', 'array', 'max:12'],
            'buttons.*.id' => ['required_with:buttons', 'string', 'max:80'],
            'buttons.*.type' => ['required_with:buttons', Rule::in(['preset', 'custom'])],
            'buttons.*.preset_key' => ['nullable', Rule::in(['upload', 'gallery', 'wall', 'play'])],
            'buttons.*.label' => ['required_with:buttons', 'string', 'max:120'],
            'buttons.*.icon' => ['required_with:buttons', Rule::in($iconOptions)],
            'buttons.*.href' => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/)/i'],
            'buttons.*.is_visible' => ['required_with:buttons', 'boolean'],
            'buttons.*.opens_in_new_tab' => ['required_with:buttons', 'boolean'],
            'buttons.*.background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'buttons.*.text_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'buttons.*.outline_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
