<?php

namespace App\Modules\MediaProcessing\Console;

use App\Modules\MediaProcessing\Jobs\GenerateMediaVariantsJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaToolingStatusService;
use Illuminate\Console\Command;

class BackfillWallVideoVariantsCommand extends Command
{
    protected $signature = 'media:backfill-wall-video-variants
        {--event_id= : Restringe o backfill a um evento especifico}
        {--limit=100 : Numero maximo de videos a enfileirar}
        {--dry-run : Apenas lista os videos candidatos sem enfileirar}';

    protected $description = 'Enfileira a geracao de wall_video_* e poster para videos legados do wall.';

    public function handle(MediaToolingStatusService $tooling): int
    {
        $toolingPayload = $tooling->payload();

        if (! ($toolingPayload['ready'] ?? false)) {
            $this->error('ffmpeg/ffprobe ainda nao estao prontos neste ambiente.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));

        $media = EventMedia::query()
            ->with('variants')
            ->where('media_type', 'video')
            ->whereNotNull('original_path')
            ->when($this->option('event_id'), fn ($query, $eventId) => $query->where('event_id', (int) $eventId))
            ->where(function ($query): void {
                $query->whereNull('duration_seconds')
                    ->orWhereNull('width')
                    ->orWhereNull('height')
                    ->orWhereDoesntHave('variants', fn ($variants) => $variants->where('variant_key', 'wall_video_720p'))
                    ->orWhereDoesntHave('variants', fn ($variants) => $variants->where('variant_key', 'wall_video_poster'));
            })
            ->orderBy('event_id')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($media->isEmpty()) {
            $this->info('Nenhum video legado pendente de wall variants foi encontrado.');

            return self::SUCCESS;
        }

        $this->line("Videos candidatos: {$media->count()}");

        foreach ($media as $item) {
            $this->line(" - event={$item->event_id} media={$item->id}");
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry run: nenhuma fila foi disparada.');

            return self::SUCCESS;
        }

        foreach ($media as $item) {
            GenerateMediaVariantsJob::dispatch($item->id);
        }

        $this->info("Backfill enfileirado para {$media->count()} video(s).");

        return self::SUCCESS;
    }
}
