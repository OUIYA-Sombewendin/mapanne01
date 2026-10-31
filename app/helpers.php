<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /** Valeur d'un réglage modifiable depuis l'administration. */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('feature')) {
    /** Une option activable depuis l'administration est-elle activée ? */
    function feature(string $key): bool
    {
        return (bool) Setting::get($key, false);
    }
}
