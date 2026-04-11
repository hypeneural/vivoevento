<?php

namespace App\Modules\Events\Support;

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventPublicLinkQrConfig;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventPublicLinkQrStateBuilder
{
    public function __construct(
        private readonly EventPublicLinksService $publicLinks,
        private readonly EventBrandingResolver $brandingResolver,
        private readonly EventPublicLinkQrConfigSchema $schema,
    ) {}

    public function build(Event $event, string $linkKey): array
    {
        $linksPayload = $this->publicLinks->links($event);
        $branding = $this->brandingResolver->resolve($event);
        $savedConfigs = $this->loadSavedConfigs($event);

        return $this->buildFromResolvedState(
            event: $event,
            linkKey: $linkKey,
            links: $linksPayload['links'] ?? [],
            branding: $branding,
            savedConfigs: $savedConfigs,
        );
    }

    public function buildAll(Event $event): array
    {
        $linksPayload = $this->publicLinks->links($event);
        $branding = $this->brandingResolver->resolve($event);
        $savedConfigs = $this->loadSavedConfigs($event);

        return array_map(
            fn (string $linkKey) => $this->buildFromResolvedState(
                event: $event,
                linkKey: $linkKey,
                links: $linksPayload['links'] ?? [],
                branding: $branding,
                savedConfigs: $savedConfigs,
            ),
            array_keys($linksPayload['links'] ?? []),
        );
    }

    /**
     * @return array<string, EventPublicLinkQrConfig>
     */
    private function loadSavedConfigs(Event $event): array
    {
        if (! Schema::hasTable('event_public_link_qr_configs')) {
            return [];
        }

        try {
            return EventPublicLinkQrConfig::query()
                ->where('event_id', $event->id)
                ->get()
                ->keyBy('link_key')
                ->all();
        } catch (QueryException $exception) {
            if ($this->isMissingQrConfigTableException($exception)) {
                return [];
            }

            throw $exception;
        }
    }

    private function isMissingQrConfigTableException(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'event_public_link_qr_configs')
            && (
                str_contains($message, 'Undefined table')
                || str_contains($message, 'does not exist')
                || str_contains($message, 'no such table')
            );
    }

    /**
     * @param array<string, array<string, mixed>> $links
     * @param array<string, mixed> $branding
     * @param array<string, EventPublicLinkQrConfig> $savedConfigs
     */
    private function buildFromResolvedState(
        Event $event,
        string $linkKey,
        array $links,
        array $branding,
        array $savedConfigs,
    ): array {
        if (! array_key_exists($linkKey, $links)) {
            throw new NotFoundHttpException("QR config link [{$linkKey}] was not found.");
        }

        $saved = $savedConfigs[$linkKey] ?? null;
        $config = $saved
            ? $this->schema->normalize(
                input: $saved->config_json,
                linkKey: $linkKey,
                effectiveBranding: null,
                applyBrandingDefaults: false,
            )
            : $this->schema->defaultForLink($linkKey, $branding);

        return [
            'event_id' => $event->id,
            'link_key' => $linkKey,
            'link' => $links[$linkKey],
            'effective_branding' => $branding,
            'config' => $config,
            'config_source' => $saved ? 'saved' : 'default',
            'has_saved_config' => $saved !== null,
            'updated_at' => $saved?->updated_at?->toISOString(),
            'assets' => [
                'svg_path' => $saved?->svg_path,
                'png_path' => $saved?->png_path,
            ],
        ];
    }
}
