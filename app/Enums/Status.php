<?php

namespace App\Enums;

enum Status: string
{
    case Open = 'open';
    case Lost = 'lost';
    case Winned = 'winned';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierto',
            self::Lost => 'Perdido',
            self::Winned => 'Ganado',
        };
    }

    public function number(): int
    {
        return match ($this) {
            self::Open => 1,
            self::Lost => 2,
            self::Winned => 3,
        };
    }
}
