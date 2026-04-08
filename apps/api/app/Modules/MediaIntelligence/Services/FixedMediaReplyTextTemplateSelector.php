<?php

namespace App\Modules\MediaIntelligence\Services;

class FixedMediaReplyTextTemplateSelector
{
    /**
     * @param array<int, string> $templates
     */
    public function pick(array $templates, string $seed): ?string
    {
        $templates = array_values(array_filter(array_map(
            static fn (mixed $item): ?string => is_string($item) && trim($item) !== '' ? trim($item) : null,
            $templates,
        )));

        if ($templates === []) {
            return null;
        }

        $index = abs(crc32($seed)) % count($templates);

        return $templates[$index] ?? null;
    }
}
