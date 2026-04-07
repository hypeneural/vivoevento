<?php

namespace App\Modules\MediaIntelligence\Services;

class OpenAiCompatibleMultimodalPayloadNormalizer
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array
    {
        $normalized = $payload;

        if (isset($payload['messages']) && is_array($payload['messages'])) {
            $normalized['messages'] = array_values(array_map(
                fn (mixed $message): array => $this->normalizeMessage($message),
                $payload['messages'],
            ));
        }

        if (isset($payload['response_format']) && is_array($payload['response_format'])) {
            $responseFormat = $this->normalizeResponseFormat($payload['response_format']);

            if ($responseFormat === null) {
                unset($normalized['response_format']);
            } else {
                $normalized['response_format'] = $responseFormat;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMessage(mixed $message): array
    {
        if (! is_array($message)) {
            return [
                'role' => 'user',
                'content' => '',
            ];
        }

        $normalized = [
            'role' => is_string($message['role'] ?? null) ? $message['role'] : 'user',
        ];

        $content = $message['content'] ?? '';

        if (is_array($content)) {
            $normalized['content'] = $this->normalizeContentItems($content);

            return $normalized;
        }

        $normalized['content'] = is_string($content) ? $content : '';

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeContentItems(array $items): array
    {
        $normalized = [];
        $hasImage = false;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = is_string($item['type'] ?? null) ? $item['type'] : null;

            if ($type === 'text') {
                $text = $this->normalizeTextItem($item);

                if ($text !== null) {
                    $normalized[] = $text;
                }

                continue;
            }

            if ($type === 'image_url' && ! $hasImage) {
                $image = $this->normalizeImageItem($item);

                if ($image !== null) {
                    $normalized[] = $image;
                    $hasImage = true;
                }
            }
        }

        return array_values($normalized);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>|null
     */
    private function normalizeTextItem(array $item): ?array
    {
        if (! is_string($item['text'] ?? null)) {
            return null;
        }

        return [
            'type' => 'text',
            'text' => $item['text'],
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>|null
     */
    private function normalizeImageItem(array $item): ?array
    {
        $imageUrl = $item['image_url'] ?? null;
        $url = null;

        if (is_string($imageUrl)) {
            $url = trim($imageUrl);
        } elseif (is_array($imageUrl) && is_string($imageUrl['url'] ?? null)) {
            $url = trim($imageUrl['url']);
        }

        if ($url === null || $url === '') {
            return null;
        }

        return [
            'type' => 'image_url',
            'image_url' => [
                'url' => $url,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $responseFormat
     * @return array<string, mixed>|null
     */
    private function normalizeResponseFormat(array $responseFormat): ?array
    {
        $type = is_string($responseFormat['type'] ?? null)
            ? $responseFormat['type']
            : null;

        if ($type !== 'json_schema') {
            return null;
        }

        $jsonSchema = $responseFormat['json_schema'] ?? null;

        if (! is_array($jsonSchema) || ! is_string($jsonSchema['name'] ?? null) || ! is_array($jsonSchema['schema'] ?? null)) {
            return null;
        }

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => $jsonSchema['name'],
                'strict' => (bool) ($jsonSchema['strict'] ?? false),
                'schema' => $jsonSchema['schema'],
            ],
        ];
    }
}
