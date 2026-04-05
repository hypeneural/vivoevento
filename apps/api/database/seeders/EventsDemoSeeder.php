<?php

namespace Database\Seeders;

use App\Modules\Clients\Models\Client;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $coverImages = [
            'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1530103862676-de8c9debad1d?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1522673607200-164d1b6ce486?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1517457373958-b7bdd4587205?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1505236858219-8359eb29e329?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1523580494863-6f3031224c94?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?auto=format&fit=crop&w=1200&q=80',
        ];

        $colorPairs = [
            ['primary' => '#f97316', 'secondary' => '#1d4ed8'],
            ['primary' => '#0f766e', 'secondary' => '#f59e0b'],
            ['primary' => '#ec4899', 'secondary' => '#8b5cf6'],
            ['primary' => '#0ea5e9', 'secondary' => '#4338ca'],
            ['primary' => '#16a34a', 'secondary' => '#0f766e'],
            ['primary' => '#e11d48', 'secondary' => '#7c3aed'],
        ];

        $organization = Organization::query()->where('slug', 'demo-fotografia')->first();
        $owner = User::query()->where('email', 'parceiro@eventovivo.com.br')->first()
            ?? User::query()->where('email', 'admin@eventovivo.com.br')->first();

        if (! $organization || ! $owner) {
            return;
        }

        $clients = collect([
            ['name' => 'Ana & Pedro', 'type' => 'pessoa_fisica', 'email' => 'ana-pedro@example.com'],
            ['name' => 'Julia & Marcos', 'type' => 'pessoa_fisica', 'email' => 'julia-marcos@example.com'],
            ['name' => 'TechForward Brasil', 'type' => 'empresa', 'email' => 'marketing@techforward.com.br'],
            ['name' => 'Colégio Horizonte', 'type' => 'empresa', 'email' => 'eventos@horizonte.edu.br'],
            ['name' => 'Marina Oliveira', 'type' => 'pessoa_fisica', 'email' => 'marina@example.com'],
            ['name' => 'Feira Casa Viva', 'type' => 'empresa', 'email' => 'contato@casaviva.com.br'],
        ])->map(function (array $clientData) use ($organization, $owner) {
            return Client::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => $clientData['name'],
                ],
                [
                    'type' => $clientData['type'],
                    'email' => $clientData['email'],
                    'created_by' => $owner->id,
                ]
            );
        })->keyBy('name');

        $events = [
            [
                'title' => 'Casamento Ana & Pedro',
                'client' => 'Ana & Pedro',
                'event_type' => 'wedding',
                'status' => 'active',
                'starts_at' => now()->addDays(10)->setTime(16, 0),
                'ends_at' => now()->addDays(10)->setTime(23, 30),
                'location_name' => 'Espaco Villa Real, Sao Paulo',
                'description' => 'Casamento ao ar livre com live gallery, wall e hub publico.',
                'visibility' => 'public',
                'moderation_mode' => 'manual',
                'modules' => ['live', 'wall', 'play', 'hub'],
                'media_count' => 36,
                'published_media_count' => 18,
            ],
            [
                'title' => 'Convenção TechForward 2026',
                'client' => 'TechForward Brasil',
                'event_type' => 'corporate',
                'status' => 'scheduled',
                'starts_at' => now()->addDays(18)->setTime(9, 0),
                'ends_at' => now()->addDays(18)->setTime(21, 0),
                'location_name' => 'Centro de Convencoes Frei Caneca',
                'description' => 'Convencao anual com cobertura ao vivo e onboarding de parceiros.',
                'visibility' => 'public',
                'moderation_mode' => 'ai',
                'modules' => ['live', 'wall', 'hub'],
                'media_count' => 12,
                'published_media_count' => 6,
            ],
            [
                'title' => '15 Anos Marina',
                'client' => 'Marina Oliveira',
                'event_type' => 'fifteen',
                'status' => 'draft',
                'starts_at' => now()->addDays(26)->setTime(19, 0),
                'ends_at' => now()->addDays(27)->setTime(1, 0),
                'location_name' => 'Buffet Encanto, Belo Horizonte',
                'description' => 'Evento em preparacao com foco em gameplay e galerias tematicas.',
                'visibility' => 'private',
                'moderation_mode' => 'manual',
                'modules' => ['live', 'play'],
                'media_count' => 0,
                'published_media_count' => 0,
            ],
            [
                'title' => 'Formatura Horizonte 2026',
                'client' => 'Colégio Horizonte',
                'event_type' => 'graduation',
                'status' => 'active',
                'starts_at' => now()->addDays(5)->setTime(18, 0),
                'ends_at' => now()->addDays(5)->setTime(23, 59),
                'location_name' => 'Clube Atletico Mineiro',
                'description' => 'Cerimonia e festa com hub do evento e slideshow de telão.',
                'visibility' => 'public',
                'moderation_mode' => 'manual',
                'modules' => ['live', 'wall', 'hub'],
                'media_count' => 28,
                'published_media_count' => 14,
            ],
            [
                'title' => 'Feira Casa Viva Expo',
                'client' => 'Feira Casa Viva',
                'event_type' => 'fair',
                'status' => 'paused',
                'starts_at' => now()->subDays(2)->setTime(10, 0),
                'ends_at' => now()->addDays(1)->setTime(20, 0),
                'location_name' => 'Expo Center Norte',
                'description' => 'Feira setorial com pausa operacional no wall para ajuste de cenarios.',
                'visibility' => 'public',
                'moderation_mode' => 'none',
                'modules' => ['live', 'wall', 'hub'],
                'media_count' => 42,
                'published_media_count' => 25,
            ],
            [
                'title' => 'Workshop Retratos Intensivo',
                'client' => 'TechForward Brasil',
                'event_type' => 'other',
                'status' => 'ended',
                'starts_at' => now()->subDays(12)->setTime(8, 30),
                'ends_at' => now()->subDays(12)->setTime(18, 0),
                'location_name' => 'Studio Demo Fotografia',
                'description' => 'Workshop encerrado, mantido para consulta de materiais e galeria.',
                'visibility' => 'unlisted',
                'moderation_mode' => 'manual',
                'modules' => ['live', 'hub'],
                'media_count' => 18,
                'published_media_count' => 10,
            ],
            [
                'title' => 'Casamento Julia & Marcos',
                'client' => 'Julia & Marcos',
                'event_type' => 'wedding',
                'status' => 'archived',
                'starts_at' => now()->subMonths(2)->setTime(15, 30),
                'ends_at' => now()->subMonths(2)->setTime(23, 0),
                'location_name' => 'Fazenda Santa Clara',
                'description' => 'Evento historico usado como referencia para layout e publico.',
                'visibility' => 'public',
                'moderation_mode' => 'manual',
                'modules' => ['live', 'wall', 'hub'],
                'media_count' => 24,
                'published_media_count' => 16,
            ],
            [
                'title' => 'Hackday Produto Aurora',
                'client' => 'TechForward Brasil',
                'event_type' => 'corporate',
                'status' => 'draft',
                'starts_at' => now()->addDays(40)->setTime(9, 0),
                'ends_at' => now()->addDays(40)->setTime(22, 0),
                'location_name' => 'Campus Cubo',
                'description' => 'Hackday interno ainda sem publicacao oficial.',
                'visibility' => 'private',
                'moderation_mode' => 'none',
                'modules' => ['live', 'play', 'hub'],
                'media_count' => 4,
                'published_media_count' => 0,
            ],
            [
                'title' => 'Aniversario Lucas 30',
                'client' => 'Marina Oliveira',
                'event_type' => 'birthday',
                'status' => 'active',
                'starts_at' => now()->addDays(3)->setTime(20, 0),
                'ends_at' => now()->addDays(4)->setTime(2, 0),
                'location_name' => 'Roofbar Downtown',
                'description' => 'Festa intimista com painel de fotos e QR de envio rapido.',
                'visibility' => 'public',
                'moderation_mode' => 'none',
                'modules' => ['live', 'wall'],
                'media_count' => 15,
                'published_media_count' => 8,
            ],
            [
                'title' => 'Mostra Arquitetura Viva',
                'client' => 'Feira Casa Viva',
                'event_type' => 'fair',
                'status' => 'scheduled',
                'starts_at' => now()->addDays(55)->setTime(14, 0),
                'ends_at' => now()->addDays(58)->setTime(21, 0),
                'location_name' => 'Pavilhao Bienal',
                'description' => 'Mostra com calendario multi-dia e captura orientada por ambientes.',
                'visibility' => 'public',
                'moderation_mode' => 'manual',
                'modules' => ['live', 'hub'],
                'media_count' => 7,
                'published_media_count' => 0,
            ],
            [
                'title' => 'Cerimonia Horizonte Night',
                'client' => 'Colégio Horizonte',
                'event_type' => 'graduation',
                'status' => 'ended',
                'starts_at' => now()->subDays(20)->setTime(19, 0),
                'ends_at' => now()->subDays(20)->setTime(23, 45),
                'location_name' => 'Palacio das Artes',
                'description' => 'Encerrado com playback de momentos e hub publico ativo.',
                'visibility' => 'public',
                'moderation_mode' => 'manual',
                'modules' => ['live', 'wall', 'play', 'hub'],
                'media_count' => 31,
                'published_media_count' => 20,
            ],
            [
                'title' => 'Treinamento Comercial Q2',
                'client' => 'TechForward Brasil',
                'event_type' => 'corporate',
                'status' => 'scheduled',
                'starts_at' => now()->addDays(8)->setTime(8, 0),
                'ends_at' => now()->addDays(8)->setTime(17, 0),
                'location_name' => 'Hotel Premium Paulista',
                'description' => 'Treinamento com material privado e cobertura controlada.',
                'visibility' => 'private',
                'moderation_mode' => 'manual',
                'modules' => ['hub'],
                'media_count' => 2,
                'published_media_count' => 0,
            ],
        ];

        foreach ($events as $index => $eventData) {
            $client = $clients->get($eventData['client']);
            $slugBase = Str::slug($eventData['title']);
            $colors = $colorPairs[$index % count($colorPairs)];

            $event = Event::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'slug' => "{$slugBase}-demo-" . ($index + 1),
                ],
                [
                    'client_id' => $client?->id,
                    'created_by' => $owner->id,
                    'title' => $eventData['title'],
                    'event_type' => $eventData['event_type'],
                    'status' => $eventData['status'],
                    'visibility' => $eventData['visibility'],
                    'moderation_mode' => $eventData['moderation_mode'],
                    'starts_at' => $eventData['starts_at'],
                    'ends_at' => $eventData['ends_at'],
                    'location_name' => $eventData['location_name'],
                    'description' => $eventData['description'],
                    'cover_image_path' => $coverImages[$index % count($coverImages)],
                    'retention_days' => 30,
                    'primary_color' => $colors['primary'],
                    'secondary_color' => $colors['secondary'],
                ]
            );

            $event->update([
                'public_url' => rtrim((string) config('app.url'), '/') . '/e/' . $event->slug,
                'upload_url' => $event->publicUploadUrl(),
            ]);

            $enabledModules = collect($eventData['modules'])->flip();

            foreach (['live', 'wall', 'play', 'hub'] as $moduleKey) {
                EventModule::query()->updateOrCreate(
                    [
                        'event_id' => $event->id,
                        'module_key' => $moduleKey,
                    ],
                    [
                        'is_enabled' => $enabledModules->has($moduleKey),
                    ]
                );
            }

            EventMedia::query()->where('event_id', $event->id)->delete();

            $publishedCount = min($eventData['published_media_count'], $eventData['media_count']);
            $pendingCount = max($eventData['media_count'] - $publishedCount, 0);

            if ($publishedCount > 0) {
                EventMedia::factory()
                    ->count($publishedCount)
                    ->published()
                    ->create([
                        'event_id' => $event->id,
                        'source_label' => 'Galeria publica',
                    ]);
            }

            if ($pendingCount > 0) {
                EventMedia::factory()
                    ->count($pendingCount)
                    ->create([
                        'event_id' => $event->id,
                        'source_label' => 'Fila de moderacao',
                    ]);
            }
        }
    }
}
