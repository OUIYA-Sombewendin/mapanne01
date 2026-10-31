<?php

namespace App\Enums;

enum UserRole: string
{
    case Client = 'client';
    case Provider = 'provider';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Client',
            self::Provider => 'Prestataire',
            self::Admin => 'Administrateur',
        };
    }
}
