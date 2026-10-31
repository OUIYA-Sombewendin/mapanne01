<?php

namespace App\Enums;

enum VehicleType: string
{
    case Car = 'voiture';
    case Motorbike = 'moto';
    case Tricycle = 'tricycle';
    case Truck = 'camion';

    public function label(): string
    {
        return match ($this) {
            self::Car => 'Voiture',
            self::Motorbike => 'Moto',
            self::Tricycle => 'Tricycle',
            self::Truck => 'Camion',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
