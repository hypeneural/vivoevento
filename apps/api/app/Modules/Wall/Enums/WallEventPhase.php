<?php

namespace App\Modules\Wall\Enums;

enum WallEventPhase: string
{
    case Reception = 'reception';
    case Flow = 'flow';
    case Party = 'party';
    case Closing = 'closing';

    public function label(): string
    {
        return match ($this) {
            self::Reception => 'Recepcao',
            self::Flow => 'Fluxo',
            self::Party => 'Festa',
            self::Closing => 'Encerramento',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Reception => 'Momento inicial do evento, com fila mais calma e mais cuidado com repeticao.',
            self::Flow => 'Fase padrao do evento, equilibrando novidade, replay e justica.',
            self::Party => 'Pico da festa, com troca mais viva e menor atraso para mostrar conteudo novo.',
            self::Closing => 'Fechamento do evento, com ritmo mais contemplativo e replays um pouco mais flexiveis.',
        };
    }
}
