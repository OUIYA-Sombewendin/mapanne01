<?php

namespace App\Enums;

enum ProviderStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente de validation',
            self::Approved => 'Validé',
            self::Rejected => 'Refusé',
            self::Suspended => 'Suspendu',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800',
            self::Approved => 'bg-emerald-100 text-emerald-800',
            self::Rejected => 'bg-red-100 text-red-800',
            self::Suspended => 'bg-slate-200 text-slate-700',
        };
    }
}
