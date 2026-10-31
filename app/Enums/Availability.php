<?php

namespace App\Enums;

enum Availability: string
{
    case Available = 'available';
    case Busy = 'busy';
    case Offline = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Busy => 'Occupé',
            self::Offline => 'Indisponible',
        };
    }

    public function dot(): string
    {
        return match ($this) {
            self::Available => 'bg-emerald-500',
            self::Busy => 'bg-amber-500',
            self::Offline => 'bg-red-500',
        };
    }
}
