<?php

namespace App\Modules\Events\Http\Requests;

use App\Modules\MediaIntelligence\Services\OpenRouterModelPolicy;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateEventJourneyRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $contentModeration = $this->input('content_moderation');

        if (
            is_array($contentModeration)
            && array_key_exists('objective_safety_scope', $contentModeration)
            && ! array_key_exists('analysis_scope', $contentModeration)
        ) {
            $contentModeration['analysis_scope'] = $contentModeration['objective_safety_scope'];

            $this->merge([
                'content_moderation' => $contentModeration,
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('events.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'moderation_mode' => ['sometimes', 'string', 'in:none,manual,ai'],

            'modules' => ['sometimes', 'array'],
            'modules.live' => ['sometimes', 'boolean'],
            'modules.wall' => ['sometimes', 'boolean'],
            'modules.play' => ['sometimes', 'boolean'],
            'modules.hub' => ['sometimes', 'boolean'],

            'intake_defaults' => ['sometimes', 'array'],
            'intake_defaults.whatsapp_instance_id' => ['nullable', 'integer', 'exists:whatsapp_instances,id'],
            'intake_defaults.whatsapp_instance_mode' => ['nullable', 'string', 'in:shared,dedicated'],

            'intake_channels' => ['sometimes', 'array'],
            'intake_channels.whatsapp_groups' => ['sometimes', 'array'],
            'intake_channels.whatsapp_groups.enabled' => ['sometimes', 'boolean'],
            'intake_channels.whatsapp_groups.groups' => ['nullable', 'array'],
            'intake_channels.whatsapp_groups.groups.*.group_external_id' => ['required_with:intake_channels.whatsapp_groups.groups', 'string', 'max:180'],
            'intake_channels.whatsapp_groups.groups.*.group_name' => ['nullable', 'string', 'max:180'],
            'intake_channels.whatsapp_groups.groups.*.is_active' => ['nullable', 'boolean'],
            'intake_channels.whatsapp_groups.groups.*.auto_feedback_enabled' => ['nullable', 'boolean'],
            'intake_channels.whatsapp_direct' => ['sometimes', 'array'],
            'intake_channels.whatsapp_direct.enabled' => ['sometimes', 'boolean'],
            'intake_channels.whatsapp_direct.media_inbox_code' => ['nullable', 'string', 'max:80'],
            'intake_channels.whatsapp_direct.session_ttl_minutes' => ['nullable', 'integer', 'min:1', 'max:4320'],
            'intake_channels.public_upload' => ['sometimes', 'array'],
            'intake_channels.public_upload.enabled' => ['sometimes', 'boolean'],
            'intake_channels.telegram' => ['sometimes', 'array'],
            'intake_channels.telegram.enabled' => ['sometimes', 'boolean'],
            'intake_channels.telegram.bot_username' => ['nullable', 'string', 'max:80'],
            'intake_channels.telegram.media_inbox_code' => ['nullable', 'string', 'max:80'],
            'intake_channels.telegram.session_ttl_minutes' => ['nullable', 'integer', 'min:1', 'max:4320'],

            'content_moderation' => ['sometimes', 'array'],
            'content_moderation.inherit_global' => ['sometimes', 'boolean'],
            'content_moderation.enabled' => ['sometimes', 'boolean'],
            'content_moderation.provider_key' => ['sometimes', 'string', 'in:openai,noop'],
            'content_moderation.mode' => ['sometimes', 'string', 'in:enforced,observe_only'],
            'content_moderation.threshold_version' => ['sometimes', 'string', 'max:100'],
            'content_moderation.fallback_mode' => ['sometimes', 'string', 'in:review,block'],
            'content_moderation.analysis_scope' => ['sometimes', 'string', 'in:image_only,image_and_text_context'],
            'content_moderation.objective_safety_scope' => ['sometimes', 'string', 'in:image_only,image_and_text_context'],
            'content_moderation.normalized_text_context_mode' => ['sometimes', 'string', 'in:none,body_only,caption_only,body_plus_caption,operator_summary'],
            'content_moderation.hard_block_thresholds' => ['sometimes', 'array'],
            'content_moderation.review_thresholds' => ['sometimes', 'array'],

            'media_intelligence' => ['sometimes', 'array'],
            'media_intelligence.inherit_global' => ['sometimes', 'boolean'],
            'media_intelligence.enabled' => ['sometimes', 'boolean'],
            'media_intelligence.provider_key' => ['sometimes', 'string', 'in:vllm,openrouter,noop'],
            'media_intelligence.model_key' => ['sometimes', 'string', 'max:160'],
            'media_intelligence.mode' => ['sometimes', 'string', 'in:enrich_only,gate'],
            'media_intelligence.prompt_version' => ['nullable', 'string', 'max:100'],
            'media_intelligence.approval_prompt' => ['nullable', 'string', 'max:5000'],
            'media_intelligence.freeform_instruction' => ['nullable', 'string', 'max:5000'],
            'media_intelligence.caption_style_prompt' => ['sometimes', 'string', 'max:5000'],
            'media_intelligence.response_schema_version' => ['sometimes', 'string', 'max:100'],
            'media_intelligence.timeout_ms' => ['sometimes', 'integer', 'min:1000', 'max:30000'],
            'media_intelligence.fallback_mode' => ['sometimes', 'string', 'in:review,skip'],
            'media_intelligence.context_scope' => ['sometimes', 'string', 'in:image_only,image_and_text_context'],
            'media_intelligence.reply_scope' => ['sometimes', 'string', 'in:image_only,image_and_text_context'],
            'media_intelligence.normalized_text_context_mode' => ['sometimes', 'string', 'in:none,body_only,caption_only,body_plus_caption,operator_summary'],
            'media_intelligence.contextual_policy_preset_key' => ['sometimes', 'string', 'max:80'],
            'media_intelligence.policy_version' => ['sometimes', 'string', 'max:100'],
            'media_intelligence.allow_alcohol' => ['sometimes', 'boolean'],
            'media_intelligence.allow_tobacco' => ['sometimes', 'boolean'],
            'media_intelligence.required_people_context' => ['sometimes', 'string', 'in:optional,required'],
            'media_intelligence.blocked_terms' => ['nullable', 'array', 'max:30'],
            'media_intelligence.blocked_terms.*' => ['string', 'max:120'],
            'media_intelligence.allowed_exceptions' => ['nullable', 'array', 'max:30'],
            'media_intelligence.allowed_exceptions.*' => ['string', 'max:120'],
            'media_intelligence.require_json_output' => ['sometimes', 'boolean'],
            'media_intelligence.reply_text_enabled' => ['sometimes', 'boolean'],
            'media_intelligence.reply_text_mode' => ['nullable', 'string', 'in:disabled,ai,fixed_random'],
            'media_intelligence.reply_prompt_override' => ['nullable', 'string', 'max:5000'],
            'media_intelligence.reply_fixed_templates' => ['nullable', 'array', 'max:20'],
            'media_intelligence.reply_fixed_templates.*' => ['string', 'max:500'],
            'media_intelligence.reply_prompt_preset_id' => ['nullable', 'integer', 'exists:ai_media_reply_prompt_presets,id'],
        ];
    }

    /**
     * @return array<int, \Closure(\Illuminate\Validation\Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $event = $this->route('event');
                $entitlements = (array) ($event?->current_entitlements_json ?? []);

                if ($this->boolean('intake_channels.telegram.enabled')) {
                    if (blank($this->input('intake_channels.telegram.bot_username'))) {
                        $validator->errors()->add(
                            'intake_channels.telegram.bot_username',
                            'Informe o username do bot do Telegram.',
                        );
                    }

                    if (blank($this->input('intake_channels.telegram.media_inbox_code'))) {
                        $validator->errors()->add(
                            'intake_channels.telegram.media_inbox_code',
                            'Informe o codigo de inbox do Telegram.',
                        );
                    }
                }

                if ($this->boolean('intake_channels.whatsapp_direct.enabled')
                    && blank($this->input('intake_channels.whatsapp_direct.media_inbox_code'))
                ) {
                    $validator->errors()->add(
                        'intake_channels.whatsapp_direct.media_inbox_code',
                        'Informe o codigo de inbox do WhatsApp direto.',
                    );
                }

                if ($this->boolean('modules.wall') && ! (bool) data_get($entitlements, 'modules.wall', false)) {
                    $validator->errors()->add(
                        'modules.wall',
                        'O evento nao tem entitlement para habilitar o telao.',
                    );
                }

                $whatsAppInstanceId = $this->input('intake_defaults.whatsapp_instance_id');

                if ($event !== null && $whatsAppInstanceId !== null) {
                    $instance = WhatsAppInstance::query()->find($whatsAppInstanceId);

                    if ($instance !== null && $instance->organization_id !== $event->organization_id) {
                        $validator->errors()->add(
                            'intake_defaults.whatsapp_instance_id',
                            'A instancia WhatsApp precisa pertencer a mesma organizacao do evento.',
                        );
                    }
                }

                if (! is_array($this->input('media_intelligence'))) {
                    return;
                }

                $mode = (string) $this->input('media_intelligence.mode', 'enrich_only');
                $fallback = (string) $this->input('media_intelligence.fallback_mode', 'review');
                $replyTextMode = (string) $this->input('media_intelligence.reply_text_mode', 'disabled');
                $providerKey = (string) $this->input('media_intelligence.provider_key', 'vllm');
                $modelKey = (string) $this->input('media_intelligence.model_key', '');
                $requireJsonOutput = (bool) $this->boolean('media_intelligence.require_json_output', true);

                if ($mode === 'gate' && $fallback !== 'review') {
                    $validator->errors()->add(
                        'media_intelligence.fallback_mode',
                        'Eventos com VLM em gate devem usar fallback review para nunca aprovar por erro tecnico.',
                    );
                }

                if ($replyTextMode === 'fixed_random') {
                    $templates = collect((array) $this->input('media_intelligence.reply_fixed_templates', []))
                        ->map(fn (mixed $template) => is_string($template) ? trim($template) : '')
                        ->filter()
                        ->values();

                    if ($templates->isEmpty()) {
                        $validator->errors()->add(
                            'media_intelligence.reply_fixed_templates',
                            'Informe ao menos um template quando o modo de resposta for fixed_random.',
                        );
                    }
                }

                if ($providerKey === 'openrouter') {
                    $error = app(OpenRouterModelPolicy::class)->validationError($modelKey, $requireJsonOutput);

                    if ($error !== null) {
                        $validator->errors()->add('media_intelligence.model_key', $error);
                    }
                }
            },
        ];
    }
}
