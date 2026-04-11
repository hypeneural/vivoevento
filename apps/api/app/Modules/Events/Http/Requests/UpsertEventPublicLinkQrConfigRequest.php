<?php

namespace App\Modules\Events\Http\Requests;

use App\Modules\Events\Support\EventPublicLinkQrConfigSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertEventPublicLinkQrConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'config' => ['required', 'array'],
            'config.config_version' => ['nullable', 'string', 'max:80'],
            'config.version' => ['nullable', 'string', 'max:80'],
            'config.usage_preset' => ['nullable', Rule::in(EventPublicLinkQrConfigSchema::USAGE_PRESETS)],
            'config.skin_preset' => ['nullable', Rule::in(EventPublicLinkQrConfigSchema::SKIN_PRESETS)],

            'config.render' => ['nullable', 'array'],
            'config.render.preview_type' => ['nullable', Rule::in(EventPublicLinkQrConfigSchema::DRAW_TYPES)],
            'config.render.preview_size' => ['nullable', 'integer', 'min:64', 'max:4096'],
            'config.render.margin_modules' => ['nullable', 'integer', 'min:0', 'max:128'],
            'config.render.margin' => ['nullable', 'integer', 'min:0', 'max:128'],
            'config.render.background_mode' => ['nullable', Rule::in(EventPublicLinkQrConfigSchema::BACKGROUND_MODES)],

            'config.style' => ['nullable', 'array'],
            'config.style.dots' => ['nullable', 'array'],
            'config.style.dots.type' => ['nullable', 'string', 'max:40'],
            'config.style.dots.color' => ['nullable', 'string', 'max:32'],
            'config.style.dots.gradient' => ['nullable', 'array'],
            'config.style.dots.gradient.type' => ['nullable', 'string', 'max:40'],
            'config.style.dots.gradient.rotation' => ['nullable', 'numeric'],
            'config.style.dots.gradient.colorStops' => ['nullable', 'array'],
            'config.style.dots.gradient.colorStops.*.offset' => ['required_with:config.style.dots.gradient.colorStops', 'numeric', 'between:0,1'],
            'config.style.dots.gradient.colorStops.*.color' => ['required_with:config.style.dots.gradient.colorStops', 'string', 'max:32'],

            'config.style.corners_square' => ['nullable', 'array'],
            'config.style.corners_square.type' => ['nullable', 'string', 'max:40'],
            'config.style.corners_square.color' => ['nullable', 'string', 'max:32'],
            'config.style.corners_square.gradient' => ['nullable', 'array'],

            'config.style.corners_dot' => ['nullable', 'array'],
            'config.style.corners_dot.type' => ['nullable', 'string', 'max:40'],
            'config.style.corners_dot.color' => ['nullable', 'string', 'max:32'],
            'config.style.corners_dot.gradient' => ['nullable', 'array'],

            'config.style.background' => ['nullable', 'array'],
            'config.style.background.color' => ['nullable', 'string', 'max:32'],
            'config.style.background.gradient' => ['nullable', 'array'],
            'config.style.background.transparent' => ['nullable', 'boolean'],

            'config.logo' => ['nullable', 'array'],
            'config.logo.mode' => ['nullable', Rule::in(EventPublicLinkQrConfigSchema::LOGO_MODES)],
            'config.logo.asset_path' => ['nullable', 'string', 'max:255'],
            'config.logo.asset_url' => ['nullable', 'string', 'max:2048'],
            'config.logo.image_size' => ['nullable', 'numeric', 'between:0,1'],
            'config.logo.margin_px' => ['nullable', 'integer', 'min:0', 'max:128'],
            'config.logo.hide_background_dots' => ['nullable', 'boolean'],
            'config.logo.save_as_blob' => ['nullable', 'boolean'],

            'config.advanced' => ['nullable', 'array'],
            'config.advanced.error_correction_level' => ['nullable', Rule::in(EventPublicLinkQrConfigSchema::ERROR_CORRECTION_LEVELS)],
            'config.advanced.error_correction' => ['nullable', Rule::in(EventPublicLinkQrConfigSchema::ERROR_CORRECTION_LEVELS)],
            'config.advanced.shape' => ['nullable', Rule::in(EventPublicLinkQrConfigSchema::SHAPES)],
            'config.advanced.round_size' => ['nullable', 'boolean'],
            'config.advanced.type_number' => ['nullable', 'integer', 'min:0', 'max:40'],
            'config.advanced.mode' => ['nullable', Rule::in(EventPublicLinkQrConfigSchema::MODES)],

            'config.export_defaults' => ['nullable', 'array'],
            'config.export_defaults.extension' => ['nullable', Rule::in(EventPublicLinkQrConfigSchema::EXPORT_EXTENSIONS)],
            'config.export_defaults.size' => ['nullable', 'integer', 'min:64', 'max:4096'],
            'config.export_defaults.download_name_pattern' => ['nullable', 'string', 'max:255'],
        ];
    }
}
