<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Modules\Dashboard\Actions\BuildDashboardStatsAction;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends BaseController
{
    /**
     * GET /api/v1/dashboard/stats
     *
     * Returns the full dashboard payload for the authenticated user's organization.
     * Cached for 60 seconds per organization to avoid heavy queries on every load.
     */
    public function stats(Request $request, BuildDashboardStatsAction $action): JsonResponse
    {
        $user = $request->user();
        $organization = $user->currentOrganization();

        if (! $organization) {
            return $this->error('Nenhuma organização encontrada', 404);
        }

        $isAdmin = $user->hasAnyRole(['super-admin', 'platform-admin']);
        $cacheKey = "dashboard:stats:org:{$organization->id}:" . ($isAdmin ? 'admin' : 'user');

        $data = Cache::remember($cacheKey, 60, function () use ($action, $organization, $isAdmin) {
            return $action->execute($organization->id, $isAdmin);
        });

        return $this->success($data);
    }

    /**
     * GET /api/v1/dashboard/search?q=term
     *
     * Global search across events and media for the authenticated user's organization.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $user = $request->user();
        $organization = $user->currentOrganization();

        if (! $organization) {
            return $this->error('Nenhuma organização encontrada', 404);
        }

        $query = $request->input('q');
        $orgId = $organization->id;
        $like = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        // Search events
        $events = \App\Modules\Events\Models\Event::query()
            ->where('organization_id', $orgId)
            ->where(function ($q) use ($query, $like) {
                $q->where('title', $like, "%{$query}%")
                  ->orWhere('slug', $like, "%{$query}%")
                  ->orWhere('location_name', $like, "%{$query}%");
            })
            ->select('id', 'uuid', 'title', 'slug', 'event_type', 'status', 'starts_at', 'cover_image_path')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($e) => [
                'id'       => $e->id,
                'uuid'     => $e->uuid,
                'title'    => $e->title,
                'slug'     => $e->slug,
                'type'     => 'event',
                'subtitle' => ucfirst($e->event_type) . ' · ' . ucfirst($e->status),
                'url'      => "/events/{$e->id}",
                'image'    => $e->cover_image_path
                    ? (preg_match('/^https?:\/\//i', $e->cover_image_path)
                        ? $e->cover_image_path
                        : \Illuminate\Support\Facades\Storage::disk('public')->url($e->cover_image_path))
                    : null,
            ]);

        // Search media (by caption/source_label)
        $media = \App\Modules\MediaProcessing\Models\EventMedia::query()
            ->whereIn('event_id', function ($sub) use ($orgId) {
                $sub->select('id')
                    ->from('events')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at');
            })
            ->where(function ($q) use ($query, $like) {
                $q->where('caption', $like, "%{$query}%")
                  ->orWhere('source_label', $like, "%{$query}%")
                  ->orWhere('original_filename', $like, "%{$query}%");
            })
            ->select('id', 'event_id', 'caption', 'source_label', 'media_type', 'original_filename')
            ->with('event:id,title')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($m) => [
                'id'       => $m->id,
                'title'    => $m->caption ?: $m->original_filename ?: "Mídia #{$m->id}",
                'type'     => 'media',
                'subtitle' => ($m->event?->title ?? 'Evento') . ' · ' . ucfirst($m->media_type ?? 'image'),
                'url'      => "/events/{$m->event_id}?tab=media",
                'image'    => null,
            ]);

        // Search clients
        $clients = \App\Modules\Clients\Models\Client::query()
            ->where('organization_id', $orgId)
            ->where(function ($q) use ($query, $like) {
                $q->where('name', $like, "%{$query}%")
                  ->orWhere('email', $like, "%{$query}%")
                  ->orWhere('phone', $like, "%{$query}%");
            })
            ->select('id', 'name', 'email', 'type')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get()
            ->map(fn ($c) => [
                'id'       => $c->id,
                'title'    => $c->name,
                'type'     => 'client',
                'subtitle' => $c->email ?? ucfirst($c->type ?? 'Cliente'),
                'url'      => "/clients/{$c->id}",
                'image'    => null,
            ]);

        $results = collect()
            ->merge($events)
            ->merge($media)
            ->merge($clients)
            ->values()
            ->all();

        return $this->success([
            'results'    => $results,
            'total'      => count($results),
            'query'      => $query,
        ]);
    }
}
