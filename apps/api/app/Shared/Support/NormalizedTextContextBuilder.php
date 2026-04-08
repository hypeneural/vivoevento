<?php

namespace App\Shared\Support;

class NormalizedTextContextBuilder
{
    /**
     * @return array{mode:string,text:?string}
     */
    public function build(
        string $mode,
        ?string $caption = null,
        ?string $bodyText = null,
        ?string $operatorSummary = null,
    ): array {
        $resolvedMode = $this->normalizeMode($mode);

        $text = match ($resolvedMode) {
            'none' => null,
            'body_only' => $this->clean($bodyText),
            'caption_only' => $this->clean($caption),
            'operator_summary' => $this->clean($operatorSummary),
            default => $this->combine($caption, $bodyText),
        };

        return [
            'mode' => $resolvedMode,
            'text' => $text,
        ];
    }

    private function normalizeMode(?string $mode): string
    {
        return match ($mode) {
            'none', 'body_only', 'caption_only', 'body_plus_caption', 'operator_summary' => $mode,
            default => 'body_plus_caption',
        };
    }

    private function clean(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim(preg_replace("/\r\n?/", "\n", $value) ?? $value);

        return $normalized === '' ? null : $normalized;
    }

    private function combine(?string $caption, ?string $bodyText): ?string
    {
        $parts = [];

        foreach ([$caption, $bodyText] as $candidate) {
            $cleaned = $this->clean($candidate);

            if ($cleaned === null || in_array($cleaned, $parts, true)) {
                continue;
            }

            $parts[] = $cleaned;
        }

        if ($parts === []) {
            return null;
        }

        return implode("\n\n", $parts);
    }
}
