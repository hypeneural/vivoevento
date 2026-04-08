<?php

namespace App\Modules\MediaIntelligence\Http\Controllers;

use App\Modules\MediaIntelligence\Http\Requests\StoreMediaReplyPromptPresetRequest;
use App\Modules\MediaIntelligence\Http\Requests\UpdateMediaReplyPromptPresetRequest;
use App\Modules\MediaIntelligence\Http\Resources\MediaReplyPromptPresetResource;
use App\Modules\MediaIntelligence\Models\MediaReplyPromptPreset;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaReplyPromptPresetController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = MediaReplyPromptPreset::query()
            ->with('categoryEntry')
            ->orderBy('sort_order')
            ->orderBy('name');

        if (! ($request->user()?->hasAnyRole(['super-admin', 'platform-admin']) ?? false)) {
            $query->where('is_active', true);
        }

        return $this->success(MediaReplyPromptPresetResource::collection($query->get())->resolve());
    }

    public function store(StoreMediaReplyPromptPresetRequest $request): JsonResponse
    {
        $preset = MediaReplyPromptPreset::query()->create([
            'slug' => $this->resolveSlug($request->validated('slug'), $request->validated('name')),
            'name' => $request->validated('name'),
            'category' => $request->validated('category'),
            'description' => $request->validated('description'),
            'prompt_template' => $request->validated('prompt_template'),
            'sort_order' => (int) ($request->validated('sort_order') ?? 0),
            'is_active' => (bool) $request->validated('is_active'),
            'created_by' => $request->user()?->id,
        ]);

        return $this->created((new MediaReplyPromptPresetResource($preset->load('categoryEntry')))->resolve());
    }

    public function update(UpdateMediaReplyPromptPresetRequest $request, MediaReplyPromptPreset $preset): JsonResponse
    {
        $preset->fill([
            'slug' => $this->resolveSlug($request->validated('slug'), $request->validated('name')),
            'name' => $request->validated('name'),
            'category' => $request->validated('category'),
            'description' => $request->validated('description'),
            'prompt_template' => $request->validated('prompt_template'),
            'sort_order' => (int) ($request->validated('sort_order') ?? 0),
            'is_active' => (bool) $request->validated('is_active'),
        ])->save();

        return $this->success((new MediaReplyPromptPresetResource($preset->refresh()->load('categoryEntry')))->resolve());
    }

    public function destroy(Request $request, MediaReplyPromptPreset $preset): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['super-admin', 'platform-admin']) ?? false, 403);

        $preset->delete();

        return $this->noContent();
    }

    private function resolveSlug(?string $slug, string $name): string
    {
        $resolved = trim((string) $slug);

        if ($resolved !== '') {
            return Str::slug($resolved);
        }

        return Str::slug($name);
    }
}
