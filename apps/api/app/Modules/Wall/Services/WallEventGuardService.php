<?php

namespace App\Modules\Wall\Services;

use App\Modules\Events\Models\Event;
use Illuminate\Validation\ValidationException;

class WallEventGuardService
{
    public function ensureCanStart(Event $event): void
    {
        $event->loadMissing('modules');

        if (! $event->isModuleEnabled('wall')) {
            throw ValidationException::withMessages([
                'event' => ['O modulo Wall nao esta habilitado para este evento.'],
            ]);
        }

        if (! $event->isActive()) {
            throw ValidationException::withMessages([
                'event' => ['O wall so pode ser iniciado em eventos ativos.'],
            ]);
        }
    }
}
