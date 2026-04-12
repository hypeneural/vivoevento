<?php

namespace App\Modules\Gallery\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Actions\AutosaveEventGalleryDraftAction;
use App\Modules\Gallery\Actions\PublishEventGalleryDraftAction;
use App\Modules\Gallery\Actions\RunGalleryBuilderPromptAction;
use App\Modules\Gallery\Actions\RestoreEventGalleryRevisionAction;
use App\Modules\Gallery\Actions\UploadEventGalleryAssetAction;
use App\Modules\Gallery\Actions\UpdateEventGallerySettingsAction;
use App\Modules\Gallery\Http\Requests\RunGalleryBuilderPromptRequest;
use App\Modules\Gallery\Http\Requests\ShowEventGallerySettingsRequest;
use App\Modules\Gallery\Http\Requests\UploadEventGalleryAssetRequest;
use App\Modules\Gallery\Http\Requests\UpdateEventGallerySettingsRequest;
use App\Modules\Gallery\Http\Resources\EventGalleryRevisionResource;
use App\Modules\Gallery\Http\Resources\EventGallerySettingsResource;
use App\Modules\Gallery\Models\EventGalleryRevision;
use App\Modules\Gallery\Models\EventGallerySetting;
use App\Modules\Gallery\Queries\ListEventGalleryRevisionsQuery;
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
    ): JsonResponse {
        abort_unless($request->user()?->can('gallery.builder.manage'), 403);
        $this->authorize('update', $event);
        abort_unless($revision->event_id === $event->id, 404);

        $settings = $this->ensureSettings($event, $request->user(), $registry);
        $result = $action->execute($settings, $revision, $request->user());

        return $this->success([
            'settings' => (new EventGallerySettingsResource($result['settings']))->resolve(),
            'revision' => (new EventGalleryRevisionResource($result['revision']))->resolve(),
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
            'updated_by' => $user?->id,
        ])->loadMissing(['currentDraftRevision', 'currentPublishedRevision', 'previewRevision']);
    }
}
