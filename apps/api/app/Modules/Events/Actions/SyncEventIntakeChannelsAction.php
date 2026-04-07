<?php

namespace App\Modules\Events\Actions;

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Support\EventIntakeChannelsStateBuilder;
use App\Modules\WhatsApp\Enums\GroupBindingType;
use App\Modules\WhatsApp\Models\WhatsAppGroupBinding;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class SyncEventIntakeChannelsAction
{
    public function __construct(
        private readonly EventIntakeChannelsStateBuilder $stateBuilder,
    ) {}

    public function execute(Event $event, ?array $incomingChannels = null, ?array $incomingDefaults = null): Event
    {
        if ($incomingChannels === null && $incomingDefaults === null) {
            return $event;
        }

        $current = $this->stateBuilder->build($event);

        $state = array_replace_recursive($current, [
            'intake_defaults' => $incomingDefaults ?? [],
            'intake_channels' => $incomingChannels ?? [],
        ]);

        $instance = $this->resolveAndValidate($event, $state);

        $event->forceFill([
            'default_whatsapp_instance_id' => data_get($state, 'intake_defaults.whatsapp_instance_id'),
            'whatsapp_instance_mode' => data_get($state, 'intake_defaults.whatsapp_instance_mode', 'shared'),
        ])->save();

        $this->syncWhatsAppGroups($event, $state, $instance);
        $this->syncWhatsAppDirect($event, $state, $instance);
        $this->syncPublicUpload($event, $state);
        $this->syncTelegram($event, $state);

        return $event->fresh(['channels', 'whatsappGroupBindings', 'defaultWhatsAppInstance']);
    }

    private function resolveAndValidate(Event $event, array $state): ?WhatsAppInstance
    {
        $errors = [];
        $entitlements = (array) ($event->current_entitlements_json['channels'] ?? []);
        $groupsEnabled = (bool) data_get($state, 'intake_channels.whatsapp_groups.enabled', false);
        $groups = collect((array) data_get($state, 'intake_channels.whatsapp_groups.groups', []))
            ->filter(fn ($group) => filled(data_get($group, 'group_external_id')))
            ->values();
        $directEnabled = (bool) data_get($state, 'intake_channels.whatsapp_direct.enabled', false);
        $uploadEnabled = (bool) data_get($state, 'intake_channels.public_upload.enabled', false);
        $telegramEnabled = (bool) data_get($state, 'intake_channels.telegram.enabled', false);
        $needsWhatsAppInstance = $groupsEnabled || $directEnabled;
        $instanceId = data_get($state, 'intake_defaults.whatsapp_instance_id');
        $instanceMode = data_get($state, 'intake_defaults.whatsapp_instance_mode', 'shared');
        $instance = null;

        if (! in_array($instanceMode, ['shared', 'dedicated'], true)) {
            $errors['intake_defaults.whatsapp_instance_mode'][] = 'Modo de instancia invalido.';
        }

        if ($instanceId !== null) {
            $instance = WhatsAppInstance::query()->find($instanceId);

            if (! $instance || $instance->organization_id !== $event->organization_id) {
                $errors['intake_defaults.whatsapp_instance_id'][] = 'A instancia WhatsApp precisa pertencer a mesma organizacao do evento.';
                $instance = null;
            }
        }

        if ($needsWhatsAppInstance && $instance === null) {
            $errors['intake_defaults.whatsapp_instance_id'][] = 'Selecione uma instancia WhatsApp para os canais do evento.';
        }

        if ($groupsEnabled && ! (bool) data_get($entitlements, 'whatsapp_groups.enabled', false)) {
            $errors['intake_channels.whatsapp_groups.enabled'][] = 'O evento nao tem entitlement para receber por grupos de WhatsApp.';
        }

        $maxGroups = data_get($entitlements, 'whatsapp_groups.max');
        if ($groupsEnabled && $maxGroups !== null && $groups->count() > (int) $maxGroups) {
            $errors['intake_channels.whatsapp_groups.groups'][] = 'A quantidade de grupos excede o limite permitido para o evento.';
        }

        if ($directEnabled && ! (bool) data_get($entitlements, 'whatsapp_direct.enabled', false)) {
            $errors['intake_channels.whatsapp_direct.enabled'][] = 'O evento nao tem entitlement para WhatsApp direto.';
        }

        if ($uploadEnabled && ! (bool) data_get($entitlements, 'public_upload.enabled', false)) {
            $errors['intake_channels.public_upload.enabled'][] = 'O evento nao tem entitlement para link de upload.';
        }

        if ($telegramEnabled && ! (bool) data_get($entitlements, 'telegram.enabled', false)) {
            $errors['intake_channels.telegram.enabled'][] = 'O evento nao tem entitlement para Telegram.';
        }

        if ($telegramEnabled && blank(data_get($state, 'intake_channels.telegram.bot_username'))) {
            $errors['intake_channels.telegram.bot_username'][] = 'Informe o username do bot do Telegram.';
        }

        if ($telegramEnabled && blank(data_get($state, 'intake_channels.telegram.media_inbox_code'))) {
            $errors['intake_channels.telegram.media_inbox_code'][] = 'Informe o codigo de inbox do Telegram.';
        }

        if ($needsWhatsAppInstance && $instanceMode === 'shared' && ! (bool) data_get($entitlements, 'whatsapp.shared_instance.enabled', false)) {
            $errors['intake_defaults.whatsapp_instance_mode'][] = 'O evento nao pode usar instancia compartilhada.';
        }

        if ($needsWhatsAppInstance && $instanceMode === 'dedicated') {
            if (! (bool) data_get($entitlements, 'whatsapp.dedicated_instance.enabled', false)) {
                $errors['intake_defaults.whatsapp_instance_mode'][] = 'O evento nao pode usar instancia dedicada.';
            }

            $maxPerEvent = data_get($entitlements, 'whatsapp.dedicated_instance.max_per_event');

            if ($maxPerEvent !== null && (int) $maxPerEvent < 1) {
                $errors['intake_defaults.whatsapp_instance_mode'][] = 'O pacote nao permite instancia dedicada para este evento.';
            }

            if ($instance !== null) {
                $conflict = Event::query()
                    ->where('id', '!=', $event->id)
                    ->where('default_whatsapp_instance_id', $instance->id)
                    ->where('whatsapp_instance_mode', 'dedicated')
                    ->exists();

                if ($conflict) {
                    $errors['intake_defaults.whatsapp_instance_id'][] = 'A instancia dedicada ja esta vinculada a outro evento.';
                }
            }
        }

        if ($instance !== null && $groupsEnabled) {
            $conflictingGroups = WhatsAppGroupBinding::query()
                ->where('instance_id', $instance->id)
                ->where('binding_type', GroupBindingType::EventGallery->value)
                ->where('is_active', true)
                ->where('event_id', '!=', $event->id)
                ->whereIn('group_external_id', $groups->pluck('group_external_id')->all())
                ->pluck('group_external_id')
                ->all();

            if ($conflictingGroups !== []) {
                $errors['intake_channels.whatsapp_groups.groups'][] = 'Um ou mais grupos ja estao vinculados a outro evento nesta instancia.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $instance;
    }

    private function syncWhatsAppGroups(Event $event, array $state, ?WhatsAppInstance $instance): void
    {
        $enabled = (bool) data_get($state, 'intake_channels.whatsapp_groups.enabled', false);
        $groups = collect((array) data_get($state, 'intake_channels.whatsapp_groups.groups', []))
            ->map(fn ($group) => [
                'group_external_id' => trim((string) data_get($group, 'group_external_id')),
                'group_name' => data_get($group, 'group_name'),
                'is_active' => (bool) data_get($group, 'is_active', true),
                'auto_feedback_enabled' => (bool) data_get($group, 'auto_feedback_enabled', false),
            ])
            ->filter(fn (array $group): bool => $group['group_external_id'] !== '')
            ->values();

        if (! $enabled || $instance === null) {
            EventChannel::query()
                ->where('event_id', $event->id)
                ->where('channel_type', ChannelType::WhatsAppGroup->value)
                ->delete();

            WhatsAppGroupBinding::query()
                ->where('event_id', $event->id)
                ->where('binding_type', GroupBindingType::EventGallery->value)
                ->update(['is_active' => false]);

            return;
        }

        EventChannel::query()->updateOrCreate(
            [
                'event_id' => $event->id,
                'channel_type' => ChannelType::WhatsAppGroup->value,
            ],
            [
                'provider' => $instance->providerKeyValue(),
                'external_id' => null,
                'label' => 'WhatsApp grupos',
                'status' => 'active',
                'config_json' => [
                    'groups' => $groups->all(),
                ],
            ],
        );

        $activeGroupIds = $groups->pluck('group_external_id')->all();

        foreach ($groups as $group) {
            WhatsAppGroupBinding::query()->updateOrCreate(
                [
                    'instance_id' => $instance->id,
                    'group_external_id' => $group['group_external_id'],
                    'binding_type' => GroupBindingType::EventGallery->value,
                ],
                [
                    'organization_id' => $event->organization_id,
                    'event_id' => $event->id,
                    'group_name' => $group['group_name'],
                    'is_active' => $group['is_active'],
                    'metadata_json' => [
                        'auto_feedback_enabled' => $group['auto_feedback_enabled'],
                    ],
                ],
            );
        }

        WhatsAppGroupBinding::query()
            ->where('event_id', $event->id)
            ->where('binding_type', GroupBindingType::EventGallery->value)
            ->where(function ($query) use ($activeGroupIds, $instance) {
                $query->where('instance_id', '!=', $instance->id);

                if ($activeGroupIds !== []) {
                    $query->orWhereNotIn('group_external_id', $activeGroupIds);
                } else {
                    $query->orWhereNotNull('group_external_id');
                }
            })
            ->update(['is_active' => false]);
    }

    private function syncWhatsAppDirect(Event $event, array $state, ?WhatsAppInstance $instance): void
    {
        $enabled = (bool) data_get($state, 'intake_channels.whatsapp_direct.enabled', false);
        $mediaInboxCode = $this->normalizeInboundCode(
            data_get($state, 'intake_channels.whatsapp_direct.media_inbox_code')
        );

        if (! $enabled || $instance === null) {
            EventChannel::query()
                ->where('event_id', $event->id)
                ->where('channel_type', ChannelType::WhatsAppDirect->value)
                ->delete();

            return;
        }

        EventChannel::query()->updateOrCreate(
            [
                'event_id' => $event->id,
                'channel_type' => ChannelType::WhatsAppDirect->value,
            ],
            [
                'provider' => $instance->providerKeyValue(),
                'external_id' => $mediaInboxCode,
                'label' => 'WhatsApp direto',
                'status' => 'active',
                'config_json' => array_filter([
                    'media_inbox_code' => $mediaInboxCode,
                    'session_ttl_minutes' => data_get($state, 'intake_channels.whatsapp_direct.session_ttl_minutes'),
                ], fn (mixed $value): bool => $value !== null),
            ],
        );
    }

    private function syncPublicUpload(Event $event, array $state): void
    {
        $enabled = (bool) data_get($state, 'intake_channels.public_upload.enabled', false);

        if (! $enabled) {
            EventChannel::query()
                ->where('event_id', $event->id)
                ->where('channel_type', ChannelType::PublicUploadLink->value)
                ->delete();

            return;
        }

        EventChannel::query()->updateOrCreate(
            [
                'event_id' => $event->id,
                'channel_type' => ChannelType::PublicUploadLink->value,
            ],
            [
                'provider' => 'eventovivo',
                'external_id' => $event->upload_slug,
                'label' => 'Link de upload',
                'status' => 'active',
                'config_json' => [],
            ],
        );
    }

    private function syncTelegram(Event $event, array $state): void
    {
        $enabled = (bool) data_get($state, 'intake_channels.telegram.enabled', false);
        $mediaInboxCode = $this->normalizeInboundCode(
            data_get($state, 'intake_channels.telegram.media_inbox_code')
        );

        if (! $enabled) {
            EventChannel::query()
                ->where('event_id', $event->id)
                ->where('channel_type', ChannelType::TelegramBot->value)
                ->delete();

            return;
        }

        EventChannel::query()->updateOrCreate(
            [
                'event_id' => $event->id,
                'channel_type' => ChannelType::TelegramBot->value,
            ],
            [
                'provider' => 'telegram',
                'external_id' => $mediaInboxCode,
                'label' => 'Telegram',
                'status' => 'active',
                'config_json' => array_filter([
                    'bot_username' => data_get($state, 'intake_channels.telegram.bot_username'),
                    'media_inbox_code' => $mediaInboxCode,
                    'session_ttl_minutes' => data_get($state, 'intake_channels.telegram.session_ttl_minutes', 180),
                    'allow_private' => true,
                    'v1_allowed_updates' => ['message', 'my_chat_member'],
                ], fn (mixed $value): bool => $value !== null),
            ],
        );
    }

    private function normalizeInboundCode(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtoupper(trim($value));

        return $normalized !== '' ? $normalized : null;
    }
}
