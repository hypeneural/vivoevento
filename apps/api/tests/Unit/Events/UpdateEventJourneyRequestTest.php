<?php

use App\Modules\Events\Http\Requests\UpdateEventJourneyRequest;
use App\Modules\Events\Models\Event;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\CreatesUsers;

uses(CreatesUsers::class);

function makeJourneyPatchEvent(array $attributes = []): Event
{
    return Event::factory()->create(array_merge([
        'current_entitlements_json' => [
            'channels' => [
                'whatsapp_direct' => ['enabled' => true],
                'whatsapp_groups' => ['enabled' => true, 'max' => 3],
                'public_upload' => ['enabled' => true],
                'telegram' => ['enabled' => true],
                'blacklist' => ['enabled' => true],
                'whatsapp' => [
                    'shared_instance' => ['enabled' => true],
                    'dedicated_instance' => ['enabled' => true, 'max_per_event' => 1],
                ],
            ],
            'modules' => [
                'wall' => true,
            ],
        ],
    ], $attributes));
}

/**
 * @return array{0: UpdateEventJourneyRequest, 1: \Illuminate\Validation\Validator}
 */
function validateJourneyPatchPayload(object $testCase, array $payload, Event $event, $user): array
{
    $request = UpdateEventJourneyRequest::create(
        "/api/v1/events/{$event->id}/journey-builder",
        'PATCH',
        $payload,
    );

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setUserResolver(fn () => $user);
    $request->setRouteResolver(function () use ($event) {
        return new class($event)
        {
            public function __construct(
                private readonly Event $event,
            ) {}

            public function parameter(string $key, mixed $default = null): mixed
            {
                return $key === 'event' ? $this->event : $default;
            }
        };
    });

    $prepare = new ReflectionMethod($request, 'prepareForValidation');
    $prepare->setAccessible(true);
    $prepare->invoke($request);

    $validator = Validator::make(
        $request->all(),
        app()->call([$request, 'rules']),
        $request->messages(),
        $request->attributes(),
    );

    foreach ($request->after() as $after) {
        $after($validator);
    }

    return [$request, $validator];
}

it('authorizes only users that can update events', function () {
    [$owner, $organization] = $this->actingAsOwner();
    [, $viewerOrganization] = $this->actingAsViewer();

    $event = makeJourneyPatchEvent([
        'organization_id' => $organization->id,
    ]);

    [$ownerRequest] = validateJourneyPatchPayload($this, [], $event, $owner);

    expect($ownerRequest->authorize())->toBeTrue();

    [$viewer] = $this->actingAsViewer($organization);
    [$viewerRequest] = validateJourneyPatchPayload($this, [], $event, $viewer);

    expect($viewerRequest->authorize())->toBeFalse();
});

it('accepts a valid aggregated journey patch payload', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = makeJourneyPatchEvent([
        'organization_id' => $organization->id,
    ]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
    ]);

    [, $validator] = validateJourneyPatchPayload($this, [
        'moderation_mode' => 'ai',
        'modules' => [
            'live' => true,
            'wall' => true,
        ],
        'intake_defaults' => [
            'whatsapp_instance_id' => $instance->id,
            'whatsapp_instance_mode' => 'shared',
        ],
        'intake_channels' => [
            'whatsapp_direct' => [
                'enabled' => true,
                'media_inbox_code' => 'NOIVAEJOAO',
                'session_ttl_minutes' => 180,
            ],
            'telegram' => [
                'enabled' => true,
                'bot_username' => 'EventoVivoBot',
                'media_inbox_code' => 'NOIVABOT',
                'session_ttl_minutes' => 180,
            ],
            'public_upload' => [
                'enabled' => true,
            ],
        ],
        'content_moderation' => [
            'enabled' => true,
            'provider_key' => 'openai',
            'mode' => 'enforced',
            'fallback_mode' => 'review',
            'analysis_scope' => 'image_and_text_context',
            'normalized_text_context_mode' => 'body_plus_caption',
        ],
        'media_intelligence' => [
            'enabled' => true,
            'provider_key' => 'vllm',
            'model_key' => 'Qwen/Qwen2.5-VL-3B-Instruct',
            'mode' => 'gate',
            'fallback_mode' => 'review',
            'context_scope' => 'image_and_text_context',
            'reply_scope' => 'image_and_text_context',
            'normalized_text_context_mode' => 'body_plus_caption',
            'reply_text_mode' => 'ai',
            'reply_text_enabled' => true,
            'require_json_output' => true,
        ],
    ], $event, $user);

    expect($validator->passes())->toBeTrue();
});

