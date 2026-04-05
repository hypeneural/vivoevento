<?php

namespace App\Modules\Wall\Enums;

enum WallSelectionMode: string
{
    case Balanced = 'balanced';
    case Live = 'live';
    case Inclusive = 'inclusive';
    case Editorial = 'editorial';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Balanced => 'Equilibrado',
            self::Live => 'Ao vivo',
            self::Inclusive => 'Inclusivo',
            self::Editorial => 'Editorial',
            self::Custom => 'Personalizado',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Balanced => 'Distribui melhor entre convidados e evita monopolizacao do telao.',
            self::Live => 'Valoriza fotos recem-chegadas sem perder a justica basica da fila.',
            self::Inclusive => 'Prioriza mostrar pessoas diferentes antes de repetir remetentes.',
            self::Editorial => 'Mantem a justica base, mas abre mais espaco para destaques da operacao.',
            self::Custom => 'Usa uma combinacao personalizada de regras de fila e fairness.',
        };
    }
}
