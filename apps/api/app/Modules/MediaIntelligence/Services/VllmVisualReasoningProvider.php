<?php

namespace App\Modules\MediaIntelligence\Services;

class VllmVisualReasoningProvider extends AbstractOpenAiCompatibleVisualReasoningProvider
{
    protected function providerKey(): string
    {
        return 'vllm';
    }

    protected function providerLabel(): string
    {
        return 'vLLM';
    }
}