it('requires review fallback when media intelligence gate mode is selected', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = makeJourneyPatchEvent([
        'organization_id' => $organization->id,
    ]);

    [, $validator] = validateJourneyPatchPayload($this, [
        'media_intelligence' => [
            'enabled' => true,
            'provider_key' => 'vllm',
            'model_key' => 'Qwen/Qwen2.5-VL-3B-Instruct',
            'mode' => 'gate',
            'fallback_mode' => 'skip',
            'reply_text_mode' => 'disabled',
            'require_json_output' => true,
        ],
    ], $event, $user);

    expect($validator->errors()->has('media_intelligence.fallback_mode'))->toBeTrue();
});

it('requires telegram bot username and inbox code when telegram intake is enabled', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = makeJourneyPatchEvent([
        'organization_id' => $organization->id,
    ]);

    [, $validator] = validateJourneyPatchPayload($this, [
        'intake_channels' => [
            'telegram' => [
                'enabled' => true,
                'session_ttl_minutes' => 180,
            ],
        ],
    ], $event, $user);

    expect($validator->errors()->has('intake_channels.telegram.bot_username'))->toBeTrue()
        ->and($validator->errors()->has('intake_channels.telegram.media_inbox_code'))->toBeTrue();
});

it('requires whatsapp direct inbox code when direct intake is enabled', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = makeJourneyPatchEvent([
        'organization_id' => $organization->id,
    ]);

    [, $validator] = validateJourneyPatchPayload($this, [
        'intake_channels' => [
            'whatsapp_direct' => [
                'enabled' => true,
                'session_ttl_minutes' => 180,
            ],
        ],
    ], $event, $user);

    expect($validator->errors()->has('intake_channels.whatsapp_direct.media_inbox_code'))->toBeTrue();
});

it('validates channel ttl boundaries in the aggregated patch', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = makeJourneyPatchEvent([
        'organization_id' => $organization->id,
    ]);

    [, $validator] = validateJourneyPatchPayload($this, [
        'intake_channels' => [
            'whatsapp_direct' => [
                'enabled' => true,
                'media_inbox_code' => 'NOIVA',
                'session_ttl_minutes' => 0,
            ],
            'telegram' => [
                'enabled' => true,
                'bot_username' => 'EventoBot',
                'media_inbox_code' => 'NOIVA',
                'session_ttl_minutes' => 5000,
            ],
        ],
    ], $event, $user);

    expect($validator->errors()->has('intake_channels.whatsapp_direct.session_ttl_minutes'))->toBeTrue()
        ->and($validator->errors()->has('intake_channels.telegram.session_ttl_minutes'))->toBeTrue();
});

it('requires fixed reply templates when fixed random reply mode is selected', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = makeJourneyPatchEvent([
        'organization_id' => $organization->id,
    ]);

    [, $validator] = validateJourneyPatchPayload($this, [
        'media_intelligence' => [
            'enabled' => true,
            'provider_key' => 'vllm',
            'model_key' => 'Qwen/Qwen2.5-VL-3B-Instruct',
            'mode' => 'enrich_only',
            'fallback_mode' => 'review',
            'reply_text_mode' => 'fixed_random',
            'reply_fixed_templates' => [],
            'require_json_output' => true,
        ],
    ], $event, $user);

    expect($validator->errors()->has('media_intelligence.reply_fixed_templates'))->toBeTrue();
});

it('blocks wall module enablement when the event entitlement does not allow wall', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = makeJourneyPatchEvent([
        'organization_id' => $organization->id,
        'current_entitlements_json' => [
            'channels' => [
                'whatsapp_direct' => ['enabled' => true],
            ],
            'modules' => [
                'wall' => false,
            ],
        ],
    ]);

    [, $validator] = validateJourneyPatchPayload($this, [
        'modules' => [
            'wall' => true,
        ],
    ], $event, $user);

    expect($validator->errors()->has('modules.wall'))->toBeTrue();
});

it('requires whatsapp instances to belong to the same organization as the event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = makeJourneyPatchEvent([
        'organization_id' => $organization->id,
    ]);

    $foreignInstance = WhatsAppInstance::factory()->connected()->create();

    [, $validator] = validateJourneyPatchPayload($this, [
        'intake_defaults' => [
            'whatsapp_instance_id' => $foreignInstance->id,
            'whatsapp_instance_mode' => 'shared',
        ],
    ], $event, $user);

    expect($validator->errors()->has('intake_defaults.whatsapp_instance_id'))->toBeTrue();
});

it('rejects blocked openrouter router aliases in the aggregated patch', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = makeJourneyPatchEvent([
        'organization_id' => $organization->id,
    ]);

    [, $validator] = validateJourneyPatchPayload($this, [
        'media_intelligence' => [
            'enabled' => true,
            'provider_key' => 'openrouter',
            'model_key' => 'openrouter/auto',
            'mode' => 'enrich_only',
            'fallback_mode' => 'review',
            'reply_text_mode' => 'disabled',
            'require_json_output' => true,
        ],
    ], $event, $user);

    expect($validator->errors()->has('media_intelligence.model_key'))->toBeTrue();
});
