<?php

namespace App\Modules\Gallery\Http\Controllers;

use App\Modules\Analytics\Services\AnalyticsTracker;
use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Actions\AutosaveEventGalleryDraftAction;
use App\Modules\Gallery\Actions\PublishEventGalleryDraftAction;
use App\Modules\Gallery\Actions\RunGalleryBuilderPromptAction;
use App\Modules\Gallery\Actions\RestoreEventGalleryRevisionAction;
use App\Modules\Gallery\Actions\UploadEventGalleryAssetAction;
use App\Modules\Gallery\Actions\UpdateEventGallerySettingsAction;
use App\Modules\Gallery\Http\Requests\RunGalleryBuilderPromptRequest;
use App\Modules\Gallery\Http\Requests\ShowEventGallerySettingsRequest;
use App\Modules\Gallery\Http\Requests\StoreGalleryBuilderTelemetryRequest;
use App\Modules\Gallery\Http\Requests\UploadEventGalleryAssetRequest;
use App\Modules\Gallery\Http\Requests\UpdateEventGallerySettingsRequest;
use App\Modules\Gallery\Http\Resources\EventGalleryRevisionResource;
use App\Modules\Gallery\Http\Resources\EventGallerySettingsResource;
use App\Modules\Gallery\Models\EventGalleryRevision;
use App\Modules\Gallery\Models\EventGallerySetting;
use App\Modules\Gallery\Models\GalleryBuilderPromptRun;
use App\Modules\Gallery\Queries\ListEventGalleryRevisionsQuery;
use App\Modules\Gallery\Support\GalleryBuilderOperationalFeedbackResolver;
use App\Modules\Gallery\Support\GalleryBuilderPresetRegistry;
use App\Modules\Gallery\Support\GalleryBuilderSchemaRegistry;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GalleryBuilderController extends BaseController
{
    public function show(
        ShowEventGallerySettingsRequest $request,
        Event $event,
        GalleryBuilderPresetRegistry $registry,
        GalleryBuilderSchemaRegistry $schemaRegistry,
    ): JsonResponse {
        $this->authorize('view', $event);

        $settings = $this->ensureSettings($event, $request->user(), $registry);

        return $this->success([
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
            ],
            'settings' => (new EventGallerySettingsResource($settings))->resolve(),
            'mobile_budget' => $schemaRegistry->mobileBudget(),
            'responsive_source_contract' => $schemaRegistry->responsiveSourceContract(),
            'optimized_renderer_trigger' => $schemaRegistry->optimizedRendererTrigger(),
            'operational_feedback' => $this->feedback($settings, app(GalleryBuilderOperationalFeedbackResolver::class)),
        ]);
    }

    public function update(
        UpdateEventGallerySettingsRequest $request,
        Event $event,
        GalleryBuilderPresetRegistry $registry,
        UpdateEventGallerySettingsAction $action,
    ): JsonResponse {
        $this->authorize('update', $event);

        $settings = $this->ensureSettings($event, $request->user(), $registry);
        $settings = $action->execute($event, $settings, $request->validated(), $request->user());

        return $this->success([
            'settings' => (new EventGallerySettingsResource($settings))->resolve(),
        ]);
    }

    public function autosave(
        Request $request,
        Event $event,
        GalleryBuilderPresetRegistry $registry,
        AutosaveEventGalleryDraftAction $action,
    ): JsonResponse {
        abort_unless($request->user()?->can('gallery.builder.manage'), 403);
        $this->authorize('update', $event);

        $settings = $this->ensureSettings($event, $request->user(), $registry);
        $result = $action->execute($settings, $request->user());

        return $this->success([
            'settings' => (new EventGallerySettingsResource($result['settings']))->resolve(),
            'revision' => (new EventGalleryRevisionResource($result['revision']))->resolve(),
        ]);
    }

    public function publish(
        Request $request,
        Event $event,
        GalleryBuilderPresetRegistry $registry,
        PublishEventGalleryDraftAction $action,
        AnalyticsTracker $analytics,
    ): JsonResponse {
        abort_unless($request->user()?->can('gallery.builder.manage'), 403);
        $this->authorize('update', $event);

        $settings = $this->ensureSettings($event, $request->user(), $registry);
        $result = $action->execute($settings, $request->user());
        $revision = $result['revision'];

        $analytics->trackEvent(
            $event,
            'gallery.builder_published',
            $request,
            [
                'revision_id' => $revision->id,
                'version_number' => $revision->version_number,
                'draft_version' => $result['settings']->draft_version,
                'published_version' => $result['settings']->published_version,
            ],
            channel: 'gallery_builder',
        );

        return $this->success([
            'settings' => (new EventGallerySettingsResource($result['settings']))->resolve(),
            'revision' => (new EventGalleryRevisionResource($revision))->resolve(),
        ]);
    }

    public function revisions(
        Request $request,
        Event $event,
        GalleryBuilderPresetRegistry $registry,
        ListEventGalleryRevisionsQuery $query,
    ): JsonResponse {
        abort_unless($request->user()?->can('gallery.builder.manage'), 403);
        $this->authorize('view', $event);

        $this->ensureSettings($event, $request->user(), $registry);

        return $this->success(
            EventGalleryRevisionResource::collection($query->execute($event->id))->resolve(),
        );
    }

    public function restore(
        Request $request,
        Event $event,
        EventGalleryRevision $revision,
        GalleryBuilderPresetRegistry $registry,
        RestoreEventGalleryRevisionAction $action,
        AnalyticsTracker $analytics,
    ): JsonResponse {
        abort_unless($request->user()?->can('gallery.builder.manage'), 403);
        $this->authorize('update', $event);
        abort_unless($revision->event_id === $event->id, 404);

        $settings = $this->ensureSettings($event, $request->user(), $registry);
        $result = $action->execute($settings, $revision, $request->user());
        $restoredSettings = $result['settings'];
        $restoredRevision = $result['revision'];

        $restoredSettings->forceFill([
            'current_preset_origin_json' => $this->makePresetOrigin(
                originType: 'restore',
                key: 'revision:'.$revision->id,
                label: 'Restaurado da versao '.$revision->version_number,
                user: $request->user(),
            ),
        ])->save();
        $restoredSettings = $restoredSettings->fresh(['currentDraftRevision', 'currentPublishedRevision', 'previewRevision']);

        $analytics->trackEvent(
            $event,
            'gallery.builder_restored',
            $request,
            [
                'revision_id' => $restoredRevision->id,
                'version_number' => $restoredRevision->version_number,
                'restored_from_revision_id' => $revision->id,
                'restored_from_version_number' => $revision->version_number,
            ],
            channel: 'gallery_builder',
        );

        return $this->success([
            'settings' => (new EventGallerySettingsResource($restoredSettings))->resolve(),
            'revision' => (new EventGalleryRevisionResource($restoredRevision))->resolve(),
        ]);
    }

    public function previewLink(
        Request $request,
        Event $event,
        GalleryBuilderPresetRegistry $registry,
        AutosaveEventGalleryDraftAction $autosaveAction,
    ): JsonResponse {
        abort_unless($request->user()?->can('gallery.builder.manage'), 403);
        $this->authorize('update', $event);

        $settings = $this->ensureSettings($event, $request->user(), $registry);
        $result = $autosaveAction->execute($settings, $request->user());
        $settings = $result['settings'];
        $revision = $result['revision'];
        $token = Str::lower(Str::random(32));
        $expiresAt = now()->addDays(7);

        $settings->fill([
            'preview_share_token' => $token,
            'preview_share_expires_at' => $expiresAt,
            'preview_revision_id' => $revision->id,
            'updated_by' => $request->user()?->id,
        ]);
        $settings->save();

        return $this->success([
            'token' => $token,
            'preview_url' => rtrim((string) config('app.url'), '/')."/api/v1/public/gallery-previews/{$token}",
            'expires_at' => $expiresAt->toIso8601String(),
            'revision' => (new EventGalleryRevisionResource($revision))->resolve(),
        ]);
    }

    public function uploadHeroImage(
        UploadEventGalleryAssetRequest $request,
        Event $event,
        GalleryBuilderPresetRegistry $registry,
        UploadEventGalleryAssetAction $action,
    ): JsonResponse {
        abort_unless($request->user()?->can('gallery.builder.manage'), 403);
        $this->authorize('update', $event);

        $settings = $this->ensureSettings($event, $request->user(), $registry);
        $result = $action->execute(
            $event,
            $settings,
            $request->file('file'),
            'hero',
            $request->validated('previous_path'),
            $request->user(),
        );

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties(['event_id' => $event->id, 'hero_image_path' => $result['path']])
            ->log('Hero image da galeria atualizada');

        return $this->success([
            'asset' => [
                'kind' => $result['kind'],
                'path' => $result['path'],
                'url' => $result['url'],
            ],
            'settings' => (new EventGallerySettingsResource($result['settings']))->resolve(),
        ]);
    }

    public function uploadBannerImage(
        UploadEventGalleryAssetRequest $request,
        Event $event,
        GalleryBuilderPresetRegistry $registry,
        UploadEventGalleryAssetAction $action,
    ): JsonResponse {
        abort_unless($request->user()?->can('gallery.builder.manage'), 403);
        $this->authorize('update', $event);

        $settings = $this->ensureSettings($event, $request->user(), $registry);
        $result = $action->execute(
            $event,
            $settings,
            $request->file('file'),
            'banner',
            $request->validated('previous_path'),
            $request->user(),
        );

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties(['event_id' => $event->id, 'banner_image_path' => $result['path']])
            ->log('Banner image da galeria atualizada');

        return $this->success([
            'asset' => [
                'kind' => $result['kind'],
                'path' => $result['path'],
                'url' => $result['url'],
            ],
            'settings' => (new EventGallerySettingsResource($result['settings']))->resolve(),
        ]);
    }

    public function aiProposals(
        RunGalleryBuilderPromptRequest $request,
        Event $event,
        GalleryBuilderPresetRegistry $registry,
        RunGalleryBuilderPromptAction $action,
    ): JsonResponse {
        abort_unless($request->user()?->can('gallery.builder.manage'), 403);
        $this->authorize('update', $event);

        $settings = $this->ensureSettings($event, $request->user(), $registry);
        $result = $action->execute($event, $settings, $request->validated(), $request->user());

        return $this->success([
            'run' => [
                'id' => $result['run']->id,
                'event_id' => $result['run']->event_id,
                'organization_id' => $result['run']->organization_id,
                'user_id' => $result['run']->user_id,
                'prompt_text' => $result['run']->prompt_text,
                'persona_key' => $result['run']->persona_key,
                'event_type_key' => $result['run']->event_type_key,
                'target_layer' => $result['run']->target_layer,
                'base_preset_key' => $result['run']->base_preset_key,
                'response_schema_version' => $result['run']->response_schema_version,
                'status' => $result['run']->status,
                'provider_key' => $result['run']->provider_key,
                'model_key' => $result['run']->model_key,
                'created_at' => $result['run']->created_at?->toIso8601String(),
            ],
            'variations' => $result['variations'],
        ]);
    }

    public function telemetry(
        StoreGalleryBuilderTelemetryRequest $request,
        Event $event,
        GalleryBuilderPresetRegistry $registry,
        AnalyticsTracker $analytics,
        GalleryBuilderOperationalFeedbackResolver $feedbackResolver,
    ): JsonResponse {
        $this->authorize('update', $event);

        $settings = $this->ensureSettings($event, $request->user(), $registry);
        $payload = $request->validated();

        if ($payload['event'] === 'preset_applied') {
            $origin = [
                'origin_type' => $payload['preset']['origin_type'],
                'key' => $payload['preset']['key'],
                'label' => $payload['preset']['label'],
                'applied_at' => now()->toIso8601String(),
                'applied_by' => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                    ]
                    : null,
            ];

            $settings->forceFill([
                'current_preset_origin_json' => $origin,
                'updated_by' => $request->user()?->id ?? $settings->updated_by,
            ])->save();

            $analytics->trackEvent(
                $event,
                'gallery.builder_preset_applied',
                $request,
                [
                    'origin_type' => $origin['origin_type'],
                    'origin_key' => $origin['key'],
                    'origin_label' => $origin['label'],
                ],
                channel: 'gallery_builder',
            );
        }

        if ($payload['event'] === 'ai_applied') {
            $run = GalleryBuilderPromptRun::query()->firstWhere('id', $payload['run_id']);
            abort_unless($run instanceof GalleryBuilderPromptRun && $run->event_id === $event->id, 404);

            $responsePayload = is_array($run->response_payload_json) ? $run->response_payload_json : [];
            $responsePayload['selected_variation'] = [
                'id' => $payload['variation_id'],
                'apply_scope' => $payload['apply_scope'],
                'applied_at' => now()->toIso8601String(),
                'applied_by' => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                    ]
                    : null,
            ];

            $run->forceFill([
                'selected_variation_id' => $payload['variation_id'],
                'response_payload_json' => $responsePayload,
            ])->save();

            $analytics->trackEvent(
                $event,
                'gallery.builder_ai_applied',
                $request,
                [
                    'run_id' => $run->id,
                    'variation_id' => $payload['variation_id'],
                    'apply_scope' => $payload['apply_scope'],
                    'target_layer' => $run->target_layer,
                ],
                channel: 'gallery_builder',
            );
        }

        if ($payload['event'] === 'vitals_sample') {
            $analytics->trackEvent(
                $event,
                'gallery.builder_vitals_sample',
                $request,
                [
                    'viewport' => $payload['viewport'] ?? null,
                    'item_count' => $payload['item_count'] ?? null,
                    'layout' => $payload['layout'] ?? null,
                    'density' => $payload['density'] ?? null,
                    'render_mode' => $payload['render_mode'] ?? null,
                    'lcp_ms' => $payload['lcp_ms'] ?? null,
                    'inp_ms' => $payload['inp_ms'] ?? null,
                    'cls' => $payload['cls'] ?? null,
                    'preview_latency_ms' => $payload['preview_latency_ms'] ?? null,
                    'publish_latency_ms' => $payload['publish_latency_ms'] ?? null,
                ],
                channel: 'gallery_builder',
            );
        }

        $settings = $settings->fresh(['currentDraftRevision', 'currentPublishedRevision', 'previewRevision']);

        return $this->success([
            'current_preset_origin' => (new EventGallerySettingsResource($settings))->resolve()['current_preset_origin'] ?? null,
            'operational_feedback' => $this->feedback($settings, $feedbackResolver),
        ]);
    }

    private function ensureSettings(
        Event $event,
        ?\App\Modules\Users\Models\User $user,
        GalleryBuilderPresetRegistry $registry,
    ): EventGallerySetting {
        $settings = EventGallerySetting::query()->firstWhere('event_id', $event->id);

        if ($settings instanceof EventGallerySetting) {
            return $settings->loadMissing(['currentDraftRevision', 'currentPublishedRevision', 'previewRevision']);
        }

        $defaults = $registry->defaultsForEvent($event);

        return EventGallerySetting::query()->create([
            'event_id' => $event->id,
            'is_enabled' => $defaults['is_enabled'],
            'event_type_family' => $defaults['event_type_family'],
            'style_skin' => $defaults['style_skin'],
            'behavior_profile' => $defaults['behavior_profile'],
            'theme_key' => $defaults['theme_key'],
            'layout_key' => $defaults['layout_key'],
            'theme_tokens_json' => $defaults['theme_tokens'],
            'page_schema_json' => $defaults['page_schema'],
            'media_behavior_json' => $defaults['media_behavior'],
            'current_preset_origin_json' => null,
            'updated_by' => $user?->id,
        ])->loadMissing(['currentDraftRevision', 'currentPublishedRevision', 'previewRevision']);
    }

    /**
     * @return array<string, mixed>
     */
    private function feedback(
        EventGallerySetting $settings,
        GalleryBuilderOperationalFeedbackResolver $resolver,
    ): array {
        return $resolver->resolve($settings);
    }

    /**
     * @return array<string, mixed>
     */
    private function makePresetOrigin(
        string $originType,
        ?string $key,
        ?string $label,
        ?\App\Modules\Users\Models\User $user,
    ): array {
        return [
            'origin_type' => $originType,
            'key' => $key,
            'label' => $label,
            'applied_at' => now()->toIso8601String(),
            'applied_by' => $user
                ? [
                    'id' => $user->id,
                    'name' => $user->name,
                ]
                : null,
        ];
    }
}
