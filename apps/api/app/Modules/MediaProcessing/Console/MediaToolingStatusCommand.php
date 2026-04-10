<?php

namespace App\Modules\MediaProcessing\Console;

use App\Modules\MediaProcessing\Services\MediaToolingStatusService;
use Illuminate\Console\Command;

class MediaToolingStatusCommand extends Command
{
    protected $signature = 'media:tooling-status';

    protected $description = 'Mostra se ffmpeg e ffprobe estao realmente disponiveis neste ambiente.';

    public function handle(MediaToolingStatusService $tooling): int
    {
        $payload = $tooling->payload();

        $this->line('ffmpeg: '.($payload['ffmpeg_resolved_path'] ?? $payload['ffmpeg_bin']));
        $this->line('ffprobe: '.($payload['ffprobe_resolved_path'] ?? $payload['ffprobe_bin']));
        $this->line('Status: '.($payload['ready'] ? 'ready' : 'not_ready'));

        return $payload['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
