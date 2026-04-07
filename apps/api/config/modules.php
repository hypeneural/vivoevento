<?php

/**
 * Modules Configuration
 *
 * Register all domain modules and their service providers.
 * Each module must have a ServiceProvider registered here.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Registered Modules
    |--------------------------------------------------------------------------
    |
    | List of all domain modules and their service providers.
    | Order matters: dependencies should be loaded first.
    |
    */

    'modules' => [
        // Etapa 1 — Core
        'Organizations' => App\Modules\Organizations\Providers\OrganizationsServiceProvider::class,
        'Users'         => App\Modules\Users\Providers\UsersServiceProvider::class,
        'Roles'         => App\Modules\Roles\Providers\RolesServiceProvider::class,
        'Auth'          => App\Modules\Auth\Providers\AuthServiceProvider::class,
        'Clients'       => App\Modules\Clients\Providers\ClientsServiceProvider::class,
        'Events'        => App\Modules\Events\Providers\EventsServiceProvider::class,
        'EventTeam'     => App\Modules\EventTeam\Providers\EventTeamServiceProvider::class,
        'Dashboard'     => App\Modules\Dashboard\Providers\DashboardServiceProvider::class,

        // Etapa 2 — Ingestão
        'Channels'        => App\Modules\Channels\Providers\ChannelsServiceProvider::class,
        'InboundMedia'    => App\Modules\InboundMedia\Providers\InboundMediaServiceProvider::class,
        'WhatsApp'        => App\Modules\WhatsApp\Providers\WhatsAppServiceProvider::class,
        'Telegram'        => App\Modules\Telegram\Providers\TelegramServiceProvider::class,
        'MediaProcessing' => App\Modules\MediaProcessing\Providers\MediaProcessingServiceProvider::class,
        'ContentModeration' => App\Modules\ContentModeration\Providers\ContentModerationServiceProvider::class,
        'FaceSearch' => App\Modules\FaceSearch\Providers\FaceSearchServiceProvider::class,
        'MediaIntelligence' => App\Modules\MediaIntelligence\Providers\MediaIntelligenceServiceProvider::class,

        // Etapa 3 — Experiência
        'Gallery' => App\Modules\Gallery\Providers\GalleryServiceProvider::class,
        'Wall'    => App\Modules\Wall\Providers\WallServiceProvider::class,

        // Etapa 4 — Interação
        'Play' => App\Modules\Play\Providers\PlayServiceProvider::class,
        'Hub'  => App\Modules\Hub\Providers\HubServiceProvider::class,

        // Etapa 5 — Negócio
        'Plans'         => App\Modules\Plans\Providers\PlansServiceProvider::class,
        'Billing'       => App\Modules\Billing\Providers\BillingServiceProvider::class,
        'Partners'      => App\Modules\Partners\Providers\PartnersServiceProvider::class,
        'Analytics'     => App\Modules\Analytics\Providers\AnalyticsServiceProvider::class,
        'Audit'         => App\Modules\Audit\Providers\AuditServiceProvider::class,
        'Notifications' => App\Modules\Notifications\Providers\NotificationsServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Prefix & Version
    |--------------------------------------------------------------------------
    */

    'api_prefix'  => 'api',
    'api_version' => 'v1',

];
