<?php

namespace App\Models\Concerns;

use App\Models\Lock;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Élément verrouillable depuis l'admin. Le verrou bloque les actions d'administration
 * (middleware « unlocked ») ; il ne gêne pas l'utilisateur concerné ni les traitements automatiques.
 */
trait Lockable
{
    public function lock(): MorphOne
    {
        return $this->morphOne(Lock::class, 'lockable');
    }

    public function isLocked(): bool
    {
        return $this->lock !== null;
    }
}
