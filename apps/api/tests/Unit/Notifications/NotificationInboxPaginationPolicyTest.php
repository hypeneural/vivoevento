<?php

use App\Modules\Notifications\Support\NotificationInboxPaginationPolicy;

it('normalizes inbox pagination size for cursor queries', function () {
    expect(NotificationInboxPaginationPolicy::normalizePerPage(null))->toBe(NotificationInboxPaginationPolicy::DEFAULT_PER_PAGE)
        ->and(NotificationInboxPaginationPolicy::normalizePerPage(0))->toBe(NotificationInboxPaginationPolicy::DEFAULT_PER_PAGE)
        ->and(NotificationInboxPaginationPolicy::normalizePerPage(-10))->toBe(NotificationInboxPaginationPolicy::DEFAULT_PER_PAGE)
        ->and(NotificationInboxPaginationPolicy::normalizePerPage(8))->toBe(8)
        ->and(NotificationInboxPaginationPolicy::normalizePerPage(500))->toBe(NotificationInboxPaginationPolicy::MAX_PER_PAGE);
});

it('keeps the header dropdown smaller than a full inbox page', function () {
    expect(NotificationInboxPaginationPolicy::DROPDOWN_LIMIT)->toBeLessThan(NotificationInboxPaginationPolicy::DEFAULT_PER_PAGE)
        ->and(NotificationInboxPaginationPolicy::MAX_PER_PAGE)->toBe(50);
});
