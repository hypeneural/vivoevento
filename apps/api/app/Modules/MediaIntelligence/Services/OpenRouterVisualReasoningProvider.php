<?php

namespace App\Modules\MediaIntelligence\Services;

use Illuminate\Http\Client\PendingRequest;

class OpenRouterVisualReasoningProvider extends AbstractOpenAiCompatibleVisualReasoningProvider
{
    protected function providerKey(): string
    {
        return 'openrouter';
    }

    protected function providerLabel(): string
    {
        return 'OpenRouter';
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function extendRequest(PendingRequest $request, array $config): PendingRequest
    {
        $siteUrl = trim((string) ($config['site_url'] ?? ''));
        $appName = trim((string) ($config['app_name'] ?? ''));

        if ($siteUrl !== '') {
            $request = $request->withHeaders([
                'HTTP-Referer' => $siteUrl,
            ]);
        }

        if ($appName !== '') {
            $request = $request->withHeaders([
                'X-Title' => $appName,
            ]);
        }

        return $request;
    }
}
