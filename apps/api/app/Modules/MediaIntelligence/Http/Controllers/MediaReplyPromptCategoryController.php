<?php

namespace App\Modules\MediaIntelligence\Http\Controllers;

use App\Modules\MediaIntelligence\Http\Requests\StoreMediaReplyPromptCategoryRequest;
use App\Modules\MediaIntelligence\Http\Requests\UpdateMediaReplyPromptCategoryRequest;
use App\Modules\MediaIntelligence\Http\Resources\MediaReplyPromptCategoryResource;
use App\Modules\MediaIntelligence\Models\MediaReplyPromptCategory;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaReplyPromptCategoryController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = MediaReplyPromptCategory::query()->orderBy('sort_order')->orderBy('name');

        if (! ($request->user()?->hasAnyRole(['super-admin', 'platform-admin']) ?? false)) {
            $query->where('is_active', true);
        }

        return $this->success(MediaReplyPromptCategoryResource::collection($query->get())->resolve());
    }

    public function store(StoreMediaReplyPromptCategoryRequest $request): JsonResponse
    {
        $category = MediaReplyPromptCategory::query()->create([
            'slug' => $this->resolveSlug($request->validated('slug'), $request->validated('name')),
            'name' => $request->validated('name'),
            'sort_order' => (int) ($request->validated('sort_order') ?? 0),
            'is_active' => (bool) $request->validated('is_active'),
        ]);

        return $this->created((new MediaReplyPromptCategoryResource($category))->resolve());
    }

    public function update(UpdateMediaReplyPromptCategoryRequest $request, MediaReplyPromptCategory $category): JsonResponse
    {
        $oldSlug = $category->slug;

        $category->fill([
            'slug' => $this->resolveSlug($request->validated('slug'), $request->validated('name')),
            'name' => $request->validated('name'),
            'sort_order' => (int) ($request->validated('sort_order') ?? 0),
            'is_active' => (bool) $request->validated('is_active'),
        ])->save();

        if ($oldSlug !== $category->slug) {
            $category->presets()->update(['category' => $category->slug]);
        }

        return $this->success((new MediaReplyPromptCategoryResource($category->refresh()))->resolve());
    }

    public function destroy(Request $request, MediaReplyPromptCategory $category): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['super-admin', 'platform-admin']) ?? false, 403);

        $category->presets()->update(['category' => null]);
        $category->delete();

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
