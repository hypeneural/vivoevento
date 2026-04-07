<?php

namespace App\Modules\MediaIntelligence\Services;

class OpenRouterModelCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        /** @var array<string, array<string, mixed>> $models */
        $models = (array) config('media_intelligence.providers.openrouter.allowed_models', []);

        return $models;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function metadata(string $modelKey): ?array
    {
        $resolvedModel = trim($modelKey);

        if ($resolvedModel === '') {
            return null;
        }

        $metadata = $this->all()[$resolvedModel] ?? null;

        return is_array($metadata) ? $metadata : null;
    }

    public function supportsImage(string $modelKey): bool
    {
        return (bool) ($this->metadata($modelKey)['supports_image'] ?? false);
    }

    public function supportsStructuredOutputs(string $modelKey): bool
    {
        return (bool) ($this->metadata($modelKey)['supports_json_schema'] ?? false);
    }
}
