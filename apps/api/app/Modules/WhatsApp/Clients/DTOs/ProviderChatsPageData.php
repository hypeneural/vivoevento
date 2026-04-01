<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Paginated chats result from provider.
 */
final readonly class ProviderChatsPageData
{
    /**
     * @param bool   $success
     * @param array  $chats    Array of chat objects from provider
     * @param int    $page     Current page
     * @param int    $pageSize Items per page
     * @param string|null $error
     */
    public function __construct(
        public bool $success,
        public array $chats = [],
        public int $page = 1,
        public int $pageSize = 20,
        public ?string $error = null,
    ) {}
}
